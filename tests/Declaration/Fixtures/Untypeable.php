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

/** An input parameter no schema describes. */
#[Operation(name: 'bad:input', description: 'Takes a collaborator as input.')]
#[Reads]
final readonly class Untypeable
{
    public function __construct(public Greetings $greetings)
    {
    }

    /** @return array<string, mixed> */
    public function run(): array
    {
        return [];
    }
}
