<?php

/**
 * This file is part of milpa/command — the atom of the Milpa PHP framework.
 *
 * (c) Rodrigo Vicente - TeamX Agency — https://teamx.agency <hola@teamx.agency>
 *
 * @license Apache-2.0
 *
 * @link    https://github.com/getmilpa/command
 */

declare(strict_types=1);

namespace Milpa\Command\Consent;

use Milpa\Command\Effect\VerifiedPrincipal;

/**
 * THE FACT: a principal answered a concrete question, for a concrete act, under a concrete context.
 *
 * Greenhouse decisions/0030, in Rod's words:
 *
 *     «El consentimiento ocurre una vez; todo lo demás debería ser transporte, evidencia o
 *     proyección de ese mismo acto.»
 *
 * That acta names what was found: three components can each recognise a different representation of
 * consent and none of them be its canonical authority. A session asked and recorded a permission, a
 * gate wanted a signature, an orchestrator wanted the word CONFIRMAR — each reasonable alone, none
 * aware of the other two, and a human who said yes watched nothing happen (evidence/0176).
 *
 * This is the fact they should all consume. A token, a signature and a human word remain what they
 * always were — MECHANISMS TO PRODUCE OR DEMONSTRATE this grant — and stop being parallel
 * authorities. `provenance` is where each one leaves its trace, so the grant can say HOW it was
 * earned without any consumer having to re-earn it.
 *
 * IT IS NOT A MASTER KEY, and the shape says so: it names one operation, one principal, one session,
 * and the arguments it was given for. Change a substantive dimension and it stops covering the call
 * — that is what separates a grant from an open door, and it is case 2 of the frozen battery.
 */
final readonly class ConsentGrant
{
    /**
     * @param array<string, mixed> $arguments los argumentos SOBRE LOS QUE se consintió; vacío
     *                                        significa «para esta operación en esta sesión», que es
     *                                        lo que una pregunta de sesión produce hoy
     * @param list<string>         $scope     permisos adicionales que el sí abarcó, si los hubo
     * @param ?VerifiedPrincipal   $admission LA PRUEBA VIVA detrás de este sí, o `null` cuando no
     *                                        hay ninguna. Un grant ordinario —un sí grabado y
     *                                        re-derivado del stream— llega SIN prueba: `null`, y ese
     *                                        es su estado honesto, porque un grado verificado que
     *                                        sobrevive al stream es exactamente la falsificación que
     *                                        greenhouse evidence/0254 tumbó. La prueba sólo existe
     *                                        cuando un canal la re-verificó EN VIVO ({@see
     *                                        VerifiedPrincipal::admit()}), y sólo entonces
     *                                        {@see admits()} deja pasar una demanda de firma. La
     *                                        firma gpg del CLI es UNA proyección de esta prueba; la
     *                                        respuesta humana verificada de un canal de grabación es
     *                                        otra — una autoridad, muchas proyecciones (0187 D-01).
     */
    public function __construct(
        public OperationId $operation,
        public ?string $principal,
        public ?string $session,
        public \DateTimeImmutable $grantedAt,
        public string $provenance,
        public array $arguments = [],
        public array $scope = [],
        public ?VerifiedPrincipal $admission = null,
    ) {
    }

    /**
     * El sí de una INTENCIÓN HUMANA VERIFICADA, acuñado como el hecho de consentimiento canónico.
     *
     * No es una autoridad paralela: es la palabra humana —una de las tres proyecciones que
     * decisions/0030 nombró (token, firma, palabra)— hecha proof-backed para que COMPONGA contra la
     * misma demanda que hoy sólo la firma gpg del CLI satisface. El humano dijo que sí a ESTA llamada,
     * un canal re-verificó quién lo dijo, y de ahí sale un grant que {@see admits()} reconoce.
     *
     * Dos invariantes lo mantienen honesto.
     *
     * PRIMERA: la fábrica RECHAZA una admisión sin grado. Un `VerifiedPrincipal` que no pasó por
     * {@see VerifiedPrincipal::admit()} llega `verified:false` —por construcción, no por olvido— y
     * acuñar un grant proof-backed a partir de él sería fabricar la prueba que no hubo. Sin grado
     * vivo no hay IntentGrant: se lanza, no se degrada en silencio.
     *
     * SEGUNDA: la fábrica RECHAZA una intención sin argumentos. `covers()` cubre TODA llamada de una
     * operación cuando el grant no nombra argumentos —el sí «para esta operación en esta sesión»— y
     * un sí así, proof-backed, limpiaría una demanda de firma de CUALQUIER llamada de un privilegio
     * a partir de UNA confirmación que el humano nunca vio caso por caso. Eso es la llave maestra que
     * el {@see ConsentGrant} existe para no ser. La misma regla que `grantFromIntentClaim` ya aplica a
     * la intención del modelo (greenhouse decisions/0184, «un claim nunca compra una manta») aplica,
     * con más razón, a la intención humana verificada: un IntentGrant es siempre para una llamada
     * CONCRETA. Una confirmación humana genuina siempre nombra qué se confirmó; una que no lo hace no
     * es la palabra humana de decisions/0030 sino un cheque en blanco.
     *
     * @param array<string, mixed> $arguments los argumentos EXACTOS a los que el humano dijo que sí
     */
    public static function fromVerifiedIntent(
        OperationId $operation,
        VerifiedPrincipal $admission,
        ?string $session,
        \DateTimeImmutable $grantedAt,
        array $arguments,
    ): self {
        if (! $admission->verified) {
            throw new \InvalidArgumentException(
                'Un IntentGrant sólo se acuña de una prueba VIVA: el VerifiedPrincipal llegó sin grado '
                . '(verified:false), así que no hay intención verificada que proyectar. Re-admite por su '
                . 'prueba con VerifiedPrincipal::admit() antes de acuñar — nunca desde un campo guardado.',
            );
        }

        if ($arguments === []) {
            throw new \InvalidArgumentException(
                'Un IntentGrant es siempre para una llamada CONCRETA: sin argumentos cubriría toda '
                . 'llamada de la operación, y una demanda de firma no se limpia con una manta. Nombra '
                . 'los argumentos EXACTOS que el humano confirmó.',
            );
        }

        return new self(
            operation: $operation,
            principal: $admission->principal,
            session: $session,
            grantedAt: $grantedAt,
            provenance: 'intent-grant',
            arguments: $arguments,
            admission: $admission,
        );
    }

    /**
     * ¿Este hecho cubre ESTA llamada?
     *
     * La identidad se compara por {@see OperationId}, nunca por cadena: una compuerta que compara
     * ortografías está comparando UI. Y los argumentos se comparan sólo cuando el grant los nombró
     * — un sí dado «para esta operación en esta sesión» no puede fingir que revisó argumentos que
     * nadie le enseñó, y decirlo es más honesto que aceptarlos en silencio.
     *
     * @param array<string, mixed> $argumentos
     */
    public function covers(OperationId|string $operacion, array $argumentos = [], ?string $sesion = null): bool
    {
        if (! $this->operation->is($operacion)) {
            return false;
        }

        // Un grant atado a una sesión no vale en otra. Sin esto sería una llave maestra con fecha.
        if ($this->session !== null && $sesion !== null && $this->session !== $sesion) {
            return false;
        }

        if ($this->arguments === []) {
            return true;
        }

        foreach ($this->arguments as $llave => $valor) {
            if (! \array_key_exists($llave, $argumentos) || $argumentos[$llave] !== $valor) {
                return false;
            }
        }

        return true;
    }

    /**
     * ¿Este grant ADMITE una demanda de firma sobre ESTA llamada? (0187 D-01)
     *
     * `covers()` responde «¿este sí es para esta llamada?». `admits()` responde una pregunta más
     * fuerte: «¿este sí trae la PRUEBA que una operación demandante exige?». La diferencia es el
     * hueco que D-01 cierra — hoy `Consent::demanded()` es una regla surface-agnóstica, pero
     * satisfacerla es surface-específico: el CLI la limpia con una firma gpg, y un sí humano grabado
     * por un canal de grabación —por verificado que estuviera en vivo— se re-deriva del stream como
     * grant SIN prueba y la ruta de firma jamás lo consulta. El humano autorizó la llamada exacta y
     * la vio pedir una firma que su canal no puede producir (evidence/0176, ahora en su forma
     * privilegiada).
     *
     * Admite cuando —y sólo cuando— cubre la llamada, NOMBRA sus argumentos, Y trae una admisión con
     * grado VIVO. Tres condiciones, y cada una tapa un hueco distinto:
     *
     * - GRADO VIVO, inforjeable por la misma puerta que {@see VerifiedPrincipal}: el grado se PRODUCE
     *   re-verificando una prueba, jamás se lee de un campo. Un grant reconstruido del stream trae
     *   `admission` en `null` o `verified:false` —{@see VerifiedPrincipal::fromArray()} nunca carga el
     *   grado— así que no admite: la falsificación de evidence/0254 sigue muerta, ahora también para
     *   la demanda de firma.
     * - NOMBRA SUS ARGUMENTOS Y SÓLO ESOS: `covers()` es subset —los argumentos NOMBRADOS presentes e
     *   iguales— y eso es correcto para un sí de sesión, pero una demanda de FIRMA la limpia hoy una
     *   firma gpg que liga el vector de argumentos COMPLETO («in full», {@see
     *   \Milpa\ToolRuntime\Identity\OperationAuthorization::canonical()}): agregar `force:true` cambia
     *   los bytes firmados y la firma falla. Para que las dos proyecciones sean INTERCAMBIABLES —el
     *   corazón de D-01— `admits()` exige coincidencia EXACTA en ambas direcciones: mismos argumentos,
     *   ni uno de más. Sin esto la proyección humana sería una llave MÁS ancha que la firma —un sí a
     *   `{name:a2a}` limpiaría `{name:a2a, force:true}` que el humano nunca vio— y «una autoridad,
     *   muchas proyecciones» sería mentira. Un grant sin argumentos, además, cubriría toda llamada
     *   (la manta que decisions/0184 prohíbe): negado aquí, no sólo en la fábrica.
     * - LIGADO A SU SESIÓN, EXACTA: `covers()` trata la sesión `null` como comodín, y para un sí de
     *   sesión eso pasa. Pero una autoridad que limpia un privilegio no puede caer abierta porque
     *   quien pregunta olvidó pasar la sesión: `admits()` exige que ambas sesiones existan y sean la
     *   misma. Un grant de otra sesión —o una consulta sin sesión— no admite.
     *
     * @param array<string, mixed> $argumentos
     */
    public function admits(OperationId|string $operacion, array $argumentos = [], ?string $sesion = null): bool
    {
        return $this->arguments !== []
            && $this->session !== null
            && $sesion !== null
            && $this->session === $sesion
            && \count($argumentos) === \count($this->arguments)
            && $this->covers($operacion, $argumentos, $sesion)
            && $this->admission !== null
            && $this->admission->verified;
    }

    /** Lo que un consumidor guarda para poder demostrar de dónde salió el sí. */
    public function evidence(): string
    {
        return sprintf(
            '%s por %s en %s (%s)',
            $this->operation->canonical,
            $this->principal ?? 'principal sin nombre',
            $this->session ?? 'sin sesión',
            $this->provenance,
        );
    }
}
