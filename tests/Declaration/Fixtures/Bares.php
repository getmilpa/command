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

/** Returns a result with nothing to derive — the output schema is honestly null. */
#[Operation(name: 'lab:bare', description: 'Returns a bare object.')]
#[Reads]
final class Bares
{
    public function run(): Bare
    {
        return new Bare();
    }
}
