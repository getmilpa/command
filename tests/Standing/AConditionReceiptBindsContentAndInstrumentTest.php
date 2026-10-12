<?php

declare(strict_types=1);

namespace Milpa\Command\Tests\Standing;

use Milpa\Command\Standing\ConditionReceipt;
use PHPUnit\Framework\TestCase;

/**
 * A receipt is bound to WHAT was measured and to WHAT measured it (greenhouse decisions/0615 §2).
 */
final class AConditionReceiptBindsContentAndInstrumentTest extends TestCase
{
    public function testItReadsBackWhatItWrote(): void
    {
        $receipt = Grants::receipt(arguments: ['path' => 'candidate.tar', 'strict' => true]);
        $again = ConditionReceipt::fromArray($receipt->toArray());

        self::assertEquals($receipt, $again);
        self::assertSame('lab.scan', $receipt->toArray()['by']);
        self::assertSame(Grants::CANDIDATE, $again?->subject);
        self::assertTrue($again?->ok);
    }

    public function testAReceiptThatBindsNothingCannotBeWritten(): void
    {
        foreach ([[' ', Grants::INSTRUMENT], [Grants::CANDIDATE, '']] as [$subject, $instrument]) {
            try {
                new ConditionReceipt('lab:scan', [], $subject, true, $instrument, null, Grants::at());
                self::fail('a receipt bound to nothing was written');
            } catch (\InvalidArgumentException $error) {
                self::assertStringContainsString('binds nothing', $error->getMessage());
            }
        }
    }

    public function testALedgerLineThatIsNotAReceiptMeasuresNothing(): void
    {
        $stored = Grants::receipt()->toArray();
        self::assertNotNull(ConditionReceipt::fromArray($stored));
        self::assertNull(ConditionReceipt::fromArray(['who' => 7] + $stored)?->who, 'a who that is not text is nobody');

        foreach ([
            ['by' => 7], ['arguments' => 'path'], ['subject' => null], ['subject' => ' '], ['ok' => 'yes'],
            ['ok' => 1], ['instrument' => null], ['instrument' => ''], ['at' => 'no date'], ['at' => 7],
        ] as $broken) {
            self::assertNull(ConditionReceipt::fromArray($broken + $stored), (string) json_encode($broken));
        }
        self::assertNull(ConditionReceipt::fromArray([]));
    }
}
