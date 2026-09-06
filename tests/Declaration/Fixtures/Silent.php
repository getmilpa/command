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

/** Declares an operation and says nothing about what it does — the lie the house refuses to accept. */
#[Operation(name: 'silent:op', description: 'Says nothing about what it does.')]
final class Silent
{
    /** @return array<string, mixed> */
    public function run(): array
    {
        return [];
    }
}
