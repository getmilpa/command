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
use Milpa\Command\InvocationContext;

/** Assumes every surface attributes the call — MCP does not, so this is refused at declaration. */
#[Operation(name: 'lab:assumes', description: 'Assume someone is always there.')]
#[Reads]
final readonly class AttributedNonNullable
{
    /** @return array<string, mixed> */
    public function run(InvocationContext $context): array
    {
        return ['actor' => $context->actor];
    }
}
