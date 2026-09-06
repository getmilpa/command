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

/** A result whose promoted properties cover every derived output type. */
final readonly class Reading
{
    /** @param list<string> $tags */
    public function __construct(
        public float $ratio,
        public bool $dry,
        public array $tags,
        public string $priority,
    ) {
    }
}
