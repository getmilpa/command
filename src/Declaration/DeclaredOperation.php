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

namespace Milpa\Command\Declaration;

use Milpa\Command\Operation as OperationContract;

/**
 * Turns a declared class into the SAME `Operation` value object every projector already reads.
 *
 * This is the derivation half of greenhouse decisions/0212. The rule it implements, in one line:
 *
 *   **What changes authority is DECLARED; what a type already says is DERIVED.**
 *
 * So the attributes carry intent — the name, the purpose, what it mutates, whose authority it
 * spends, which argument the human must name — and this class reads the CONSTRUCTOR to derive the
 * input schema and the RETURN TYPE to derive the output one. A `string $name` needs no
 * `['type' => 'string']` written beside it; a PHP enum needs no second list of its own values.
 *
 * ── WHY IT PRODUCES THE OLD VALUE OBJECT AND NOT A NEW ONE ──────────────────────────────────────
 *
 * Because nothing downstream may change. CLI flags, MCP tools, HTTP routes and methods, consent,
 * signatures, the agent's catalogue and `plugins:architecture` all read `Operation` today, and 82
 * declaration sites across the platform build it by hand. Emitting a different type would fork the
 * ecosystem on the day it landed. Emitting the same one makes the two styles peers: the attribute
 * wins where it exists, and the hand-written form dies by attrition rather than by decree.
 *
 * ── WHY IT RUNS ONCE, AT DECLARATION ────────────────────────────────────────────────────────────
 *
 * Reflection is not free, and an operation is declared once per process and invoked many times. So
 * every reflective question — the schema, the target, the collaborators `run()` needs, whether they
 * can even be resolved — is asked HERE, and the handler that comes out closes over plain arrays. A
 * request pays nothing for having been declared this way.
 *
 * ── WHAT IT REFUSES TO GUESS ────────────────────────────────────────────────────────────────────
 *
 * Every refusal below names the class and says what to declare, and every one of them exists because
 * the alternative is a silent lie:
 *
 *   - Neither `#[Mutates]` nor `#[Reads]`. Silence is not «harmless»; an author who forgets would
 *     ship `mutating: false` on an operation that writes.
 *   - A constructor parameter whose type cannot become a schema. Emitting an empty `{}` would tell
 *     every surface «anything goes».
 *   - Two `#[Target]`s. Two named targets is not a stricter contract, it is an ambiguous one.
 *   - A `run()` that needs collaborators with nobody to resolve them — caught at declaration, not on
 *     the first request in production.
 */
final class DeclaredOperation
{
    /** Does this class declare itself an operation? Lets a provider mix both styles in one list. */
    public static function isDeclared(string $class): bool
    {
        return class_exists($class)
            && (new \ReflectionClass($class))->getAttributes(Operation::class) !== [];
    }

    /**
     * Derives the operation a declared class describes — once, at declaration.
     *
     * @param class-string  $class   The declaring class.
     * @param \Closure|null $resolve `fn (string $type): object` — how `run()`'s collaborators are found.
     *
     * @throws DeclarationException when the declaration cannot be derived without guessing
     */
    public static function from(string $class, ?\Closure $resolve = null): OperationContract
    {
        if (!class_exists($class)) {
            throw new DeclarationException("Cannot derive an operation from '{$class}': the class does not exist.");
        }

        $reflection = new \ReflectionClass($class);
        $declaration = self::attribute($reflection, Operation::class);

        if (!$declaration instanceof Operation) {
            throw new DeclarationException(
                "Class {$class} is not a declared operation: add #[Operation(name: '…', description: '…')]."
            );
        }

        $mutates = self::attribute($reflection, Mutates::class);
        $reads = self::attribute($reflection, Reads::class);

        if ($mutates instanceof Mutates && $reads instanceof Reads) {
            throw new DeclarationException(
                "Operation {$class} declares BOTH #[Mutates] and #[Reads]. It is one or the other — "
                . 'an operation that both writes and does not write has no answer to give a consumer.'
            );
        }

        if (!$mutates instanceof Mutates && !$reads instanceof Reads) {
            throw new DeclarationException(
                "Operation {$class} declares neither #[Mutates] nor #[Reads]. Silence is not an answer: "
                . 'an operation that forgot to say it writes would ship as mutating: false and every '
                . 'consumer would believe it. Declare what this one does.'
            );
        }

        if (!$reflection->hasMethod('run') || !$reflection->getMethod('run')->isPublic()) {
            throw new DeclarationException(
                "Operation {$class} has no public run() method — the handler is a method here, with its "
                . 'collaborators injected and its return type declaring the output.'
            );
        }

        [$schema, $parameters, $namedTarget] = self::inputContract($reflection, $class);
        $run = $reflection->getMethod('run');
        $collaborators = self::collaborators($run, $class, $resolve);
        $purpose = self::attribute($reflection, Because::class);
        $needs = self::attribute($reflection, Needs::class);
        $needs = $needs instanceof Needs ? $needs : new Needs();

        return new OperationContract(
            name: $declaration->name,
            description: $purpose instanceof Because
                ? $declaration->description . ' ' . $purpose->purpose
                : $declaration->description,
            handler: self::handler($class, $parameters, $collaborators, $resolve),
            inputSchema: $schema,
            mutating: $mutates instanceof Mutates,
            requiresConfirmation: self::attribute($reflection, Confirms::class) instanceof Confirms,
            scopes: $needs->scopes,
            outputSchema: self::outputContract($run),
            version: $declaration->version,
            path: $declaration->path,
            surfaces: $declaration->surfaces,
            permission: $needs->permission,
            namedTarget: $namedTarget,
            effects: ($mutates ?? $reads)->profile(),
        );
    }

    /**
     * The input schema, the parameter plan the handler closes over, and the named target.
     *
     * @param \ReflectionClass<object> $reflection
     *
     * @return array{0: array<string, mixed>|null, 1: list<array<string, mixed>>, 2: string|null}
     */
    private static function inputContract(\ReflectionClass $reflection, string $class): array
    {
        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return [null, [], null];
        }

        $properties = [];
        $required = [];
        $plan = [];
        $namedTarget = null;

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            [$spec, $enum] = self::specOf($parameter, $class);

            if ($parameter->isDefaultValueAvailable()) {
                $default = $parameter->getDefaultValue();
                $spec['default'] = $default instanceof \BackedEnum ? $default->value : $default;
            } else {
                $required[] = $name;
            }

            if ($parameter->getAttributes(Target::class) !== []) {
                if ($namedTarget !== null) {
                    throw new DeclarationException(
                        "Operation {$class} marks two parameters with #[Target] ('{$namedTarget}' and "
                        . "'{$name}'). A named target is the ONE value the human must name; two of them "
                        . 'is not a stricter contract, it is an ambiguous one.'
                    );
                }
                $namedTarget = $name;
            }

            $properties[$name] = $spec;
            $plan[] = [
                'name' => $name,
                'enum' => $enum,
                'optional' => $parameter->isDefaultValueAvailable(),
            ];
        }

        $schema = ['type' => 'object'];
        if ($required !== []) {
            $schema['required'] = $required;
        }
        $schema['properties'] = $properties;

        return [$schema, $plan, $namedTarget];
    }

    /**
     * One parameter's JSON-Schema spec, and the enum class behind it when there is one.
     *
     * The shapes emitted here are the ones the surfaces actually coerce ({@see
     * \Milpa\Console\SchemaCoercer}): a single string `type`, an optional `enum` list, an optional
     * `default`. A nullable parameter is emitted as its non-null type and shows its nullability by
     * being absent from `required` — a union type in `type` would be silently read back as 'string',
     * which is worse than saying less.
     *
     * @return array{0: array<string, mixed>, 1: class-string|null}
     */
    private static function specOf(\ReflectionParameter $parameter, string $class): array
    {
        $type = $parameter->getType();
        $name = $parameter->getName();

        if (!$type instanceof \ReflectionNamedType) {
            throw new DeclarationException(
                "Operation {$class}: parameter \${$name} has no single type, so its schema cannot be "
                . 'derived. Emitting an open schema here would tell every surface that anything goes — '
                . 'give it one type, or take it out of the input contract.'
            );
        }

        $spec = [];
        $enum = null;
        $named = $type->getName();

        $spec['type'] = match ($named) {
            'string' => 'string',
            'int' => 'integer',
            'float' => 'number',
            'bool' => 'boolean',
            'array' => 'array',
            default => null,
        };

        if ($spec['type'] === null) {
            if (!enum_exists($named)) {
                throw new DeclarationException(
                    "Operation {$class}: parameter \${$name} is typed {$named}, which is not a scalar, "
                    . 'an array or an enum, so no schema describes it. Input crosses a wire — pass an '
                    . 'identifier the operation resolves, or inject the collaborator into run().'
                );
            }

            $cases = $named::cases();
            $backed = $cases !== [] && $cases[0] instanceof \BackedEnum;
            $spec['type'] = $backed && \is_int($cases[0]->value) ? 'integer' : 'string';
            $spec['enum'] = array_map(
                static fn (\UnitEnum $case): string|int => $case instanceof \BackedEnum ? $case->value : $case->name,
                $cases,
            );
            $enum = $named;
        }

        $because = $parameter->getAttributes(Because::class);
        if ($because !== []) {
            $spec['description'] = $because[0]->newInstance()->purpose;
        }

        return [$spec, $enum];
    }

    /**
     * What `run()` needs, checked HERE so a missing collaborator is a declaration error, not a 500.
     *
     * @return list<class-string>
     */
    private static function collaborators(\ReflectionMethod $run, string $class, ?\Closure $resolve): array
    {
        $types = [];

        foreach ($run->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                throw new DeclarationException(
                    "Operation {$class}: run() takes \${$parameter->getName()}, which is not a collaborator. "
                    . 'The input contract is the CONSTRUCTOR; run() receives only the services this '
                    . 'operation works through.'
                );
            }

            $types[] = $type->getName();
        }

        if ($types !== [] && $resolve === null) {
            throw new DeclarationException(
                "Operation {$class}: run() needs " . implode(', ', $types) . ' but no resolver was given '
                . 'to DeclaredOperation::from(). Pass one — a declaration whose collaborators cannot be '
                . 'found should fail at boot, not on the first request.'
            );
        }

        return $types;
    }

    /**
     * The output schema, read off `run()`'s return type when that type is a result object.
     *
     * `array` and `mixed` return `null` — the same «unspecified» every hand-written operation that
     * never wrote an output schema already returns. Nothing is invented from a shapeless return.
     *
     * @return array<string, mixed>|null
     */
    private static function outputContract(\ReflectionMethod $run): ?array
    {
        $type = $run->getReturnType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin() || !class_exists($type->getName())) {
            return null;
        }

        $result = new \ReflectionClass($type->getName());
        $constructor = $result->getConstructor();

        if ($constructor === null) {
            return null;
        }

        $properties = [];
        foreach ($constructor->getParameters() as $parameter) {
            if (!$parameter->isPromoted()) {
                continue;
            }
            $parameterType = $parameter->getType();
            $properties[$parameter->getName()] = [
                'type' => $parameterType instanceof \ReflectionNamedType
                    ? match ($parameterType->getName()) {
                        'int' => 'integer',
                        'float' => 'number',
                        'bool' => 'boolean',
                        'array' => 'array',
                        default => 'string',
                    }
                    : 'string',
            ];
        }

        return $properties === [] ? null : ['type' => 'object', 'properties' => $properties];
    }

    /**
     * The handler every surface already calls: `($operation->handler)($input): array`.
     *
     * @param list<array<string, mixed>> $parameters
     * @param list<class-string>         $collaborators
     */
    private static function handler(string $class, array $parameters, array $collaborators, ?\Closure $resolve): \Closure
    {
        return static function (array $input) use ($class, $parameters, $collaborators, $resolve): array {
            $arguments = [];

            foreach ($parameters as $parameter) {
                $name = $parameter['name'];

                if (!\array_key_exists($name, $input)) {
                    if ($parameter['optional']) {
                        continue;
                    }

                    throw new DeclarationException("Operation {$class}: missing required input '{$name}'.");
                }

                $value = $input[$name];
                $enum = $parameter['enum'];

                if ($enum !== null && !$value instanceof $enum) {
                    // The surfaces validate enum membership before the handler is reached; a caller
                    // that bypassed them still does not get to invent a case.
                    $case = is_a($enum, \BackedEnum::class, true)
                        ? $enum::tryFrom($value)
                        : (\defined("{$enum}::{$value}") ? \constant("{$enum}::{$value}") : null);

                    if ($case === null) {
                        throw new DeclarationException(
                            "Operation {$class}: '{$name}' is not one of " . $enum . "'s cases."
                        );
                    }

                    $value = $case;
                }

                $arguments[$name] = $value;
            }

            $instance = new $class(...$arguments);

            $services = [];
            foreach ($collaborators as $type) {
                /** @var \Closure $resolve */
                $services[] = $resolve($type);
            }

            $result = $instance->run(...$services);

            if (\is_array($result)) {
                return $result;
            }

            return \is_object($result) ? get_object_vars($result) : [];
        };
    }

    /**
     * The first instance of an attribute on a class, or null.
     *
     * @param \ReflectionClass<object> $reflection
     */
    private static function attribute(\ReflectionClass $reflection, string $attribute): ?object
    {
        $found = $reflection->getAttributes($attribute);

        return $found === [] ? null : $found[0]->newInstance();
    }
}
