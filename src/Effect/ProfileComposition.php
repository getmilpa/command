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
 * The effective ceiling AND the receipt of how it was reached (greenhouse decisions/0057).
 *
 * `forCall` composes a ceiling and returns it; for a long time that was all — the answer kept, the
 * question thrown away. This carries both: the effective profile this call runs under, and one
 * {@see AxisReduction} per axis that came down, each naming the producer that had the right to lower
 * it. The effective ceiling is not a number; it is a list of reductions, each signed by its producer.
 */
final readonly class ProfileComposition
{
    /**
     * @param list<AxisReduction> $reductions one per axis lowered, in declaration order of the axes
     */
    public function __construct(
        public EffectProfile $effective,
        public array $reductions,
    ) {
    }
}
