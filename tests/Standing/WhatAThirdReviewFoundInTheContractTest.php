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

namespace Milpa\Command\Tests\Standing;

use Milpa\Command\Standing\Canonical;
use Milpa\Command\Standing\StandingGrant;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What a third independent review found in the contract of standing grants (greenhouse decisions/0615, «Lo que la
 * tercera revisión encontró», C1–C3): a signed document that says a key twice, a number the canonical spelling does
 * not give back, and one reader for a new document and for one already signed.
 *
 * @subject-in milpa/command
 */
final class WhatAThirdReviewFoundInTheContractTest extends TestCase
{
    /** The lab grant as text, with something put in place of something else. */
    private static function written(string $find, string $put, ?array $change = null): string
    {
        $text = (string) json_encode(Grants::document($change ?? []), \JSON_UNESCAPED_SLASHES);
        self::assertSame(1, substr_count($text, $find), "the lab grant says {$find} once");

        return str_replace($find, $put, $text);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function whatIsSaidTwice(): iterable
    {
        $whole = (string) json_encode(Grants::document(), \JSON_UNESCAPED_SLASHES);
        yield 'an expiry, and then none' => ['{"expires_at":"2000-01-01T00:00:00Z",' . substr($whole, 1, -1) . ',"expires_at":null}', 'expires_at', ''];
        yield 'no expiry, and then one' => ['{"expires_at":null,' . substr($whole, 1, -1) . ',"expires_at":"2000-01-01T00:00:00Z"}', 'expires_at', ''];
        yield 'who it lends to' => ['{"to":["SOMEONE-ELSE"],' . substr($whole, 1), 'to', ''];
        yield 'what it covers' => [substr($whole, 0, -1) . ',"covers":[{"operation":"lab:everything"}]}', 'covers', ''];
        yield 'its own id' => ['{"id":"another",' . substr($whole, 1), 'id', ''];
        yield 'an axis of its ceiling' => [self::written('"reversibility":"compensatable"', '"reversibility":"irreversible","reversibility":"compensatable"'), 'reversibility', ', inside "ceiling"'];
        yield 'the operation of a covered call' => [self::written('"covers":[{"operation":"lab:publish"', '"covers":[{"operation":"lab:announce","operation":"lab:publish"'), 'operation', ', inside "covers"'];
        yield 'an argument a covered call fixes' => [self::written('"arguments":{"channel":"stable"}', '"arguments":{"channel":"beta","channel":"stable"}'), 'channel', ', inside "arguments"'];
        yield 'the instrument of a condition' => [self::written('"by":{"operation":"lab:scan"', '"by":{"operation":"lab:soft","operation":"lab:scan"'), 'operation', ', inside "by"'];
        yield 'the watcher of its falsifier' => [self::written('"watcher":{"operation":"lab:watch"}', '"watcher":{"operation":"lab:watch"},"watcher":{"operation":"lab:blind"}'), 'watcher', ', inside "falsifier"'];
        // It was «to» with its first letter written as an escape. A plain letter written so is now refused for that
        // (a proposal of the sixth review, not decided), so the two spellings are of a letter a writer does escape:
        // the question — one key, spelled two ways, said twice — holds with that proposal and without it.
        yield 'a key spelled two ways' => ['{"ca\\u00f1a":1,"ca' . "\u{f1}" . 'a":2,' . substr($whole, 1), 'ca' . "\u{f1}" . 'a', ''];
        yield 'a key with a quote in it' => [substr($whole, 0, -1) . ',"a \\"b":1,"a \\"b":2}', 'a "b', ''];
    }

    #[DataProvider('whatIsSaidTwice')]
    public function testADocumentThatSaysAKeyTwiceIsRefused(string $text, string $key, string $inside): void
    {
        self::assertIsArray(json_decode($text, true), 'control: it is JSON, and a reader takes it');

        try {
            $grant = StandingGrant::fromJson($text);
        } catch (\InvalidArgumentException $refused) {
            self::assertStringContainsString('a standing grant says ' . json_encode($key) . " twice{$inside}: a document a person signs says each thing once", $refused->getMessage());

            return;
        }
        self::fail('accepted, and read as «' . $grant->id . '» lending to ' . implode(', ', $grant->to) . ', ' . ($grant->expiresAt === null ? 'with NO expiry' : 'expiring ' . $grant->expiresAt->format(\DATE_ATOM)));
    }

    /** @return iterable<string, array{string}> */
    public static function whatSaysNothingTwice(): iterable
    {
        yield 'the lab grant: «operation» and «name» each in several objects' => [(string) json_encode(Grants::document())];
        yield 'written over several lines, with spaces' => [(string) json_encode(Grants::document(), \JSON_PRETTY_PRINT)];
        yield 'a text that holds the name of a key, a colon, and braces' => [self::written('"undo":"', '"undo":"say \\"id\\": {\\"to\\": [] } twice, and \\\\\\"id\\": once more — ')];
        yield 'a text that ends in a backslash' => [(string) json_encode(Grants::document(['undo' => 'ends in a backslash\\']))];
        yield 'the same argument fixed by two covered calls' => [(string) json_encode(Grants::document(['covers' => [['operation' => 'lab:publish', 'arguments' => ['channel' => 'stable']], ['operation' => 'lab:merge', 'arguments' => ['channel' => 'stable']]]]))];
        yield 'an empty object and an empty list in it' => [(string) json_encode(Grants::document(['covers' => [['operation' => 'lab:publish', 'arguments' => new \stdClass()]]]))];
    }

    #[DataProvider('whatSaysNothingTwice')]
    public function testADocumentThatSaysEachThingOnceIsReadAsBefore(string $text): void
    {
        $grant = StandingGrant::fromJson($text);

        self::assertSame(StandingGrant::fromArray(json_decode($text, true))->digest(), $grant->digest());
    }

    public function testOneNumberIsNotWrittenAndReadBackTheSameAndTheClassSaysSo(): void
    {
        // C2, pinned as it is: changing the spelling now would change the digest of every stored line that holds a
        // whole-number float. A host that writes a line with this spelling reads it back before it trusts it.
        $written = Canonical::json(['n' => -0.0]);

        self::assertSame('{"n":-0}', $written);
        self::assertNotSame($written, Canonical::json(json_decode($written, true)));
        self::assertStringContainsString('has to read it back before it trusts', (string) (new \ReflectionClass(Canonical::class))->getDocComment());
    }

    public function testADocumentAnEarlierContractAcceptedIsRefusedLikeAnyTextAndTheReaderSaysWhatAHostDoes(): void
    {
        // C3, pinned as it is: one reader. What it owes a host that keeps signed documents is to say so.
        $refusal = static function (string $text): \InvalidArgumentException {
            try {
                StandingGrant::fromJson($text);
            } catch (\InvalidArgumentException $refused) {
                return $refused;
            }
            self::fail('accepted: ' . $text);
        };
        $once = $refusal((string) json_encode(Grants::document(['expires_at' => '2099-01-01'])));
        $never = $refusal('{"not":"a grant"}');

        self::assertSame([$once::class, $once->getCode()], [$never::class, $never->getCode()]);
        self::assertStringContainsString('can no longer read as a FAULT of its record', (string) (new \ReflectionMethod(StandingGrant::class, 'fromJson'))->getDocComment());
    }
}
