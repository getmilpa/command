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
use Milpa\Command\Effect\EffectProfile;
use Milpa\Command\Effect\Externality;
use Milpa\Command\Effect\Mutation;
use Milpa\Command\Effect\Reversibility;
use Milpa\Command\Effect\Subject;

/**
 * This operation does not mutate — said out loud, because silence must not be the way to say it.
 *
 * The acta first wrote «the absence of {@see Mutates} means it does not mutate». Building it showed
 * that to be the permissive default GOV-13 forbids, arrived at from silence: an author who FORGETS
 * the declaration would ship `mutating: false` on an operation that writes, and every consumer would
 * believe it. So absence is an error, and this attribute is how a read declares itself.
 *
 * With nothing declared it IS {@see EffectProfile::readOnly()} — the profile this package already
 * ships as the canonical read, down to the `nothing-to-roll-back` contract. That reuse is deliberate:
 * a second opinion about what «read-only» means is exactly the second source of truth the whole
 * package refuses.
 *
 * Two axes stay open, because a read is not automatically harmless: `externality` (reading a
 * customer's record and mailing it to a third party is, on the mutation axis, still a read) and
 * `authority` (whose credentials it spends). There is no `subject` here on purpose — {@see
 * EffectProfile} refuses `Mutation::None` beside a subject, since a change to nothing is a change of
 * nothing, and a knob whose every value is refused is worse than no knob.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Reads
{
    /** @param list<string> $escalatesOn */
    public function __construct(
        public Externality $externality = Externality::None,
        public Authority $authority = Authority::Read,
        public array $escalatesOn = [],
    ) {
    }

    /** Nothing changes, so nothing rolls back — and the contract says so instead of staying silent. */
    public function profile(): EffectProfile
    {
        return new EffectProfile(
            Mutation::None,
            $this->externality,
            Reversibility::Guaranteed,
            $this->authority,
            $this->escalatesOn,
            Subject::None,
            'nothing-to-roll-back',
        );
    }
}
