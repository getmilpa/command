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

namespace Milpa\Command\Declaration;

/**
 * The class IS the operation — its identity, declared where a human writes it and an agent reads it.
 *
 * This is the intent half of greenhouse decisions/0212: «what changes authority is DECLARED; what a
 * type already says is DERIVED». Everything this attribute carries is intent — a name a human types,
 * a sentence that explains the purpose, the surfaces it is allowed to appear on. Nothing here can be
 * inferred from a signature, which is exactly why it is written down.
 *
 * The mechanics — the input schema, the admissible values of an enum, the output shape — are NOT
 * here: they are read off the constructor and the return type by {@see DeclaredOperation}.
 *
 * ```php
 * #[Operation(name: 'hola:greet', description: 'Write a greeting into var/greetings.txt')]
 * #[Mutates(Mutation::Persistent, Externality::None, Reversibility::Guaranteed, subject: Subject::Data)]
 * final class Greet
 * {
 *     public function __construct(#[Target] #[Because('who to greet')] public readonly string $name) {}
 *
 *     public function run(Greetings $greetings): array { return $greetings->write($this->name); }
 * }
 * ```
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Operation
{
    /**
     * @param string            $name        How a human and an agent both call it (`domain:verb`).
     * @param string            $description What it does, in one sentence — the text the CLI's `--help`
     *                                       and the agent's catalogue BOTH show. One truth, two
     *                                       readers; there is no second copy to keep in sync.
     * @param string|null       $version     The contract version, when this operation has one.
     * @param string|null       $path        An explicit HTTP path, when the derived one will not do.
     * @param list<string>|null $surfaces    The surfaces allowed to project it; `null` means all.
     */
    public function __construct(
        public string $name,
        public string $description,
        public ?string $version = null,
        public ?string $path = null,
        public ?array $surfaces = null,
    ) {
    }
}
