<?php

declare(strict_types=1);

namespace Milpa\Command\Tests\Standing;

use Milpa\Command\Effect\EffectProfile;
use Milpa\Command\Standing\Falsifier;
use Milpa\Command\Standing\GrantCondition;
use Milpa\Command\Standing\NamedCall;
use Milpa\Command\Standing\StandingGrant;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What cannot be written down as a standing grant (greenhouse decisions/0615, «Decidido por Rod»).
 *
 * The floors are refused at construction, so no such document exists to be signed. Each case changes
 * ONE thing in a grant that is otherwise accepted — the first test is that control — and the refusal
 * must name what was wrong: a grant refused for the wrong reason would pass these with a bare
 * expectException.
 */
final class AStandingGrantNeverCrossesItsFloorsTest extends TestCase
{
    public function testTheGrantTheseCasesStartFromIsAccepted(): void
    {
        $grant = Grants::grant();

        self::assertSame('release-line', $grant->id);
        self::assertSame([Grants::SEAT], $grant->to);
        self::assertNull($grant->supersedes);
        self::assertNull($grant->expiresAt);
    }

    /**
     * @param array<string, mixed> $change
     */
    #[DataProvider('documentsThatAreRefused')]
    public function testItIsRefusedAndTheRefusalNamesWhy(array $change, string $says): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($says);

        Grants::grant($change);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function documentsThatAreRefused(): iterable
    {
        $ceiling = Grants::document()['ceiling'];

        // The two floors Rod decided on 2026-10-11, and the measured one behind the second.
        yield 'a ceiling that admits the irreversible' => [['ceiling' => ['reversibility' => 'irreversible'] + $ceiling], 'the highest it carries is «compensatable»'];
        yield 'a ceiling that admits what only a person can recover' => [['ceiling' => ['reversibility' => 'manual_recovery'] + $ceiling], 'never lends what cannot at least be compensated'];
        yield 'a ceiling with an unclassified axis' => [['ceiling' => ['externality' => 'unknown'] + $ceiling], 'an unclassified ceiling admits everything'];
        yield 'a ceiling with unclassified reversibility' => [['ceiling' => ['reversibility' => 'unknown'] + $ceiling], 'an unclassified ceiling admits everything'];
        yield 'covering the act that gives a grant' => [['covers' => [['operation' => 'standing:grant']]], 'nothing a grant lends may give, restore, withdraw or stop a grant'];
        yield 'covering the act that restores a grant, spelled another way' => [['covers' => [['operation' => 'standing_reinstate']]], 'cannot cover «standing.reinstate»'];
        yield 'covering the whole standing namespace by its bare name' => [['covers' => [['operation' => 'standing']]], 'cannot cover «standing»'];

        // A grant that asks nothing, or that nothing watches, is not a grant.
        yield 'no condition' => [['conditions' => []], 'the scope handed over whole'];
        yield 'no falsifier' => [['falsifier' => null], 'A position nobody watches is not granted'];
        yield 'a falsifier with no watcher' => [['falsifier' => ['name' => 'f', 'statement' => 's', 'within_seconds' => 60]], 'in «watcher», names an operation'];
        yield 'a watcher with no window' => [['falsifier' => ['name' => 'f', 'statement' => 's', 'watcher' => ['operation' => 'lab:watch']]], 'needs «within_seconds»'];
        yield 'a watcher with a window of nothing' => [['falsifier' => ['name' => 'f', 'statement' => 's', 'watcher' => ['operation' => 'lab:watch'], 'within_seconds' => 0]], 'needs «within_seconds»'];
        yield 'a falsifier that does not say what it is' => [['falsifier' => ['name' => 'f', 'statement' => ' ', 'watcher' => ['operation' => 'lab:watch'], 'within_seconds' => 60]], 'falsifier «f» needs «statement»'];

        // A positive, closed list: never anyone, never a pattern.
        yield 'lending to anyone' => [['to' => ['*']], 'never to «anyone»'];
        yield 'lending to nobody' => [['to' => []], 'lends to nobody'];
        yield 'lending to something that is not a key' => [['to' => [7]], 'never to «anyone»'];
        yield 'seats not given as a list' => [['to' => Grants::SEAT], 'gives «to» as a list'];
        yield 'covering nothing' => [['covers' => []], 'covers nothing'];
        yield 'covering a pattern' => [['covers' => [['operation' => 'lab:*']]], 'no pattern, no wildcard'];
        yield 'covering something that is not an operation' => [['covers' => [['arguments' => ['channel' => 'stable']]]], 'in «covers», names an operation'];
        yield 'fixing an argument to something that is not one plain value' => [['covers' => [['operation' => 'lab:publish', 'arguments' => ['channels' => ['stable']]]]], 'one plain value'];
        yield 'fixing arguments that are not named' => [['covers' => [['operation' => 'lab:publish', 'arguments' => 'stable']]], 'fixes its arguments by name'];

        // A line the judge would ignore is a limit nobody keeps — and money is the one a person will try.
        yield 'a budget, which nothing reads' => [['budget' => 50], 'carries «budget», which nothing reads'];
        yield 'a limit inside a covered call' => [['covers' => [['operation' => 'lab:publish', 'max_spend' => 50]]], 'carries «max_spend», which nothing reads'];
        yield 'a limit inside the ceiling' => [['ceiling' => ['money' => 'none'] + $ceiling], 'carries «money», which nothing reads'];
        yield 'a line inside a condition' => [['conditions' => [['name' => 'c', 'statement' => 's', 'by' => ['operation' => 'lab:scan'], 'optional' => true]]], 'carries «optional», which nothing reads'];
        yield 'a line inside the falsifier' => [['falsifier' => ['name' => 'f', 'statement' => 's', 'watcher' => ['operation' => 'lab:watch'], 'within_seconds' => 60, 'mute' => true]], 'carries «mute», which nothing reads'];

        // The rest of the document says what it is.
        yield 'no id' => [['id' => null], 'needs «id»'];
        yield 'an id that is not a short name' => [['id' => 'Release Line'], 'is known by a short id'];
        yield 'superseding itself' => [['supersedes' => 'release-line'], 'and never itself'];
        yield 'superseding something that is not an id' => [['supersedes' => 7], 'supersedes another grant by its id'];
        yield 'superseding a name that is not an id' => [['supersedes' => 'the old one'], 'supersedes another grant by its id'];
        yield 'no ceiling' => [['ceiling' => null], 'needs «ceiling»'];
        yield 'a ceiling missing an axis' => [['ceiling' => ['mutation' => 'persistent']], 'a stored profile needs a valid «reversibility»'];
        yield 'no undo' => [['undo' => '  '], 'needs «undo»'];
        yield 'a condition that is not one' => [['conditions' => ['measured']], 'a condition is {"name", "statement", "by"'];
        yield 'a condition nothing measures' => [['conditions' => [['name' => 'c', 'statement' => 's']]], 'condition «c», in «by», names an operation'];
        yield 'two conditions under one name' => [['conditions' => [
            ['name' => 'c', 'statement' => 's', 'by' => ['operation' => 'lab:scan']],
            ['name' => 'c', 'statement' => 't', 'by' => ['operation' => 'lab:build']],
        ]], 'each under its own name'];
        yield 'conditions not given as a list' => [['conditions' => ['c' => ['name' => 'c']]], 'gives «conditions» as a list'];
        yield 'an expiry that is not a date' => [['expires_at' => 'next week, maybe'], 'gives «expires_at» as a date and time'];
        yield 'an expiry that is not text' => [['expires_at' => 1790000000], 'gives «expires_at» as a date and time'];
    }

    public function testTheValuesRefuseTheSameThingsWhenBuiltByHand(): void
    {
        $by = new NamedCall('lab:scan');
        $ceiling = EffectProfile::fromArray(Grants::document()['ceiling']);
        $falsifier = new Falsifier('f', 's', new NamedCall('lab:watch'), 60);
        $condition = new GrantCondition('c', 's', $by);
        $refused = static function (\Closure $build): string {
            try {
                $build();
            } catch (\InvalidArgumentException $error) {
                return $error->getMessage();
            }

            return 'ACCEPTED';
        };

        self::assertStringContainsString('covers named calls, one by one', $refused(static fn () => new StandingGrant('g', [Grants::SEAT], [['operation' => 'lab:publish']], $ceiling, [$condition], 'undo', $falsifier)));
        self::assertStringContainsString('lists its conditions one by one', $refused(static fn () => new StandingGrant('g', [Grants::SEAT], [$by], $ceiling, ['c'], 'undo', $falsifier)));
        self::assertStringContainsString('needs «undo»', $refused(static fn () => new StandingGrant('g', [Grants::SEAT], [$by], $ceiling, [$condition], '  ', $falsifier)));
        self::assertStringContainsString('a claim nobody can check', $refused(static fn () => new GrantCondition(' ', 's', $by)));
        self::assertStringContainsString('names itself and says what would show the grant wrong', $refused(static fn () => new Falsifier('', 's', $by, 60)));
        self::assertStringContainsString('a name and one plain value', $refused(static fn () => new NamedCall('lab:scan', [' ' => 'x'])));
        self::assertStringContainsString('does not name one operation', $refused(static fn () => new NamedCall(new \Milpa\Command\Consent\OperationId('lab scan'))));
        self::assertSame('ACCEPTED', $refused(static fn () => new StandingGrant('g', [Grants::SEAT], [$by], $ceiling, [$condition], 'undo', $falsifier)));
    }

    public function testAGrantIsOneJsonObject(): void
    {
        self::assertSame('release-line', StandingGrant::fromJson((string) json_encode(Grants::document()))->id);

        foreach (['not json at all', '["a", "list"]', '"a string"'] as $text) {
            try {
                StandingGrant::fromJson($text);
                self::fail("«{$text}» was read as a grant");
            } catch (\InvalidArgumentException $error) {
                self::assertStringContainsString('a standing grant is one JSON object', $error->getMessage());
            }
        }
    }
}
