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

namespace Milpa\Command\Standing;

/**
 * What the judge said of one call under one grant (greenhouse decisions/0615).
 *
 * Three answers, and they must not be confused: the grant does not name this call (it is nobody's
 * refusal — the call goes on to whatever judged it before); it names the call and refuses it; it names
 * the call and lets it pass. There is no fourth: a verdict never grants, widens or restores anything.
 *
 * `reason` is the stable word for the failure, for a host that words its own refusals or translates
 * them; `sentence` is the English one, written for whoever is reading the refusal.
 */
final readonly class StandingVerdict
{
    /**
     * @param list<ConditionReceipt> $cited the receipts a passing call ran on, one per condition
     */
    private function __construct(
        public ?StandingCheck $failed,
        public string $reason,
        public string $sentence,
        public array $cited = [],
    ) {
    }

    /**
     * The call passes, on these receipts.
     *
     * @param list<ConditionReceipt> $cited
     */
    public static function pass(string $sentence, array $cited): self
    {
        return new self(null, 'passed', $sentence, $cited);
    }

    /** The call stops at this check. */
    public static function refuse(StandingCheck $failed, string $reason, string $sentence): self
    {
        return new self($failed, $reason, $sentence);
    }

    /** Whether every check passed. */
    public function passes(): bool
    {
        return $this->failed === null;
    }

    /** Whether the grant names this call at all — false means it is not this grant's to judge. */
    public function named(): bool
    {
        return $this->failed !== StandingCheck::Named;
    }
}
