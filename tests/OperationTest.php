<?php

declare(strict_types=1);

namespace Milpa\Command\Tests;

use Milpa\Command\DeclaredCondition;
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

    public function testPermissionDefaultsNull(): void
    {
        $op = new Operation('n', 'd', static fn (): array => []);
        self::assertNull($op->permission);
    }

    public function testPermissionTypedOperation(): void
    {
        $op = new Operation('crm.contact.update', 'd', static fn (): array => [], permission: 'crm.contact:update');
        self::assertSame('crm.contact:update', $op->permission);
        self::assertSame([], $op->scopes);
    }

    public function testScopeAndPermissionTogetherIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Operation('bad', 'd', static fn (): array => [], scopes: ['crm.contact:update'], permission: 'crm.contact:update');
    }

    /**
     * The contract fields are ADDITIVE: an operation that declares nothing keeps exactly the shape
     * it always had — empty lists, no evidence — so no existing consumer changes behaviour.
     */
    public function testAnOperationThatDeclaresNoContractCarriesEmptyDeclarations(): void
    {
        $op = new Operation('ping', 'Ping', static fn (): string => 'pong');

        self::assertSame([], $op->preconditions);
        self::assertSame([], $op->postconditions);
        self::assertSame([], $op->artifacts);
        self::assertNull($op->observableEvidence);
    }

    /** The declared contract travels on the operation, readable by any surface. */
    public function testAnOperationCarriesItsDeclaredContract(): void
    {
        $pre = new DeclaredCondition('phpunit-installed', 'vendor/bin/phpunit exists under the app root');
        $post = new DeclaredCondition('entity_file', 'the entity class file exists on disk');

        $op = new Operation(
            name: 'make',
            description: 'Scaffold an artifact',
            handler: static fn (array $i): array => $i,
            preconditions: [$pre],
            postconditions: [$post],
            artifacts: ['the scaffolded files by kind', 'the postcondition report'],
            observableEvidence: 'the postcondition report in the result',
        );

        self::assertSame([$pre], $op->preconditions);
        self::assertSame([$post], $op->postconditions);
        self::assertSame(['the scaffolded files by kind', 'the postcondition report'], $op->artifacts);
        self::assertSame('the postcondition report in the result', $op->observableEvidence);
    }
}
