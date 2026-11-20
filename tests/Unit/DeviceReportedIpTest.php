<?php

namespace Tests\Unit;

use App\Services\DeviceStateService;
use PHPUnit\Framework\TestCase;

class DeviceReportedIpTest extends TestCase
{
    public function test_normalizes_legacy_endpoint_formats(): void
    {
        $this->assertSame('192.0.2.10', DeviceStateService::normalizeReportedIp('192.0.2.10:443'));
        $this->assertSame('2001:db8::1', DeviceStateService::normalizeReportedIp('[2001:db8::1]:443'));
        $this->assertSame('::ffff:192.0.2.10', DeviceStateService::normalizeReportedIp('[::ffff:192.0.2.10]:443'));
        $this->assertSame('2001:db8::443', DeviceStateService::normalizeReportedIp('2001:db8::443'));
        $this->assertNull(DeviceStateService::normalizeReportedIp('[::ffff:999.0.2.10]:443'));
    }
}
