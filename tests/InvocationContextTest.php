<?php

/**
 * This file is part of Milpa Command — the operation contract of the Milpa PHP framework.
 *
 * (c) Rodrigo Vicente - TeamX Agency — https://teamx.agency <hola@teamx.agency>
 *
 * @license Apache-2.0
 *
 * @link    https://github.com/getmilpa/command
 */

declare(strict_types=1);

namespace Milpa\Command\Tests;

use Milpa\Command\InvocationContext;
use PHPUnit\Framework\TestCase;

/**
 * El contexto que viaja con una invocación: quién actuó, por dónde, y bajo qué autorización.
 *
 * Lo que estas pruebas fijan no es la forma de un objeto — es la regla que impide que un registro se
 * lea como auditoría sin serlo.
 */
final class InvocationContextTest extends TestCase
{
    /**
     * Una terminal aporta EJECUTOR, no actor.
     *
     * El usuario del sistema operativo lo puede ser cualquiera con esa terminal. Llamarlo actor sería
     * el sustituto que esta clase existe para impedir: anotar el proceso donde iba la persona.
     */
    public function testATerminalBringsAnExecutorAndNoActor(): void
    {
        $ctx = InvocationContext::cli('rod@laptop', 'req-1');

        self::assertNull($ctx->actor);
        self::assertFalse($ctx->verified);
        self::assertSame('cli', $ctx->channel);
        self::assertSame('rod@laptop', $ctx->executor);
        self::assertSame('req-1', $ctx->correlationId);
        self::assertFalse($ctx->isAttributable(), 'y por eso no puede firmar nada que exija atribución');
    }

    /**
     * La web trae persona verificada Y la decisión que la autorizó, con nombre.
     *
     * Sin ese identificador, «autorizado» es una palabra: nadie puede ir a ver bajo qué decisión
     * corrió una operación.
     */
    public function testTheWebBringsAVerifiedActorAndTheDecisionThatAuthorisedIt(): void
    {
        $ctx = InvocationContext::web('actor:member:42', 'dec-7', 'www-data@host', 'req-2');

        self::assertSame('actor:member:42', $ctx->actor);
        self::assertTrue($ctx->verified);
        self::assertSame('web', $ctx->channel);
        self::assertSame('dec-7', $ctx->authorizationId);
        self::assertSame('www-data@host', $ctx->executor, 'el proceso acompaña al actor');
        self::assertTrue($ctx->isAttributable());
    }

    /**
     * Un actor SIN verificar no es atribuible, aunque tenga nombre.
     *
     * Es la distinción entera: un nombre que nadie comprobó es una afirmación, no una identidad. Una
     * operación que exija atribución tiene que poder negarse ante esto.
     */
    public function testAnUnverifiedActorIsNotAttributableEvenWithAName(): void
    {
        $ctx = new InvocationContext(actor: 'actor:quien-dice-ser', verified: false, channel: 'web');

        self::assertFalse($ctx->isAttributable());
    }

    /** Y un canal sin nadie detrás tampoco, que es el caso por default. */
    public function testAContextWithNobodyBehindItIsNotAttributable(): void
    {
        self::assertFalse((new InvocationContext())->isAttributable());
    }
}
