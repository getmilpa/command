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
 * What would show a standing grant was wrong to give, and what watches for it (greenhouse decisions/0615).
 *
 * A position whose falsifier nothing watches is not granted (greenhouse decisions/0613 §1): a flag
 * that is gone looks exactly like a flag that is obeyed. So the falsifier names `watcher` — the
 * operation of the house that looks — and `withinSeconds`, how recently it must have said it looked
 * and found nothing. A watcher that went quiet is not green: past that window the grant lends nothing.
 */
final readonly class Falsifier
{
    public function __construct(
        public string $name,
        public string $statement,
        public NamedCall $watcher,
        public int $withinSeconds,
    ) {
        if (trim($name) === '' || trim($statement) === '') {
            throw new \InvalidArgumentException('a falsifier names itself and says what would show the grant wrong');
        }
        if ($withinSeconds < 1) {
            throw new \InvalidArgumentException("falsifier «{$name}» needs «within_seconds»: how recently its watcher must have given a sign of life, as a whole number of seconds above zero");
        }
    }

    /**
     * The falsifier as data.
     *
     * @return array{name: string, statement: string, watcher: array<string, mixed>, within_seconds: int}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'statement' => $this->statement, 'watcher' => $this->watcher->toArray(), 'within_seconds' => $this->withinSeconds];
    }

    /** The inverse of {@see self::toArray()}. */
    public static function fromArray(mixed $data): self
    {
        if (!\is_array($data)) {
            throw new \InvalidArgumentException(
                'a standing grant needs a falsifier that something watches: {"name", "statement", "watcher": {"operation": "…"}, "within_seconds"}. '
                . 'A position nobody watches is not granted.',
            );
        }
        Canonical::only($data, ['name', 'statement', 'watcher', 'within_seconds'], 'the falsifier');
        $name = Canonical::text($data, 'name', 'the falsifier');
        $within = $data['within_seconds'] ?? null;

        return new self(
            $name,
            Canonical::text($data, 'statement', "falsifier «{$name}»"),
            NamedCall::fromArray($data['watcher'] ?? null, "falsifier «{$name}», in «watcher»,"),
            \is_int($within) ? $within : 0,
        );
    }
}
