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

/**
 * The contract a surface projector implements — the seam that stabilizes the wave's later surfaces
 * (web SchemaForm, TUI, pluggable channels). A projector materializes an {@see Operation} into one
 * surface's native shape. Cada superficie produce un modelo distinto —flags, rutas, herramientas—
 * pero TODAS producen un modelo: eso es lo que fija {@see self::project()}, y lo que este contrato
 * antes declinaba fijar.
 */
interface SurfaceProjector
{
    /** The surface this projector targets, e.g. `cli`, `mcp`, `http`. */
    public function surface(): string;

    /** Whether the operation opts into this projector's surface. */
    public function supports(Operation $op): bool;

    /**
     * Proyecta la operación al modelo de esta superficie.
     *
     * Devuelve un valor y no toca nada: no ejecuta, no registra, no responde. Materializar ese
     * modelo —correrlo, montarlo en un registry, pintarlo— es de otra pieza, y esa separación es lo
     * que permite cambiar el renderer de una superficie sin tocar su projector.
     *
     * Este método NO existía, y su ausencia no era un olvido: el contrato decía que el método de
     * proyección era «específico de cada superficie» y por eso sólo fijaba `surface()` y
     * `supports()`. El resultado fue que las tres implementaciones hicieran cosas distintas e
     * incompatibles — una ejecutaba y devolvía un código de salida, otra atendía peticiones HTTP, la
     * tercera mutaba un registry y devolvía `void`. Nombrarlo es la cláusula 3 de ADR-0035.
     */
    public function project(Operation $op): SurfaceModel;
}
