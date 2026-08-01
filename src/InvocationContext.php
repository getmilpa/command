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

namespace Milpa\Command;

/**
 * Quién está corriendo esta operación, por dónde, y bajo qué autorización.
 *
 * ── POR QUÉ NO ES EL `ToolContext` ──────────────────────────────────────────────────────────────
 *
 * Porque `ToolContext` pertenece a la frontera de herramientas y autorización: lleva scopes, canal y
 * detalles de transporte. Recibirlo aquí como contrato público acoplaría **cada operación** a HTTP,
 * MCP, CLI y al vocabulario de scopes — y una operación que puede leer scopes es una operación que
 * puede volver a decidir con ellos, que es exactamente lo que la política ya decidió.
 *
 * Esto es lo mínimo que un handler necesita para **atribuir**, no para autorizar:
 *
 * - quién actuó y si esa identidad se verificó;
 * - por qué canal;
 * - bajo qué decisión autorizante;
 * - con qué correlación, para poder seguir el hilo entre sistemas.
 *
 * Los scopes no están, y su ausencia es la regla: **la política autoriza, la operación atribuye.**
 *
 * ── POR QUÉ VIAJA Y NO SE AMBIENTA ──────────────────────────────────────────────────────────────
 *
 * No vive en el contenedor. Meter la identidad de una petición en algo que es de la aplicación crea
 * estado ambiental: las pruebas dependen de un montaje invisible, una operación puede **olvidar**
 * leer al actor y seguir funcionando, y el contenedor puede conservar el actor de la petición
 * anterior. El contexto viaja por el mismo camino explícito que la invocación o no viaja.
 *
 * ── ACTOR Y EJECUTOR SON DOS IDENTIDADES ────────────────────────────────────────────────────────
 *
 * El **actor** es quien autorizó: una persona autenticada, o nadie. El **ejecutor** es el proceso
 * técnico que materializó la operación: `www-data`, un runner, una terminal. El registro puede
 * conservar los dos —y conviene— pero **jamás sustituir uno por el otro**: anotar `www-data` donde
 * había una persona identificada convierte una cadena de custodia real en una falsa.
 */
final readonly class InvocationContext
{
    /**
     * @param string|null $actor           quién autorizó, con su origen adelante (`actor:member:42`),
     *                                     o `null` cuando nadie se identificó
     * @param bool        $verified        si detrás de ese actor hubo una credencial que alguien
     *                                     comprobó. Un `true` sin credencial es una mentira cara
     * @param string      $channel         `cli`, `web`, `mcp`, `tui` — por dónde entró
     * @param string|null $executor        el proceso que la corrió, cuando se sabe. Nunca reemplaza al
     *                                     actor: acompaña
     * @param string|null $authorizationId identificador de la decisión que autorizó esto, o de la
     *                                     evidencia equivalente. Sin él, «autorizado» es una palabra
     * @param string|null $correlationId   el hilo al que pertenece esta invocación, para seguirla entre
     *                                     sistemas
     */
    public function __construct(
        public ?string $actor = null,
        public bool $verified = false,
        public string $channel = 'cli',
        public ?string $executor = null,
        public ?string $authorizationId = null,
        public ?string $correlationId = null,
    ) {
    }

    /**
     * El contexto de una terminal: hay ejecutor y **no hay actor verificado**.
     *
     * El usuario del sistema operativo se guarda como ejecutor y no como actor, porque cualquiera con
     * esa terminal puede serlo. Llamarlo actor sería el sustituto que esta clase existe para impedir.
     */
    public static function cli(?string $executor = null, ?string $correlationId = null): self
    {
        return new self(
            actor: null,
            verified: false,
            channel: 'cli',
            executor: $executor,
            correlationId: $correlationId,
        );
    }

    /** Hay una persona identificada detrás, y la decisión que la autorizó tiene nombre. */
    public static function web(
        string $actor,
        string $authorizationId,
        ?string $executor = null,
        ?string $correlationId = null,
    ): self {
        return new self(
            actor: $actor,
            verified: true,
            channel: 'web',
            executor: $executor,
            authorizationId: $authorizationId,
            correlationId: $correlationId,
        );
    }

    /**
     * ¿Se puede atribuir esta invocación a alguien verificable?
     *
     * Lo usan las operaciones que **exigen atribución**: si esto es falso, la respuesta correcta es
     * negarse, no degradar a un ejecutor. Escribir el proceso donde debía ir la persona produce un
     * registro que se lee como auditoría y no lo es.
     */
    public function isAttributable(): bool
    {
        return $this->actor !== null && $this->verified;
    }
}
