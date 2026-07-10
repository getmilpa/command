<?php

declare(strict_types=1);

namespace Milpa\Command\Tests;

use Milpa\Command\Operation;
use PHPUnit\Framework\TestCase;

final class OperationTest extends TestCase
{
    public function testDefaultsAreNonMutatingAllSurfaces(): void
    {
        $op = new Operation('ping', 'Ping', static fn (): string => 'pong');

        self::assertSame('ping', $op->name);
        self::assertSame('Ping', $op->description);
        self::assertIsCallable($op->handler);
        self::assertNull($op->inputSchema);
        self::assertFalse($op->mutating);
        self::assertFalse($op->requiresConfirmation);
        self::assertSame([], $op->scopes);
        self::assertNull($op->outputSchema);
        self::assertNull($op->version);
        self::assertNull($op->path);
        self::assertNull($op->surfaces);
    }

    public function testSupportsEverySurfaceWhenSurfacesIsNull(): void
    {
        $op = new Operation('ping', 'Ping', static fn (): string => 'pong');

        self::assertTrue($op->supportsSurface('cli'));
        self::assertTrue($op->supportsSurface('http'));
        self::assertTrue($op->supportsSurface('mcp'));
    }

    public function testSurfacesListIsAnOptIn(): void
    {
        $op = new Operation('ping', 'Ping', static fn (): string => 'pong', surfaces: ['cli', 'http']);

        self::assertTrue($op->supportsSurface('cli'));
        self::assertTrue($op->supportsSurface('http'));
        self::assertFalse($op->supportsSurface('mcp'));
    }

    public function testCarriesPolicyAndHttpMetadata(): void
    {
        $op = new Operation(
            name: 'create_post',
            description: 'Create a post',
            handler: static fn (array $i): array => $i,
            inputSchema: ['type' => 'object'],
            mutating: true,
            requiresConfirmation: true,
            scopes: ['posts:write'],
            path: '/posts',
        );

        self::assertTrue($op->mutating);
        self::assertTrue($op->requiresConfirmation);
        self::assertSame(['posts:write'], $op->scopes);
        self::assertSame('/posts', $op->path);
    }
}
