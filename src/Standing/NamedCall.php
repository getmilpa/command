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
 * ITS LIMIT IS PART OF THE CONTRACT. A call matches when it is that operation and carries EACH fixed
 * argument with exactly that value; arguments the document does not fix are free. That is what lets
 * one grant cover a class — the candidate changes on every call — and it is also why an operation
 * whose own argument can weaken it must have that argument fixed here, by whoever writes the grant.
 */
final readonly class NamedCall
{
    public OperationId $operation;

    /** @var array<string, string|int|bool> */
    public array $arguments;

    /**
     * @param array<array-key, mixed> $arguments the values a call must carry, by argument name
     */
    public function __construct(OperationId|string $operation, array $arguments = [])
    {
        $id = $operation instanceof OperationId ? $operation : new OperationId($operation);
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\.[a-z0-9]+(?:-[a-z0-9]+)*)*$/', $id->canonical) !== 1) {
            throw new \InvalidArgumentException(\sprintf(
                '«%s» does not name one operation. A standing grant names each operation whole — no pattern, no wildcard: what is not named is not covered.',
                $operation instanceof OperationId ? $operation->canonical : $operation,
            ));
        }

        $fixed = [];
        foreach ($arguments as $name => $value) {
            if (!\is_string($name) || trim($name) === '' || !(\is_string($value) || \is_int($value) || \is_bool($value))) {
                throw new \InvalidArgumentException(\sprintf(
                    'an argument fixed for «%s» is a name and one plain value — text, a whole number, or true/false; got %s',
                    $id->canonical,
                    json_encode([$name => $value]) ?: get_debug_type($value),
                ));
            }
            $fixed[$name] = $value;
        }
        ksort($fixed);

        $this->operation = $id;
        $this->arguments = $fixed;
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
     * The name as data. `arguments` appears only when something is fixed, so one call has one spelling.
     *
     * @return array{operation: string, arguments?: array<string, string|int|bool>}
     */
    public function toArray(): array
    {
        return ['operation' => $this->operation->canonical] + ($this->arguments === [] ? [] : ['arguments' => $this->arguments]);
    }

    /** The inverse of {@see self::toArray()}; `$where` names the place in the document, for the refusal. */
    public static function fromArray(mixed $data, string $where): self
    {
        if (!\is_array($data) || !\is_string($data['operation'] ?? null)) {
            throw new \InvalidArgumentException(\sprintf('%s names an operation: {"operation": "…"} and, when it fixes any, {"arguments": {…}}', $where));
        }
        Canonical::only($data, ['operation', 'arguments'], $where);
        $arguments = $data['arguments'] ?? [];
        if (!\is_array($arguments)) {
            throw new \InvalidArgumentException(\sprintf('%s fixes its arguments by name: {"arguments": {"name": value}}', $where));
        }

        return new self($data['operation'], $arguments);
    }
}
