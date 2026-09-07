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

use Milpa\Command\Consent\OperationId;

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

        // NOTHING TO UNDO IS NOT A PROMISE TO UNDO — the same treatment as the subject above, aimed at
        // the other claim that lowers scrutiny.
        //
        // `Guaranteed` was carrying two incompatible meanings and the cheap one was drowning the
        // expensive one: measured on a founded app, twenty of twenty-three `Guaranteed` operations
        // changed NOTHING and backed the claim with the prose «nothing-to-roll-back». An audit of who
        // promises reversibility was 87% noise, and the one real debt hid inside it.
        if ($mutation === Mutation::None && $reversibility === Reversibility::Guaranteed) {
            throw new \InvalidArgumentException(
                'an operation that changes nothing cannot promise to undo it: `Mutation::None` and '
                . '«guaranteed» disagree about whether there is anything to take back — '
                . '`Reversibility::NotApplicable` is what a read declares'
            );
        }

        // And its mirror, so the new case cannot become a second way to look reversible: «nothing to
        // undo» is only true when nothing happened.
        if ($mutation !== Mutation::None && $reversibility === Reversibility::NotApplicable) {
            throw new \InvalidArgumentException(
                'an operation that changes something cannot say undoing does not apply: `Mutation::'
                . $mutation->value . '` and «not_applicable» disagree about whether anything happens'
            );
        }

        if ($reversibility === Reversibility::Guaranteed && ($rollbackContract === null || trim($rollbackContract) === '')) {
            throw new \InvalidArgumentException(
                'reversibility «guaranteed» requires a rollback contract: a claim that lowers scrutiny '
                . 'must name what backs it, or it is the operation certifying itself'
            );
        }

        // A PROMISE THAT LOWERS SCRUTINY NAMES THE INVERSE; IT DOES NOT DESCRIBE IT.
        //
        // The guard above asked for a contract and accepted a sentence, so the house asked for proof
        // and took a note: «delete var/capability-index.json» reads like an answer and is not one —
        // nothing can run it, nothing can check it ran, and no gate can put it through the ceremony
        // the original call went through. What CAN be all three is the name of another operation, in
        // the identity this family already has for exactly this ({@see OperationId}: spelling belongs
        // to the projection, identity to the atom).
        //
        // Well-formed is checked here because it needs nothing but the string. Whether that name
        // resolves to an operation this app actually offers is a question about the TABLE, not about
        // one profile, and it is asked where the table exists.
        if ($reversibility === Reversibility::Guaranteed && !self::namesAnOperation((string) $rollbackContract)) {
            throw new \InvalidArgumentException(\sprintf(
                'the rollback contract «%s» describes an inverse instead of naming one: reversibility '
                . '«guaranteed» must name the operation that undoes this (e.g. «plugins.disable»), '
                . 'because prose cannot be run, cannot be checked, and cannot be put through a gate. '
                . 'An operation whose inverse is not an operation is not guaranteed — declare what it '
                . 'really is.',
                $rollbackContract,
            ));
        }
    }

    /**
     * Whether a rollback contract NAMES an operation instead of describing one.
     *
     * The shape of an identity, not of a sentence: canonical segments of letters and digits (a hyphen
     * inside a segment is allowed — `plugins.disable-unsafe` is one operation). Anything carrying a
     * space, a slash or a path is prose, and prose is what this rule exists to refuse.
     */
    private static function namesAnOperation(string $contract): bool
    {
        return preg_match('/^[a-z0-9]+(?:[-][a-z0-9]+)*(?:\.[a-z0-9]+(?:[-][a-z0-9]+)*)*$/', OperationId::canonizar($contract)) === 1;
    }

    /**
     * The operation that undoes this one — the identity, not the spelling — or null when this profile
     * promises no inverse.
     *
     * Callers ask for the identity so a gate can find that operation however a surface writes it, and
     * so what the ledger records is a NAME with arguments rather than a class: a class in a stored
     * record is a dangling pointer the day it is renamed.
     */
    public function rollbackOperation(): ?OperationId
    {
        // NAMING AND PROMISING ARE DIFFERENT THINGS, and this used to conflate them.
        //
        // It answered only for `Guaranteed`, so an operation demoted to `Compensatable` while still
        // naming a real inverse stopped being able to say what undoes it — and demotion is exactly what
        // greenhouse decisions/0221 prescribes for a guarantee that depends on the host. The rungs differ
        // in how much SCRUTINY they buy, not in whether they can name an operation: a compensating action
        // is just as runnable, and just as worth finding, as a guaranteed one.
        //
        // What still decides is the CONTRACT: an identity when it names an operation, null when it is
        // prose. `capabilities:refresh` («delete var/capability-index.json») and any hand-recovery note
        // answer null, which is what they are.
        if ($this->rollbackContract === null || !self::namesAnOperation($this->rollbackContract)) {
            return null;
        }

        return new OperationId($this->rollbackContract);
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
            Reversibility::NotApplicable,
            Authority::Read,
            subject: Subject::None,
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
        $mutation = $this->mutation->weight() >= $other->mutation->weight() ? $this->mutation : $other->mutation;
        $reversibility = $this->reversibility->weight() >= $other->reversibility->weight() ? $this->reversibility : $other->reversibility;
        // «Undoing does not apply» is what a side that changes NOTHING says, so it has no opinion about
        // undoing what the other side changed. It weighs the same as `Guaranteed` (both are the floor),
        // so an axis-by-axis pick can land on it by a tie and produce a profile that mutates while
        // saying undoing does not apply — which the constructor refuses, rightly. The side that
        // actually mutates is the one whose answer means anything.
        if ($mutation !== Mutation::None && $reversibility === Reversibility::NotApplicable) {
            $reversibility = $this->mutation === Mutation::None ? $other->reversibility : $this->reversibility;
        }

        return new self(
            $mutation,
            $this->externality->weight() >= $other->externality->weight() ? $this->externality : $other->externality,
            $reversibility,
            $this->authority->weight() >= $other->authority->weight() ? $this->authority : $other->authority,
            array_values(array_unique([...$this->escalatesOn, ...$other->escalatesOn])),
            $this->subject->weight() >= $other->subject->weight() ? $this->subject : $other->subject,
            // The joined profile keeps a rollback contract ONLY while it still guarantees one. Joining a
            // guaranteed operation with an irreversible one does not produce something half-recoverable;
            // it produces something irreversible, and the contract no longer applies.
            $reversibility === Reversibility::Guaranteed
                ? ($this->reversibility === Reversibility::Guaranteed ? $this->rollbackContract : $other->rollbackContract)
                : null,
        );
    }

    /**
     * The greatest-lower-bound of two profiles — the mirror of {@see self::join()}.
     *
     * Where join composes UPWARD (the more dangerous of each axis, so a ceiling covers both), meet
     * composes DOWNWARD (the safer), so the result can only LOWER and never raise any axis. It is the
     * primitive a STRUCTURAL counter rests on: when a human tightens the envelope of a gated call —
     * «authorise it, but only if reversible / only Read» — the tightened ceiling is
     * `meet($ceiling, $humanProfile)`, and it is safe precisely because a meet is `<=` both operands
     * on every axis. That «never raises» is the whole safety claim.
     */
    public function meet(self $other): self
    {
        $mutation = $this->mutation->weight() <= $other->mutation->weight() ? $this->mutation : $other->mutation;

        $subject = $this->subject->weight() <= $other->subject->weight() ? $this->subject : $other->subject;
        // A no-mutation profile has no subject (constructor invariant). meet can drop mutation to None
        // while keeping the mutating side's subject; Subject::None is `<=` every subject, so clamping
        // it stays a valid greatest-lower-bound.
        if ($mutation === Mutation::None) {
            $subject = Subject::None;
        }

        $reversibility = $this->reversibility->weight() <= $other->reversibility->weight() ? $this->reversibility : $other->reversibility;
        // The twin of the subject clamp above, and true for the same reason: a profile that changes
        // nothing does not promise to undo it (constructor invariant). meet can drop mutation to None
        // while keeping the mutating side's reversibility; `NotApplicable` weighs the same as
        // `Guaranteed` — both are the floor — so clamping it lowers no axis and stays a valid
        // greatest-lower-bound.
        if ($mutation === Mutation::None) {
            $reversibility = Reversibility::NotApplicable;
        }

        // Guaranteed reversibility requires a rollback contract. meet reaches Guaranteed when EITHER
        // side is, so it carries the contract from whichever side declared it — otherwise the result
        // would be an invalid profile.
        $rollbackContract = null;
        if ($reversibility === Reversibility::Guaranteed) {
            $rollbackContract = $this->reversibility === Reversibility::Guaranteed ? $this->rollbackContract : $other->rollbackContract;
        }

        return new self(
            $mutation,
            $this->externality->weight() <= $other->externality->weight() ? $this->externality : $other->externality,
            $reversibility,
            $this->authority->weight() <= $other->authority->weight() ? $this->authority : $other->authority,
            array_values(array_unique([...$this->escalatesOn, ...$other->escalatesOn])),
            $subject,
            $rollbackContract,
        );
    }

    /**
     * Whether this profile is `<=` `$other` on EVERY axis — the one comparator.
     *
     * It is what a gate asserts right after a meet (the never-widens tripwire: `meet($b, $p)` must be
     * no wider than `$b`, and if it ever is not, nothing is granted) and what a policy uses to admit a
     * call under a tightened envelope (the call's composed profile must be no wider than the envelope).
     * One ordering, in one place: a second comparator elsewhere is the duplicate-judge defect, because
     * the day the two disagree, whichever one a consumer happens to read decides authority.
     *
     * `Unknown` weighs as the top of its axis, so an unclassified axis is never «narrower» than a
     * classified one — not knowing is not permission.
     */
    public function isNoWiderThan(self $other): bool
    {
        return $this->mutation->weight() <= $other->mutation->weight()
            && $this->externality->weight() <= $other->externality->weight()
            && $this->reversibility->weight() <= $other->reversibility->weight()
            && $this->authority->weight() <= $other->authority->weight()
            && $this->subject->weight() <= $other->subject->weight();
    }

    /**
     * A human's tightening as a profile: the axes they named, `Unknown` on the ones they did not.
     *
     * `Unknown` is the TOP of every axis, so meeting this with a declared ceiling leaves the omitted
     * axes exactly at the ceiling — naming one axis tightens one axis. The keys are the five axes and
     * nothing else: an `amount`, a `path`, any value, is a change of TARGET, and that is not a
     * tightening but a new proposal (greenhouse decisions/0065) — it is refused here so it can only
     * travel the advisory route. `Guaranteed` is refused too: it buys less scrutiny and needs a
     * producer's rollback contract, which nobody can supply by clicking.
     *
     * @param array<string, mixed> $axes e.g. `['reversibility' => 'compensatable', 'authority' => 'read']`
     */
    public static function fromPartial(array $axes): self
    {
        if ($axes === []) {
            throw new \InvalidArgumentException('a tightening names at least one axis; an empty one tightens nothing');
        }

        $known = ['mutation', 'externality', 'reversibility', 'authority', 'subject'];
        foreach (array_keys($axes) as $key) {
            if (!\in_array($key, $known, true)) {
                throw new \InvalidArgumentException(sprintf(
                    '«%s» is not an effect axis (%s): changing it is a new proposal, not a tightening — use a counter',
                    (string) $key,
                    implode(', ', $known),
                ));
            }
        }

        $level = static function (string $axis, string $enum, array $axes): mixed {
            if (!\array_key_exists($axis, $axes)) {
                return $enum::Unknown;
            }
            $raw = $axes[$axis];
            $case = \is_string($raw) ? $enum::tryFrom($raw) : null;
            if ($case === null) {
                throw new \InvalidArgumentException(sprintf('%s is not a level of %s', json_encode($raw) ?: get_debug_type($raw), $axis));
            }

            return $case;
        };

        $reversibility = $level('reversibility', Reversibility::class, $axes);
        if ($reversibility === Reversibility::Guaranteed) {
            throw new \InvalidArgumentException(
                'reversibility «guaranteed» cannot be claimed by a tightening: it buys less scrutiny and '
                . 'needs a producer-backed rollback contract, which a human cannot supply',
            );
        }

        // And neither can its twin, or the new case becomes the back door to the same floor.
        // `NotApplicable` weighs exactly what `Guaranteed` weighs, and it is a claim about the WORLD —
        // that nothing happened — which is the producer's to make, never the tightener's. A human who
        // believes an operation changes nothing tightens `mutation: none` and lets the profile derive it.
        if ($reversibility === Reversibility::NotApplicable) {
            throw new \InvalidArgumentException(
                'reversibility «not_applicable» cannot be claimed by a tightening: it sits at the same '
                . 'floor as «guaranteed» and asserts that nothing happens, which only the operation can '
                . 'say — tighten «mutation» instead',
            );
        }

        return new self(
            $level('mutation', Mutation::class, $axes),
            $level('externality', Externality::class, $axes),
            $reversibility,
            $level('authority', Authority::class, $axes),
            subject: $level('subject', Subject::class, $axes),
        );
    }

    /**
     * The inverse of {@see self::toArray()}: a profile back from the array an event stored.
     *
     * A granted envelope lives in an event payload; the policy rehydrates it here to compare with
     * {@see self::isNoWiderThan()}. All five axes are required — a partial array is a tightening
     * ({@see self::fromPartial()}), not a stored profile, and reading one as the other would turn
     * «axis not recorded» into «axis at its top» silently.
     *
     * @param array<string, mixed> $data as produced by toArray()
     */
    public static function fromArray(array $data): self
    {
        $axis = static function (string $key, string $enum, array $data): mixed {
            $raw = $data[$key] ?? null;
            $case = \is_string($raw) ? $enum::tryFrom($raw) : null;
            if ($case === null) {
                throw new \InvalidArgumentException(sprintf('a stored profile needs a valid «%s»; got %s', $key, json_encode($raw) ?: get_debug_type($raw)));
            }

            return $case;
        };

        $escalatesOn = \is_array($data['escalates_on'] ?? null)
            ? array_values(array_filter($data['escalates_on'], 'is_string'))
            : [];
        $rollback = \is_string($data['rollback_contract'] ?? null) ? $data['rollback_contract'] : null;

        $mutation = $axis('mutation', Mutation::class, $data);
        $reversibility = $axis('reversibility', Reversibility::class, $data);

        // ── HISTORY IS ATTESTED, NEVER VALIDATED ───────────────────────────────────────────────────
        //
        // Every profile stored before `Reversibility::NotApplicable` existed says `guaranteed` for an
        // operation that changes nothing — that pair was how a read was written, and the constructor
        // now refuses it. Throwing here would make a rule adopted TODAY unable to read what the ledger
        // recorded YESTERDAY: an archived event is not a declaration anyone can still fix, and a house
        // that cannot read its own ledger has lost the argument it was keeping the ledger to win.
        //
        // So a stored pair is normalised, not rejected. It lowers nothing — `NotApplicable` weighs what
        // `guaranteed` weighed — and it says of that old event exactly what it always meant: nothing
        // happened, so there was nothing to take back. The refusal keeps its whole force where it
        // belongs, at the moment somebody DECLARES a profile.
        if ($mutation === Mutation::None && $reversibility === Reversibility::Guaranteed) {
            $reversibility = Reversibility::NotApplicable;
            $rollback = null;
        }

        // And a stored promise backed by PROSE is read as what it actually was: something a human can
        // undo by hand. It is not rejected — the event happened and cannot be re-declared — and the
        // sentence is KEPT, so nothing anyone wrote is lost. What is not kept is the discount:
        // reporting `guaranteed` to a policy reading this envelope today would hand out lower scrutiny
        // on the strength of a note. This RAISES scrutiny and never lowers it, which is the same rule
        // that puts `Unknown` level with `Irreversible` instead of below it.
        if ($reversibility === Reversibility::Guaranteed && !self::namesAnOperation((string) $rollback)) {
            $reversibility = Reversibility::ManualRecovery;
        }

        return new self(
            $mutation,
            $axis('externality', Externality::class, $data),
            $reversibility,
            $axis('authority', Authority::class, $data),
            $escalatesOn,
            $axis('subject', Subject::class, $data),
            $rollback,
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
        $base = new ProfileComposition($this, []);
        foreach ($this->descents as $descent) {
            if ($descent->triggeredBy($arguments) && $descent->holds($this, $subject)) {
                $base = new ProfileComposition($descent->to, $descent->explain($this, $subject));
                break;
            }
        }

        // THE THIRD PRODUCER: CONFINEMENT (greenhouse decisions/0068, 0069). A call routed to a
        // disposable trial workspace composes its mutation as EPHEMERAL — what it writes dies with the
        // workspace — and NOTHING else: a copy isolates files, not the world, so externality,
        // authority, reversibility and subject stay exactly where the declared descents (or the
        // ceiling) left them. It composes ON TOP of a held descent and, like every producer, only
        // lowers: an operation already at Ephemeral or None gets no reduction, so no receipt.
        $confinement = $subject?->confinement;
        if ($confinement !== null && $base->effective->mutation->weight() > Mutation::Ephemeral->weight()) {
            $from = $base->effective;
            $to = new self(
                Mutation::Ephemeral,
                $from->externality,
                $from->reversibility,
                $from->authority,
                $from->escalatesOn,
                $from->subject,
                $from->rollbackContract,
            );
            $reductions = $base->reductions;
            $reductions[] = new AxisReduction('mutation', $from->mutation->value, Mutation::Ephemeral->value, 'trial-workspace', $confinement->provenance());
            $base = new ProfileComposition($to, $reductions);
        }

        // THE FOURTH PRODUCER: THE PAYLOAD'S OWNER ATTESTS THE SUBJECT (greenhouse decisions/0080). An
        // operation that carries its change as data — a promotion carries a diff — can only DECLARE the
        // worst case; the producer that owns that payload may attest what this call's change is really
        // made of, and composition lowers `subject` to it — ONE axis, with the producer and what it
        // checked on the receipt — and nothing else. Like every producer it only lowers: an attestation
        // at or above the effective subject is not a reduction and leaves no receipt; a read (no
        // mutation, subject None) has nothing to lower. It composes ON TOP of a held descent and of
        // confinement, so both receipts survive.
        $attestation = $subject?->subjectAttestation;
        if ($attestation !== null && $attestation->subject->weight() < $base->effective->subject->weight()) {
            $from = $base->effective;
            $to = new self(
                $from->mutation,
                $from->externality,
                $from->reversibility,
                $from->authority,
                $from->escalatesOn,
                $attestation->subject,
                $from->rollbackContract,
            );
            $reductions = $base->reductions;
            $reductions[] = new AxisReduction('subject', $from->subject->value, $attestation->subject->value, $attestation->producer, $attestation->provenance);
            $base = new ProfileComposition($to, $reductions);
        }

        return $base;
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
