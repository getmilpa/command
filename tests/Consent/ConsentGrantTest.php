<?php

declare(strict_types=1);

namespace Milpa\Command\Tests\Consent;

use Milpa\Command\Consent\ConsentGrant;
use Milpa\Command\Consent\OperationId;
use PHPUnit\Framework\TestCase;

/**
 * Rod's battery, frozen in greenhouse decisions/0030, and unrunnable until this class existed.
 *
 * Three of its four cases needed a grant to pass through several representations, and there was no
 * object to pass — only a list of strings in an extra bag. That three of four were unrunnable was
 * the measure of how much was missing (greenhouse evidence/0177).
 *
 *     «El consentimiento ocurre una vez; todo lo demás debería ser transporte, evidencia o
 *     proyección de ese mismo acto.»
 */
final class ConsentGrantTest extends TestCase
{
    /**
     * 1 · THE SAME consent through several projections of the same OperationId → identical verdict.
     */
    public function testOneConsentCoversEveryProjectionOfTheSameAct(): void
    {
        $grant = $this->grantDe('config.set');

        foreach (['config.set', 'config:set', 'config_set', 'CONFIG:SET'] as $comoLoEscriben) {
            self::assertTrue($grant->covers($comoLoEscriben), "no cubrió «{$comoLoEscriben}»");
        }
    }

    /** 2 · another operation is another consent — a grant is not a master key. */
    public function testAnotherOperationIsNotCovered(): void
    {
        self::assertFalse($this->grantDe('config.set')->covers('plugins.register'));
    }

    /** 2b · another session is another context. */
    public function testAnotherSessionIsNotCovered(): void
    {
        $grant = new ConsentGrant(
            operation: new OperationId('config.set'),
            principal: 'cli:rod@cm4070',
            session: 'ses-A',
            grantedAt: new \DateTimeImmutable('2026-08-13 10:00:00'),
            provenance: 'session.question_answered',
        );

        self::assertTrue($grant->covers('config.set', [], 'ses-A'));
        self::assertFalse($grant->covers('config.set', [], 'ses-B'));
    }

    /** 2c · substantive arguments: consenting to one value is not consenting to another. */
    public function testDifferentArgumentsAreNotCovered(): void
    {
        $grant = new ConsentGrant(
            operation: new OperationId('config.set'),
            principal: 'cli:rod@cm4070',
            session: 'ses-A',
            grantedAt: new \DateTimeImmutable('2026-08-13 10:00:00'),
            provenance: 'session.question_answered',
            arguments: ['key' => 'agent.treeBudget', 'value' => 7],
        );

        self::assertTrue($grant->covers('config_set', ['key' => 'agent.treeBudget', 'value' => 7]));
        self::assertFalse($grant->covers('config_set', ['key' => 'agent.treeBudget', 'value' => 999]));
        self::assertFalse($grant->covers('config_set', ['key' => 'agent.instructions', 'value' => 7]));
    }

    /**
     * 3 · changing ONLY the spelling does not change the verdict.
     *
     * This is the case that separates identity from UI, and the one that makes the earlier textual
     * normalisation unnecessary rather than merely tidy.
     */
    public function testSpellingAloneNeverChangesTheVerdict(): void
    {
        $grant = $this->grantDe('plugins.register');

        $veredictos = array_map(
            static fn (string $g): bool => $grant->covers($g),
            ['plugins.register', 'plugins:register', 'plugins_register'],
        );

        self::assertSame([true, true, true], $veredictos);
    }

    /** A grant with no arguments covers the act in its session, and says so by shape. */
    public function testAGrantWithoutArgumentsCoversTheActItself(): void
    {
        self::assertTrue($this->grantDe('config.set')->covers('config_set', ['key' => 'lo que sea']));
    }

    /** The evidence line names how the yes was earned, so no consumer has to earn it again. */
    public function testTheEvidenceNamesItsProvenance(): void
    {
        self::assertStringContainsString('session.question_answered', $this->grantDe('config.set')->evidence());
    }

    private function grantDe(string $operacion): ConsentGrant
    {
        return new ConsentGrant(
            operation: new OperationId($operacion),
            principal: 'cli:rod@cm4070',
            session: 'ses-A',
            grantedAt: new \DateTimeImmutable('2026-08-13 10:00:00'),
            provenance: 'session.question_answered',
        );
    }
}
