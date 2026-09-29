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

/** Needs nothing from the container — only to know who is asking. */
#[Operation(name: 'lab:whoami', description: 'Say who is asking.')]
#[Reads]
final readonly class AttributedAlone
{
    /** @return array<string, mixed> */
    public function run(?InvocationContext $context): array
    {
        return ['actor' => $context?->actor];
    }
}
