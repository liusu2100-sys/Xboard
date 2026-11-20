<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IpLocationService
{
    public function resolve(?string $ip): ?string
    {
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return '局域网/保留地址';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return 'IPv6 地址';
        }

        return Cache::remember('ip_region:' . $ip, now()->addDays(30), function () use ($ip) {
            try {
                $region = trim((string) (new \Ip2Region())->simple($ip));
                return $region !== '' ? $region : null;
            } catch (\Throwable $e) {
                Log::debug('Failed to resolve device IP region', [
                    'ip' => $ip,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }
}
