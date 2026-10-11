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
 * One condition of a standing grant, and the operation whose result measures it (greenhouse decisions/0615).
 *
 * A condition nobody can measure does not fit in a grant: it is a sentence, and a sentence depends
 * on someone obeying it. So each one names `by` — the operation of the house whose run, over the very
 * content the act touches, says whether it holds. The house writes that reading down itself; nothing
 * lets a caller hand one in.
 */
final readonly class GrantCondition
{
    public function __construct(
        public string $name,
        public string $statement,
        public NamedCall $by,
    ) {
        if (trim($name) === '' || trim($statement) === '') {
            throw new \InvalidArgumentException('a condition names itself and says what must hold — one without a name or a statement is a claim nobody can check');
        }
    }

    /**
     * The condition as data.
     *
     * @return array{name: string, statement: string, by: array<string, mixed>}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'statement' => $this->statement, 'by' => $this->by->toArray()];
    }

    /** The inverse of {@see self::toArray()}. */
    public static function fromArray(mixed $data): self
    {
        if (!\is_array($data)) {
            throw new \InvalidArgumentException('a condition is {"name", "statement", "by": {"operation": "…"}}');
        }
        Canonical::only($data, ['name', 'statement', 'by'], 'a condition');
        $name = Canonical::text($data, 'name', 'a condition');

        return new self($name, Canonical::text($data, 'statement', "condition «{$name}»"), NamedCall::fromArray($data['by'] ?? null, "condition «{$name}», in «by»,"));
    }
}
