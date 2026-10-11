<?php

declare(strict_types=1);

namespace Milpa\Command\Tests\Standing;

use Milpa\Command\Standing\GrantStanding;
use Milpa\Command\Standing\NamedCall;
use Milpa\Command\Standing\StandingGrant;
use Milpa\Command\Standing\StandingJudge;
use PHPUnit\Framework\TestCase;

/**
 * What an independent security review found in the contract, each reproduced before it was fixed
 * (greenhouse decisions/0615, «Lo que la revisión encontró»).
 *
 *   4. The floor «never an operation that itself gives authority» was a word, not the axis that says it.
 *   5. A condition's instrument and a falsifier's watcher matched any call that carried the fixed
 *      arguments, so a later run with an argument that weakens it counted.
 *   6. A grant could take a suspended one out of the way without naming the suspension it lifts.
 */
final class WhatAReviewFoundInTheContractTest extends TestCase
{
    public function testACeilingNeverCarriesInstitutionalAuthority(): void
    {
        $ceiling = Grants::document()['ceiling'];

        self::assertSame('write_as_user', Grants::grant()->ceiling->authority->value, 'control: acting as a user fits under a ceiling');
        foreach (['privileged', 'unknown'] as $authority) {
            try {
                Grants::grant(['ceiling' => ['authority' => $authority] + $ceiling]);
                self::fail("a ceiling with authority «{$authority}» was accepted");
            } catch (\InvalidArgumentException $refused) {
                self::assertMatchesRegularExpression('/gives authority|unclassified ceiling/', $refused->getMessage());
            }
        }
    }

    public function testAnInstrumentMatchesOnlyTheCallItsConditionDescribes(): void
    {
        // The condition fixes strict=true and says which argument carries the candidate. Nothing else may ride along.
        $by = NamedCall::fromArray(['operation' => 'lab:scan', 'arguments' => ['strict' => true], 'free' => ['path']], 'here');

        self::assertTrue($by->matchesExactly('lab:scan', ['strict' => true, 'path' => 'a.tar']));
        self::assertTrue($by->matchesExactly('lab_scan', ['strict' => true]), 'an argument it leaves free may be absent');
        self::assertFalse($by->matchesExactly('lab:scan', ['strict' => true, 'path' => 'a.tar', 'skip' => 'binaries']), 'an argument nobody described');
        self::assertFalse($by->matchesExactly('lab:scan', ['strict' => false, 'path' => 'a.tar']));
        self::assertFalse($by->matchesExactly('lab:scan', ['path' => 'a.tar']), 'a fixed argument left out');
        self::assertFalse($by->matchesExactly('lab:lint', ['strict' => true, 'path' => 'a.tar']));
        self::assertSame(['operation' => 'lab.scan', 'arguments' => ['strict' => true], 'free' => ['path']], $by->toArray());
        self::assertSame(['operation' => 'lab.scan'], (new NamedCall('lab:scan'))->toArray(), 'one spelling when nothing is free');
    }

    public function testALaterGreenRunWithAnArgumentThatWeakensItDoesNotCoverARedOne(): void
    {
        $grant = Grants::grant(['conditions' => [['name' => 'no-host-names', 'statement' => 'names no machine', 'by' => ['operation' => 'lab:scan', 'free' => ['path']]]]]);
        $red = Grants::receipt(ok: false);
        $weakened = Grants::receipt(arguments: ['path' => 'candidate.tar', 'lenient' => true]);

        $verdict = StandingJudge::judge($grant, Grants::standing([$red, $weakened]), Grants::call());

        self::assertSame('measured_red', $verdict->reason, 'the run nobody described does not speak for the condition');
        self::assertTrue(StandingJudge::judge($grant, Grants::standing([$red, Grants::receipt()]), Grants::call())->passes(), 'control: the same call, green afterwards, does');
    }

    public function testACoveredActStillLeavesItsOtherArgumentsFree(): void
    {
        // A grant covers a CLASS of acts: the candidate changes on every call. Closing that would cover nothing.
        self::assertNotNull(Grants::grant()->covering('lab:publish', ['channel' => 'stable', 'path' => 'any.tar', 'note' => 'x']));

        try {
            Grants::grant(['covers' => [['operation' => 'lab:publish', 'free' => ['path']]]]);
            self::fail('«free» was accepted where nothing reads it');
        } catch (\InvalidArgumentException $refused) {
            self::assertStringContainsString('«free»', $refused->getMessage());
        }
    }

    public function testWhatIsFreeIsAListOfArgumentNamesAndNeverOneThatIsFixed(): void
    {
        foreach ([['free' => 'path'], ['free' => [7]], ['free' => [' ']], ['arguments' => ['path' => 'a'], 'free' => ['path']]] as $broken) {
            try {
                NamedCall::fromArray(['operation' => 'lab:scan'] + $broken, 'here');
                self::fail('accepted: ' . json_encode($broken));
            } catch (\InvalidArgumentException $refused) {
                self::assertStringContainsString('free', $refused->getMessage());
            }
        }
    }

    public function testAGrantThatSupersedesAnotherMaySayWhichSuspensionItLifts(): void
    {
        $grant = Grants::grant(['id' => 'second', 'supersedes' => 'release-line', 'lifts' => 'sha256:the-suspension']);

        self::assertSame('sha256:the-suspension', $grant->lifts);
        self::assertSame('sha256:the-suspension', $grant->toArray()['lifts']);
        self::assertSame($grant->digest(), StandingGrant::fromJson($grant->canonical())->digest());
        self::assertNull(Grants::grant()->lifts);
        self::assertNotSame($grant->digest(), Grants::grant(['id' => 'second', 'supersedes' => 'release-line'])->digest(), 'what it lifts is part of what was signed');

        foreach ([['lifts' => 'sha256:x'], ['supersedes' => 'older', 'lifts' => ' '], ['supersedes' => 'older', 'lifts' => 7]] as $broken) {
            try {
                Grants::grant($broken);
                self::fail('accepted: ' . json_encode($broken));
            } catch (\InvalidArgumentException $refused) {
                self::assertStringContainsString('lifts', $refused->getMessage());
            }
        }
    }

    public function testAWatcherIsNamedTheSameClosedWay(): void
    {
        $grant = Grants::grant(['falsifier' => ['name' => 'f', 'statement' => 's', 'watcher' => ['operation' => 'lab:watch', 'arguments' => ['line' => 'stable']], 'within_seconds' => 60]]);

        self::assertTrue($grant->falsifier->watcher->matchesExactly('lab:watch', ['line' => 'stable']));
        self::assertFalse($grant->falsifier->watcher->matchesExactly('lab:watch', ['line' => 'stable', 'quiet' => true]));
        self::assertInstanceOf(GrantStanding::class, Grants::standing());
    }
}
