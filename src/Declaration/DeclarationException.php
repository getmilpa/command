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
 * A declaration that cannot be derived without guessing — refused, naming the class.
 *
 * Every message this carries points at one class and says what to declare. That is the whole posture:
 * fail closed and say so, the way the admin's gate refuses to open without its key and the composite
 * component registry names a conflict instead of picking a winner.
 */
final class DeclarationException extends \InvalidArgumentException
{
}
