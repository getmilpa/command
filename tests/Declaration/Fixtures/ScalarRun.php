<?php

/**
 * This file is part of Milpa Command — the Command-as-atom core of the Milpa PHP framework.
 *
 * (c) Rodrigo Vicente - TeamX Agency — https://teamx.agency <hola@teamx.agency>
 *
 * @license Apache-2.0
 *
 * @link    https://github.com/getmilpa/command
 */

declare(strict_types=1);

namespace Milpa\Command\Tests\Declaration\Fixtures;

use Milpa\Command\Declaration\Operation;
use Milpa\Command\Declaration\Reads;

/** Input smuggled into run() instead of the constructor. */
#[Operation(name: 'bad:run', description: 'Takes input in run().')]
#[Reads]
final class ScalarRun
{
    /** @return array<string, mixed> */
    public function run(string $id): array
    {
        return ['id' => $id];
    }
}
