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

namespace Milpa\Command\Declaration;

/**
 * A human confirms this before it runs — the operation asking for the pause, not the caller granting it.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Confirms
{
}
