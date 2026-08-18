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
use Milpa\Command\Effect\Descent;
use Milpa\Command\Effect\DescentCertificate;
use Milpa\Command\Effect\EffectProfile;
use Milpa\Command\Effect\Externality;
use Milpa\Command\Effect\Mutation;
use Milpa\Command\Effect\Reversibility;
use Milpa\Command\Effect\Subject;
use PHPUnit\Framework\TestCase;

/**
 * The battery greenhouse decisions/0029 froze before this class existed, extended by decisions/0050.
 *
 * The second case is the control and it is what makes the rest mean anything: WITHOUT the argument
 * the ceiling must stay where it was. A descent that applies either way is not lowering on demand,
 * it is a lighter ceiling declared through a longer sentence.
 *
 * Cases 6 to 10 are the battery 0050 froze. Case 1 is now their positive control and carries the
 * weight of `F-6` of decisions/0045: if a certified, honest descent still paid the full ceiling, the
 * mechanism would have cost something and bought nothing.
 */
final class DescentTest extends TestCase
{
    /** The digest the caller offers for the handler that is about to run. */
    private const DIGEST = 'sha256:the-handler-the-verifier-watched';

    /** The operation this whole battery is about. */
    private const OPERACION = 'capabilities:enable';

    private string $publica = '';

    private string $privada = '';

    protected function setUp(): void
    {
        $par = sodium_crypto_sign_keypair();
        $this->publica = base64_encode(sodium_crypto_sign_publickey($par));
        $this->privada = sodium_crypto_sign_secretkey($par);
    }

    /** What the call is about to run — the operation and the handler, which travel together. */
    private function sujeto(string $operacion = self::OPERACION, ?string $digest = self::DIGEST): CallSubject
    {
        return new CallSubject($operacion, $digest);
    }


    /** 1 · with the argument AND a certificate that covers it, the declared destination is what the call carries. */
    public function testTheDeclaredArgumentLowersTheCeilingForThatCall(): void
    {
        $techo = $this->instala();

        self::assertSame(Subject::None, $techo->forCall(['dry_run' => true], $this->sujeto())->subject);
        self::assertSame(Mutation::None, $techo->forCall(['dry_run' => true], $this->sujeto())->mutation);
    }

    /** 2 · THE CONTROL: without it, nothing moves. */
    public function testWithoutTheArgumentTheCeilingStaysWhereItWas(): void
    {
        $techo = $this->instala();

        self::assertSame(Subject::Executable, $techo->forCall([], $this->sujeto())->subject);
        self::assertSame(Subject::Executable, $techo->forCall(['other' => true], $this->sujeto())->subject);
    }

    /** 3 · a descent with no reason lowers nothing — failing upwards is the only affordable failure. */
    public function testADescentWithoutAReasonDoesNotLower(): void
    {
        $techo = $this->instala(razon: '   ');

        self::assertSame(Subject::Executable, $techo->forCall(['dry_run' => true], $this->sujeto())->subject);
    }

    /** 4 · a «descent» to a HIGHER ceiling is not a back door for climbing quietly. */
    public function testADescentThatRaisesAnythingIsIgnored(): void
    {
        $suave = new EffectProfile(
            mutation: Mutation::None,
            externality: Externality::None,
            reversibility: Reversibility::Guaranteed,
            authority: Authority::Read,
            subject: Subject::None,
            rollbackContract: 'reads only',
            descents: [new Descent('escalate', true, new EffectProfile(
                mutation: Mutation::Persistent,
                externality: Externality::ThirdParty,
                reversibility: Reversibility::Irreversible,
                authority: Authority::Privileged,
                subject: Subject::Executable,
            ), 'claims to lower while raising every axis')],
        );

        self::assertSame(Subject::None, $suave->forCall(['escalate' => true])->subject);
        self::assertSame(Authority::Read, $suave->forCall(['escalate' => true])->authority);
    }

    /** 5 · the same argument carrying another value does not trigger it. */
    public function testAnotherValueDoesNotTriggerTheDescent(): void
    {
        $techo = $this->instala();

        self::assertSame(Subject::Executable, $techo->forCall(['dry_run' => false], $this->sujeto())->subject);
        self::assertSame(Subject::Executable, $techo->forCall(['dry_run' => 'yes'], $this->sujeto())->subject);
    }

    /**
     * 6 · WITHOUT A CERTIFICATE NOTHING COMES DOWN — `F-1` of greenhouse decisions/0045.
     *
     * This is the whole point of decisions/0050. Before it, a non-empty `because` was the entire
     * mechanism: whoever declared a descent was believed, and lying bought an exemption instead of
     * costing one.
     */
    public function testADeclarationWithoutACertificateLowersNothing(): void
    {
        $techo = $this->instala(certificado: null);

        self::assertSame(Subject::Executable, $techo->forCall(['dry_run' => true], $this->sujeto())->subject);
        self::assertSame(Mutation::Persistent, $techo->forCall(['dry_run' => true], $this->sujeto())->mutation);
    }

    /** 7 · a certificate earned by OTHER arguments is a borrowed one — `F-2` of decisions/0045. */
    public function testACertificateForAnotherPredicateDoesNotTravel(): void
    {
        $techo = $this->instala(certificado: $this->certificado(predicado: ['dry_run' => 'yes']));

        self::assertSame(Subject::Executable, $techo->forCall(['dry_run' => true], $this->sujeto())->subject);
    }

    /** 8 · the handler moved, so what the verifier watched is not what will run — `F-3` of decisions/0045. */
    public function testACertificateForAnotherHandlerIsStale(): void
    {
        $techo = $this->instala(certificado: $this->certificado(handler: 'sha256:something-else'));

        self::assertSame(Subject::Executable, $techo->forCall(['dry_run' => true], $this->sujeto())->subject);
    }

    /**
     * 9 · THE ONE evidence/0245 PAID FOR: an honest disk certificate presented as proof about the network.
     *
     * The descent lowers `externality` too, and nothing demonstrated that. A claim wider than its
     * proof buys nothing — which is why the certificate carries its envelope at all (decisions/0046).
     */
    public function testACertificateThatDoesNotCoverEveryLoweredAxisBuysNothing(): void
    {
        $techo = $this->instala(certificado: $this->certificado(cubre: ['mutation', 'subject']));

        self::assertSame(Subject::Executable, $techo->forCall(['dry_run' => true], $this->sujeto())->subject);
    }

    /** 10 · the certificate justified one destination and the descent declares another. */
    public function testACertificateForAnotherDestinationDoesNotJustifyThisOne(): void
    {
        $techo = $this->instala(certificado: $this->certificado(hacia: new EffectProfile(
            mutation: Mutation::Ephemeral,
            externality: Externality::None,
            reversibility: Reversibility::Guaranteed,
            authority: Authority::Read,
            subject: Subject::None,
            rollbackContract: 'a different destination than the one this descent declares',
        )));

        self::assertSame(Subject::Executable, $techo->forCall(['dry_run' => true], $this->sujeto())->subject);
    }

    /** 11 · the caller cannot say which handler is about to run, so nothing can be checked, so nothing descends. */
    public function testWithoutAHandlerDigestToCompareNothingDescends(): void
    {
        $techo = $this->instala();

        self::assertSame(Subject::Executable, $techo->forCall(['dry_run' => true], $this->sujeto(digest: null))->subject);
    }

    /** The shape of the operation that forced this: installs code, unless it is only rehearsing. */
    private function instala(
        string $razon = 'the handler prints the command it would run and returns before running it',
        DescentCertificate|string|null $certificado = 'el que este descenso se ganó',
    ): EffectProfile {
        // `null` significa «ningún certificado» y es un caso de la batería, así que la omisión
        // necesita un centinela propio en vez de reusarlo.
        if (\is_string($certificado)) {
            $certificado = $this->certificado();
        }

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
                because: $razon,
                certificate: $certificado,
            )],
        );
    }

    /**
     * The certificate the lab emits (greenhouse evidence/0245), narrowed to what a runtime can check
     * on its own: which predicate was exercised, which handler was watched, which axes a control
     * actually demonstrated, and which destination that bought.
     *
     * @param array<string, mixed> $predicado
     * @param list<string>         $cubre
     */
    private function certificado(
        array $predicado = ['dry_run' => true],
        array $cubre = ['mutation', 'externality', 'reversibility', 'authority', 'subject'],
        ?EffectProfile $hacia = null,
        ?string $handler = self::DIGEST,
    ): DescentCertificate {
        return (new DescentCertificate(
            verifier: 'verify-descent+observe-network/2026-08-18',
            operation: self::OPERACION,
            predicate: $predicado,
            covers: $cubre,
            to: $hacia ?? $this->destino(),
            handlerSha256: $handler,
            verifierPublicKey: $this->publica,
        ))->signedWith($this->privada);
    }

    /** Where this descent lands, in full — the same object the descent declares. */
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
}
