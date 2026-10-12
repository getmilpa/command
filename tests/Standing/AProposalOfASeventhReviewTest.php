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
use Milpa\Command\Standing\NamedCall;
use Milpa\Command\Standing\StandingCall;
use Milpa\Command\Standing\StandingGrant;
use Milpa\Command\Standing\StandingJudge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A PROPOSAL OF THE SEVENTH REVIEW, REBUILT AS THE EIGHTH ASKED — BUILT, AND NOT DECIDED (greenhouse
 * decisions/0615): UNDER DOUBT, A STOP STOPS.
 *
 * A stopped grant — suspended, or its record not read whole — that lends to the seat and covers the operation refuses
 * the call, whether or not another grant would lend it, unless the call says BEYOND DOUBT another value for something
 * that entry fixes: a value of the same kind as the fixed one, written the way a grant can fix it, and different.
 * Everything else is doubt. It is a small change that can be taken out: this file, and the lines of `StandingJudge`
 * that carry its name.
 *
 * The first test is the seventh reviewer's own, and the data of the third are the eighth's, as they were written.
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

    private static function wide(string $id = 'wide'): StandingGrant
    {
        return Grants::grant(['id' => $id, 'covers' => [['operation' => 'lab:publish']]]);
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
     * What a call may carry and still be, for all the judge can tell, the act a stopped grant fixes: EVERYTHING but
     * a value of the same kind, written as a grant writes one, and different. Nothing here is a list of what PHP
     * reads as one value — that list never ends; these are samples of the doubt.
     *
     * @return iterable<string, array{string|int|bool, array<string, mixed>}> what the stopped grant fixes for «it», and what the call carries
     */
    public static function spellingsThatMayBeTheSameAct(): iterable
    {
        // It says nothing.
        yield 'the argument is not there: the default of the operation' => ['stable', []];
        yield 'the argument is null' => ['stable', ['it' => null]];
        yield 'the empty text: what a handler reads where the argument is not there' => ['stable', ['it' => '']];
        yield 'the empty text, against a number' => [7, ['it' => '']];
        yield 'the empty text, against true: it says nothing, it does not say no' => [true, ['it' => '']];
        yield 'nothing but spaces' => ['stable', ['it' => '   ']];
        yield 'the empty list' => ['stable', ['it' => []]];
        // It is not written the way a grant writes a value.
        yield 'a space after the value' => ['stable', ['it' => 'stable ']];
        yield 'a space before the value' => ['stable', ['it' => ' stable']];
        yield 'the end of a line after the value, as a pipe leaves it' => ['stable', ['it' => "stable\n"]];
        yield 'a tab before the value' => ['stable', ['it' => "\tstable"]];
        yield 'a no-break space after the value' => ['stable', ['it' => 'stable' . "\u{00A0}"]];
        yield 'another value, with spaces around it: not a value a grant could fix' => ['stable', ['it' => ' beta ']];
        yield 'another value, with two spaces inside it' => ['stable', ['it' => 'be  ta']];
        yield 'another value, with a letter that is not ASCII' => ['stable', ['it' => 'bet' . "\u{00E1}"]];
        yield 'a list that holds the value' => ['stable', ['it' => ['stable']]];
        yield 'a list that holds another value' => ['stable', ['it' => ['beta']]];
        yield 'an object' => ['stable', ['it' => new \stdClass()]];
        // It is of another kind than the fixed one — the same value, or another, to some reader.
        yield 'a number, sent as the text a terminal sends' => [7, ['it' => '7']];
        yield 'a number, sent as text with a zero before it' => [7, ['it' => '07']];
        yield 'a number, sent as text with spaces around it' => [7, ['it' => ' 7 ']];
        yield 'a number, sent as a number with no fraction' => [7, ['it' => 7.0]];
        yield 'a number, sent as a number with a fraction' => [7, ['it' => 7.5]];
        yield 'ANOTHER number, sent as text' => [7, ['it' => '8']];
        yield 'ANOTHER number, sent with a fraction' => [7, ['it' => 8.0]];
        yield 'a number, and the call says true' => [7, ['it' => true]];
        yield 'a text that is a number, sent as the number' => ['7', ['it' => 7]];
        yield 'a text, and the call says zero' => ['stable', ['it' => 0]];
        yield 'a text, and the call says false' => ['stable', ['it' => false]];
        yield 'the word true, sent as the answer' => ['true', ['it' => true]];
        yield 'true, sent as the word' => [true, ['it' => 'true']];
        yield 'true, sent as the text a bare flag is at a terminal' => [true, ['it' => '1']];
        yield 'true, sent as the number one' => [true, ['it' => 1]];
        yield 'true, sent as yes' => [true, ['it' => 'yes']];
        yield 'true, sent as the WORD false, which `if` reads as true' => [true, ['it' => 'false']];
        yield 'true, sent as zero: the opposite answer, in another kind' => [true, ['it' => 0]];
        yield 'false, sent as the word' => [false, ['it' => 'false']];
        yield 'false, sent as zero' => [false, ['it' => 0]];
        yield 'false, sent as the text zero' => [false, ['it' => '0']];
        yield 'false, sent as the word true: the opposite answer, in another kind' => [false, ['it' => 'true']];
    }

    /**
     * The eighth reviewer's eight, as they were written: each walked around the stop at the head they read.
     *
     * @return iterable<string, array{string|int|bool, mixed}> what the stopped grant fixes, and what the call carries
     */
    public static function whatSaysNothingOrTheSame(): iterable
    {
        // «Never the empty text: `channel=` and no `channel` at all read alike» (README, NamedCall) - and the rule takes
        // the absent one for doubt, and the empty one for another value.
        yield 'a text, and the call carries the empty text' => ['stable', ''];
        yield 'a number, and the call carries the empty text' => [7, ''];
        // PHP's own readers of a text as a number: is_numeric(), `==`, a typed parameter, (int)
        yield 'a number, and the call carries its text with a zero before' => [7, '07'];
        yield 'a number, and the call carries its text with a space before' => [7, ' 7'];
        yield 'a number, and the call carries its text with .0 after' => [7, '7.0'];
        // PHP's own reader of a text as a boolean: FILTER_VALIDATE_BOOLEAN - and the family's coercer (yes, on)
        yield 'true, and the call carries the word yes' => [true, 'yes'];
        yield 'true, and the call carries the word on' => [true, 'on'];
        // ...and PHP's truth: every text but "" and "0" is true - the word «false» among them
        yield 'true, and the call carries the WORD false, which PHP reads as true' => [true, 'false'];
    }

    #[DataProvider('whatSaysNothingOrTheSame')]
    public function testAStopIsNotWalkedAroundByAValueThatSaysNothingOrThatPhpReadsAsTheFixedOne(string|int|bool $fixed, mixed $carried): void
    {
        $held = static fn (): array => [['grant' => self::narrow(['it' => $fixed]), 'standing' => self::suspended()], ['grant' => self::wide(), 'standing' => self::standing()]];
        $call = Grants::call(arguments: ['it' => $carried, 'path' => 'candidate.tar']);
        // controls: not carried at all, the stop reaches the call; with another value said, the wide grant lends
        self::assertFalse(StandingJudge::among($held(), Grants::call(arguments: ['path' => 'candidate.tar']), null)['verdict']->passes() ?? true, 'control: under doubt a stop stops');
        self::assertTrue(StandingJudge::among($held(), Grants::call(arguments: ['it' => \is_bool($fixed) ? !$fixed : (\is_int($fixed) ? $fixed + 1 : 'beta'), 'path' => 'candidate.tar']), null)['verdict']->passes() ?? false, 'control: another value, said');

        $answer = StandingJudge::among($held(), $call, null);

        self::assertFalse($answer['verdict']->passes() ?? true, '«narrow» fixes it to ' . json_encode($fixed) . ' and is suspended, and the call passes under «' . ($answer['grant']->id ?? '?') . '» carrying ' . json_encode($carried));
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
                self::assertStringContainsString('does not say, beyond doubt, another value for what it fixes', $answer['verdict']->sentence);
            }
        }
    }

    /**
     * What SAYS another value, beyond doubt: of the same kind as the fixed one, written as a grant writes a value,
     * and different. The stop is not this call's, and the wide grant lends. Those marked are what the rule does NOT
     * reach, and says so: an operation's OWN reading of a value — one that folds capitals, or reads the text "07"
     * as the text "7" — makes the stopped act of a call that says another value here.
     *
     * @return iterable<string, array{string|int|bool, array<string, mixed>}>
     */
    public static function spellingsThatSayAnotherValue(): iterable
    {
        yield 'another text' => ['stable', ['it' => 'beta']];
        yield 'another text, with one space inside it, as a grant may fix one' => ['stable', ['it' => 'be ta']];
        yield 'another number' => [7, ['it' => 8]];
        yield 'zero, where another number is fixed' => [7, ['it' => 0]];
        yield 'the opposite answer' => [true, ['it' => false]];
        yield 'the opposite answer, the other way' => [false, ['it' => true]];
        yield 'another text, where the fixed text is a number' => ['7', ['it' => '8']];
        yield 'NOT REACHED, AND SAID: the value in capitals' => ['stable', ['it' => 'Stable']];
        yield 'NOT REACHED, AND SAID: a text that is the same number to PHP' => ['7', ['it' => '07']];
        yield 'NOT REACHED, AND SAID: the text zero, which `?:` reads as nothing' => ['stable', ['it' => '0']];
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

    /** A call that says one fixed value exactly and leaves another out is still, for all the judge can tell, that act. */
    public function testEveryFixedArgumentHasToSayAnotherValueOrBeTheGrantsOwn(): void
    {
        $two = self::narrow(['channel' => 'stable', 'build' => 7]);
        $held = static fn (): array => [['grant' => $two, 'standing' => self::suspended()], ['grant' => self::wide(), 'standing' => self::standing()]];
        $reason = static function (array $arguments) use ($held): string {
            $answer = StandingJudge::among($held(), Grants::call(arguments: $arguments + ['path' => 'candidate.tar']), null);

            return ($answer['verdict']->passes() ?? false) ? 'PASSED under ' . ($answer['grant']->id ?? '?') : ($answer['verdict']->reason ?? 'nobody\'s call');
        };

        self::assertSame('suspended', $reason(['channel' => 'stable', 'build' => 7]), 'control: both said exactly — the grant names the call');
        self::assertSame('stopped_beside', $reason(['channel' => 'stable']), 'one said exactly, the other left out');
        self::assertSame('stopped_beside', $reason(['channel' => 'stable', 'build' => '7']), 'one said exactly, the other in another kind');
        self::assertSame('PASSED under wide', $reason(['channel' => 'stable', 'build' => 8]), 'one said exactly, another value for the other: it is not this grant\'s call');
        self::assertSame('PASSED under wide', $reason(['channel' => 'beta']), 'another value for one, the other left out');

        // The same for each kind of value: said exactly, it does not say another — so what is left out still decides.
        $three = self::narrow(['channel' => 'stable', 'build' => 7, 'dry' => false]);
        $asked = static fn (array $arguments): bool => StandingJudge::mayBeNaming($three, Grants::call(arguments: $arguments + ['path' => 'candidate.tar']));
        self::assertTrue($asked(['channel' => 'stable']), 'the text said exactly, the number and the answer left out');
        self::assertTrue($asked(['build' => 7]), 'the number said exactly');
        self::assertTrue($asked(['dry' => false]), 'the answer said exactly');
        self::assertFalse($asked(['channel' => 'stable', 'build' => 7, 'dry' => true]), 'control: another answer, said');
    }

    /** With two stops beside the call, the first that was given speaks: the answer does not depend on which was found last. */
    public function testOfTwoStopsBesideTheCallTheFirstGivenSpeaks(): void
    {
        $call = Grants::call(arguments: ['path' => 'candidate.tar']);
        $first = ['grant' => Grants::grant(['id' => 'first', 'covers' => [['operation' => 'lab:publish', 'arguments' => ['channel' => 'stable']]]]), 'standing' => self::suspended()];
        $second = ['grant' => Grants::grant(['id' => 'second', 'covers' => [['operation' => 'lab:publish', 'arguments' => ['channel' => 'beta']]]]), 'standing' => self::standing(['recordFault' => 'line 9 cannot be read today'])];

        self::assertSame('first', StandingJudge::among([$first, $second], $call, null)['grant']->id ?? null);
        self::assertSame('second', StandingJudge::among([$second, $first], $call, null)['grant']->id ?? null);
    }

    /**
     * What a grant can fix an argument to, asked on its own: the proposal says «another value» only of one of these.
     *
     * @return iterable<string, array{mixed, bool}>
     */
    public static function whatAnArgumentCanBeFixedTo(): iterable
    {
        yield 'a text a person can read' => ['stable', true];
        yield 'a text with one space inside' => ['stable release', true];
        yield 'one visible character' => ['*', true];
        yield 'a whole number' => [7, true];
        yield 'zero' => [0, true];
        yield 'true' => [true, true];
        yield 'false' => [false, true];
        yield 'the empty text' => ['', false];
        yield 'a space before' => [' stable', false];
        yield 'a space after' => ['stable ', false];
        yield 'two spaces inside' => ['stable  release', false];
        yield 'the end of a line after' => ["stable\n", false];
        yield 'a letter that is not ASCII' => ['estable' . "\u{00E9}", false];
        yield 'a number with a fraction' => [7.0, false];
        yield 'nothing' => [null, false];
        yield 'a list' => [['stable'], false];
        yield 'an object' => [new \stdClass(), false];
    }

    #[DataProvider('whatAnArgumentCanBeFixedTo')]
    public function testWhatAnArgumentCanBeFixedToIsAskedOnItsOwn(mixed $value, bool $fixable): void
    {
        self::assertSame($fixable, NamedCall::fixable($value));
        // …and it is what the constructor of a named call takes, value for value.
        try {
            new NamedCall('lab:publish', ['it' => $value]);
            $taken = true;
        } catch (\InvalidArgumentException) {
            $taken = false;
        }
        self::assertSame($fixable, $taken);
    }

    /** THE SECOND HALF: a stop beside the call speaks whether or not another grant would lend it. */
    public function testAStopBesideTheCallSpeaksWhetherOrNotAnotherGrantWouldLend(): void
    {
        $call = Grants::call(arguments: ['path' => 'candidate.tar']);
        $stopped = ['grant' => self::narrow(['channel' => 'stable']), 'standing' => self::suspended()];
        $beside = static fn (?array $answer): array => [$answer['grant']->id ?? null, $answer['verdict']->failed ?? null, $answer['verdict']->reason ?? null, $answer === null ? null : $answer['verdict']->passes(), $answer === null ? null : $answer['verdict']->named()];
        $stop = ['narrow', StandingCheck::InForce, 'stopped_beside', false, true];

        // Alone: the answer was `null`, «nobody's call», and a host let what the seat holds on its own decide.
        self::assertSame($stop, $beside(StandingJudge::among([$stopped], $call, null)));
        // Beside a grant in force that does not name the call either.
        self::assertSame($stop, $beside(StandingJudge::among([$stopped, ['grant' => Grants::grant(['id' => 'for-beta', 'covers' => [['operation' => 'lab:publish', 'arguments' => ['channel' => 'beta']]]]), 'standing' => self::standing()]], $call, null)));
        // Beside a grant that names the call and refuses it for its own reason: the stop is said, in either order.
        $unmeasured = ['grant' => self::wide(), 'standing' => self::standing(['receipts' => []])];
        self::assertSame('not_measured', StandingJudge::among([$unmeasured], $call, null)['verdict']->reason ?? null, 'control');
        self::assertSame($stop, $beside(StandingJudge::among([$stopped, $unmeasured], $call, null)));
        self::assertSame($stop, $beside(StandingJudge::among([$unmeasured, $stopped], $call, null)));
        // It cites nothing, and it is never a pass.
        self::assertSame([], StandingJudge::among([$stopped], $call, null)['verdict']->cited ?? null);

        // A stop that NAMES the call as it is spelled still speaks first, as it always did.
        $exact = StandingJudge::among([$stopped, ['grant' => Grants::grant(['id' => 'exact']), 'standing' => self::suspended()], ['grant' => self::wide(), 'standing' => self::standing()]], Grants::call(arguments: ['channel' => 'stable', 'path' => 'candidate.tar']), null);
        self::assertSame('suspended', $exact['verdict']->reason ?? null);
        $unread = StandingJudge::among([['grant' => self::narrow(['channel' => 'stable']), 'standing' => self::suspended()], ['grant' => self::wide('reads-no-more'), 'standing' => self::standing(['recordFault' => 'line 9 cannot be read today'])]], $call, null);
        self::assertSame(['reads-no-more', 'record_unread'], [$unread['grant']->id ?? null, $unread['verdict']->reason ?? null]);
        // …also when ANOTHER stopped grant is only beside the call: «finer» fixes one argument more, which the call
        // leaves out, and «exact» names the call as it is spelled. The one that names it speaks, in either order.
        $said = Grants::call(arguments: ['channel' => 'stable', 'path' => 'candidate.tar']);
        $finer = ['grant' => Grants::grant(['id' => 'finer', 'covers' => [['operation' => 'lab:publish', 'arguments' => ['channel' => 'stable', 'build' => 7]]]]), 'standing' => self::suspended()];
        $named = ['grant' => Grants::grant(['id' => 'exact']), 'standing' => self::suspended()];
        self::assertSame('stopped_beside', StandingJudge::among([$finer], $said, null)['verdict']->reason ?? null, 'control: alone, «finer» is a stop beside this call');
        foreach ([[$finer, $named], [$named, $finer]] as $held) {
            $answer = StandingJudge::among($held, $said, null);
            self::assertSame(['exact', 'suspended'], [$answer['grant']->id ?? null, $answer['verdict']->reason ?? null]);
        }

        // And where nothing is stopped, nothing changed: a grant IN FORCE that only may be naming the call says
        // nothing of it — the answer is «nobody's call», and the question is the host's to ask.
        self::assertNull(StandingJudge::among([['grant' => self::narrow(['channel' => 'stable']), 'standing' => self::standing()]], $call, null));
        // A grant that is gone and whose record reads stops nobody, alone either.
        self::assertNull(StandingJudge::among([['grant' => self::narrow(['channel' => 'stable']), 'standing' => self::standing(['state' => GrantState::Revoked])]], $call, null));
    }

    /** The question the contract hands a host, to ask of a grant IN FORCE before a seat's own word decides. */
    public function testAHostCanAskWhetherAGrantMayBeNamingTheCall(): void
    {
        $narrow = self::narrow(['channel' => 'stable']);
        $asks = static fn (array $arguments, ?StandingGrant $grant = null): bool => StandingJudge::mayBeNaming($grant ?? $narrow, Grants::call(arguments: $arguments + ['path' => 'candidate.tar']));

        self::assertTrue($asks([]), 'the fixed argument left out');
        self::assertTrue($asks(['channel' => '']));
        self::assertTrue($asks(['channel' => 'stable']), 'said exactly: it names it, so it may be naming it');
        self::assertFalse($asks(['channel' => 'beta']), 'another value, said');
        self::assertFalse($asks([], Grants::grant(['id' => 'narrow', 'to' => ['ANOTHER-SEAT'], 'covers' => [['operation' => 'lab:publish', 'arguments' => ['channel' => 'stable']]]])), 'it lends to another seat');
        self::assertFalse($asks([], Grants::grant(['id' => 'narrow', 'covers' => [['operation' => 'lab:merge', 'arguments' => ['channel' => 'stable']]]])), 'it covers another operation');
        self::assertTrue(StandingJudge::mayBeNaming($narrow, new StandingCall('LAB_PUBLISH', ['path' => 'candidate.tar'], Grants::SEAT, Grants::CANDIDATE, Grants::effect(), Grants::at('2026-10-11T12:00:00Z'))), 'the operation under another of its spellings is that operation');

        // Nothing is ever LENT on that answer: asked of the grant in force, the call is not named, and is nobody's.
        $call = Grants::call(arguments: ['path' => 'candidate.tar']);
        self::assertFalse(StandingJudge::judge($narrow, self::standing(), $call)->named());
        self::assertNull(StandingJudge::among([['grant' => $narrow, 'standing' => self::standing()]], $call, null));
    }

    /** What a host owes the judge is said where a host reads it. */
    public function testTheCallSaysWhatAHostOwesIt(): void
    {
        $said = static fn (string $class, ?string $method = null): string => (string) preg_replace('/\s*\*\s*/', ' ', (string) ($method === null ? (new \ReflectionClass($class))->getConstructor()?->getDocComment() : (new \ReflectionMethod($class, $method))->getDocComment()));

        self::assertStringContainsString('the arguments of the call AS THE OPERATION READS THEM', $said(StandingCall::class));
        self::assertStringContainsString('Such a host asks this of every grant it holds for that seat before the seat\'s own word decides', $said(StandingJudge::class, 'mayBeNaming'));
    }
}
