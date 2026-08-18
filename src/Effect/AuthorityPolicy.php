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
 * The one producer with the right to judge authority (greenhouse decisions/0053, 0054).
 *
 * `evidence/0252` classified the axes: `mutation` is observable, `subject` derivable — and
 * `authority` was DECLARED-WITHOUT-PRODUCER, on the axis Consent reads. It cannot be observed
 * (requiring privilege is not a diff on disk) and the contract's own fields collide when asked to
 * derive it. What produces it is an institutional decision over verified facts — this contract.
 */
interface AuthorityPolicy
{
    /**
     * Judge the effective authority for this call, or refuse.
     *
     * Refusal is the default and it is threefold: unverified facts are hearsay and judge nothing; an
     * operation without a rule gets nothing (deny by default); a missing scope satisfies nothing.
     * No judgment means no claim, and without a claim the authority axis does not come down.
     */
    public function judge(ContextFacts $facts, CallSubject $subject): ?AuthorityClaim;

    /** The exact version of the rules doing the judging — editing one rule re-versions the policy. */
    public function digest(): string;
}
