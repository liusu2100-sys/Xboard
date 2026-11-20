<?php

namespace App\Services;

use App\Models\Server;
use App\Models\DeviceTrafficReport;
use App\Models\StatDevice;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserDevicePresence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class SubscriptionDeviceService
{
    private const REDIS_PREFIX = 'user_devices:';
    private const PRESENCE_TTL = 300;
    private const OFFLINE_BLOCK_SECONDS = 600;
    private const MAX_TRAFFIC_ENTRIES = 10000;
    private const SUBSCRIPTION_WRITE_TTL = 60;

    public function __construct(private readonly IpLocationService $ipLocationService)
    {
    }

    /**
     * Subscription downloads are the earliest point where the panel can see
     * client metadata. A node report can enrich this record later.
     */
    public function observeSubscriptionAccess(User $user, Request $request, array $clientInfo = []): void
    {
        $userAgent = (string) $request->userAgent();
        $ip = $this->resolveRequestIp($request);
        $platform = $this->detectPlatform($userAgent);
        $model = $this->detectModel($userAgent, $platform);
        $clientName = $clientInfo['name'] ?? $this->detectClientName($userAgent);
        $externalId = trim((string) $request->header('X-Device-ID', $request->input('device_id', '')));
        $name = implode(' / ', array_values(array_filter([$clientName, $model ?: $platform])));

        $observationKey = $externalId !== '' ? 'id:' . $externalId : 'ip:' . ($ip ?: 'unknown');
        if (!Cache::add(
            'device:subscription_observed:' . $user->id . ':' . hash('sha256', $observationKey),
            1,
            self::SUBSCRIPTION_WRITE_TTL
        )) {
            return;
        }

        $device = $this->observeDevice($user->id, [
            'device_id' => $externalId ?: null,
            'ip' => $ip,
            'name' => $name ?: null,
            'model' => $model,
            'client_name' => $clientName,
            'client_version' => $clientInfo['version'] ?? null,
            'platform' => $platform,
            'user_agent' => $userAgent,
            'source' => $externalId ? 'client_id' : 'subscription_ip',
        ]);

        if ($device) {
            $device->forceFill(['last_subscription_at' => time()])->save();
        }
    }

    /**
     * Persist a node alive report and return the IP list allowed into the
     * legacy Redis device counter. Legacy input is ["1.2.3.4"]. Extended
     * input is [{device_id, ip, name, model, client_name, platform, user_agent}].
     */
    public function recordAlive(int $userId, int $nodeId, array $reportedDevices): array
    {
        $now = time();
        $activeDeviceIds = [];
        $allowedIps = [];

        foreach ($this->normalizeDeviceList($reportedDevices) as $descriptor) {
            $device = $this->observeDevice($userId, $descriptor, $nodeId);
            if (!$device) {
                continue;
            }

            $activeDeviceIds[] = $device->id;
            if ($this->isTemporarilyBlocked($device)) {
                $this->pushKickCommand($device, [$nodeId]);
                continue;
            }

            UserDevicePresence::query()->upsert([[
                'device_id' => $device->id,
                'server_id' => $nodeId,
                'user_id' => $userId,
                'ip' => $device->last_ip,
                'is_online' => true,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'last_offline_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['device_id', 'server_id'], [
                'user_id', 'ip', 'is_online', 'last_seen_at', 'last_offline_at', 'updated_at',
            ]);

            if ($device->last_ip) {
                $allowedIps[] = $device->last_ip;
            }
        }

        $stale = UserDevicePresence::query()
            ->where('user_id', $userId)
            ->where('server_id', $nodeId)
            ->where('is_online', true);
        if ($activeDeviceIds) {
            $stale->whereNotIn('device_id', $activeDeviceIds);
        }
        $stale->update([
            'is_online' => false,
            'last_offline_at' => $now,
            'updated_at' => $now,
        ]);

        return array_values(array_unique($allowedIps));
    }

    public function markUserNodeOffline(int $userId, int $nodeId): void
    {
        $now = time();
        UserDevicePresence::query()
            ->where('user_id', $userId)
            ->where('server_id', $nodeId)
            ->where('is_online', true)
            ->update([
                'is_online' => false,
                'last_offline_at' => $now,
                'updated_at' => $now,
            ]);
    }

    public function markNodeOffline(int $nodeId): void
    {
        $now = time();
        UserDevicePresence::query()
            ->where('server_id', $nodeId)
            ->where('is_online', true)
            ->update([
                'is_online' => false,
                'last_offline_at' => $now,
                'updated_at' => $now,
            ]);
    }

    /**
     * Device traffic is analytics only. It never increments v2_user traffic,
     * because the existing aggregate traffic report remains the billing source.
     */
    public function recordTraffic(Server $server, array $payload, ?string $reportId = null): bool
    {
        $entryCount = $this->countTrafficEntries($payload);
        if ($entryCount > self::MAX_TRAFFIC_ENTRIES) {
            throw new \InvalidArgumentException('Device traffic report is too large');
        }

        $rate = (float) $server->getCurrentRate();
        $recordAt = strtotime(date('Y-m-d'));
        $now = time();

        if ($reportId !== null && trim($reportId) !== '') {
            $reportHash = hash('sha256', trim($reportId));

            return DB::transaction(function () use ($server, $payload, $reportHash, $entryCount, $rate, $recordAt, $now) {
                $inserted = DeviceTrafficReport::query()->insertOrIgnore([[
                    'server_id' => $server->id,
                    'report_id' => $reportHash,
                    'record_count' => $entryCount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]]);
                if (!$inserted) {
                    return false;
                }

                $this->applyTrafficPayload($server, $payload, $rate, $recordAt, $now);
                return true;
            }, 3);
        }

        $this->applyTrafficPayload($server, $payload, $rate, $recordAt, $now);
        return true;
    }

    private function applyTrafficPayload(
        Server $server,
        array $payload,
        float $rate,
        int $recordAt,
        int $now
    ): void {

        foreach ($payload as $userId => $entries) {
            if (!is_numeric($userId) || !is_array($entries)) {
                continue;
            }

            foreach ($this->normalizeDeviceList($entries) as $entry) {
                $u = max(0, (int) ($entry['u'] ?? 0));
                $d = max(0, (int) ($entry['d'] ?? 0));
                if ($u === 0 && $d === 0) {
                    continue;
                }

                $device = $this->observeDevice((int) $userId, $entry, $server->id);
                if (!$device) {
                    continue;
                }

                $chargedU = (int) floor($u * $rate);
                $chargedD = (int) floor($d * $rate);

                UserDevice::query()->whereKey($device->id)->incrementEach([
                    'u' => $u,
                    'd' => $d,
                    'charged_u' => $chargedU,
                    'charged_d' => $chargedD,
                ], [
                    'traffic_updated_at' => $now,
                    'last_seen_at' => $now,
                    'last_server_id' => $server->id,
                ]);

                StatDevice::query()->insertOrIgnore([[
                    'device_id' => $device->id,
                    'server_id' => $server->id,
                    'server_rate' => $rate,
                    'record_at' => $recordAt,
                    'user_id' => $device->user_id,
                    'u' => 0,
                    'd' => 0,
                    'charged_u' => 0,
                    'charged_d' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]]);
                StatDevice::query()
                    ->where('device_id', $device->id)
                    ->where('server_id', $server->id)
                    ->where('server_rate', $rate)
                    ->where('record_at', $recordAt)
                    ->incrementEach([
                        'u' => $u,
                        'd' => $d,
                        'charged_u' => $chargedU,
                        'charged_d' => $chargedD,
                    ], ['updated_at' => $now]);
            }
        }
    }

    private function countTrafficEntries(array $payload): int
    {
        $count = 0;
        foreach ($payload as $entries) {
            if (is_array($entries)) {
                $count += $this->looksLikeDescriptor($entries) ? 1 : count($entries);
            }
            if ($count > self::MAX_TRAFFIC_ENTRIES) {
                break;
            }
        }
        return $count;
    }

    public function getUserDevices(int $userId): array
    {
        $now = time();
        $this->expireStalePresence($userId, $now);
        $monthStart = now()->startOfMonth()->timestamp;
        $monthly = StatDevice::query()
            ->selectRaw('device_id, SUM(u) AS u, SUM(d) AS d, SUM(charged_u) AS charged_u, SUM(charged_d) AS charged_d')
            ->where('user_id', $userId)
            ->where('record_at', '>=', $monthStart)
            ->groupBy('device_id')
            ->get()
            ->keyBy('device_id');

        return UserDevice::query()
            ->where('user_id', $userId)
            ->with([
                'server:id,name,type',
                'presences' => fn($query) => $query->with('server:id,name,type')->orderByDesc('last_seen_at'),
            ])
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(function (UserDevice $device) use ($monthly, $now) {
                $active = $device->presences->filter(
                    fn(UserDevicePresence $presence) => $presence->is_online
                        && $now - $presence->last_seen_at <= self::PRESENCE_TTL
                );
                $month = $monthly->get($device->id);
                $blocked = $this->isTemporarilyBlocked($device);

                return [
                    'id' => $device->id,
                    'name' => $device->name ?: ($device->model ?: ($device->client_name ?: '未知订阅设备')),
                    'model' => $device->model,
                    'client_name' => $device->client_name,
                    'client_version' => $device->client_version,
                    'platform' => $device->platform,
                    'ip' => $device->last_ip,
                    'region' => $device->region,
                    'identity_source' => $device->identity_source,
                    'first_seen_at' => $device->first_seen_at,
                    'last_seen_at' => $device->last_seen_at,
                    'last_subscription_at' => $device->last_subscription_at,
                    'is_online' => !$blocked && $active->isNotEmpty(),
                    'blocked_until' => $blocked ? $device->blocked_until : null,
                    'nodes' => $active->map(fn(UserDevicePresence $presence) => [
                        'id' => $presence->server_id,
                        'name' => $presence->server?->name ?: '节点 #' . $presence->server_id,
                        'type' => $presence->server?->type,
                        'ip' => $presence->ip,
                    ])->values()->all(),
                    'last_node' => $device->server?->name,
                    'traffic_supported' => !empty($device->traffic_updated_at),
                    'traffic' => [
                        'u' => $device->u,
                        'd' => $device->d,
                        'charged_u' => $device->charged_u,
                        'charged_d' => $device->charged_d,
                        'month_u' => (int) ($month->u ?? 0),
                        'month_d' => (int) ($month->d ?? 0),
                        'month_charged_u' => (int) ($month->charged_u ?? 0),
                        'month_charged_d' => (int) ($month->charged_d ?? 0),
                        'updated_at' => $device->traffic_updated_at,
                    ],
                ];
            })
            ->all();
    }

    public function getUserTraffic(int $userId, int $days = 30): array
    {
        $from = now()->subDays(max(1, min($days, 90)) - 1)->startOfDay()->timestamp;

        return StatDevice::query()
            ->where('user_id', $userId)
            ->where('record_at', '>=', $from)
            ->with(['device:id,name,model,client_name,platform', 'server:id,name,type'])
            ->orderByDesc('record_at')
            ->get()
            ->map(fn(StatDevice $stat) => [
                'id' => $stat->id,
                'device_id' => $stat->device_id,
                'device_name' => $stat->device?->name ?: ($stat->device?->model ?: '未知设备'),
                'server_id' => $stat->server_id,
                'server_name' => $stat->server?->name ?: '节点 #' . $stat->server_id,
                'server_rate' => $stat->server_rate,
                'u' => $stat->u,
                'd' => $stat->d,
                'charged_u' => $stat->charged_u,
                'charged_d' => $stat->charged_d,
                'record_at' => $stat->record_at,
            ])
            ->all();
    }

    public function forceOffline(int $userId, int $deviceId): array
    {
        $device = UserDevice::query()->where('user_id', $userId)->whereKey($deviceId)->firstOrFail();
        $blockedUntil = time() + self::OFFLINE_BLOCK_SECONDS;
        $presences = UserDevicePresence::query()
            ->where('device_id', $device->id)
            ->where('is_online', true)
            ->get();

        $device->forceFill(['blocked_until' => $blockedUntil])->save();
        foreach ($presences as $presence) {
            $presence->forceFill(['is_online' => false, 'last_offline_at' => time()])->save();
            if ($presence->ip) {
                Redis::hdel(self::REDIS_PREFIX . $userId, "{$presence->server_id}:{$presence->ip}");
            }
        }

        $nodeIds = $presences->pluck('server_id')->unique()->values()->all();
        if (!$nodeIds && $device->last_server_id) {
            $nodeIds = [$device->last_server_id];
        }
        $pushSupported = $this->pushKickCommand($device, $nodeIds);
        app(DeviceStateService::class)->notifyUpdate($userId, true);

        return [
            'device_id' => $device->id,
            'blocked_until' => $blockedUntil,
            'enforcement' => $pushSupported ? 'node_push' : 'panel_only',
            'message' => $pushSupported
                ? '下线指令已发送，设备将在 10 分钟内被阻止重新接入'
                : '设备已在面板标记下线；当前节点未连接控制通道，旧版节点可能仍需升级',
        ];
    }

    private function observeDevice(int $userId, array $descriptor, ?int $nodeId = null): ?UserDevice
    {
        $ip = DeviceStateService::normalizeReportedIp($descriptor['ip'] ?? $descriptor['address'] ?? null);
        $externalId = trim((string) ($descriptor['device_id'] ?? $descriptor['fingerprint'] ?? ''));
        if ($externalId === '' && !$ip) {
            return null;
        }

        $deviceKey = hash('sha256', $externalId !== '' ? 'id:' . $externalId : 'ip:' . $ip);
        $now = time();
        $device = UserDevice::query()->firstOrCreate(
            ['user_id' => $userId, 'device_key' => $deviceKey],
            [
                'identity_source' => $externalId !== '' ? 'device_id' : ($descriptor['source'] ?? 'ip'),
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'u' => 0,
                'd' => 0,
                'charged_u' => 0,
                'charged_d' => 0,
            ]
        );

        $oldIp = $device->last_ip;
        $userAgent = Str::limit((string) ($descriptor['user_agent'] ?? ''), 512, '');
        $platform = $descriptor['platform'] ?? $this->detectPlatform($userAgent);
        $model = $descriptor['model'] ?? $this->detectModel($userAgent, $platform);
        $client = $descriptor['client_name'] ?? $descriptor['client'] ?? $this->detectClientName($userAgent);

        $device->identity_source = $externalId !== '' ? 'device_id' : ($descriptor['source'] ?? 'ip');
        $device->last_seen_at = $now;
        $device->last_server_id = $nodeId ?: $device->last_server_id;
        $device->last_ip = $ip ?: $device->last_ip;
        if ($ip && $ip !== $oldIp) {
            $device->region = $this->ipLocationService->resolve($ip);
        }
        $device->name = $this->filledValue($descriptor['name'] ?? null, $device->name, 120);
        $device->model = $this->filledValue($model, $device->model, 120);
        $device->client_name = $this->filledValue($client, $device->client_name, 80);
        $device->client_version = $this->filledValue($descriptor['client_version'] ?? $descriptor['version'] ?? null, $device->client_version, 40);
        $device->platform = $this->filledValue($platform, $device->platform, 80);
        $device->user_agent = $this->filledValue($userAgent, $device->user_agent, 512);
        if (isset($descriptor['metadata']) && is_array($descriptor['metadata'])) {
            $device->metadata = $descriptor['metadata'];
        }
        $device->save();

        return $device;
    }

    private function normalizeDeviceList(array $devices): array
    {
        if ($this->looksLikeDescriptor($devices)) {
            $devices = [$devices];
        }

        $result = [];
        foreach ($devices as $key => $device) {
            if (is_string($device)) {
                $result[] = ['ip' => $device];
            } elseif (is_array($device)) {
                if (!$this->looksLikeDescriptor($device) && is_string($key)) {
                    $device['device_id'] = $key;
                }
                $result[] = $device;
            }
        }
        return $result;
    }

    private function looksLikeDescriptor(array $value): bool
    {
        return array_intersect(
            ['device_id', 'fingerprint', 'ip', 'address', 'u', 'd', 'model', 'client_name'],
            array_keys($value)
        ) !== [];
    }

    private function pushKickCommand(UserDevice $device, array $nodeIds): bool
    {
        $pushed = false;
        foreach (array_unique(array_filter($nodeIds)) as $nodeId) {
            if (!NodeSyncService::isNodeOnline((int) $nodeId)) {
                continue;
            }
            $pushed = true;
            if (!Cache::add("device:kick:{$device->id}:{$nodeId}", 1, 10)) {
                continue;
            }
            NodeSyncService::push((int) $nodeId, 'device.kick', [
                'user_id' => $device->user_id,
                'device_key' => $device->device_key,
                'ip' => $device->last_ip,
                'blocked_until' => $device->blocked_until,
            ]);
        }
        return $pushed;
    }

    private function expireStalePresence(int $userId, int $now): void
    {
        UserDevicePresence::query()
            ->where('user_id', $userId)
            ->where('is_online', true)
            ->where('last_seen_at', '<', $now - self::PRESENCE_TTL)
            ->update(['is_online' => false, 'last_offline_at' => $now, 'updated_at' => $now]);
    }

    private function isTemporarilyBlocked(UserDevice $device): bool
    {
        return !empty($device->blocked_until) && $device->blocked_until > time();
    }

    private function resolveRequestIp(Request $request): ?string
    {
        foreach (['CF-Connecting-IP', 'X-Real-IP'] as $header) {
            if ($ip = $this->validIp($request->header($header))) {
                return $ip;
            }
        }
        foreach (explode(',', (string) $request->header('X-Forwarded-For', '')) as $candidate) {
            if ($ip = $this->validIp($candidate)) {
                return $ip;
            }
        }
        return $this->validIp($request->ip());
    }

    private function validIp(mixed $value): ?string
    {
        $value = trim((string) $value);
        return filter_var($value, FILTER_VALIDATE_IP) ? $value : null;
    }

    private function detectPlatform(string $userAgent): ?string
    {
        return match (true) {
            str_contains($userAgent, 'iPhone') => 'iOS',
            str_contains($userAgent, 'iPad') => 'iPadOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Macintosh') || str_contains($userAgent, 'Mac OS X') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };
    }

    private function detectModel(string $userAgent, ?string $platform): ?string
    {
        if ($platform === 'iOS') return 'iPhone';
        if ($platform === 'iPadOS') return 'iPad';
        if ($platform === 'Windows') return 'Windows PC';
        if ($platform === 'macOS') return 'Mac';
        if ($platform === 'Android' && preg_match('/Android[^;]*;\s*([^;)]+?)(?:\s+Build\/|\))/i', $userAgent, $match)) {
            return trim($match[1]);
        }
        return $platform;
    }

    private function detectClientName(string $userAgent): ?string
    {
        foreach (['Hiddify', 'FlClash', 'ClashMeta', 'Clash', 'Stash', 'Shadowrocket', 'Surge', 'Loon', 'NekoBox', 'v2rayNG', 'sing-box', 'Surfboard'] as $name) {
            if (stripos($userAgent, $name) !== false) {
                return $name;
            }
        }
        return null;
    }

    private function filledValue(mixed $newValue, mixed $oldValue, int $limit): mixed
    {
        return filled($newValue) ? Str::limit((string) $newValue, $limit, '') : $oldValue;
    }
}
