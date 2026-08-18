<?php

/**
 * This file is part of milpa/command — the atom: one declared Operation, projected by every surface.
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
 * The verified facts of an execution context — and NOTHING that judges (greenhouse decisions/0054).
 *
 * This object deliberately has no authority field. If the facts carried the verdict, identity would
 * have become the legislator — the same collapse decisions/0031 separated between a token and a
 * principal, one floor up. Facts here, judgment in {@see AuthorityPolicy}.
 *
 * `verified` is a fact ABOUT the facts: whether someone actually established them, or they are
 * hearsay. A policy refuses to judge hearsay — `evidence/0207` measured sessions born ownerless and
 * the channel's only Principal arriving `verified:false`, and this flag is where that history bites.
 */
final readonly class ContextFacts
{
    /**
     * @param string|null  $principal who is acting, when known
     * @param bool         $verified  whether these facts were established rather than asserted
     * @param string|null  $channel   the surface the call arrives through
     * @param list<string> $scopes    what the principal was granted
     */
    public function __construct(
        public ?string $principal = null,
        public bool $verified = false,
        public ?string $channel = null,
        public array $scopes = [],
    ) {
    }

    /** A stable digest of the facts, so a receipt can cite exactly what was judged. */
    public function fingerprint(): string
    {
        $scopes = $this->scopes;
        sort($scopes);

        return 'sha256:' . hash('sha256', (string) json_encode([
            'channel' => $this->channel,
            'principal' => $this->principal,
            'scopes' => $scopes,
            'verified' => $this->verified,
        ]));
    }
}
