<?php

namespace Tests\Unit;

use App\Models\Server;
use App\Protocols\ClashMeta;
use App\Protocols\General;
use App\Protocols\Shadowrocket;
use App\Protocols\SingBox;
use App\Protocols\Stash;
use App\Support\AbstractProtocol;
use Illuminate\Http\Request;
use ReflectionClass;
use Tests\TestCase;

class ProtocolCompatibilityTest extends TestCase
{
    public function test_mihomo_user_agent_selects_clash_meta_generator(): void
    {
        $class = app('protocols.manager')->matchProtocolClassName('mihomo/1.19.10');

        $this->assertSame(ClashMeta::class, $class);
    }

    public function test_plain_proxy_group_names_are_not_treated_as_regular_expressions(): void
    {
        $protocol = new class([], []) extends AbstractProtocol {
            public function handle()
            {
                return null;
            }

            public function regex(mixed $value): bool
            {
                return $this->isRegex($value);
            }
        };

        $this->assertFalse($protocol->regex('自动选择'));
        $this->assertFalse($protocol->regex('DIRECT'));
        $this->assertTrue($protocol->regex('/香港|HK/iu'));
    }

    public function test_clash_meta_reality_node_gets_a_fingerprint_fallback(): void
    {
        $server = [
            'name' => 'Reality node',
            'host' => 'node.example.com',
            'port' => 443,
            'type' => Server::TYPE_VLESS,
            'protocol_settings' => [
                'network' => 'tcp',
                'tls' => 2,
                'reality_settings' => [
                    'server_name' => 'www.example.com',
                    'public_key' => 'test-public-key',
                    'short_id' => '0123456789abcdef',
                ],
            ],
        ];
        $uuid = '00000000-0000-4000-8000-000000000000';
        $proxy = ClashMeta::buildVless($uuid, $server);

        $this->assertSame('chrome', $proxy['client-fingerprint']);
        $this->assertStringContainsString('fp=chrome', General::buildVless($uuid, $server));
        $this->assertStringContainsString('fp=chrome', Shadowrocket::buildVless($uuid, $server));

        $stash = new Stash([], [$server], 'stash', '1.0.0', 'Stash/1.0.0');
        $this->assertSame('chrome', $stash->buildVless($uuid, $server)['client-fingerprint']);
    }

    public function test_clash_meta_adds_node_dns_and_direct_rules_dynamically(): void
    {
        app()->instance('request', Request::create('https://panel.example.com/subscription'));
        $protocol = new ClashMeta([], [[
            'name' => 'Reality node',
            'type' => Server::TYPE_VLESS,
            'host' => 'node.example.com',
            'port' => 443,
            'protocol_settings' => ['network' => 'tcp', 'tls' => 2],
        ]], 'mihomo', '1.19.10', 'mihomo/1.19.10');

        $config = $protocol->buildRules([
            'dns' => ['fake-ip-filter' => ['*.lan']],
            'rules' => ['MATCH,Proxy'],
        ]);

        $this->assertSame(['223.5.5.5', '119.29.29.29'], $config['dns']['proxy-server-nameserver']);
        $this->assertContains('node.example.com', $config['dns']['fake-ip-filter']);
        $this->assertContains('panel.example.com', $config['dns']['fake-ip-filter']);
        $this->assertContains('DOMAIN,node.example.com,DIRECT', $config['rules']);
        $this->assertContains('DOMAIN,panel.example.com,DIRECT', $config['rules']);
    }

    public function test_hiddify_receives_modern_sing_box_schema_from_legacy_template(): void
    {
        $protocol = new SingBox([], [], 'hiddify', '4.0.0', 'Hiddify/4.0.0');
        $legacyConfig = [
            'dns' => [
                'servers' => [
                    ['tag' => 'remote', 'address' => 'https://1.1.1.1/dns-query'],
                    ['tag' => 'local', 'address' => 'https://223.5.5.5/dns-query'],
                ],
                'rules' => [['outbound' => ['any'], 'server' => 'local']],
            ],
            'inbounds' => [[
                'type' => 'tun',
                'address' => ['172.19.0.1/30'],
                'sniff' => true,
                'sniff_override_destination' => true,
                'domain_strategy' => 'prefer_ipv4',
                'endpoint_independent_nat' => true,
            ]],
            'outbounds' => [
                ['tag' => 'direct', 'type' => 'direct'],
                ['tag' => 'block', 'type' => 'block'],
                ['tag' => 'dns-out', 'type' => 'dns'],
            ],
            'route' => [
                'rules' => [
                    ['protocol' => 'dns', 'outbound' => 'dns-out'],
                    ['domain_suffix' => ['ads.example'], 'outbound' => 'block'],
                ],
            ],
        ];

        $reflection = new ReflectionClass($protocol);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($protocol, $legacyConfig);
        $reflection->getMethod('adaptConfigForVersion')->invoke($protocol);
        $config = $configProperty->getValue($protocol);

        $this->assertSame('https', $config['dns']['servers'][0]['type']);
        $this->assertArrayNotHasKey('address', $config['dns']['servers'][0]);
        $this->assertSame('local', $config['route']['default_domain_resolver']['server']);
        $this->assertNotContains('block', array_column($config['outbounds'], 'type'));
        $this->assertNotContains('dns', array_column($config['outbounds'], 'type'));
        $this->assertArrayNotHasKey('sniff', $config['inbounds'][0]);
        $this->assertArrayNotHasKey('domain_strategy', $config['inbounds'][0]);
        $this->assertSame('sniff', $config['route']['rules'][0]['action']);
        $this->assertSame('hijack-dns', $config['route']['rules'][1]['action']);
        $this->assertSame('reject', $config['route']['rules'][2]['action']);
    }
}
