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
        $standing = new GrantStanding(GrantState::Suspended, 'its falsifier fired at 2026-10-11T11:55:00Z', watcherSignalAt: Grants::at('2026-10-11T11:50:00Z'), receipts: [Grants::receipt()], instruments: ['lab.scan' => Grants::INSTRUMENT]);
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
            $verdict = StandingJudge::judge(Grants::grant(), new GrantStanding($state, receipts: [Grants::receipt()]), Grants::call());

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
        $grant = Grants::grant(['conditions' => [['name' => 'no-host-names', 'statement' => 'strictly', 'by' => ['operation' => 'lab:scan', 'arguments' => ['strict' => true]]]]]);

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
        $standing = new GrantStanding(watcherSignalAt: Grants::at('2026-10-11T11:50:00Z'), receipts: [Grants::receipt()], instruments: []);

        self::assertSame('instrument_changed', StandingJudge::judge(Grants::grant(), $standing, Grants::call())->reason);
    }

    public function testEveryConditionNeedsItsOwnReceipt(): void
    {
        $grant = Grants::grant(['conditions' => [
            ['name' => 'no-host-names', 'statement' => 'names no machine', 'by' => ['operation' => 'lab:scan']],
            ['name' => 'builds', 'statement' => 'the candidate builds', 'by' => ['operation' => 'lab:build']],
        ]]);
        $scan = Grants::receipt();
        $build = Grants::receipt(by: 'lab:build');
        $instruments = ['lab.scan' => Grants::INSTRUMENT, 'lab.build' => Grants::INSTRUMENT];
        $watched = Grants::at('2026-10-11T11:50:00Z');

        $missing = StandingJudge::judge($grant, new GrantStanding(watcherSignalAt: $watched, receipts: [$scan], instruments: $instruments), Grants::call());
        self::assertSame('not_measured', $missing->reason);
        self::assertStringContainsString('«builds»', $missing->sentence);
        self::assertSame([], $missing->cited);

        $both = StandingJudge::judge($grant, new GrantStanding(watcherSignalAt: $watched, receipts: [$build, $scan], instruments: $instruments), Grants::call());
        self::assertTrue($both->passes());
        self::assertSame([$scan, $build], $both->cited);
    }

    public function testWhoseRefusalItIsComesBeforeWhatTheCallerCouldFix(): void
    {
        // Suspended AND unmeasured AND above the ceiling: the answer is the person's one.
        $standing = new GrantStanding(GrantState::Suspended, 'someone asked for it to stop');
        $verdict = StandingJudge::judge(Grants::grant(), $standing, Grants::call(subject: null, effect: Grants::effect(Reversibility::Irreversible)));

        self::assertSame(StandingCheck::InForce, $verdict->failed);

        // And a broken signature comes before the state the ledger derives from it.
        $broken = new GrantStanding(GrantState::Suspended, signatureFault: 'its bytes were altered');
        self::assertSame(StandingCheck::Signature, StandingJudge::judge(Grants::grant(), $broken, Grants::call())->failed);
    }
}
