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

namespace Milpa\Command\Effect;

/**
 * What a channel proved about who is calling — and exactly how much of it (greenhouse decisions/0055).
 *
 * This sits at the boundary decisions/0054 drew: identity produces FACTS about identity, the policy
 * produces CONSEQUENCES of authority. So this object has scopes and a verification story, and it has
 * no authority field. An authenticator that named authority would have made the channel a legislator
 * — the collapse decisions/0031 separated between a token and a principal.
 *
 * THE ONE INVARIANT: a verified grade is PRODUCED by re-verifying a proof, never READ from a stored
 * field. greenhouse evidence/0254 forged the old version — verified:true with a plausible method and
 * issuer, hand-written — and authority came down, because a string is not a proof. So the grade has
 * exactly one door, {@see admit()}, which a channel calls after it has re-verified. {@see fromArray()}
 * reconstructs the ASSERTION and NEVER carries the grade across the data boundary: what is persisted
 * is the signed assertion, re-verified on admission, which is the receipt doctrine of decisions/0053
 * made mandatory for identity — a verified fact is a receipt, not currency.
 */
final readonly class VerifiedPrincipal
{
    /**
     * @param string       $principal who, with its origin in front: `cli:rod@laptop`, `key:ABCD…`
     * @param bool         $verified  whether a proof actually backs this — never asserted alone
     * @param string|null  $channel   the surface it arrived through
     * @param list<string> $scopes    what is verifiable, not what was asked for
     * @param string|null  $method    how it was proved (e.g. `gpg-detached`) — half of the proof
     * @param string|null  $issuer    who did the proving (e.g. a keyring, an IdP) — the other half
     */
    private function __construct(
        public string $principal,
        public bool $verified = false,
        public ?string $channel = null,
        public array $scopes = [],
        public ?string $method = null,
        public ?string $issuer = null,
    ) {
    }

    /**
     * The one door to a verified grade: a channel calls this AFTER it has re-verified a proof, live.
     *
     * The constructor is private precisely so no caller can hand-build a verified principal from
     * data — the grade cannot exist without a channel having just checked the proof that backs it.
     *
     * @param list<string> $scopes
     */
    public static function admit(string $principal, string $channel, array $scopes, string $method, string $issuer): self
    {
        return new self($principal, true, $channel, $scopes, $method, $issuer);
    }

    /**
     * The os-user a terminal reports: a fact, but an UNVERIFIED one, by construction.
     *
     * It is the best hint available with no credential behind it — anyone at that terminal is it —
     * so it can never be talked up. There is no proof to carry, so `verified` stays false forever.
     */
    public static function fromTerminal(?string $user, ?string $host): self
    {
        return new self(
            principal: 'cli:' . ($user ?? 'desconocido') . '@' . ($host ?? 'desconocido'),
            verified: false,
            channel: 'cli',
        );  // never verified: there is no proof to re-check
    }

    /**
     * Reconstruct the ASSERTION from a payload — and NEVER the grade.
     *
     * evidence/0254 forged exactly what an earlier version honoured: verified:true with a plausible
     * method and issuer, hand-written into a blob. So this returns an UNVERIFIED principal always,
     * whatever the row claims. The grade is not data to be carried; it is produced by {@see admit()}
     * when a channel re-verifies. A caller that wants the grade must re-admit through its proof, not
     * read it back from storage.
     *
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): ?self
    {
        $principal = \is_string($row['principal'] ?? null) ? $row['principal'] : '';
        if ($principal === '') {
            return null;
        }

        return new self(
            principal: $principal,
            verified: false,
            channel: \is_string($row['channel'] ?? null) ? $row['channel'] : null,
            scopes: array_values(array_filter((array) ($row['scopes'] ?? []), \is_string(...))),
            method: \is_string($row['method'] ?? null) ? $row['method'] : null,
            issuer: \is_string($row['issuer'] ?? null) ? $row['issuer'] : null,
        );
    }

    /**
     * The payload form, carrying the proof alongside the grade so {@see fromArray()} can refuse to
     * honour a grade whose proof did not survive the round trip.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'principal' => $this->principal,
            'verified' => $this->verified,
            'channel' => $this->channel,
            'scopes' => $this->scopes,
            'method' => $this->method,
            'issuer' => $this->issuer,
        ];
    }

    /** The facts the authority policy consumes — carrying exactly what was proved, no more. */
    public function toFacts(): ContextFacts
    {
        return new ContextFacts(
            principal: $this->principal,
            verified: $this->verified,
            channel: $this->channel,
            scopes: $this->scopes,
        );
    }
}
