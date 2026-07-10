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
 * The contract a surface projector implements — the seam that stabilizes the wave's later surfaces
 * (web SchemaForm, TUI, pluggable channels). A projector materializes an {@see Operation} into one
 * surface's native shape; the projection method itself is surface-specific (a CLI projector derives
 * flags, an MCP projector registers a tool, an HTTP projector synthesizes routes), so this contract
 * only fixes the surface tag and the per-operation opt-out check.
 */
interface SurfaceProjector
{
    /** The surface this projector targets, e.g. `cli`, `mcp`, `http`. */
    public function surface(): string;

    /** Whether the operation opts into this projector's surface. */
    public function supports(Operation $op): bool;
}
