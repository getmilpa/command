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
            self::Guaranteed => 0,
            self::Compensatable => 1,
            self::ManualRecovery => 2,
            self::Irreversible => 3,
            // Level with irreversible, NOT below it. «We do not know if this can be undone» has to be
            // treated as «it cannot», or the unknown becomes the cheap way to look reversible.
            self::Unknown => 3,
        };
    }
}
