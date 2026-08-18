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
 * The battery greenhouse decisions/0051 froze — the signature, and only the signature.
 *
 * `evidence/0249` measured what an unsigned certificate costs: the artifact was deleted, rewritten by
 * hand with a digest computed by `sed | sha256sum`, and the ceiling came down. No verifier had run.
 * `evidence/0250` then made the verifier produce its own `covers`, which stopped the author from
 * WIDENING what is certified — but the file stayed editable, so a hand-written certificate still
 * bought the descent.
 *
 * WHAT THE SIGNATURE ADDS IS PROVENANCE, NOT TRUTH. It answers «did this exact payload come from the
 * certifier», never «is the descent real». The criterion already lives in the verifier, and it stays
 * there: a signed certificate that covers only `mutation` still cannot lower `authority`.
 *
 * THE PUBLIC KEY NEVER COMES FROM THE ARTIFACT. It is declared where the operation is declared —
 * reviewed code — because a key read from the same file as the signature would let a forger swap
 * both and sign their own lie.
 */
final class SignedCertificateTest extends TestCase
{
    private string $publica = '';

    private string $privada = '';

    protected function setUp(): void
    {
        $par = sodium_crypto_sign_keypair();
        $this->publica = base64_encode(sodium_crypto_sign_publickey($par));
        $this->privada = sodium_crypto_sign_secretkey($par);
    }

    /** 1 · CONTROL POSITIVO · a certificate signed by its verifier lowers what it covers — F-6 of decisions/0045. */
    public function testASignedCertificateStillLowersWhatItCovers(): void
    {
        $techo = $this->instala($this->certificado());

        self::assertSame(Subject::None, $techo->forCall(['dry_run' => true], $this->sujeto())->subject);
    }

    /** 2 · THE CELL evidence/0249 MEASURED: a hand-written certificate, no signature at all. */
    public function testAnUnsignedCertificateBuysNothing(): void
    {
        $techo = $this->instala($this->certificado(firmar: false));

        self::assertSame(Subject::Executable, $techo->forCall(['dry_run' => true], $this->sujeto())->subject);
    }

    /** 3 · the payload is untouched and the signature is not — someone else's signature does not travel. */
    public function testAnAlteredSignatureBuysNothing(): void
    {
        $legitimo = $this->certificado();
        $roto = new DescentCertificate(
            verifier: $legitimo->verifier,
            operation: $legitimo->operation,
            predicate: $legitimo->predicate,
            covers: $legitimo->covers,
            to: $legitimo->to,
            handlerSha256: $legitimo->handlerSha256,
            envelope: $legitimo->envelope,
            verdict: $legitimo->verdict,
            signature: base64_encode(str_repeat('x', 64)),
            verifierPublicKey: $this->publica,
        );

        self::assertSame(Subject::Executable, $this->instala($roto)->forCall(['dry_run' => true], $this->sujeto())->subject);
    }

    /**
     * 4 · THE FORGERY, exactly as `forge-certificate.sh` performs it: the signature is kept and one
     * field of the payload is edited. This is the cell the whole slice exists to flip.
     */
    public function testEditingOneFieldOfASignedPayloadBuysNothing(): void
    {
        $legitimo = $this->certificado(cubre: ['mutation']);
        $ensanchado = new DescentCertificate(
            verifier: $legitimo->verifier,
            operation: $legitimo->operation,
            predicate: $legitimo->predicate,
            // The author widens what was certified, and keeps the verifier's signature.
            covers: ['mutation', 'externality', 'reversibility', 'authority', 'subject'],
            to: $legitimo->to,
            handlerSha256: $legitimo->handlerSha256,
            envelope: $legitimo->envelope,
            verdict: $legitimo->verdict,
            signature: $legitimo->signature,
            verifierPublicKey: $this->publica,
        );

        self::assertSame(Subject::Executable, $this->instala($ensanchado)->forCall(['dry_run' => true], $this->sujeto())->subject);
    }

    /** 5 · signed by SOMEONE, but not by the verifier this deployment recognises. */
    public function testACertificateSignedByAnotherKeyBuysNothing(): void
    {
        $otro = sodium_crypto_sign_keypair();
        $ajeno = $this->certificado(conPrivada: sodium_crypto_sign_secretkey($otro));

        self::assertSame(Subject::Executable, $this->instala($ajeno)->forCall(['dry_run' => true], $this->sujeto())->subject);
    }

    /** 6 · a legitimate certificate copied onto ANOTHER operation — greenhouse evidence/0248 found this collision. */
    public function testALegitimateCertificateDoesNotTravelToAnotherOperation(): void
    {
        $techo = $this->instala($this->certificado());

        self::assertSame(
            Subject::Executable,
            $techo->forCall(['dry_run' => true], new CallSubject('otra:operacion', self::DIGEST))->subject,
        );
    }

    /**
     * 7 · signed, current, and honest about covering only `mutation` — while the descent lowers more.
     *
     * The signature adds no criterion. It says who minted the modesty, and the modesty still binds.
     */
    public function testASignedCertificateCannotLowerAnAxisItDoesNotCover(): void
    {
        $techo = $this->instala($this->certificado(cubre: ['mutation']));

        self::assertSame(Subject::Executable, $techo->forCall(['dry_run' => true], $this->sujeto())->subject);
    }

    /**
     * 8 · a signature that is not even the right SHAPE — base64 garbage, a key of the wrong length.
     *
     * Malformed is not «maybe»: sodium would throw on a 3-byte key, and a certificate that can crash
     * the gate is a certificate that decides who is allowed to run by breaking.
     */
    public function testAMalformedSignatureOrKeyBuysNothing(): void
    {
        foreach ([
            ['firma' => 'no es base64 válido !!!', 'llave' => $this->publica],
            ['firma' => base64_encode('corta'), 'llave' => $this->publica],
            ['firma' => base64_encode(str_repeat('x', \SODIUM_CRYPTO_SIGN_BYTES)), 'llave' => base64_encode('llave corta')],
        ] as $caso) {
            $legitimo = $this->certificado();
            $roto = new DescentCertificate(
                verifier: $legitimo->verifier,
                operation: $legitimo->operation,
                predicate: $legitimo->predicate,
                covers: $legitimo->covers,
                to: $legitimo->to,
                handlerSha256: $legitimo->handlerSha256,
                envelope: $legitimo->envelope,
                verdict: $legitimo->verdict,
                signature: $caso['firma'],
                verifierPublicKey: $caso['llave'],
            );

            self::assertFalse($roto->signedByItsVerifier(), 'una firma mal formada no es una duda: es un no');
        }
    }

    /** 9 · a nested envelope is ordered too, so «the same envelope» cannot be two different payloads. */
    public function testANestedEnvelopeIsCanonicalisedAsWell(): void
    {
        $uno = $this->conSobre(['filesystem' => ['scope' => 'app tree', 'blind' => 'outside it'], 'network' => 'differential']);
        $otro = $this->conSobre(['network' => 'differential', 'filesystem' => ['blind' => 'outside it', 'scope' => 'app tree']]);

        self::assertSame($uno->canonicalPayload(), $otro->canonicalPayload());
    }

    /** @param array<string, mixed> $sobre */
    private function conSobre(array $sobre): DescentCertificate
    {
        return new DescentCertificate(
            verifier: 'verify-descent/2026-08-18',
            operation: 'probe:enable',
            predicate: ['dry_run' => true],
            covers: ['mutation'],
            to: $this->destino(),
            handlerSha256: self::DIGEST,
            envelope: $sobre,
            verdict: 'CERTIFIED_WITHIN_ENVELOPE',
            verifierPublicKey: $this->publica,
        );
    }

    private const DIGEST = 'sha256:the-handler-the-verifier-watched';

    private function sujeto(): CallSubject
    {
        // The destination lowers authority too, and that axis is judged by its own producer now
        // (greenhouse decisions/0054) — the signature battery is about the certificate, so the
        // policy here is simply sufficient and constant.
        return new CallSubject(
            'probe:enable',
            self::DIGEST,
            policy: new DeclaredAuthorityPolicy('bateria', [
                'probe:enable' => ['scopes' => [], 'authority' => Authority::Read],
            ]),
            facts: new ContextFacts(principal: 'rod', verified: true),
        );
    }

    /** @param list<string> $cubre */
    private function certificado(array $cubre = ['mutation', 'externality', 'reversibility', 'authority', 'subject'], bool $firmar = true, ?string $conPrivada = null): DescentCertificate
    {
        $sinFirma = new DescentCertificate(
            verifier: 'verify-descent/2026-08-18',
            operation: 'probe:enable',
            predicate: ['dry_run' => true],
            covers: $cubre,
            to: $this->destino(),
            handlerSha256: self::DIGEST,
            envelope: ['filesystem' => 'app tree, blind outside it'],
            verdict: 'CERTIFIED_WITHIN_ENVELOPE',
            verifierPublicKey: $this->publica,
        );

        if (! $firmar) {
            return $sinFirma;
        }

        return $sinFirma->signedWith($conPrivada ?? $this->privada);
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

    private function instala(DescentCertificate $certificado): EffectProfile
    {
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
