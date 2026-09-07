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

use Milpa\Command\Effect\Authority;
use Milpa\Command\Effect\EffectProfile;
use Milpa\Command\Effect\Externality;
use Milpa\Command\Effect\Mutation;
use Milpa\Command\Effect\Reversibility;
use Milpa\Command\Effect\Subject;
use Milpa\Command\Operation;
use Milpa\Command\RollbackContracts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The half of the rollback guard that one profile cannot see.
 *
 * `EffectProfile` refuses prose. It cannot refuse `nothing-to-roll-back`, which is a well-formed name
 * for an operation nobody wrote — only the table knows what this app offers.
 */
#[CoversClass(RollbackContracts::class)]
final class RollbackContractsTest extends TestCase
{
    /** A promise whose inverse is on the table is kept. */
    public function testAPromiseAnsweredByTheTableIsNotAFinding(): void
    {
        $table = [
            self::promising('plugins.enable', 'plugins.disable'),
            self::promising('plugins.disable', 'plugins.enable'),
        ];

        self::assertSame([], RollbackContracts::unresolved($table));
        self::assertSame([], RollbackContracts::findings($table));
    }

    /**
     * THE ONE THE SHAPE LETS THROUGH. `nothing-to-roll-back` is a valid name; it is simply not an
     * operation, and that is the whole finding.
     */
    public function testAWellFormedNameThatNamesNothingIsAFinding(): void
    {
        $table = [self::promising('capabilities.refresh', 'nothing-to-roll-back')];

        self::assertSame(['capabilities.refresh' => 'nothing-to-roll-back'], RollbackContracts::unresolved($table));
        self::assertStringContainsString('does not offer', RollbackContracts::findings($table)[0]);
        self::assertStringContainsString('is not a guarantee', RollbackContracts::findings($table)[0]);
    }

    /** Identity, never spelling: a promise written for one surface resolves against another's. */
    public function testTheSpellingOfTheSurfaceDoesNotDecideWhetherAPromiseResolves(): void
    {
        self::assertSame([], RollbackContracts::unresolved([
            self::promising('plugins:enable', 'plugins_disable'),
            self::promising('plugins.disable', 'plugins.enable'),
        ]), 'colon, underscore and dot are three ways to write one act');
    }

    /**
     * Resolved against the WHOLE table, not as it is walked — otherwise a house would be reporting the
     * order of its own iteration as a broken promise.
     */
    public function testAnInverseContributedLaterStillResolves(): void
    {
        self::assertSame([], RollbackContracts::unresolved([
            self::promising('plugins.enable', 'plugins.disable'),
            self::reading('plugins.disable'),
        ]));
    }

    /** Only `guaranteed` promises. A note on anything else is a note, and notes are not audited. */
    public function testOnlyAGuaranteedProfileIsHeldToItsContract(): void
    {
        $manual = new Operation(
            name: 'capabilities.refresh',
            effects: new EffectProfile(
                Mutation::Persistent,
                Externality::ThirdParty,
                Reversibility::ManualRecovery,
                Authority::Read,
                subject: Subject::Data,
                rollbackContract: 'delete var/capability-index.json',
            ),
            description: 'x',
            handler: static fn (): array => [],
            inputSchema: ['type' => 'object'],
            mutating: true,
        );

        self::assertSame([], RollbackContracts::unresolved([$manual]), 'it promised nothing, so it broke nothing');
    }

    /** An app with no operations promises nothing, and answering otherwise would be inventing a debt. */
    public function testAnEmptyTableHasNoBrokenPromises(): void
    {
        self::assertSame([], RollbackContracts::unresolved([]));
    }

    private static function promising(string $name, string $inverse): Operation
    {
        return new Operation(
            name: $name,
            effects: new EffectProfile(
                Mutation::Persistent,
                Externality::None,
                Reversibility::Guaranteed,
                Authority::WriteAsUser,
                subject: Subject::Configuration,
                rollbackContract: $inverse,
            ),
            description: 'x',
            handler: static fn (): array => [],
            inputSchema: ['type' => 'object'],
            mutating: true,
        );
    }

    private static function reading(string $name): Operation
    {
        return new Operation(
            name: $name,
            effects: EffectProfile::readOnly(),
            description: 'x',
            handler: static fn (): array => [],
            inputSchema: ['type' => 'object'],
        );
    }
}
