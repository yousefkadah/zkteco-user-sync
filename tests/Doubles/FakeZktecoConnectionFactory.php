<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Models\Device;
use App\Services\Zkteco\ZktecoConnectionFactory;
use CodingLibs\ZktecoPhp\Libs\ZKTeco;

class FakeZktecoConnectionFactory extends ZktecoConnectionFactory
{
    public function __construct(public FakeZkteco $fake) {}

    public function make(Device $device): ZKTeco
    {
        return $this->fake;
    }
}
