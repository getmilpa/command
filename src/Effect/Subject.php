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
 * What the change is made OF — the only dimension here that does not answer «how much».
 *
 * ── WHY A FIFTH AXIS, MEASURED RATHER THAN ARGUED ───────────────────────────────────────────────
 *
 * Eight operations were compared: four demanded a cryptographic signature and four did not, and on
 * every declared dimension they were IDENTICAL — same durability, same authority, same
 * recoverability. Four axes and none of them discriminated, because all four answer how much and
 * none answers of what. The property this framework actually gates on was declared nowhere.
 *
 * It is also not derivable. Three independent static readers were built to infer it from what a
 * handler touches — shallow, transitive, and verb-qualified — and all three failed differently, and
 * all three missed an operation whose package-manager command had been printed by running it. The
 * property lives between what reading derives and what running measures, so it must be declared.
 *
 * ── AND `Unknown` IS THE CEILING, NOT A GAP ─────────────────────────────────────────────────────
 *
 * Like every dimension in this namespace, an operation that never said carries the worst reading,
 * not the most convenient one (GOV-05). That inverts who carries the burden: whoever wants to run
 * without consent has to WRITE that their operation does not touch the executable, and a written
 * claim is one a reviewer can quote and refute. It does not prevent the lie; it gives it a name.
 */
enum Subject: string
{
    /** Nothing changes. The operation reads — there is no subject to speak of. */
    case None = 'none';

    /** Rows, tokens, entries in a store, an index. What the code reads and writes, not the code. */
    case Data = 'data';

    /**
     * How the code that is ALREADY there behaves: a setting, a mode, a constitution.
     *
     * The same classes keep loading; they act differently. This is the level that stopped the
     * previous rule from overreaching — founding an app and changing an agent's autonomy both live
     * here, and neither is the kind of act a signature is for.
     */
    case Configuration = 'configuration';

    /**
     * WHICH CODE WILL RUN: installs, removes, replaces, or stops something from booting.
     *
     * Writing a new class into the app's own tree belongs here too. The test is not whether bytes
     * reach the disk — it is whether the set of things this app will execute is different afterwards.
     */
    case Executable = 'executable';

    case Unknown = 'unknown';

    /** How much scrutiny this level demands — higher wins when profiles are joined. */
    public function weight(): int
    {
        return match ($this) {
            self::None => 0,
            self::Data => 1,
            self::Configuration => 2,
            self::Executable => 3,
            // ABOVE changing the executable, for the same reason it is above every other maximum in
            // this namespace: not knowing what an act is made of is worse than knowing the worst.
            self::Unknown => 4,
        };
    }
}
