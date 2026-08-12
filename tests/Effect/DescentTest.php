<?php

/**
 * This file is part of milpa/command — the atom: one declared Operation, projected by every surface.
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
use Milpa\Command\Effect\Descent;
use Milpa\Command\Effect\EffectProfile;
use Milpa\Command\Effect\Externality;
use Milpa\Command\Effect\Mutation;
use Milpa\Command\Effect\Reversibility;
use Milpa\Command\Effect\Subject;
use PHPUnit\Framework\TestCase;

/**
 * The battery greenhouse decisions/0029 froze before this class existed.
 *
 * The second case is the control and it is what makes the rest mean anything: WITHOUT the argument
 * the ceiling must stay where it was. A descent that applies either way is not lowering on demand,
 * it is a lighter ceiling declared through a longer sentence.
 */
final class DescentTest extends TestCase
{
    /** 1 · with the argument, the declared destination is what the call carries. */
    public function testTheDeclaredArgumentLowersTheCeilingForThatCall(): void
    {
        $techo = $this->instala();

        self::assertSame(Subject::None, $techo->forCall(['dry_run' => true])->subject);
        self::assertSame(Mutation::None, $techo->forCall(['dry_run' => true])->mutation);
    }

    /** 2 · THE CONTROL: without it, nothing moves. */
    public function testWithoutTheArgumentTheCeilingStaysWhereItWas(): void
    {
        $techo = $this->instala();

        self::assertSame(Subject::Executable, $techo->forCall([])->subject);
        self::assertSame(Subject::Executable, $techo->forCall(['other' => true])->subject);
    }

    /** 3 · a descent with no reason lowers nothing — failing upwards is the only affordable failure. */
    public function testADescentWithoutAReasonDoesNotLower(): void
    {
        $techo = $this->instala(razon: '   ');

        self::assertSame(Subject::Executable, $techo->forCall(['dry_run' => true])->subject);
    }

    /** 4 · a «descent» to a HIGHER ceiling is not a back door for climbing quietly. */
    public function testADescentThatRaisesAnythingIsIgnored(): void
    {
        $suave = new EffectProfile(
            mutation: Mutation::None,
            externality: Externality::None,
            reversibility: Reversibility::Guaranteed,
            authority: Authority::Read,
            subject: Subject::None,
            rollbackContract: 'reads only',
            descents: [new Descent('escalate', true, new EffectProfile(
                mutation: Mutation::Persistent,
                externality: Externality::ThirdParty,
                reversibility: Reversibility::Irreversible,
                authority: Authority::Privileged,
                subject: Subject::Executable,
            ), 'claims to lower while raising every axis')],
        );

        self::assertSame(Subject::None, $suave->forCall(['escalate' => true])->subject);
        self::assertSame(Authority::Read, $suave->forCall(['escalate' => true])->authority);
    }

    /** 5 · the same argument carrying another value does not trigger it. */
    public function testAnotherValueDoesNotTriggerTheDescent(): void
    {
        $techo = $this->instala();

        self::assertSame(Subject::Executable, $techo->forCall(['dry_run' => false])->subject);
        self::assertSame(Subject::Executable, $techo->forCall(['dry_run' => 'yes'])->subject);
    }

    /** The shape of the operation that forced this: installs code, unless it is only rehearsing. */
    private function instala(string $razon = 'the handler prints the command it would run and returns before running it'): EffectProfile
    {
        return new EffectProfile(
            mutation: Mutation::Persistent,
            externality: Externality::ThirdParty,
            reversibility: Reversibility::Compensatable,
            authority: Authority::Privileged,
            subject: Subject::Executable,
            descents: [new Descent(
                argument: 'dry_run',
                whenValue: true,
                to: new EffectProfile(
                    mutation: Mutation::None,
                    externality: Externality::None,
                    reversibility: Reversibility::Guaranteed,
                    authority: Authority::Read,
                    subject: Subject::None,
                    rollbackContract: 'nothing ran, so there is nothing to undo',
                ),
                because: $razon,
            )],
        );
    }
}
