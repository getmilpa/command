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
        $operation = $call->operation->forCli();

        if ($standing->signatureFault !== null) {
            return StandingVerdict::refuse(StandingCheck::Signature, 'signature', \sprintf(
                'Standing grant «%s» names this call and is not honoured: %s. Nothing is lent under it. A person gives it again with a new signature; no seat can.',
                $id,
                $standing->signatureFault,
            ));
        }

        if ($standing->state !== GrantState::InForce) {
            return StandingVerdict::refuse(StandingCheck::InForce, $standing->state->value, \sprintf(
                'Standing grant «%s» is %s%s. %s',
                $id,
                $standing->state->value,
                $standing->because === null ? '' : ' — ' . $standing->because,
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

        $watcher = $grant->falsifier->watcher->operation->forCli();
        $signal = $standing->watcherSignalAt;
        if ($signal === null) {
            return StandingVerdict::refuse(StandingCheck::Watched, 'never_watched', \sprintf(
                'Standing grant «%s» is not being watched: `%s`, which watches its falsifier «%s», has given no sign of life since the grant was given or last restored. No sign is not green. Run `%s`.',
                $id,
                $watcher,
                $grant->falsifier->name,
                $watcher,
            ));
        }
        $age = $call->at->getTimestamp() - $signal->getTimestamp();
        if ($age < 0 || $age > $grant->falsifier->withinSeconds) {
            return StandingVerdict::refuse(StandingCheck::Watched, 'watcher_quiet', \sprintf(
                'Standing grant «%s» is not being watched: `%s`, which watches its falsifier «%s», last gave a sign of life at %s, and the grant asks for one within %d seconds. A watcher that went quiet is not green. Run `%s`.',
                $id,
                $watcher,
                $grant->falsifier->name,
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
            $measuring = $condition->by->operation->forCli();
            $last = null;
            foreach ($standing->receipts as $receipt) {
                if ($receipt->subject === $call->subject && $condition->by->matches($receipt->by, $receipt->arguments)) {
                    $last = $receipt;
                }
            }
            if ($last === null) {
                return StandingVerdict::refuse(StandingCheck::Conditions, 'not_measured', \sprintf(
                    'Standing grant «%s» lends nothing to this call: condition «%s» was never measured on this candidate (%s). Run `%s` on it first — a measurement taken on another candidate, or after the act, is not this one.',
                    $id,
                    $condition->name,
                    $call->subject,
                    $measuring,
                ));
            }
            if (($standing->instruments[$last->by->canonical] ?? null) !== $last->instrument) {
                return StandingVerdict::refuse(StandingCheck::Conditions, 'instrument_changed', \sprintf(
                    'Standing grant «%s» lends nothing to this call: condition «%s» was measured on this candidate by `%s` as it was then, and it is not that instrument any more. What the old one found does not hold for the new one. Run `%s` again.',
                    $id,
                    $condition->name,
                    $measuring,
                    $measuring,
                ));
            }
            if (!$last->ok) {
                return StandingVerdict::refuse(StandingCheck::Conditions, 'measured_red', \sprintf(
                    'Standing grant «%s» lends nothing to this call: condition «%s» does not hold on this candidate (%s) — `%s` measured it and said no.',
                    $id,
                    $condition->name,
                    $call->subject,
                    $measuring,
                ));
            }
            $cited[] = $last;
        }

        return StandingVerdict::pass(\sprintf(
            'Standing grant «%s» covers this call: %d condition(s) measured green on %s, its effect under the ceiling, its falsifier watched.',
            $id,
            \count($cited),
            $call->subject,
        ), $cited);
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
