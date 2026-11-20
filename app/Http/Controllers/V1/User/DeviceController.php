<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionDeviceService;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function __construct(private readonly SubscriptionDeviceService $deviceService)
    {
    }

    public function fetch(Request $request)
    {
        return $this->success($this->deviceService->getUserDevices($request->user()->id));
    }

    public function traffic(Request $request)
    {
        $data = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        return $this->success($this->deviceService->getUserTraffic(
            $request->user()->id,
            (int) ($data['days'] ?? 30)
        ));
    }

    public function offline(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'integer', 'min:1'],
        ]);

        return $this->success($this->deviceService->forceOffline(
            $request->user()->id,
            (int) $data['device_id']
        ));
    }
}
