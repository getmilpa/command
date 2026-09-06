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

/** A result whose constructor argument is NOT promoted — not part of the output contract. */
final class Wrapped
{
    public function __construct(string $hidden)
    {
        $this->shown = $hidden;
    }

    public string $shown = '';
}
