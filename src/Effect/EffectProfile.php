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
 * What an operation can do at WORST — the upper bound, declared by the operation itself.
 *
 * ── WHY AN UPPER BOUND AND NOT A DESCRIPTION ────────────────────────────────────────────────────
 *
 * Most operations do not have one fixed effect. `file.write` can write a scratch file or a
 * production artifact; `plugins.install` can pull a local path or a package from the internet. If
 * the profile described the typical case, every classification would be a bet on the arguments
 * nobody has seen yet.
 *
 * So the profile declares the CEILING: what this operation could do if its arguments turn out to be
 * the worst plausible ones. Arguments can only ever narrow it — and only when they are actually
 * resolved. An unresolved argument keeps the ceiling, which is GOV-05 applied at the call site:
 * not knowing where the file goes is not permission to assume it goes somewhere harmless.
 *
 * ── WHY IT DOES NOT REPLACE `mutating` ──────────────────────────────────────────────────────────
 *
 * `Operation::$mutating` is read in eight places across four packages: the session gate, the CLI
 * runner, the TUI projector, the MCP model, the HTTP policy. Replacing it would be a migration; and
 * keeping both without a rule would be a second source of truth — the defect this repository has
 * caught four times in a week, always the same way, always through a consumer that read the stale
 * one.
 *
 * So there is one rule, enforced at construction: a profile cannot say `Mutation::None` for an
 * operation that declares `mutating: true`. The contradiction is not resolved at read time; it is
 * IMPOSSIBLE TO DECLARE. `mutating` stays the coarse, load-bearing signal, and this is the refinement
 * that says what kind of mutation it is.
 *
 * ── WHY THE JOIN AND NOT AN AVERAGE ─────────────────────────────────────────────────────────────
 *
 * When two profiles combine — an ambiguity with several interpretations, a step that calls several
 * operations — the result takes the HIGHEST of each dimension independently. Risks are not averaged
 * (GOV-06). A local, reversible operation on highly sensitive data is not «medium»: it is an
 * operation on highly sensitive data.
 */
final class EffectProfile
{
    /**
     * @param list<string> $escalatesOn argument names whose value can RAISE this ceiling — declared
     *                                  so a caller knows which unresolved argument is the one that
     *                                  keeps the profile pinned at its worst
     */
    public function __construct(
        public readonly Mutation $mutation = Mutation::Unknown,
        public readonly Externality $externality = Externality::Unknown,
        public readonly Reversibility $reversibility = Reversibility::Unknown,
        public readonly Authority $authority = Authority::Unknown,
        public readonly array $escalatesOn = [],
        /**
         * What the change is made OF — the only axis here that does not answer «how much».
         *
         * It arrives fifth because the other four were measured NOT to discriminate: eight
         * operations, half of them demanding a signature and half not, came out identical on
         * mutation, externality, reversibility and authority. See {@see Subject}.
         */
        public readonly Subject $subject = Subject::Unknown,
        /**
         * The rollback contract, when reversibility claims to be `Guaranteed`.
         *
         * Required for that one level and for no other, because `Guaranteed` is the only claim that
         * buys the operation LESS scrutiny. A claim that lowers controls has to name the artifact
         * that backs it, or it is exactly the self-certification GOV-00 exists to forbid.
         */
        public readonly ?string $rollbackContract = null,
        /**
         * Arguments that LOWER this ceiling for one call — the dangerous direction (decisions/0029).
         *
         * LAST on purpose: every existing positional construction — join() among them — keeps
         * working untouched, and a new field that renumbers the old ones would break callers to
         * make room for something they never asked for.
         *
         * @var list<Descent>
         */
        public readonly array $descents = [],
    ) {
        // A READ HAS NO SUBJECT, AND SAYING OTHERWISE IS IMPOSSIBLE RATHER THAN MERELY WRONG.
        //
        // Four blind judges classified thirty-three operations from the definition alone, and every
        // disagreement they produced landed on an operation that changes nothing at all: they were
        // being asked what a read is made of. `Mutation::None` already answers that nothing changes,
        // so the two are made to agree by construction — the same treatment the guaranteed-rollback
        // claim gets below, and for the same reason: a contradiction that cannot be declared never
        // has to be caught by a reviewer.
        if ($mutation === Mutation::None && $subject !== Subject::None && $subject !== Subject::Unknown) {
            throw new \InvalidArgumentException(
                'an operation that changes nothing cannot declare a subject: `Mutation::None` and '
                . '«' . $subject->value . '» disagree about whether anything happens'
            );
        }

        if ($reversibility === Reversibility::Guaranteed && ($rollbackContract === null || trim($rollbackContract) === '')) {
            throw new \InvalidArgumentException(
                'reversibility «guaranteed» requires a rollback contract: a claim that lowers scrutiny '
                . 'must name what backs it, or it is the operation certifying itself'
            );
        }
    }

    /**
     * The profile an operation gets when it never declared one: everything unknown, everything at
     * its ceiling.
     *
     * This is the day-zero position for every operation in the catalogue, and it is deliberately
     * unusable for anything sensitive. The pressure it creates is the point — the way out is to
     * classify, never to add a permissive default (GOV-13).
     */
    public static function unclassified(): self
    {
        return new self();
    }

    /** Nothing at all: reads, leaves no trace, reaches nobody, spends no authority. */
    public static function readOnly(): self
    {
        return new self(
            Mutation::None,
            Externality::None,
            Reversibility::Guaranteed,
            Authority::Read,
            subject: Subject::None,
            rollbackContract: 'nothing-to-roll-back',
        );
    }

    /**
     * Whether somebody actually classified this, or it is four `unknown`s wearing a profile.
     *
     * The distinction matters to every caller: an unclassified ceiling is at its maximum, so it
     * looks strict — but it is strict from ignorance, not from a decision anyone made.
     */
    public function isFullyClassified(): bool
    {
        return $this->mutation !== Mutation::Unknown
            && $this->externality !== Externality::Unknown
            && $this->reversibility !== Reversibility::Unknown
            && $this->authority !== Authority::Unknown
            && $this->subject !== Subject::Unknown;
    }

    /**
     * The least-upper-bound of two profiles — the higher of each dimension, independently.
     *
     * Monotonic by construction: joining can only raise. That is what makes an additive adversarial
     * enumerator safe (GOV-14) — a proposer that adds an interpretation can worsen the ceiling and
     * can never improve it, so nothing has to trust that it enumerated honestly.
     */
    public function join(self $other): self
    {
        return new self(
            $this->mutation->weight() >= $other->mutation->weight() ? $this->mutation : $other->mutation,
            $this->externality->weight() >= $other->externality->weight() ? $this->externality : $other->externality,
            $this->reversibility->weight() >= $other->reversibility->weight() ? $this->reversibility : $other->reversibility,
            $this->authority->weight() >= $other->authority->weight() ? $this->authority : $other->authority,
            array_values(array_unique([...$this->escalatesOn, ...$other->escalatesOn])),
            $this->subject->weight() >= $other->subject->weight() ? $this->subject : $other->subject,
            // The joined profile keeps a rollback contract ONLY while both sides still guarantee it.
            // Joining a guaranteed operation with an irreversible one does not produce something
            // half-recoverable; it produces something irreversible, and the contract no longer applies.
            $this->reversibility === Reversibility::Guaranteed && $other->reversibility === Reversibility::Guaranteed
                ? $this->rollbackContract
                : null,
        );
    }

    /**
     * The ceiling THIS CALL carries, once its arguments are known.
     *
     * Escalation is not resolved here and must not be: `unresolvedEscalators()` answers a different
     * question — «is the ceiling still the ceiling?» — and while it returns anything the answer is
     * yes. This only ever descends.
     *
     * A descent that does not hold is ignored in silence rather than raising, because a call that
     * refuses to run because someone declared badly punishes the caller for the author's mistake.
     * The one that stops is the ceiling: it simply does not come down.
     *
     * The subject of the call travels because greenhouse decisions/0050 made a descent depend on
     * evidence bound to the code that will run, and decisions/0051 bound it to the operation too. A
     * caller that cannot say what is about to run gets no descent: not being able to look is not the
     * same as having looked and found nothing.
     *
     * @param array<string, mixed> $arguments
     */
    public function forCall(array $arguments, ?CallSubject $subject = null): self
    {
        return $this->composeForCall($arguments, $subject)->effective;
    }

    /**
     * The effective ceiling AND the receipt of how it was reached (greenhouse decisions/0057).
     *
     * Where {@see forCall()} answers «what ceiling?», this answers «what ceiling, and who lowered
     * each axis to get here?» — one AxisReduction per axis that came down, each naming its authorized
     * producer. The composer is not a producer (MILPA-G002): it asks each descent to {@see
     * Descent::explain()} what it lowered and records the answer; it never decides an axis itself.
     *
     * @param array<string, mixed> $arguments
     */
    public function composeForCall(array $arguments, ?CallSubject $subject = null): ProfileComposition
    {
        foreach ($this->descents as $descent) {
            if ($descent->triggeredBy($arguments) && $descent->holds($this, $subject)) {
                return new ProfileComposition($descent->to, $descent->explain($this, $subject));
            }
        }

        return new ProfileComposition($this, []);
    }

    /**
     * Which declared escalating arguments are still unresolved in this call.
     *
     * A caller uses it to answer the only question that matters before running: «is the ceiling
     * still the ceiling?» While this returns anything, the answer is yes.
     *
     * @param array<string, mixed> $arguments
     *
     * @return list<string>
     */
    public function unresolvedEscalators(array $arguments): array
    {
        return array_values(array_filter(
            $this->escalatesOn,
            static fn (string $name): bool => !\array_key_exists($name, $arguments)
                || $arguments[$name] === null
                || $arguments[$name] === '',
        ));
    }

    /**
     * The profile as data, with `fully_classified` travelling inside it.
     *
     * The verdict ships in the payload so a consumer reading JSON cannot mistake four `unknown`
     * values for a classification somebody made — which is the exact confusion GOV-11 is about, one
     * layer down.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mutation' => $this->mutation->value,
            'externality' => $this->externality->value,
            'reversibility' => $this->reversibility->value,
            'authority' => $this->authority->value,
            'subject' => $this->subject->value,
            'escalates_on' => $this->escalatesOn,
            'rollback_contract' => $this->rollbackContract,
            'fully_classified' => $this->isFullyClassified(),
        ];
    }
}
