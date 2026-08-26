<?php

declare(strict_types=1);

namespace Milpa\Command\Tests\Effect;

use Milpa\Command\Effect\Authority;
use Milpa\Command\Effect\CallSubject;
use Milpa\Command\Effect\EffectProfile;
use Milpa\Command\Effect\Externality;
use Milpa\Command\Effect\Mutation;
use Milpa\Command\Effect\Reversibility;
use Milpa\Command\Effect\Subject;
use Milpa\Command\Effect\SubjectAttestation;
use Milpa\Command\Effect\TrialConfinement;
use PHPUnit\Framework\TestCase;

/**
 * The owner of a call's PAYLOAD as a producer of its SUBJECT (greenhouse decisions/0080).
 *
 * `Subject` is what a change is made OF, and for an operation that carries its change as data — a
 * promotion carries a diff — the declaration can only state the ceiling. The producer that owns the
 * payload may ATTEST a lower subject for THIS call, and composition lowers that one axis, with
 * provenance, and nothing else. It never raises: an attestation above the effective subject is
 * simply not a reduction. A read has no subject to lower.
 */
final class SubjectAttestationTest extends TestCase
{
    private function techo(): EffectProfile
    {
        return new EffectProfile(
            Mutation::Persistent,
            Externality::None,
            Reversibility::ManualRecovery,
            Authority::WriteAsUser,
            subject: Subject::Executable,
        );
    }

    private function atestacion(Subject $subject = Subject::Configuration): SubjectAttestation
    {
        return new SubjectAttestation($subject, 'trial-workspace', 'diff:sha256:abc');
    }

    public function testAnAttestationNamesItsProducerAndProvenanceOrItIsNotOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SubjectAttestation(Subject::Configuration, '', 'diff:sha256:abc');
    }

    public function testAnAttestedLowerSubjectComposesThatOneAxisWithProvenance(): void
    {
        $c = $this->techo()->composeForCall([], new CallSubject('sandbox:promote', subjectAttestation: $this->atestacion()));

        self::assertSame(Subject::Configuration, $c->effective->subject);
        self::assertSame(Mutation::Persistent, $c->effective->mutation, 'only subject moves');
        self::assertSame(Externality::None, $c->effective->externality);
        self::assertSame(Reversibility::ManualRecovery, $c->effective->reversibility);
        self::assertSame(Authority::WriteAsUser, $c->effective->authority);
        self::assertCount(1, $c->reductions);
        self::assertSame('subject', $c->reductions[0]->axis);
        self::assertSame('executable', $c->reductions[0]->from);
        self::assertSame('configuration', $c->reductions[0]->to);
        self::assertSame('trial-workspace', $c->reductions[0]->producer);
        self::assertSame('diff:sha256:abc', $c->reductions[0]->provenance);
    }

    public function testAnAttestationNeverRaisesTheSubject(): void
    {
        $declared = new EffectProfile(Mutation::Persistent, Externality::None, Reversibility::ManualRecovery, Authority::WriteAsUser, subject: Subject::Data);
        $c = $declared->composeForCall([], new CallSubject('op', subjectAttestation: $this->atestacion(Subject::Executable)));

        self::assertSame(Subject::Data, $c->effective->subject, 'an attestation above the ceiling is not a reduction');
        self::assertSame([], $c->reductions);
    }

    public function testAnAttestationEqualToTheCeilingLeavesNoReceipt(): void
    {
        $c = $this->techo()->composeForCall([], new CallSubject('op', subjectAttestation: $this->atestacion(Subject::Executable)));

        self::assertSame(Subject::Executable, $c->effective->subject);
        self::assertSame([], $c->reductions);
    }

    public function testWithoutAnAttestationNothingChanges(): void
    {
        $c = $this->techo()->composeForCall([], new CallSubject('op'));

        self::assertSame(Subject::Executable, $c->effective->subject);
        self::assertSame([], $c->reductions);
    }

    public function testAReadHasNoSubjectToLower(): void
    {
        $read = EffectProfile::readOnly();
        $c = $read->composeForCall([], new CallSubject('op', subjectAttestation: $this->atestacion(Subject::Data)));

        self::assertSame(Subject::None, $c->effective->subject);
        self::assertSame([], $c->reductions);
    }

    public function testItComposesOnTopOfConfinementAndBothReceiptsSurvive(): void
    {
        $confinement = new TrialConfinement('trial-7f3a', 'sha256:abc', ['fs' => 'ro-root+rw-copy'], 'runs in a disposable copy');
        $c = $this->techo()->composeForCall([], new CallSubject('op', confinement: $confinement, subjectAttestation: $this->atestacion()));

        self::assertSame(Mutation::Ephemeral, $c->effective->mutation);
        self::assertSame(Subject::Configuration, $c->effective->subject);
        self::assertCount(2, $c->reductions);
        self::assertSame(['mutation', 'subject'], array_map(static fn ($r) => $r->axis, $c->reductions));
        self::assertTrue($c->confinedByTrial());
    }
}
