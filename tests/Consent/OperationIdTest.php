<?php

declare(strict_types=1);

namespace Milpa\Command\Tests\Consent;

use Milpa\Command\Consent\OperationId;
use PHPUnit\Framework\TestCase;

/**
 * Identity belongs to the atom; spelling belongs to the projection.
 *
 * The projections are the point rather than a convenience: they are what lets every surface write
 * the act its own way while the authority compares one thing. A projection nobody exercises is a
 * claim that the surfaces agree, and the coverage floor of this package caught exactly that — three
 * lines written and never run.
 */
final class OperationIdTest extends TestCase
{
    /** The three separators this family's surfaces use all mean the same act. */
    public function testEverySeparatorIsTheSameAct(): void
    {
        $id = new OperationId('config.set');

        self::assertTrue($id->is('config:set'));
        self::assertTrue($id->is('config_set'));
        self::assertTrue($id->is(new OperationId('CONFIG.SET')));
        self::assertFalse($id->is('config.get'));
    }

    /** Each surface gets its own spelling back, from one identity. */
    public function testEachSurfaceProjectsItsOwnSpelling(): void
    {
        $id = new OperationId('plugins_register');

        self::assertSame('plugins.register', $id->canonical);
        self::assertSame('plugins:register', $id->forCli());
        self::assertSame('plugins_register', $id->forTool());
        self::assertSame('plugins.register', (string) $id);
    }

    /** A round trip through any projection lands on the same identity. */
    public function testAProjectionRoundTripsToTheSameIdentity(): void
    {
        $id = new OperationId('agent.answer');

        foreach ([$id->forCli(), $id->forTool(), $id->canonical] as $proyectada) {
            self::assertTrue((new OperationId($proyectada))->is($id));
        }
    }

    /** Stray separators and case do not create a second act. */
    public function testStraySeparatorsDoNotCreateASecondAct(): void
    {
        self::assertSame('config.set', (new OperationId('  Config:Set.  '))->canonical);
    }
}
