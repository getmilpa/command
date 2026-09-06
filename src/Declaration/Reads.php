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
 * It answers ONE axis — mutation. A read still reaches somewhere and still spends somebody's
 * authority, and this invents neither: the other axes stay unknown unless declared right here.
 * Reading a customer's record and mailing it to a third party is, on the mutation axis, a read.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Reads
{
    /** @param list<string> $escalatesOn */
    public function __construct(
        public Externality $externality = Externality::Unknown,
        public Authority $authority = Authority::Unknown,
        public Subject $subject = Subject::Unknown,
        public array $escalatesOn = [],
    ) {
    }

    /** Mutation answered, reversibility answered by it, everything else left where it was found. */
    public function profile(): EffectProfile
    {
        return new EffectProfile(
            Mutation::None,
            $this->externality,
            Reversibility::Guaranteed,
            $this->authority,
            $this->escalatesOn,
            $this->subject,
        );
    }
}
