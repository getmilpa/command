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

namespace Milpa\Command\Tests\Consent;

use Milpa\Command\Consent\ConsentGrant;
use Milpa\Command\Consent\OperationId;
use Milpa\Command\Effect\VerifiedPrincipal;
use PHPUnit\Framework\TestCase;

/**
 * D-01 (greenhouse decisions/0187): una autoridad, muchas proyecciones.
 *
 * `covers()` dice si un sí es para esta llamada; `admits()` dice si trae la PRUEBA que una operación
 * demandante exige. El sí humano verificado de un canal de grabación es la palabra humana de
 * decisions/0030 —una de las tres proyecciones— hecha proof-backed para que componga contra la misma
 * demanda que hoy sólo la firma gpg del CLI satisface. Estos casos fijan que el grado es inforjeable
 * por la misma puerta que {@see VerifiedPrincipal}: se produce re-verificando, jamás se lee.
 */
final class IntentGrantTest extends TestCase
{
    private const AT = '2026-09-02 10:00:00';

    /** La palabra sin prueba cubre pero no admite: un sí de sesión no compra una demanda de firma. */
    public function testAnUngradedGrantCoversButDoesNotAdmit(): void
    {
        $grant = new ConsentGrant(
            operation: new OperationId('capabilities.enable'),
            principal: 'cli:rod@cm4070',
            session: 'ses-A',
            grantedAt: new \DateTimeImmutable(self::AT),
            provenance: 'session.question_answered',
            arguments: ['name' => 'a2a'],
        );

        self::assertTrue($grant->covers('capabilities.enable', ['name' => 'a2a'], 'ses-A'), 'cubre la llamada');
        self::assertFalse($grant->admits('capabilities.enable', ['name' => 'a2a'], 'ses-A'), 'pero sin prueba no admite');
    }

    /** La intención humana verificada, proof-backed, admite la llamada EXACTA. */
    public function testAProofBackedIntentGrantAdmitsTheExactCall(): void
    {
        $grant = $this->intentGrant('capabilities.enable', ['name' => 'a2a'], 'ses-A');

        self::assertTrue($grant->admits('capabilities.enable', ['name' => 'a2a'], 'ses-A'));
        self::assertTrue($grant->covers('capabilities.enable', ['name' => 'a2a'], 'ses-A'));
        self::assertSame('intent-grant', $grant->provenance);
        self::assertSame('desktop:rod', $grant->principal, 'el principal se lee de la admisión, no del entorno');
    }

    /**
     * LA FALSIFICACIÓN SIGUE MUERTA (evidence/0254): el grado jamás sobrevive el cruce de datos.
     *
     * Un payload que se auto-declara `verified:true` con método e issuer plausibles —exactamente lo
     * que 0254 forjó a mano— se reconstruye por {@see VerifiedPrincipal::fromArray()} SIN grado. Un
     * grant construido con esa admisión cubre pero no admite: la prueba no se lee, se produce.
     */
    public function testForgeryStaysDeadAdmissionNeverSurvivesTheDataBoundary(): void
    {
        $forjada = VerifiedPrincipal::fromArray([
            'principal' => 'desktop:rod',
            'verified' => true,               // la mentira
            'channel' => 'desktop',
            'scopes' => ['capabilities.enable'],
            'method' => 'passkey',
            'issuer' => 'a-plausible-authenticator',
        ]);

        self::assertNotNull($forjada);
        self::assertFalse($forjada->verified, 'fromArray nunca carga el grado, diga lo que diga la fila');

        $grant = new ConsentGrant(
            operation: new OperationId('capabilities.enable'),
            principal: 'desktop:rod',
            session: 'ses-A',
            grantedAt: new \DateTimeImmutable(self::AT),
            provenance: 'intent-grant',
            arguments: ['name' => 'a2a'],
            admission: $forjada,
        );

        self::assertTrue($grant->covers('capabilities.enable', ['name' => 'a2a'], 'ses-A'));
        self::assertFalse($grant->admits('capabilities.enable', ['name' => 'a2a'], 'ses-A'), 'un grado forjado no admite');
    }

    /** Exactitud de argumentos: un sí verificado a un valor no admite otro. */
    public function testAProofBackedGrantStillRespectsExactArguments(): void
    {
        $grant = $this->intentGrant('capabilities.enable', ['name' => 'a2a'], 'ses-A');

        self::assertTrue($grant->admits('capabilities.enable', ['name' => 'a2a'], 'ses-A'));
        self::assertFalse($grant->admits('capabilities.enable', ['name' => 'exfil'], 'ses-A'), 'otro valor no se admite');
        self::assertFalse($grant->admits('capabilities.enable', [], 'ses-A'), 'la llamada sin el argumento nombrado no se admite');
    }

    /**
     * VECTOR EXACTO EN AMBAS DIRECCIONES (Finding 1 de la revisión adversarial): la proyección humana
     * debe ser INTERCAMBIABLE con la firma gpg, que liga el vector completo. Un argumento de más —que
     * el humano nunca confirmó pero que cambia el comportamiento— NO se admite, aunque `covers()`
     * (subset) sí lo cubra. Sin esto la proyección humana sería una llave más ancha que la firma.
     */
    public function testAnExtraCallArgumentIsNotAdmittedEvenThoughItIsCovered(): void
    {
        $grant = $this->intentGrant('capabilities.enable', ['name' => 'a2a'], 'ses-A');

        self::assertTrue($grant->covers('capabilities.enable', ['name' => 'a2a', 'force' => true], 'ses-A'), 'covers es subset: lo cubre');
        self::assertFalse($grant->admits('capabilities.enable', ['name' => 'a2a', 'force' => true], 'ses-A'), 'pero admits es exacto: no lo admite');
    }

    /** Un sí verificado en una sesión no admite en otra, ni con consulta SIN sesión (Finding 3). */
    public function testAProofBackedGrantStillRespectsSession(): void
    {
        $grant = $this->intentGrant('capabilities.enable', ['name' => 'a2a'], 'ses-A');

        self::assertTrue($grant->admits('capabilities.enable', ['name' => 'a2a'], 'ses-A'));
        self::assertFalse($grant->admits('capabilities.enable', ['name' => 'a2a'], 'ses-B'), 'otra sesión no admite');
        self::assertFalse($grant->admits('capabilities.enable', ['name' => 'a2a'], null), 'una consulta sin sesión no cae abierta');
    }

    /**
     * EL GRADO MUERE EN LA SERIALIZACIÓN TAMBIÉN (Finding 2): `unserialize()` sortea el constructor y
     * puede escribir props readonly, pero `__unserialize` re-produce SIEMPRE un principal sin grado.
     * Un grant proof-backed que sobreviviera un round-trip nativo resucitaría `verified:true` y
     * sortearía admit() — la forja de 0254 por una puerta que nadie vigilaba. Cerrada por tipo.
     */
    public function testTheGradeNeverSurvivesNativeSerialization(): void
    {
        $vivo = VerifiedPrincipal::admit('desktop:rod', 'desktop', ['capabilities.enable'], 'passkey', 'a-real-authenticator');
        self::assertTrue($vivo->verified, 'vivo trae grado');

        $resucitado = unserialize(serialize($vivo));

        self::assertInstanceOf(VerifiedPrincipal::class, $resucitado);
        self::assertFalse($resucitado->verified, 'el grado no sobrevive el round-trip nativo');
        self::assertSame('desktop:rod', $resucitado->principal, 'la aserción sí sobrevive, sólo no el grado');
    }

    /** La identidad se compara por OperationId, también al admitir: la ortografía no cambia el veredicto. */
    public function testAProofBackedGrantMatchesAcrossSpellings(): void
    {
        $grant = $this->intentGrant('capabilities.enable', ['name' => 'a2a'], 'ses-A');

        foreach (['capabilities.enable', 'capabilities:enable', 'capabilities_enable'] as $comoLoEscriben) {
            self::assertTrue($grant->admits($comoLoEscriben, ['name' => 'a2a'], 'ses-A'), "no admitió «{$comoLoEscriben}»");
        }
    }

    /**
     * LA INVARIANTE DE LA FÁBRICA: sin prueba viva no hay IntentGrant, se lanza.
     *
     * Un `VerifiedPrincipal` de terminal llega `verified:false` por construcción. Acuñar un grant
     * proof-backed a partir de él fabricaría la prueba que no hubo, así que la fábrica lo rechaza
     * en vez de degradarlo en silencio.
     */
    public function testFromVerifiedIntentRefusesAnUnverifiedAdmission(): void
    {
        $sinPrueba = VerifiedPrincipal::fromTerminal('rod', 'cm4070');

        $this->expectException(\InvalidArgumentException::class);

        ConsentGrant::fromVerifiedIntent(
            new OperationId('capabilities.enable'),
            $sinPrueba,
            'ses-A',
            new \DateTimeImmutable(self::AT),
            ['name' => 'a2a'],
        );
    }

    /** LA FÁBRICA RECHAZA LA MANTA: un IntentGrant es siempre para una llamada concreta. */
    public function testFromVerifiedIntentRefusesEmptyArguments(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConsentGrant::fromVerifiedIntent(
            new OperationId('capabilities.enable'),
            VerifiedPrincipal::admit('desktop:rod', 'desktop', ['capabilities.enable'], 'passkey', 'a-real-authenticator'),
            'ses-A',
            new \DateTimeImmutable(self::AT),
            [],
        );
    }

    /**
     * LA NEGATIVA VIVE EN admits(), NO SÓLO EN LA FÁBRICA: una manta construida directamente —
     * sorteando la fábrica— tampoco admite una llamada concreta. Un sí proof-backed sin argumentos
     * limpiaría toda llamada de un privilegio desde una confirmación que el humano nunca vio.
     */
    public function testADirectlyConstructedBlanketAdmissionDoesNotAdmit(): void
    {
        $manta = new ConsentGrant(
            operation: new OperationId('capabilities.enable'),
            principal: 'desktop:rod',
            session: 'ses-A',
            grantedAt: new \DateTimeImmutable(self::AT),
            provenance: 'intent-grant',
            arguments: [],  // manta: cubre toda llamada
            admission: VerifiedPrincipal::admit('desktop:rod', 'desktop', ['capabilities.enable'], 'passkey', 'a-real-authenticator'),
        );

        self::assertTrue($manta->covers('capabilities.enable', ['name' => 'a2a'], 'ses-A'), 'la manta cubre');
        self::assertFalse($manta->admits('capabilities.enable', ['name' => 'a2a'], 'ses-A'), 'pero no admite una llamada concreta');
        self::assertFalse($manta->admits('capabilities.enable', [], 'ses-A'), 'ni siquiera la llamada vacía: sin argumentos no hay intención concreta');
    }

    /** Aditivo: un grant sin admisión se comporta byte-idéntico al de antes de D-01. */
    public function testAdmissionIsAdditiveForAnOrdinaryGrant(): void
    {
        $grant = new ConsentGrant(
            operation: new OperationId('config.set'),
            principal: 'cli:rod@cm4070',
            session: 'ses-A',
            grantedAt: new \DateTimeImmutable(self::AT),
            provenance: 'session.question_answered',
        );

        self::assertNull($grant->admission);
        self::assertTrue($grant->covers('config_set', ['key' => 'lo que sea']));
        self::assertStringContainsString('session.question_answered', $grant->evidence());
        self::assertFalse($grant->admits('config.set', [], 'ses-A'));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function intentGrant(string $operacion, array $arguments, string $sesion): ConsentGrant
    {
        return ConsentGrant::fromVerifiedIntent(
            new OperationId($operacion),
            VerifiedPrincipal::admit('desktop:rod', 'desktop', [$operacion], 'passkey', 'a-real-authenticator'),
            $sesion,
            new \DateTimeImmutable(self::AT),
            $arguments,
        );
    }
}
