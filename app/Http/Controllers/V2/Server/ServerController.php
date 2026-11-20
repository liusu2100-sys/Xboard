<?php

namespace App\Http\Controllers\V2\Server;

use App\Http\Controllers\Controller;
use App\Services\ServerService;
use App\Services\SubscriptionDeviceService;
use App\WebSocket\NodeWorker;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ServerController extends Controller
{
    /**
     * server handshake api
     */
    public function handshake(Request $request): JsonResponse
    {
        $websocket = ['enabled' => false];

        if ((bool) admin_setting('server_ws_enable', 1) && Cache::has(NodeWorker::HEARTBEAT_CACHE_KEY)) {
            $customUrl = trim((string) admin_setting('server_ws_url', ''));

            if ($customUrl !== '') {
                $wsUrl = rtrim($customUrl, '/');
            } else {
                $wsScheme = $request->isSecure() ? 'wss' : 'ws';
                $wsUrl = "{$wsScheme}://{$request->getHttpHost()}/ws";
            }

            $websocket = [
                'enabled' => true,
                'ws_url' => $wsUrl,
            ];
        }

        return response()->json([
            'websocket' => $websocket
        ]);
    }

    /**
     * node report api - merge traffic + alive + status + metrics
     */
    public function report(Request $request): JsonResponse
    {
        $node = $request->attributes->get('node_info');

        ServerService::touchNode($node);

        $traffic = $request->input('traffic');
        if (is_array($traffic) && !empty($traffic)) {
            ServerService::processTraffic($node, $traffic);
        }

        $alive = $request->input('alive');
        if (is_array($alive) && !empty($alive)) {
            ServerService::processAlive($node->id, $alive);
        }

        $online = $request->input('online');
        if (is_array($online) && !empty($online)) {
            ServerService::processOnline($node, $online);
        }

        // Optional per-device traffic deltas. Aggregate traffic above remains
        // the sole billing source, so this analytics channel cannot double bill.
        $deviceTraffic = $request->input('device_traffic');
        if (is_array($deviceTraffic) && !empty($deviceTraffic)) {
            try {
                app(SubscriptionDeviceService::class)->recordTraffic(
                    $node,
                    $deviceTraffic,
                    $request->filled('device_traffic_report_id')
                        ? (string) $request->input('device_traffic_report_id')
                        : null
                );
            } catch (\Throwable $e) {
                Log::warning('Device traffic report failed', [
                    'node_id' => $node->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $status = $request->input('status');
        if (is_array($status) && !empty($status)) {
            ServerService::processStatus($node, $status);
        }

        $metrics = $request->input('metrics');
        if (is_array($metrics) && !empty($metrics)) {
            ServerService::updateMetrics($node, $metrics);
        }

        return response()->json(['data' => true]);
    }
}
