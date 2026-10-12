<?php

declare(strict_types=1);

namespace Milpa\Command\Tests\Standing;

use Milpa\Command\Consent\OperationId;
use Milpa\Command\Standing\NamedCall;
use Milpa\Command\Standing\StandingGrant;
use PHPUnit\Framework\TestCase;

/**
 * What a standing grant names, and how it is identified (greenhouse decisions/0615 §1).
 *
 * A positive, closed list: the seats, and the calls. And one spelling for the document, so that the
 * grant a person signed and the grant a host rebuilt are the same bytes.
 */
final class AStandingGrantNamesWhatItCoversTest extends TestCase
{
    public function testItLendsOnlyToTheSeatsItNamesExactlyAsItSpellsThem(): void
    {
        $grant = Grants::grant(['to' => ['SEAT-B', Grants::SEAT, 'SEAT-B']]);

        self::assertSame(['SEAT-B', Grants::SEAT], $grant->to, 'a seat named twice is named once, and the order is not part of the grant');
        self::assertTrue($grant->lendsTo('SEAT-B'));
        self::assertFalse($grant->lendsTo('seat-b'));
        self::assertFalse($grant->lendsTo('SEAT-C'));
    }

    public function testItNamesAnOperationHoweverASurfaceSpellsIt(): void
    {
        $grant = Grants::grant();

        self::assertTrue($grant->namesOperation('lab:publish'));
        self::assertTrue($grant->namesOperation('lab_publish'));
        self::assertTrue($grant->namesOperation(new OperationId('lab.publish')));
        self::assertFalse($grant->namesOperation('lab:merge'));
    }

    public function testACallIsCoveredWhenItCarriesEveryFixedArgumentWithThatValue(): void
    {
        $grant = Grants::grant(['covers' => [
            ['operation' => 'lab:publish', 'arguments' => ['channel' => 'stable', 'dry' => false, 'retries' => 2]],
            ['operation' => 'lab:merge'],
        ]]);
        $exact = ['channel' => 'stable', 'dry' => false, 'retries' => 2, 'path' => 'candidate.tar'];

        self::assertSame('lab.publish', $grant->covering('lab_publish', $exact)?->operation->canonical);
        self::assertNull($grant->covering('lab:publish', ['channel' => 'beta'] + $exact));
        self::assertNull($grant->covering('lab:publish', ['dry' => true] + $exact));
        self::assertNull($grant->covering('lab:publish', ['retries' => '2'] + $exact), 'the text «2» is not the number 2');
        self::assertNull($grant->covering('lab:publish', ['channel' => 'stable']), 'an argument the grant fixes and the call omits');
        self::assertNotNull($grant->covering('lab:merge', ['anything' => 'at all']), 'what a grant does not fix is free');
        self::assertNull($grant->covering('lab:scan', $exact));
    }

    public function testANamedCallHasOneSpelling(): void
    {
        self::assertSame(['operation' => 'lab.scan'], (new NamedCall('lab:scan'))->toArray());
        self::assertSame(
            ['operation' => 'lab.scan', 'arguments' => ['a' => 1, 'b' => true]],
            NamedCall::fromArray(['operation' => 'lab_scan', 'arguments' => ['b' => true, 'a' => 1]], 'here')->toArray(),
        );
    }

    public function testItsBytesDoNotDependOnTheOrderItWasWrittenIn(): void
    {
        $document = Grants::document(['supersedes' => 'the-one-before', 'expires_at' => '2026-12-31T18:00:00-06:00']);
        $reordered = array_reverse($document, true);
        $reordered['ceiling'] = array_reverse($document['ceiling'], true);

        $grant = StandingGrant::fromArray($document);
        $same = StandingGrant::fromArray($reordered);

        self::assertSame($grant->canonical(), $same->canonical());
        self::assertSame($grant->digest(), $same->digest());
        self::assertSame('sha256:' . hash('sha256', $grant->canonical()), $grant->digest());
        self::assertSame('2027-01-01T00:00:00Z', $grant->toArray()['expires_at'], 'one instant, one spelling');
        self::assertSame('the-one-before', $grant->supersedes);
    }

    /** @return iterable<string, array{string, string}> */
    public static function theFormsAnExpiryIsWrittenIn(): iterable
    {
        yield 'in UTC, as the grant itself writes it' => ['2026-12-31T00:00:00Z', '2026-12-31T00:00:00Z'];
        yield 'with an offset of zero' => ['2026-12-31T00:00:00+00:00', '2026-12-31T00:00:00Z'];
        yield 'west of UTC' => ['2026-12-31T18:00:00-06:00', '2027-01-01T00:00:00Z'];
        yield 'east of UTC, by a quarter of an hour' => ['2026-12-31T05:45:00+05:45', '2026-12-31T00:00:00Z'];
        yield 'the last second of a leap day' => ['2028-02-29T23:59:59Z', '2028-02-29T23:59:59Z'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('theFormsAnExpiryIsWrittenIn')]
    public function testAnExpiryIsOneInstantWhenItIsWrittenAndTheSameOneWheneverItIsRead(string $written, string $instant): void
    {
        $grant = Grants::grant(['expires_at' => $written]);

        self::assertSame($instant, $grant->toArray()['expires_at']);
        // Read again later, in a process that keeps another clock and another zone: the same grant.
        $zone = date_default_timezone_get();
        date_default_timezone_set('Pacific/Kiritimati');
        try {
            self::assertSame($grant->digest(), Grants::grant(['expires_at' => $written])->digest());
            self::assertSame($grant->digest(), StandingGrant::fromJson($grant->canonical())->digest());
        } finally {
            date_default_timezone_set($zone);
        }
    }

    public function testItReadsBackWhatItWrote(): void
    {
        $grant = Grants::grant(['expires_at' => '2026-12-31T00:00:00Z']);
        $again = StandingGrant::fromJson($grant->canonical());

        self::assertSame($grant->digest(), $again->digest());
        self::assertEquals($grant, $again);
    }

    public function testChangingAnythingAPersonSignedChangesItsDigest(): void
    {
        $digest = Grants::grant()->digest();

        foreach ([
            ['to' => [Grants::SEAT, 'SEAT-B']],
            ['covers' => [['operation' => 'lab:publish']]],
            ['ceiling' => ['externality' => 'third_party'] + Grants::document()['ceiling']],
            ['undo' => 'withdraw it'],
            ['expires_at' => '2026-12-31T00:00:00Z'],
            ['supersedes' => 'older'],
        ] as $change) {
            self::assertNotSame($digest, Grants::grant($change)->digest(), (string) json_encode($change));
        }
    }

    public function testACeilingThatPromisesAnInverseKeepsTheOperationItNames(): void
    {
        $ceiling = ['reversibility' => 'guaranteed', 'rollback_contract' => 'lab.withdraw'] + Grants::document()['ceiling'];
        $grant = Grants::grant(['ceiling' => $ceiling]);

        self::assertSame('lab.withdraw', $grant->toArray()['ceiling']['rollback_contract']);
        self::assertSame($grant->digest(), StandingGrant::fromJson($grant->canonical())->digest());
    }
}
