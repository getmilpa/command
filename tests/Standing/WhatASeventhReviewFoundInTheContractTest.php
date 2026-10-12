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

namespace Milpa\Command\Tests\Standing;

use Milpa\Command\Consent\OperationId;
use Milpa\Command\Standing\ConditionReceipt;
use Milpa\Command\Standing\GrantStanding;
use Milpa\Command\Standing\GrantState;
use Milpa\Command\Standing\NamedCall;
use Milpa\Command\Standing\StandingCall;
use Milpa\Command\Standing\StandingJudge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What a seventh independent review found in the contract of standing grants (greenhouse decisions/0615, «Lo que la
 * séptima revisión encontró»). The first test is the reviewer's own, moved here as it was written. Its finding of
 * medium severity is a question for whoever decides, built as a proposal in a file of its own.
 *
 * @subject-in milpa/command
 */
final class WhatASeventhReviewFoundInTheContractTest extends TestCase
{
    /** @param array<string, mixed> $facts */
    private static function standing(array $facts = []): GrantStanding
    {
        return new GrantStanding(...$facts + ['state' => GrantState::InForce, 'signatureFault' => null, 'watcherSignalAt' => Grants::at('2026-10-11T11:50:00Z'), 'receipts' => [Grants::receipt()], 'instruments' => ['lab.scan' => Grants::INSTRUMENT], 'recordFault' => null]);
    }

    // ── L1 · a line that was not read, in the list of receipts, stops the other grants too ──────────────────

    /**
     * «What the host keeps of the grant does not read whole — its list of receipts holding something that is not a
     * receipt among the ways», and «a grant that names the call and whose record does not read whole refuses it too,
     * whatever another grant would lend». The judge says record_unread of the grant — and among() lets the call pass
     * under the other.
     */
    public function testAGrantWhoseReceiptsHoldALineThatWasNotReadStopsTheOthersToo(): void
    {
        $unread = Grants::grant(['id' => 'record-unread']);
        $other = Grants::grant(['id' => 'would-lend']);
        // What array_map(ConditionReceipt::fromArray(...), $lines) hands the judge for a line it cannot read.
        /** @phpstan-ignore-next-line */
        $withALineNotRead = self::standing(['receipts' => [Grants::receipt(), null]]);
        $saidByTheHost = self::standing(['recordFault' => 'entry 2 of its receipts cannot be read today']);

        self::assertSame('record_unread', StandingJudge::judge($unread, $withALineNotRead, Grants::call())->reason, 'control: alone, the judge says its record does not read whole');
        foreach ([[$unread, $other], [$other, $unread]] as [$first, $second]) {
            $held = static fn (GrantStanding $of): array => [
                ['grant' => $first, 'standing' => $first->id === 'record-unread' ? $of : self::standing()],
                ['grant' => $second, 'standing' => $second->id === 'record-unread' ? $of : self::standing()],
            ];
            self::assertFalse(StandingJudge::among($held($saidByTheHost), Grants::call(), null)['verdict']->passes() ?? true, 'control: the same fault, said by the host, stops the other grant');

            $answer = StandingJudge::among($held($withALineNotRead), Grants::call(), null);
            self::assertFalse($answer['verdict']->passes() ?? true, 'the judge says of «record-unread» that what the house keeps of it does not read whole, and the call passes under «' . ($answer['grant']->id ?? '?') . '»');
            self::assertSame(['record-unread', 'record_unread'], [$answer['grant']->id ?? null, $answer['verdict']->reason]);
            self::assertStringContainsString('it lends nothing while what the house keeps of «record-unread» does not read whole', $answer['verdict']->sentence);
        }
    }

    public function testItIsAskedOfTheListAndNotOfTheReasonTheJudgeGave(): void
    {
        // A signature that does not hold is said BEFORE the list is looked at: the grant's own refusal then reads
        // «signature», and the line that was not read is still in its list. Asked by the reason, it would be hidden.
        $unread = Grants::grant(['id' => 'record-unread']);
        $other = Grants::grant(['id' => 'would-lend']);
        /** @phpstan-ignore-next-line */
        $faultedAndUnread = self::standing(['signatureFault' => 'the key that signed it left the house', 'receipts' => [null]]);
        self::assertSame('signature', StandingJudge::judge($unread, $faultedAndUnread, Grants::call())->reason, 'control: alone, the signature speaks first');

        $answer = StandingJudge::among([['grant' => $unread, 'standing' => $faultedAndUnread], ['grant' => $other, 'standing' => self::standing()]], Grants::call(), null);

        self::assertFalse($answer['verdict']->passes() ?? true);
        self::assertSame('record-unread', $answer['grant']->id ?? null);
        // Control: a grant whose signature does not hold, and whose list reads, stops nobody — as before.
        $faulted = self::standing(['signatureFault' => 'the key that signed it left the house']);
        self::assertTrue(StandingJudge::among([['grant' => $unread, 'standing' => $faulted], ['grant' => $other, 'standing' => self::standing()]], Grants::call(), null)['verdict']->passes() ?? false);
    }

    // ── an identity built elsewhere cannot stand in for the name as it reads ────────────────────────────────

    /** @return iterable<string, array{string}> */
    public static function namesThatAreNotAsTheyRead(): iterable
    {
        yield 'a space before it and a line break after it' => [" lab:scan\n"];
        yield 'a separator at each end' => [':lab:scan:'];
        yield 'a null byte after it' => ["lab:scan\0"];
        yield 'a sentence' => ['not an operation !!'];
        yield 'nothing' => [''];
    }

    #[DataProvider('namesThatAreNotAsTheyRead')]
    public function testNothingInThisContractIsBuiltFromANameThatIsNotAsItReads(string $name): void
    {
        // The identity of an operation takes what stands at the ends of a name off before anything looks. So a name
        // is given here AS TEXT, and the identity is made from it after it has been looked at.
        $builds = [
            'a named call' => static fn (): object => new NamedCall($name),
            'a receipt' => static fn (): object => new ConditionReceipt($name, [], Grants::CANDIDATE, true, Grants::INSTRUMENT, null, Grants::at('2026-10-11T11:50:00Z')),
            'a call' => static fn (): object => new StandingCall($name, [], Grants::SEAT, Grants::CANDIDATE, Grants::effect(), Grants::at('2026-10-11T12:00:00Z')),
        ];
        foreach ($builds as $what => $build) {
            try {
                $build();
                self::fail("{$what} was built from " . json_encode($name));
            } catch (\InvalidArgumentException $refused) {
                self::assertSame(0, preg_match('/[^\x20-\x7E«»—…]/u', $refused->getMessage()), $what);
            }
        }
    }

    public function testAnIdentityBuiltElsewhereIsNotTakenInPlaceOfTheName(): void
    {
        // `new OperationId(" lab:scan\n")` IS «lab.scan»: what was around it is gone, and nothing can ask for it back.
        // So none of the three is built from a ready-made identity — only from the text it was made of.
        $identity = new OperationId(" lab:scan\n");
        self::assertSame('lab.scan', $identity->canonical, 'control: the identity has already lost its ends');
        $builds = [
            /** @phpstan-ignore-next-line */
            static fn (): object => new NamedCall($identity),
            /** @phpstan-ignore-next-line */
            static fn (): object => new ConditionReceipt($identity, [], Grants::CANDIDATE, true, Grants::INSTRUMENT, null, Grants::at('2026-10-11T11:50:00Z')),
            /** @phpstan-ignore-next-line */
            static fn (): object => new StandingCall($identity, [], Grants::SEAT, Grants::CANDIDATE, Grants::effect(), Grants::at('2026-10-11T12:00:00Z')),
        ];
        foreach ($builds as $at => $build) {
            try {
                $build();
                self::fail("built from an identity made elsewhere ({$at})");
            } catch (\TypeError) {
                self::assertTrue(true);
            }
        }
        // Controls: from the text, each is built, and each carries the identity; a surface's spelling is still one.
        self::assertSame('lab.scan', (new NamedCall('lab:scan'))->operation->canonical);
        self::assertSame('lab.scan', (new ConditionReceipt('lab_scan', [], Grants::CANDIDATE, true, Grants::INSTRUMENT, null, Grants::at('2026-10-11T11:50:00Z')))->by->canonical);
        self::assertSame('lab.publish', (new StandingCall('Lab:Publish', [], Grants::SEAT, null, Grants::effect(), Grants::at('2026-10-11T12:00:00Z')))->operation->canonical);
    }
}
