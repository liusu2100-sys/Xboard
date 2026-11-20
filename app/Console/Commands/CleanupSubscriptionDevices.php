<?php

namespace App\Console\Commands;

use App\Models\DeviceTrafficReport;
use App\Models\StatDevice;
use App\Models\UserDevice;
use App\Models\UserDevicePresence;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupSubscriptionDevices extends Command
{
    protected $signature = 'device:cleanup {--device-days=180} {--traffic-days=180}';
    protected $description = '清理过期的订阅设备、在线状态和设备流量统计';

    public function handle(): int
    {
        $deviceDays = max(30, (int) $this->option('device-days'));
        $trafficDays = max(30, (int) $this->option('traffic-days'));
        $deviceCutoff = now()->subDays($deviceDays)->timestamp;
        $trafficCutoff = now()->subDays($trafficDays)->startOfDay()->timestamp;

        $statCount = StatDevice::query()->where('record_at', '<', $trafficCutoff)->delete();
        $reportCount = DeviceTrafficReport::query()->where('created_at', '<', $trafficCutoff)->delete();
        $deviceCount = 0;

        UserDevice::query()
            ->where('last_seen_at', '<', $deviceCutoff)
            ->where(function ($query) {
                $query->whereNull('blocked_until')->orWhere('blocked_until', '<', time());
            })
            ->whereDoesntHave('presences', fn($query) => $query->where('is_online', true))
            ->select('id')
            ->chunkById(200, function ($devices) use (&$deviceCount) {
                $ids = $devices->pluck('id')->all();
                DB::transaction(function () use ($ids) {
                    StatDevice::query()->whereIn('device_id', $ids)->delete();
                    UserDevicePresence::query()->whereIn('device_id', $ids)->delete();
                    UserDevice::query()->whereIn('id', $ids)->delete();
                });
                $deviceCount += count($ids);
            });

        $this->info("Removed {$deviceCount} devices, {$statCount} traffic rows and {$reportCount} report keys.");
        return self::SUCCESS;
    }
}
