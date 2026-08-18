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
    /** 1 · a channel that proved identity by credential yields verified facts. */
    public function testAProvenPrincipalYieldsVerifiedFacts(): void
    {
        $principal = new VerifiedPrincipal(
            principal: 'key:ABCD1234',
            verified: true,
            channel: 'cli-sign',
            scopes: ['probes:run'],
            method: 'gpg-detached',
            issuer: 'local-keyring',
        );

        $facts = $principal->toFacts();

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
     * 3 · F-3 · NO LATER LAYER RAISES THE GRADE.
     *
     * Reconstructing from a payload that claims verified:true does not make it so unless the payload
     * ALSO carries the proof (method + issuer). A bare «verified: true» is exactly the editable
     * covers of evidence/0249, one field over.
     */
    public function testReconstructionCannotRaiseVerificationWithoutProof(): void
    {
        $sinPrueba = VerifiedPrincipal::fromArray(['principal' => 'key:X', 'verified' => true, 'channel' => 'c']);

        self::assertNotNull($sinPrueba);
        self::assertFalse($sinPrueba->toFacts()->verified, 'verified:true sin método ni emisor no es verificación');
    }

    /** 4 · with the proof present, reconstruction preserves the grade — it carries, it does not invent. */
    public function testReconstructionPreservesAProvenGrade(): void
    {
        $conPrueba = VerifiedPrincipal::fromArray([
            'principal' => 'key:X', 'verified' => true, 'channel' => 'cli-sign',
            'scopes' => ['probes:run'], 'method' => 'gpg-detached', 'issuer' => 'local-keyring',
        ]);

        self::assertNotNull($conPrueba);
        self::assertTrue($conPrueba->toFacts()->verified);
    }

    /** 5 · a proof cannot be minted by asserting the fields: a channel builds it, a payload only echoes it. */
    public function testFactsRoundTripThroughTheirOwnFingerprint(): void
    {
        $principal = new VerifiedPrincipal('key:X', true, 'cli-sign', ['probes:run'], 'gpg-detached', 'local-keyring');
        $ida = $principal->toFacts();
        $vuelta = VerifiedPrincipal::fromArray($principal->toArray())->toFacts();

        self::assertSame($ida->fingerprint(), $vuelta->fingerprint());
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
