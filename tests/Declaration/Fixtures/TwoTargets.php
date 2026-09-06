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
use Milpa\Command\Declaration\Target;

/** Two named targets — ambiguity wearing the costume of rigour. */
#[Operation(name: 'two:targets', description: 'Names two targets.')]
#[Reads]
final readonly class TwoTargets
{
    public function __construct(
        #[Target]
        public string $first,
        #[Target]
        public string $second,
    ) {
    }

    /** @return array<string, mixed> */
    public function run(): array
    {
        return [];
    }
}
