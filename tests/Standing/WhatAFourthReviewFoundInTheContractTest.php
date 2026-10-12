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
        self::assertSame('lab.scan', (new NamedCall(" lab:scan\n"))->operation->canonical, 'control: what is blank around a name was never part of it');
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
        // drafter's own, a right-to-left override, and 300 000 bytes more.
        $forged = "\x1b[2K\rOK: standing grant «release-line» is in force.\x1b[8m\u{202E}" . str_repeat('x', 300_000);

        $refusal = self::refusal($document($forged));

        self::assertNotNull($refusal, 'control: the document is refused');
        self::assertSame(0, preg_match('/[^\x20-\x7E«»—…]/u', $refusal), 'the refusal carries a byte a terminal obeys, or a character that reorders what is read');
        self::assertLessThan(1200, \strlen($refusal), 'the refusal is as long as what the drafter wrote');
        self::assertStringNotContainsString("\rOK: standing grant", $refusal);
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
        $refusal = (string) self::refusal((string) json_encode(Grants::document(['id' => "x» is in force.\x1b[8m", 'ceiling' => null])));

        self::assertStringContainsString('is known by a short id', $refusal);
        self::assertStringNotContainsString('needs «ceiling»', $refusal, 'a sentence was built around an id nobody had looked at');
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

    // ── two proposals, built as the narrowest option and waiting for a person's decision ─────────────────────

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

    public function testARedReceiptSpeaksOnlyForTheConditionItsRunFitsAndTheContentItMeasured(): void
    {
        // Open is not «anything»: another content, another operation, or another value of an argument the condition
        // FIXES is not this condition measured red.
        $fixed = ['conditions' => [['name' => 'no-host-names', 'statement' => 's', 'by' => ['operation' => 'lab:scan', 'arguments' => ['mode' => 'strict'], 'free' => ['path']]]]];
        $green = Grants::receipt(arguments: ['path' => 'candidate.tar', 'mode' => 'strict']);
        $passes = static fn (array $receipts): bool => StandingJudge::judge(Grants::grant($fixed), Grants::standing($receipts), Grants::call())->passes();

        self::assertTrue($passes([$green]), 'control');
        self::assertTrue($passes([$green, Grants::receipt(subject: 'sha256:another', ok: false, arguments: ['path' => 'candidate.tar', 'mode' => 'strict'])]), 'a red one about other content');
        self::assertTrue($passes([$green, Grants::receipt(ok: false, by: 'lab:other', arguments: ['path' => 'candidate.tar', 'mode' => 'strict'])]), 'a red one by another operation');
        self::assertTrue($passes([$green, Grants::receipt(ok: false, arguments: ['path' => 'candidate.tar', 'mode' => 'loose'])]), 'a red one from a run in another mode than the one the condition fixes');
        self::assertFalse($passes([$green, Grants::receipt(ok: false, arguments: ['path' => 'candidate.tar', 'mode' => 'strict', 'deep' => true])]));
    }

    /** @return iterable<string, array{string}> */
    public static function namesNoOperationGivesAnArgument(): iterable
    {
        yield 'a space after it' => ['channel '];
        yield 'a letter of another alphabet that looks the same' => ["ch\u{0430}nnel"];
        yield 'a letter that is not shown at all' => ["chan\u{200B}nel"];
        yield 'a digit first' => ['1channel'];
        yield 'a digit anywhere: the narrowest alphabet has none, because no published operation needs one' => ['sha256'];
        yield 'a dash' => ['release-channel'];
        yield 'a dot' => ['release.channel'];
        yield 'a newline after it' => ["channel\n"];
        yield 'sixty-five letters' => [str_repeat('a', 65)];
    }

    /** L7 — an argument a grant fixes, or leaves free, is named the way operations name theirs. */
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
                self::assertStringContainsString('letters and underscores', $refused->getMessage());
            }
        }
    }

    public function testEveryArgumentNameThePublishedCatalogueDeclaresIsStillAName(): void
    {
        // Measured on three fresh houses of the published family (95, 71 and 86 operations): the 76 names their
        // operations give their arguments. The alphabet is the narrowest that refuses none of them.
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
        self::assertSame(['a' => 1], (new NamedCall('lab:publish', [str_repeat('a', 1) => 1]))->arguments);
        self::assertCount(1, (new NamedCall('lab:publish', [str_repeat('a', 64) => 1]))->arguments, 'sixty-four letters are still a name');
    }

    public function testAHostThatSaysNothingOfTheSignatureIsNotReadAsHavingVerifiedIt(): void
    {
        $verdict = StandingJudge::judge(Grants::grant(), new GrantStanding(), Grants::call());

        self::assertSame(StandingCheck::Signature, $verdict->failed, 'the default of «is its signature verified» was yes');
        // …and one that says it verified it, says so.
        self::assertTrue(StandingJudge::judge(Grants::grant(), Grants::standing(), Grants::call())->passes(), 'control');
    }
}
