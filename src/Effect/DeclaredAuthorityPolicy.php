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
 * An institutional policy declared as a rule table — in reviewed code, like the certifier's public
 * key (greenhouse decisions/0051): a path the forgery of `evidence/0249` does not control.
 *
 * Deliberately small. A rule says: for THIS operation, a principal whose verified scopes include ALL
 * of these gets THIS effective authority. Anything the table does not say is a refusal, not a
 * default — `evidence/0237` measured that the only gate treating the bot and the human alike was the
 * one that denied by default.
 */
final readonly class DeclaredAuthorityPolicy implements AuthorityPolicy
{
    /**
     * @param string                                                           $id    who this policy is, for the receipt
     * @param array<string, array{scopes: list<string>, authority: Authority}> $rules operation → what its rule demands and grants
     */
    public function __construct(
        public string $id,
        public array $rules,
    ) {
    }

    public function judge(ContextFacts $facts, CallSubject $subject): ?AuthorityClaim
    {
        // Hearsay judges nothing: the flag is a fact ABOUT the facts, and it gates everything.
        if (! $facts->verified) {
            return null;
        }

        $regla = $this->rules[$subject->operation] ?? null;
        if ($regla === null) {
            return null;
        }

        foreach ($regla['scopes'] as $scope) {
            if (! \in_array($scope, $facts->scopes, true)) {
                return null;
            }
        }

        return new AuthorityClaim(
            authority: $regla['authority'],
            operation: $subject->operation,
            policyId: $this->id,
            policyDigest: $this->digest(),
            factsFingerprint: $facts->fingerprint(),
        );
    }

    public function digest(): string
    {
        $reglas = [];
        foreach ($this->rules as $operacion => $regla) {
            $scopes = $regla['scopes'];
            sort($scopes);
            $reglas[$operacion] = ['authority' => $regla['authority']->value, 'scopes' => $scopes];
        }
        ksort($reglas);

        return 'sha256:' . hash('sha256', (string) json_encode(['id' => $this->id, 'rules' => $reglas]));
    }
}
