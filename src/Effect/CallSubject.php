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
 * WHAT a call is about to run, so a certificate can be checked against it — greenhouse decisions/0051.
 *
 * The name and the digest travel together because either alone lets a certificate slide. Without the
 * digest, evidence about code that has since moved keeps its authority. Without the name, two
 * operations whose handler bodies are textually identical share one certificate — measured in
 * `evidence/0248`, where three probes came out with the same digest at first contact.
 */
final readonly class CallSubject
{
    /**
     * @param string               $operation     the operation about to run, by name
     * @param string|null          $handlerDigest the digest of the handler body about to run, or null when it cannot be read
     * @param AuthorityPolicy|null $policy        the institution with the right to judge authority for this call, when the caller has one (greenhouse decisions/0054)
     * @param ContextFacts|null    $facts         the verified facts of who is calling — facts only, never a verdict
     */
    public function __construct(
        public string $operation,
        public ?string $handlerDigest = null,
        public ?AuthorityPolicy $policy = null,
        public ?ContextFacts $facts = null,
        /**
         * The call is confined to a disposable trial workspace — the live producer that lets
         * composition lower `mutation` to Ephemeral, and nothing else (greenhouse decisions/0068).
         * Set only by the router that also sends the call there: one source for both.
         */
        public ?TrialConfinement $confinement = null,
        /**
         * What THIS call's change is made of, attested by the producer that owns its payload — the
         * live producer that lets composition lower `subject`, and nothing else (greenhouse
         * decisions/0080). Set only by the owner of the payload it classified: one source for both.
         */
        public ?SubjectAttestation $subjectAttestation = null,
    ) {
    }
}
