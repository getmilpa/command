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

    /**
     * A call cites a receipt its OWN operation signed — and, when it says so, one signed by an operation it names
     * (greenhouse decisions/0526 §2: an `agent` receipt also covers `agent:answer` in the same session).
     */
    public function testACallCitesOnlyWhatItsOwnOperationSignedUnlessItNamesAnother(): void
    {
        $plain = new Operation(name: 'agent:answer', description: 'x', handler: static fn (): null => null, continues: static fn (array $a): ?string => 's');

        self::assertSame([], $plain->citesReceiptsOf);
        self::assertTrue($plain->mayCiteReceiptOf('agent:answer'));
        self::assertFalse($plain->mayCiteReceiptOf('agent'));

        $answer = new Operation(
            name: 'agent:answer',
            description: 'x',
            handler: static fn (): null => null,
            continues: static fn (array $a): ?string => 's',
            citesReceiptsOf: ['agent'],
        );

        self::assertTrue($answer->mayCiteReceiptOf('agent'));
        self::assertTrue($answer->mayCiteReceiptOf('agent:answer'));
        self::assertFalse($answer->mayCiteReceiptOf('recipe:apply'), 'only what it names');
        self::assertFalse($answer->mayCiteReceiptOf(''));
    }

    /** Naming receipts to cite without naming the sequence they bind to is refused at declaration. */
    public function testNamingReceiptsWithoutASequenceIsRefusedAtDeclaration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('continues');

        new Operation(name: 'agent:answer', description: 'x', handler: static fn (): null => null, citesReceiptsOf: ['agent']);
    }

    /** A blank name would read as «any operation» to a careless reader; it is refused. */
    public function testABlankNameIsRefusedAtDeclaration(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Operation(name: 'agent:answer', description: 'x', handler: static fn (): null => null, continues: static fn (array $a): ?string => 's', citesReceiptsOf: [' ']);
    }

    public function testTheReceiptsItMayCiteSurviveACopyOfTheOperation(): void
    {
        $op = new Operation(name: 'agent:answer', description: 'x', handler: static fn (): null => null, continues: static fn (array $a): ?string => 's', citesReceiptsOf: ['agent']);
        $copy = new Operation(...array_replace(get_object_vars($op), ['description' => 'y']));

        self::assertTrue($copy->mayCiteReceiptOf('agent'));
    }
}
