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
 * A policy's judgment about authority, with its provenance — a RECEIPT, not currency
 * (greenhouse decisions/0054).
 *
 * An expensive producer's claim is currency: minted at graduation, signed, stored, its freshness
 * checked (the descent certificate, decisions/0050–0051). A cheap deterministic producer is
 * consulted LIVE instead, so nothing stored can go stale when the policy changes — F-4 of
 * decisions/0053, dead by construction. What this object is for is the CHANNEL: an auditable record
 * naming which policy version judged which facts, citable next to the effect it enabled.
 */
final readonly class AuthorityClaim
{
    /**
     * @param Authority $authority        the judged effective authority for this call
     * @param string    $operation        the operation the judgment is about
     * @param string    $policyId         who judged
     * @param string    $policyDigest     the exact version of the rules that judged
     * @param string    $factsFingerprint the exact facts that were judged
     */
    public function __construct(
        public Authority $authority,
        public string $operation,
        public string $policyId,
        public string $policyDigest,
        public string $factsFingerprint,
    ) {
    }

    /** Does this judgment justify landing at the given authority? Judged above the destination does not. */
    public function justifies(Authority $to): bool
    {
        return $this->authority->weight() <= $to->weight();
    }
}
