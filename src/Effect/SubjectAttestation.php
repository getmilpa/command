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
 * A producer's statement of what THIS call's change is made of — the producer of one axis, `subject`.
 *
 * ── WHY THE DECLARATION CANNOT SAY IT ────────────────────────────────────────────────────────────
 *
 * {@see Subject} is declared, not derived: three static readers failed to infer it from what a handler
 * touches, so an operation states its ceiling once. But some operations carry their change as DATA —
 * a promotion carries the diff a trial produced — and what that change is made of differs per call.
 * The declaration can only be the worst case (`Executable`: a promotion MAY install code). Held there,
 * the axis is dead to consent: a human who tightens a grant to `subject ≤ configuration` mints a grant
 * nothing can ever satisfy (greenhouse decisions/0080, measured on the published packages).
 *
 * ── WHO MAY LOWER IT, AND HOW FAR ───────────────────────────────────────────────────────────────
 *
 * Only the producer that OWNS the payload — the trial workspace owns the diff — and only by attesting
 * what it can check: a concrete list of changed paths, judged by an allowlist, where anything it
 * cannot vouch for keeps the ceiling. Composition then lowers `subject` to the attested level, with
 * this producer and provenance on the receipt, and touches nothing else. It never raises: an
 * attestation at or above the effective subject is not a reduction and leaves no receipt. Like
 * {@see TrialConfinement}, it is a LIVE producer, not a signed artefact — its trust is the check the
 * producer actually ran, recorded as provenance so an auditor can repeat it after the fact.
 */
final readonly class SubjectAttestation
{
    /**
     * @param Subject $subject    what the change is made of, as the producer could verify it
     * @param string  $producer   who attests — the name composition records on the reduction
     * @param string  $provenance what the producer checked, e.g. the digest of the diff it classified
     */
    public function __construct(
        public Subject $subject,
        public string $producer,
        public string $provenance,
    ) {
        if (trim($producer) === '' || trim($provenance) === '') {
            throw new \InvalidArgumentException('a subject attestation names its producer and what it checked — an attestation without them is a claim nobody can audit');
        }
    }
}
