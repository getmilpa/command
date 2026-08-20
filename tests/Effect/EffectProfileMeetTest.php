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
 * `meet()` — the greatest-lower-bound of two effect profiles, the mirror of `join()`.
 *
 * Where `join()` composes UPWARD (takes the more dangerous of each axis, so a ceiling covers both),
 * `meet()` composes DOWNWARD (takes the safer). It is the primitive a STRUCTURAL counter rests on
 * (greenhouse the structural-counter rung): when a human tightens the envelope of a gated call —
 * «authorise it, but only if reversible / only Read» — the tightened ceiling is `meet(C, P_human)`,
 * and it is safe precisely because a greatest-lower-bound can only LOWER, never raise. That «never
 * raises any axis» is the whole safety claim, so it is tested as an invariant, not an example.
 */
final class EffectProfileMeetTest extends TestCase
{
    /** meet takes the LOWER (safer) of each axis — the mirror of join. */
    public function testMeetTakesTheLowerOfEachAxis(): void
    {
        $high = new EffectProfile(
            Mutation::Persistent,
            Externality::Public,
            Reversibility::Irreversible,
            Authority::Privileged,
            subject: Subject::Executable,
        );
        $low = new EffectProfile(
            Mutation::Ephemeral,
            Externality::SamePrincipal,
            Reversibility::Compensatable,
            Authority::Read,
            subject: Subject::Data,
        );

        $m = $high->meet($low);

        self::assertSame(Mutation::Ephemeral, $m->mutation);
        self::assertSame(Externality::SamePrincipal, $m->externality);
        self::assertSame(Reversibility::Compensatable, $m->reversibility);
        self::assertSame(Authority::Read, $m->authority);
        self::assertSame(Subject::Data, $m->subject);
        self::assertEquals($m, $low->meet($high), 'meet is commutative on the lattice axes');
    }

    /**
     * THE SAFETY INVARIANT: meet NEVER raises any axis above either operand.
     *
     * This is the property a structural counter is safe by — `meet(ceiling, P) <= ceiling` on every
     * axis, always. If it ever fails, adjudicating a counter with meet could grant MORE than the
     * agent's call, which is the one thing that must be impossible.
     */
    public function testMeetNeverRaisesAnyAxis(): void
    {
        $profiles = [
            new EffectProfile(Mutation::Persistent, Externality::ThirdParty, Reversibility::Irreversible, Authority::Privileged, subject: Subject::Configuration),
            new EffectProfile(Mutation::Ephemeral, Externality::None, Reversibility::Guaranteed, Authority::Read, subject: Subject::Data, rollbackContract: 'undo'),
            new EffectProfile(Mutation::Unknown, Externality::Public, Reversibility::Unknown, Authority::Unknown, subject: Subject::Unknown),
            new EffectProfile(Mutation::None, Externality::None, Reversibility::Guaranteed, Authority::None, subject: Subject::None, rollbackContract: 'nothing-to-roll-back'),
        ];

        foreach ($profiles as $a) {
            foreach ($profiles as $b) {
                $m = $a->meet($b);
                self::assertLessThanOrEqual(min($a->mutation->weight(), $b->mutation->weight()), $m->mutation->weight(), 'mutation never rises');
                self::assertLessThanOrEqual(min($a->externality->weight(), $b->externality->weight()), $m->externality->weight(), 'externality never rises');
                self::assertLessThanOrEqual(min($a->reversibility->weight(), $b->reversibility->weight()), $m->reversibility->weight(), 'reversibility never rises');
                self::assertLessThanOrEqual(min($a->authority->weight(), $b->authority->weight()), $m->authority->weight(), 'authority never rises');
                self::assertLessThanOrEqual(min($a->subject->weight(), $b->subject->weight()), $m->subject->weight(), 'subject never rises');
            }
        }
    }

    /** meet reaches Guaranteed when EITHER side is, and must carry that side's rollback contract. */
    public function testMeetKeepsTheRollbackContractWhenReversibilityBecomesGuaranteed(): void
    {
        $guaranteed = new EffectProfile(Mutation::Persistent, Externality::None, Reversibility::Guaranteed, Authority::WriteAsUser, subject: Subject::Data, rollbackContract: 'delete the row');
        $irreversible = new EffectProfile(Mutation::Persistent, Externality::None, Reversibility::Irreversible, Authority::WriteAsUser, subject: Subject::Data);

        $m = $guaranteed->meet($irreversible);

        self::assertSame(Reversibility::Guaranteed, $m->reversibility, 'meet takes the safer reversibility');
        self::assertSame('delete the row', $m->rollbackContract, 'and carries the guaranteed side contract, or the profile is invalid');
        self::assertSame('delete the row', $irreversible->meet($guaranteed)->rollbackContract, 'either order');
    }

    /**
     * A no-mutation meet has NO subject — the invariant a lower bound must not break.
     *
     * If the mutating side declares Subject::Unknown and the other is Mutation::None, a naive
     * per-axis meet would keep the mutating side's classified subject while mutation drops to None,
     * producing `Mutation::None` + a subject — which the constructor forbids. Subject::None is `<=`
     * every subject, so clamping it stays a valid greatest-lower-bound.
     */
    public function testANoMutationMeetHasNoSubject(): void
    {
        $readonly = new EffectProfile(Mutation::None, Externality::None, Reversibility::Guaranteed, Authority::Read, subject: Subject::Unknown, rollbackContract: 'nothing-to-roll-back');
        $writes = new EffectProfile(Mutation::Persistent, Externality::None, Reversibility::ManualRecovery, Authority::WriteAsUser, subject: Subject::Data);

        $m = $readonly->meet($writes);

        self::assertSame(Mutation::None, $m->mutation);
        self::assertSame(Subject::None, $m->subject, 'no mutation, so no subject — clamped to keep the profile valid');
    }
}
