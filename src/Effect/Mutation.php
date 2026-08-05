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
 * What this operation changes, and for how long.
 *
 * `Unknown` is the default everywhere in this namespace and it is NOT a gap to be filled in later
 * with something permissive. Under GOV-05 — «lo desconocido nunca reduce controles» — an unclassified
 * operation carries the ceiling of its dimension, not the floor. The reason is the failure this
 * house keeps measuring: a system that treats «I could not determine it» as «it is fine» has built a
 * default that whoever needs to pass the gate will learn to produce.
 */
enum Mutation: string
{
    /** Reads. Leaves nothing behind that a later run could observe. */
    case None = 'none';

    /** Writes something that dies with the process — a temp file, an in-memory cache. */
    case Ephemeral = 'ephemeral';

    /** Writes state that outlives the run: a file, a row, a config, a lock. */
    case Persistent = 'persistent';

    case Unknown = 'unknown';

    /** How much scrutiny this level demands — higher wins when profiles are joined. */
    public function weight(): int
    {
        return match ($this) {
            self::None => 0,
            self::Ephemeral => 1,
            self::Persistent => 2,
            // ABOVE persistent on purpose. «I do not know what this writes» is a worse position to be
            // in than «I know it writes to disk», because the second one can be reasoned about.
            self::Unknown => 3,
        };
    }
}
