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

use Milpa\Command\Declaration\Confirms;
use Milpa\Command\Declaration\Mutates;
use Milpa\Command\Declaration\Needs;
use Milpa\Command\Declaration\Operation;
use Milpa\Command\Effect\Mutation;

/** An enum parameter, a default, scopes and a confirmation — all declared, none inferred. */
#[Operation(name: 'doc:publish', description: 'Move a document to a state.')]
#[Mutates(Mutation::Persistent)]
#[Needs(scopes: ['docs:write'])]
#[Confirms]
final readonly class Publish
{
    public function __construct(
        public string $id,
        public Status $status = Status::Draft,
        public int $revision = 1,
    ) {
    }

    public function run(): Receipt
    {
        return new Receipt($this->id, $this->status->value, $this->revision);
    }
}
