<?php

declare(strict_types=1);

namespace Milpa\Command\Tests;

use Milpa\Command\CommandProvider;
use Milpa\Command\Operation;
use Milpa\Command\HttpRouteModel;
use Milpa\Command\SurfaceModel;
use Milpa\Command\SurfaceProjector;
use PHPUnit\Framework\TestCase;

final class ContractsTest extends TestCase
{
    public function testAProviderReturnsItsOperations(): void
    {
        $provider = new class () implements CommandProvider {
            public function operations(): array
            {
                return [new Operation('ping', 'Ping', static fn (): string => 'pong')];
            }
        };

        $ops = $provider->operations();
        self::assertCount(1, $ops);
        self::assertSame('ping', $ops[0]->name);
    }

    public function testAProjectorReportsItsSurfaceAndHonoursOptOut(): void
    {
        $projector = new class () implements SurfaceProjector {
            public function surface(): string
            {
                return 'cli';
            }

            public function supports(Operation $op): bool
            {
                return $op->supportsSurface($this->surface());
            }

            public function project(Operation $op): SurfaceModel
            {
                return new class ($op->name) implements SurfaceModel {
                    public function __construct(private readonly string $nombre)
                    {
                    }

                    public function surface(): string
                    {
                        return 'cli';
                    }

                    /** @return array<string, mixed> */
                    public function toArray(): array
                    {
                        return ['surface' => 'cli', 'name' => $this->nombre];
                    }
                };
            }
        };

        self::assertSame('cli', $projector->surface());
        self::assertTrue($projector->supports(new Operation('a', 'A', static fn () => null)));
        self::assertFalse($projector->supports(new Operation('b', 'B', static fn () => null, surfaces: ['http'])));
    }

    /**
     * El contrato exige un MODELO, no un efecto — ADR-0035, cláusula 3. Antes sólo fijaba
     * `surface()` y `supports()`, y esa omisión fue lo que dejó que tres implementaciones hicieran
     * cosas incompatibles: una ejecutaba, otra atendía HTTP, la tercera mutaba un registry.
     */
    public function testTheContractDemandsAModelAndTheModelIsSerialisable(): void
    {
        $modelo = new HttpRouteModel('GET', '/x/ping', 'ping', ['x:read']);

        self::assertInstanceOf(SurfaceModel::class, $modelo);
        self::assertSame('http', $modelo->surface());
        self::assertSame(
            ['surface' => 'http', 'method' => 'GET', 'path' => '/x/ping', 'name' => 'ping',
                'scopes' => ['x:read'], 'permission' => null],
            $modelo->toArray(),
        );
        self::assertJson(json_encode($modelo->toArray(), JSON_THROW_ON_ERROR));
    }

    /** Un modelo es inerte: leerlo dos veces da lo mismo y no ocurre nada. */
    public function testAModelIsInert(): void
    {
        $modelo = new HttpRouteModel('POST', '/x/crear', 'crear');

        self::assertSame($modelo->toArray(), $modelo->toArray());
    }
}
