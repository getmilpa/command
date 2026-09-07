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
use PHPUnit\Framework\TestCase;

/**
 * «Nothing to undo» and «I can undo it» are different claims, and one was eating the other.
 *
 * Measured on a founded app before this existed: of twenty-three operations claiming
 * `Reversibility::Guaranteed`, TWENTY changed nothing at all and backed the claim with the prose
 * «nothing-to-roll-back» — produced by {@see EffectProfile::readOnly()} itself, so it was one
 * constructor's doing, not twenty authors'. Three actually mutated. Auditing «who promises
 * reversibility» was therefore 87% noise, and the one real debt hid inside it.
 *
 * `Guaranteed` is the only value in that enum that BUYS lower scrutiny. Handing it to everything that
 * reads is not an imprecision — it is the discount applied where no promise was ever made.
 */
#[CoversClass(EffectProfile::class)]
#[CoversClass(Reversibility::class)]
final class NothingToUndoIsNotAPromiseTest extends TestCase
{
    /** F1. A profile cannot say both «nothing happens» and «I can undo it». */
    public function testAnOperationThatChangesNothingCannotPromiseToUndoIt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot promise to undo it');

        new EffectProfile(
            Mutation::None,
            Externality::None,
            Reversibility::Guaranteed,
            Authority::Read,
            subject: Subject::None,
            rollbackContract: 'nothing-to-roll-back',
        );
    }

    /** F1's mirror, so the new case cannot become a second way to look reversible. */
    public function testAnOperationThatChangesSomethingCannotSayUndoingDoesNotApply(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot say undoing does not apply');

        new EffectProfile(
            Mutation::Persistent,
            Externality::None,
            Reversibility::NotApplicable,
            Authority::WriteAsUser,
            subject: Subject::Data,
        );
    }

    /**
     * THE POSITIVE CONTROL. The guard must bite because the two DISAGREE, not because the new case
     * is unbuildable: a read declares it and is fully classified, at the floor, with no contract.
     */
    public function testAReadDeclaresItAndIsFullyClassifiedAtTheFloor(): void
    {
        $read = EffectProfile::readOnly();

        self::assertSame(Reversibility::NotApplicable, $read->reversibility);
        self::assertNull($read->rollbackContract);
        self::assertTrue($read->isFullyClassified(), 'it is a decision somebody made, not an unknown');
        self::assertSame(0, $read->reversibility->weight(), 'a read never demands more scrutiny than a tested inverse');
    }

    /** F2. A real promise still has to name what backs it — the older guard is untouched. */
    public function testAMutationThatClaimsGuaranteedStillNeedsAContract(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a rollback contract');

        new EffectProfile(
            Mutation::Persistent,
            Externality::None,
            Reversibility::Guaranteed,
            Authority::WriteAsUser,
            subject: Subject::Data,
        );
    }

    /**
     * F3. HISTORY IS ATTESTED, NEVER VALIDATED.
     *
     * Every profile stored before this case existed says `guaranteed` for an operation that changes
     * nothing. `fromArray()` runs through the constructor, so a rule adopted today would otherwise
     * make the house unable to read the ledger it wrote yesterday.
     */
    public function testAProfileArchivedBeforeThisRuleStillReads(): void
    {
        $archived = EffectProfile::fromArray([
            'mutation' => 'none',
            'externality' => 'none',
            'reversibility' => 'guaranteed',
            'authority' => 'read',
            'subject' => 'none',
            'rollback_contract' => 'nothing-to-roll-back',
        ]);

        self::assertSame(Reversibility::NotApplicable, $archived->reversibility, 'said of that old event what it always meant');
        self::assertNull($archived->rollbackContract);
        self::assertSame(0, $archived->reversibility->weight(), 'and it lowered nothing: the weight is what it was');
    }

    /** F4. The tightening door stays shut for BOTH floor values, or the new one is a back door. */
    public function testAHumanCannotTightenIntoEitherFloorValue(): void
    {
        foreach (['guaranteed' => 'cannot be claimed by a tightening', 'not_applicable' => 'tighten «mutation» instead'] as $axis => $expected) {
            try {
                EffectProfile::fromPartial(['reversibility' => $axis]);
                self::fail(\sprintf('a tightening claimed «%s» and nothing refused it', $axis));
            } catch (\InvalidArgumentException $refused) {
                self::assertStringContainsString($expected, $refused->getMessage());
            }
        }
    }

    /**
     * F5. COMPOSITION DERIVES, IT DOES NOT DECLARE.
     *
     * `meet` and `join` pick each axis independently, so they can reach a pair the constructor
     * refuses. They are not declarations by anyone — they are derivations — so they agree with the
     * mutation they produced instead of throwing. Neither moves an axis: `NotApplicable` and
     * `Guaranteed` are both the floor.
     */
    public function testComposingAxesNeverProducesAProfileNobodyCouldDeclare(): void
    {
        $read = EffectProfile::readOnly();
        $install = new EffectProfile(
            Mutation::Persistent,
            Externality::ThirdParty,
            Reversibility::Guaranteed,
            Authority::Privileged,
            subject: Subject::Executable,
            rollbackContract: 'plugins.disable',
        );

        $met = $read->meet($install);
        self::assertSame(Mutation::None, $met->mutation);
        self::assertSame(Reversibility::NotApplicable, $met->reversibility, 'meet dropped mutation to none, so undoing does not apply');
        self::assertTrue($met->isNoWiderThan($read), 'and it still never widens');
        self::assertTrue($met->isNoWiderThan($install));

        $joined = $read->join($install);
        self::assertSame(Mutation::Persistent, $joined->mutation);
        self::assertSame(Reversibility::Guaranteed, $joined->reversibility, 'the side that mutates is the one whose answer means anything');
        self::assertSame('plugins.disable', $joined->rollbackContract, 'and it carries the contract that backs it');
        self::assertTrue($read->isNoWiderThan($joined), 'join only raises');
        self::assertTrue($install->isNoWiderThan($joined));
    }

    /** The round trip survives: what the axis says is what the ledger stores and reads back. */
    public function testTheNewCaseSurvivesSerialisation(): void
    {
        $back = EffectProfile::fromArray(EffectProfile::readOnly()->toArray());

        self::assertSame(Reversibility::NotApplicable, $back->reversibility);
        self::assertEquals(EffectProfile::readOnly(), $back);
    }
}
