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

namespace Milpa\Command\Tests\Effect;

use Milpa\Command\Effect\Authority;
use Milpa\Command\Effect\EffectProfile;
use Milpa\Command\Effect\Externality;
use Milpa\Command\Effect\Mutation;
use Milpa\Command\Effect\Reversibility;
use Milpa\Command\Effect\Subject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A promise that lowers scrutiny NAMES the inverse; it does not describe it.
 *
 * The older guard asked for a rollback contract and accepted a sentence, so the house asked for proof
 * and took a note — `delete var/capability-index.json` reads like an answer and is not one. Nothing can
 * run it, nothing can check it ran, and no gate can put it through the ceremony the original call went
 * through. What can be all three is the NAME of another operation.
 */
#[CoversClass(EffectProfile::class)]
final class ARollbackNamesAnOperationTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function prose(): array
    {
        return [
            'a path' => ['delete var/capability-index.json'],
            'a sentence' => ['remove the line from var/greetings.txt'],
            'an excuse' => ['dies with the process'],
            'a reason' => ['reads only: there is nothing to roll back'],
        ];
    }

    /**
     * WHAT THE SHAPE ALONE CANNOT CATCH, AND WHY THE OTHER HALF OF THIS GUARD EXISTS.
     *
     * `nothing-to-roll-back` — the placeholder twenty operations carried — is a well-formed name: one
     * segment with hyphens, exactly like `plugins.disable-unsafe` needs to be. Nothing about the STRING
     * says it is not an operation. Only the TABLE can say that, which is why «does this name resolve to
     * an operation this app offers» is asked where the table exists ({@see \Milpa\Command\RollbackContracts}).
     */
    public function testAWellFormedNameThatNamesNothingIsNotCaughtByShapeAlone(): void
    {
        self::assertSame('nothing-to-roll-back', self::guaranteedBy('nothing-to-roll-back')->rollbackContract);
    }

    #[DataProvider('prose')]
    public function testProseIsRefusedAsARollbackContract(string $prose): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('describes an inverse instead of naming one');

        self::guaranteedBy($prose);
    }

    /**
     * THE POSITIVE CONTROL. The guard bites because prose is prose, not because the field is unusable:
     * a name goes in, in any spelling a surface uses, and the profile builds.
     *
     * @return array<string, array{0: string}>
     */
    public static function names(): array
    {
        return [
            'dotted' => ['plugins.disable'],
            'a colon, as a terminal writes it' => ['plugins:disable'],
            'an underscore, as a tool catalogue writes it' => ['plugins_disable'],
            'a hyphen inside one segment' => ['plugins.disable-unsafe'],
            'a single segment' => ['undo'],
        ];
    }

    #[DataProvider('names')]
    public function testANameIsAccepted(string $name): void
    {
        self::assertSame($name, self::guaranteedBy($name)->rollbackContract);
    }

    /**
     * And what the caller gets back is the IDENTITY, not the spelling — so a gate can find that
     * operation however a surface writes it, and the ledger records a name instead of a class.
     */
    #[DataProvider('names')]
    public function testTheContractResolvesToOneIdentityWhateverTheSpelling(string $name): void
    {
        $id = self::guaranteedBy($name)->rollbackOperation();

        self::assertNotNull($id);
        self::assertTrue($id->is($name));
        self::assertStringNotContainsString(':', $id->canonical, 'the canonical form is the atom, never a surface');
        self::assertStringNotContainsString('_', $id->canonical);
    }

    /** Anything that promises nothing has no inverse to hand back. */
    public function testAProfileThatPromisesNothingHasNoRollbackOperation(): void
    {
        self::assertNull(EffectProfile::readOnly()->rollbackOperation());
        self::assertNull(EffectProfile::unclassified()->rollbackOperation());
        self::assertNull((new EffectProfile(
            Mutation::Persistent,
            Externality::None,
            Reversibility::ManualRecovery,
            Authority::WriteAsUser,
            subject: Subject::Data,
            rollbackContract: 'somebody edits the file back',
        ))->rollbackOperation(), 'a contract on a non-guaranteed profile is a note, not a promise');
    }

    /**
     * HISTORY IS ATTESTED, NEVER VALIDATED — and a promise backed by prose loses the discount, not the
     * sentence.
     *
     * The event happened and cannot be re-declared, so it is not rejected. But reporting `guaranteed`
     * to a policy reading this envelope TODAY would hand out lower scrutiny on the strength of a note,
     * so it reads as what it actually was: something a human undoes by hand. Raises scrutiny, never
     * lowers it — the same rule that puts `Unknown` level with `Irreversible`.
     */
    public function testAnArchivedPromiseBackedByProseIsReadAsManualRecovery(): void
    {
        $archived = EffectProfile::fromArray([
            'mutation' => 'persistent',
            'externality' => 'third_party',
            'reversibility' => 'guaranteed',
            'authority' => 'read',
            'subject' => 'data',
            'rollback_contract' => 'delete var/capability-index.json',
        ]);

        self::assertSame(Reversibility::ManualRecovery, $archived->reversibility);
        self::assertSame('delete var/capability-index.json', $archived->rollbackContract, 'nothing anyone wrote is lost');
        self::assertNull($archived->rollbackOperation(), 'and it no longer answers as a promise');
        self::assertGreaterThan(
            Reversibility::Guaranteed->weight(),
            $archived->reversibility->weight(),
            'it raised scrutiny; it never lowers it',
        );
    }

    /** An archived promise that DOES name an operation keeps its promise, unchanged. */
    public function testAnArchivedPromiseThatNamesAnOperationSurvivesIntact(): void
    {
        $archived = EffectProfile::fromArray([
            'mutation' => 'persistent',
            'externality' => 'none',
            'reversibility' => 'guaranteed',
            'authority' => 'write_as_user',
            'subject' => 'configuration',
            'rollback_contract' => 'plugins.disable',
        ]);

        self::assertSame(Reversibility::Guaranteed, $archived->reversibility);
        self::assertTrue($archived->rollbackOperation()?->is('plugins:disable'));
    }

    private static function guaranteedBy(string $contract): EffectProfile
    {
        return new EffectProfile(
            Mutation::Persistent,
            Externality::None,
            Reversibility::Guaranteed,
            Authority::WriteAsUser,
            subject: Subject::Configuration,
            rollbackContract: $contract,
        );
    }
}
