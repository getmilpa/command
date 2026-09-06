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

/** A union type: PHP knows two answers and a schema needs one. */
#[Operation(name: 'bad:union', description: 'Takes a union.')]
#[Reads]
final readonly class Untyped
{
    public function __construct(public int|string $id)
    {
    }

    /** @return array<string, mixed> */
    public function run(): array
    {
        return [];
    }
}
