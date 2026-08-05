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
 * Whose authority the operation spends when it runs.
 *
 * The distinction that matters is `WriteAsUser`: an operation that acts in somebody's name produces
 * effects the world will attribute to THEM. That is a different kind of risk from an operation that
 * merely writes, and it is why authority is its own dimension instead of a shade of mutation.
 */
enum Authority: string
{
    /** Needs nothing granted. */
    case None = 'none';

    /** Reads within what the principal can already see. */
    case Read = 'read';

    /** Acts in the principal's name — the effect is attributed to them. */
    case WriteAsUser = 'write_as_user';

    /** Acts above the principal: infrastructure, other people's resources, the runtime itself. */
    case Privileged = 'privileged';

    case Unknown = 'unknown';

    /** How much scrutiny this level demands — higher wins when profiles are joined. */
    public function weight(): int
    {
        return match ($this) {
            self::None => 0,
            self::Read => 1,
            self::WriteAsUser => 2,
            self::Privileged => 3,
            self::Unknown => 4,
        };
    }
}
