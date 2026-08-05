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
 * Who, outside this process, finds out — and can no longer be made to forget.
 *
 * This is the dimension `mutating: bool` could never carry, and the one that decides whether a
 * mistake stays inside the house. Writing a local file and sending an email are both «mutations»;
 * only one of them arrives at somebody else's inbox.
 */
enum Externality: string
{
    /** Nothing leaves the process boundary. */
    case None = 'none';

    /** Reaches systems the same principal already controls — its own repo, its own database. */
    case SamePrincipal = 'same_principal';

    /** Reaches a third party: an API, a bot channel, another person's inbox. */
    case ThirdParty = 'third_party';

    /** Reaches anyone: a published page, a public package, a released tag. */
    case Public = 'public';

    case Unknown = 'unknown';

    /** How much scrutiny this level demands — higher wins when profiles are joined. */
    public function weight(): int
    {
        return match ($this) {
            self::None => 0,
            self::SamePrincipal => 1,
            self::ThirdParty => 2,
            self::Public => 3,
            self::Unknown => 4,
        };
    }
}
