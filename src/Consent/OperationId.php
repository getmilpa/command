<?php

/**
 * This file is part of milpa/command — the atom of the Milpa PHP framework.
 *
 * (c) Rodrigo Vicente - TeamX Agency — https://teamx.agency <hola@teamx.agency>
 *
 * @license Apache-2.0
 *
 * @link    https://github.com/getmilpa/command
 */

declare(strict_types=1);

namespace Milpa\Command\Consent;

/**
 * WHAT an operation IS, apart from how any surface writes it down.
 *
 * The same act arrives spelled three ways: `config:set` in a terminal, `config_set` in a tool
 * catalogue, `config.set` over HTTP. Greenhouse evidence/0176 measured what that costs — a human
 * consented to `config:set`, the gate compared strings against `config_set`, and the yes matched
 * nothing. The first fix normalised the text, which greenhouse decisions/0030 then filed as debt:
 *
 *     «La ortografía pertenece a la proyección; la identidad pertenece al átomo. Si una compuerta
 *     compara ortografías, está comparando UI, no autoridad.»  — Rod, 2026-08-13
 *
 * So identity lives here, in the atom that has no dependencies, and every surface PROJECTS it. A
 * gate asks whether two things are the same act; it never learns to spell.
 */
final readonly class OperationId
{
    /** The canonical form: segments joined by a dot, lowercase. */
    public string $canonical;

    public function __construct(string $cualquierGrafia)
    {
        $this->canonical = self::canonizar($cualquierGrafia);
    }

    /**
     * The same act however it was written.
     *
     * Colon, underscore and dot are the three separators this family's surfaces use, and they are
     * the reason this class exists rather than a `str_replace` at each call site.
     */
    public static function canonizar(string $nombre): string
    {
        $limpio = strtolower(trim($nombre));

        return trim(str_replace([':', '_'], '.', $limpio), '.');
    }

    /** Is this the same act as that one, whatever surface wrote it? */
    public function is(self|string $otro): bool
    {
        return $this->canonical === ($otro instanceof self ? $otro->canonical : self::canonizar($otro));
    }

    /** How a terminal writes it. */
    public function forCli(): string
    {
        return str_replace('.', ':', $this->canonical);
    }

    /** How a tool catalogue writes it. */
    public function forTool(): string
    {
        return str_replace('.', '_', $this->canonical);
    }

    public function __toString(): string
    {
        return $this->canonical;
    }
}
