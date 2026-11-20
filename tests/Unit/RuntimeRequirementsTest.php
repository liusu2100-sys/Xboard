<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class RuntimeRequirementsTest extends TestCase
{
    public function test_supported_php_runtime_and_dependency_lock_are_present(): void
    {
        $this->assertTrue(version_compare(PHP_VERSION, '8.2.0', '>='));
        $this->assertFileExists(dirname(__DIR__, 2) . '/composer.lock');
    }
}
