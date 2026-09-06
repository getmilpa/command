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

/** A result object — its promoted properties ARE the output schema. */
final readonly class Receipt
{
    public function __construct(
        public string $id,
        public string $status,
        public int $revision,
    ) {
    }
}
