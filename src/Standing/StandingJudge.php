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

namespace Milpa\Command\Standing;

use Milpa\Command\Effect\EffectProfile;

/**
 * THE JUDGMENT: does this standing grant lend anything to this call, right now? (greenhouse decisions/0615 §3)
 *
 * Pure. It reads a grant, what a host knows of it at this moment and the call as facts, and answers.
 * It opens no file and asks no clock but the one it is handed — so every way it can fail is a case a
 * test can write down, and a host in any language can be held to the same seven questions.
 *
 * ── WHAT IT DOES NOT DO ─────────────────────────────────────────────────────────────────────────
 *
 * Grant. Widen. Restore. It refuses or lets pass, and when the grant does not name the call it says
 * exactly that and nothing else: the call is then judged as it was before any grant existed.
 * Growing back is a person's new signature and happens somewhere else entirely.
 *
 * ── WHY THE ORDER ───────────────────────────────────────────────────────────────────────────────
 *
 * Whose refusal it is comes first. A grant that is suspended is the PERSON's to lift, and saying so
 * matters more than telling the caller its candidate was not measured: measuring would not help. Only
 * a grant that stands asks what the caller can actually fix — the subject, the ceiling, the conditions.
 *
 * ── MORE THAN ONE GRANT ─────────────────────────────────────────────────────────────────────────
 *
 * {@see self::judge()} answers for one grant. A house may hold several that name the same call, and how
 * their answers combine is part of the judgment too: {@see self::among()}.
 *
 * ── THE ONE COMPARATOR ──────────────────────────────────────────────────────────────────────────
 *
 * The ceiling is asked with {@see EffectProfile::isNoWiderThan()} and with nothing else. A second
 * ordering here would be the duplicate judge: the day the two disagree, whichever one a reader
 * happens to consult decides authority.
 */
final class StandingJudge
{
    /** The seven checks, in order; the first that fails is the verdict. */
    public static function judge(StandingGrant $grant, GrantStanding $standing, StandingCall $call): StandingVerdict
    {
        $id = $grant->id;
        $act = $grant->lendsTo($call->seat) ? $grant->covering($call->operation, $call->arguments) : null;
        if ($act === null) {
            return StandingVerdict::refuse(StandingCheck::Named, 'not_named', "Standing grant «{$id}» does not name this call.");
        }
        $operation = Canonical::short($call->operation->forCli());

        if ($standing->signatureFault !== null) {
            return StandingVerdict::refuse(StandingCheck::Signature, 'signature', \sprintf(
                'Standing grant «%s» names this call and is not honoured: %s. Nothing is lent under it. A person gives it again with a new signature; no seat can.',
                $id,
                Canonical::said($standing->signatureFault),
            ));
        }
        // What the host keeps of this grant is asked with the signature, before any of it is read: a receipt, a
        // sign of the watcher or a stop that can no longer be read is not «none» — the line may be the one that says no.
        if ($standing->recordFault !== null) {
            return StandingVerdict::refuse(StandingCheck::Signature, 'record_unread', \sprintf(
                'Standing grant «%s» names this call and is not honoured: what the house keeps of it does not read whole — %s. Nothing is lent under it until its record reads again; a line that cannot be read is never read as «nothing happened».',
                $id,
                Canonical::said($standing->recordFault),
            ));
        }
        // …AND WHAT IS IN THE LIST OF RECEIPTS AND IS NOT ONE IS A LINE THAT WAS NOT READ. The reader of a line answers
        // `null` for one it cannot read, and a list built by mapping that reader over the lines holds the `null`:
        // skipped, a red receipt that no longer reads left the green before it speaking.
        $receipts = [];
        foreach ($standing->receipts as $at => $receipt) {
            if (!$receipt instanceof ConditionReceipt) {
                return StandingVerdict::refuse(StandingCheck::Signature, 'record_unread', \sprintf(
                    'Standing grant «%s» names this call and is not honoured: what the house keeps of it does not read whole — entry %d of its receipts is not a receipt (%s). Nothing is lent under it until its record reads again; a line that cannot be read is never read as «nothing happened».',
                    $id,
                    (int) $at + 1,
                    get_debug_type($receipt),
                ));
            }
            $receipts[] = $receipt;
        }

        if ($standing->state !== GrantState::InForce) {
            return StandingVerdict::refuse(StandingCheck::InForce, $standing->state->value, \sprintf(
                'Standing grant «%s» is %s%s. %s',
                $id,
                $standing->state->value,
                $standing->because === null ? '' : ' — ' . Canonical::said($standing->because),
                $standing->state === GrantState::Suspended
                    ? 'While it is, every act it covers is the person\'s again, this one included: a person lifts it with a new signature that names this suspension. No seat can, and neither can the house.'
                    : 'It lends nothing, and nothing brings it back: a person gives a new grant.',
            ));
        }
        if ($grant->expiresAt !== null && $call->at >= $grant->expiresAt) {
            return StandingVerdict::refuse(StandingCheck::InForce, 'expired', \sprintf(
                'Standing grant «%s» expired at %s. It lends nothing, and nothing brings it back: a person gives a new grant.',
                $id,
                $grant->expiresAt->format(\DATE_ATOM),
            ));
        }

        $watcher = Canonical::short($grant->falsifier->watcher->operation->forCli());
        $signal = $standing->watcherSignalAt;
        if ($signal === null) {
            return StandingVerdict::refuse(StandingCheck::Watched, 'never_watched', \sprintf(
                'Standing grant «%s» is not being watched: `%s`, which watches its falsifier %s, has given no sign of life since the grant was given or last restored. No sign is not green. Run `%s`.',
                $id,
                $watcher,
                Canonical::shown($grant->falsifier->name),
                $watcher,
            ));
        }
        $age = $call->at->getTimestamp() - $signal->getTimestamp();
        if ($age < 0 || $age > $grant->falsifier->withinSeconds) {
            return StandingVerdict::refuse(StandingCheck::Watched, 'watcher_quiet', \sprintf(
                'Standing grant «%s» is not being watched: `%s`, which watches its falsifier %s, last gave a sign of life at %s, and the grant asks for one within %d seconds. A watcher that went quiet is not green. Run `%s`.',
                $id,
                $watcher,
                Canonical::shown($grant->falsifier->name),
                $signal->format(\DATE_ATOM),
                $grant->falsifier->withinSeconds,
                $watcher,
            ));
        }

        if ($call->subject === null) {
            return StandingVerdict::refuse(StandingCheck::Subject, 'no_subject', \sprintf(
                'Standing grant «%s» lends nothing to this call: `%s` did not say what content the call acts on, so no condition can be bound to it. Not being able to look is not having looked.',
                $id,
                $operation,
            ));
        }

        if (!$call->effect->isNoWiderThan($grant->ceiling)) {
            return StandingVerdict::refuse(StandingCheck::Ceiling, 'above_ceiling', \sprintf(
                'Standing grant «%s» lends nothing to this call: its effect (%s) is wider than the grant\'s ceiling (%s). No condition measured on the candidate changes that — an act above the ceiling is a person\'s, call by call.',
                $id,
                self::axes($call->effect),
                self::axes($grant->ceiling),
            ));
        }

        $cited = [];
        foreach ($grant->conditions as $condition) {
            $measuring = Canonical::short($condition->by->operation->forCli());
            $last = null;
            // WHAT SPEAKS FOR A CONDITION IS READ CLOSED, AND A «NO» COUNTS FROM ANY RUN (decided by Rod, 2026-10-12).
            // A green receipt counts only from the run the condition describes — a lenient run that says yes measured
            // something else. A red one is that instrument saying no of this very content, whatever arguments the run
            // carried: one more, the fixed one missing or at another value, or none at all. It stops the act until
            // the content is measured again by the run the condition describes. Another content, or another
            // operation, says nothing of this condition.
            foreach ($receipts as $receipt) {
                if ($receipt->subject === $call->subject
                    && ($condition->by->matchesExactly($receipt->by, $receipt->arguments) || (!$receipt->ok && $condition->by->operation->is($receipt->by)))) {
                    $last = $receipt;
                }
            }
            if ($last === null) {
                return StandingVerdict::refuse(StandingCheck::Conditions, 'not_measured', \sprintf(
                    'Standing grant «%s» lends nothing to this call: condition %s was never measured on this candidate (%s). Run `%s` on it first — a measurement taken on another candidate, or after the act, is not this one.',
                    $id,
                    Canonical::shown($condition->name),
                    Canonical::shown($call->subject),
                    $measuring,
                ));
            }
            if (($standing->instruments[$last->by->canonical] ?? null) !== $last->instrument) {
                return StandingVerdict::refuse(StandingCheck::Conditions, 'instrument_changed', \sprintf(
                    'Standing grant «%s» lends nothing to this call: condition %s was measured on this candidate by `%s` as it was then, and it is not that instrument any more. What the old one found does not hold for the new one. Run `%s` again.',
                    $id,
                    Canonical::shown($condition->name),
                    $measuring,
                    $measuring,
                ));
            }
            if (!$last->ok) {
                return StandingVerdict::refuse(StandingCheck::Conditions, 'measured_red', \sprintf(
                    'Standing grant «%s» lends nothing to this call: condition %s does not hold on this candidate (%s) — `%s` measured it and said no.',
                    $id,
                    Canonical::shown($condition->name),
                    Canonical::shown($call->subject),
                    $measuring,
                ));
            }
            $cited[] = $last;
        }

        return StandingVerdict::pass(\sprintf(
            'Standing grant «%s» covers this call: %d condition(s) measured green on %s, its effect under the ceiling, its falsifier watched.',
            $id,
            \count($cited),
            Canonical::shown($call->subject),
        ), $cited);
    }

    /**
     * ONE CALL UNDER EVERY GRANT A HOST HOLDS: which answer stands — or `null` when none of them names the call.
     *
     * Each grant is judged by {@see self::judge()}, alone; this only says how their answers combine, and it adds
     * no criterion to any of them:
     *
     *   · A grant that names the call and is SUSPENDED refuses it, whatever another grant would lend. Shrinking
     *     wins (greenhouse decisions/0613 §4): a suspension that a second grant walks around is not a suspension.
     *     What takes a grant out of the way is a person's act that names it — lifting that suspension, or giving
     *     a grant that supersedes it — and a host then no longer reports it as suspended. Expired, revoked and
     *     superseded grants are not suspended: they lend nothing and stop nobody.
     *   · A grant that names the call and WHOSE RECORD DOES NOT READ WHOLE ({@see GrantStanding::$recordFault})
     *     refuses it too, whatever another grant would lend: the line that cannot be read may be the one that
     *     stopped it, and a stop that cannot be read is not «no stop». A suspension that reads speaks before it.
     *   · Otherwise one grant that passes is enough.
     *   · Otherwise the refusal that got furthest through the seven checks speaks — it is the one the caller is
     *     closest to — and, level with another, the later grant's.
     *
     * AND THE RECORD AS A WHOLE HAS TO BE SPOKEN FOR (`$recordFault`). Not everything a host can no longer read is
     * the record of ONE grant it can hand in. A document it kept and can no longer read is no {@see StandingGrant}
     * at all ({@see StandingGrant::fromJson()}), so it cannot be in the list — and it may be the grant that names
     * this call, stopped. So may a line it cannot attribute to one grant. A host says here whether everything it
     * keeps of standing grants reads: `null` when it does, the reason when it does not — and there is no default,
     * because skipping what cannot be read is the easy thing to do. When it does not read whole, NOTHING IS LENT TO
     * ANY SEAT, whatever grants were handed in and whether or not one of them names the call: the answer carries no
     * grant, and it is never `null`. A host that would rather refuse before it asks may; one that asks says it.
     *
     * @param list<array{grant: StandingGrant, standing: GrantStanding}> $grants      every grant the host holds that
     *                                                                                may concern the call, in the
     *                                                                                order they were given
     * @param string|null                                                $recordFault why the host's record of standing
     *                                                                                grants does not read whole, or
     *                                                                                `null`: every line and every
     *                                                                                document it keeps reads
     *
     * @return array{grant: StandingGrant|null, verdict: StandingVerdict}|null
     */
    public static function among(array $grants, StandingCall $call, ?string $recordFault): ?array
    {
        if ($recordFault !== null) {
            return ['grant' => null, 'verdict' => StandingVerdict::refuse(StandingCheck::Signature, 'record_unread', \sprintf(
                'What the house keeps of its standing grants does not read whole — %s. Nothing is lent to any seat until it reads again: a grant that cannot be read may be the one that names this call, and a stop that cannot be read is never read as «no stop».',
                Canonical::said($recordFault),
            ))];
        }
        $suspended = null;
        $unread = null;
        $passed = null;
        $furthest = null;
        $naming = 0;
        foreach ($grants as ['grant' => $grant, 'standing' => $standing]) {
            $verdict = self::judge($grant, $standing, $call);
            if (!$verdict->named()) {
                continue;
            }
            ++$naming;
            $answer = ['grant' => $grant, 'verdict' => $verdict];
            if ($standing->state === GrantState::Suspended) {
                $suspended = $answer;
            } elseif ($standing->recordFault !== null) {
                $unread ??= $answer;
            } elseif ($verdict->passes()) {
                $passed ??= $answer;
            } elseif ($furthest === null || self::reached($verdict) >= self::reached($furthest['verdict'])) {
                $furthest = $answer;
            }
        }

        if ($suspended !== null && $naming > 1) {
            $verdict = $suspended['verdict'];

            return ['grant' => $suspended['grant'], 'verdict' => StandingVerdict::refuse(
                $verdict->failed ?? StandingCheck::InForce,
                $verdict->reason,
                $verdict->sentence . \sprintf(
                    ' Another grant names this call too, and it lends nothing while «%1$s» is suspended: a person lifts that suspension, or gives a grant that supersedes «%1$s» by name.',
                    $suspended['grant']->id,
                ),
            )];
        }

        // Reached with more than one grant only when no suspension spoke: a stop that reads is said first, above.
        if ($unread !== null && $naming > 1) {
            $verdict = $unread['verdict'];

            return ['grant' => $unread['grant'], 'verdict' => StandingVerdict::refuse(
                $verdict->failed ?? StandingCheck::Signature,
                $verdict->reason,
                $verdict->sentence . \sprintf(
                    ' Another grant names this call too, and it lends nothing while what the house keeps of «%1$s» does not read whole: the line that cannot be read may be the one that stopped it.',
                    $unread['grant']->id,
                ),
            )];
        }

        return $suspended ?? $unread ?? $passed ?? $furthest;
    }

    /** How far through the seven checks a refusal got. */
    private static function reached(StandingVerdict $verdict): int
    {
        return (int) array_search($verdict->failed, StandingCheck::cases(), true);
    }

    /** A profile's five axes as a reader sees them — a description, never a comparison. */
    private static function axes(EffectProfile $profile): string
    {
        return \sprintf(
            'mutation «%s», externality «%s», reversibility «%s», authority «%s», subject «%s»',
            $profile->mutation->value,
            $profile->externality->value,
            $profile->reversibility->value,
            $profile->authority->value,
            $profile->subject->value,
        );
    }
}
