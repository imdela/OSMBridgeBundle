<?php

declare(strict_types=1);

namespace Ossm\OssmBridgeBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class OssmBridgeBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
