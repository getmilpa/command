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
 * The fifth dimension: what the mutation is made OF.
 *
 * The other four answer «how much» — how durable, how far it reaches, whether it comes back, what
 * power it spends. None of them answers «of what», and that turned out to be the axis this house
 * actually gates on: eight operations were measured identical on all four while half demanded a
 * signature and half did not (greenhouse decisions/0017, 0018, 0019).
 */
final class SubjectTest extends TestCase
{
    /**
     * Unknown outranks everything KNOWN, exactly like the other four dimensions.
     *
     * «I do not know what this changes» is a worse position than «I know it replaces code», because
     * the second can be reasoned about and the first cannot.
     */
    public function testUnknownOutweighsEvenChangingTheExecutable(): void
    {
        self::assertGreaterThan(Subject::None->weight(), Subject::Configuration->weight());
        self::assertGreaterThan(Subject::Configuration->weight(), Subject::Executable->weight());
        self::assertGreaterThan(Subject::Executable->weight(), Subject::Unknown->weight());
    }

    /** An operation that never said gets the ceiling, not the floor (GOV-05). */
    public function testTheDefaultIsUnknownAndUnknownIsNotClassified(): void
    {
        $profile = EffectProfile::unclassified();

        self::assertSame(Subject::Unknown, $profile->subject);
        self::assertFalse($profile->isFullyClassified());
    }

    /** Declaring the other four and forgetting this one is still not classified. */
    public function testFourOutOfFiveIsNotClassified(): void
    {
        $profile = new EffectProfile(
            Mutation::Persistent,
            Externality::None,
            Reversibility::ManualRecovery,
            Authority::Privileged,
        );

        self::assertFalse(
            $profile->isFullyClassified(),
            'a profile missing the subject is four answers about how much and none about of what',
        );
    }

    /** Joining takes the higher subject, like every other dimension — risks are not averaged. */
    public function testJoiningTakesTheHigherSubject(): void
    {
        $data = new EffectProfile(
            Mutation::Persistent,
            Externality::None,
            Reversibility::ManualRecovery,
            Authority::WriteAsUser,
            subject: Subject::Data,
        );
        $code = new EffectProfile(
            Mutation::Persistent,
            Externality::None,
            Reversibility::ManualRecovery,
            Authority::WriteAsUser,
            subject: Subject::Executable,
        );

        self::assertSame(Subject::Executable, $data->join($code)->subject);
        self::assertSame(Subject::Executable, $code->join($data)->subject);
    }

    /**
     * A read has no subject, and saying otherwise is IMPOSSIBLE rather than merely wrong.
     *
     * Four blind judges classified thirty-three operations with only the definition, and every
     * disagreement they produced was on an operation that changes nothing at all — they were being
     * asked what a read is made of, and there is no answer. The durability axis already says nothing
     * changes; this invariant makes the two agree by construction instead of by review.
     */
    public function testAnOperationThatChangesNothingCannotClaimASubject(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/changes nothing/i');

        new EffectProfile(
            Mutation::None,
            Externality::None,
            Reversibility::Guaranteed,
            Authority::Read,
            subject: Subject::Executable,
            rollbackContract: 'nothing-to-roll-back',
        );
    }

    /** And the read-only profile says so itself. */
    public function testTheReadOnlyProfileDeclaresNoSubject(): void
    {
        self::assertSame(Subject::None, EffectProfile::readOnly()->subject);
        self::assertTrue(EffectProfile::readOnly()->isFullyClassified());
    }
}
