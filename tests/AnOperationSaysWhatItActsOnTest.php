<?php

declare(strict_types=1);

namespace Milpa\Command\Tests;

use Milpa\Command\Effect\EffectProfile;
use Milpa\Command\Operation;
use PHPUnit\Framework\TestCase;

/**
 * An operation says what content a call acts on, and what makes it the instrument it is
 * (greenhouse decisions/0615 §2).
 *
 * The digest is derived from the bytes the arguments lead to, by the operation's own declaration; a
 * host joins a measurement and an act by it. An operation that cannot say answers `null`, and `null`
 * binds nothing.
 */
final class AnOperationSaysWhatItActsOnTest extends TestCase
{
    private string $candidate;

    protected function setUp(): void
    {
        $this->candidate = (string) tempnam(sys_get_temp_dir(), 'candidate');
        file_put_contents($this->candidate, 'the candidate, byte for byte');
    }

    protected function tearDown(): void
    {
        @unlink($this->candidate);
    }

    private function operation(?\Closure $actsOn, ?\Closure $handler = null, ?array $scopes = null): Operation
    {
        return new Operation(
            name: 'lab:scan',
            description: 'Lab only',
            handler: $handler ?? static fn (array $input): array => ['ok' => true],
            scopes: $scopes ?? ['lab:scan'],
            effects: EffectProfile::readOnly(),
            actsOn: $actsOn,
        );
    }

    private static function sha256OfThePath(): \Closure
    {
        return static fn (array $arguments): ?string => 'sha256:' . hash_file('sha256', (string) $arguments['path']);
    }

    public function testAnOperationThatDeclaresNothingActsOnNothingItCanName(): void
    {
        self::assertNull($this->operation(null)->actsOnDigest(['path' => $this->candidate]));
    }

    public function testTheDigestComesFromTheBytesAndFollowsThem(): void
    {
        $operation = $this->operation(self::sha256OfThePath());
        $before = $operation->actsOnDigest(['path' => $this->candidate]);

        self::assertSame('sha256:' . hash('sha256', 'the candidate, byte for byte'), $before);
        self::assertSame($before, $operation->actsOnDigest(['path' => $this->candidate]), 'the same bytes, the same digest');

        file_put_contents($this->candidate, 'the candidate, byte for bytE');
        self::assertNotSame($before, $operation->actsOnDigest(['path' => $this->candidate]), 'one byte apart is another candidate');
    }

    public function testEveryWayOfNotKnowingIsNull(): void
    {
        $missing = $this->operation(static function (array $arguments): string {
            throw new \RuntimeException('there is no such candidate');
        });

        self::assertNull($missing->actsOnDigest(['path' => '/nowhere']), 'a declaration that could not look');
        self::assertNull($this->operation(static fn (array $arguments): ?string => null)->actsOnDigest([]), 'a declaration that answered nothing');
        self::assertNull($this->operation(static fn (array $arguments): string => '   ')->actsOnDigest([]), 'a blank digest is no digest');
        self::assertSame('sha256:abc', $this->operation(static fn (array $arguments): string => " sha256:abc\n")->actsOnDigest([]));
    }

    public function testAnInstrumentIsPinnedByItsDeclarationItsHandlerAndWhatItSaysItActsOn(): void
    {
        $actsOn = self::sha256OfThePath();
        $instrument = $this->operation($actsOn)->instrumentDigest();

        self::assertIsString($instrument);
        self::assertStringStartsWith('sha256:', $instrument);
        self::assertSame($instrument, $this->operation($actsOn)->instrumentDigest(), 'the same operation, the same instrument');

        // A guard that did not see, hardened afterwards, is another instrument.
        $hardened = $this->operation($actsOn, static fn (array $input): array => ['ok' => false, 'found' => 3]);
        self::assertNotSame($instrument, $hardened->instrumentDigest());

        // And so is one that reads other bytes, or declares another reach.
        self::assertNotSame($instrument, $this->operation(static fn (array $arguments): string => 'sha256:' . hash('sha256', 'something else'))->instrumentDigest());
        self::assertNotSame($instrument, $this->operation($actsOn, scopes: ['lab:scan', 'lab:read'])->instrumentDigest());
    }

    public function testWhatCannotBePinnedIsNoInstrument(): void
    {
        self::assertNull($this->operation(null)->instrumentDigest(), 'it measures nothing in particular');

        $unreadable = new Operation('lab:scan', 'Lab only', ['App\\Lab\\Scanner', 'scan'], actsOn: self::sha256OfThePath());
        self::assertNull($unreadable->instrumentDigest(), 'its handler cannot be read');

        $internal = new Operation('lab:scan', 'Lab only', static fn (array $input): array => [], actsOn: strtoupper(...));
        self::assertNull($internal->instrumentDigest(), 'what says what it acts on cannot be read');
    }
}
