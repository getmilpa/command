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
 * Declares that a plugin contributes operations to the kernel's command table — the discovery seam
 * for Command-as-atom. The canonical successor to `Milpa\Runtime\CommandProviderInterface`: the
 * kernel checks every booted plugin for this interface and merges its {@see operations()} into the
 * list it exposes, from which each surface projector (CLI, MCP, HTTP) materializes its own shape.
 */
interface CommandProvider
{
    /**
     * Operations this plugin contributes.
     *
     * @return list<Operation>
     */
    public function operations(): array;
}
