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

namespace Milpa\Command\Tests;

use Milpa\Command\Operation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * An operation says it AMENDS the target it names — and says nothing it cannot mean (greenhouse decisions/0596).
 *
 * The intent contract's line was drawn by the verb: an operation that creates its named target is not asked «you did
 * not name it», one that selects an existing target is. Of 34 times a house asked a person to confirm an `edit`, 28
 * were about a class that same session had brought into the house. So an operation can declare that it amends what
 * its target names, and the session's floor reads from that session's own record where the target came from.
 *
 * The declaration is a statement about a target: it needs one, and it cannot sit beside the statement that the
 * operation creates that same target. Both contradictions are refused where they are written, not reconciled by
 * whoever reads them later.
 */
#[CoversClass(Operation::class)]
final class AnOperationSaysItAmendsItsNamedTargetTest extends TestCase
{
    public function testAnOperationDoesNotAmendUnlessItSaysSo(): void
    {
        $operation = new Operation('edit', 'Edits a class.', static fn (): array => [], namedTarget: 'class');

        self::assertFalse($operation->amendsNamedTarget, 'fail-closed: silence keeps the question');
    }

    public function testAnOperationThatNamesNoTargetAndSaysNothingIsDeclaredAsBefore(): void
    {
        $operation = new Operation('list', 'Lists things.', static fn (): array => []);

        self::assertNull($operation->namedTarget, 'the control: most operations name no target, and say nothing about one');
        self::assertFalse($operation->amendsNamedTarget);
    }

    public function testAnOperationThatSaysSoCarriesIt(): void
    {
        $operation = new Operation('edit', 'Edits a class.', static fn (): array => [], namedTarget: 'class', amendsNamedTarget: true);

        self::assertTrue($operation->amendsNamedTarget);
        self::assertSame('class', $operation->namedTarget);
        self::assertFalse($operation->createsNamedTarget, 'amending is not creating');
    }

    public function testItSurvivesACopyOfTheContract(): void
    {
        $operation = new Operation('edit', 'Edits a class.', static fn (): array => [], namedTarget: 'class', amendsNamedTarget: true);

        $copy = new Operation(...array_replace(get_object_vars($operation), ['description' => 'Edits a class, and says more.']));

        self::assertTrue($copy->amendsNamedTarget, 'a host that rebuilds the contract keeps what it declared');
    }

    public function testAmendingNeedsATargetToSpeakOf(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Operation 'edit' declares that it amends its named target and names none");

        new Operation('edit', 'Edits a class.', static fn (): array => [], amendsNamedTarget: true);
    }

    public function testAnOperationCannotBothCreateItsTargetAndAmendOneThatExists(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Operation 'edit' declares that it creates its named target AND that it amends it");

        new Operation('edit', 'Edits a class.', static fn (): array => [], namedTarget: 'class', createsNamedTarget: true, amendsNamedTarget: true);
    }

    public function testCreatingAloneIsStillWhatItWas(): void
    {
        $operation = new Operation('implement', 'Fills a class.', static fn (): array => [], namedTarget: 'class', createsNamedTarget: true);

        self::assertTrue($operation->createsNamedTarget);
        self::assertFalse($operation->amendsNamedTarget);
    }
}
