<?php

declare(strict_types=1);

namespace Milpa\Command\Tests\Effect;

use Milpa\Command\Effect\Authority;
use Milpa\Command\Effect\EffectProfile;
use Milpa\Command\Effect\Externality;
use Milpa\Command\Effect\Mutation;
use Milpa\Command\Effect\Reversibility;
use Milpa\Command\Operation;
use PHPUnit\Framework\TestCase;

/**
 * The effect ceiling: what an operation can do at worst, and why it cannot be talked down.
 *
 * These tests are the constitution's first executable form. Every one of them fixes a way the
 * ceiling could be lowered by something other than evidence — which is the whole class of defect
 * GOV-00 names: the subject under control retaining the ability to decide how much control it gets.
 */
final class EffectProfileTest extends TestCase
{
    /**
     * AN OPERATION THAT DECLARED NOTHING IS AT ITS CEILING, NOT AT ITS FLOOR.
     *
     * This is GOV-05 in one assertion. A catalogue that treated «unclassified» as «harmless» would
     * hand the cheapest possible classification to whoever never bothered to classify — which makes
     * not classifying the winning move.
     */
    public function testAnUnclassifiedOperationCarriesEveryDimensionAtItsMaximum(): void
    {
        $ceiling = (new Operation('x', 'x', static fn (): array => []))->effectCeiling();

        self::assertSame(Mutation::Unknown, $ceiling->mutation);
        self::assertSame(Externality::Unknown, $ceiling->externality);
        self::assertSame(Reversibility::Unknown, $ceiling->reversibility);
        self::assertSame(Authority::Unknown, $ceiling->authority);
        self::assertFalse($ceiling->isFullyClassified());
    }

    /** And the accessor never returns null, so no caller can write `?? readOnly()` by accident. */
    public function testTheCeilingIsNeverNullSoNobodyCanDefaultItToSomethingHarmless(): void
    {
        self::assertInstanceOf(
            EffectProfile::class,
            (new Operation('x', 'x', static fn (): array => []))->effectCeiling(),
        );
    }

    /**
     * UNKNOWN OUTRANKS PERSISTENT, AND SITS LEVEL WITH IRREVERSIBLE.
     *
     * «I do not know what this writes» is a worse position than «I know it writes to disk», because
     * the second can be reasoned about. And an unknown reversibility must be treated as irreversible,
     * or «we never checked» becomes the cheap way to look recoverable.
     */
    public function testNotKnowingIsRankedAboveKnowingSomethingBad(): void
    {
        self::assertGreaterThan(Mutation::Persistent->weight(), Mutation::Unknown->weight());
        self::assertGreaterThan(Externality::Public->weight(), Externality::Unknown->weight());
        self::assertGreaterThan(Authority::Privileged->weight(), Authority::Unknown->weight());
        self::assertSame(Reversibility::Irreversible->weight(), Reversibility::Unknown->weight());
    }

    /**
     * THE JOIN TAKES THE HIGHEST OF EACH DIMENSION, INDEPENDENTLY — it never averages.
     *
     * A local, reversible operation on highly sensitive data is not «medium». Averaging risk is how
     * a dangerous dimension gets cancelled by a safe one, and GOV-06 exists to forbid it.
     */
    public function testJoiningTakesTheWorstOfEachDimensionAndNeverAverages(): void
    {
        $local = new EffectProfile(
            Mutation::Persistent,
            Externality::None,
            Reversibility::Guaranteed,
            Authority::Read,
            rollbackContract: 'RBK-1',
        );
        $reaching = new EffectProfile(
            Mutation::None,
            Externality::Public,
            Reversibility::Irreversible,
            Authority::WriteAsUser,
        );

        $joined = $local->join($reaching);

        self::assertSame(Mutation::Persistent, $joined->mutation, 'the worse mutation survives');
        self::assertSame(Externality::Public, $joined->externality, 'the wider reach survives');
        self::assertSame(Reversibility::Irreversible, $joined->reversibility);
        self::assertSame(Authority::WriteAsUser, $joined->authority);
    }

    /** And joining is monotonic: it can only ever raise, which is what makes an additive proposer safe. */
    public function testJoiningCanOnlyRaiseTheCeiling(): void
    {
        $high = new EffectProfile(Mutation::Persistent, Externality::Public, Reversibility::Irreversible, Authority::Privileged);
        $low = EffectProfile::readOnly();

        foreach ([$high->join($low), $low->join($high)] as $joined) {
            self::assertSame(Mutation::Persistent, $joined->mutation);
            self::assertSame(Externality::Public, $joined->externality);
            self::assertSame(Reversibility::Irreversible, $joined->reversibility);
            self::assertSame(Authority::Privileged, $joined->authority);
        }
    }

    /** `readOnly()` is the other named position: reads, reaches nobody, spends nothing. */
    public function testTheReadOnlyProfileIsFullyClassifiedAndAtTheFloor(): void
    {
        $profile = EffectProfile::readOnly();

        self::assertTrue($profile->isFullyClassified());
        self::assertSame(Mutation::None, $profile->mutation);
        self::assertSame(Externality::None, $profile->externality);
        self::assertSame(Authority::Read, $profile->authority);
        self::assertNotNull($profile->rollbackContract, 'even «nothing to undo» has to name itself');
    }

    /**
     * THE PROFILE SERIALISES WITH ITS VERDICT INCLUDED.
     *
     * `fully_classified` travels in the payload so a consumer reading JSON cannot mistake four
     * `unknown` values for a classification somebody actually made.
     */
    public function testItSerialisesWithWhetherItIsActuallyClassified(): void
    {
        $unclassified = EffectProfile::unclassified()->toArray();
        self::assertFalse($unclassified['fully_classified']);
        self::assertSame('unknown', $unclassified['mutation']);
        self::assertSame([], $unclassified['escalates_on']);

        $classified = (new EffectProfile(
            Mutation::Persistent,
            Externality::ThirdParty,
            Reversibility::Irreversible,
            Authority::WriteAsUser,
            escalatesOn: ['path'],
        ))->toArray();
        self::assertTrue($classified['fully_classified']);
        self::assertSame(['path'], $classified['escalates_on']);
        self::assertNull($classified['rollback_contract']);
    }

    /**
     * A GUARANTEED ROLLBACK DOES NOT SURVIVE BEING JOINED WITH SOMETHING IRREVERSIBLE.
     *
     * Two operations, one revertible and one not, do not compose into something half-recoverable.
     * Keeping the contract would leave a claim of recoverability attached to work that cannot be
     * recovered — worse than no claim, because someone would rely on it.
     */
    public function testARollbackContractDoesNotSurviveAnIrreversibleJoin(): void
    {
        $revertible = new EffectProfile(reversibility: Reversibility::Guaranteed, rollbackContract: 'RBK-19');
        $sent = new EffectProfile(reversibility: Reversibility::Irreversible);

        self::assertNull($revertible->join($sent)->rollbackContract);
        self::assertSame('RBK-19', $revertible->join($revertible)->rollbackContract);
    }

    /**
     * CLAIMING «GUARANTEED» REQUIRES NAMING WHAT BACKS IT.
     *
     * It is the only level that buys an operation LESS scrutiny, and a claim that lowers controls
     * has to point at an artifact. Otherwise the operation is certifying itself — GOV-00 exactly.
     */
    public function testGuaranteedReversibilityWithoutAContractIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new EffectProfile(reversibility: Reversibility::Guaranteed);
    }

    /**
     * THE CONTRADICTION IS IMPOSSIBLE TO DECLARE, not resolved at read time.
     *
     * `mutating: true` with `mutation: none` would give a different answer depending on which of the
     * two fields a consumer happened to read — and there are eight of them across four packages.
     */
    public function testAnOperationCannotDeclareMutatingAndAProfileThatSaysItDoesNotMutate(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Operation(
            'plugins_lock',
            'x',
            static fn (): array => [],
            mutating: true,
            effects: new EffectProfile(Mutation::None),
        );
    }

    /** The compatible direction is allowed: mutating, and the profile says what kind. */
    public function testAMutatingOperationMayRefineWhatKindOfMutationItPerforms(): void
    {
        $op = new Operation(
            'plugins_lock',
            'x',
            static fn (): array => [],
            mutating: true,
            effects: new EffectProfile(Mutation::Persistent, Externality::None, Reversibility::ManualRecovery, Authority::WriteAsUser),
        );

        self::assertTrue($op->effectCeiling()->isFullyClassified());
        self::assertSame(Mutation::Persistent, $op->effectCeiling()->mutation);
    }

    /**
     * AN UNRESOLVED ESCALATING ARGUMENT KEEPS THE CEILING WHERE IT IS.
     *
     * `file.write` can write a scratch file or a production artifact. Until the path is known, the
     * operation is the production one — not knowing where it goes is not permission to assume it
     * goes somewhere harmless.
     */
    public function testAnUnresolvedEscalatingArgumentIsNamedSoTheCeilingStays(): void
    {
        $profile = new EffectProfile(
            Mutation::Persistent,
            Externality::Unknown,
            Reversibility::Unknown,
            Authority::WriteAsUser,
            escalatesOn: ['path', 'visibility'],
        );

        self::assertSame(['path', 'visibility'], $profile->unresolvedEscalators([]));
        self::assertSame(['visibility'], $profile->unresolvedEscalators(['path' => '/tmp/x']));
        self::assertSame(['path'], $profile->unresolvedEscalators(['path' => '', 'visibility' => 'private']));
        self::assertSame([], $profile->unresolvedEscalators(['path' => '/tmp/x', 'visibility' => 'private']));
    }
}
