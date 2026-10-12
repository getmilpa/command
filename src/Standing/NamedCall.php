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

use Milpa\Command\Consent\OperationId;

/**
 * An operation, and the arguments a document fixes for it (greenhouse decisions/0615).
 *
 * A standing grant never says «anything like this»: it names each operation whole, by its identity,
 * and the values a call must carry to be the act that was meant. The same shape names the act a
 * grant covers, the operation that measures one of its conditions, and the one that watches its
 * falsifier — three places, one way of naming a call.
 *
 * TWO WAYS OF MATCHING, AND WHICH PLACE USES WHICH.
 *
 *   · {@see self::matches()} — OPEN. The call is that operation and carries each fixed argument with
 *     exactly that value; what the document does not fix is free. It is what lets one grant cover a
 *     CLASS of acts: the candidate changes on every call. Only `covers` is read this way.
 *   · {@see self::matchesExactly()} — CLOSED. Besides the fixed arguments, a call may carry only the
 *     ones named in `free` (the argument that says WHICH candidate). An instrument and a watcher are
 *     read this way, because an open match fails open there: a later run with an argument nobody
 *     described — `--lenient`, `--skip=…` — would speak for the condition, and a green one would
 *     cover a red one (greenhouse decisions/0615, «Lo que la revisión encontró», 5).
 */
final readonly class NamedCall
{
    /**
     * How an argument is named: letters and underscores, starting with a letter, at most 64 of them.
     *
     * A name a person can read, and no other. «channel » with a space after it, or with a letter of another
     * alphabet that looks the same, is a name no operation gives an argument: a grant that fixed it would cover a
     * call carrying it BESIDE the real one, whatever the real one said. Measured on the published catalogue, this is
     * the narrowest alphabet that refuses none of the 76 names its operations declare.
     */
    private const ARGUMENT = '/^[A-Za-z][A-Za-z_]{0,63}$/D';

    public OperationId $operation;

    /** @var array<string, string|int|bool> */
    public array $arguments;

    /** @var list<string> */
    public array $free;

    /**
     * @param array<array-key, mixed> $arguments the values a call must carry, by argument name
     * @param array<array-key, mixed> $free      the arguments a call may also carry, with any value — for a closed
     *                                           match: typically the one that says which candidate
     */
    public function __construct(OperationId|string $operation, array $arguments = [], array $free = [])
    {
        $id = $operation instanceof OperationId ? $operation : new OperationId($operation);
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\.[a-z0-9]+(?:-[a-z0-9]+)*)*$/D', $id->canonical) !== 1) {
            throw new \InvalidArgumentException(\sprintf(
                '%s does not name one operation. A standing grant names each operation whole — no pattern, no wildcard: what is not named is not covered.',
                Canonical::shown($operation instanceof OperationId ? $operation->canonical : $operation),
            ));
        }

        $fixed = [];
        foreach ($arguments as $name => $value) {
            if (!\is_string($name) || preg_match(self::ARGUMENT, $name) !== 1 || !(\is_string($value) || \is_int($value) || \is_bool($value))) {
                throw new \InvalidArgumentException(\sprintf(
                    'an argument fixed for «%s» is a name — letters and underscores, starting with a letter — and one plain value: text, a whole number, or true/false; got %s',
                    $id->canonical,
                    Canonical::shown([$name => $value]),
                ));
            }
            $fixed[$name] = $value;
        }
        ksort($fixed, \SORT_STRING);

        $open = [];
        foreach ($free as $name) {
            if (!\is_string($name) || preg_match(self::ARGUMENT, $name) !== 1 || \array_key_exists($name, $fixed)) {
                throw new \InvalidArgumentException(\sprintf(
                    '«free» of «%s» lists the names of the arguments a call may carry with any value — each in letters and underscores, starting with a letter, and never one it already fixes; got %s',
                    $id->canonical,
                    Canonical::shown($name),
                ));
            }
            $open[$name] = true;
        }
        $open = array_map(strval(...), array_keys($open));
        sort($open, \SORT_STRING);

        $this->operation = $id;
        $this->arguments = $fixed;
        $this->free = $open;
    }

    /**
     * Whether a call is the one this names: that operation, carrying every fixed argument with that value.
     *
     * @param array<string, mixed> $arguments
     */
    public function matches(OperationId|string $operation, array $arguments): bool
    {
        if (!$this->operation->is($operation)) {
            return false;
        }
        foreach ($this->arguments as $name => $value) {
            if (!\array_key_exists($name, $arguments) || $arguments[$name] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a call is EXACTLY the one this describes: that operation, every fixed argument with that value, and
     * no argument besides them except the ones left free.
     *
     * @param array<string, mixed> $arguments
     */
    public function matchesExactly(OperationId|string $operation, array $arguments): bool
    {
        if (!$this->matches($operation, $arguments)) {
            return false;
        }
        foreach (array_keys($arguments) as $name) {
            if (!\array_key_exists($name, $this->arguments) && !\in_array($name, $this->free, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The name as data. `arguments` and `free` appear only when they say something, so one call has one spelling.
     *
     * @return array{operation: string, arguments?: array<string, string|int|bool>, free?: list<string>}
     */
    public function toArray(): array
    {
        return ['operation' => $this->operation->canonical]
            + ($this->arguments === [] ? [] : ['arguments' => $this->arguments])
            + ($this->free === [] ? [] : ['free' => $this->free]);
    }

    /** The inverse of {@see self::toArray()}; `$where` names the place in the document, for the refusal. */
    public static function fromArray(mixed $data, string $where): self
    {
        if (!\is_array($data) || !\is_string($data['operation'] ?? null)) {
            throw new \InvalidArgumentException(\sprintf('%s names an operation: {"operation": "…"} and, when it fixes any, {"arguments": {…}}', $where));
        }
        Canonical::only($data, ['operation', 'arguments', 'free'], $where);
        $arguments = $data['arguments'] ?? [];
        if (!\is_array($arguments)) {
            throw new \InvalidArgumentException(\sprintf('%s fixes its arguments by name: {"arguments": {"name": value}}', $where));
        }
        $free = $data['free'] ?? [];
        if (!\is_array($free) || !array_is_list($free)) {
            throw new \InvalidArgumentException(\sprintf('%s gives «free» as a list of argument names', $where));
        }

        return new self($data['operation'], $arguments, $free);
    }
}
