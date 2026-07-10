<?php

declare(strict_types=1);

namespace Milpa\Command\Tests;

use Milpa\Command\CommandProvider;
use Milpa\Command\Operation;
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
        };

        self::assertSame('cli', $projector->surface());
        self::assertTrue($projector->supports(new Operation('a', 'A', static fn () => null)));
        self::assertFalse($projector->supports(new Operation('b', 'B', static fn () => null, surfaces: ['http'])));
    }
}
