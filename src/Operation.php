<?php

/**
 * This file is part of Milpa Command — the Command-as-atom core of the Milpa PHP framework.
 *
 * (c) Rodrigo Vicente - TeamX Agency — https://teamx.agency <hola@teamx.agency>
 *
 * @license Apache-2.0
 *
 * @link    https://github.com/getmilpa/command
 */

declare(strict_types=1);

namespace Milpa\Command;

use Milpa\Command\Effect\EffectProfile;
use Milpa\Command\Effect\Mutation;

/**
 * The atom: one operation defined once — schema of inputs + handler + metadata — that the family
 * projects to N surfaces (CLI, MCP, HTTP, web, TUI). A `SurfaceProjector` turns this into each
 * surface's native shape; the operation is written once and surfaced wherever it is enabled.
 *
 * `readonly` (not `final`) so that the deprecated `Milpa\Runtime\CommandDefinition` can remain a
 * subclass during the transition — a readonly class may only be extended by a readonly class.
 */
readonly class Operation
{
    /**
     * @param callable|array{0: class-string, 1: string} $handler      A plain PHP callable, or a
     *                                                                 `[class-string, method]` pair a host resolves through DI. Typed
     *                                                                 `mixed` because PHP forbids the native `callable` type on a property.
     *                                                                 Called with the coerced `array<string,mixed> $input`; returns domain data.
     * @param array<string, mixed>|null                  $inputSchema  JSON-Schema-shaped input definition; null = no typed inputs.
     * @param list<string>                               $scopes       Auth scopes enforced by a policy gate on surfaces that have
     *                                                                 one wired — MCP via tool-runtime's PolicyGate, and HTTP via
     *                                                                 the HttpProjector's scope gate: a non-empty `$scopes` makes
     *                                                                 the projector attach a per-route RequireScope middleware and
     *                                                                 build the `ToolContext::web()` the same PolicyGate reads, so
     *                                                                 HTTP now enforces scopes instead of ignoring them.
     * @param array<string, mixed>|null                  $outputSchema JSON-Schema-shaped output definition.
     * @param string|null                                $path         HTTP path; declared here, or (null) derived from `$name`.
     * @param list<string>|null                          $surfaces     Surfaces this operation opts into; null = all.
     * @param string|null                                $permission   The semantic permission key (`{namespace}.{resource}:{action}`) a
     *                                                                 permission-aware surface enforces; mutually exclusive with `$scopes`.
     */
    public function __construct(
        public string $name,
        public string $description,
        public mixed $handler,
        public ?array $inputSchema = null,
        public bool $mutating = false,
        public bool $requiresConfirmation = false,
        public array $scopes = [],
        public ?array $outputSchema = null,
        public ?string $version = null,
        public ?string $path = null,
        public ?array $surfaces = null,
        public ?string $permission = null,
        /**
         * El argumento cuyo valor debe venir NOMBRADO por el humano en la petición, o `null` si esta
         * operación no lo exige.
         *
         * Es el contrato de intención de ADR-0044: la duda no la detecta un modelo, la declara la
         * operación. Q-P19-K midió el costo de que nadie la declarara — ante «quita el plugin viejo»,
         * tres corridas mataron un plugin, tres otro, diez ninguno, y ningún hecho dice por qué. Con
         * esto declarado, un objetivo que la petición no nombra no se ejecuta: se pregunta, con la
         * operación y los argumentos en la pregunta.
         *
         * Quién lo hace valer no es esta clase: es el piso de la sesión, que ya es la autoridad
         * no-persuadible y la única que ve los argumentos concretos. Declarar sin ese piso no hace
         * nada — a propósito: un contrato es una exigencia, no una conducta.
         */
        public ?string $namedTarget = null,
        /**
         * What this operation can do AT WORST — the ceiling, declared by the operation itself.
         *
         * `null` means unclassified, and unclassified is NOT «harmless». Under GOV-05 an operation
         * that never declared its effects carries the ceiling of every dimension: unknown mutation,
         * unknown externality, unknown reversibility, unknown authority. That is deliberately
         * unusable for anything sensitive, and the pressure it creates is the point — the way out is
         * to classify, never to invent a permissive default (GOV-13).
         *
         * It does not replace `$mutating`, which eight consumers across four packages already read.
         * It REFINES it: `mutating` says whether, this says what kind, how far it reaches, whether
         * it can be taken back, and whose authority it spends. The two cannot contradict each other,
         * because the contradiction is refused below at construction rather than resolved by
         * whichever consumer happens to read first.
         */
        public ?EffectProfile $effects = null,
    ) {
        // A SECOND SOURCE OF TRUTH IS REFUSED AT DECLARATION, not reconciled at read time.
        //
        // `mutating: true` with `Mutation::None` is not a state anyone should have to resolve later;
        // it is a statement that contradicts itself, and every consumer that reads one of the two
        // fields would get a different answer depending on which one it happens to read. This
        // repository has caught that exact shape four times in a week, always through the consumer
        // that read the stale one.
        if ($this->mutating && $this->effects?->mutation === Mutation::None) {
            throw new \InvalidArgumentException(
                "Operation '{$this->name}' declares mutating: true and an effect profile with "
                . 'mutation: none. One of the two is wrong, and no consumer can be asked to guess '
                . 'which — declare the mutation this operation actually performs.'
            );
        }

        if ($this->scopes !== [] && $this->permission !== null) {
            throw new \InvalidArgumentException(
                "Operation '{$this->name}' declares BOTH scopes and a permission. In this release an "
                . 'operation is typed by scope XOR permission — declare one, not both. Composition '
                . '(allOf/anyOf) is a deliberate future move, not an implicit "both must pass".'
            );
        }
    }

    /**
     * The effect ceiling of this operation — never `null`.
     *
     * An operation that declared nothing gets `unclassified()`, which is every dimension at its
     * maximum. Callers therefore never have to write `?? something-safe`, and cannot accidentally
     * write `?? readOnly()` — which is precisely the permissive default GOV-13 forbids and the shape
     * a tired reviewer would wave through.
     */
    public function effectCeiling(): EffectProfile
    {
        return $this->effects ?? EffectProfile::unclassified();
    }

    /**
     * Whether this operation is projected to the given surface. `null` $surfaces means every
     * surface; a list is an explicit opt-in.
     */
    public function supportsSurface(string $surface): bool
    {
        return $this->surfaces === null || \in_array($surface, $this->surfaces, true);
    }
}
