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

/** The collaborator run() works through — injected, never part of the input contract. */
final class Greetings
{
    /** @var list<string> */
    public array $written = [];

    public function write(string $name): string
    {
        $this->written[] = $name;

        return 'var/greetings.txt';
    }
}
