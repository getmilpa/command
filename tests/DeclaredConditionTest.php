<?php

declare(strict_types=1);

namespace Milpa\Command\Tests;

use Milpa\Command\DeclaredCondition;
use PHPUnit\Framework\TestCase;

/**
 * The declaration is data a gate and a surface can rely on: named, described, serialisable both
 * ways, and impossible to declare empty — a condition nobody can check must not be declarable.
 */
final class DeclaredConditionTest extends TestCase
{
    public function testItCarriesItsNameAndDescription(): void
    {
        $condition = new DeclaredCondition('phpunit-installed', 'vendor/bin/phpunit exists under the app root');

        self::assertSame('phpunit-installed', $condition->name);
        self::assertSame('vendor/bin/phpunit exists under the app root', $condition->description);
    }

    public function testItSerialisesToItsTwoKeys(): void
    {
        $condition = new DeclaredCondition('entity_file', 'the entity class file exists on disk');

        self::assertSame(
            ['name' => 'entity_file', 'description' => 'the entity class file exists on disk'],
            $condition->toArray(),
        );
    }

    public function testItRoundTripsThroughItsArrayForm(): void
    {
        $original = new DeclaredCondition('routes_declared', 'all five REST routes are declared in the wiring plugin');

        $rehydrated = DeclaredCondition::fromArray($original->toArray());

        self::assertSame($original->name, $rehydrated->name);
        self::assertSame($original->description, $rehydrated->description);
    }

    public function testABlankNameCannotBeDeclared(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DeclaredCondition('   ', 'a description without a name');
    }

    public function testABlankDescriptionCannotBeDeclared(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DeclaredCondition('a-name', '');
    }

    public function testAStoredConditionWithoutAStringNameIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('a stored condition needs a string «name»');
        DeclaredCondition::fromArray(['name' => 7, 'description' => 'valid']);
    }

    public function testAStoredConditionWithoutADescriptionIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DeclaredCondition::fromArray(['name' => 'valid']);
    }
}
