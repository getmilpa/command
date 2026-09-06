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
 * The argument the human must NAME in the request — ADR-0044's contract of intent, as syntax.
 *
 * «Remove the old plugin» does not say WHICH, and Q-P19-K measured what happens when nobody declares
 * that it must: three runs killed one plugin, three killed another, ten killed none, and no fact says
 * why. Marking the parameter says it: a request that does not name this value is not executed, it is
 * asked back — with the operation and the arguments inside the question.
 *
 * Exactly one parameter may carry it. Two named targets is not a stricter contract, it is an
 * ambiguous one, and {@see DeclaredOperation} refuses it by name.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class Target
{
}
