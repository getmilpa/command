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

namespace Milpa\Command;

/**
 * One condition an operation DECLARES about itself — a named precondition or postcondition.
 *
 * It exists so a caller can ask «what should happen?» before executing and «did it?» after, without
 * a model inventing either answer: the operation states its conditions once, as data, and every
 * surface reads the same statement. It is deliberately just {name, description}, because this value
 * object is the DECLARATION and never the proof: the truth of a precondition lives in the handler
 * that refuses when it is violated, and the truth of a postcondition lives in the verifier that
 * checks it after the run. A package that declares a condition owes the test tying the declared name
 * to that enforcement — declaring what nothing enforces is the lie this shape makes checkable.
 */
final readonly class DeclaredCondition
{
    /**
     * @param string $name        stable identifier of the condition, the same name the handler's
     *                            refusal or the verifier's report uses (e.g. `entity_file`,
     *                            `phpunit-installed`)
     * @param string $description what must hold, in one line a human or an agent can read
     */
    public function __construct(
        public string $name,
        public string $description,
    ) {
        if (trim($name) === '' || trim($description) === '') {
            throw new \InvalidArgumentException(
                'a declared condition names itself and says what must hold — one without a name or '
                . 'description is a claim nobody can check',
            );
        }
    }

    /**
     * The condition as data — the shape a surface projects and a contract reader returns.
     *
     * @return array{name: string, description: string}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'description' => $this->description];
    }

    /**
     * The inverse of {@see self::toArray()}: a condition back from the array a consumer stored.
     *
     * Both keys are required and must be strings — a partial array is not a declaration, and reading
     * one as if it were would turn «nobody declared this» into «somebody declared something» silently.
     *
     * @param array<string, mixed> $data as produced by toArray()
     */
    public static function fromArray(array $data): self
    {
        $name = $data['name'] ?? null;
        $description = $data['description'] ?? null;
        if (!\is_string($name) || !\is_string($description)) {
            throw new \InvalidArgumentException(sprintf(
                'a stored condition needs a string «name» and a string «description»; got %s and %s',
                json_encode($name) ?: get_debug_type($name),
                json_encode($description) ?: get_debug_type($description),
            ));
        }

        return new self($name, $description);
    }
}
