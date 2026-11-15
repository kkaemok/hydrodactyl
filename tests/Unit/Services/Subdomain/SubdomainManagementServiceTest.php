<?php

namespace Pterodactyl\Tests\Unit\Services\Subdomain;

use ReflectionMethod;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Tests\TestCase;
use Pterodactyl\Services\Subdomain\SubdomainManagementService;

class SubdomainManagementServiceTest extends TestCase
{
    public function testUsesPrimaryAllocationAliasEvenWhenNodeTrustAliasIsDisabled(): void
    {
        $records = [
            ['type' => 'A', 'name' => 'play', 'content' => '10.0.0.227'],
            ['type' => 'SRV', 'name' => '_minecraft._tcp.play', 'content' => ['port' => 25570]],
        ];

        $normalized = $this->normalize($this->server('203.0.113.20', false), $records);

        $this->assertSame('203.0.113.20', $normalized[0]['content']);
        $this->assertSame($records[1], $normalized[1], 'SRV records must be preserved.');
    }

    public function testKeepsAllocationAddressWhenAliasIsMissing(): void
    {
        $records = [['type' => 'A', 'name' => 'play', 'content' => '10.0.0.227']];

        $normalized = $this->normalize($this->server(null, true), $records);

        $this->assertSame('10.0.0.227', $normalized[0]['content']);
    }

    public function testRejectsNonIpv4AliasesRatherThanPublishingThePrivateIp(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->normalize(
            $this->server('server.example.com', false),
            [['type' => 'A', 'name' => 'play', 'content' => '10.0.0.227']],
        );
    }

    private function server(?string $alias, bool $trustAlias): Server
    {
        $server = new Server();
        $server->setRelation('allocation', new Allocation([
            'ip' => '10.0.0.227',
            'ip_alias' => $alias,
            'port' => 25570,
        ]));
        $server->setRelation('node', new Node(['trust_alias' => $trustAlias]));

        return $server;
    }

    private function normalize(Server $server, array $records): array
    {
        return (new ReflectionMethod(SubdomainManagementService::class, 'normalizeIpAddresses'))
            ->invoke(new SubdomainManagementService(), $records, $server);
    }
}
