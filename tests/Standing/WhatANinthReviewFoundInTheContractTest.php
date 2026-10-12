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
use Milpa\Command\Effect\EffectProfile;
use Milpa\Command\Effect\Externality;
use Milpa\Command\Effect\Mutation;
use Milpa\Command\Effect\Reversibility;
use Milpa\Command\Effect\Subject;
use Milpa\Command\Standing\ConditionReceipt;
use Milpa\Command\Standing\GrantStanding;
use Milpa\Command\Standing\GrantState;
use Milpa\Command\Standing\StandingCheck;
use Milpa\Command\Standing\StandingGrant;
use Milpa\Command\Standing\StandingJudge;
use Milpa\Command\Standing\StandingVerdict;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * WHAT A NINTH REVIEW FOUND IN THE CONTRACT (greenhouse decisions/0615). It passed: no finding of high or medium
 * severity, three of low. Each is here: what a run answered is asked for its type by the contract, as a name is; the
 * words say which of the two doors answers a stop beside a call; and two sentences of the README say what is so.
 *
 * The reviewer's tests lived in a file that declares no `strict_types`, on purpose. This suite's files all declare
 * it, so a caller in PHP's default mode is reproduced THROUGH REFLECTION, whose call is made in that mode whatever a
 * file declares.
 *
 * @subject-in milpa/command
 */
final class WhatANinthReviewFoundInTheContractTest extends TestCase
{
    /** @param array<string, mixed> $facts */
    private static function standing(array $facts = []): GrantStanding
    {
        return new GrantStanding(...$facts + ['state' => GrantState::InForce, 'signatureFault' => null, 'watcherSignalAt' => Grants::at('2026-10-11T11:50:00Z'), 'receipts' => [Grants::receipt()], 'instruments' => ['lab.scan' => Grants::INSTRUMENT], 'recordFault' => null]);
    }

    /** A receipt as a caller in PHP's default mode builds it: the answer is whatever that caller hands in. */
    private static function receiptFromACallerInDefaultMode(mixed $answered): ConditionReceipt
    {
        return (new \ReflectionClass(ConditionReceipt::class))->newInstanceArgs(['lab:scan', ['path' => 'candidate.tar'], Grants::CANDIDATE, $answered, Grants::INSTRUMENT, 'w', Grants::at('2026-10-11T11:50:00Z')]);
    }

    // ── L1 · what a run answered is asked for its type here, and is never read open ──────────────────────────

    /** @return iterable<string, array{mixed}> what a run answered, as a host in PHP's default mode may hand it in */
    public static function answersThatAreNotTrue(): iterable
    {
        // The reviewer's four, as they were written.
        yield 'the word false' => ['false'];
        yield 'the word no' => ['no'];
        yield 'the word failed' => ['failed'];
        yield 'the number 2' => [2];
        // …and what PHP would have read as an answer either way: none of them IS one.
        yield 'the word true' => ['true'];
        yield 'the number 1' => [1];
        yield 'the number 0, which PHP reads as no' => [0];
        yield 'the text zero' => ['0'];
        yield 'the empty text' => [''];
        yield 'a number with a fraction' => [1.0];
        yield 'nothing' => [null];
        yield 'a list holding true' => [[true]];
    }

    #[DataProvider('answersThatAreNotTrue')]
    public function testWhatARunAnsweredIsNotReadAsYesFromACallerWithoutStrictTypes(mixed $answered): void
    {
        try {
            $receipt = self::receiptFromACallerInDefaultMode($answered);
        } catch (\TypeError $refused) {
            self::assertStringContainsString('what a run answered is given as true or false, never as a word or a number that stands for one; got ' . get_debug_type($answered), $refused->getMessage());

            return;
        }
        $verdict = StandingJudge::judge(Grants::grant(), self::standing(['receipts' => [$receipt]]), Grants::call());
        self::assertFalse($verdict->passes(), \sprintf('a run that answered %s is a GREEN receipt, and the call passes on it', var_export($answered, true)));
        self::fail(\sprintf('a receipt was built from %s: the answer of a run is true or false', var_export($answered, true)));
    }

    public function testControlABooleanAnswerIsTakenAndARedOneRefuses(): void
    {
        $red = self::receiptFromACallerInDefaultMode(false);
        self::assertFalse($red->ok);
        self::assertSame('measured_red', StandingJudge::judge(Grants::grant(), self::standing(['receipts' => [$red]]), Grants::call())->reason);
        $green = self::receiptFromACallerInDefaultMode(true);
        self::assertTrue($green->ok);
        self::assertTrue(StandingJudge::judge(Grants::grant(), self::standing(['receipts' => [$green]]), Grants::call())->passes());
        // …and a line of a ledger was already held to it: what is not true or false there is not a receipt.
        self::assertNull(ConditionReceipt::fromArray(['by' => 'lab:scan', 'arguments' => [], 'subject' => Grants::CANDIDATE, 'ok' => 'false', 'instrument' => Grants::INSTRUMENT, 'who' => null, 'at' => '2026-10-11T11:50:00Z']));
    }

    /** The sibling: the inverse a ceiling names is the name of one operation, as text, for every caller too. */
    public function testTheInverseACeilingNamesIsNotAnIdentityBuiltElsewhereEither(): void
    {
        // control: the text with its ends is refused
        try {
            Grants::grant(['ceiling' => Grants::document()['ceiling'] + ['rollback_contract' => ' lab:unpublish' . "\n"]]);
            self::fail('the control was taken');
        } catch (\InvalidArgumentException) {
        }
        $ceiling = static fn (mixed $inverse): EffectProfile => (new \ReflectionClass(EffectProfile::class))->newInstanceArgs([
            'mutation' => Mutation::Persistent,
            'externality' => Externality::Public,
            'reversibility' => Reversibility::Compensatable,
            'authority' => Authority::WriteAsUser,
            'subject' => Subject::Executable,
            'rollbackContract' => $inverse,
        ]);
        // control: the name as text is a ceiling, and a grant is built on it
        $g = Grants::grant();
        self::assertSame('lab:unpublish', (new StandingGrant('m-01', $g->to, $g->covers, $ceiling('lab:unpublish'), $g->conditions, $g->undo, $g->falsifier))->ceiling->rollbackContract);
        self::assertNull($ceiling(null)->rollbackContract);

        foreach (['an identity built elsewhere' => new OperationId(' lab:unpublish' . "\n"), 'a number' => 7, 'true' => true, 'a list' => ['lab:unpublish']] as $what => $notAText) {
            try {
                $ceiling($notAText);
                self::fail('a ceiling was built from ' . $what);
            } catch (\TypeError $refused) {
                self::assertStringContainsString('the rollback contract of an effect profile is given as text; got ' . get_debug_type($notAText), $refused->getMessage(), $what);
            }
        }
    }

    // ── L2 · which of the two doors answers a stop beside the call, said where nobody can miss it ────────────

    public function testAStopBesideTheCallIsAnsweredByTheDoorForSeveralGrantsAlsoWhenThereIsOne(): void
    {
        $narrow = Grants::grant(['id' => 'narrow']);
        $stopped = self::standing(['state' => GrantState::Suspended, 'because' => 'its falsifier fired']);
        $call = Grants::call(arguments: ['path' => 'candidate.tar']);          // says no channel

        $among = StandingJudge::among([['grant' => $narrow, 'standing' => $stopped]], $call, null);
        self::assertNotNull($among);
        self::assertSame(['stopped_beside', true, false], [$among['verdict']->reason, $among['verdict']->named(), $among['verdict']->passes()]);
        // The door for ONE grant does not ask it, and that is not changed: it says whether THIS grant names the call.
        $alone = StandingJudge::judge($narrow, $stopped, $call);
        self::assertSame(['not_named', false], [$alone->reason, $alone->named()]);
        // The question itself is public, for whoever asks the door for one grant.
        self::assertTrue(StandingJudge::mayBeNaming($narrow, $call));

        // So the words say it, where a host reads them: the two docblocks, the verdict's, and the README.
        $said = static fn (\Reflector $of): string => (string) preg_replace('/\s*\*\s*/', ' ', (string) (method_exists($of, 'getDocComment') ? $of->getDocComment() : ''));
        $judge = $said(new \ReflectionMethod(StandingJudge::class, 'judge'));
        self::assertStringContainsString('IT DOES NOT ANSWER A STOP BESIDE THE CALL', $judge);
        self::assertStringContainsString('A HOST ASKS among() ALSO WHEN IT HOLDS ONE GRANT', $judge);
        $among = $said(new \ReflectionMethod(StandingJudge::class, 'among'));
        self::assertStringContainsString('IT ADDS ONE CRITERION', $among);
        self::assertStringContainsString('every grant the host holds that lends to the seat and covers the operation', $among);
        self::assertStringNotContainsString('adds no criterion', $among);
        self::assertStringContainsString('A STOP BESIDE THE CALL', $said(new \ReflectionMethod(StandingVerdict::class, 'named')));
        self::assertStringContainsString('a fourth', $said(new \ReflectionClass(StandingVerdict::class)));
        $readme = (string) preg_replace('/\s+/', ' ', (string) file_get_contents(\dirname(__DIR__, 2) . '/README.md'));
        self::assertStringContainsString('A host asks `among()`, also when it holds one grant', $readme);
        self::assertStringContainsString('`judge()` does not answer a stop beside the call', $readme);
    }

    /**
     * A stop beside the call is filed under the check its kind belongs to: a record that does not read under the
     * signature, as every fault of a record is; a suspension that reads under «in force».
     *
     * @return iterable<string, array{array<string, mixed>, StandingCheck}>
     */
    public static function kindsOfStopBeside(): iterable
    {
        yield 'suspended, and its record reads' => [['state' => GrantState::Suspended, 'because' => 'its falsifier fired'], StandingCheck::InForce];
        yield 'its record does not read whole, said by the host' => [['recordFault' => 'line 9 cannot be read today'], StandingCheck::Signature];
        yield 'a line that was not read in its list of receipts' => [['receipts' => [null]], StandingCheck::Signature];
        yield 'suspended, and its record does not read' => [['state' => GrantState::Suspended, 'recordFault' => 'line 9 cannot be read today'], StandingCheck::Signature];
        yield 'revoked, and its record does not read' => [['state' => GrantState::Revoked, 'recordFault' => 'line 9 cannot be read today'], StandingCheck::Signature];
    }

    /** @param array<string, mixed> $stop */
    #[DataProvider('kindsOfStopBeside')]
    public function testAStopBesideIsFiledUnderTheCheckOfItsKind(array $stop, StandingCheck $filed): void
    {
        $call = Grants::call(arguments: ['path' => 'candidate.tar']);
        /** @phpstan-ignore-next-line a line that was not read is what a host hands in for one */
        $stopped = self::standing($stop);
        $wide = ['grant' => Grants::grant(['id' => 'wide', 'covers' => [['operation' => 'lab:publish']]]), 'standing' => self::standing()];

        foreach ([[['grant' => Grants::grant(['id' => 'narrow']), 'standing' => $stopped]], [['grant' => Grants::grant(['id' => 'narrow']), 'standing' => $stopped], $wide], [$wide, ['grant' => Grants::grant(['id' => 'narrow']), 'standing' => $stopped]]] as $held) {
            $verdict = StandingJudge::among($held, $call, null)['verdict'] ?? null;
            self::assertSame(['stopped_beside', $filed], [$verdict?->reason, $verdict?->failed]);
        }
        // control: the same stop, NAMING the call, is filed by the judge under the same check.
        $naming = StandingJudge::judge(Grants::grant(['id' => 'narrow']), $stopped, Grants::call());
        self::assertSame($filed, $naming->failed);
    }

    // ── L3 · two sentences of the README say what is so ──────────────────────────────────────────────────────

    public function testTheCostOfAStopIsSaidAsItIs(): void
    {
        $two = Grants::grant(['id' => 'narrow', 'covers' => [['operation' => 'lab:publish', 'arguments' => ['channel' => 'stable', 'build' => 7]]]]);
        $wide = Grants::grant(['id' => 'wide', 'covers' => [['operation' => 'lab:publish']]]);
        $held = [['grant' => $two, 'standing' => self::standing(['state' => GrantState::Suspended])], ['grant' => $wide, 'standing' => self::standing()]];
        $answers = static function (array $arguments) use ($held): string {
            $answer = StandingJudge::among($held, Grants::call(arguments: $arguments + ['path' => 'candidate.tar']), null);

            return ($answer['verdict']->passes() ?? false) ? 'PASSES under ' . ($answer['grant']->id ?? '?') : ($answer['verdict']->reason ?? 'nobody');
        };

        // ONE thing the entry fixes, said as another value, is enough — the README used to say «each».
        self::assertSame('PASSES under wide', $answers(['channel' => 'beta']), 'says only the channel, another value; the build is not said');
        self::assertSame('PASSES under wide', $answers(['build' => 8]), 'says only the build, another value; the channel is not said');
        self::assertSame('stopped_beside', $answers([]), 'says neither');
        self::assertSame('stopped_beside', $answers(['channel' => 'stable']), 'says the channel as it is fixed, and not the build');
        // …for EACH entry of the grant over the operation: one entry that may still be naming the call stops it.
        $entries = Grants::grant(['id' => 'narrow', 'covers' => [['operation' => 'lab:publish', 'arguments' => ['channel' => 'stable']], ['operation' => 'lab:publish', 'arguments' => ['build' => 7]]]]);
        $either = static fn (array $arguments): bool => StandingJudge::mayBeNaming($entries, Grants::call(arguments: $arguments + ['path' => 'candidate.tar']));
        self::assertTrue($either(['channel' => 'beta']), 'another channel for the first entry; the second fixes the build, which is not said');
        self::assertFalse($either(['channel' => 'beta', 'build' => 8]), 'another value for one thing each entry fixes');

        $readme = (string) preg_replace('/\s+/', ' ', (string) file_get_contents(\dirname(__DIR__, 2) . '/README.md'));
        self::assertStringContainsString('is refused unless it says, in the grant\'s own kind and spelling, another value for one thing each of that grant\'s entries over the operation fixes', $readme);
        self::assertStringNotContainsString('unless it says each fixed argument', $readme);
    }

    public function testASuspensionOutlivesTheGrantsOwnDateAndTheWordsSaySo(): void
    {
        $call = Grants::call(arguments: ['path' => 'candidate.tar']);
        $dated = static fn (): StandingGrant => Grants::grant(['id' => 'narrow', 'expires_at' => '2026-10-01T00:00:00Z']);
        $wide = ['grant' => Grants::grant(['id' => 'wide', 'covers' => [['operation' => 'lab:publish']]]), 'standing' => self::standing()];
        $lends = static fn (GrantStanding $of): bool => StandingJudge::among([['grant' => $dated(), 'standing' => $of], $wide], $call, null)['verdict']->passes() ?? false;

        // Past its own date, in force, its record reads: it stops nobody.
        self::assertTrue($lends(self::standing()));
        // Past its own date AND STILL SUSPENDED: it stops, beside the call and naming it — nothing was changed here.
        self::assertFalse($lends(self::standing(['state' => GrantState::Suspended])));
        self::assertSame('suspended', StandingJudge::among([['grant' => $dated(), 'standing' => self::standing(['state' => GrantState::Suspended])], $wide], Grants::call(), null)['verdict']->reason ?? null);
        // …until a person supersedes it by name, as its refusal says.
        self::assertTrue($lends(self::standing(['state' => GrantState::Superseded])));

        $readme = (string) preg_replace('/\s+/', ' ', (string) file_get_contents(\dirname(__DIR__, 2) . '/README.md'));
        self::assertStringContainsString('Revoked and superseded grants whose record reads stop nobody; a grant past its own date stops nobody unless it is still suspended', $readme);
        $among = (string) preg_replace('/\s*\*\s*/', ' ', (string) (new \ReflectionMethod(StandingJudge::class, 'among'))->getDocComment());
        self::assertStringContainsString('a grant past its own date stops nobody UNLESS IT IS STILL SUSPENDED', $among);
    }
}
