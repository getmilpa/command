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
 * The authority this operation spends — scopes XOR a permission, never both.
 *
 * The exclusion is not a style rule: an operation typed by both would have two answers to «may I»,
 * and composition (allOf/anyOf) is a deliberate future move rather than an implicit «both must
 * pass». {@see \Milpa\Command\Operation} refuses that contradiction at construction, and this
 * attribute hands it the same two fields, unchanged.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Needs
{
    /** @param list<string> $scopes */
    public function __construct(
        public array $scopes = [],
        public ?string $permission = null,
    ) {
    }
}
