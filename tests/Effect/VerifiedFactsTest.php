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

use Milpa\Command\Effect\ContextFacts;
use Milpa\Command\Effect\VerifiedPrincipal;
use PHPUnit\Framework\TestCase;

/**
 * The battery greenhouse decisions/0055 froze: a channel delivers identity facts with provenance,
 * and no later layer may raise the verification grade it received.
 *
 * `evidence/0207` measured sessions born ownerless and the channel's only Principal arriving
 * verified:false; `decisions/0054` then built a judge that only judges verified facts. This is the
 * translation from what a channel proved into the ContextFacts that judge consumes — and its whole
 * job is to carry the proof faithfully, never to improve it.
 */
final class VerifiedFactsTest extends TestCase
{
    /** 1 · a channel that re-verified a proof, live, yields verified facts. */
    public function testAnAdmittedPrincipalYieldsVerifiedFacts(): void
    {
        $facts = VerifiedPrincipal::admit('key:ABCD1234', 'cli-sign', ['probes:run'], 'gpg-detached', 'local-keyring')->toFacts();

        self::assertTrue($facts->verified);
        self::assertSame('key:ABCD1234', $facts->principal);
        self::assertSame(['probes:run'], $facts->scopes);
    }

    /** 2 · a terminal's os-user is a fact, but an unverified one — the useful, honest default. */
    public function testATerminalPrincipalIsUnverified(): void
    {
        $principal = VerifiedPrincipal::fromTerminal('rod', 'laptop');

        self::assertFalse($principal->toFacts()->verified);
        self::assertSame('cli:rod@laptop', $principal->principal);
    }

    /**
     * 3 · F-2 of evidence/0254, and the whole reason for this amendment: fromArray CANNOT mint a
     * verified grade, no matter how plausible the strings.
     *
     * The measurement forged exactly this — verified:true with a plausible method and issuer — and
     * the old code honoured it, so authority came down on a hand-written blob. A string is not a
     * proof. fromArray reconstructs the ASSERTION; the grade is decided elsewhere, by re-verifying.
     */
    public function testFromArrayCannotMintAVerifiedGrade(): void
    {
        $forjado = VerifiedPrincipal::fromArray([
            'principal' => 'key:X', 'verified' => true, 'channel' => 'cli-sign',
            'scopes' => ['probes:run'], 'method' => 'gpg-detached', 'issuer' => 'local-keyring',
        ]);

        self::assertNotNull($forjado);
        self::assertFalse($forjado->toFacts()->verified, 'un blob de datos nunca produce verified:true');
    }

    /** 4 · the ONLY door to a verified grade is admit(): a channel that re-verified a proof, live. */
    public function testOnlyAdmissionProducesAVerifiedGrade(): void
    {
        $admitido = VerifiedPrincipal::admit(
            principal: 'key:X',
            channel: 'lab-idp',
            scopes: ['probes:run'],
            method: 'ed25519-detached',
            issuer: 'lab-idp',
        );

        self::assertTrue($admitido->toFacts()->verified);
        self::assertSame('ed25519-detached', $admitido->method);
    }

    /** 5 · a re-read of an admitted principal still cannot carry the grade across the data boundary. */
    public function testTheGradeDoesNotSurviveSerialisation(): void
    {
        $admitido = VerifiedPrincipal::admit('key:X', 'lab-idp', ['probes:run'], 'ed25519-detached', 'lab-idp');

        self::assertTrue($admitido->toFacts()->verified);
        self::assertFalse(VerifiedPrincipal::fromArray($admitido->toArray())->toFacts()->verified, 'lo persistido es la aserción, no el grado');
    }

    /** 7 · a payload with no principal is not a principal — the boundary refuses to build one. */
    public function testAPayloadWithoutAPrincipalIsRefused(): void
    {
        self::assertNull(VerifiedPrincipal::fromArray(['verified' => true, 'method' => 'x', 'issuer' => 'y']));
    }

    /** 6 · the terminal path can never be talked up: fromTerminal is verified:false by construction. */
    public function testTheTerminalPathHasNoProofToCarry(): void
    {
        $arr = VerifiedPrincipal::fromTerminal('rod', 'laptop')->toArray();

        self::assertArrayNotHasKey('method', array_filter($arr, static fn ($v) => $v !== null));
        self::assertFalse(VerifiedPrincipal::fromArray($arr)->toFacts()->verified);
    }
}
