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

namespace Milpa\Command\Tests\Declaration;

use Milpa\Command\Declaration\DeclarationException;
use Milpa\Command\Declaration\DeclaredOperation;
use Milpa\Command\Effect\EffectProfile;
use Milpa\Command\Effect\Externality;
use Milpa\Command\Effect\Mutation;
use Milpa\Command\Effect\Reversibility;
use Milpa\Command\Effect\Subject;
use Milpa\Command\Operation;
use Milpa\Command\Declaration\Because;
use Milpa\Command\Declaration\Confirms;
use Milpa\Command\Declaration\Mutates;
use Milpa\Command\Declaration\Needs;
use Milpa\Command\Declaration\Operation as OperationAttribute;
use Milpa\Command\Declaration\Reads;
use Milpa\Command\Declaration\Target;
use Milpa\Command\Tests\Declaration\Fixtures\Bares;
use Milpa\Command\Tests\Declaration\Fixtures\Contradictory;
use Milpa\Command\Tests\Declaration\Fixtures\Greet;
use Milpa\Command\Tests\Declaration\Fixtures\Greetings;
use Milpa\Command\Tests\Declaration\Fixtures\Handless;
use Milpa\Command\Tests\Declaration\Fixtures\Measure;
use Milpa\Command\Tests\Declaration\Fixtures\Priority;
use Milpa\Command\Tests\Declaration\Fixtures\Publish;
use Milpa\Command\Tests\Declaration\Fixtures\ScalarRun;
use Milpa\Command\Tests\Declaration\Fixtures\Silent;
use Milpa\Command\Tests\Declaration\Fixtures\Status;
use Milpa\Command\Tests\Declaration\Fixtures\TwoTargets;
use Milpa\Command\Tests\Declaration\Fixtures\Untypeable;
use Milpa\Command\Tests\Declaration\Fixtures\Untyped;
use Milpa\Command\Tests\Declaration\Fixtures\Wraps;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The declaration IS the contract — and it produces exactly what the hand-written form produced.
 *
 * The load-bearing test here is the first one: a declared class and the `new Operation(...)` it
 * replaces are compared field by field. If that ever drifts, every projector downstream — CLI flags,
 * MCP tools, HTTP routes, consent, the agent's catalogue — drifts with it, and the whole premise of
 * greenhouse decisions/0212 («nothing downstream changes») is false.
 */
#[CoversClass(DeclaredOperation::class)]
#[CoversClass(OperationAttribute::class)]
#[CoversClass(Mutates::class)]
#[CoversClass(Reads::class)]
#[CoversClass(Needs::class)]
#[CoversClass(Because::class)]
#[CoversClass(Confirms::class)]
#[CoversClass(Target::class)]
#[CoversClass(DeclarationException::class)]
final class DeclaredOperationTest extends TestCase
{
    public function testDerivesTheSameOperationTheHandWrittenFormBuilt(): void
    {
        $greetings = new Greetings();

        $handWritten = new Operation(
            name: 'hola:greet',
            description: 'Write a greeting for a person by name into var/greetings.txt (appends one line). Reversible: remove the line.',
            handler: static fn (array $input): array => [],
            inputSchema: [
                'type' => 'object',
                'required' => ['name'],
                'properties' => ['name' => ['type' => 'string', 'description' => 'who to greet']],
            ],
            mutating: true,
            namedTarget: 'name',
            effects: new EffectProfile(
                Mutation::Persistent,
                Externality::None,
                Reversibility::Guaranteed,
                subject: Subject::Data,
                rollbackContract: 'remove the line from var/greetings.txt',
            ),
        );

        $derived = DeclaredOperation::from(Greet::class, static fn (string $type): object => $greetings);

        self::assertSame($handWritten->name, $derived->name);
        self::assertSame($handWritten->description, $derived->description);
        self::assertSame($handWritten->inputSchema, $derived->inputSchema);
        self::assertSame($handWritten->mutating, $derived->mutating);
        self::assertSame($handWritten->requiresConfirmation, $derived->requiresConfirmation);
        self::assertSame($handWritten->scopes, $derived->scopes);
        self::assertSame($handWritten->outputSchema, $derived->outputSchema);
        self::assertSame($handWritten->version, $derived->version);
        self::assertSame($handWritten->path, $derived->path);
        self::assertSame($handWritten->surfaces, $derived->surfaces);
        self::assertSame($handWritten->permission, $derived->permission);
        self::assertSame($handWritten->namedTarget, $derived->namedTarget);
        self::assertEquals($handWritten->effects, $derived->effects);
    }

    public function testTheDerivedHandlerDoesWhatTheHandWrittenOneDid(): void
    {
        $greetings = new Greetings();
        $operation = DeclaredOperation::from(Greet::class, static fn (string $type): object => $greetings);

        $result = ($operation->handler)(['name' => 'Rod']);

        self::assertSame(['ok' => true, 'greeted' => 'Rod', 'file' => 'var/greetings.txt'], $result);
        self::assertSame(['Rod'], $greetings->written);
    }

    public function testAnEnumParameterCarriesItsAdmissibleValuesAndItsDefault(): void
    {
        $operation = DeclaredOperation::from(Publish::class);

        self::assertSame(
            [
                'type' => 'object',
                'required' => ['id'],
                'properties' => [
                    'id' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => ['draft', 'published'], 'default' => 'draft'],
                    'revision' => ['type' => 'integer', 'default' => 1],
                ],
            ],
            $operation->inputSchema,
        );
    }

    public function testTheHandlerTurnsAnAdmissibleValueIntoItsCaseAndRefusesAnythingElse(): void
    {
        $operation = DeclaredOperation::from(Publish::class);

        self::assertSame(
            ['id' => 'doc-1', 'status' => 'published', 'revision' => 2],
            ($operation->handler)(['id' => 'doc-1', 'status' => 'published', 'revision' => 2]),
        );

        $this->expectException(DeclarationException::class);
        ($operation->handler)(['id' => 'doc-1', 'status' => 'deleted']);
    }

    public function testAnOmittedOptionalKeepsThePhpDefault(): void
    {
        $operation = DeclaredOperation::from(Publish::class);

        self::assertSame(
            ['id' => 'doc-1', 'status' => Status::Draft->value, 'revision' => 1],
            ($operation->handler)(['id' => 'doc-1']),
        );
    }

    public function testTheReturnTypeDeclaresTheOutput(): void
    {
        $operation = DeclaredOperation::from(Publish::class);

        self::assertSame(
            [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string'],
                    'status' => ['type' => 'string'],
                    'revision' => ['type' => 'integer'],
                ],
            ],
            $operation->outputSchema,
        );
    }

    public function testIntentIsCarriedThroughUntouched(): void
    {
        $operation = DeclaredOperation::from(Publish::class);

        self::assertTrue($operation->mutating);
        self::assertTrue($operation->requiresConfirmation);
        self::assertSame(['docs:write'], $operation->scopes);
        self::assertNull($operation->permission);
    }

    public function testSilenceAboutEffectsIsRefusedByName(): void
    {
        $this->expectException(DeclarationException::class);
        $this->expectExceptionMessageMatches('/Silent declares neither #\[Mutates\] nor #\[Reads\]/');

        DeclaredOperation::from(Silent::class);
    }

    public function testDeclaringBothEffectsIsRefused(): void
    {
        $this->expectException(DeclarationException::class);
        $this->expectExceptionMessageMatches('/declares BOTH #\[Mutates\] and #\[Reads\]/');

        DeclaredOperation::from(Contradictory::class);
    }

    public function testTwoNamedTargetsAreRefused(): void
    {
        $this->expectException(DeclarationException::class);
        $this->expectExceptionMessageMatches('/marks two parameters with #\[Target\]/');

        DeclaredOperation::from(TwoTargets::class);
    }

    public function testAnOperationWithoutARunMethodIsRefused(): void
    {
        $this->expectException(DeclarationException::class);
        $this->expectExceptionMessageMatches('/has no public run\(\)/');

        DeclaredOperation::from(Handless::class);
    }

    public function testAnInputTypeNoSchemaDescribesIsRefused(): void
    {
        $this->expectException(DeclarationException::class);
        $this->expectExceptionMessageMatches('/is typed .*Greetings, which is not a scalar/');

        DeclaredOperation::from(Untypeable::class);
    }

    public function testInputSmuggledIntoRunIsRefused(): void
    {
        $this->expectException(DeclarationException::class);
        $this->expectExceptionMessageMatches('/run\(\) takes \$id, which is not a collaborator/');

        DeclaredOperation::from(ScalarRun::class);
    }

    public function testACollaboratorWithNobodyToResolveItFailsAtDeclarationNotAtRuntime(): void
    {
        $this->expectException(DeclarationException::class);
        $this->expectExceptionMessageMatches('/run\(\) needs .*Greetings but no resolver was given/');

        DeclaredOperation::from(Greet::class);
    }

    public function testAClassThatDeclaresNothingIsNotAnOperation(): void
    {
        self::assertFalse(DeclaredOperation::isDeclared(Greetings::class));
        self::assertTrue(DeclaredOperation::isDeclared(Greet::class));

        $this->expectException(DeclarationException::class);
        DeclaredOperation::from(Greetings::class);
    }

    /**
     * The derivation is paid once, at declaration — never per invocation.
     *
     * Measured by counting how often the resolver is asked: reflection runs inside `from()`, and a
     * thousand calls through the derived handler ask for the collaborator a thousand times and
     * reflect zero more times. If the handler ever re-derived, this is where it would show.
     */
    public function testDerivationHappensOnceAndAnInvocationPaysNothingForIt(): void
    {
        $greetings = new Greetings();
        $resolutions = 0;

        $operation = DeclaredOperation::from(Greet::class, static function (string $type) use ($greetings, &$resolutions): object {
            ++$resolutions;

            return $greetings;
        });

        self::assertSame(0, $resolutions, 'declaration must not resolve collaborators');

        for ($i = 0; $i < 1000; ++$i) {
            ($operation->handler)(['name' => 'Rod']);
        }

        self::assertSame(1000, $resolutions);
        self::assertCount(1000, $greetings->written);
    }

    public function testEveryCoercibleTypeIsDerivedAndAPureEnumOffersItsCaseNames(): void
    {
        $operation = DeclaredOperation::from(Measure::class);

        self::assertSame(
            [
                'type' => 'object',
                'required' => ['ratio', 'dry', 'tags'],
                'properties' => [
                    'ratio' => ['type' => 'number'],
                    'dry' => ['type' => 'boolean'],
                    'tags' => ['type' => 'array'],
                    'priority' => ['type' => 'string', 'enum' => ['Low', 'High'], 'default' => Priority::Low],
                ],
            ],
            $operation->inputSchema,
        );

        self::assertSame(
            [
                'type' => 'object',
                'properties' => [
                    'ratio' => ['type' => 'number'],
                    'dry' => ['type' => 'boolean'],
                    'tags' => ['type' => 'array'],
                    'priority' => ['type' => 'string'],
                ],
            ],
            $operation->outputSchema,
        );
    }

    public function testThePurposeJoinsTheDescriptionSoBothReadersSeeOneSentence(): void
    {
        $operation = DeclaredOperation::from(Measure::class);

        self::assertSame('Measure a thing. so the catalogue explains WHY, not just what.', $operation->description);
        self::assertSame('2', $operation->version);
        self::assertSame('/measure', $operation->path);
        self::assertSame(['cli'], $operation->surfaces);
        self::assertFalse($operation->mutating);
    }

    public function testAPureEnumCaseArrivesByNameAndAnythingElseIsRefused(): void
    {
        $operation = DeclaredOperation::from(Measure::class);

        self::assertSame(
            ['ratio' => 0.5, 'dry' => true, 'tags' => ['a'], 'priority' => 'High'],
            ($operation->handler)(['ratio' => 0.5, 'dry' => true, 'tags' => ['a'], 'priority' => 'High']),
        );

        $this->expectException(DeclarationException::class);
        ($operation->handler)(['ratio' => 0.5, 'dry' => true, 'tags' => [], 'priority' => 'Urgent']);
    }

    public function testAMissingRequiredInputIsRefusedByTheHandler(): void
    {
        $operation = DeclaredOperation::from(Measure::class);

        $this->expectException(DeclarationException::class);
        $this->expectExceptionMessageMatches("/missing required input 'ratio'/");

        ($operation->handler)([]);
    }

    public function testAUnionTypedInputIsRefused(): void
    {
        $this->expectException(DeclarationException::class);
        $this->expectExceptionMessageMatches('/\$id has no single type/');

        DeclaredOperation::from(Untyped::class);
    }

    public function testAClassThatDoesNotExistIsRefused(): void
    {
        $this->expectException(DeclarationException::class);
        $this->expectExceptionMessageMatches('/the class does not exist/');

        DeclaredOperation::from('Milpa\Command\Tests\Declaration\Fixtures\Nope');
    }

    public function testAResultWithNothingPromotedDeclaresNoOutputSchema(): void
    {
        self::assertNull(DeclaredOperation::from(Bares::class)->outputSchema);
        self::assertNull(DeclaredOperation::from(Wraps::class)->outputSchema);
    }

    public function testAnObjectResultIsReturnedAsItsPublicValues(): void
    {
        $operation = DeclaredOperation::from(Bares::class);

        self::assertSame(['note' => 'nothing promoted here'], ($operation->handler)([]));
    }

    /**
     * A declared read is the canonical read — not a second opinion about what read-only means.
     *
     * The first version of #[Reads] built its own profile and claimed guaranteed reversibility with
     * nothing to back it. EffectProfile refused it at construction, which is the package's own
     * doctrine working: a claim that lowers scrutiny must name what backs it.
     */
    public function testADeclaredReadIsExactlyTheCanonicalReadOnlyProfile(): void
    {
        self::assertEquals(EffectProfile::readOnly(), DeclaredOperation::from(Bares::class)->effects);
    }
}
