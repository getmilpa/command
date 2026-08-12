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
 * An argument that LOWERS this operation's ceiling for one call, and the reason it may.
 *
 * greenhouse decisions/0029, forced by `capabilities:enable --dry-run` asking permission to do
 * nothing: rule S2 judges the OPERATION, so a rehearsal of an Executable and Privileged operation
 * carried the ceiling of the real thing though it wrote nothing.
 *
 * WHY THIS IS THE DANGEROUS DIRECTION. `escalatesOn` is safe because it can only raise: a careless
 * or lying declarant only harms themself, which is what lets an adversarial enumerator be additive
 * (GOV-14). Lowering inverts that — whoever declares a descent badly is not punished, they are
 * EXEMPTED, and the failure is invisible: a heavy operation that quietly stops asking.
 *
 * So three things are true of every descent here:
 *
 *   · it names the full RESULTING ceiling, never a delta — «a bit less» is not a place, and what a
 *     reader needs is exactly where this lands;
 *   · it carries its REASON, the same shape `rollbackContract` already has for the one reversibility
 *     level that buys less scrutiny;
 *   · a descent that cannot hold does not lower anything. Failing upwards is the only failure this
 *     axis can afford.
 */
final readonly class Descent
{
    /**
     * @param string        $argument  the input key whose presence triggers this descent
     * @param mixed         $whenValue the value that triggers it — identity, so `--dry-run=false` is not a descent
     * @param EffectProfile $to        the ceiling this call actually carries, in full
     * @param string        $because   what makes it true, and without which nothing is lowered
     */
    public function __construct(
        public string $argument,
        public mixed $whenValue,
        public EffectProfile $to,
        public string $because,
    ) {
    }

    /**
     * Does this call trigger the descent? Identity on the value, so a different one does not.
     *
     * @param array<string, mixed> $arguments
     */
    public function triggeredBy(array $arguments): bool
    {
        return \array_key_exists($this->argument, $arguments)
            && $arguments[$this->argument] === $this->whenValue;
    }

    /**
     * Is this descent one anybody should honour?
     *
     * A reason is required, and the destination has to be genuinely lighter on every axis. A descent
     * that raises anything is not a descent — it would be a back door for climbing without saying so.
     */
    public function holds(EffectProfile $original): bool
    {
        if (trim($this->because) === '') {
            return false;
        }

        return $this->to->mutation->weight() <= $original->mutation->weight()
            && $this->to->externality->weight() <= $original->externality->weight()
            && $this->to->reversibility->weight() <= $original->reversibility->weight()
            && $this->to->authority->weight() <= $original->authority->weight()
            && $this->to->subject->weight() <= $original->subject->weight();
    }
}
