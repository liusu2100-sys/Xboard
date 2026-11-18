<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class DeviceStateService
{
    private const PREFIX = 'user_devices:';
    private const NODE_INDEX_PREFIX = 'node_device_users:';
    private const TTL = 300;                     // device state ttl
    private const DB_THROTTLE = 10;             // update db throttle

    /**
     * 批量设置设备
     * 用于 HTTP /alive 和 WebSocket report.devices
     */
    public function setDevices(int $userId, int $nodeId, array $ips): void
    {
        $key = self::PREFIX . $userId;
        $timestamp = time();

        $this->removeNodeDevices($nodeId, $userId, false);

        // Persist richer device metadata as a side channel. Any failure here
        // must not interrupt the legacy Redis device-limit implementation.
        try {
            $ips = app(SubscriptionDeviceService::class)->recordAlive($userId, $nodeId, $ips);
        } catch (\Throwable $e) {
            Log::warning('Subscription device report could not be persisted', [
                'user_id' => $userId,
                'node_id' => $nodeId,
                'error' => $e->getMessage(),
            ]);
            $ips = $this->extractLegacyIps($ips);
        }

        if (!empty($ips)) {
            $fields = [];
            foreach ($ips as $ip) {
                $fields["{$nodeId}:{$ip}"] = $timestamp;
            }
            Redis::hMset($key, $fields);
            Redis::expire($key, self::TTL);
            Redis::sadd(self::NODE_INDEX_PREFIX . $nodeId, $userId);
            Redis::expire(self::NODE_INDEX_PREFIX . $nodeId, self::TTL * 2);
        } else {
            Redis::srem(self::NODE_INDEX_PREFIX . $nodeId, $userId);
        }

        $this->notifyUpdate($userId);
    }

    /**
     * 获取某节点的所有设备数据
     * 返回: {userId: [ip1, ip2, ...], ...}
     */
    public function getNodeDevices(int $nodeId): array
    {
        $userIds = Redis::smembers(self::NODE_INDEX_PREFIX . $nodeId);
        $prefix = "{$nodeId}:";
        $result = [];
        foreach ($userIds as $userId) {
            $uid = (int) $userId;
            $data = Redis::hgetall(self::PREFIX . $uid);
            foreach ($data as $field => $timestamp) {
                if (str_starts_with($field, $prefix)) {
                    $ip = substr($field, strlen($prefix));
                    $result[$uid][] = $ip;
                }
            }
            if (!isset($result[$uid])) {
                Redis::srem(self::NODE_INDEX_PREFIX . $nodeId, $uid);
            }
        }

        return $result;
    }

    /**
     * 删除某节点某用户的设备
     */
    public function removeNodeDevices(int $nodeId, int $userId, bool $persistOffline = true): void
    {
        $key = self::PREFIX . $userId;
        $prefix = "{$nodeId}:";

        foreach (Redis::hkeys($key) as $field) {
            if (str_starts_with($field, $prefix)) {
                Redis::hdel($key, $field);
            }
        }
        Redis::srem(self::NODE_INDEX_PREFIX . $nodeId, $userId);

        if ($persistOffline) {
            try {
                app(SubscriptionDeviceService::class)->markUserNodeOffline($userId, $nodeId);
            } catch (\Throwable $e) {
                Log::debug('Subscription device presence cleanup skipped', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * 清除节点所有设备数据（用于节点断开连接）
     */
    public function clearAllNodeDevices(int $nodeId): array
    {
        $oldDevices = $this->getNodeDevices($nodeId);
        foreach ($oldDevices as $userId => $ips) {
            $this->removeNodeDevices($nodeId, $userId, false);
        }
        Redis::del(self::NODE_INDEX_PREFIX . $nodeId);

        try {
            app(SubscriptionDeviceService::class)->markNodeOffline($nodeId);
        } catch (\Throwable $e) {
            Log::debug('Subscription node presence cleanup skipped', ['error' => $e->getMessage()]);
        }

        return array_keys($oldDevices);
    }

    /**
     * get user device count (deduplicated by IP, filter expired data)
     */
    public function getDeviceCount(int $userId): int
    {
        $data = Redis::hgetall(self::PREFIX . $userId);
        $now = time();
        $ips = [];

        foreach ($data as $field => $timestamp) {
            if ($now - (int) $timestamp <= self::TTL) {
                $ips[] = substr($field, strpos($field, ':') + 1);
            }
        }

        return count(array_unique($ips));
    }

    /**
     * get user device count (for alivelist interface)
     */
    public function getAliveList(Collection $users): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $result = [];
        foreach ($users as $user) {
            $count = $this->getDeviceCount($user->id);
            if ($count > 0) {
                $result[$user->id] = $count;
            }
        }

        return $result;
    }

    /**
     * get devices of multiple users (for sync.devices, filter expired data)
     */
    public function getUsersDevices(array $userIds): array
    {
        $result = [];
        $now = time();
        foreach ($userIds as $userId) {
            $data = Redis::hgetall(self::PREFIX . $userId);
            if (!empty($data)) {
                $ips = [];
                foreach ($data as $field => $timestamp) {
                    if ($now - (int) $timestamp <= self::TTL) {
                        $ips[] = substr($field, strpos($field, ':') + 1);
                    }
                }
                if (!empty($ips)) {
                    $result[$userId] = array_unique($ips);
                }
            }
        }

        return $result;
    }

    /**
     * notify update (throttle control)
     */
    public function notifyUpdate(int $userId, bool $force = false): void
    {
        $dbThrottleKey = "device:db_throttle:{$userId}";

        if ($force || Cache::add($dbThrottleKey, 1, self::DB_THROTTLE)) {
            User::query()
                ->whereKey($userId)
                ->update([
                    'online_count' => $this->getDeviceCount($userId),
                    'last_online_at' => now(),
                ]);
        }
    }

    private function extractLegacyIps(array $devices): array
    {
        $ips = [];
        foreach ($devices as $device) {
            $ip = is_array($device) ? ($device['ip'] ?? $device['address'] ?? null) : $device;
            if (is_string($ip) && filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                $ips[] = trim($ip);
            }
        }
        return array_values(array_unique($ips));
    }
}
