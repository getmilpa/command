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
 * Lo que un {@see SurfaceProjector} produce: el modelo de UNA operación en UNA superficie.
 *
 * No es una representación física. Un modelo describe la interacción —qué flags expone, qué ruta
 * responde, qué herramienta se anuncia—; convertirla en ANSI, HTML o JSON-RPC es trabajo de un
 * renderer o de un materializador, nunca del projector. Es
 * [ADR-0035](https://github.com/getmilpa/governance) — *a projection is a value, not an effect*.
 *
 * ── POR QUÉ EL CONTRATO EXIGE `toArray()` ───────────────────────────────────────────────────────
 *
 * Porque la invariante que una interfaz no puede expresar se vuelve a violar. `SurfaceProjector`
 * declinaba nombrar su método de proyección —«es específico de cada superficie», decía su docblock— y
 * eso fue lo que autorizó que tres implementaciones ejecutaran, atendieran y mutaran un registry en
 * vez de devolver algo.
 *
 * Serializable no es un adorno: un modelo que lleva un closure no se compila, no se cachea, no se
 * manda por un socket y ningún renderer alternativo lo consume. Por eso los handlers viajan como
 * REFERENCIA —clase y método, no el callable resuelto— y resolverlos le toca al materializador. El
 * precedente ya está publicado en la familia: `HandlerReference` de `milpa/http` es *"serializable,
 * so route tables can be compiled and cached; a HandlerResolverInterface turns it into a live PSR-15
 * request handler."*
 *
 * ── INERTE ──────────────────────────────────────────────────────────────────────────────────────
 *
 * Un modelo no toca nada al construirse ni al leerse. Proyectar dos veces la misma operación tiene
 * que ser inofensivo; que hoy `McpProjector::project()` lance `ToolAlreadyRegisteredException` la
 * segunda vez es exactamente el síntoma que este contrato existe para quitar. Escribir es del
 * materializador, y ahí sí registrar dos veces debe fallar.
 */
interface SurfaceModel
{
    /** La superficie para la que se proyectó — coincide con el `surface()` de su projector. */
    public function surface(): string;

    /**
     * El modelo como datos planos: serializable, comparable y transportable.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
