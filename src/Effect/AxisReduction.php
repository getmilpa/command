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
 * One axis brought down, and the producer that had the right to do it (greenhouse decisions/0057).
 *
 * This is the unit of provenance the composition keeps so a channel can record — and an Audit view
 * can paint — WHY the ceiling is where it is: not «authority is read» but «authority is read because
 * the policy lab-app judged these facts». An axis with no producer never becomes an AxisReduction,
 * because it was never lowered.
 */
final readonly class AxisReduction
{
    /**
     * @param string $axis       one of mutation, externality, reversibility, authority, subject
     * @param string $producer   'observer' (the descent certificate) or 'policy' (the authority judge)
     * @param string $provenance the citation: the certificate's verifier and covers, or policyId@digest over the facts fingerprint
     */
    public function __construct(
        public string $axis,
        public string $from,
        public string $to,
        public string $producer,
        public string $provenance,
    ) {
    }
}
