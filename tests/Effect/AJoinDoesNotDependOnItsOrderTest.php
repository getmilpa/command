<?php

declare(strict_types=1);

namespace Milpa\Command\Tests\Effect;

use Milpa\Command\Effect\Authority;
use Milpa\Command\Effect\EffectProfile;
use Milpa\Command\Effect\Externality;
use Milpa\Command\Effect\Mutation;
use Milpa\Command\Effect\Reversibility;
use Milpa\Command\Effect\Subject;
use PHPUnit\Framework\TestCase;

/**
 * A FOLD WHOSE LABEL DEPENDS ON THE ORDER IT WAS FOLDED IN IS NOT A CEILING.
 *
 * Measured before this test existed (greenhouse decisions/0224): with `Unknown` and `Irreversible`
 * weighing the same, `irreversible.join(unknown)` answered `irreversible` and
 * `unknown.join(irreversible)` answered `unknown` — the join broke the tie towards its left side,
 * so `isFullyClassified()` of a fold depended on which profile came first. Downstream, that let a
 * provider's ceiling depend on the order of `config/operations.php`.
 */
final class AJoinDoesNotDependOnItsOrderTest extends TestCase
{
    /** F0 · joining in either order yields the same profile, reversibility included. */
    public function testJoinIsCommutativeOnEveryAxisIncludingReversibility(): void
    {
        $irreversible = self::profile(Reversibility::Irreversible);
        $unknown = EffectProfile::unclassified();

        self::assertEquals($unknown->join($irreversible)->toArray(), $irreversible->join($unknown)->toArray());
        self::assertSame(Reversibility::Unknown, $irreversible->join($unknown)->reversibility, 'the known label hid the unknown one');
    }

    /**
     * The shape that flipped: a profile classified on four axes and unknown on reversibility, folded
     * with an irreversible one. It must come out NOT fully classified whichever side folds first —
     * otherwise the unknown disappears behind the irreversible label on one of the two orders.
     */
    public function testAPartiallyClassifiedProfileStaysUnclassifiedWhicheverSideFoldsFirst(): void
    {
        $irreversible = self::profile(Reversibility::Irreversible);
        $partial = self::profile(Reversibility::Unknown);

        self::assertFalse($irreversible->join($partial)->isFullyClassified(), 'the unknown hid behind the known label');
        self::assertFalse($partial->join($irreversible)->isFullyClassified());
        self::assertEquals($partial->join($irreversible)->toArray(), $irreversible->join($partial)->toArray());
    }

    /** THE CONTROL: two KNOWN levels already joined the same in either order, and still do. */
    public function testTwoKnownLevelsJoinTheSameInEitherOrder(): void
    {
        $manual = self::profile(Reversibility::ManualRecovery);
        $irreversible = self::profile(Reversibility::Irreversible);

        self::assertEquals($irreversible->join($manual)->toArray(), $manual->join($irreversible)->toArray());
        self::assertSame(Reversibility::Irreversible, $manual->join($irreversible)->reversibility);
    }

    /** And «not below»: unknown still never reads as cheaper than irreversible. */
    public function testUnknownIsNotBelowIrreversible(): void
    {
        self::assertFalse(self::profile(Reversibility::Unknown)->isNoWiderThan(self::profile(Reversibility::Irreversible)), 'unknown fit under irreversible — the cheap way to look recoverable');
        self::assertTrue(self::profile(Reversibility::Irreversible)->isNoWiderThan(self::profile(Reversibility::Unknown)));
    }

    private static function profile(Reversibility $reversibility): EffectProfile
    {
        return new EffectProfile(Mutation::Persistent, Externality::None, $reversibility, Authority::WriteAsUser, subject: Subject::Data);
    }
}
