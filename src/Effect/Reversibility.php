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

namespace Milpa\Command\Effect;

/**
 * Whether the effect can be taken back — and this is NOT an opinion.
 *
 * An operation is not reversible because somebody says «we can undo it». It is reversible when a
 * rollback contract exists, names the inverse operation, and has been exercised. Rod's examples are
 * the calibration:
 *
 *   · a local commit           — plausibly Guaranteed, `git revert` exists and runs;
 *   · sending an email         — IRREVERSIBLE. Sending a second one does not unsend the first;
 *   · publishing information   — Compensatable at best. Deleting it does not delete the copies;
 *   · transferring money       — irreversible, or subject to an external process. Never «reversible
 *                                because we can ask for it back».
 */
enum Reversibility: string
{
    /** A tested inverse operation exists and the authority to run it is available. */
    case Guaranteed = 'guaranteed';

    /**
     * Nothing happened, so there is nothing to take back — the answer for an operation that reads.
     *
     * It exists because `Guaranteed` was carrying two incompatible meanings, and the cheaper one was
     * drowning the expensive one. Measured on a founded app: of twenty-three operations claiming
     * `Guaranteed`, TWENTY changed nothing at all and backed the claim with the prose
     * «nothing-to-roll-back». Three actually mutated, and only those three were making a promise.
     * An audit of «who claims reversibility» was therefore 87% noise, and the one real debt —
     * `capabilities:refresh`, whose rollback is prose, not an operation — hid inside it.
     *
     * A read is not reversible. Reversibility is a promise about how to undo an effect, and where
     * there is no effect there is no promise to keep. Saying `Guaranteed` there is not a small
     * imprecision: it is the only claim in this enum that BUYS lower scrutiny, handed out for free
     * to everything that reads.
     *
     * It weighs the same as `Guaranteed` — the floor — because a read must never demand more
     * scrutiny than an operation with a tested inverse.
     */
    case NotApplicable = 'not_applicable';

    /** Cannot be undone; a compensating action exists that limits the damage. */
    case Compensatable = 'compensatable';

    /** A human can recover it, by hand, with effort. */
    case ManualRecovery = 'manual_recovery';

    /** Once done, it is done. */
    case Irreversible = 'irreversible';

    case Unknown = 'unknown';

    /** How much scrutiny this level demands — higher wins when profiles are joined. */
    public function weight(): int
    {
        return match ($this) {
            // The floor, and shared on purpose: an operation that changes nothing cannot be more
            // suspicious than one that changes something it can provably undo.
            self::NotApplicable => 0,
            self::Guaranteed => 0,
            self::Compensatable => 1,
            self::ManualRecovery => 2,
            self::Irreversible => 3,
            // ABOVE irreversible, not level with it. «We do not know if this can be undone» has to be
            // treated as at least «it cannot», or the unknown becomes the cheap way to look
            // reversible — and it cannot weigh the SAME either: `join()` breaks a tie in favour of
            // its left side, so with equal weights `irreversible.join(unknown)` answered
            // `irreversible` and `unknown.join(irreversible)` answered `unknown`. A fold whose
            // label depends on the order it was folded in is not a ceiling (greenhouse
            // decisions/0224). Strictly above, every axis is a chain and the join is the same
            // whichever side is folded first.
            self::Unknown => 4,
        };
    }
}
