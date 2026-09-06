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

/** Returns a result whose only constructor argument is not promoted. */
#[Operation(name: 'lab:wrap', description: 'Returns a wrapped object.')]
#[Reads]
final class Wraps
{
    public function run(): Wrapped
    {
        return new Wrapped('x');
    }
}
