<?php

/**
 * This file is part of milpa/command — the atom: one declared Operation, projected by every surface.
 *
 * (c) Rodrigo Vicente - TeamX Agency — https://teamx.agency <hola@teamx.agency>
 *
 * @license Apache-2.0
 *
 * @link    https://github.com/getmilpa/command
 */

declare(strict_types=1);

namespace Milpa\Command\Tests\Effect;

use Milpa\Command\Effect\Authority;
use Milpa\Command\Effect\CallSubject;
use Milpa\Command\Effect\ContextFacts;
use Milpa\Command\Effect\DeclaredAuthorityPolicy;
use Milpa\Command\Effect\Descent;
use Milpa\Command\Effect\DescentCertificate;
use Milpa\Command\Effect\EffectProfile;
use Milpa\Command\Effect\Externality;
use Milpa\Command\Effect\Mutation;
use Milpa\Command\Effect\Reversibility;
use Milpa\Command\Effect\Subject;
use PHPUnit\Framework\TestCase;

/**
 * The battery greenhouse decisions/0057 froze, and it promotes H-EFFECT-PROVENANCE (decisions/0053)
 * from a hypothesis to a claim with executable falsifiers.
 *
 * composeForCall returns not just the effective ceiling but the RECEIPT of how it was reached: one
 * reduction per axis that came down, each naming the producer that had the right to lower it — the
 * observer's certificate for observed axes, the policy's judgment for authority. Composing the
 * ceiling without that receipt is keeping the answer and throwing away the question.
 */
final class ProfileCompositionTest extends TestCase
{
    private const DIGEST = 'sha256:the-handler-the-verifier-watched';

    private string $publica = '';

    private string $privada = '';

    protected function setUp(): void
    {
        $par = sodium_crypto_sign_keypair();
        $this->publica = base64_encode(sodium_crypto_sign_publickey($par));
        $this->privada = sodium_crypto_sign_secretkey($par);
    }

    /** 1 · a descent lowering authority alone records ONE reduction, produced by the POLICY. */
    public function testAuthorityReductionIsAttributedToThePolicy(): void
    {
        $composicion = $this->soloAutoridad()->composeForCall(['dry_run' => true], $this->sujeto());

        self::assertSame(Authority::Read, $composicion->effective->authority);
        self::assertCount(1, $composicion->reductions);
        $r = $composicion->reductions[0];
        self::assertSame('authority', $r->axis);
        self::assertSame('policy', $r->producer);
        self::assertStringContainsString('lab', $r->provenance);
    }

    /** 2 · a descent lowering an observed axis records it as produced by the OBSERVER's certificate. */
    public function testAnObservedReductionIsAttributedToTheCertificate(): void
    {
        $composicion = $this->conCertificado()->composeForCall(['dry_run' => true], $this->sujeto());

        $ejes = array_map(static fn ($r) => $r->axis, $composicion->reductions);
        self::assertContains('mutation', $ejes);
        foreach ($composicion->reductions as $r) {
            if ($r->axis === 'mutation') {
                self::assertSame('observer', $r->producer);
                self::assertStringContainsString('verify-descent', $r->provenance);
            }
        }
    }

    /** 3 · forCall is composeForCall(...)->effective: the returned profile is byte-identical. */
    public function testForCallStillReturnsTheEffectiveProfile(): void
    {
        $techo = $this->soloAutoridad();

        self::assertEquals(
            $techo->composeForCall(['dry_run' => true], $this->sujeto())->effective,
            $techo->forCall(['dry_run' => true], $this->sujeto()),
        );
    }

    /** 4 · F-3 of decisions/0053: a certificate covering «authority» is NOT its producer. */
    public function testACertificateNeverProducesTheAuthorityAxis(): void
    {
        // The certificate's covers lists every axis, authority included; the composition must still
        // attribute authority to the policy, never to the certificate.
        $composicion = $this->conCertificado()->composeForCall(['dry_run' => true], $this->sujeto());

        foreach ($composicion->reductions as $r) {
            if ($r->axis === 'authority') {
                self::assertSame('policy', $r->producer, 'authority is the policy\'s, whatever a certificate claims to cover');
            }
        }
    }

    /** 5 · F-5 of decisions/0053: an axis with no producer never appears in the receipt. */
    public function testAnAxisWithoutAProducerIsNotReduced(): void
    {
        // externality has no producer; the descent does not lower it, so it must not appear.
        $composicion = $this->conCertificado()->composeForCall(['dry_run' => true], $this->sujeto());

        $ejes = array_map(static fn ($r) => $r->axis, $composicion->reductions);
        self::assertNotContains('externality', $ejes);
    }

    /** 6 · no descent triggered: no reductions, and the effective ceiling is the declared one. */
    public function testWithoutADescentNothingIsReduced(): void
    {
        $composicion = $this->soloAutoridad()->composeForCall([], $this->sujeto());

        self::assertSame([], $composicion->reductions);
        self::assertSame(Authority::Privileged, $composicion->effective->authority);
    }

    /** 7 · F-4 of decisions/0053: change the policy, and the authority reduction changes at once. */
    public function testChangingThePolicyChangesTheReceiptWithoutAnyStoredClaim(): void
    {
        $techo = $this->soloAutoridad();

        $concede = $techo->composeForCall(['dry_run' => true], $this->sujeto());
        self::assertCount(1, $concede->reductions);

        // A policy that grants nothing for this operation: the descent no longer holds, no reduction.
        $niega = $techo->composeForCall(['dry_run' => true], $this->sujeto(policy: new DeclaredAuthorityPolicy('vacia', [])));
        self::assertSame([], $niega->reductions);
        self::assertSame(Authority::Privileged, $niega->effective->authority);
    }

    /**
     * 8 · explain() is honest even about a descent that would not hold: called directly with no
     * policy, an authority reduction says «no judgment» rather than inventing a producer.
     */
    public function testExplainNamesAMissingProducerRatherThanInventingOne(): void
    {
        $techo = $this->soloAutoridad();
        $descent = $techo->descents[0];

        // No policy, no facts on the subject: the axis still came down in `to`, and explain says why it could not be attributed.
        $reductions = $descent->explain($techo, new CallSubject('capabilities:enable', self::DIGEST));

        self::assertCount(1, $reductions);
        self::assertStringContainsString('no judgment', $reductions[0]->provenance);
    }

    /** 9 · an observed axis lowered by a descent with no certificate says so, rather than citing nothing. */
    public function testExplainNamesAMissingCertificate(): void
    {
        $to = new EffectProfile(
            mutation: Mutation::Ephemeral,
            externality: Externality::ThirdParty,
            reversibility: Reversibility::Compensatable,
            authority: Authority::Privileged,
            subject: Subject::Executable,
        );
        $techo = new EffectProfile(
            mutation: Mutation::Persistent,
            externality: Externality::ThirdParty,
            reversibility: Reversibility::Compensatable,
            authority: Authority::Privileged,
            subject: Subject::Executable,
            descents: [new Descent('dry_run', true, $to, 'lowers mutation with no certificate', null)],
        );

        $reductions = $techo->descents[0]->explain($techo, new CallSubject('x', self::DIGEST));

        self::assertCount(1, $reductions);
        self::assertSame('mutation', $reductions[0]->axis);
        self::assertStringContainsString('no certificate', $reductions[0]->provenance);
    }

    private function sujeto(?DeclaredAuthorityPolicy $policy = null): CallSubject
    {
        return new CallSubject(
            'capabilities:enable',
            self::DIGEST,
            policy: $policy ?? new DeclaredAuthorityPolicy('lab', [
                'capabilities:enable' => ['scopes' => ['probes:run'], 'authority' => Authority::Read],
            ]),
            facts: new ContextFacts(principal: 'rod', verified: true, scopes: ['probes:run']),
        );
    }

    /** A descent that lowers authority alone — the policy is its only producer. */
    private function soloAutoridad(): EffectProfile
    {
        $to = new EffectProfile(
            mutation: Mutation::Persistent,
            externality: Externality::ThirdParty,
            reversibility: Reversibility::Compensatable,
            authority: Authority::Read,
            subject: Subject::Executable,
        );

        return new EffectProfile(
            mutation: Mutation::Persistent,
            externality: Externality::ThirdParty,
            reversibility: Reversibility::Compensatable,
            authority: Authority::Privileged,
            subject: Subject::Executable,
            descents: [new Descent('dry_run', true, $to, 'the policy judges who may rehearse', null)],
        );
    }

    /** A descent that lowers every axis: the certificate covers the observed ones, the policy authority. */
    private function conCertificado(): EffectProfile
    {
        // The destination lowers ONLY mutation (observer) and authority (policy). externality,
        // reversibility and subject keep their declared levels, because no producer can demonstrate
        // them (greenhouse evidence/0252) — so an honest certificate covers just mutation.
        $to = new EffectProfile(
            mutation: Mutation::Ephemeral,
            externality: Externality::ThirdParty,
            reversibility: Reversibility::Compensatable,
            authority: Authority::Read,
            subject: Subject::Executable,
        );
        $cert = (new DescentCertificate(
            verifier: 'verify-descent/2026-08-18',
            operation: 'capabilities:enable',
            predicate: ['dry_run' => true],
            covers: ['mutation'],
            to: $to,
            handlerSha256: self::DIGEST,
            verifierPublicKey: $this->publica,
        ))->signedWith($this->privada);

        return new EffectProfile(
            mutation: Mutation::Persistent,
            externality: Externality::ThirdParty,
            reversibility: Reversibility::Compensatable,
            authority: Authority::Privileged,
            subject: Subject::Executable,
            descents: [new Descent('dry_run', true, $to, 'rehearsal', $cert)],
        );
    }
}
