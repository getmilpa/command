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

use Milpa\Command\Effect\Authority;
use Milpa\Command\Effect\EffectProfile;
use Milpa\Command\Effect\Externality;
use Milpa\Command\Effect\Mutation;
use Milpa\Command\Effect\Reversibility;
use Milpa\Command\Standing\Canonical;
use Milpa\Command\Standing\ConditionReceipt;
use Milpa\Command\Standing\GrantStanding;
use Milpa\Command\Standing\GrantState;
use Milpa\Command\Standing\NamedCall;
use Milpa\Command\Standing\StandingCheck;
use Milpa\Command\Standing\StandingGrant;
use Milpa\Command\Standing\StandingJudge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What a fourth independent review found in the contract of standing grants, each as its own probe reproduced it
 * (greenhouse decisions/0615, «Lo que la cuarta revisión encontró»). Nine of these tests are the reviewer's own,
 * moved here as they were written; every one was red on the head it read.
 *
 * @subject-in milpa/command
 */
final class WhatAFourthReviewFoundInTheContractTest extends TestCase
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

    /** What the reader says of a text it refuses — or `null`: it took it. */
    private static function refusal(string $text): ?string
    {
        try {
            StandingGrant::fromJson($text);
        } catch (\InvalidArgumentException $refused) {
            return $refused->getMessage();
        }

        return null;
    }

    /** The lab grant as text, with something put in place of something else. */
    private static function written(string $find, string $put): string
    {
        $text = (string) json_encode(Grants::document(), \JSON_UNESCAPED_SLASHES);
        self::assertSame(1, substr_count($text, $find), "the lab grant says {$find} once");

        return str_replace($find, $put, $text);
    }

    // ── M1 · a name ends where it ends ──────────────────────────────────────────────────────────────────────

    /** «supersedes another grant by its id»: an id followed by a newline is not an id. */
    public function testWhatAGrantSupersedesIsAnIdAndNothingAfterIt(): void
    {
        self::assertTrue(self::refused(['supersedes' => 'the old one']), 'control: a name that is not an id is refused');
        self::assertTrue(self::refused(['supersedes' => "older\n"]), 'accepted: «supersedes» = "older" and a newline — it names no grant a house holds');
        self::assertFalse(self::refused(['supersedes' => 'older']), 'control: an id is taken');
    }

    /** «and never itself». */
    public function testAGrantNeverSupersedesItselfHoweverItIsSpelled(): void
    {
        self::assertTrue(self::refused(['supersedes' => 'release-line']), 'control: superseding itself is refused');
        self::assertTrue(self::refused(['supersedes' => "release-line\n"]), 'accepted: «release-line» supersedes "release-line" and a newline');
    }

    /** An operation is named whole: nothing after the name — where a grant covers, measures and watches. */
    public function testAnOperationNameEndsWhereItEnds(): void
    {
        self::assertTrue(self::refused(['covers' => [['operation' => 'standing']]]), 'control: «standing» is refused');
        self::assertTrue(self::refused(['covers' => [['operation' => "standing\n."]]]), 'accepted: an operation named "standing" and a newline');
        self::assertTrue(self::refused(['covers' => [['operation' => "lab:publish\n."]]]), 'accepted: an operation that names no call');
        foreach (["lab:scan\n.", "lab.scan\n:", "lab\n.scan"] as $name) {
            try {
                new NamedCall($name);
                self::fail('a named call took ' . json_encode($name));
            } catch (\InvalidArgumentException $refused) {
                self::assertStringContainsString('does not name one operation', $refused->getMessage());
            }
        }
        // What is blank around a name WAS taken off it, and this test pinned that. It is refused now (the fifth
        // review, L1): a name is given as it reads. What stays is that a surface's spelling is one identity.
        foreach ([" lab:scan\n", "lab:scan\n", ' lab:scan', "lab:scan\0"] as $name) {
            try {
                new NamedCall($name);
                self::fail('a named call took ' . json_encode($name));
            } catch (\InvalidArgumentException $refused) {
                self::assertStringContainsString('does not name one operation', $refused->getMessage());
            }
        }
        self::assertSame('lab.scan', (new NamedCall('lab:scan'))->operation->canonical, 'control: a name as a surface spells it');
    }

    /** The third place the same pattern stands: the operation a ceiling names as its inverse. */
    public function testTheOperationThatUndoesIsNamedWholeToo(): void
    {
        $guaranteed = static fn (string $inverse): EffectProfile => new EffectProfile(Mutation::Persistent, Externality::None, Reversibility::Guaranteed, Authority::WriteAsUser, rollbackContract: $inverse);
        self::assertSame('lab.unpublish', $guaranteed('lab:unpublish')->rollbackOperation()?->canonical, 'control: an operation is taken as the inverse');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('describes an inverse instead of naming one');
        $guaranteed("lab:unpublish\n.");
    }

    // ── M2 · the scan costs what the text is long ───────────────────────────────────────────────────────────

    /** The key scan is linear in the text: four times the text, about four times the time (and never sixteen). */
    public function testTheKeyScanCostsWhatTheTextIsLong(): void
    {
        $text = static fn (int $n): string => '{' . implode(',', array_map(static fn (int $i): string => '"k' . $i . '":[]', range(1, $n))) . '}';
        // The best of three: a machine doing something else makes one run slow, never all of them fast.
        $seconds = static function (string $json): float {
            $best = \INF;
            for ($run = 0; $run < 3; ++$run) {
                $from = hrtime(true);
                Canonical::saidOnce($json, 'a text');
                $best = min($best, (hrtime(true) - $from) / 1e9);
            }

            return $best;
        };
        $small = $seconds($text(5000));
        $large = $seconds($text(20000));

        self::assertLessThan(8, $large / $small, \sprintf('four times the text took %.1f times as long (%.3f s → %.3f s)', $large / $small, $small, $large));
    }

    // ── L1 · one spelling, whatever order its author wrote it in ────────────────────────────────────────────

    public function testOneDocumentHasOneDigestWhateverOrderItsArgumentsWereWrittenIn(): void
    {
        // The reviewer's test named its arguments «1.0» and «1.00», which sorted as numbers are equal: two orders,
        // two digests. Such a name is refused now (L7, below), so the same is asked with names that are taken —
        // and of the spelling itself, where a map may still carry keys that look like numbers
        // ({@see self::testTheCanonicalOrderIsTheOrderOfTheBytesAndTheClassSaysSo()}).
        $one = Grants::grant(['covers' => [['operation' => 'lab:publish', 'arguments' => ['b' => 'x', 'B' => 'y', 'a_b' => 'z']]]]);
        $other = Grants::grant(['covers' => [['operation' => 'lab:publish', 'arguments' => ['a_b' => 'z', 'b' => 'x', 'B' => 'y']]]]);

        self::assertSame($one->digest(), $other->digest());
        self::assertSame('{"1.0":"a","1.00":"b"}', Canonical::json(['1.00' => 'b', '1.0' => 'a']));
    }

    /** The seats are a set. */
    public function testOneDocumentHasOneDigestWhateverOrderItsSeatsWereWrittenIn(): void
    {
        self::assertSame(Grants::grant(['to' => ['7', '007']])->digest(), Grants::grant(['to' => ['007', '7']])->digest());
    }

    public function testTheArgumentsACallMayCarryAreASetToo(): void
    {
        $spelled = static fn (array $free): string => (string) json_encode((new NamedCall('lab:scan', free: $free))->toArray());

        self::assertSame($spelled(['b', 'B', 'a_b']), $spelled(['a_b', 'b', 'B']));
        self::assertSame('{"operation":"lab.scan","free":["B","a_b","b"]}', $spelled(['b', 'a_b', 'B']));
    }

    public function testTheCanonicalOrderIsTheOrderOfTheBytesAndTheClassSaysSo(): void
    {
        // Sorted the way any implementation sorts text — not the way one language compares what looks like a number.
        self::assertSame('{"10":2,"9":1}', Canonical::json(['9' => 1, '10' => 2]));
        self::assertSame(Canonical::json(['0e1' => 'a', '0e2' => 'b']), Canonical::json(['0e2' => 'b', '0e1' => 'a']));
        self::assertSame('{"1.0":"a","1.00":"b"}', Canonical::json(['1.00' => 'b', '1.0' => 'a']));
        self::assertSame('{"B":1,"a":2,"b":{"10":1,"9":2}}', Canonical::json(['b' => ['9' => 2, '10' => 1], 'a' => 2, 'B' => 1]));
        $says = (string) (new \ReflectionClass(Canonical::class))->getDocComment();
        self::assertStringContainsString('by the bytes of its keys', $says);
        self::assertStringContainsString('serialize_precision', $says);
    }

    // ── L2 · a value the reader does not read ───────────────────────────────────────────────────────────────

    /** «Any key it does not read» is refused; a value it does not read is a line nobody keeps too. */
    public function testARollbackContractThatIsNotTextIsNotDroppedInSilence(): void
    {
        $ceiling = Grants::document()['ceiling'];
        $said = ['ceiling' => $ceiling + ['rollback_contract' => ['operation' => 'lab:unpublish']]];

        self::assertTrue(self::refused($said) || Grants::grant($said)->digest() !== Grants::grant()->digest(), 'accepted, with the digest of the grant that names no rollback at all');
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function ceilingsReadAsSomethingElse(): iterable
    {
        $readOnly = ['mutation' => 'none', 'externality' => 'none', 'reversibility' => 'guaranteed', 'authority' => 'read', 'subject' => 'none'];
        $written = Grants::document()['ceiling'];
        yield 'an inverse given as an object' => [$written + ['rollback_contract' => ['operation' => 'lab:unpublish']], 'rollback_contract'];
        yield 'an inverse given as a list of two' => [$written + ['rollback_contract' => ['lab:unpublish', 'lab:announce-withdrawal']], 'rollback_contract'];
        yield 'an inverse given as a number' => [$written + ['rollback_contract' => 7], 'rollback_contract'];
        yield 'an inverse given as blank text' => [$written + ['rollback_contract' => '  '], 'rollback_contract'];
        yield 'a ceiling that changes nothing, said «guaranteed»' => [$readOnly, 'reversibility'];
        yield 'a ceiling «guaranteed» by an inverse that is a sentence' => [['mutation' => 'persistent', 'externality' => 'none', 'reversibility' => 'guaranteed', 'authority' => 'write_as_user', 'subject' => 'data', 'rollback_contract' => 'delete the file by hand'], 'reversibility'];
        yield 'an axis that is not one of its values' => [['mutation' => 'a lot'] + $written, 'mutation'];
        yield 'an axis given as a list' => [['subject' => ['data']] + $written, 'subject'];
        yield 'a ceiling that changes nothing, said «guaranteed» by an inverse' => [$readOnly + ['rollback_contract' => 'lab:undo'], 'rollback_contract'];
    }

    /** @param array<string, mixed> $ceiling */
    #[DataProvider('ceilingsReadAsSomethingElse')]
    public function testACeilingIsReadAsItIsWrittenOrItIsRefused(array $ceiling, string $what): void
    {
        try {
            $grant = Grants::grant(['ceiling' => $ceiling]);
        } catch (\InvalidArgumentException $refused) {
            self::assertStringContainsString("«{$what}»", $refused->getMessage());

            return;
        }
        self::fail('accepted, and written back as ' . json_encode($grant->toArray()['ceiling']));
    }

    public function testACeilingThatSaysWhatItIsReadAsIsTaken(): void
    {
        // Controls: the same ceilings, written as they are read; an inverse in words on a ceiling that asks none;
        // and an inverse that says nothing at all.
        $written = Grants::document()['ceiling'];
        $readOnly = ['mutation' => 'none', 'externality' => 'none', 'reversibility' => 'not_applicable', 'authority' => 'read', 'subject' => 'none'];

        self::assertSame($readOnly, Grants::grant(['ceiling' => $readOnly])->toArray()['ceiling']);
        self::assertSame('lab:unpublish', Grants::grant(['ceiling' => $written + ['rollback_contract' => 'lab:unpublish']])->toArray()['ceiling']['rollback_contract']);
        self::assertSame(Grants::grant()->digest(), Grants::grant(['ceiling' => $written + ['rollback_contract' => null]])->digest(), 'an inverse given as nothing is no inverse');
        $guaranteed = ['mutation' => 'persistent', 'externality' => 'none', 'reversibility' => 'guaranteed', 'authority' => 'write_as_user', 'subject' => 'data', 'rollback_contract' => 'lab:unpublish'];
        self::assertSame($guaranteed, Grants::grant(['ceiling' => $guaranteed])->toArray()['ceiling']);
    }

    // ── L3 · an expiry the reader takes is one it reads back ────────────────────────────────────────────────

    /** @return iterable<string, array{string}> */
    public static function expiriesWhoseOwnSpellingIsNotTheForm(): iterable
    {
        yield 'the last second of 9999, west of UTC' => ['9999-12-31T23:59:59-06:00'];
        yield 'the last second of 9999, one minute west' => ['9999-12-31T23:59:59-00:01'];
        yield 'the first second of 0000, one minute east' => ['0000-01-01T00:00:00+00:01'];
        yield 'the first second of 0000, far east' => ['0000-01-01T00:00:00+23:59'];
    }

    #[DataProvider('expiriesWhoseOwnSpellingIsNotTheForm')]
    public function testAnExpiryWhoseOwnSpellingTheReaderRefusesIsRefusedWhenItIsRead(string $written): void
    {
        // It was accepted, and its canonical form — the instant in UTC — was a text the reader refuses.
        self::assertTrue(self::refused(['expires_at' => $written]), 'accepted: ' . $written);
        // …and whoever wrote it is told the form, by the reader: not that a grant built in code ends badly.
        self::assertStringContainsString('gives «expires_at» as a date and time with its offset, in ISO 8601', (string) self::refusal((string) json_encode(Grants::document(['expires_at' => $written]))));
    }

    public function testAnExpiryTheReaderTakesIsOneItReadsBackFromItsOwnSpelling(): void
    {
        foreach (['9999-12-31T23:59:59Z', '9999-12-31T23:59:59+06:00', '0000-01-01T00:00:00Z', '0000-01-01T00:00:00-06:00', '2099-01-01T00:00:00-06:00'] as $written) {
            $grant = Grants::grant(['expires_at' => $written]);

            self::assertSame($grant->digest(), StandingGrant::fromJson($grant->canonical())->digest(), $written);
        }
    }

    // ── L4 · the scan of keys answers for any text ──────────────────────────────────────────────────────────

    /** @return iterable<string, array{string}> */
    public static function textsThatDoNotParse(): iterable
    {
        yield 'a text that is never closed' => ['{"a'];
        yield 'a backslash at the very end' => ['{"a\\'];
        yield 'one quote and nothing else' => ['"'];
        yield 'a million lists opened and none closed' => [str_repeat('[', 1_000_000)];
        yield 'nothing' => [''];
    }

    #[DataProvider('textsThatDoNotParse')]
    public function testTheScanOfKeysRefusesATextThatDoesNotParseAndItReturns(string $text): void
    {
        // The method is part of the contract and public: asked of a text that does not parse, it never returned.
        // Asked in a process of its own, with a limit, so that a reader that does not return is a failure and not a
        // suite that never ends.
        $script = 'require $argv[1]; error_reporting(0); set_time_limit(5); $text = (string) file_get_contents("php://stdin");'
            . ' try { Milpa\\Command\\Standing\\Canonical::saidOnce($text, "a text"); echo "RETURNED"; }'
            . ' catch (InvalidArgumentException $refused) { echo "REFUSED: ", $refused->getMessage(); }';
        $child = proc_open([\PHP_BINARY, '-d', 'display_errors=0', '-d', 'memory_limit=64M', '-r', $script, '--', \dirname(__DIR__, 2) . '/vendor/autoload.php'], [['pipe', 'r'], ['pipe', 'w'], ['file', '/dev/null', 'w']], $pipes);
        self::assertIsResource($child);
        fwrite($pipes[0], $text);
        fclose($pipes[0]);
        $said = (string) stream_get_contents($pipes[1]);
        proc_close($child);

        self::assertStringStartsWith('REFUSED: a text is not JSON', $said, 'it did not return, or it answered for a text it could not read');
    }

    public function testTheScanOfKeysStillAnswersForJsonThatIsNotAnObject(): void
    {
        foreach (['[]', '"text"', '7', 'null', '[{"a":1},{"a":2}]', '{"a":{"a":1}}'] as $json) {
            Canonical::saidOnce($json, 'a text');
        }
        // …and, asked here, of texts that do not parse and that the scan always came back from.
        foreach (['', '{"a":1,}', '{"a":1}{"a":2}', "{'a':1}", str_repeat('[', 513) . str_repeat(']', 513)] as $text) {
            try {
                Canonical::saidOnce($text, 'a text');
                self::fail('it answered for ' . json_encode(substr($text, 0, 20)));
            } catch (\InvalidArgumentException $refused) {
                self::assertStringStartsWith('a text is not JSON this contract reads', $refused->getMessage());
            }
        }
    }

    public function testWhatIsSaidBackOfAValueThatIsNotTextIsBoundedToo(): void
    {
        self::assertSame('"budget"', Canonical::shown('budget'));
        self::assertSame('{"channel":["a","b"]}', Canonical::shown(['channel' => ['a', 'b']]));
        self::assertSame('null', Canonical::shown(null));
        self::assertSame('"a/b \\u00e9 \\u001b"', Canonical::shown("a/b \u{e9} \x1b"));
        self::assertSame('"\\ufffd"', Canonical::shown("\xB1"), 'bytes that are not text are said as what they are not');
        self::assertSame('"a\\u007fb \\u0085 \\u202e"', Canonical::shown("a\x7fb \u{85} \u{202e}"), 'the one control character JSON leaves as it is, and the ones above it');
        $long = Canonical::shown(str_repeat('x', 500));
        self::assertSame(96, strpos($long, '…'));
        self::assertStringEndsWith('… (502 bytes as written)', $long);
        self::assertSame(98, \strlen(Canonical::shown(str_repeat('x', 94))) + 2, 'what fits in a line is said whole');
        self::assertStringNotContainsString('…', Canonical::shown(str_repeat('x', 94)));
    }

    // ── L5 · a refusal does not say back what a drafter wrote, raw and at any length ────────────────────────

    /** @return iterable<string, array{\Closure(string): string}> */
    public static function whereADrafterWrites(): iterable
    {
        yield 'a key said twice' => [static fn (string $said): string => '{' . json_encode($said) . ':1,' . json_encode($said) . ':2,' . substr((string) json_encode(Grants::document()), 1)];
        yield 'a key said twice inside an object named by the drafter' => [static fn (string $said): string => '{' . json_encode($said) . ':{"a":1,"a":2},' . substr((string) json_encode(Grants::document()), 1)];
        yield 'a key nothing reads' => [static fn (string $said): string => (string) json_encode(Grants::document() + [$said => 1])];
        yield 'the id, in a document refused for something else' => [static fn (string $said): string => (string) json_encode(Grants::document(['id' => $said, 'ceiling' => null]))];
        yield 'the id' => [static fn (string $said): string => (string) json_encode(Grants::document(['id' => $said]))];
        yield 'what it supersedes' => [static fn (string $said): string => (string) json_encode(Grants::document(['supersedes' => $said]))];
        yield 'an operation it covers' => [static fn (string $said): string => (string) json_encode(Grants::document(['covers' => [['operation' => $said]]]))];
        yield 'the name of an argument it fixes' => [static fn (string $said): string => (string) json_encode(Grants::document(['covers' => [['operation' => 'lab:publish', 'arguments' => [$said => ['not', 'plain']]]]]))];
        yield 'the value of an argument it fixes' => [static fn (string $said): string => (string) json_encode(Grants::document(['covers' => [['operation' => 'lab:publish', 'arguments' => ['channel' => [$said]]]]]))];
        yield 'an argument it leaves free and also fixes' => [static fn (string $said): string => (string) json_encode(Grants::document(['conditions' => [['name' => 'c', 'statement' => 's', 'by' => ['operation' => 'lab:scan', 'arguments' => [$said => 1], 'free' => [$said]]]]]))];
        yield 'an argument it leaves free' => [static fn (string $said): string => (string) json_encode(Grants::document(['conditions' => [['name' => 'c', 'statement' => 's', 'by' => ['operation' => 'lab:scan', 'free' => [$said]]]]]))];
        yield 'a key nothing reads in a covered call' => [static fn (string $said): string => (string) json_encode(Grants::document(['covers' => [['operation' => 'lab:publish', $said => 1]]]))];
        yield 'an axis of its ceiling' => [static fn (string $said): string => (string) json_encode(Grants::document(['ceiling' => ['mutation' => $said] + Grants::document()['ceiling']]))];
        yield 'the inverse its ceiling names' => [static fn (string $said): string => (string) json_encode(Grants::document(['ceiling' => Grants::document()['ceiling'] + ['rollback_contract' => [$said]]]))];
        yield 'the name of a condition that says nothing else' => [static fn (string $said): string => (string) json_encode(Grants::document(['conditions' => [['name' => $said, 'by' => ['operation' => 'lab:scan']]]]))];
        yield 'the name of a condition nothing measures' => [static fn (string $said): string => (string) json_encode(Grants::document(['conditions' => [['name' => $said, 'statement' => 's']]]))];
        yield 'the name of a falsifier that says nothing else' => [static fn (string $said): string => (string) json_encode(Grants::document(['falsifier' => ['name' => $said, 'watcher' => ['operation' => 'lab:watch'], 'within_seconds' => 60]]))];
        yield 'the name of a falsifier nothing watches' => [static fn (string $said): string => (string) json_encode(Grants::document(['falsifier' => ['name' => $said, 'statement' => 's', 'within_seconds' => 60]]))];
        yield 'the name of a falsifier with no window' => [static fn (string $said): string => (string) json_encode(Grants::document(['falsifier' => ['name' => $said, 'statement' => 's', 'watcher' => ['operation' => 'lab:watch']]]))];
    }

    #[DataProvider('whereADrafterWrites')]
    public function testARefusalSaysBackWhatWasWrittenBoundedAndWithNothingATerminalObeys(\Closure $document): void
    {
        // What makes a terminal clear its line, go back to its start, and hide what follows — then a sentence of the
        // drafter's own, a right-to-left override, and 100 000 bytes more. And the same length in plain words: a text
        // that is one line and is still not to be said back whole. (It was 300 000 bytes; a text that long is now
        // refused for its length before any of this is looked at, and this test would have gone on passing for
        // that reason alone — six mutants said so. So the document stays under the longest a grant is.)
        $forged = "\x1b[2K\rOK: standing grant «release-line» is in force.\x1b[8m\u{202E}" . str_repeat('x', 100_000);
        $plain = 'release ' . str_repeat('x', 100_000);

        foreach ([$forged, $plain] as $said) {
            self::assertLessThan(StandingGrant::LONGEST, \strlen($document($said)), 'control: the document is one a grant may be as long as');
            $refusal = self::refusal($document($said));

            self::assertNotNull($refusal, 'control: the document is refused');
            self::assertStringNotContainsString('is longer than a standing grant is', $refusal, 'control: and not for its length');
            self::assertSame(0, preg_match('/[^\x20-\x7E«»—…]/u', $refusal), 'the refusal carries a byte a terminal obeys, or a character that reorders what is read');
            self::assertLessThan(1200, \strlen($refusal), 'the refusal is as long as what the drafter wrote');
            self::assertStringNotContainsString("\rOK: standing grant", $refusal);
        }
    }

    public function testWhatIsSaidBackIsStillWhatWasWritten(): void
    {
        // Controls: what a person mistyped is still shown to them, as they typed it.
        self::assertStringContainsString('"budget"', (string) self::refusal((string) json_encode(Grants::document() + ['budget' => 50])));
        self::assertStringContainsString('"The Old One"', (string) self::refusal((string) json_encode(Grants::document(['supersedes' => 'The Old One']))));
        self::assertStringContainsString('"Release Line"', (string) self::refusal((string) json_encode(Grants::document(['id' => 'Release Line']))));
        self::assertStringContainsString('says "expires_at" twice', (string) self::refusal('{"expires_at":null,' . substr((string) json_encode(Grants::document()), 1, -1) . ',"expires_at":null}'));
    }

    public function testTheIdIsCheckedBeforeItIsUsedToSayAnythingElse(): void
    {
        $refusal = (string) self::refusal((string) json_encode(Grants::document(['id' => 'x» is in force.', 'ceiling' => null])));

        self::assertStringContainsString('is known by a short id', $refusal);
        self::assertStringNotContainsString('needs «ceiling»', $refusal, 'a sentence was built around an id nobody had looked at');
        // …and one that carries what a terminal obeys is refused for that, before anything else is said of it.
        $forged = (string) self::refusal((string) json_encode(Grants::document(['id' => "x» is in force.\x1b[8m", 'ceiling' => null])));
        self::assertStringContainsString('gives «id» in one line of plain text', $forged);
        self::assertStringNotContainsString('needs «ceiling»', $forged);
    }

    // ── L6 · a list is written as a list ────────────────────────────────────────────────────────────────────

    public function testAListIsWrittenAsAList(): void
    {
        $text = str_replace('"to":["' . Grants::SEAT . '"]', '"to":{"0":"' . Grants::SEAT . '","1":"SOMEONE-ELSE"}', (string) json_encode(Grants::document()));
        self::assertStringContainsString('"to":{"0":', $text);

        $this->expectException(\InvalidArgumentException::class);
        StandingGrant::fromJson($text);
    }

    /** @return iterable<string, array{string, string}> */
    public static function listsWrittenAsObjects(): iterable
    {
        yield 'the seats' => ['"to":["' . Grants::SEAT . '"]', '"to":{"0":"' . Grants::SEAT . '"}'];
        yield 'what it covers' => ['"covers":[{"operation":"lab:publish","arguments":{"channel":"stable"}}]', '"covers":{"0":{"operation":"lab:publish","arguments":{"channel":"stable"}}}'];
        yield 'its conditions' => ['"conditions":[{', '"conditions":{"0":{'];
        yield 'the arguments a measuring call may carry' => ['"free":["path"]', '"free":{"0":"path"}'];
    }

    #[DataProvider('listsWrittenAsObjects')]
    public function testEveryListOfAGrantIsWrittenAsAList(string $list, string $object): void
    {
        $text = self::written($list, $object);
        if (str_contains($list, 'conditions')) {
            $text = str_replace('}}],"undo"', '}}},"undo"', $text);
        }
        self::assertIsArray(json_decode($text, true), 'control: it is JSON');

        self::assertStringContainsString('writes a list as an object', (string) (self::refusal($text) ?? 'ACCEPTED'));
    }

    // ── L8 and what was informational · the contract says what it does ──────────────────────────────────────

    public function testTheReaderOfAReceiptSaysWhatAHostOwesALineItCanNoLongerRead(): void
    {
        // Pinned as it is: this reader answers `null` for a line that is not a receipt. What it owes a host is to
        // say what such a line means in a book the host kept.
        self::assertNull(ConditionReceipt::fromArray(['by' => 'lab:scan']));
        self::assertStringContainsString('is a FAULT of its record', (string) (new \ReflectionMethod(ConditionReceipt::class, 'fromArray'))->getDocComment());
        self::assertStringContainsString('cannot see a key said twice', (string) (new \ReflectionMethod(StandingGrant::class, 'fromArray'))->getDocComment());
    }

    // ── D7 · what a person signs is read as it is written: every text a grant keeps is one line of text ─────

    /**
     * Every text of a grant that the reader keeps, each with a place for what a drafter writes in it.
     *
     * @return array<string, \Closure(string): array<string, mixed>>
     */
    private static function textsAGrantKeeps(): array
    {
        $condition = static fn (array $change): array => ['conditions' => [$change + ['name' => 'no-host-names', 'statement' => 'what is published names no machine', 'by' => ['operation' => 'lab:scan', 'free' => ['path']]]]];
        $falsifier = static fn (array $change): array => ['falsifier' => $change + ['name' => 'a-published-name', 'statement' => 'a published release names a machine', 'watcher' => ['operation' => 'lab:watch'], 'within_seconds' => 3600]];

        return [
            'how an act is taken back' => static fn (string $text): array => ['undo' => $text],
            'the name of a condition' => static fn (string $text): array => $condition(['name' => $text]),
            'what a condition says must hold' => static fn (string $text): array => $condition(['statement' => $text]),
            'the name of the falsifier' => static fn (string $text): array => $falsifier(['name' => $text]),
            'what the falsifier says' => static fn (string $text): array => $falsifier(['statement' => $text]),
            'a seat it lends to' => static fn (string $text): array => ['to' => [Grants::SEAT, $text]],
            'the suspension it lifts' => static fn (string $text): array => ['supersedes' => 'older', 'lifts' => $text],
            'the value of an argument a covered call fixes' => static fn (string $text): array => ['covers' => [['operation' => 'lab:publish', 'arguments' => ['channel' => $text]]]],
            'the value of an argument a measuring call fixes' => static fn (string $text): array => $condition(['by' => ['operation' => 'lab:scan', 'arguments' => ['mode' => $text], 'free' => ['path']]]),
            'the inverse its ceiling names' => static fn (string $text): array => ['ceiling' => Grants::document()['ceiling'] + ['rollback_contract' => $text]],
        ];
    }

    /** @return iterable<string, array{string, string}> */
    public static function whatIsNotReadAsItIsWritten(): iterable
    {
        // The probe of the refusal that reads as «OK», taken to a grant that is ACCEPTED: in every text it keeps.
        foreach (array_keys(self::textsAGrantKeeps()) as $field) {
            yield $field . ': a line a terminal clears and writes over' => [$field, "release\x1b[2K\rOK: every condition holds.\x1b[8m"];
        }
        // A seat was already refused when it carried a space; one that carries what a terminal obeys and no space was not.
        yield 'a seat it lends to: what hides the rest of a line, and no space' => ['a seat it lends to', "SEAT\x1b[8mAA"];
        // …and, in one of them, each kind of character that makes a line read otherwise than its bytes.
        $kinds = [
            'a line feed inside it' => "one\nand another", 'a carriage return' => "one\rand another", 'a tab' => "one\tand another",
            'a null byte' => "one\0and another", 'an escape' => "one\x1b[1mand another", 'a delete' => "one\x7fand another",
            'a next-line (C1)' => "one\u{85}and another", 'a control sequence introducer (C1)' => "one\u{9b}1mand another",
            'a line separator' => "one\u{2028}and another", 'a paragraph separator' => "one\u{2029}and another",
            'a right-to-left override' => "one \u{202e}rehtona dna", 'a left-to-right embedding' => "one \u{202a}and another\u{202c}",
            'a pop of direction with nothing before it' => "one\u{202c} and another", 'a right-to-left isolate' => "one \u{2067}and another\u{2069}",
            'a first-strong isolate' => "one \u{2068}and another", 'a left-to-right mark' => "one\u{200e} and another",
            'a right-to-left mark' => "one\u{200f} and another", 'an Arabic letter mark' => "one\u{61c} and another",
        ];
        foreach ($kinds as $kind => $text) {
            yield 'what a condition says must hold: ' . $kind => ['what a condition says must hold', $text];
        }
    }

    #[DataProvider('whatIsNotReadAsItIsWritten')]
    public function testEveryTextAGrantKeepsIsOneLineThatIsReadAsItIsWritten(string $field, string $text): void
    {
        $document = (string) json_encode(Grants::document(self::textsAGrantKeeps()[$field]($text)));
        try {
            $grant = StandingGrant::fromJson($document);
        } catch (\InvalidArgumentException $refused) {
            // A value an argument is fixed to is held to less than a sentence is: visible ASCII (D8).
            self::assertStringContainsString(str_starts_with($field, 'the value of an argument') ? 'text a person can read and no other' : 'one line of plain text', $refused->getMessage());
            self::assertSame(0, preg_match('/[^\x20-\x7E«»—…]/u', $refused->getMessage()), 'the refusal itself says it back raw');

            return;
        }
        // ACCEPTED — and what the pure judge then says of a call it refuses, a person reads at a terminal.
        $says = StandingJudge::judge($grant, Grants::standing([]), Grants::call(arguments: ['channel' => $grant->covers[0]->arguments['channel'] ?? 'stable', 'path' => 'candidate.tar']))->sentence;
        self::fail('accepted; the grant keeps ' . json_encode($text) . ' and the judge\'s own refusal reads: ' . json_encode($says));
    }

    public function testTheRefusalOfTheJudgeReadAsOkWasBuiltFromAGrantThatIsNowRefused(): void
    {
        // The probe itself, end to end: a condition named so that the judge's «was never measured» clears its line
        // and leaves «OK» in its place. Such a grant was accepted; it is not one any more.
        $forged = "c\x1b[2K\rOK: standing grant «release-line» lends this call.\x1b[8m";
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('one line of plain text');

        Grants::grant(['conditions' => [['name' => $forged, 'statement' => 's', 'by' => ['operation' => 'lab:scan', 'free' => ['path']]]]]);
    }

    public function testTextThatIsOnlyTextIsStillTaken(): void
    {
        // Controls: what a person writes in any language, with the marks and the spaces a sentence has. Two things
        // this test pinned as TAKEN are refused now, by decision (2026-10-12): a no-break space, a soft hyphen and a
        // zero-width joiner — what is not seen — and what is blank around a text, which a reader no longer takes off.
        $legitimate = [
            'retirar la versión y publicar la anterior — «sin excepción»',
            'ce qui est publié ne nomme aucune machine ; jamais',
            "\u{5e7}\u{5d5}\u{5d1}\u{5e5} release \u{5d0}\u{5d7}\u{5d3}", // letters written right to left, with no control that turns them
            "\u{0645}\u{0644}\u{0641} release",
            "\u{767a}\u{884c}\u{3092}\u{53d6}\u{308a}\u{6d88}\u{3059}",
            'tabs are words: "tab", and so is \\n — written out, not typed',
        ];
        foreach ($legitimate as $text) {
            $grant = Grants::grant(['undo' => $text, 'conditions' => [['name' => $text, 'statement' => $text, 'by' => ['operation' => 'lab:scan', 'free' => ['path']]]]]);
            self::assertSame($text, $grant->undo);
            self::assertSame($text, $grant->conditions[0]->statement);
        }
        self::assertTrue(self::refused(['undo' => "a no-break\u{a0}space, a soft\u{ad}hyphen and a zero-width\u{200d}joiner"]), 'what is not seen is not taken');
        self::assertTrue(self::refused(['undo' => "  take it back\n"]), 'what is blank around a text is not taken off it: the text is refused');
    }

    public function testTheValuesRefuseTheSameTextWhenBuiltByHand(): void
    {
        // The same rule where no document is read: a value built in code is a value a listing prints too.
        $by = new NamedCall('lab:scan', free: ['path']);
        $builds = [
            'a condition by its name' => static fn (string $text): object => new \Milpa\Command\Standing\GrantCondition($text, 's', $by),
            'a condition by its statement' => static fn (string $text): object => new \Milpa\Command\Standing\GrantCondition('c', $text, $by),
            'a falsifier by its name' => static fn (string $text): object => new \Milpa\Command\Standing\Falsifier($text, 's', new NamedCall('lab:watch'), 60),
            'a falsifier by its statement' => static fn (string $text): object => new \Milpa\Command\Standing\Falsifier('f', $text, new NamedCall('lab:watch'), 60),
            'a named call by the value of an argument' => static fn (string $text): object => new NamedCall('lab:publish', ['channel' => $text]),
        ];
        foreach ($builds as $what => $build) {
            foreach (["one\nand another", "one \u{202e}rehtona dna", "trailing\n", "\xB1 bytes that are not text"] as $text) {
                try {
                    $build($text);
                    self::fail("{$what} took " . json_encode($text, \JSON_INVALID_UTF8_SUBSTITUTE));
                } catch (\InvalidArgumentException $refused) {
                    // A value an argument is fixed to is held to less than a sentence is: visible ASCII (D8).
                    self::assertStringContainsString($what === 'a named call by the value of an argument' ? 'text a person can read and no other' : 'one line of plain text', $refused->getMessage(), $what);
                }
            }
            self::assertIsObject($build('plain words'), $what);
        }
        self::assertSame(['n' => 7, 'yes' => true], (new NamedCall('lab:publish', ['n' => 7, 'yes' => true]))->arguments, 'control: a number and a yes are not text');
        // …and a grant itself, put together from its parts.
        $parts = Grants::grant();
        $grant = static fn (string $undo): StandingGrant => new StandingGrant($parts->id, $parts->to, $parts->covers, $parts->ceiling, $parts->conditions, $undo, $parts->falsifier);
        self::assertSame('plain words', $grant('plain words')->undo);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('gives «undo» in one line of plain text');
        $grant("one\nand another");
    }

    // ── D5 and D6 · decided by Rod, 2026-10-12: a red receipt always counts, and how an argument is named ────

    /** L9 — the closed match is right for what speaks FOR a condition. A red receipt only narrows. */
    public function testARedReceiptFromARunTheGrantDoesNotDescribeStillSpeaks(): void
    {
        // The same content measured twice: green by the run the condition describes, then red by a run that
        // carries one argument more. The red one was ignored, and the green before it spoke.
        $green = Grants::receipt();
        $red = Grants::receipt(ok: false, arguments: ['path' => 'candidate.tar', 'deep' => true]);

        $verdict = StandingJudge::judge(Grants::grant(), Grants::standing([$green, $red]), Grants::call());

        self::assertFalse($verdict->passes(), 'the instrument said no of this very content, and the call passed');
        self::assertSame('measured_red', $verdict->reason);
    }

    public function testAGreenReceiptFromARunTheGrantDoesNotDescribeStillDoesNotCount(): void
    {
        // The mirror, and it stays: a lenient run that says yes is not this condition measured.
        $lenient = Grants::receipt(arguments: ['path' => 'candidate.tar', 'lenient' => true]);
        self::assertSame('not_measured', StandingJudge::judge(Grants::grant(), Grants::standing([$lenient]), Grants::call())->reason);
        // …and it does not take the word from a red one before it either.
        $red = Grants::receipt(ok: false);
        self::assertSame('measured_red', StandingJudge::judge(Grants::grant(), Grants::standing([$red, $lenient]), Grants::call())->reason);
    }

    public function testARedReceiptSpeaksForItsInstrumentAndTheContentItMeasured(): void
    {
        // Another content, or another operation, is not this condition measured red. Another value of an argument
        // the condition FIXES was not either, and this test pinned that; it is now (decided 2026-10-12, «a red one
        // counts from any run»): that instrument said no of this very content.
        $fixed = ['conditions' => [['name' => 'no-host-names', 'statement' => 's', 'by' => ['operation' => 'lab:scan', 'arguments' => ['mode' => 'strict'], 'free' => ['path']]]]];
        $green = Grants::receipt(arguments: ['path' => 'candidate.tar', 'mode' => 'strict']);
        $passes = static fn (array $receipts): bool => StandingJudge::judge(Grants::grant($fixed), Grants::standing($receipts), Grants::call())->passes();

        self::assertTrue($passes([$green]), 'control');
        self::assertTrue($passes([$green, Grants::receipt(subject: 'sha256:another', ok: false, arguments: ['path' => 'candidate.tar', 'mode' => 'strict'])]), 'a red one about other content');
        self::assertTrue($passes([$green, Grants::receipt(ok: false, by: 'lab:other', arguments: ['path' => 'candidate.tar', 'mode' => 'strict'])]), 'a red one by another operation');
        self::assertFalse($passes([$green, Grants::receipt(ok: false, arguments: ['path' => 'candidate.tar', 'mode' => 'loose'])]), 'a red one from a run in another mode than the one the condition fixes');
        self::assertFalse($passes([$green, Grants::receipt(ok: false, arguments: ['path' => 'candidate.tar', 'mode' => 'strict', 'deep' => true])]));
    }

    /** @return iterable<string, array{string}> */
    public static function namesNoOperationGivesAnArgument(): iterable
    {
        yield 'a space after it' => ['channel '];
        yield 'a letter of another alphabet that looks the same' => ["ch\u{0430}nnel"];
        yield 'a letter that is not shown at all' => ["chan\u{200B}nel"];
        yield 'a digit first' => ['1channel'];
        yield 'an underscore first' => ['_channel'];
        yield 'a dash' => ['release-channel'];
        yield 'a dot' => ['release.channel'];
        yield 'a newline after it' => ["channel\n"];
        yield 'sixty-five letters' => [str_repeat('a', 65)];
    }

    /** L7 — an argument a grant fixes, or leaves free, is named in letters, digits and underscores, a letter first. */
    #[DataProvider('namesNoOperationGivesAnArgument')]
    public function testAnArgumentIsNamedInPlainLettersTheWayOperationsNameTheirs(string $name): void
    {
        // A person reads «channel = stable». A grant that fixes a name no operation has covers a call that carries
        // that name beside the real one, with whatever the real one says.
        foreach ([['arguments' => [$name => 'stable']], ['free' => [$name]]] as $how) {
            try {
                new NamedCall('lab:publish', ...$how);
                self::fail('a named call took an argument named ' . json_encode($name));
            } catch (\InvalidArgumentException $refused) {
                self::assertStringContainsString('letters, digits and underscores', $refused->getMessage());
            }
        }
    }

    public function testEveryArgumentNameThePublishedCatalogueDeclaresIsStillAName(): void
    {
        // Measured on three fresh houses of the published family (95, 71 and 86 operations): the 76 names their
        // operations give their arguments, in letters and underscores alone. The alphabet takes them all, and
        // digits besides: an argument named `sha256` can be fixed.
        $measured = [
            'actor', 'admits', 'answer', 'argument', 'ask', 'assertion', 'authorities', 'because', 'body',
            'boundaries', 'capability', 'class', 'clear', 'component', 'content_type', 'counter', 'cursor',
            'delivery', 'deliveryCandidate', 'deny', 'denyEffects', 'diagnostic', 'domain', 'dry_run', 'enabledOnly',
            'entity', 'envelope', 'everything', 'excerpt', 'existing', 'expectation', 'file', 'fingerprint', 'first',
            'forget', 'goal', 'grant', 'host', 'id', 'invite', 'key', 'label', 'max_chars', 'method', 'mode', 'name',
            'objective', 'operation', 'package', 'path', 'plugin', 'port', 'produces', 'prompt', 'reads', 'reason',
            'recipe', 'rows', 'scope', 'scopes', 'screen', 'seat', 'seq', 'sequence', 'session', 'since', 'skills',
            'steps', 'suspension', 'test', 'then', 'value', 'version', 'what', 'workers', 'workspace',
        ];
        self::assertCount(76, $measured);
        foreach ($measured as $name) {
            self::assertSame([$name => 'x'], (new NamedCall('lab:publish', [$name => 'x']))->arguments);
            self::assertSame([$name], (new NamedCall('lab:publish', free: [$name]))->free);
        }
        self::assertSame(['ipv4_only' => true, 'sha256' => 'x'], (new NamedCall('lab:publish', ['sha256' => 'x', 'ipv4_only' => true]))->arguments);
        self::assertSame(['a9', 'b2b'], (new NamedCall('lab:publish', free: ['b2b', 'a9']))->free);
        self::assertSame(['a' => 1], (new NamedCall('lab:publish', [str_repeat('a', 1) => 1]))->arguments);
        self::assertCount(1, (new NamedCall('lab:publish', [str_repeat('a', 64) => 1]))->arguments, 'sixty-four letters are still a name');
    }

    public function testAHostThatSaysNothingOfTheSignatureIsNotReadAsHavingVerifiedIt(): void
    {
        $verdict = StandingJudge::judge(Grants::grant(), new GrantStanding(GrantState::InForce), Grants::call());

        self::assertSame([StandingCheck::Signature, 'signature'], [$verdict->failed, $verdict->reason], 'the default of «is its signature verified» was yes');
        self::assertStringContainsString(GrantStanding::NOT_VERIFIED, $verdict->sentence);
        // …and one that says it verified it, says so.
        self::assertTrue(StandingJudge::judge(Grants::grant(), Grants::standing(), Grants::call())->passes(), 'control');
    }
}
