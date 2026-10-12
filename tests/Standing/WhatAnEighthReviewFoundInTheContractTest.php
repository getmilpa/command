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
use Milpa\Command\Standing\StandingGrant;
use Milpa\Command\Standing\StandingJudge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * WHAT AN EIGHTH REVIEW FOUND IN THE CONTRACT (greenhouse decisions/0615) — its three findings of low severity that
 * are fixed. Its finding of medium severity is a proposal and has a file of its own
 * ({@see AProposalOfASeventhReviewTest}), and so has what it found in the proposal about escapes
 * ({@see TwoProposalsOfASixthReviewTest}).
 *
 * The reviewer's own tests are here as they were written, each beside what was added to it.
 *
 * @subject-in milpa/command
 */
final class WhatAnEighthReviewFoundInTheContractTest extends TestCase
{
    /** @param array<string, mixed> $facts */
    private static function standing(array $facts = []): GrantStanding
    {
        return new GrantStanding(...$facts + ['state' => GrantState::InForce, 'signatureFault' => null, 'watcherSignalAt' => Grants::at('2026-10-11T11:50:00Z'), 'receipts' => [Grants::receipt()], 'instruments' => ['lab.scan' => Grants::INSTRUMENT], 'recordFault' => null]);
    }

    private static function wide(string $id = 'wide', ?string $supersedes = null): StandingGrant
    {
        return Grants::grant(['id' => $id, 'covers' => [['operation' => 'lab:publish']]] + ($supersedes === null ? [] : ['supersedes' => $supersedes]));
    }

    // ── L1 · a refusal names a way out that takes the stop away ──────────────────────────────────────────────

    /** The reviewer's test, as written. */
    public function testTheWayOutThatAStopBesideNamesIsOneThatTakesTheStopAway(): void
    {
        $call = Grants::call(arguments: ['path' => 'candidate.tar']);
        $narrow = Grants::grant(['id' => 'narrow']);
        $unread = ['recordFault' => 'line 9 cannot be read today'];
        $before = StandingJudge::among([['grant' => $narrow, 'standing' => self::standing($unread)], ['grant' => Grants::grant(['id' => 'wide', 'covers' => [['operation' => 'lab:publish']]]), 'standing' => self::standing()]], $call, null);
        self::assertSame('stopped_beside', $before['verdict']->reason, 'control');
        if (!str_contains($before['verdict']->sentence, 'gives a grant that supersedes «narrow» by name')) {
            self::assertStringContainsString('narrow', $before['verdict']->sentence);

            return;                                     // the refusal does not name that way out: nothing to hold it to
        }

        // A person does it: a grant that supersedes «narrow» by name. The house now reports «narrow» as superseded.
        $successor = Grants::grant(['id' => 'wide-2', 'supersedes' => 'narrow', 'covers' => [['operation' => 'lab:publish']]]);
        $after = StandingJudge::among([['grant' => $narrow, 'standing' => self::standing(['state' => GrantState::Superseded] + $unread)], ['grant' => $successor, 'standing' => self::standing()]], $call, null);

        self::assertTrue(
            ($after['verdict']->passes() ?? false) || !str_contains($after['verdict']->sentence, 'supersedes «narrow» by name'),
            'the person gave the grant the refusal asked for, the call is refused again, and the refusal asks for the same grant: ' . $after['verdict']->sentence,
        );
    }

    /**
     * Each kind of stop, and the way out its refusal names — followed, to see that it leads out.
     *
     * @return iterable<string, array{array<string, mixed>, string, string, bool}> the stop; how it is said; the way out; whether superseding by name takes it away
     */
    public static function kindsOfStop(): iterable
    {
        yield 'suspended, and its record reads' => [['state' => GrantState::Suspended, 'because' => 'its falsifier fired'], 'is suspended', 'until a person lifts that stop or gives a grant that supersedes «narrow» by name', true];
        yield 'its record does not read whole, said by the host' => [['recordFault' => 'line 9 cannot be read today'], 'has a record that does not read whole', 'until what the house keeps of «narrow» reads again', false];
        yield 'a line that was not read in its list of receipts' => [['receipts' => [null]], 'has a record that does not read whole', 'until what the house keeps of «narrow» reads again', false];
        yield 'suspended, and its record does not read' => [['state' => GrantState::Suspended, 'recordFault' => 'line 9 cannot be read today'], 'is suspended and has a record that does not read whole', 'until what the house keeps of «narrow» reads again', false];
    }

    /** @param array<string, mixed> $stop */
    #[DataProvider('kindsOfStop')]
    public function testAStopBesideTheCallSaysTheWayOutOfItsOwnKind(array $stop, string $how, string $wayOut, bool $supersedingLeadsOut): void
    {
        $call = Grants::call(arguments: ['path' => 'candidate.tar']);
        $narrow = Grants::grant(['id' => 'narrow']);
        /** @phpstan-ignore-next-line a line that was not read is what a host hands in for one */
        $stopped = self::standing($stop);

        $sentence = StandingJudge::among([['grant' => $narrow, 'standing' => $stopped], ['grant' => self::wide(), 'standing' => self::standing()]], $call, null)['verdict']->sentence ?? '';

        self::assertStringContainsString("Standing grant «narrow» {$how}, and it may be the one that names this call", $sentence);
        self::assertStringContainsString("nothing is lent to this call {$wayOut}.", $sentence);
        self::assertSame($supersedingLeadsOut, str_contains($sentence, 'supersedes «narrow» by name'));

        // The other way out is followed: a grant that supersedes it by name. It leads out only where the sentence said so.
        /** @phpstan-ignore-next-line */
        $superseded = self::standing(['state' => GrantState::Superseded] + $stop);
        $after = StandingJudge::among([['grant' => $narrow, 'standing' => $superseded], ['grant' => self::wide('wide-2', 'narrow'), 'standing' => self::standing()]], $call, null);
        self::assertSame($supersedingLeadsOut, $after['verdict']->passes() ?? false);
    }

    /**
     * The same defect one line above, found beside the reviewer's: a grant that NAMES the call, suspended, whose
     * record does not read either. The refusal said «gives a grant that supersedes it by name»; a person gave it,
     * and the unread record refused the call again.
     */
    public function testAStopThatNamesTheCallDoesNotNameAWayOutThatDoesNotLeadOutEither(): void
    {
        $call = Grants::call();
        $stopped = Grants::grant(['id' => 'stopped']);
        $both = ['state' => GrantState::Suspended, 'because' => 'its falsifier fired', 'recordFault' => 'line 9 cannot be read today'];

        $before = StandingJudge::among([['grant' => $stopped, 'standing' => self::standing($both)], ['grant' => Grants::grant(['id' => 'would-lend']), 'standing' => self::standing()]], $call, null);
        self::assertFalse($before['verdict']->passes() ?? true, 'control');
        self::assertStringNotContainsString('supersedes «stopped» by name', $before['verdict']->sentence, 'the refusal names a grant that would not take this stop away');
        self::assertStringContainsString('lends nothing while what the house keeps of «stopped» does not read whole', $before['verdict']->sentence);

        // control: with a record that reads, the suspension says its own way out, and following it leads out
        $reads = StandingJudge::among([['grant' => $stopped, 'standing' => self::standing(['state' => GrantState::Suspended, 'because' => 'its falsifier fired'])], ['grant' => Grants::grant(['id' => 'would-lend']), 'standing' => self::standing()]], $call, null);
        self::assertStringContainsString('gives a grant that supersedes «stopped» by name', $reads['verdict']->sentence ?? '');
        $after = StandingJudge::among([['grant' => $stopped, 'standing' => self::standing(['state' => GrantState::Superseded])], ['grant' => Grants::grant(['id' => 'would-lend', 'supersedes' => 'stopped']), 'standing' => self::standing()]], $call, null);
        self::assertTrue($after['verdict']->passes() ?? false);
        // …and a suspension that reads still speaks before a record that does not, of another grant.
        $two = StandingJudge::among([['grant' => Grants::grant(['id' => 'unread']), 'standing' => self::standing(['recordFault' => 'line 9 cannot be read today'])], ['grant' => $stopped, 'standing' => self::standing(['state' => GrantState::Suspended, 'because' => 'its falsifier fired'])]], $call, null);
        self::assertSame(['stopped', 'suspended'], [$two['grant']->id ?? null, $two['verdict']->reason ?? null]);
    }

    /** «Expired, revoked and superseded grants whose record reads stop nobody» — and one whose record does not read stops. */
    public function testAGrantThatIsGoneStopsNobodyOnlyWhileItsRecordReads(): void
    {
        $call = Grants::call(arguments: ['path' => 'candidate.tar']);
        $wide = ['grant' => self::wide(), 'standing' => self::standing()];
        foreach ([GrantState::Revoked, GrantState::Superseded] as $gone) {
            $reads = StandingJudge::among([['grant' => Grants::grant(['id' => 'narrow']), 'standing' => self::standing(['state' => $gone])], $wide], $call, null);
            self::assertTrue($reads['verdict']->passes() ?? false, $gone->value . ', its record reads: it stops nobody');

            $unread = StandingJudge::among([['grant' => Grants::grant(['id' => 'narrow']), 'standing' => self::standing(['state' => $gone, 'recordFault' => 'line 9 cannot be read today'])], $wide], $call, null);
            self::assertSame('stopped_beside', $unread['verdict']->reason ?? null, $gone->value . ', its record does not read: the line that cannot be read may be the one that stopped it');
        }
        $readme = (string) preg_replace('/\s+/', ' ', (string) file_get_contents(\dirname(__DIR__, 2) . '/README.md'));
        self::assertStringContainsString('Expired, revoked and superseded grants whose record reads stop nobody', $readme);
    }

    // ── L2 · a name is given as text, whatever the caller's file declares ────────────────────────────────────

    /**
     * `strict_types` belongs to the caller's file. This file declares it, so each thing is built here THROUGH
     * REFLECTION: that call is made in PHP's default mode whatever a file declares — the mode of a caller with no
     * declaration, in which an `OperationId`, being `Stringable`, turns itself into text for a `string` parameter.
     */
    public function testAnIdentityBuiltElsewhereIsNotTakenFromACallerInPhpsDefaultModeEither(): void
    {
        $identity = new OperationId(' lab:scan' . "\n");
        $built = [];
        foreach ([
            'a named call' => [NamedCall::class, [$identity]],
            'a receipt' => [ConditionReceipt::class, [$identity, [], Grants::CANDIDATE, true, Grants::INSTRUMENT, null, Grants::at('2026-10-11T11:50:00Z')]],
            'a call' => [StandingCall::class, [$identity, [], Grants::SEAT, Grants::CANDIDATE, Grants::effect(), Grants::at('2026-10-11T12:00:00Z')]],
        ] as $what => [$class, $arguments]) {
            try {
                (new \ReflectionClass($class))->newInstanceArgs($arguments);
                $built[] = $what;
            } catch (\TypeError $refused) {
                self::assertStringContainsString('given as text, never as an identity built elsewhere; got ' . OperationId::class, $refused->getMessage());
            }
        }
        self::assertSame([], $built, 'built from an OperationId made of a name that is not as it reads, by a caller in PHP\'s default mode');

        // control: the same three, through the same door, from the name as text
        self::assertSame('lab.scan', (new \ReflectionClass(NamedCall::class))->newInstanceArgs(['lab:scan'])->operation->canonical);
        self::assertSame('lab.scan', (new \ReflectionClass(ConditionReceipt::class))->newInstanceArgs(['lab:scan', [], Grants::CANDIDATE, true, Grants::INSTRUMENT, null, Grants::at('2026-10-11T11:50:00Z')])->by->canonical);
        self::assertSame('lab.publish', (new \ReflectionClass(StandingCall::class))->newInstanceArgs(['lab:publish', [], Grants::SEAT, Grants::CANDIDATE, Grants::effect(), Grants::at('2026-10-11T12:00:00Z')])->operation->canonical);
    }

    /** @return iterable<string, array{mixed}> */
    public static function whatIsNotAText(): iterable
    {
        yield 'an identity built elsewhere' => [new OperationId('lab:scan')];
        yield 'a number' => [7];
        yield 'nothing' => [null];
        yield 'a list holding the name' => [['lab:scan']];
        yield 'true' => [true];
    }

    #[DataProvider('whatIsNotAText')]
    public function testTheNameOfAnOperationIsATextOrATypeError(mixed $notAText): void
    {
        self::assertSame('lab.scan', NamedCall::named('lab:scan')?->canonical, 'control');

        foreach ([
            static fn (): mixed => NamedCall::named($notAText),
            static fn (): mixed => new NamedCall($notAText),
            static fn (): mixed => new ConditionReceipt($notAText, [], Grants::CANDIDATE, true, Grants::INSTRUMENT, null, Grants::at('2026-10-11T11:50:00Z')),
            static fn (): mixed => new StandingCall($notAText, [], Grants::SEAT, Grants::CANDIDATE, Grants::effect(), Grants::at('2026-10-11T12:00:00Z')),
        ] as $ask) {
            try {
                $ask();
                self::fail('taken: ' . get_debug_type($notAText));
            } catch (\TypeError $refused) {
                self::assertStringContainsString('the name of an operation is given as text, never as an identity built elsewhere; got ' . get_debug_type($notAText), $refused->getMessage());
            }
        }
    }

    // ── L3 · a text the reader takes is one it reads back from its own spelling ──────────────────────────────

    /** The reviewer's test, as written. */
    public function testAGrantThatIsReadIsReadBackFromItsOwnSpelling(): void
    {
        $document = Grants::document(['undo' => 'X']);
        $room = StandingGrant::LONGEST - \strlen((string) json_encode($document, \JSON_UNESCAPED_SLASHES)) + 1;
        $text = (string) json_encode(Grants::document(['undo' => str_repeat('a', $room)]), \JSON_UNESCAPED_SLASHES);
        self::assertSame(StandingGrant::LONGEST, \strlen($text), 'control: a text of exactly the longest length');
        try {
            $grant = StandingGrant::fromJson($text);
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);

            return;                                     // not taken at all: then there is nothing to read back
        }

        try {
            $back = StandingGrant::fromJson($grant->canonical());
        } catch (\InvalidArgumentException $refused) {
            self::fail('the reader took a text of ' . \strlen($text) . ' bytes and refuses its own canonical spelling of it (' . \strlen($grant->canonical()) . ' bytes): ' . substr($refused->getMessage(), 0, 90));
        }
        self::assertSame($grant->digest(), $back->digest());
    }

    public function testTheLimitIsAskedOfTheSpellingAHouseKeeps(): void
    {
        // A text that leaves out `supersedes`, `lifts` and `expires_at`; its canonical spelling says all three.
        $written = static fn (int $letters): string => (string) json_encode(Grants::document(['undo' => str_repeat('a', $letters)]), \JSON_UNESCAPED_SLASHES);
        $more = \strlen(Grants::grant()->canonical()) - \strlen($written(\strlen(Grants::grant()->undo)));
        self::assertGreaterThan(0, $more, 'control: the canonical spelling is the longer one');
        $fits = StandingGrant::LONGEST - \strlen($written(0)) - $more;

        $grant = StandingGrant::fromJson($written($fits));
        self::assertSame(StandingGrant::LONGEST, \strlen($grant->canonical()), 'control: a spelling of exactly the longest length');
        self::assertSame($grant->digest(), StandingGrant::fromJson($grant->canonical())->digest());

        self::assertLessThan(StandingGrant::LONGEST, \strlen($written($fits + 1)), 'the text itself is still under the limit');
        try {
            StandingGrant::fromJson($written($fits + 1));
            self::fail('read: a text whose own canonical spelling is one byte longer than a grant is');
        } catch (\InvalidArgumentException $refused) {
            self::assertStringContainsString('the canonical spelling of this standing grant is ' . (StandingGrant::LONGEST + 1) . ' bytes, longer than a standing grant is', $refused->getMessage());
        }

        // A grant put together in code has no limit of its own, and the README says so.
        self::assertGreaterThan(StandingGrant::LONGEST, \strlen(Grants::grant(['undo' => str_repeat('a', 300_000)])->canonical()));
        $readme = (string) preg_replace('/\s+/', ' ', (string) file_get_contents(\dirname(__DIR__, 2) . '/README.md'));
        self::assertStringContainsString('a grant put together in code has no limit of its own', $readme);
    }
}
