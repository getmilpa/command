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
use Milpa\Command\Effect\Externality;
use Milpa\Command\Effect\Mutation;
use Milpa\Command\Effect\Reversibility;
use Milpa\Command\Effect\Subject;
use Milpa\Command\InvocationContext;

/** Says who ran it, read from the context the surface attributed — never from its own input. */
#[Operation(name: 'lab:attributed', description: 'Record who approved a note.')]
#[Mutates(Mutation::Persistent, Externality::None, Reversibility::ManualRecovery, subject: Subject::Data)]
final readonly class Attributed
{
    public function __construct(
        #[Because('what is being approved')]
        public string $note,
    ) {
    }

    /** @return array<string, mixed> */
    public function run(Greetings $greetings, ?InvocationContext $context = null): array
    {
        return [
            'note' => $this->note,
            'by' => $context?->actor,
            'verified' => $context?->verified ?? false,
            'channel' => $context?->channel,
            'wrote' => $greetings->write($this->note),
        ];
    }
}
