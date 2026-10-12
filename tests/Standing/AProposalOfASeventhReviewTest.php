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

use Milpa\Command\Standing\GrantStanding;
use Milpa\Command\Standing\GrantState;
use Milpa\Command\Standing\StandingCheck;
use Milpa\Command\Standing\StandingGrant;
use Milpa\Command\Standing\StandingJudge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A PROPOSAL OF THE SEVENTH REVIEW — BUILT, AND NOT DECIDED (greenhouse decisions/0615): UNDER DOUBT, A STOP STOPS.
 *
 * A stopped grant — suspended, or its record not read whole — that lends to the seat and covers the operation refuses
 * the call that another grant would lend, unless the call SAYS another value for something that entry fixes. It is a
 * small change that can be taken out: this file, and the lines of `StandingJudge` that carry its name.
 *
 * The first test is the reviewer's own, moved here as it was written.
 *
 * @subject-in milpa/command
 */
final class AProposalOfASeventhReviewTest extends TestCase
{
    /** @param array<string, mixed> $facts */
    private static function standing(array $facts = []): GrantStanding
    {
        return new GrantStanding(...$facts + ['state' => GrantState::InForce, 'signatureFault' => null, 'watcherSignalAt' => Grants::at('2026-10-11T11:50:00Z'), 'receipts' => [Grants::receipt()], 'instruments' => ['lab.scan' => Grants::INSTRUMENT], 'recordFault' => null]);
    }

    private static function suspended(): GrantStanding
    {
        return self::standing(['state' => GrantState::Suspended, 'because' => 'its falsifier fired']);
    }

    /** @param array<string, string|int|bool> $fixes */
    private static function narrow(array $fixes): StandingGrant
    {
        return Grants::grant(['id' => 'narrow', 'covers' => [['operation' => 'lab:publish', 'arguments' => $fixes]]]);
    }

    private static function wide(): StandingGrant
    {
        return Grants::grant(['id' => 'wide', 'covers' => [['operation' => 'lab:publish']]]);
    }

    /**
     * M1 - «a suspension that a second grant walks around is not a suspension»: a grant that fixes an argument is
     * stopped, a wider grant is in force, and the seat spells the same act without that argument (the operation's
     * default), or with its value as another type. The stopped grant no longer «names» the call; the wider one lends.
     * The same walk passes a grant whose record does not read whole.
     */
    public function testAStopIsNotWalkedAroundBySpellingTheCallAnotherWay(): void
    {
        $narrow = Grants::grant(['id' => 'narrow']);                                                          // lab:publish, channel fixed to "stable"
        $wide = Grants::grant(['id' => 'wide', 'covers' => [['operation' => 'lab:publish']]]);                // lab:publish, nothing fixed
        $stops = [
            'suspended' => self::standing(['state' => GrantState::Suspended, 'because' => 'its falsifier fired']),
            'its record does not read whole' => self::standing(['recordFault' => 'the line that stopped it cannot be read today']),
        ];
        foreach ($stops as $how => $stopped) {
            $held = [['grant' => $narrow, 'standing' => $stopped], ['grant' => $wide, 'standing' => self::standing()]];

            // control: spelled as the stopped grant spells it, the call is refused whatever the wide grant would lend
            $answer = StandingJudge::among($held, Grants::call(arguments: ['channel' => 'stable', 'path' => 'candidate.tar']), null);
            self::assertFalse($answer['verdict']->passes() ?? true, "control ({$how})");
            // control: a call that is certainly another act - another channel, said - is the wide grant's to lend
            $answer = StandingJudge::among($held, Grants::call(arguments: ['channel' => 'beta', 'path' => 'candidate.tar']), null);
            self::assertTrue($answer['verdict']->passes() ?? false, "control ({$how}): another channel, said");

            // the same act, spelled without the argument the stopped grant fixes
            $answer = StandingJudge::among($held, Grants::call(arguments: ['path' => 'candidate.tar']), null);
            self::assertFalse($answer['verdict']->passes() ?? true, "«narrow» is {$how}, and the call passes under «" . ($answer['grant']->id ?? '?') . '» when it does not say the channel');
        }

        // and with the fixed value as another type: a number a terminal sends as text
        $numbered = Grants::grant(['id' => 'narrow', 'covers' => [['operation' => 'lab:publish', 'arguments' => ['build' => 7]]]]);
        $held = [['grant' => $numbered, 'standing' => $stops['suspended']], ['grant' => $wide, 'standing' => self::standing()]];
        self::assertFalse(StandingJudge::among($held, Grants::call(arguments: ['build' => 7, 'path' => 'candidate.tar']), null)['verdict']->passes() ?? true, 'control');
        $answer = StandingJudge::among($held, Grants::call(arguments: ['build' => '7', 'path' => 'candidate.tar']), null);
        self::assertFalse($answer['verdict']->passes() ?? true, 'the stopped grant fixes build to the number 7, and the call passes under «' . ($answer['grant']->id ?? '?') . '» with build as the text "7"');
    }

    /**
     * What a call may carry and still be, for all the judge can tell, the act a stopped grant fixes: THE LEAST that
     * ordinary PHP reads as one value — the argument not there, null, and a text standing for a number or a boolean.
     *
     * @return iterable<string, array{string|int|bool, array<string, mixed>}> what the stopped grant fixes for «it», and what the call carries
     */
    public static function spellingsThatMayBeTheSameAct(): iterable
    {
        yield 'the argument is not there: the default of the operation' => ['stable', []];
        yield 'the argument is null' => ['stable', ['it' => null]];
        yield 'a number, sent as the text a terminal sends' => [7, ['it' => '7']];
        yield 'a text that is a number, sent as the number' => ['7', ['it' => 7]];
        yield 'a number, sent as a number with no fraction' => [7, ['it' => 7.0]];
        yield 'true, sent as the word' => [true, ['it' => 'true']];
        yield 'true, sent as the text a bare flag is at a terminal' => [true, ['it' => '1']];
        yield 'true, sent as the number one' => [true, ['it' => 1]];
        yield 'false, sent as the word' => [false, ['it' => 'false']];
        yield 'false, sent as zero' => [false, ['it' => 0]];
        yield 'false, sent as the text zero' => [false, ['it' => '0']];
        yield 'false, sent as the empty text' => [false, ['it' => '']];
        yield 'the word true, sent as the answer' => ['true', ['it' => true]];
    }

    /** @param array<string, mixed> $carried */
    #[DataProvider('spellingsThatMayBeTheSameAct')]
    public function testUnderDoubtAStopStops(string|int|bool $fixed, array $carried): void
    {
        $call = Grants::call(arguments: $carried + ['path' => 'candidate.tar']);
        $narrow = self::narrow(['it' => $fixed]);
        self::assertFalse(StandingJudge::judge($narrow, self::standing(), $call)->named(), 'control: spelled so, it is not the call the narrow grant names — nothing is LENT on a spelling that is not exact');
        self::assertTrue(StandingJudge::among([['grant' => self::wide(), 'standing' => self::standing()]], $call, null)['verdict']->passes() ?? false, 'control: the wide grant alone lends it');

        $stops = ['is suspended' => self::suspended(), 'has a record that does not read whole' => self::standing(['recordFault' => 'line 9 cannot be read today'])];
        foreach ($stops as $said => $stopped) {
            foreach ([[$narrow, $stopped, self::wide(), self::standing()], [self::wide(), self::standing(), $narrow, $stopped]] as [$first, $how, $second, $other]) {
                $answer = StandingJudge::among([['grant' => $first, 'standing' => $how], ['grant' => $second, 'standing' => $other]], $call, null);

                self::assertFalse($answer['verdict']->passes() ?? true, 'the stopped grant may be the one that names this call, and the wide one lent it');
                self::assertSame(['narrow', StandingCheck::InForce, 'stopped_beside'], [$answer['grant']->id ?? null, $answer['verdict']->failed, $answer['verdict']->reason]);
                self::assertStringContainsString("Standing grant «narrow» {$said}, and it may be the one that names this call: it lends to this seat and covers `lab:publish`", $answer['verdict']->sentence);
                self::assertStringContainsString('does not say another value for what it fixes', $answer['verdict']->sentence);
            }
        }
    }

    /**
     * What SAYS another value: the stop is not this call's, and the wide grant lends. The last four are what the
     * rule does NOT set aside, by decision of how little it reaches — capitals, a space around a value, and words
     * for yes and no that are not PHP's own.
     *
     * @return iterable<string, array{string|int|bool, array<string, mixed>}>
     */
    public static function spellingsThatSayAnotherValue(): iterable
    {
        yield 'another value, said' => ['stable', ['it' => 'beta']];
        yield 'another number' => [7, ['it' => 8]];
        yield 'another number, as text' => [7, ['it' => '8']];
        yield 'the opposite answer' => [true, ['it' => false]];
        yield 'the opposite answer, as zero' => [true, ['it' => 0]];
        yield 'the opposite answer, as the word' => [false, ['it' => 'true']];
        yield 'a list' => ['stable', ['it' => ['stable']]];
        yield 'NOT SET ASIDE: the value in capitals' => ['stable', ['it' => 'Stable']];
        yield 'NOT SET ASIDE: a space after the value' => ['stable', ['it' => 'stable ']];
        yield 'NOT SET ASIDE: a number written with a zero before it' => [7, ['it' => '07']];
        yield 'NOT SET ASIDE: yes, for true' => [true, ['it' => 'yes']];
    }

    /** @param array<string, mixed> $carried */
    #[DataProvider('spellingsThatSayAnotherValue')]
    public function testACallThatSaysAnotherValueIsNotTheStoppedGrantsCall(string|int|bool $fixed, array $carried): void
    {
        $call = Grants::call(arguments: $carried + ['path' => 'candidate.tar']);

        $answer = StandingJudge::among([['grant' => self::narrow(['it' => $fixed]), 'standing' => self::suspended()], ['grant' => self::wide(), 'standing' => self::standing()]], $call, null);

        self::assertTrue($answer['verdict']->passes() ?? false);
        self::assertSame('wide', $answer['grant']->id ?? null);
    }

    public function testAStopStopsOnlyWhatItMayBeNaming(): void
    {
        $call = Grants::call(arguments: ['path' => 'candidate.tar']);
        $wide = ['grant' => self::wide(), 'standing' => self::standing()];
        $lends = static fn (StandingGrant $grant, GrantStanding $standing): bool => StandingJudge::among([['grant' => $grant, 'standing' => $standing], $wide], $call, null)['verdict']->passes() ?? false;

        self::assertFalse($lends(self::narrow(['channel' => 'stable']), self::suspended()), 'control: the stop this file is about');
        // Not stopped at all: a grant in force that does not name the call says nothing of it.
        self::assertTrue($lends(self::narrow(['channel' => 'stable']), self::standing()));
        // Stopped, for another seat; stopped, over another operation; gone for good, which stops nobody.
        self::assertTrue($lends(Grants::grant(['id' => 'narrow', 'to' => ['ANOTHER-SEAT'], 'covers' => [['operation' => 'lab:publish', 'arguments' => ['channel' => 'stable']]]]), self::suspended()));
        self::assertTrue($lends(Grants::grant(['id' => 'narrow', 'covers' => [['operation' => 'lab:merge', 'arguments' => ['channel' => 'stable']]]]), self::suspended()));
        self::assertTrue($lends(self::narrow(['channel' => 'stable']), self::standing(['state' => GrantState::Revoked])));
        // Stopped, with two entries over the operation: one the call says another value for, one it may be naming.
        $two = Grants::grant(['id' => 'narrow', 'covers' => [['operation' => 'lab:publish', 'arguments' => ['path' => 'another.tar']], ['operation' => 'lab:publish', 'arguments' => ['channel' => 'stable']]]]);
        self::assertFalse($lends($two, self::suspended()));
        $none = Grants::grant(['id' => 'narrow', 'covers' => [['operation' => 'lab:publish', 'arguments' => ['path' => 'another.tar']], ['operation' => 'lab:publish', 'arguments' => ['path' => 'a-third.tar', 'channel' => 'stable']]]]);
        self::assertTrue($lends($none, self::suspended()), 'the call says another value for something EACH entry fixes');
        // …and a list of receipts that holds a line that was not read is a stop of that kind too.
        /** @phpstan-ignore-next-line */
        self::assertFalse($lends(self::narrow(['channel' => 'stable']), self::standing(['receipts' => [null]])));
    }

    public function testItChangesNothingWhereNoOtherGrantWouldLend(): void
    {
        // The stop is for the call ANOTHER grant would lend. With no other grant, or one that names the call and
        // refuses it for its own reason, the answer is what it was: nobody's call, or that refusal.
        $call = Grants::call(arguments: ['path' => 'candidate.tar']);
        $stopped = ['grant' => self::narrow(['channel' => 'stable']), 'standing' => self::suspended()];

        self::assertNull(StandingJudge::among([$stopped], $call, null), 'nobody names this call: it is judged as before any grant existed');
        self::assertNull(StandingJudge::among([$stopped, ['grant' => Grants::grant(['id' => 'also-narrow', 'covers' => [['operation' => 'lab:publish', 'arguments' => ['channel' => 'stable']]]]), 'standing' => self::standing()]], $call, null));
        $unmeasured = StandingJudge::among([$stopped, ['grant' => self::wide(), 'standing' => self::standing(['receipts' => []])]], $call, null);
        self::assertSame(['wide', 'not_measured'], [$unmeasured['grant']->id ?? null, $unmeasured['verdict']->reason ?? null]);
        // A stop that names the call as it is spelled still speaks first, as it always did.
        $exact = StandingJudge::among([$stopped, ['grant' => Grants::grant(['id' => 'exact']), 'standing' => self::suspended()], ['grant' => self::wide(), 'standing' => self::standing()]], Grants::call(arguments: ['channel' => 'stable', 'path' => 'candidate.tar']), null);
        self::assertSame('suspended', $exact['verdict']->reason ?? null);
    }
}
