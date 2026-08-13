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
     */
    public function __construct(
        public OperationId $operation,
        public ?string $principal,
        public ?string $session,
        public \DateTimeImmutable $grantedAt,
        public string $provenance,
        public array $arguments = [],
        public array $scope = [],
    ) {
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
