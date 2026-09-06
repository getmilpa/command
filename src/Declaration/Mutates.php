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

use Milpa\Command\Effect\Authority;
use Milpa\Command\Effect\Descent;
use Milpa\Command\Effect\EffectProfile;
use Milpa\Command\Effect\Externality;
use Milpa\Command\Effect\Mutation;
use Milpa\Command\Effect\Reversibility;
use Milpa\Command\Effect\Subject;

/**
 * What this operation can do AT WORST — the ceiling, declared by the operation about itself.
 *
 * It carries the vocabulary of {@see EffectProfile} unchanged, on purpose: that vocabulary is
 * measured and this attribute is a SYNTAX for it, not a second opinion about it. What the attribute
 * adds is where the declaration lives — next to the code that performs the effect, in the same place
 * the human writes the intent and the agent reads it.
 *
 * A class carrying this is mutating. A class carrying {@see Reads} is not. A class carrying NEITHER
 * does not become an operation at all — silence is not an answer here (greenhouse decisions/0212 §5).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Mutates
{
    /**
     * @param list<string>  $escalatesOn Argument names whose value can RAISE this ceiling.
     * @param list<Descent> $descents    Declared descents from the ceiling, where they are proven —
     *                                   `new Descent(...)` is a valid attribute argument, so a proven
     *                                   descent is written right here rather than reconstructed later.
     */
    public function __construct(
        public Mutation $mutation = Mutation::Unknown,
        public Externality $externality = Externality::Unknown,
        public Reversibility $reversibility = Reversibility::Unknown,
        public Authority $authority = Authority::Unknown,
        public Subject $subject = Subject::Unknown,
        public ?string $rollback = null,
        public array $escalatesOn = [],
        public array $descents = [],
    ) {
    }

    /** The same value object every consumer in the platform already reads. */
    public function profile(): EffectProfile
    {
        return new EffectProfile(
            $this->mutation,
            $this->externality,
            $this->reversibility,
            $this->authority,
            $this->escalatesOn,
            $this->subject,
            $this->rollback,
            $this->descents,
        );
    }
}
