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
     * @param string      $operation     the operation about to run, by name
     * @param string|null $handlerDigest the digest of the handler body about to run, or null when it cannot be read
     */
    public function __construct(
        public string $operation,
        public ?string $handlerDigest = null,
    ) {
    }
}
