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
use Milpa\Command\Effect\Authority;
use Milpa\Command\Effect\ContextFacts;
use Milpa\Command\Effect\DeclaredAuthorityPolicy;
use Milpa\Command\Effect\DescentCertificate;
use Milpa\Command\Effect\EffectProfile;
use Milpa\Command\Effect\Externality;
use Milpa\Command\Effect\Mutation;
use Milpa\Command\Effect\Reversibility;
use Milpa\Command\Effect\Subject;
use Milpa\Command\Operation;
use Milpa\Command\Standing\Canonical;
use Milpa\Command\Standing\ConditionReceipt;
use Milpa\Command\Standing\Falsifier;
use Milpa\Command\Standing\GrantCondition;
use Milpa\Command\Standing\GrantStanding;
use Milpa\Command\Standing\GrantState;
use Milpa\Command\Standing\NamedCall;
use Milpa\Command\Standing\StandingCall;
use Milpa\Command\Standing\StandingCheck;
use Milpa\Command\Standing\StandingGrant;
use Milpa\Command\Standing\StandingJudge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What a fifth independent review found in the contract of standing grants, and what Rod decided about it on
 * 2026-10-12 (greenhouse decisions/0615, «Lo que la quinta revisión encontró»). Nine of these tests are the
 * reviewer's own, moved here as they were written; every text is built from code points, never typed.
 *
 * @subject-in milpa/command
 */
final class WhatAFifthReviewFoundInTheContractTest extends TestCase
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

    /** @return array<string, mixed> */
    private static function measuredBy(string|int|bool $mode): array
    {
        return ['conditions' => [['name' => 'no-host-names', 'statement' => 'what is published names no machine', 'by' => ['operation' => 'lab:scan', 'arguments' => ['mode' => $mode], 'free' => ['path']]]]];
    }

    /** No byte a terminal obeys, and nothing that is not plain ASCII or the contract's own prose. */
    private static function assertSafeToRead(string $said, int $atMost = 2000): void
    {
        self::assertSame(0, preg_match('/[^\x20-\x7E«»—…]/u', $said), 'it carries a byte a terminal obeys, or a character that is not shown as it is written: ' . json_encode(substr($said, 0, 300)));
        self::assertLessThan($atMost, \strlen($said), 'it is ' . \strlen($said) . ' bytes long');
    }

    // ── D8 (M1) · an argument is fixed to a value a person can read, and no other ───────────────────────────

    /** The value an argument is fixed to is compared byte by byte with what a caller sends: it is read as it is shown. */
    public function testAnArgumentIsFixedToAValueAPersonCanReadAndNoOther(): void
    {
        self::assertFalse(self::refused(self::measuredBy('strict')), 'control: «mode = strict» is a grant');
        self::assertTrue(self::refused(self::measuredBy('strict' . mb_chr(0x200B))), 'accepted: «mode» fixed to "strict" and a ZERO WIDTH SPACE — on a line it reads «mode=strict»');
        self::assertTrue(self::refused(self::measuredBy('strict ')), 'accepted: «mode» fixed to "strict" and a space after it');
        self::assertTrue(self::refused(self::measuredBy('strict' . mb_chr(0xE0073))), 'accepted: «mode» fixed to "strict" and a TAG character nobody sees');
    }

    /** What it costs in the judge: the run a person read said NO last, and the grant lends on another run's yes. */
    public function testTheRunAPersonReadSayingNoIsNotOutvotedByARunThatOnlyLooksLikeIt(): void
    {
        $asWritten = 'strict' . mb_chr(0x200B);
        try {
            $grant = Grants::grant(self::measuredBy($asWritten));
        } catch (\InvalidArgumentException) {
            self::assertTrue(true, 'the reader refuses the look-alike: nothing to judge');

            return;
        }
        $verdict = StandingJudge::judge($grant, Grants::standing([
            Grants::receipt(ok: true, arguments: ['mode' => $asWritten, 'path' => 'candidate.tar']),
            Grants::receipt(ok: false, arguments: ['mode' => 'strict', 'path' => 'candidate.tar']),
        ]), Grants::call());

        self::assertFalse($verdict->passes(), 'the grant lends: `lab:scan mode=strict` said NO of this candidate last, and a green receipt from the run spelled with a zero-width space speaks for the condition');
    }

    /** @return iterable<string, array{string}> */
    public static function valuesAPersonCannotTellApart(): iterable
    {
        yield 'a letter of another alphabet that looks the same' => ['str' . mb_chr(0x0456) . 'ct'];
        yield 'a space before it' => [' strict'];
        yield 'two spaces inside it' => ['very  strict'];
        yield 'a no-break space inside it' => ['very' . mb_chr(0xA0) . 'strict'];
        yield 'a letter with an accent — what the decision costs, pinned' => ['estr' . mb_chr(0xED) . 'cto'];
        yield 'only a space' => [' '];
        yield 'a delete' => ["str\x7fict"];
    }

    #[DataProvider('valuesAPersonCannotTellApart')]
    public function testAFixedValueIsVisibleAsciiWithSingleSpacesInsideAndNoneAround(string $value): void
    {
        foreach ([self::measuredBy($value), ['covers' => [['operation' => 'lab:publish', 'arguments' => ['channel' => $value]]]]] as $change) {
            try {
                Grants::grant($change);
                self::fail('a grant fixed an argument to ' . json_encode($value));
            } catch (\InvalidArgumentException $refused) {
                self::assertSafeToRead($refused->getMessage());
            }
        }
    }

    public function testWhatAFixedValueMayStillBe(): void
    {
        foreach (['strict', 'very strict', 'a b c', 'v1.2.3-rc.1', 'refs/heads/main', '~!@#$%^&*()_+{}|:"<>?', '!'] as $value) {
            self::assertSame($value, Grants::grant(self::measuredBy($value))->conditions[0]->by->arguments['mode']);
        }
        // The empty text was in that list, and this test pinned it as a value an argument may be fixed to. It is not
        // (the sixth review, L3): D8 says visible ASCII, and nothing is no character at all.
        self::assertTrue(self::refused(self::measuredBy('')));
        // A value that is not text is as it was: a whole number, and true or false.
        self::assertSame(7, Grants::grant(self::measuredBy(7))->conditions[0]->by->arguments['mode']);
        self::assertTrue(Grants::grant(self::measuredBy(true))->conditions[0]->by->arguments['mode']);
        // …and the class says what a host owes whoever signs: the value, shown as it is spelled.
        $said = (string) preg_replace('/\s+\*\s+/', ' ', (string) (new \ReflectionClass(NamedCall::class))->getDocComment());
        self::assertStringContainsString('shows it to whoever signs through {@see Canonical::shown()}', $said);
    }

    // ── L1 · nothing is taken off the ends of a text: a text that is not as it reads is refused ─────────────

    /** «A name ends where it ends: an id, the name of an operation […] take nothing after them, not even a newline». */
    public function testAnIdAndAnOperationTakeNothingAfterThemNotEvenANewline(): void
    {
        self::assertTrue(self::refused(['supersedes' => "older\n"]), 'control: the fourth review\'s case is refused');
        self::assertTrue(self::refused(['id' => "release-line\n"]), 'accepted: an id followed by a newline');
        self::assertTrue(self::refused(['covers' => [['operation' => "lab:publish\n"]]]), 'accepted: an operation named "lab:publish" and a newline');
    }

    /** No line break, no tab, in a text a grant is given — also at its two ends. */
    public function testATextWithALineBreakAtItsEndIsNotOneLine(): void
    {
        self::assertTrue(self::refused(['undo' => "take it\nback"]), 'control: a line break inside the text is refused');
        self::assertTrue(self::refused(['undo' => "take it back\n"]), 'accepted: «undo» with a line break at its end');
        self::assertTrue(self::refused(['undo' => "\t\0take it back"]), 'accepted: «undo» that begins with a tab and a NUL');
    }

    /**
     * The seventeen places a grant holds a text, each with a place for the text.
     *
     * @return array<string, array{\Closure(string): array<string, mixed>, string}> the change, and a text that place takes
     */
    private static function everyTextOfAGrant(): array
    {
        $condition = static fn (array $change): array => ['conditions' => [$change + ['name' => 'no-host-names', 'statement' => 'what is published names no machine', 'by' => ['operation' => 'lab:scan', 'free' => ['path']]]]];
        $falsifier = static fn (array $change): array => ['falsifier' => $change + ['name' => 'a-published-name', 'statement' => 'a published release names a machine', 'watcher' => ['operation' => 'lab:watch'], 'within_seconds' => 3600]];

        return [
            'its id' => [static fn (string $text): array => ['id' => $text], 'another-line'],
            'the grant it supersedes' => [static fn (string $text): array => ['supersedes' => $text], 'older'],
            'the stop it lifts' => [static fn (string $text): array => ['supersedes' => 'older', 'lifts' => $text], 'sha256:abc'],
            'a seat' => [static fn (string $text): array => ['to' => [$text]], 'SEAT1'],
            'how an act is taken back' => [static fn (string $text): array => ['undo' => $text], 'take it back'],
            'the name of a condition' => [static fn (string $text): array => $condition(['name' => $text]), 'no-host-names'],
            'what a condition says' => [static fn (string $text): array => $condition(['statement' => $text]), 'it names no machine'],
            'the name of the falsifier' => [static fn (string $text): array => $falsifier(['name' => $text]), 'a-published-name'],
            'what the falsifier says' => [static fn (string $text): array => $falsifier(['statement' => $text]), 'a release names a machine'],
            'the operation it covers' => [static fn (string $text): array => ['covers' => [['operation' => $text]]], 'lab:publish'],
            'the operation that measures' => [static fn (string $text): array => $condition(['by' => ['operation' => $text]]), 'lab:scan'],
            'the operation that watches' => [static fn (string $text): array => $falsifier(['watcher' => ['operation' => $text]]), 'lab:watch'],
            'the name of an argument it fixes' => [static fn (string $text): array => ['covers' => [['operation' => 'lab:publish', 'arguments' => [$text => 'stable']]]], 'channel'],
            'the name of an argument it leaves free' => [static fn (string $text): array => $condition(['by' => ['operation' => 'lab:scan', 'free' => [$text]]]), 'path'],
            'the text an argument is fixed to' => [static fn (string $text): array => ['covers' => [['operation' => 'lab:publish', 'arguments' => ['channel' => $text]]]], 'stable'],
            'the inverse its ceiling names' => [static fn (string $text): array => ['ceiling' => Grants::document()['ceiling'] + ['rollback_contract' => $text]], 'lab:unpublish'],
            'when it expires' => [static fn (string $text): array => ['expires_at' => $text], '2099-01-01T00:00:00Z'],
        ];
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function textsWithSomethingAtAnEnd(): iterable
    {
        $ends = ['a space' => ' ', 'a line feed' => "\n", 'a carriage return' => "\r", 'a tab' => "\t", 'a vertical tab' => "\x0B", 'a null byte' => "\0"];
        foreach (array_keys(self::everyTextOfAGrant()) as $field) {
            foreach ($ends as $what => $byte) {
                yield "{$field}: {$what} after it" => [$field, '', $byte];
                yield "{$field}: {$what} before it" => [$field, $byte, ''];
            }
        }
    }

    #[DataProvider('textsWithSomethingAtAnEnd')]
    public function testNoTextOfAGrantIsRepairedByTakingSomethingOffItsEnds(string $field, string $before, string $after): void
    {
        [$change, $text] = self::everyTextOfAGrant()[$field];
        self::assertFalse(self::refused($change($text)), "control: {$field} given as it reads is a grant");

        self::assertTrue(self::refused($change($before . $text . $after)), "accepted: {$field} given as " . json_encode($before . $text . $after));
    }

    /** @return iterable<string, array{string}> */
    public static function namesWithASeparatorAtAnEnd(): iterable
    {
        yield 'a colon before it' => [':lab:publish'];
        yield 'a colon after it' => ['lab:publish:'];
        yield 'a dot before it' => ['.lab.publish'];
        yield 'a dot after it' => ['lab.publish.'];
        yield 'an underscore after it' => ['lab_publish_'];
        yield 'separators at both ends' => ['.:lab:publish:.'];
    }

    #[DataProvider('namesWithASeparatorAtAnEnd')]
    public function testTheNameOfAnOperationIsNotRepairedByTakingASeparatorOffItsEnds(string $written): void
    {
        // The identity of an operation forgives how a surface SPELLS it — and that forgiveness took a separator off
        // either end as well: «:lab:publish:» was kept as «lab.publish». It is the same repair as a line break taken off.
        $places = [
            'covers' => ['covers' => [['operation' => $written]]],
            'by' => ['conditions' => [['name' => 'c', 'statement' => 's', 'by' => ['operation' => $written, 'free' => ['path']]]]],
            'watcher' => ['falsifier' => ['name' => 'f', 'statement' => 's', 'watcher' => ['operation' => $written], 'within_seconds' => 60]],
            'the inverse' => ['ceiling' => Grants::document()['ceiling'] + ['rollback_contract' => $written]],
        ];
        foreach ($places as $where => $change) {
            self::assertTrue(self::refused($change), "accepted in {$where}: " . json_encode($written));
        }
        self::assertNull(ConditionReceipt::fromArray(['by' => $written, 'arguments' => [], 'subject' => Grants::CANDIDATE, 'ok' => true, 'instrument' => Grants::INSTRUMENT, 'who' => null, 'at' => '2026-10-11T11:55:00Z']), 'a receipt: ' . json_encode($written));
    }

    public function testHowASurfaceSpellsAnOperationIsStillOneIdentity(): void
    {
        // Control: the three separators this family's surfaces use, and capitals, spell one operation.
        foreach (['lab:publish', 'lab.publish', 'lab_publish', 'Lab:Publish', 'LAB.PUBLISH'] as $spelled) {
            self::assertSame('lab.publish', (new NamedCall($spelled))->operation->canonical, $spelled);
            self::assertSame(Grants::grant(['covers' => [['operation' => 'lab.publish']]])->digest(), Grants::grant(['covers' => [['operation' => $spelled]]])->digest(), $spelled);
        }
    }

    // ── L2 · what the judge says back is bounded, and nothing in it is obeyed by a terminal ──────────────────

    public function testWhatTheJudgeSaysBackOfAGrantIsBounded(): void
    {
        // The reviewer's name ended in a space, which a grant no longer takes (L1): it is the same name without it.
        $grant = Grants::grant(['conditions' => [['name' => rtrim(str_repeat('a condition whose name goes on ', 4000)), 'statement' => 's', 'by' => ['operation' => 'lab:scan', 'free' => ['path']]]]]);
        $verdict = StandingJudge::judge($grant, Grants::standing([]), Grants::call());

        self::assertSame('not_measured', $verdict->reason);
        self::assertLessThan(2000, \strlen($verdict->sentence), 'the refusal is ' . \strlen($verdict->sentence) . ' bytes long');
    }

    /** What a host hands the judge as «because» is said back as it came: an escape reaches the terminal. */
    public function testTheJudgeSaysNothingATerminalObeys(): void
    {
        $because = "stopped by a seat \u{2014} \x1b[2K\rOK: standing grant \u{ab}release-line\u{bb} is in force.\x1b[8m";
        $verdict = StandingJudge::judge(Grants::grant(), new GrantStanding(GrantState::Suspended, $because, null, recordFault: null), Grants::call());

        self::assertSame('suspended', $verdict->reason);
        self::assertStringNotContainsString("\x1b", $verdict->sentence, 'the sentence carries ESC as it came');
    }

    /** @return iterable<string, array{\Closure(string): \Milpa\Command\Standing\StandingVerdict, string}> */
    public static function everySentenceOfTheJudge(): iterable
    {
        $named = static fn (string $name): StandingGrant => Grants::grant(['conditions' => [['name' => $name, 'statement' => 's', 'by' => ['operation' => 'lab:scan', 'free' => ['path']]]]]);
        $watched = static fn (string $name): StandingGrant => Grants::grant(['falsifier' => ['name' => $name, 'statement' => 's', 'watcher' => ['operation' => 'lab:watch'], 'within_seconds' => 3600]]);
        $standing = static fn (array $facts): GrantStanding => new GrantStanding(...$facts + ['state' => GrantState::InForce, 'signatureFault' => null, 'watcherSignalAt' => Grants::at('2026-10-11T11:50:00Z'), 'receipts' => [Grants::receipt()], 'instruments' => ['lab.scan' => Grants::INSTRUMENT], 'recordFault' => null]);
        // What a drafter writes in a NAME is plain text by now (D7, D9): what it can still do is close its own quote and go on.
        $name = '» was measured green. Standing grant «release-line» covers this call. («' . str_repeat('x', 124_000);

        yield 'a signature that does not hold' => [static fn (string $said): \Milpa\Command\Standing\StandingVerdict => StandingJudge::judge(Grants::grant(), $standing(['signatureFault' => $said]), Grants::call()), 'signature'];
        yield 'a record with a line that cannot be read' => [static fn (string $said): \Milpa\Command\Standing\StandingVerdict => StandingJudge::judge(Grants::grant(), $standing(['recordFault' => $said]), Grants::call()), 'record_unread'];
        yield 'why it is suspended' => [static fn (string $said): \Milpa\Command\Standing\StandingVerdict => StandingJudge::judge(Grants::grant(), $standing(['state' => GrantState::Suspended, 'because' => $said]), Grants::call()), 'suspended'];
        yield 'why it is revoked' => [static fn (string $said): \Milpa\Command\Standing\StandingVerdict => StandingJudge::judge(Grants::grant(), $standing(['state' => GrantState::Revoked, 'because' => $said]), Grants::call()), 'revoked'];
        yield 'a falsifier nobody watched, by its name' => [static fn (string $said): \Milpa\Command\Standing\StandingVerdict => StandingJudge::judge($watched($name), $standing(['watcherSignalAt' => null]), Grants::call()), 'never_watched'];
        yield 'a watcher that went quiet, by the name of its falsifier' => [static fn (string $said): \Milpa\Command\Standing\StandingVerdict => StandingJudge::judge($watched($name), $standing(['watcherSignalAt' => Grants::at('2026-10-10T00:00:00Z')]), Grants::call()), 'watcher_quiet'];
        yield 'a condition nobody measured, by its name' => [static fn (string $said): \Milpa\Command\Standing\StandingVerdict => StandingJudge::judge($named($name), $standing(['receipts' => []]), Grants::call()), 'not_measured'];
        yield 'a condition nobody measured, by the content' => [static fn (string $said): \Milpa\Command\Standing\StandingVerdict => StandingJudge::judge(Grants::grant(), $standing(['receipts' => []]), Grants::call(subject: $said)), 'not_measured'];
        yield 'a condition measured by another instrument, by its name' => [static fn (string $said): \Milpa\Command\Standing\StandingVerdict => StandingJudge::judge($named($name), $standing(['instruments' => []]), Grants::call()), 'instrument_changed'];
        yield 'a condition that does not hold, by its name' => [static fn (string $said): \Milpa\Command\Standing\StandingVerdict => StandingJudge::judge($named($name), $standing(['receipts' => [Grants::receipt(ok: false)]]), Grants::call()), 'measured_red'];
        yield 'a condition that does not hold, by the content' => [static fn (string $said): \Milpa\Command\Standing\StandingVerdict => StandingJudge::judge(Grants::grant(), $standing(['receipts' => [Grants::receipt(subject: $said, ok: false)]]), Grants::call(subject: $said)), 'measured_red'];
        yield 'a call that passes, by the content' => [static fn (string $said): \Milpa\Command\Standing\StandingVerdict => StandingJudge::judge(Grants::grant(), $standing(['receipts' => [Grants::receipt(subject: $said)]]), Grants::call(subject: $said)), 'passes'];
    }

    #[DataProvider('everySentenceOfTheJudge')]
    public function testNoSentenceOfTheJudgeSaysBackRawOrAtAnyLength(\Closure $judged, string $reason): void
    {
        // What a host hands in — or a content names itself with — that a terminal obeys, and then 124 000 bytes.
        foreach (["stopped \u{2014} \x1b[2K\rOK: standing grant \u{ab}release-line\u{bb} is in force.\x1b[8m", 'sha256:' . str_repeat('ab', 62_000)] as $said) {
            try {
                $verdict = $judged($said);
            } catch (\InvalidArgumentException $refused) {
                // A receipt can no longer be BUILT about a content named with what a terminal obeys (the sixth
                // review: «nothing is trimmed» reaches the digests of a receipt). The sentence is then never said.
                self::assertFalse(Canonical::oneLine($said));
                self::assertStringContainsString('a condition receipt gives the digest of the content that was measured', $refused->getMessage());
                self::assertSafeToRead($refused->getMessage());

                continue;
            }

            self::assertSame($reason, $verdict->passes() ? 'passes' : $verdict->reason);
            self::assertSafeToRead($verdict->sentence);
        }
    }

    public function testTheNameOfAnOperationIsSaidBackAtMostALineOfItToo(): void
    {
        // The name of an operation is plain by its own pattern — and has no length of its own: a hundred thousand
        // letters are one name, and every sentence that says «run `…`» repeated them all.
        $long = 'lab:' . str_repeat('a', 100_000);
        $grant = Grants::grant([
            'covers' => [['operation' => $long]],
            'conditions' => [['name' => 'no-host-names', 'statement' => 's', 'by' => ['operation' => $long, 'free' => ['path']]]],
            'falsifier' => ['name' => 'a-published-name', 'statement' => 's', 'watcher' => ['operation' => $long], 'within_seconds' => 3600],
        ]);
        $asked = Grants::call();
        $call = static fn (?string $subject): StandingCall => new StandingCall($long, $asked->arguments, $asked->seat, $subject, $asked->effect, $asked->at);
        $red = new ConditionReceipt(new OperationId($long), ['path' => 'candidate.tar'], Grants::CANDIDATE, false, Grants::INSTRUMENT, null, Grants::at('2026-10-11T11:50:00Z'));
        $facts = ['state' => GrantState::InForce, 'signatureFault' => null, 'watcherSignalAt' => Grants::at('2026-10-11T11:50:00Z'), 'receipts' => [$red], 'instruments' => [$red->by->canonical => Grants::INSTRUMENT], 'recordFault' => null];
        $verdicts = [
            'never_watched' => StandingJudge::judge($grant, new GrantStanding(...['watcherSignalAt' => null] + $facts), $call(Grants::CANDIDATE)),
            'watcher_quiet' => StandingJudge::judge($grant, new GrantStanding(...['watcherSignalAt' => Grants::at('2026-10-10T00:00:00Z')] + $facts), $call(Grants::CANDIDATE)),
            'no_subject' => StandingJudge::judge($grant, new GrantStanding(...$facts), $call(null)),
            'not_measured' => StandingJudge::judge($grant, new GrantStanding(...['receipts' => []] + $facts), $call(Grants::CANDIDATE)),
            'instrument_changed' => StandingJudge::judge($grant, new GrantStanding(...['instruments' => []] + $facts), $call(Grants::CANDIDATE)),
            'measured_red' => StandingJudge::judge($grant, new GrantStanding(...$facts), $call(Grants::CANDIDATE)),
        ];
        foreach ($verdicts as $reason => $verdict) {
            self::assertSame($reason, $verdict->reason);
            self::assertSafeToRead($verdict->sentence);
            self::assertStringContainsString('`lab:' . str_repeat('a', 92) . '… (100004 bytes as written)`', $verdict->sentence, $reason);
        }
        // …and the refusals that name the operation a document is refused under.
        $refusals = [
            'a name of an argument that is not one' => ['covers' => [['operation' => $long, 'arguments' => ['not a name' => 1]]]],
            'a value that is not one' => ['covers' => [['operation' => $long, 'arguments' => ['channel' => 'estándar']]]],
            'a free argument that is not one' => ['conditions' => [['name' => 'c', 'statement' => 's', 'by' => ['operation' => $long, 'free' => ['not a name']]]]],
            'a covered call that says what is free' => ['covers' => [['operation' => $long, 'free' => ['path']]]],
            'a covered call that is the grant itself' => ['covers' => [['operation' => 'standing:' . str_repeat('a', 100_000)]]],
        ];
        foreach ($refusals as $what => $change) {
            try {
                Grants::grant($change);
                self::fail("{$what}: taken");
            } catch (\InvalidArgumentException $refused) {
                self::assertSafeToRead($refused->getMessage(), 1200);
                self::assertStringContainsString(str_repeat('a', 80) . '… (10000', $refused->getMessage(), $what);
            }
        }
        // Control: a name of the length names have is said whole, as it always was.
        self::assertStringContainsString('Run `lab:scan` on it first', StandingJudge::judge(Grants::grant(), Grants::standing([]), Grants::call())->sentence);
        self::assertSame(str_repeat('a', 96), Canonical::short(str_repeat('a', 96)));
    }

    public function testASentenceAHostHandsInIsSaidAsItCameWhenItIsAPlainLine(): void
    {
        // The other side of it: the reason a person has to read is not cut to a line, nor spelled in escapes.
        $because = 'its falsifier «a-published-name» fired: `lab:watch` reported it, at 2026-10-11T11:55:00+00:00, run by key:' . Grants::SEAT . ' (suspension sha256:' . str_repeat('ab', 32) . ')';
        $verdict = StandingJudge::judge(Grants::grant(), new GrantStanding(GrantState::Suspended, $because, null, recordFault: null), Grants::call());

        self::assertStringContainsString('is suspended — ' . $because . '. While it is', $verdict->sentence);
        // …and the name of a condition is said in quotes, so that it ends where it ends.
        $unmeasured = StandingJudge::judge(Grants::grant(), Grants::standing([]), Grants::call());
        self::assertStringContainsString('condition "no-host-names" was never measured', $unmeasured->sentence);
    }

    public function testAProfileSaysBackWhatItRefusesBoundedToo(): void
    {
        $payload = "\x1b[2K\rOK\x1b[8m" . str_repeat('x', 300_000);
        $refusals = [
            'an inverse that is a sentence' => static fn () => new EffectProfile(Mutation::Persistent, Externality::None, Reversibility::Guaranteed, Authority::WriteAsUser, rollbackContract: $payload),
            'an axis that is not one' => static fn () => EffectProfile::fromPartial([$payload => 'read']),
            'a level that is not one' => static fn () => EffectProfile::fromPartial(['authority' => $payload]),
            'a stored axis that is not one' => static fn () => EffectProfile::fromArray(['mutation' => $payload, 'externality' => 'none', 'reversibility' => 'compensatable', 'authority' => 'read', 'subject' => 'data']),
        ];
        foreach ($refusals as $what => $refuses) {
            try {
                $refuses();
                self::fail("{$what} was taken");
            } catch (\InvalidArgumentException $refused) {
                self::assertSafeToRead($refused->getMessage(), 1200);
            }
        }
    }

    // ── L3 · a line that cannot be read is not the easy thing to skip ────────────────────────────────────────

    public function testALineThatNamesNoOperationIsNotAReceipt(): void
    {
        $line = ['by' => 'lab:scan', 'arguments' => ['path' => 'candidate.tar'], 'subject' => Grants::CANDIDATE, 'ok' => false, 'instrument' => Grants::INSTRUMENT, 'who' => null, 'at' => '2026-10-11T11:55:00Z'];

        self::assertNotNull(ConditionReceipt::fromArray($line), 'control: the line as the contract writes it');
        self::assertNull(ConditionReceipt::fromArray(['by' => ''] + $line), 'a receipt: «by» is empty');
        self::assertNull(ConditionReceipt::fromArray(['by' => 'not an operation !!'] + $line), 'a receipt: «by» is a sentence');
    }

    public function testAReceiptBuiltByHandNamesAnOperationToo(): void
    {
        // What the reader of a line refuses, the constructor refuses: a receipt is matched against a condition by
        // the operation it names, and one that names none would be matched against nothing.
        self::assertSame('lab.scan', Grants::receipt(by: 'lab:scan')->by->canonical, 'control');
        // The last one is a name that ends in a line break once its identity is taken: nothing comes after a name.
        foreach (['', 'not an operation !!', 'lab:*', "lab:scan\n."] as $by) {
            try {
                Grants::receipt(by: $by);
                self::fail('a receipt was built that names ' . json_encode($by));
            } catch (\InvalidArgumentException $refused) {
                self::assertStringContainsString('says which operation measured, by its name', $refused->getMessage());
            }
        }
    }

    public function testAHostThatDoesNotSayItsRecordReadsWholeIsNotReadAsHavingSaidSo(): void
    {
        // The safe reading is the default, as it is for the signature: a host that skipped a line it could not read
        // — the easy thing — has to SAY its record reads whole, or nothing is lent.
        $facts = ['signatureFault' => null, 'watcherSignalAt' => Grants::at('2026-10-11T11:50:00Z'), 'receipts' => [Grants::receipt()], 'instruments' => ['lab.scan' => Grants::INSTRUMENT]];
        $silent = StandingJudge::judge(Grants::grant(), new GrantStanding(GrantState::InForce, ...$facts), Grants::call());

        self::assertSame([StandingCheck::Signature, 'record_unread'], [$silent->failed, $silent->reason], 'a host said nothing of its record, and the grant lent');
        self::assertStringContainsString(GrantStanding::NOT_READ, $silent->sentence);
        $faulted = StandingJudge::judge(Grants::grant(), new GrantStanding(GrantState::InForce, ...$facts + ['recordFault' => 'line 4 is a «measured» line this house cannot read today']), Grants::call());
        self::assertSame('record_unread', $faulted->reason);
        self::assertStringContainsString('line 4 is a «measured» line this house cannot read today', $faulted->sentence);
        self::assertTrue(StandingJudge::judge(Grants::grant(), new GrantStanding(GrantState::InForce, ...$facts + ['recordFault' => null]), Grants::call())->passes(), 'control: a host that says its record reads whole');
        // The same for where the grant stands — the one default that still said «yes»: now it has to be said.
        $this->expectException(\ArgumentCountError::class);
        new GrantStanding(...$facts + ['recordFault' => null]);
    }

    // ── L5 · a grant's text has a length ─────────────────────────────────────────────────────────────────────

    public function testATextLongerThanAGrantIsRefusedBeforeItIsRead(): void
    {
        $document = (string) json_encode(Grants::document());
        $padded = static fn (int $bytes): string => substr($document, 0, -1) . str_repeat(' ', $bytes - \strlen($document)) . '}';
        self::assertSame(Grants::grant()->digest(), StandingGrant::fromJson($padded(StandingGrant::LONGEST))->digest(), 'control: a text of the longest length a grant has is read');

        $from = hrtime(true);
        try {
            StandingGrant::fromJson($padded(StandingGrant::LONGEST + 1));
            self::fail('a text longer than a grant was read');
        } catch (\InvalidArgumentException $refused) {
            self::assertStringContainsString('is longer than a standing grant is', $refused->getMessage());
        }
        // Keys chosen to land in one bucket of PHP's own table held the reader for twenty seconds at 2.4 MB: no
        // such text is read at all now, whatever its keys are.
        $colliding = '{' . implode(',', array_map(static fn (int $i): string => '"' . str_repeat('Ez', $i % 7) . str_repeat('FY', 7 - $i % 7) . $i . '":1', range(1, 60_000))) . '}';
        self::assertGreaterThan(StandingGrant::LONGEST, \strlen($colliding));
        self::assertSame(262_144, StandingGrant::LONGEST, '256 KB');
        try {
            StandingGrant::fromJson($colliding);
            self::fail('read');
        } catch (\InvalidArgumentException $refused) {
            self::assertStringContainsString('is longer than a standing grant is', $refused->getMessage());
        }
        self::assertLessThan(0.5, (hrtime(true) - $from) / 1e9, 'refusing a text for its length took as long as reading it');
    }

    public function testTheShapeOfAListIsReadFromTheTextByTheSameScanThatReadsItsKeys(): void
    {
        // One reading of the text decides both: a key said twice, and a list written as an object.
        $text = str_replace('"to":["' . Grants::SEAT . '"]', '"to":{"0":"' . Grants::SEAT . '"}', (string) json_encode(Grants::document()));
        try {
            Canonical::saidOnce($text, 'a standing grant');
            self::fail('the scan of keys does not see a list written as an object');
        } catch (\InvalidArgumentException $refused) {
            self::assertStringContainsString('a standing grant writes a list as an object with the keys "0", "1"', $refused->getMessage());
            self::assertStringContainsString('in "to"', $refused->getMessage());
        }
        // An object whose keys only look like the start of a list, or are out of order, or begin at one, is an object.
        foreach (['{"0":1,"2":2}', '{"1":1,"0":2}', '{"1":1}', '{"00":1}', '{"0":1,"a":2}', '{"a":1,"1":2}', '{"a":{"x":1},"b":[{"0x":1}]}', '{}', '[[],{}]'] as $object) {
            Canonical::saidOnce($object, 'a text');
        }
        self::assertFalse(method_exists(Canonical::class, 'listsAreLists'), 'the text is decoded a second time to ask what the scan already saw');
    }

    // ── L6 · one digest, whatever order what it digests was written in ───────────────────────────────────────

    public function testAnInstrumentHasOneDigestWhateverOrderItsScopesWereWrittenIn(): void
    {
        $handler = static fn (array $input): array => ['ok' => true];
        $actsOn = static fn (array $input): string => 'sha256:x';
        $make = static fn (array $scopes): ?string => (new Operation(name: 'lab:scan', description: 'd', handler: $handler, scopes: $scopes, effects: EffectProfile::readOnly(), actsOn: $actsOn))->instrumentDigest();

        self::assertNotNull($make(['a']));
        self::assertSame($make(['b', 'a']), $make(['a', 'b']), 'control: plain scopes');
        self::assertSame($make(['1.0', '1.00']), $make(['1.00', '1.0']));
    }

    public function testWhatIsJudgedAndWhatJudgesHaveOneDigestWhateverOrderTheyWereWrittenIn(): void
    {
        // The same default comparison, in the three other places of this package a digest is taken of a sorted list.
        self::assertSame((new ContextFacts(scopes: ['1.0', '1.00']))->fingerprint(), (new ContextFacts(scopes: ['1.00', '1.0']))->fingerprint());
        $policy = static fn (array $rules): string => (new DeclaredAuthorityPolicy('lab', $rules))->digest();
        $rule = static fn (array $scopes): array => ['authority' => Authority::Read, 'scopes' => $scopes];
        self::assertSame($policy(['lab.scan' => $rule(['1.0', '1.00'])]), $policy(['lab.scan' => $rule(['1.00', '1.0'])]));
        self::assertSame($policy(['1.0' => $rule(['a']), '1.00' => $rule(['a'])]), $policy(['1.00' => $rule(['a']), '1.0' => $rule(['a'])]));
        $certificate = static fn (array $predicate, array $covers, array $envelope): string => (new DescentCertificate('lab.verifier', 'lab.publish', $predicate, $covers, EffectProfile::readOnly(), envelope: $envelope))->canonicalPayload();
        self::assertSame($certificate(['1.0' => 'a', '1.00' => 'b'], ['x'], []), $certificate(['1.00' => 'b', '1.0' => 'a'], ['x'], []));
        self::assertSame($certificate(['a' => 1], ['1.0', '1.00'], []), $certificate(['a' => 1], ['1.00', '1.0'], []));
        self::assertSame($certificate(['a' => 1], ['x'], ['k' => ['1.0' => 1, '1.00' => 2]]), $certificate(['a' => 1], ['x'], ['k' => ['1.00' => 2, '1.0' => 1]]));
        // Control: names that do not look like numbers were always one digest, and are the digest they were.
        self::assertSame((new ContextFacts(scopes: ['b', 'a']))->fingerprint(), (new ContextFacts(scopes: ['a', 'b']))->fingerprint());
    }

    // ── L7 · a grant built by hand is held to what the reader holds a document to ────────────────────────────

    public function testAGrantBuiltByHandReadsBackFromItsOwnSpelling(): void
    {
        $by = new NamedCall(new OperationId('lab:scan'));
        try {
            $grant = new StandingGrant(
                'g',
                [Grants::SEAT],
                [new NamedCall('lab:publish')],
                new EffectProfile(Mutation::Persistent, Externality::Public, Reversibility::Compensatable, Authority::WriteAsUser, subject: Subject::Executable, rollbackContract: "ask a person\nOK: in force"),
                [new GrantCondition('c', 's', $by)],
                'u',
                new Falsifier('f', 's', new NamedCall('lab:watch'), 60),
            );
        } catch (\InvalidArgumentException) {
            self::assertTrue(true, 'refused at construction, like the other nine texts');

            return;
        }

        self::assertSame($grant->digest(), StandingGrant::fromJson($grant->canonical())->digest());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function whatTheReaderRefusesAndAConstructorTook(): iterable
    {
        $profile = static fn (?string $inverse): EffectProfile => new EffectProfile(Mutation::Persistent, Externality::Public, Reversibility::Compensatable, Authority::WriteAsUser, subject: Subject::Executable, rollbackContract: $inverse);
        yield 'an inverse with what a terminal obeys' => [['ceiling' => $profile("lab:unpublish\x1b[8m")]];
        yield 'an inverse that is a sentence, under a ceiling that asks for none' => [['ceiling' => $profile('ask a person to delete the release by hand')]];
        yield 'how an act is taken back, with spaces around it' => [['undo' => ' take it back ']];
        yield 'an expiry with a fraction of a second' => [['expiresAt' => new \DateTimeImmutable('2027-01-01T00:00:00.500000Z')]];
        yield 'an expiry in a year its own spelling cannot say' => [['expiresAt' => (new \DateTimeImmutable('9999-12-31T23:59:59Z'))->modify('+1 day')]];
        yield 'a stop it lifts, with a space after it' => [['supersedes' => 'older', 'lifts' => 'sha256:abc ']];
        yield 'a seat with a space before it' => [['to' => [' ' . Grants::SEAT]]];
    }

    /** @param array<string, mixed> $change */
    #[DataProvider('whatTheReaderRefusesAndAConstructorTook')]
    public function testTheConstructorRefusesWhatTheReaderRefuses(array $change): void
    {
        $parts = Grants::grant();
        $build = static fn (array $with): StandingGrant => new StandingGrant(...$with + ['id' => 'g', 'to' => $parts->to, 'covers' => $parts->covers, 'ceiling' => $parts->ceiling, 'conditions' => $parts->conditions, 'undo' => 'u', 'falsifier' => $parts->falsifier]);
        $control = $build([]);
        self::assertSame($control->digest(), StandingGrant::fromJson($control->canonical())->digest(), 'control: the grant as it is built reads back');

        $this->expectException(\InvalidArgumentException::class);
        $build($change);
    }

    public function testAnInverseIsTheNameOfOneOperationUnderAnyCeiling(): void
    {
        // The README said so; under a ceiling that is not «guaranteed» any sentence was kept.
        $ceiling = Grants::document()['ceiling'];
        self::assertTrue(self::refused(['ceiling' => $ceiling + ['rollback_contract' => 'ask a person to delete the release by hand']]), 'accepted: a sentence as the inverse of a compensatable ceiling');
        self::assertSame('lab:unpublish', Grants::grant(['ceiling' => $ceiling + ['rollback_contract' => 'lab:unpublish']])->toArray()['ceiling']['rollback_contract'], 'control');
    }

    public function testWhatIsSaidBackIsNeverCutInsideAnEscape(): void
    {
        // At most a line of it — and a line that ends in half an escape is one a reader takes for something else.
        foreach (range(80, 100) as $plain) {
            $said = Canonical::shown(str_repeat('x', $plain) . "\x1b\x1b\u{202e}" . str_repeat('y', 60));
            $shown = substr($said, 0, (int) strpos($said, '…'));

            self::assertSame(0, preg_match('/\\\\(?:u[0-9a-f]{0,3})?$/', $shown), 'cut inside an escape: ' . $said);
            self::assertGreaterThanOrEqual(91, \strlen($shown), 'more was left out than the half of one escape: ' . $said);

            // The short escapes too — a line break, a quote, a backslash: an odd run of backslashes at the end is half of one.
            $said = Canonical::shown(str_repeat('x', $plain) . "\n\"\\\n\"\\" . str_repeat('y', 60));
            $shown = substr($said, 0, (int) strpos($said, '…'));
            self::assertSame(0, preg_match('/(?<!\\\\)(?:\\\\\\\\)*\\\\$/', $shown), 'cut inside an escape: ' . $said);
            self::assertGreaterThanOrEqual(95, \strlen($shown), 'more was left out than the half of one escape: ' . $said);
        }
        // …and what comes before the cut is all there: an escape in the middle of the line does not end it.
        self::assertStringStartsWith('"a\"b\\\\c\nd' . str_repeat('x', 80), Canonical::shown("a\"b\\c\nd" . str_repeat('x', 300)));
    }

    public function testASentenceOfTheHostIsCutAtItsLengthAndNotBeforeIt(): void
    {
        $exactly = str_repeat('ñ', 480);
        self::assertSame($exactly, Canonical::said($exactly), 'a sentence of exactly the length a verdict repeats is repeated whole');
        self::assertSame($exactly . '… (962 bytes as written)', Canonical::said($exactly . 'ñ'), 'one character more, and it is cut where a character ends');
        self::assertSame('', Canonical::said(''));
    }

    public function testAnOperationHandedInAsAnIdentityEndsWhereItEndsToo(): void
    {
        // An identity built elsewhere has already had its ends taken off — and what is left may still end in a line break.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not name one operation');
        new NamedCall(new OperationId("lab:scan\n."));
    }

    // ── D5, complete · a NO of that instrument on that content stops the act, from whatever run ─────────────

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function runsOfTheInstrumentThatAreNotTheOneDescribed(): iterable
    {
        yield 'one argument more' => [['path' => 'candidate.tar', 'mode' => 'strict', 'deep' => true]];
        yield 'without the argument the condition fixes' => [['path' => 'candidate.tar']];
        yield 'the fixed argument at another value' => [['path' => 'candidate.tar', 'mode' => 'loose']];
        yield 'the fixed argument as another type' => [['path' => 'candidate.tar', 'mode' => true]];
        yield 'the bare instrument, with no argument at all' => [[]];
    }

    /** @param array<string, mixed> $arguments */
    #[DataProvider('runsOfTheInstrumentThatAreNotTheOneDescribed')]
    public function testANoOfThatInstrumentOnThatContentStopsTheActFromWhateverRun(array $arguments): void
    {
        $grant = Grants::grant(self::measuredBy('strict'));
        $green = Grants::receipt(arguments: ['path' => 'candidate.tar', 'mode' => 'strict']);
        self::assertTrue(StandingJudge::judge($grant, Grants::standing([$green]), Grants::call())->passes(), 'control');

        $verdict = StandingJudge::judge($grant, Grants::standing([$green, Grants::receipt(ok: false, arguments: $arguments)]), Grants::call());

        self::assertSame('measured_red', $verdict->passes() ? 'PASSES' : $verdict->reason, 'the instrument said no of this very content, and the call passed');
        // …until that content is measured again by the run the condition describes.
        self::assertTrue(StandingJudge::judge($grant, Grants::standing([$green, Grants::receipt(ok: false, arguments: $arguments), $green]), Grants::call())->passes());
        // A YES from that same run is still not this condition measured: green stays closed.
        self::assertSame('not_measured', StandingJudge::judge($grant, Grants::standing([Grants::receipt(ok: true, arguments: $arguments)]), Grants::call())->reason);
    }

    public function testANoAboutOtherContentOrFromAnotherOperationSaysNothingOfThisCondition(): void
    {
        $grant = Grants::grant(self::measuredBy('strict'));
        $run = ['path' => 'candidate.tar', 'mode' => 'strict'];
        $green = Grants::receipt(arguments: $run);

        self::assertTrue(StandingJudge::judge($grant, Grants::standing([$green, Grants::receipt(subject: 'sha256:another', ok: false, arguments: $run)]), Grants::call())->passes(), 'a no about other content');
        self::assertTrue(StandingJudge::judge($grant, Grants::standing([$green, Grants::receipt(ok: false, by: 'lab:other', arguments: $run)]), Grants::call())->passes(), 'a no from another operation');
    }

    // ── D9 · a text a grant keeps is refused, too, for what cannot be seen in it ─────────────────────────────

    /** @return iterable<string, array{int}> */
    public static function whatCannotBeSeen(): iterable
    {
        $named = [
            'a zero-width space' => 0x200B, 'a zero-width non-joiner' => 0x200C, 'a zero-width joiner' => 0x200D, 'a soft hyphen' => 0x00AD,
            'a word joiner' => 0x2060, 'an invisible times' => 0x2062, 'an invisible plus' => 0x2064, 'a deprecated format character' => 0x206A, 'the last of them' => 0x206F,
            'a byte order mark' => 0xFEFF, 'an interlinear annotation anchor' => 0xFFF9, 'an interlinear annotation terminator' => 0xFFFB,
            'the language tag' => 0xE0001, 'a tag letter' => 0xE0073, 'the cancel tag' => 0xE007F, 'an unassigned place of the tag block' => 0xE0002,
            'a variation selector' => 0xFE0F, 'the first variation selector' => 0xFE00, 'a variation selector of the supplement' => 0xE0100, 'the last of them' . ' ' => 0xE01EF,
            'a combining grapheme joiner' => 0x034F, 'a mongolian free variation selector' => 0x180B, 'the third of them' => 0x180D, 'the fourth of them' => 0x180F,
            'a no-break space' => 0x00A0, 'an ogham space mark' => 0x1680, 'a mongolian vowel separator' => 0x180E, 'an en quad' => 0x2000, 'a hair space' => 0x200A,
            'a narrow no-break space' => 0x202F, 'a medium mathematical space' => 0x205F, 'an ideographic space' => 0x3000,
            'a khmer inherent vowel' => 0x17B4, 'the other khmer inherent vowel' => 0x17B5,
            'a hangul choseong filler' => 0x115F, 'a hangul jungseong filler' => 0x1160, 'a hangul filler' => 0x3164, 'a halfwidth hangul filler' => 0xFFA0, 'a blank braille pattern' => 0x2800,
            'a private-use character' => 0xE000, 'a private-use character of plane 15' => 0xF0000, 'a noncharacter' => 0xFDD0, 'the last place of the first plane' => 0xFFFF, 'a place nothing is assigned to' => 0x0378,
            'a shorthand format control' => 0x1BCA0, 'a musical format control' => 0x1D173, 'an arabic number sign' => 0x0600,
        ];
        foreach ($named as $what => $point) {
            yield trim($what) . \sprintf(' (U+%04X)', $point) => [$point];
        }
    }

    #[DataProvider('whatCannotBeSeen')]
    public function testATextAGrantKeepsCarriesNothingThatCannotBeSeen(int $point): void
    {
        $character = mb_chr($point);
        $kept = ['undo' => 'take it' . $character . ' back', 'conditions' => [['name' => 'no-host' . $character . 'names', 'statement' => 'it names' . $character . 'no machine', 'by' => ['operation' => 'lab:scan', 'free' => ['path']]]]];
        foreach ($kept as $field => $value) {
            try {
                $grant = Grants::grant([$field => $value]);
                self::fail("accepted: «{$field}» carrying " . \sprintf('U+%04X', $point) . ', and written back as ' . json_encode($grant->toArray()[$field]));
            } catch (\InvalidArgumentException $refused) {
                self::assertStringContainsString('one line of plain text', $refused->getMessage());
                self::assertSafeToRead($refused->getMessage());
            }
        }
        foreach (['to' => [Grants::SEAT . $character], 'lifts' => 'sha256:ab' . $character . 'cd'] as $field => $value) {
            self::assertTrue(self::refused(['supersedes' => 'older', $field => $value]), "accepted: «{$field}» carrying " . \sprintf('U+%04X', $point));
        }
    }

    /**
     * THE RULE, SAID BY THE PROPERTY AND NOT BY A LIST (decided by Rod, 2026-10-12): no code point Unicode itself
     * says is shown as nothing — `Default_Ignorable_Code_Point` — is taken in a text a grant keeps.
     *
     * Asked of every code point there is, through the reader, with a second instrument: the Unicode tables of the
     * PCRE this suite runs on. A character added to that set tomorrow turns this test red the day the tables know it.
     */
    public function testNoCodePointUnicodeSaysIsShownAsNothingIsTaken(): void
    {
        self::assertSame(0, @preg_match('/\p{DI}/u', 'a'), 'this PCRE does not know Default_Ignorable_Code_Point (it needs PCRE2 10.40): the rule cannot be asked here');
        $every = '';
        for ($point = 0; $point <= 0x10FFFF; ++$point) {
            $every .= $point >= 0xD800 && $point <= 0xDFFF ? '' : mb_chr($point, 'UTF-8');
        }
        preg_match_all('/\p{DI}/u', $every, $found);
        $ignorable = $found[0];
        // Controls of the instrument: it sees the set, and the set is the one Unicode publishes — the soft hyphen,
        // the zero-width space, a variation selector of the supplement and the reserved places of the tag block.
        self::assertGreaterThan(4000, \count($ignorable));
        foreach ([0x00AD, 0x200B, 0x17B4, 0x3164, 0xE0100, 0xE0FFF] as $known) {
            self::assertContains(mb_chr($known, 'UTF-8'), $ignorable, \sprintf('U+%04X', $known));
        }
        self::assertNotContains('a', $ignorable);
        self::assertNotContains(mb_chr(0x00E9, 'UTF-8'), $ignorable);

        $taken = [];
        foreach ($ignorable as $character) {
            if (Canonical::oneLine('a' . $character . 'b')) {
                $taken[] = \sprintf('U+%04X', mb_ord($character, 'UTF-8'));
            }
        }

        self::assertSame([], $taken, 'shown as nothing, and taken in a text a grant keeps');
    }

    public function testWhatAPersonWritesInTheirOwnLanguageIsStillAGrant(): void
    {
        // The control the decision asked for: accents, the letter eñe, the marks that open a question and an
        // exclamation, both kinds of quotes — and the ordinary space, as many as the sentence has.
        $spanish = '¿Se retira la versión y se publica la anterior? ¡Sí! — «sin excepción», "siempre" y \'cuanto antes\'; año, pingüino, niño.';
        $others = ['ce qui est publié ne nomme aucune machine ; jamais', "\u{5e7}\u{5d5}\u{5d1}\u{5e5} release \u{5d0}\u{5d7}\u{5d3}", "\u{0645}\u{0644}\u{0641} release", "\u{767a}\u{884c}\u{3092}\u{53d6}\u{308a}\u{6d88}\u{3059}", 'two  spaces, and an e' . "\u{0301}" . ' written with its accent apart', 'the replacement character ' . "\u{fffd}" . ' is a character', 'a smile ' . "\u{1f600}"];
        foreach ([$spanish, ...$others] as $text) {
            $grant = Grants::grant(['undo' => $text, 'conditions' => [['name' => $text, 'statement' => $text, 'by' => ['operation' => 'lab:scan', 'free' => ['path']]]], 'falsifier' => ['name' => $text, 'statement' => $text, 'watcher' => ['operation' => 'lab:watch'], 'within_seconds' => 60]]);

            self::assertSame([$text, $text, $text, $text, $text], [$grant->undo, $grant->conditions[0]->name, $grant->conditions[0]->statement, $grant->falsifier->name, $grant->falsifier->statement]);
            self::assertSame($grant->digest(), StandingGrant::fromJson($grant->canonical())->digest());
        }
    }
}
