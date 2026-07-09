<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Ipv4Range;
use PHPUnit\Framework\TestCase;

class Ipv4RangeTest extends TestCase
{
    public function test_it_derives_the_prefix_from_a_netmask(): void
    {
        $this->assertSame(24, Ipv4Range::prefixFromNetmask('255.255.255.0'));
        $this->assertSame(16, Ipv4Range::prefixFromNetmask('255.255.0.0'));
        $this->assertSame(25, Ipv4Range::prefixFromNetmask('255.255.255.128'));
        $this->assertSame(0, Ipv4Range::prefixFromNetmask('0.0.0.0'));
    }

    public function test_it_rejects_an_invalid_netmask(): void
    {
        $this->assertNull(Ipv4Range::prefixFromNetmask('255.0.255.0'));
        $this->assertNull(Ipv4Range::prefixFromNetmask('not-a-mask'));
    }

    public function test_hosts_of_a_24_exclude_network_broadcast_and_self(): void
    {
        $hosts = Ipv4Range::hosts('192.168.1.50', 24);

        $this->assertCount(253, $hosts); // 254 usable minus the machine itself
        $this->assertContains('192.168.1.1', $hosts);
        $this->assertContains('192.168.1.254', $hosts);
        $this->assertNotContains('192.168.1.0', $hosts);
        $this->assertNotContains('192.168.1.255', $hosts);
        $this->assertNotContains('192.168.1.50', $hosts);
    }

    public function test_a_wide_subnet_is_narrowed_around_the_host(): void
    {
        $hosts = Ipv4Range::hosts('10.0.5.20', 8, 512);

        // /8 is narrowed to /23 (510 usable) around the host.
        $this->assertLessThanOrEqual(512, count($hosts));
        $this->assertCount(509, $hosts);
        $this->assertContains('10.0.4.1', $hosts);
        $this->assertContains('10.0.5.254', $hosts);
        $this->assertNotContains('10.0.5.20', $hosts);
    }
}
