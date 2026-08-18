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
 * The battery greenhouse decisions/0054 froze: the policy judges authority, the context only brings
 * verified facts, and a cheap judgment is asked again rather than stored.
 *
 * `evidence/0252` measured why this producer exists at all: `authority` — the axis Consent reads —
 * was DECLARED-WITHOUT-PRODUCER. The contract's own fields collide (one fingerprint carried both
 * write_as_user and privileged), so no derivation can produce it, and no observation can either:
 * requiring privilege is not a diff on disk. What is left is Rod's chain — verified facts judged by
 * an institutional policy, with the judgment consulted LIVE so a policy change can never leave a
 * stale claim in force (F-4 of decisions/0053, dead by construction).
 *
 * Case 1 is the positive control and carries F-1 of decisions/0054: a descent lowering authority,
 * under a policy whose rule the verified facts satisfy, MUST come down — or the producer cost
 * something and bought nothing.
 */
final class AuthorityIsJudgedTest extends TestCase
{
    private string $publica = '';

    private string $privada = '';

    protected function setUp(): void
    {
        $par = sodium_crypto_sign_keypair();
        $this->publica = base64_encode(sodium_crypto_sign_publickey($par));
        $this->privada = sodium_crypto_sign_secretkey($par);
    }

    /** 1 · POSITIVE CONTROL · verified facts satisfying the rule, and the axis comes down. */
    public function testAJudgedAuthorityDescends(): void
    {
        $techo = $this->instala();

        self::assertSame(Authority::Read, $techo->forCall(['dry_run' => true], $this->sujeto())->authority);
        self::assertSame(Mutation::None, $techo->forCall(['dry_run' => true], $this->sujeto())->mutation);
    }

    /**
     * 1b · A DESCENT THAT LOWERS ONLY AUTHORITY NEEDS ONLY THE POLICY — no certificate at all.
     *
     * greenhouse evidence/0255 measured this on cattle with a real signature: a probe whose descent
     * lowers authority alone, admitted under a valid judgment, did NOT come down — because holds()
     * required a signed certificate even though the only lowered axis is one the certificate has no
     * say over. That couples two producers decisions/0053 said must be independent: each axis is
     * reduced only by ITS producer. Authority's producer is the policy; the certificate gates the
     * axes it covers, and when it covers none it is simply not consulted.
     */
    public function testADescentLoweringOnlyAuthorityNeedsNoCertificate(): void
    {
        // Same heavy ceiling, but the destination lowers ONLY authority — nothing for a certificate
        // to cover — and the descent carries no certificate.
        $soloAutoridad = new EffectProfile(
            mutation: Mutation::Persistent,
            externality: Externality::ThirdParty,
            reversibility: Reversibility::Compensatable,
            authority: Authority::Read,
            subject: Subject::Executable,
        );
        $techo = new EffectProfile(
            mutation: Mutation::Persistent,
            externality: Externality::ThirdParty,
            reversibility: Reversibility::Compensatable,
            authority: Authority::Privileged,
            subject: Subject::Executable,
            descents: [new Descent(
                argument: 'dry_run',
                whenValue: true,
                to: $soloAutoridad,
                because: 'the policy judges who may rehearse without privilege',
                certificate: null,
            )],
        );

        self::assertSame(Authority::Read, $techo->forCall(['dry_run' => true], $this->sujeto())->authority);
        // And the control that keeps this honest: without a judgment, it stays up.
        self::assertSame(Authority::Privileged, $techo->forCall(['dry_run' => true], new CallSubject('capabilities:enable', self::DIGEST))->authority);
    }

    /** 2 · F-3 of decisions/0053: a signed certificate whose covers says «authority» buys nothing THERE. */
    public function testACertificateCoveringAuthorityDoesNotJudgeIt(): void
    {
        $techo = $this->instala();
        $sinPolicy = new CallSubject('capabilities:enable', self::DIGEST);

        self::assertSame(Authority::Privileged, $techo->forCall(['dry_run' => true], $sinPolicy)->authority);
    }

    /** 3 · F-2 · hearsay is not a fact: verified=false judges nothing, whatever the facts say. */
    public function testUnverifiedFactsJudgeNothing(): void
    {
        $techo = $this->instala();
        $sujeto = $this->sujeto(facts: new ContextFacts(principal: 'rod', verified: false, scopes: ['capabilities:write']));

        self::assertSame(Authority::Privileged, $techo->forCall(['dry_run' => true], $sujeto)->authority);
    }

    /** 4 · a missing scope leaves the rule unsatisfied, and the axis stays up. */
    public function testAMissingScopeLeavesTheCeilingUp(): void
    {
        $techo = $this->instala();
        $sujeto = $this->sujeto(facts: new ContextFacts(principal: 'rod', verified: true, scopes: ['otra:cosa']));

        self::assertSame(Authority::Privileged, $techo->forCall(['dry_run' => true], $sujeto)->authority);
    }

    /** 5 · F-5 · DENY BY DEFAULT: an operation the policy has no rule for gets no judgment. */
    public function testAnOperationWithoutARuleGetsNoJudgment(): void
    {
        $policy = $this->policy();
        $juicio = $policy->judge(
            new ContextFacts(principal: 'rod', verified: true, scopes: ['capabilities:write']),
            new CallSubject('otra:operacion', self::DIGEST),
        );

        self::assertNull($juicio);
    }

    /** 6 · a judgment that lands ABOVE the descent's destination does not justify it. */
    public function testAJudgmentAboveTheDestinationDoesNotJustifyIt(): void
    {
        // The policy grants write_as_user; the descent promises Read. Judged > declared → no descent.
        $policy = new DeclaredAuthorityPolicy('lab', [
            'capabilities:enable' => ['scopes' => ['capabilities:write'], 'authority' => Authority::WriteAsUser],
        ]);
        $techo = $this->instala();
        $sujeto = $this->sujeto(policy: $policy);

        self::assertSame(Authority::Privileged, $techo->forCall(['dry_run' => true], $sujeto)->authority);
    }

    /** 7 · F-4 · editing one rule re-versions the policy: the receipt's digest stops matching. */
    public function testEditingARuleChangesThePolicyDigest(): void
    {
        $antes = $this->policy();
        $despues = new DeclaredAuthorityPolicy('lab', [
            'capabilities:enable' => ['scopes' => ['capabilities:write', 'nuevo:scope'], 'authority' => Authority::Read],
        ]);

        self::assertNotSame($antes->digest(), $despues->digest());
    }

    /** 8 · the claim is a RECEIPT with provenance: policy id, digest, and the facts' fingerprint. */
    public function testTheClaimCarriesItsProvenance(): void
    {
        $hechos = new ContextFacts(principal: 'rod', verified: true, scopes: ['capabilities:write']);
        $juicio = $this->policy()->judge($hechos, new CallSubject('capabilities:enable', self::DIGEST));

        self::assertNotNull($juicio);
        self::assertSame(Authority::Read, $juicio->authority);
        self::assertSame('lab', $juicio->policyId);
        self::assertSame($this->policy()->digest(), $juicio->policyDigest);
        self::assertSame($hechos->fingerprint(), $juicio->factsFingerprint);
    }

    /** 9 · a mixed descent needs BOTH producers: the certificate for its axes, the policy for authority. */
    public function testAMixedDescentNeedsBothProducers(): void
    {
        $techo = $this->instala();

        // Certificate alone (no policy): authority stays up, so the descent cannot land whole.
        self::assertSame(Authority::Privileged, $techo->forCall(['dry_run' => true], new CallSubject('capabilities:enable', self::DIGEST))->authority);
        // Both: the whole destination lands.
        self::assertSame(Subject::None, $techo->forCall(['dry_run' => true], $this->sujeto())->subject);
    }

    private const DIGEST = 'sha256:the-handler-the-verifier-watched';

    private function policy(): DeclaredAuthorityPolicy
    {
        return new DeclaredAuthorityPolicy('lab', [
            'capabilities:enable' => ['scopes' => ['capabilities:write'], 'authority' => Authority::Read],
        ]);
    }

    private function sujeto(?DeclaredAuthorityPolicy $policy = null, ?ContextFacts $facts = null): CallSubject
    {
        return new CallSubject(
            'capabilities:enable',
            self::DIGEST,
            policy: $policy ?? $this->policy(),
            facts: $facts ?? new ContextFacts(principal: 'rod', verified: true, scopes: ['capabilities:write']),
        );
    }

    private function destino(): EffectProfile
    {
        return new EffectProfile(
            mutation: Mutation::None,
            externality: Externality::None,
            reversibility: Reversibility::Guaranteed,
            authority: Authority::Read,
            subject: Subject::None,
            rollbackContract: 'nothing ran, so there is nothing to undo',
        );
    }

    /** An operation lowering every axis — authority included, which is the one under test. */
    private function instala(): EffectProfile
    {
        // The certificate deliberately claims «authority» in covers: case 2 proves the observer's
        // over-claim is ignored for that axis rather than honoured (F-3 of decisions/0053).
        $certificado = (new DescentCertificate(
            verifier: 'verify-descent/2026-08-18',
            operation: 'capabilities:enable',
            predicate: ['dry_run' => true],
            covers: ['mutation', 'externality', 'reversibility', 'authority', 'subject'],
            to: $this->destino(),
            handlerSha256: self::DIGEST,
            verifierPublicKey: $this->publica,
        ))->signedWith($this->privada);

        return new EffectProfile(
            mutation: Mutation::Persistent,
            externality: Externality::ThirdParty,
            reversibility: Reversibility::Compensatable,
            authority: Authority::Privileged,
            subject: Subject::Executable,
            descents: [new Descent(
                argument: 'dry_run',
                whenValue: true,
                to: $this->destino(),
                because: 'the handler prints the command it would run and returns',
                certificate: $certificado,
            )],
        );
    }
}
