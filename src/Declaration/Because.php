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
 * The PURPOSE, not the mechanism — the sentence a catalogue shows and a docblock hides.
 *
 * On a class it explains why the operation exists; on a parameter it becomes that field's
 * `description` in the derived input schema, which is the text an agent reads when it decides
 * whether it has enough to act. Today that sentence is typed twice — once in prose for the human,
 * once in a hand-written schema for the machine. Here it is typed once.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PARAMETER)]
final readonly class Because
{
    public function __construct(public string $purpose)
    {
    }
}
