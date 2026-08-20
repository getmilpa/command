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
 * The two helpers a structural counter needs beside `meet()` (greenhouse decisions/0067):
 *
 * - `isNoWiderThan()` — the ONE comparator: «this profile is `<=` that one on every axis». It is what
 *   the gate asserts after a meet (the never-widens tripwire) and what the policy uses to admit a
 *   call under a tightened envelope. One ordering, in one place — a second comparator elsewhere is
 *   the duplicate-judge defect.
 * - `fromPartial()` — a human's tightening as a profile: the axes they named, `Unknown` (the top of
 *   every axis) on the ones they did not, so a meet with the declared ceiling leaves those at the
 *   ceiling. `Guaranteed` is refused: it buys less scrutiny and needs a producer's rollback contract,
 *   which a human cannot supply by clicking.
 */
final class EffectProfileEnvelopeTest extends TestCase
{
    private function ceiling(): EffectProfile
    {
        return new EffectProfile(
            Mutation::Persistent,
            Externality::SamePrincipal,
            Reversibility::ManualRecovery,
            Authority::WriteAsUser,
            subject: Subject::Configuration,
        );
    }

    public function testAProfileIsNoWiderThanItself(): void
    {
        self::assertTrue($this->ceiling()->isNoWiderThan($this->ceiling()));
    }

    public function testLowerOnOneAxisIsNoWider(): void
    {
        $tighter = new EffectProfile(
            Mutation::Persistent,
            Externality::SamePrincipal,
            Reversibility::Compensatable,   // lower
            Authority::WriteAsUser,
            subject: Subject::Configuration,
        );

        self::assertTrue($tighter->isNoWiderThan($this->ceiling()));
        self::assertFalse($this->ceiling()->isNoWiderThan($tighter), 'and the ceiling is wider than it');
    }

    /** Incomparable profiles — lower on one axis, higher on another — are NOT no-wider: every axis must hold. */
    public function testHigherOnAnyAxisIsWiderEvenIfLowerElsewhere(): void
    {
        $mixed = new EffectProfile(
            Mutation::Ephemeral,            // lower
            Externality::SamePrincipal,
            Reversibility::ManualRecovery,
            Authority::Privileged,          // HIGHER
            subject: Subject::Configuration,
        );

        self::assertFalse($mixed->isNoWiderThan($this->ceiling()), 'authority rose: not no-wider, whatever mutation did');
    }

    /** Unknown is the TOP of every axis: a profile with Unknown is no narrower than one with a classified value. */
    public function testUnknownWeighsAsTheTopOfItsAxis(): void
    {
        $unknown = new EffectProfile(Mutation::Unknown, Externality::Unknown, Reversibility::Unknown, Authority::Unknown, subject: Subject::Unknown);

        self::assertTrue($this->ceiling()->isNoWiderThan($unknown), 'anything classified is no wider than all-unknown');
        self::assertFalse($unknown->isNoWiderThan($this->ceiling()), 'all-unknown is wider than a classified ceiling');
    }

    /** meet() is always no-wider than both operands — the tripwire a gate asserts after meeting. */
    public function testAMeetIsAlwaysNoWiderThanBothOperands(): void
    {
        $human = EffectProfile::fromPartial(['reversibility' => 'compensatable', 'authority' => 'read']);
        $e = $this->ceiling()->meet($human);

        self::assertTrue($e->isNoWiderThan($this->ceiling()));
        self::assertTrue($e->isNoWiderThan($human));
    }

    public function testFromPartialNamesTheGivenAxesAndLeavesTheRestUnknown(): void
    {
        $p = EffectProfile::fromPartial(['reversibility' => 'compensatable', 'authority' => 'read']);

        self::assertSame(Reversibility::Compensatable, $p->reversibility);
        self::assertSame(Authority::Read, $p->authority);
        self::assertSame(Mutation::Unknown, $p->mutation, 'omitted = Unknown = top, so a meet leaves the ceiling there');
        self::assertSame(Externality::Unknown, $p->externality);
        self::assertSame(Subject::Unknown, $p->subject);
    }

    /** Omitted axes do not tighten: meet with the ceiling keeps the ceiling's value there. */
    public function testOmittedAxesDoNotTightenUnderMeet(): void
    {
        $e = $this->ceiling()->meet(EffectProfile::fromPartial(['reversibility' => 'compensatable']));

        self::assertSame(Reversibility::Compensatable, $e->reversibility, 'the named axis tightened');
        self::assertSame(Mutation::Persistent, $e->mutation, 'an omitted axis stays at the ceiling');
        self::assertSame(Authority::WriteAsUser, $e->authority);
        self::assertSame(Subject::Configuration, $e->subject);
    }

    /** A human cannot claim Guaranteed: that needs a producer-backed rollback contract, never a click. */
    public function testFromPartialRefusesGuaranteed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('guaranteed');

        EffectProfile::fromPartial(['reversibility' => 'guaranteed']);
    }

    /** A key that is not an axis is refused — that is a VALUE change, which is the advisory counter's job. */
    public function testFromPartialRefusesNonAxisKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('amount');

        EffectProfile::fromPartial(['amount' => 200]);
    }

    public function testFromPartialRefusesAnUnknownLevel(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EffectProfile::fromPartial(['authority' => 'god']);
    }

    public function testFromPartialRefusesAnEmptyTightening(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EffectProfile::fromPartial([]);
    }

    /**
     * A stored envelope comes back as the same profile — toArray/fromArray round-trip.
     *
     * The envelope lives in an event payload as an array; the policy rehydrates it to compare with
     * the one comparator. If the round-trip drifts, the gate compares against a profile nobody
     * granted.
     */
    public function testFromArrayIsTheInverseOfToArray(): void
    {
        $guaranteed = new EffectProfile(
            Mutation::Persistent,
            Externality::None,
            Reversibility::Guaranteed,
            Authority::WriteAsUser,
            escalatesOn: ['path'],
            subject: Subject::Data,
            rollbackContract: 'delete the row',
        );

        foreach ([$this->ceiling(), $guaranteed, EffectProfile::unclassified(), EffectProfile::readOnly()] as $p) {
            $back = EffectProfile::fromArray($p->toArray());
            self::assertTrue($back->isNoWiderThan($p) && $p->isNoWiderThan($back), 'same on every axis');
            self::assertSame($p->rollbackContract, $back->rollbackContract);
            self::assertSame($p->escalatesOn, $back->escalatesOn);
        }
    }

    public function testFromArrayRefusesAMalformedEnvelope(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EffectProfile::fromArray(['mutation' => 'persistent']);   // four axes missing
    }
}
