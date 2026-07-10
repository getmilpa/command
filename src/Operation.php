<?php

/**
 * This file is part of Milpa Command — the Command-as-atom core of the Milpa PHP framework.
 *
 * (c) TeamX Agency — https://teamx.agency <hola@teamx.agency>
 *
 * @license Apache-2.0
 *
 * @link    https://github.com/getmilpa/command
 */

declare(strict_types=1);

namespace Milpa\Command;

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
     *                                                                 one wired — MCP today (via tool-runtime's PolicyGate); HTTP
     *                                                                 scope enforcement lands with per-route middleware in a later
     *                                                                 slice.
     * @param array<string, mixed>|null                  $outputSchema JSON-Schema-shaped output definition.
     * @param string|null                                $path         HTTP path; declared here, or (null) derived from `$name`.
     * @param list<string>|null                          $surfaces     Surfaces this operation opts into; null = all.
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
    ) {
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
