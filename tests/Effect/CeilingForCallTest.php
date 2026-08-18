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
use Milpa\Command\Effect\Descent;
use Milpa\Command\Effect\DescentCertificate;
use Milpa\Command\Effect\EffectProfile;
use Milpa\Command\Effect\Externality;
use Milpa\Command\Effect\Mutation;
use Milpa\Command\Effect\Reversibility;
use Milpa\Command\Effect\Subject;
use Milpa\Command\Operation;
use PHPUnit\Framework\TestCase;

/**
 * The operation offers the digest of the handler that is about to run — greenhouse decisions/0050.
 *
 * Without this, every caller would have to compute it, and a caller that got it wrong would either
 * hand out descents it should not or refuse ones it should honour. The operation is the only place
 * that holds both the handler and its declared effects, so it is the only honest place to join them.
 */
final class CeilingForCallTest extends TestCase
{
    /** 1 · a certificate earned watching THIS handler brings the ceiling down. */
    public function testACertificateBoundToThisHandlerLowersTheCeiling(): void
    {
        $operacion = $this->operacion(static fn (): string => 'the rehearsal prints and returns');

        self::assertSame(Subject::None, $operacion->ceilingForCall(['dry_run' => true])->subject);
    }

    /**
     * 2 · THE CONTROL, and it is what makes case 1 mean anything: the certificate of the handler the
     * verifier watched, presented for a handler it never saw.
     *
     * If the digest did not follow the body, this would lower the ceiling on the strength of evidence
     * about other code — which is the whole failure decisions/0045 exists to make impossible.
     */
    public function testTheSameCertificateDoesNotTravelToAnotherHandler(): void
    {
        $vigilado = static fn (): string => 'the rehearsal prints and returns';
        $otro = static fn (): string => 'the rehearsal installs for real';

        $operacion = $this->operacion($otro, vigilado: $vigilado);

        self::assertSame(Subject::Executable, $operacion->ceilingForCall(['dry_run' => true])->subject);
    }

    /** 3 · without the argument nothing moves, certificate or not. */
    public function testWithoutTheArgumentTheCeilingStaysWhereItWas(): void
    {
        $operacion = $this->operacion(static fn (): string => 'the rehearsal prints and returns');

        self::assertSame(Subject::Executable, $operacion->ceilingForCall([])->subject);
    }

    /** 4 · the digest is stable: asking twice about the same handler answers the same thing. */
    public function testTheDigestIsStableForTheSameHandler(): void
    {
        $operacion = $this->operacion(static fn (): string => 'the rehearsal prints and returns');

        self::assertSame($operacion->handlerDigest(), $operacion->handlerDigest());
        self::assertIsString($operacion->handlerDigest());
    }

    /**
     * 5 · a handler nobody can reflect on cannot be compared, so it buys no descent.
     *
     * `null` is not the benefit of the doubt here: not being able to look is not the same as having
     * looked and found nothing — the pattern this house has spent six measurements on.
     */
    public function testAHandlerThatCannotBeReadBuysNoDescent(): void
    {
        $noInvocable = $this->operacion(static fn (): string => 'watched');
        $roto = new Operation(
            name: $noInvocable->name,
            description: $noInvocable->description,
            handler: 'this string is not callable',
            effects: $noInvocable->effects,
        );

        self::assertNull($roto->handlerDigest());
        self::assertSame(Subject::Executable, $roto->ceilingForCall(['dry_run' => true])->subject);
    }

    /** 6 · an internal function has no file to hash, and a digest nobody can compute is not one. */
    public function testAnInternalFunctionHasNoDigest(): void
    {
        $vigilado = $this->operacion(static fn (): string => 'watched');
        $interna = new Operation(
            name: $vigilado->name,
            description: $vigilado->description,
            handler: 'strlen',
            effects: $vigilado->effects,
        );

        self::assertNull($interna->handlerDigest());
        self::assertSame(Subject::Executable, $interna->ceilingForCall(['dry_run' => true])->subject);
    }

    /** 7 · a certificate that never named a handler names nothing to compare against. */
    public function testACertificateWithoutAHandlerNamesNothingToCompare(): void
    {
        $certificado = new DescentCertificate(
            verifier: 'verify-descent/2026-08-18',
            predicate: ['dry_run' => true],
            covers: ['mutation'],
            to: new EffectProfile(
                mutation: Mutation::None,
                externality: Externality::None,
                reversibility: Reversibility::Guaranteed,
                authority: Authority::Read,
                subject: Subject::None,
                rollbackContract: 'nothing ran',
            ),
        );

        self::assertFalse($certificado->watched('sha256:whatever-the-caller-offers'));
    }

    /** An operation that installs, unless it is only rehearsing — the shape that forced all of this. */
    private function operacion(callable $handler, ?callable $vigilado = null): Operation
    {
        $destino = new EffectProfile(
            mutation: Mutation::None,
            externality: Externality::None,
            reversibility: Reversibility::Guaranteed,
            authority: Authority::Read,
            subject: Subject::None,
            rollbackContract: 'nothing ran, so there is nothing to undo',
        );

        // The certificate is earned watching `$vigilado` — the same handler by default, another one
        // in case 2, which is how that control presents evidence about code that will not run.
        $observado = new Operation(
            name: 'capabilities:enable',
            description: 'installs a capability',
            handler: $vigilado ?? $handler,
        );

        return new Operation(
            name: 'capabilities:enable',
            description: 'installs a capability',
            handler: $handler,
            effects: new EffectProfile(
                mutation: Mutation::Persistent,
                externality: Externality::ThirdParty,
                reversibility: Reversibility::Compensatable,
                authority: Authority::Privileged,
                subject: Subject::Executable,
                descents: [new Descent(
                    argument: 'dry_run',
                    whenValue: true,
                    to: $destino,
                    because: 'the handler prints the command it would run and returns before running it',
                    certificate: new DescentCertificate(
                        verifier: 'verify-descent+observe-network/2026-08-18',
                        predicate: ['dry_run' => true],
                        covers: ['mutation', 'externality', 'reversibility', 'authority', 'subject'],
                        to: $destino,
                        handlerSha256: $observado->handlerDigest(),
                    ),
                )],
            ),
        );
    }
}
