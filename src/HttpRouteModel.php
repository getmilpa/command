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

/**
 * Una operación vista desde HTTP: el verbo, la ruta y qué política exige.
 *
 * Vive en `milpa/command` y no junto a su projector por una razón medida: `HttpProjector` importa
 * `milpa/auth` —37 referencias, todas en su mitad de enforcement— y ese paquete está fuera del piso
 * mínimo del framework. El MODELO, en cambio, es sólo datos de ruta y no necesita nada. Separarlos
 * permite que cualquiera lea qué rutas expone una operación sin arrastrar identidad.
 *
 * Que aquí aparezcan `scopes` y `permission` no contradice eso: son lo que la operación DECLARA, no
 * lo que alguien verifica. Declarar es del modelo; verificar es de otra capa, y desenredar esa
 * distinción dentro de `HttpProjector` es lo que falta (P13.3).
 */
final readonly class HttpRouteModel implements SurfaceModel
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $method,
        public string $path,
        public string $name,
        public array $scopes = [],
        public ?string $permission = null,
    ) {
    }

    /** La superficie de este modelo — `http`. */
    public function surface(): string
    {
        return 'http';
    }

    /**
     * El modelo como datos planos: lo que una tabla de rutas, un cliente generado o un diff
     * entre versiones del API necesitan leer sin montar la superficie.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'surface' => 'http',
            'method' => $this->method,
            'path' => $this->path,
            'name' => $this->name,
            'scopes' => $this->scopes,
            'permission' => $this->permission,
        ];
    }
}
