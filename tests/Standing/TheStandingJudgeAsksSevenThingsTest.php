<?php

declare(strict_types=1);

namespace Milpa\Command\Tests\Standing;

use Milpa\Command\Effect\Externality;
use Milpa\Command\Effect\Reversibility;
use Milpa\Command\Standing\GrantStanding;
use Milpa\Command\Standing\GrantState;
use Milpa\Command\Standing\StandingCheck;
use Milpa\Command\Standing\StandingJudge;
use PHPUnit\Framework\TestCase;

/**
 * The judgment of a standing grant, one fact at a time (greenhouse decisions/0615 §3).
 *
 * Every test starts from the SAME call that passes and changes exactly one thing. So each refusal here
 * is also the control of its own check: remove that check from the judge and the test that changed that
 * one fact goes red — the call it expects refused would pass.
 */
final class TheStandingJudgeAsksSevenThingsTest extends TestCase
{
    public function testACallWithEverythingInOrderPassesAndCitesTheReceiptItRanOn(): void
    {
        $receipt = Grants::receipt();
        $verdict = StandingJudge::judge(Grants::grant(), Grants::standing([$receipt]), Grants::call());

        self::assertTrue($verdict->passes());
        self::assertTrue($verdict->named());
        self::assertNull($verdict->failed);
        self::assertSame('passed', $verdict->reason);
        self::assertSame([$receipt], $verdict->cited);
        self::assertStringContainsString(Grants::CANDIDATE, $verdict->sentence);
    }

    public function testAGrantThatDoesNotLendToThisSeatDoesNotNameTheCall(): void
    {
        $verdict = StandingJudge::judge(Grants::grant(), Grants::standing(), Grants::call(seat: 'ANOTHER-SEAT'));

        self::assertFalse($verdict->passes());
        self::assertFalse($verdict->named());
        self::assertSame(StandingCheck::Named, $verdict->failed);
        self::assertSame('not_named', $verdict->reason);
    }

    public function testAnArgumentTheGrantFixesAndTheCallChangesIsAnotherCall(): void
    {
        $verdict = StandingJudge::judge(Grants::grant(), Grants::standing(), Grants::call(arguments: ['channel' => 'beta', 'path' => 'candidate.tar']));

        self::assertFalse($verdict->named());
    }

    public function testAnotherOperationIsNotNamed(): void
    {
        $call = new \Milpa\Command\Standing\StandingCall('lab:merge', ['channel' => 'stable'], Grants::SEAT, Grants::CANDIDATE, Grants::effect(), Grants::at());

        self::assertFalse(StandingJudge::judge(Grants::grant(), Grants::standing(), $call)->named());
    }

    public function testASignatureThatDoesNotHoldNowRefusesAndSaysWhy(): void
    {
        $standing = new GrantStanding(signatureFault: 'the key that signed it no longer answers for this seat', watcherSignalAt: Grants::at('2026-10-11T11:50:00Z'), receipts: [Grants::receipt()], instruments: ['lab.scan' => Grants::INSTRUMENT]);
        $verdict = StandingJudge::judge(Grants::grant(), $standing, Grants::call());

        self::assertTrue($verdict->named());
        self::assertSame(StandingCheck::Signature, $verdict->failed);
        self::assertSame('signature', $verdict->reason);
        self::assertStringContainsString('no longer answers for this seat', $verdict->sentence);
    }

    public function testASuspendedGrantRefusesItsWholeClassAndSaysOnlyAPersonLiftsIt(): void
    {
        $standing = new GrantStanding(GrantState::Suspended, 'its falsifier fired at 2026-10-11T11:55:00Z', signatureFault: null, watcherSignalAt: Grants::at('2026-10-11T11:50:00Z'), receipts: [Grants::receipt()], instruments: ['lab.scan' => Grants::INSTRUMENT]);
        $verdict = StandingJudge::judge(Grants::grant(), $standing, Grants::call());

        self::assertSame(StandingCheck::InForce, $verdict->failed);
        self::assertSame('suspended', $verdict->reason);
        self::assertStringContainsString('its falsifier fired', $verdict->sentence);
        self::assertStringContainsString('a person lifts it with a new signature', $verdict->sentence);
        self::assertStringContainsString('No seat can', $verdict->sentence);
    }

    public function testASupersededOrRevokedGrantLendsNothingAndNothingBringsItBack(): void
    {
        foreach ([GrantState::Superseded, GrantState::Revoked] as $state) {
            $verdict = StandingJudge::judge(Grants::grant(), new GrantStanding($state, signatureFault: null, receipts: [Grants::receipt()]), Grants::call());

            self::assertSame(StandingCheck::InForce, $verdict->failed);
            self::assertSame($state->value, $verdict->reason);
            self::assertStringContainsString('a person gives a new grant', $verdict->sentence);
            self::assertStringNotContainsString('lifts it', $verdict->sentence);
        }
    }

    public function testAGrantPastItsOwnDateRefuses(): void
    {
        $grant = Grants::grant(['expires_at' => '2026-10-11T12:00:00Z']);

        self::assertTrue(StandingJudge::judge($grant, Grants::standing(), Grants::call(at: '2026-10-11T11:59:59Z'))->passes());
        $verdict = StandingJudge::judge($grant, Grants::standing(), Grants::call(at: '2026-10-11T12:00:00Z'));
        self::assertSame(StandingCheck::InForce, $verdict->failed);
        self::assertSame('expired', $verdict->reason);
    }

    public function testAWatcherThatNeverGaveASignIsNotGreen(): void
    {
        $verdict = StandingJudge::judge(Grants::grant(), Grants::standing(watcherSignalAt: null), Grants::call());

        self::assertSame(StandingCheck::Watched, $verdict->failed);
        self::assertSame('never_watched', $verdict->reason);
        self::assertStringContainsString('Run `lab:watch`', $verdict->sentence);
    }

    public function testAWatcherThatWentQuietPastItsWindowIsNotGreen(): void
    {
        // within_seconds is 3600: a sign exactly that old still counts; one second older does not.
        self::assertTrue(StandingJudge::judge(Grants::grant(), Grants::standing(watcherSignalAt: '2026-10-11T11:00:00Z'), Grants::call())->passes());

        $verdict = StandingJudge::judge(Grants::grant(), Grants::standing(watcherSignalAt: '2026-10-11T10:59:59Z'), Grants::call());
        self::assertSame(StandingCheck::Watched, $verdict->failed);
        self::assertSame('watcher_quiet', $verdict->reason);
    }

    public function testASignOfLifeFromTheFutureIsNotASignOfLife(): void
    {
        $verdict = StandingJudge::judge(Grants::grant(), Grants::standing(watcherSignalAt: '2026-10-11T12:00:01Z'), Grants::call());

        self::assertSame('watcher_quiet', $verdict->reason);
    }

    public function testACallThatCannotSayWhatItActsOnIsLentNothing(): void
    {
        $verdict = StandingJudge::judge(Grants::grant(), Grants::standing(), Grants::call(subject: null));

        self::assertSame(StandingCheck::Subject, $verdict->failed);
        self::assertSame('no_subject', $verdict->reason);
    }

    public function testAnEffectAboveTheCeilingRefusesEvenWithEveryConditionGreen(): void
    {
        foreach ([Grants::effect(Reversibility::Irreversible), Grants::effect(externality: Externality::Unknown)] as $effect) {
            $verdict = StandingJudge::judge(Grants::grant(), Grants::standing(), Grants::call(effect: $effect));

            self::assertSame(StandingCheck::Ceiling, $verdict->failed);
            self::assertSame('above_ceiling', $verdict->reason);
            self::assertStringContainsString('reversibility «compensatable»', $verdict->sentence);
        }
    }

    public function testACandidateNobodyMeasuredIsRefused(): void
    {
        $verdict = StandingJudge::judge(Grants::grant(), Grants::standing([]), Grants::call());

        self::assertSame(StandingCheck::Conditions, $verdict->failed);
        self::assertSame('not_measured', $verdict->reason);
        self::assertStringContainsString('Run `lab:scan`', $verdict->sentence);
    }

    public function testAReceiptOnAnotherCandidateIsNotThisOne(): void
    {
        $verdict = StandingJudge::judge(Grants::grant(), Grants::standing([Grants::receipt('sha256:candidate-b')]), Grants::call());

        self::assertSame('not_measured', $verdict->reason);
    }

    public function testAReceiptFromAnotherOperationDoesNotMeasureTheCondition(): void
    {
        $verdict = StandingJudge::judge(Grants::grant(), Grants::standing([Grants::receipt(by: 'lab:lint')]), Grants::call());

        self::assertSame('not_measured', $verdict->reason);
    }

    public function testAnArgumentTheConditionFixesForItsInstrumentMustHaveBeenCarried(): void
    {
        $grant = Grants::grant(['conditions' => [['name' => 'no-host-names', 'statement' => 'strictly', 'by' => ['operation' => 'lab:scan', 'arguments' => ['strict' => true], 'free' => ['path']]]]]);

        self::assertSame('not_measured', StandingJudge::judge($grant, Grants::standing(), Grants::call())->reason);
        self::assertTrue(StandingJudge::judge($grant, Grants::standing([Grants::receipt(arguments: ['path' => 'candidate.tar', 'strict' => true])]), Grants::call())->passes());
    }

    public function testACandidateMeasuredRedIsRefused(): void
    {
        $verdict = StandingJudge::judge(Grants::grant(), Grants::standing([Grants::receipt(ok: false)]), Grants::call());

        self::assertSame(StandingCheck::Conditions, $verdict->failed);
        self::assertSame('measured_red', $verdict->reason);
    }

    public function testTheLastReceiptAboutACandidateIsWhatIsKnownOfItNow(): void
    {
        // Ledger order, not the clock: green then red is red; red then green is green.
        self::assertSame('measured_red', StandingJudge::judge(Grants::grant(), Grants::standing([Grants::receipt(), Grants::receipt(ok: false)]), Grants::call())->reason);

        $green = Grants::receipt();
        $verdict = StandingJudge::judge(Grants::grant(), Grants::standing([Grants::receipt(ok: false), $green]), Grants::call());
        self::assertTrue($verdict->passes());
        self::assertSame([$green], $verdict->cited);
    }

    public function testWhatAnOlderInstrumentFoundDoesNotHoldForTheOneThatReplacedIt(): void
    {
        $verdict = StandingJudge::judge(Grants::grant(), Grants::standing([Grants::receipt(instrument: 'sha256:the-scanner-before-it-was-hardened')]), Grants::call());

        self::assertSame(StandingCheck::Conditions, $verdict->failed);
        self::assertSame('instrument_changed', $verdict->reason);
    }

    public function testAnInstrumentTheHouseNoLongerHasMeasuresNothing(): void
    {
        $standing = new GrantStanding(signatureFault: null, watcherSignalAt: Grants::at('2026-10-11T11:50:00Z'), receipts: [Grants::receipt()], instruments: []);

        self::assertSame('instrument_changed', StandingJudge::judge(Grants::grant(), $standing, Grants::call())->reason);
    }

    public function testEveryConditionNeedsItsOwnReceipt(): void
    {
        $grant = Grants::grant(['conditions' => [
            ['name' => 'no-host-names', 'statement' => 'names no machine', 'by' => ['operation' => 'lab:scan', 'free' => ['path']]],
            ['name' => 'builds', 'statement' => 'the candidate builds', 'by' => ['operation' => 'lab:build', 'free' => ['path']]],
        ]]);
        $scan = Grants::receipt();
        $build = Grants::receipt(by: 'lab:build');
        $instruments = ['lab.scan' => Grants::INSTRUMENT, 'lab.build' => Grants::INSTRUMENT];
        $watched = Grants::at('2026-10-11T11:50:00Z');

        $missing = StandingJudge::judge($grant, new GrantStanding(signatureFault: null, watcherSignalAt: $watched, receipts: [$scan], instruments: $instruments), Grants::call());
        self::assertSame('not_measured', $missing->reason);
        self::assertStringContainsString('«builds»', $missing->sentence);
        self::assertSame([], $missing->cited);

        $both = StandingJudge::judge($grant, new GrantStanding(signatureFault: null, watcherSignalAt: $watched, receipts: [$build, $scan], instruments: $instruments), Grants::call());
        self::assertTrue($both->passes());
        self::assertSame([$scan, $build], $both->cited);
    }

    public function testWhoseRefusalItIsComesBeforeWhatTheCallerCouldFix(): void
    {
        // Suspended AND unmeasured AND above the ceiling: the answer is the person's one.
        $standing = new GrantStanding(GrantState::Suspended, 'someone asked for it to stop', signatureFault: null);
        $verdict = StandingJudge::judge(Grants::grant(), $standing, Grants::call(subject: null, effect: Grants::effect(Reversibility::Irreversible)));

        self::assertSame(StandingCheck::InForce, $verdict->failed);

        // And a broken signature comes before the state the ledger derives from it.
        $broken = new GrantStanding(GrantState::Suspended, signatureFault: 'its bytes were altered');
        self::assertSame(StandingCheck::Signature, StandingJudge::judge(Grants::grant(), $broken, Grants::call())->failed);
    }

    /**
     * @param array<string, mixed> $change
     *
     * @return array{grant: \Milpa\Command\Standing\StandingGrant, standing: GrantStanding}
     */
    private static function held(string $id, ?GrantStanding $standing = null, array $change = []): array
    {
        return ['grant' => Grants::grant(['id' => $id] + $change), 'standing' => $standing ?? Grants::standing()];
    }

    private static function suspended(): GrantStanding
    {
        return new GrantStanding(GrantState::Suspended, 'its falsifier fired', signatureFault: null, watcherSignalAt: Grants::at('2026-10-11T11:50:00Z'), receipts: [Grants::receipt()], instruments: ['lab.scan' => Grants::INSTRUMENT]);
    }

    public function testAmongSeveralGrantsOneThatPassesIsEnough(): void
    {
        $unmeasured = self::held('asks-more', change: ['conditions' => [['name' => 'builds', 'statement' => 'it builds', 'by' => ['operation' => 'lab:build', 'free' => ['path']]]]]);
        $answer = StandingJudge::among([$unmeasured, self::held('first-that-passes'), self::held('also-passes')], Grants::call());

        self::assertNotNull($answer);
        self::assertTrue($answer['verdict']->passes());
        self::assertSame('first-that-passes', $answer['grant']->id);
    }

    public function testNoGrantThatNamesTheCallIsNoAnswerAtAll(): void
    {
        self::assertNull(StandingJudge::among([], Grants::call()));
        self::assertNull(StandingJudge::among([self::held('for-another-seat'), self::held('suspended-for-another-seat', self::suspended())], Grants::call(seat: 'ANOTHER-SEAT')));
    }

    public function testASuspendedGrantThatNamesTheCallRefusesItWhateverAnotherWouldLend(): void
    {
        // Shrinking wins: a suspension a second grant walks around is not a suspension. Given before it or after it.
        foreach ([[self::held('stopped', self::suspended()), self::held('would-lend')], [self::held('would-lend'), self::held('stopped', self::suspended())]] as $grants) {
            self::assertTrue(StandingJudge::judge($grants[0]['grant'], Grants::standing(), Grants::call())->passes(), 'control: alone and in force, each of them lends');

            $answer = StandingJudge::among($grants, Grants::call());

            self::assertNotNull($answer);
            self::assertFalse($answer['verdict']->passes());
            self::assertSame('stopped', $answer['grant']->id);
            self::assertSame(StandingCheck::InForce, $answer['verdict']->failed);
            self::assertSame('suspended', $answer['verdict']->reason);
            self::assertStringContainsString('Standing grant «stopped» is suspended', $answer['verdict']->sentence);
            self::assertStringContainsString('Another grant names this call too, and it lends nothing while «stopped» is suspended', $answer['verdict']->sentence);
            self::assertStringContainsString('gives a grant that supersedes «stopped» by name', $answer['verdict']->sentence);
        }
    }

    public function testASuspendedGrantSpeaksEvenWhenAnotherRefusalGotFurther(): void
    {
        // The caller could measure its candidate, and it would change nothing: the suspension is what decides.
        $answer = StandingJudge::among([self::held('stopped', self::suspended()), self::held('unmeasured', Grants::standing([]))], Grants::call());

        self::assertSame('stopped', $answer['grant']->id ?? null);
        self::assertSame('suspended', $answer['verdict']->reason ?? null);
    }

    public function testASuspendedGrantAloneSaysOnlyWhatItSaidBefore(): void
    {
        $alone = StandingJudge::among([self::held('stopped', self::suspended())], Grants::call());
        $judged = StandingJudge::judge(Grants::grant(['id' => 'stopped']), self::suspended(), Grants::call());

        self::assertSame($judged->sentence, $alone['verdict']->sentence ?? null);
        self::assertStringNotContainsString('Another grant', $judged->sentence);
    }

    public function testASuspendedGrantWhoseSignatureDoesNotHoldStillStopsTheCall(): void
    {
        $broken = new GrantStanding(GrantState::Suspended, 'stopped', signatureFault: 'the key that signed it no longer holds the word');
        $answer = StandingJudge::among([self::held('stopped', $broken), self::held('would-lend')], Grants::call());

        self::assertFalse($answer['verdict']->passes() ?? true);
        self::assertSame(StandingCheck::Signature, $answer['verdict']->failed ?? null);
        self::assertStringContainsString('lends nothing while «stopped» is suspended', $answer['verdict']->sentence ?? '');
    }

    public function testASuspendedGrantThatDoesNotNameTheCallStopsNothing(): void
    {
        $elsewhere = self::held('stopped', self::suspended(), ['covers' => [['operation' => 'lab:publish', 'arguments' => ['channel' => 'beta']]]]);
        $answer = StandingJudge::among([$elsewhere, self::held('would-lend')], Grants::call());

        self::assertTrue($answer['verdict']->passes() ?? false);
        self::assertSame('would-lend', $answer['grant']->id ?? null);
    }

    public function testExpiredRevokedAndSupersededGrantsStopNobody(): void
    {
        $gone = [
            self::held('revoked', new GrantStanding(GrantState::Revoked, signatureFault: null)),
            self::held('superseded', new GrantStanding(GrantState::Superseded, signatureFault: null)),
            self::held('expired', change: ['expires_at' => '2026-10-11T00:00:00Z']),
        ];

        $answer = StandingJudge::among([...$gone, self::held('would-lend')], Grants::call());
        self::assertTrue($answer['verdict']->passes() ?? false);
        self::assertSame('would-lend', $answer['grant']->id ?? null);

        // And with nothing left that lends, they refuse — each as what it is, the later one speaking.
        self::assertSame('expired', StandingJudge::among($gone, Grants::call())['verdict']->reason ?? null);
    }

    public function testWhenNoneLendsTheRefusalThatGotFurthestSpeaksWhicheverWasGivenFirst(): void
    {
        $unwatched = self::held('unwatched', Grants::standing(watcherSignalAt: null));
        $unmeasured = self::held('unmeasured', Grants::standing([]));

        foreach ([[$unwatched, $unmeasured], [$unmeasured, $unwatched]] as $grants) {
            $answer = StandingJudge::among($grants, Grants::call());

            self::assertSame('unmeasured', $answer['grant']->id ?? null, 'the one the caller can do something about');
            self::assertSame(StandingCheck::Conditions, $answer['verdict']->failed ?? null);
        }
    }

    public function testLevelWithAnotherTheLaterGrantSpeaks(): void
    {
        $answer = StandingJudge::among([self::held('older', new GrantStanding(GrantState::Superseded, signatureFault: null)), self::held('newer', change: ['expires_at' => '2026-10-11T00:00:00Z'])], Grants::call());

        self::assertSame('newer', $answer['grant']->id ?? null);
        self::assertSame('expired', $answer['verdict']->reason ?? null);
    }
}
