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
use Milpa\Command\Effect\TrialConfinement;
use PHPUnit\Framework\TestCase;

/**
 * The TrialWorkspace as a PRODUCER of confinement (greenhouse decisions/0068, 0069).
 *
 * Composition lowers an axis only through a producer with provenance. A declared descent is one
 * (triggered by an argument, certified by the lab); the app's policy is another (authority, judged
 * live). This adds a third, live one: a call confined to a disposable workspace composes its mutation
 * as EPHEMERAL — what it writes dies with the workspace — and NOTHING else. Externality in particular
 * is never touched: a copy isolates files, not the world (0068: zero descent on third_party).
 */
final class TrialConfinementTest extends TestCase
{
    private function techo(): EffectProfile
    {
        return new EffectProfile(
            Mutation::Persistent,
            Externality::SamePrincipal,
            Reversibility::ManualRecovery,
            Authority::WriteAsUser,
            subject: Subject::Configuration,
        );
    }

    private function confinamiento(): TrialConfinement
    {
        return new TrialConfinement(
            workspaceId: 'trial-7f3a',
            argumentsDigest: 'sha256:abc',
            bounds: ['fs' => 'ro-root+rw-copy', 'net' => 'unshared', 'pid' => 'unshared'],
            because: 'the call runs in a disposable copy under bwrap',
        );
    }

    public function testTheProvenanceNamesTheWorkspaceTheCallAndTheBounds(): void
    {
        $p = $this->confinamiento()->provenance();

        self::assertStringContainsString('trial:trial-7f3a', $p);
        self::assertStringContainsString('args:sha256:abc', $p);
        self::assertStringContainsString('net:unshared', $p);
        self::assertStringContainsString('fs:ro-root+rw-copy', $p);
    }

    /** A confined call composes its mutation as Ephemeral — and only that. */
    public function testAConfinedCallComposesMutationAsEphemeralAndNothingElse(): void
    {
        $subject = new CallSubject('probe', confinement: $this->confinamiento());

        $c = $this->techo()->composeForCall([], $subject);

        self::assertSame(Mutation::Ephemeral, $c->effective->mutation);
        self::assertSame(Externality::SamePrincipal, $c->effective->externality, 'externality is NEVER lowered by a copy');
        self::assertSame(Reversibility::ManualRecovery, $c->effective->reversibility);
        self::assertSame(Authority::WriteAsUser, $c->effective->authority);
        self::assertSame(Subject::Configuration, $c->effective->subject);
    }

    /** The reduction is a receipt: axis, from, to, producer, provenance. */
    public function testTheReductionCitesTheTrialWorkspaceAsProducer(): void
    {
        $subject = new CallSubject('probe', confinement: $this->confinamiento());

        $c = $this->techo()->composeForCall([], $subject);

        self::assertCount(1, $c->reductions);
        $r = $c->reductions[0];
        self::assertSame('mutation', $r->axis);
        self::assertSame('persistent', $r->from);
        self::assertSame('ephemeral', $r->to);
        self::assertSame('trial-workspace', $r->producer);
        self::assertStringContainsString('trial:trial-7f3a', $r->provenance);
        self::assertTrue($c->confinedByTrial());
    }

    /** Without a confinement nothing changes — and confinedByTrial() is false. */
    public function testWithoutConfinementTheCompositionIsUntouched(): void
    {
        $c = $this->techo()->composeForCall([], new CallSubject('probe'));

        self::assertSame(Mutation::Persistent, $c->effective->mutation);
        self::assertSame([], $c->reductions);
        self::assertFalse($c->confinedByTrial());
    }

    /** A third_party operation confined to a copy is STILL third_party: the copy isolates files, not the world. */
    public function testAThirdPartyOperationStaysThirdPartyWhenConfined(): void
    {
        $composer = new EffectProfile(Mutation::Persistent, Externality::ThirdParty, Reversibility::ManualRecovery, Authority::Privileged, subject: Subject::Executable);

        $c = $composer->composeForCall([], new CallSubject('capabilities:enable', confinement: $this->confinamiento()));

        self::assertSame(Mutation::Ephemeral, $c->effective->mutation, 'its files are confined');
        self::assertSame(Externality::ThirdParty, $c->effective->externality, 'what crosses the network is not undone by discarding a folder');
        self::assertSame(Authority::Privileged, $c->effective->authority);
    }

    /** Confinement cannot RAISE: an operation already at Ephemeral or None stays where it is, with no reduction. */
    public function testConfinementNeverRaisesMutation(): void
    {
        $ephemeral = new EffectProfile(Mutation::Ephemeral, Externality::None, Reversibility::Guaranteed, Authority::Read, subject: Subject::Data, rollbackContract: 'dies with the process');
        $read = EffectProfile::readOnly();

        $c1 = $ephemeral->composeForCall([], new CallSubject('tmp', confinement: $this->confinamiento()));
        $c2 = $read->composeForCall([], new CallSubject('ls', confinement: $this->confinamiento()));

        self::assertSame(Mutation::Ephemeral, $c1->effective->mutation);
        self::assertSame([], $c1->reductions, 'nothing was lowered, so no receipt');
        self::assertSame(Mutation::None, $c2->effective->mutation);
        self::assertSame([], $c2->reductions);
        self::assertFalse($c2->confinedByTrial());
    }

    /** A declared descent that held is respected; the confinement adds on top of its result. */
    public function testConfinementComposesOnTopOfADeclaredDescent(): void
    {
        // An authority-only descent (no certificate needed) lowers authority to Read when triggered;
        // here no policy is present, so it does NOT hold — the ceiling stays, then confinement lowers mutation.
        $subject = new CallSubject('probe', confinement: $this->confinamiento());

        $c = $this->techo()->composeForCall(['mode' => 'owned'], $subject);

        self::assertSame(Mutation::Ephemeral, $c->effective->mutation);
        self::assertSame(Authority::WriteAsUser, $c->effective->authority, 'no policy judged the authority descent, so authority stays');
    }
    /** A confinement that cannot name its workspace, its call or its reason is a claim nobody can check — refused. */
    public function testAConfinementWithoutItsNamesIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TrialConfinement(workspaceId: '', argumentsDigest: 'sha256:abc', bounds: [], because: 'x');
    }
}
