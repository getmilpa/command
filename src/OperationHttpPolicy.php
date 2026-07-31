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

namespace Milpa\Command;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Decide si esta petición puede correr esta operación.
 *
 * ── POR QUÉ VIVE AQUÍ Y NO JUNTO AL PROYECTOR ───────────────────────────────────────────────────
 *
 * Porque es un contrato SOBRE una operación, y `Operation` vive aquí. Nació en `milpa/console`, con
 * su proyector HTTP, y eso dejaba a quien la implementa —`milpa/auth`, que es piso— teniendo que
 * depender de console, que a su vez arrastra plugins, live y tool-runtime. La dependencia iba al
 * revés: el implementador cargando con la casa del consumidor.
 *
 * Aquí no cuesta nada. `milpa/command` sólo pide PHP y las interfaces PSR de mensajes; quien declara
 * operaciones ya lo tiene.
 *
 * ── NO SABER NO ES PERMITIR ─────────────────────────────────────────────────────────────────────
 *
 * Al revés que otros colaboradores opcionales de la familia, la ausencia de política NO deja pasar.
 * Una operación que declara scopes y corre sin nadie que los revise es el agujero que esta capa vino
 * a cerrar: quien proyecta a HTTP se niega —con un 500, no un 403— cuando el host declaró algo
 * protegido y no cableó con qué protegerlo. La culpa es del servidor y la respuesta lo dice.
 */
interface OperationHttpPolicy
{
    /**
     * `null` significa adelante; una respuesta ES la negativa, ya formada (401 o 403).
     *
     * Devuelve la respuesta en vez de lanzar porque una negativa autorizada NO es un error: es el
     * resultado correcto de preguntar. Lo que sí lanza esta capa es el caso en que no se puede ni
     * preguntar —el host no cableó cadena de autenticación—, que es un defecto de configuración.
     *
     * @throws \RuntimeException cuando la operación exige identidad y el host no cableó ninguna
     */
    public function enforce(Operation $op, ServerRequestInterface $request): ?ResponseInterface;
}
