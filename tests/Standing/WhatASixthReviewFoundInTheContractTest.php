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

use Milpa\Command\Standing\Canonical;
use Milpa\Command\Standing\ConditionReceipt;
use Milpa\Command\Standing\GrantStanding;
use Milpa\Command\Standing\GrantState;
use Milpa\Command\Standing\StandingCheck;
use Milpa\Command\Standing\StandingGrant;
use Milpa\Command\Standing\StandingJudge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What a sixth independent review found in the contract of standing grants (greenhouse decisions/0615, «Lo que la
 * sexta revisión encontró»). Four of these tests are the reviewer's own, moved here as they were written but for the
 * third argument `among()` now asks for; every text is built from code points, never typed.
 *
 * @subject-in milpa/command
 */
final class WhatASixthReviewFoundInTheContractTest extends TestCase
{
    /** @param array<string, mixed> $change */
    private static function refused(array $change): bool
    {
        try {
            Grants::grant($change);
        } catch (\InvalidArgumentException) {
            return true;
        }

        return false;
    }

    /** @param array<string, mixed> $facts */
    private static function standing(array $facts = []): GrantStanding
    {
        return new GrantStanding(...$facts + ['state' => GrantState::InForce, 'signatureFault' => null, 'watcherSignalAt' => Grants::at('2026-10-11T11:50:00Z'), 'receipts' => [Grants::receipt()], 'instruments' => ['lab.scan' => Grants::INSTRUMENT], 'recordFault' => null]);
    }

    // ── M1 · a stop that can no longer be read is not walked around by a second grant ───────────────────────

    /**
     * «A line that cannot be read is never read as "nothing happened"», and «a suspension that a second grant walks
     * around is not a suspension»: the line that stopped a grant can no longer be read, the host says so in
     * recordFault as the contract asks — and a second grant that names the same call lends.
     */
    public function testAStopThatCanNoLongerBeReadIsNotWalkedAroundByASecondGrant(): void
    {
        $stopped = Grants::grant(['id' => 'stopped']);
        $other = Grants::grant(['id' => 'would-lend']);
        $suspended = self::standing(['state' => GrantState::Suspended, 'because' => 'its falsifier fired']);
        $unread = self::standing(['recordFault' => 'line 9, written as a sign of this grant\'s watcher, cannot be read today']);

        foreach ([[$stopped, $other], [$other, $stopped]] as [$first, $second]) {
            $held = static fn (GrantStanding $of): array => [
                ['grant' => $first, 'standing' => $first->id === 'stopped' ? $of : self::standing()],
                ['grant' => $second, 'standing' => $second->id === 'stopped' ? $of : self::standing()],
            ];
            self::assertFalse(StandingJudge::among($held($suspended), Grants::call(), null)['verdict']->passes() ?? true, 'control: a stop that reads stops the other grant too');
            self::assertSame('record_unread', StandingJudge::judge($stopped, $unread, Grants::call())->reason, 'control: alone, the grant whose record does not read is refused');

            $answer = StandingJudge::among($held($unread), Grants::call(), null);

            self::assertFalse($answer['verdict']->passes() ?? true, 'the call passes under «' . ($answer['grant']->id ?? '?') . '» while the line that may have stopped «stopped» cannot be read');
            // …and it says which grant, and that the other lends nothing meanwhile.
            self::assertSame(['stopped', 'record_unread'], [$answer['grant']->id ?? null, $answer['verdict']->reason]);
            self::assertStringContainsString('Another grant names this call too, and it lends nothing while what the house keeps of «stopped» does not read whole', $answer['verdict']->sentence);
        }
    }

    public function testAGrantWhoseRecordDoesNotReadAndThatStandsAloneSaysSoWithoutNamingAnother(): void
    {
        $unread = self::standing(['recordFault' => 'line 9 cannot be read today']);

        $answer = StandingJudge::among([['grant' => Grants::grant(), 'standing' => $unread]], Grants::call(), null);

        self::assertSame('record_unread', $answer['verdict']->reason ?? null);
        self::assertStringNotContainsString('Another grant names this call too', $answer['verdict']->sentence ?? '');
        // A suspension still speaks before it: a stop that reads is the one a person lifts by name.
        $both = StandingJudge::among([
            ['grant' => Grants::grant(['id' => 'unread']), 'standing' => $unread],
            ['grant' => Grants::grant(['id' => 'stopped']), 'standing' => self::standing(['state' => GrantState::Suspended, 'because' => 'its falsifier fired'])],
        ], Grants::call(), null);
        self::assertSame(['stopped', 'suspended'], [$both['grant']->id ?? null, $both['verdict']->reason ?? null]);
        // …and a grant whose record does not read stops nobody it does not concern: another seat, another call.
        $elsewhere = StandingJudge::among([
            ['grant' => Grants::grant(['id' => 'unread', 'to' => ['ANOTHER-SEAT']]), 'standing' => $unread],
            ['grant' => Grants::grant(['id' => 'would-lend']), 'standing' => self::standing()],
        ], Grants::call(), null);
        self::assertTrue($elsewhere['verdict']->passes() ?? false);
    }

    /**
     * THE OTHER HALF OF IT: a DOCUMENT that no longer reads is no grant, so there was nowhere to say it. The host
     * says whether its WHOLE record of standing grants reads — and it has to say it: there is no default.
     */
    public function testARecordThatDoesNotReadWholeLendsToNoSeatWhateverGrantsWereHandedIn(): void
    {
        $inOrder = [['grant' => Grants::grant(), 'standing' => self::standing()]];
        self::assertTrue(StandingJudge::among($inOrder, Grants::call(), null)['verdict']->passes() ?? false, 'control: a record that reads whole, and a grant in order');

        $fault = 'line 5 says a grant was given, and it cannot be read as one today';
        foreach ([$inOrder, [], [['grant' => Grants::grant(['to' => ['ANOTHER-SEAT']]), 'standing' => self::standing()]]] as $grants) {
            $answer = StandingJudge::among($grants, Grants::call(), $fault);

            self::assertNotNull($answer, 'a grant that cannot be read may be the one that names this call: «no grant names it» is not known');
            self::assertNull($answer['grant'], 'no grant of the ones handed in is the one that speaks');
            self::assertSame([StandingCheck::Signature, 'record_unread'], [$answer['verdict']->failed, $answer['verdict']->reason]);
            self::assertStringContainsString($fault, $answer['verdict']->sentence);
            self::assertStringContainsString('Nothing is lent to any seat', $answer['verdict']->sentence);
        }
        // What the host hands in as the reason is said the way every sentence of a host is: plain, or spelled out.
        $forged = StandingJudge::among($inOrder, Grants::call(), "seen\x1b[2K\rOK: every grant is in force.\x1b[8m");
        self::assertSame(0, preg_match('/[^\x20-\x7E«»—…]/u', $forged['verdict']->sentence ?? "\x1b"));
        // …and the empty text is a fault that was not explained, never «reads whole»: only null says that.
        self::assertSame('record_unread', StandingJudge::among($inOrder, Grants::call(), '')['verdict']->reason ?? null);
    }

    public function testAHostThatDoesNotSayWhetherItsRecordReadsWholeCannotAsk(): void
    {
        $this->expectException(\ArgumentCountError::class);
        /** @phpstan-ignore-next-line */
        StandingJudge::among([['grant' => Grants::grant(), 'standing' => self::standing()]], Grants::call());
    }

    public function testTheContractSaysWhatAHostOwesALineItCannotAttribute(): void
    {
        $among = (string) preg_replace('/\s+\*\s+/', ' ', (string) (new \ReflectionMethod(StandingJudge::class, 'among'))->getDocComment());
        self::assertStringContainsStringIgnoringCase('a document it kept and can no longer read', $among);
        self::assertStringContainsStringIgnoringCase('a line it cannot attribute to one grant', $among);
        $standing = (string) preg_replace('/\s+\*\s+/', ' ', (string) (new \ReflectionMethod(GrantStanding::class, '__construct'))->getDocComment());
        self::assertStringContainsString('when the line that would decide it cannot be read', $standing);
    }

    // ── L1 · a filler that prints as nothing, and the rule built with the property where it is known ────────

    /** «A vowel or a filler that prints as nothing» was a list of seven code points; four more are blank by definition. */
    public function testAFillerThatPrintsAsNothingIsNotTaken(): void
    {
        self::assertTrue(self::refused(['undo' => 'take it' . mb_chr(0x2800) . ' back']), 'control: the blank braille pattern is refused');
        self::assertFalse(self::refused(['undo' => 'take it back, se' . mb_chr(0xF1) . 'or']), 'control: a letter is taken');
        foreach ([0x13441 => 'EGYPTIAN HIEROGLYPH FULL BLANK', 0x13442 => 'EGYPTIAN HIEROGLYPH HALF BLANK', 0x16FE4 => 'KHITAN SMALL SCRIPT FILLER', 0x1D159 => 'MUSICAL SYMBOL NULL NOTEHEAD'] as $point => $name) {
            self::assertTrue(self::refused(['undo' => 'take it back' . mb_chr($point) . 'x']), \sprintf('accepted: «undo» carrying U+%04X %s', $point, $name));
            self::assertTrue(self::refused(['to' => [Grants::SEAT, Grants::SEAT . mb_chr($point)]]), \sprintf('accepted: two seats that differ by U+%04X %s', $point, $name));
        }
        // Controls: characters of the same three scripts that draw something are text. Each is one Unicode has had
        // for many versions: the first control written here was the hieroglyph next to the blanks, which is as new
        // as they are — a PCRE with older tables has no such place yet, and refuses it for that (as the contract
        // says a host with older tables does). The check ran on such a PCRE and said so.
        foreach ([0x13000, 0x1342E, 0x16FE3, 0x1D158, 0x1D15A] as $point) {
            self::assertTrue(Canonical::oneLine('a' . mb_chr($point) . 'b'), \sprintf('U+%04X', $point));
        }
    }

    public function testThePatternNamesThePropertyItselfWhereThisPcreKnowsIt(): void
    {
        // The rule is Unicode's own «shown as nothing»; the list is what a PCRE that does not know that name is left
        // with. Where the name is known it is in the pattern, so that a character added to the set by later tables
        // is refused the day a house runs on them — not the day this suite does.
        $knows = new \ReflectionMethod(Canonical::class, 'knows');
        $unseen = new \ReflectionMethod(Canonical::class, 'unseen');
        self::assertTrue($knows->invoke(null, 'Lu'), 'control: a property every PCRE knows');
        self::assertFalse($knows->invoke(null, 'NoSuchProperty'), 'control: one that none does');
        self::assertSame($knows->invoke(null, 'DI'), str_contains($unseen->invoke(null), '\p{DI}'), 'left to itself, the pattern names the property exactly when this PCRE knows it');
        self::assertStringContainsString('\p{DI}', $unseen->invoke(null, true));
        self::assertStringNotContainsString('\p{DI}', $unseen->invoke(null, false));
        // The list ALONE still leaves out no code point of that set, under the tables of this PCRE: what a house on
        // an older PCRE runs is not a weaker rule.
        if ($knows->invoke(null, 'DI')) {
            $every = '';
            for ($point = 0; $point <= 0x10FFFF; ++$point) {
                $every .= $point >= 0xD800 && $point <= 0xDFFF ? '' : mb_chr($point, 'UTF-8');
            }
            preg_match_all('/\p{DI}/u', $every, $ignorable);
            self::assertGreaterThan(4000, \count($ignorable[0]));
            // Each asked BETWEEN two letters: alone, a mark would be refused for beginning the text (D11), and
            // twelve mutants of this list went unnoticed behind that.
            $alone = $unseen->invoke(null, false);
            self::assertSame([], array_values(array_map(
                static fn (string $character): string => \sprintf('U+%04X', mb_ord($character, 'UTF-8')),
                array_filter($ignorable[0], static fn (string $character): bool => preg_match($alone, 'a' . $character . 'b') !== 1),
            )));
        }
    }

    // ── L3 · D8 to the letter: nothing is not a value a person can read ─────────────────────────────────────

    public function testAnArgumentIsNotFixedToNothing(): void
    {
        self::assertFalse(self::refused(['covers' => [['operation' => 'lab:publish', 'arguments' => ['channel' => 'stable']]]]), 'control');
        self::assertTrue(self::refused(['covers' => [['operation' => 'lab:publish', 'arguments' => ['channel' => '']]]]), 'accepted: «channel» fixed to the empty text - on a line it reads «channel=»');
        self::assertTrue(self::refused(['conditions' => [['name' => 'c', 'statement' => 's', 'by' => ['operation' => 'lab:scan', 'arguments' => ['mode' => ''], 'free' => ['path']]]]]), 'accepted in «by»');
    }

    // ── L4 · what is not a receipt is never skipped by the judge ────────────────────────────────────────────

    /** @return iterable<string, array{mixed}> */
    public static function whatIsNotAReceipt(): iterable
    {
        yield 'what the reader of a line answers for one it cannot read' => [null];
        yield 'false' => [false];
        yield 'the line itself, as an array' => [['by' => 'lab.scan', 'arguments' => ['path' => 'candidate.tar'], 'subject' => Grants::CANDIDATE, 'ok' => false, 'instrument' => Grants::INSTRUMENT, 'who' => null, 'at' => '2026-10-11T11:55:00Z']];
        yield 'a text' => ['{"by":"lab.scan","ok":false}'];
    }

    #[DataProvider('whatIsNotAReceipt')]
    public function testWhatIsNotAReceiptIsNeverSkippedByTheJudge(mixed $line): void
    {
        // `array_map(ConditionReceipt::fromArray(...), $lines)` is the one-line way to build the list, and a red line
        // that no longer reads is then the `null`. No warning of PHP is waited for: the judge says it, and refuses.
        set_error_handler(static function (int $level, string $message): bool {
            throw new \ErrorException($message);
        });
        try {
            foreach ([[Grants::receipt(), $line], [$line, Grants::receipt()], [$line]] as $receipts) {
                /** @phpstan-ignore-next-line */
                $verdict = StandingJudge::judge(Grants::grant(), self::standing(['receipts' => $receipts]), Grants::call());

                self::assertSame([StandingCheck::Signature, 'record_unread'], [$verdict->failed, $verdict->reason], 'the judge skipped a line that is not a receipt');
                self::assertStringContainsString('is not a receipt', $verdict->sentence);
            }
        } finally {
            restore_error_handler();
        }
        self::assertTrue(StandingJudge::judge(Grants::grant(), self::standing(), Grants::call())->passes(), 'control');
    }

    // ── informational · «nothing is trimmed» reaches the two digests of a receipt ───────────────────────────

    /** @return iterable<string, array{string}> */
    public static function digestsThatAreNotAsTheyRead(): iterable
    {
        yield 'a line break after it' => [Grants::CANDIDATE . "\n"];
        yield 'a space after it' => [Grants::CANDIDATE . ' '];
        yield 'a space before it' => [' ' . Grants::CANDIDATE];
        yield 'a tab inside it' => ["sha256:\tx"];
        yield 'a character nobody sees in it' => ['sha256:' . mb_chr(0x200B) . 'x'];
        yield 'nothing' => [''];
    }

    #[DataProvider('digestsThatAreNotAsTheyRead')]
    public function testTheDigestsOfAReceiptAreGivenAsTheyRead(string $digest): void
    {
        // The judge compares the text a host wrote: a red receipt about the same content, its digest followed by a
        // line break, said nothing of the condition — and the green before it went on speaking.
        $line = ['by' => 'lab.scan', 'arguments' => ['path' => 'candidate.tar'], 'subject' => Grants::CANDIDATE, 'ok' => false, 'instrument' => Grants::INSTRUMENT, 'who' => null, 'at' => '2026-10-11T11:55:00Z'];
        self::assertNotNull(ConditionReceipt::fromArray($line), 'control');
        foreach (['subject', 'instrument'] as $which) {
            self::assertNull(ConditionReceipt::fromArray([$which => $digest] + $line), "a receipt: «{$which}» given as " . json_encode($digest));
            try {
                new ConditionReceipt('lab.scan', [], $which === 'subject' ? $digest : Grants::CANDIDATE, false, $which === 'instrument' ? $digest : Grants::INSTRUMENT, null, Grants::at('2026-10-11T11:55:00Z'));
                self::fail("a receipt was built with «{$which}» as " . json_encode($digest));
            } catch (\InvalidArgumentException $refused) {
                self::assertSame(0, preg_match('/[^\x20-\x7E«»—…]/u', $refused->getMessage()));
            }
        }
    }

    public function testADocumentThatNoLongerReadsIsSaidToTheJudgeNotLeftOut(): void
    {
        // Where the reader of a document says what a host owes one it kept and can no longer read.
        $reader = (string) preg_replace('/\s+\*\s+/', ' ', (string) (new \ReflectionMethod(StandingGrant::class, 'fromJson'))->getDocComment());
        self::assertStringContainsString('says so to the judge', $reader);
        self::assertStringContainsString('StandingJudge::among()', $reader);
    }
}
