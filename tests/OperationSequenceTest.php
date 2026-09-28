<?php

declare(strict_types=1);

namespace Milpa\Command\Tests;

use Milpa\Command\Operation;
use PHPUnit\Framework\TestCase;

/**
 * An operation declares which sequence a call continues (greenhouse decisions/0458, 0500).
 */
final class OperationSequenceTest extends TestCase
{
    public function testAnOperationThatDeclaresNothingContinuesNothing(): void
    {
        $op = new Operation(name: 'x', description: 'x', handler: static fn (): null => null);

        self::assertNull($op->continues);
        self::assertNull($op->sequenceFor(['session' => 's1']));
    }

    public function testTheDeclarationNamesTheSequenceFromTheArguments(): void
    {
        $op = new Operation(
            name: 'agent',
            description: 'x',
            handler: static fn (): null => null,
            continues: static fn (array $a): ?string => \is_string($a['session'] ?? null) ? $a['session'] : null,
        );

        self::assertSame('s1', $op->sequenceFor(['session' => ' s1 ']));
        self::assertNull($op->sequenceFor(['prompt' => 'no session']));
    }

    public function testABlankIdIsNoSequence(): void
    {
        $op = new Operation(
            name: 'agent',
            description: 'x',
            handler: static fn (): null => null,
            continues: static fn (array $a): string => '   ',
        );

        self::assertNull($op->sequenceFor([]), 'a blank id would let every unnamed call cite the same receipt');
    }

    public function testTheDeclarationSurvivesACopyOfTheOperation(): void
    {
        $op = new Operation(
            name: 'agent',
            description: 'x',
            handler: static fn (): null => null,
            continues: static fn (array $a): ?string => 'seq',
        );
        $copy = new Operation(...array_replace(get_object_vars($op), ['description' => 'y']));

        self::assertSame('seq', $copy->sequenceFor([]));
    }
}
