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

use Milpa\Command\Declaration\Because;
use Milpa\Command\Declaration\Mutates;
use Milpa\Command\Declaration\Operation;
use Milpa\Command\Declaration\Target;
use Milpa\Command\Effect\Externality;
use Milpa\Command\Effect\Mutation;
use Milpa\Command\Effect\Reversibility;
use Milpa\Command\Effect\Subject;

/** The declared twin of the hand-written `hola:greet` — the byte-for-byte falsifier of decisions/0212. */
#[Operation(
    name: 'hola:greet',
    description: 'Write a greeting for a person by name into var/greetings.txt (appends one line). Reversible: remove the line.',
)]
#[Mutates(
    Mutation::Persistent,
    Externality::None,
    Reversibility::Guaranteed,
    subject: Subject::Data,
    rollback: 'remove the line from var/greetings.txt',
)]
final readonly class Greet
{
    public function __construct(
        #[Target]
        #[Because('who to greet')]
        public string $name,
    ) {
    }

    /** @return array<string, mixed> */
    public function run(Greetings $greetings): array
    {
        $name = trim($this->name);

        if ($name === '') {
            return ['ok' => false, 'error' => 'missing `name`'];
        }

        return ['ok' => true, 'greeted' => $name, 'file' => $greetings->write($name)];
    }
}
