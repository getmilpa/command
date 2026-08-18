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
 * THE ONE INVARIANT: no later layer may RAISE the grade this carries. `verified` is true only when a
 * proof travels with it — a method and an issuer. A bare «verified: true» with nothing behind it is
 * the editable covers of evidence/0249, one field over, and {@see fromArray()} refuses to honour it.
 * A translation downstream may lower confidence; it may never invent it.
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
    public function __construct(
        public string $principal,
        public bool $verified = false,
        public ?string $channel = null,
        public array $scopes = [],
        public ?string $method = null,
        public ?string $issuer = null,
    ) {
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
        );
    }

    /**
     * Reconstruct from a payload, and NEVER raise the grade.
     *
     * `verified` is honoured only when the proof travels too: a method AND an issuer. This is the
     * invariant of decisions/0055 enforced at the one place a forger would attack — a stored blob
     * they can edit. Editing `verified` to true without also fabricating a coherent proof buys
     * nothing, exactly as editing `covers` bought nothing once the certificate was signed.
     *
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): ?self
    {
        $principal = \is_string($row['principal'] ?? null) ? $row['principal'] : '';
        if ($principal === '') {
            return null;
        }
        $method = \is_string($row['method'] ?? null) ? $row['method'] : null;
        $issuer = \is_string($row['issuer'] ?? null) ? $row['issuer'] : null;
        $conPrueba = $method !== null && $issuer !== null;

        return new self(
            principal: $principal,
            // The grade can only be as high as the proof present — asserting true is not enough.
            verified: ($row['verified'] ?? false) === true && $conPrueba,
            channel: \is_string($row['channel'] ?? null) ? $row['channel'] : null,
            scopes: array_values(array_filter((array) ($row['scopes'] ?? []), \is_string(...))),
            method: $method,
            issuer: $issuer,
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
