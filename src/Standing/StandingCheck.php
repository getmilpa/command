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

namespace Milpa\Command\Standing;

/**
 * The seven things a call must pass for a standing grant to lend it anything, in the order they are
 * asked (greenhouse decisions/0615 §3).
 *
 * They are an enum and not seven booleans because the ways of failing are not interchangeable for
 * whoever reads the refusal: «suspended» is a person's to lift, «not measured» is the caller's to
 * fix, «above the ceiling» is nobody's to fix under this grant. A refusal names the one that failed.
 */
enum StandingCheck: string
{
    /** Does the grant name this operation, these arguments and this seat? If not, it is not this grant's call. */
    case Named = 'named';

    /** Does the grant's signature hold NOW — bytes unaltered, the signer still entitled to give it? */
    case Signature = 'signature';

    /** Is it in force — not suspended, superseded, revoked, or past its own date? */
    case InForce = 'in_force';

    /** Did its falsifier's watcher give a sign of life within its window? */
    case Watched = 'watched';

    /** Does the operation say what content this call acts on? */
    case Subject = 'subject';

    /** Does the effect of THIS call fit under the grant's ceiling? */
    case Ceiling = 'ceiling';

    /** Does every condition have a green receipt, on this very content, from today's instrument? */
    case Conditions = 'conditions';
}
