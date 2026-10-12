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
use Milpa\Command\Standing\GrantStanding;
use Milpa\Command\Standing\GrantState;
use Milpa\Command\Standing\StandingGrant;
use Milpa\Command\Standing\StandingJudge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * TWO PROPOSALS OF THE SIXTH REVIEW — BUILT, AND NOT DECIDED (greenhouse decisions/0615). Each is a small change that
 * can be taken out: this file, and the lines of `Canonical` that carry its name.
 *
 *   · L2 — a text does not begin with a combining mark, and carries no more than eight in a row.
 *   · L5, as the seventh review adjusted it — the text a person signs spells plainly what NAMES are made of
 *     (letters, digits, the colon, the dot, the underscore, the dash, the space), never as a `\u` escape. What a
 *     writer that is «safe for HTML» escapes — the apostrophe, `<`, `>`, `&` — is read.
 *
 * The two tests the reviewer wrote are here as they were written; every text is built from code points.
 *
 * @subject-in milpa/command
 */
final class TwoProposalsOfASixthReviewTest extends TestCase
{
    /** @param array<string, mixed> $change */
    private static function refused(array $change): bool
    {
        try {
            Grants::grant($change);
        } catch (\InvalidArgumentException) {
            return true;
        }

        return false;
    }

    /** @param array<string, mixed> $facts */
    private static function standing(array $facts = []): GrantStanding
    {
        return new GrantStanding(...$facts + ['state' => GrantState::InForce, 'signatureFault' => null, 'watcherSignalAt' => Grants::at('2026-10-11T11:50:00Z'), 'receipts' => [Grants::receipt()], 'instruments' => ['lab.scan' => Grants::INSTRUMENT], 'recordFault' => null]);
    }

    // ── L2 (proposed) · a text is not a stack of marks, and does not begin with one ─────────────────────────

    public function testATextIsNotAStackOfMarksAndDoesNotBeginWithOne(): void
    {
        $acute = mb_chr(0x0301);
        $below = mb_chr(0x0316);
        self::assertFalse(self::refused(['undo' => 'versio' . $acute . 'n']), 'control: a letter and its accent written apart');
        self::assertFalse(self::refused(['undo' => 'Vie' . mb_chr(0x0302) . $acute . 't']), 'control: two marks on one letter');
        self::assertTrue(self::refused(['undo' => 'n' . str_repeat($acute . $below, 150) . 'ame']), 'accepted: «undo» with 300 marks stacked on one letter');
        self::assertTrue(self::refused(['conditions' => [['name' => $acute . 'name', 'statement' => 's', 'by' => ['operation' => 'lab:scan']]]]), 'accepted: a condition whose name begins with a combining mark');
        self::assertTrue(self::refused(['undo' => mb_chr(0x20E0) . 'take it back']), 'accepted: «undo» that begins with COMBINING ENCLOSING CIRCLE BACKSLASH');
        // …and the judge repeats a host's sentence «as it came» when it is such a line.
        $reason = 'stopped' . str_repeat($acute . $below, 200);
        self::assertStringNotContainsString($acute . $below . $acute . $below . $acute . $below . $acute . $below . $acute, StandingJudge::judge(Grants::grant(), self::standing(['state' => GrantState::Suspended, 'because' => $reason]), Grants::call())->sentence, 'the judge says back 400 stacked marks as they came');
    }

    public function testEightMarksInARowAreTextAndNineAreNot(): void
    {
        $mark = mb_chr(0x0301);
        self::assertTrue(Canonical::oneLine('a' . str_repeat($mark, 8) . 'b'));
        self::assertFalse(Canonical::oneLine('a' . str_repeat($mark, 9) . 'b'));
        self::assertTrue(Canonical::oneLine(str_repeat('a' . str_repeat($mark, 8), 40)), 'eight on each of forty letters: none of them nine in a row');
        self::assertFalse(Canonical::oneLine($mark), 'a mark alone begins with one');
        self::assertFalse(Canonical::oneLine(mb_chr(0x0BCD) . 'a'), 'a spacing or a non-spacing mark of any script, first');
        self::assertFalse(Canonical::oneLine(mb_chr(0x20DD) . 'a'), 'an enclosing mark, first');
        self::assertTrue(Canonical::oneLine('a ' . 'b' . $mark), 'a mark after a letter that comes after a space');
    }

    /** @return iterable<string, array{string}> */
    public static function whatLanguagesStackOnALetter(): iterable
    {
        // The most marks a letter carries in ordinary writing, per the reviewer: Vietnamese two, Thai three, pointed
        // Hebrew four or five. Each written decomposed, so that every mark is a character of its own.
        yield 'Spanish, the accent written apart' => ['versio' . mb_chr(0x0301) . 'n y an' . mb_chr(0x0303) . 'o'];
        yield 'Vietnamese, two marks on a letter' => ['Vie' . mb_chr(0x0302) . mb_chr(0x0323) . 't Nam'];
        yield 'Thai, a vowel above and a tone mark' => [mb_chr(0x0E17) . mb_chr(0x0E35) . mb_chr(0x0E48) . mb_chr(0x0E19) . mb_chr(0x0E31) . mb_chr(0x0E49) . mb_chr(0x0E19)];
        yield 'Hebrew with its points: a letter, dagesh, a vowel, a cantillation mark and meteg' => [mb_chr(0x05D1) . mb_chr(0x05BC) . mb_chr(0x05B0) . mb_chr(0x0591) . mb_chr(0x05BD) . mb_chr(0x05E8) . mb_chr(0x05B5) . mb_chr(0x05D0)];
        yield 'Arabic with its vowels and shadda' => [mb_chr(0x0645) . mb_chr(0x064F) . mb_chr(0x062D) . mb_chr(0x064E) . mb_chr(0x0645) . mb_chr(0x0651) . mb_chr(0x064E) . mb_chr(0x062F)];
        yield 'Hindi, a consonant cluster with a vowel sign and a nasal mark' => [mb_chr(0x0915) . mb_chr(0x094D) . mb_chr(0x0937) . mb_chr(0x093F) . mb_chr(0x0902)];
    }

    #[DataProvider('whatLanguagesStackOnALetter')]
    public function testWhatALanguageStacksOnALetterIsStillRead(string $text): void
    {
        $grant = Grants::grant(['undo' => $text, 'conditions' => [['name' => $text, 'statement' => $text, 'by' => ['operation' => 'lab:scan', 'free' => ['path']]]]]);

        self::assertSame([$text, $text], [$grant->undo, $grant->conditions[0]->name]);
        self::assertSame($grant->digest(), StandingGrant::fromJson($grant->canonical())->digest());
    }

    // ── L5 (proposed) · what a person signs spells plain letters plainly ───────────────────────────────────

    public function testWhatAPersonSignsSpellsPlainLettersPlainly(): void
    {
        $text = (string) json_encode(Grants::document(), \JSON_UNESCAPED_SLASHES);
        self::assertSame(Grants::grant()->digest(), StandingGrant::fromJson($text)->digest(), 'control');
        $escaped = str_replace('"lab:publish"', '"lab:' . implode('', array_map(static fn (string $letter): string => \sprintf('%su%04x', \chr(0x5C), \ord($letter)), str_split('publish'))) . '"', $text);
        self::assertNotSame($text, $escaped);
        try {
            $grant = StandingGrant::fromJson($escaped);
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);

            return;
        }
        self::fail('a text that shows «' . substr($escaped, (int) strpos($escaped, '"lab:'), 52) . '» is read as covering ' . $grant->covers[0]->operation->forCli() . ', with the digest of the plain text: ' . var_export($grant->digest() === Grants::grant()->digest(), true));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function escapesInATextAPersonSigns(): iterable
    {
        $u = \chr(0x5C) . 'u';
        yield 'a plain letter' => [$u . '0070', true];
        yield 'a plain letter, the hexadecimal in capitals' => [$u . '004A', true];
        yield 'the first printable character, the space' => [$u . '0020', true];
        yield 'a digit' => [$u . '0037', true];
        yield 'the colon, which is in every operation' => [$u . '003a', true];
        yield 'the dot' => [$u . '002E', true];
        yield 'the underscore' => [$u . '005f', true];
        yield 'the dash' => [$u . '002d', true];
        yield 'the first letter and the last, in each case' => [$u . '0041', true];
        yield 'the last capital' => [$u . '005A', true];
        yield 'the last small letter' => [$u . '007a', true];
        yield 'the first small letter' => [$u . '0061', true];
        yield 'the last digit' => [$u . '0039', true];
        yield 'the first digit' => [$u . '0030', true];
        yield 'the apostrophe, which writers that are safe for HTML escape' => [$u . '0027', false];
        yield 'the less-than sign, the same' => [$u . '003c', false];
        yield 'the greater-than sign' => [$u . '003E', false];
        yield 'the ampersand' => [$u . '0026', false];
        yield 'the equals sign' => [$u . '003d', false];
        yield 'the plus sign' => [$u . '002b', false];
        yield 'the backtick' => [$u . '0060', false];
        yield 'the slash' => [$u . '002f', false];
        yield 'the tilde, the last printable character' => [$u . '007E', false];
        yield 'the at sign, just before the capitals' => [$u . '0040', false];
        yield 'the bracket just after them' => [$u . '005b', false];
        yield 'the brace just after the small letters' => [$u . '007b', false];
        yield 'a letter of another alphabet whose low byte is a plain letter' => [$u . '0170', false];
        yield 'the quote, which a text can only say as an escape' => [$u . '0022', false];
        yield 'the backslash, the same' => [$u . '005C', false];
        yield 'a letter with an accent, which many writers escape' => [$u . '00e9', false];
        yield 'a letter of another alphabet' => [$u . '044f', false];
        yield 'the short escape of the quote' => [\chr(0x5C) . '"', false];
        yield 'the short escape of the slash, which PHP writes by default' => [\chr(0x5C) . '/', false];
        yield 'a backslash said twice and then the letters u0070' => [\chr(0x5C) . \chr(0x5C) . 'u0070', false];
    }

    public function testWhatAWriterThatIsSafeForHtmlWritesIsRead(): void
    {
        // The sentence this proposal carried said «no writer makes one». Writers do: PHP's own, asked to be safe for
        // HTML, and others by default. Their documents are read, and are the grant the plain text is.
        $document = Grants::document(['undo' => "don't publish <it> & stop"]);
        $plain = StandingGrant::fromJson((string) json_encode($document, \JSON_UNESCAPED_SLASHES));
        foreach ([\JSON_HEX_APOS, \JSON_HEX_TAG, \JSON_HEX_AMP, \JSON_HEX_QUOT, \JSON_HEX_APOS | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_QUOT] as $flags) {
            $written = (string) json_encode($document, $flags);
            self::assertSame($plain->digest(), StandingGrant::fromJson($written)->digest(), 'refused, or read as another grant, with flags ' . $flags);
        }
        self::assertStringContainsString('\u0027', (string) json_encode($document, \JSON_HEX_APOS), 'control: that writer does escape the apostrophe');
        self::assertSame("don't publish <it> & stop", $plain->undo);
    }

    #[DataProvider('escapesInATextAPersonSigns')]
    public function testOnlyAnEscapeThatSpellsAPlainCharacterIsRefused(string $escape, bool $refused): void
    {
        // In a VALUE a grant keeps, and in a KEY: the scan reads every text of the document.
        $text = (string) json_encode(Grants::document(['undo' => 'take it @@ back']), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        foreach ([str_replace('@@', $escape, $text), str_replace('"undo"', '"und' . $escape . '"', $text)] as $at => $written) {
            try {
                StandingGrant::fromJson($written);
                $said = null;
            } catch (\InvalidArgumentException $no) {
                $said = $no->getMessage();
            }
            if ($refused) {
                self::assertStringContainsString('as an escape', (string) $said, $at === 0 ? 'in a value' : 'in a key');
                self::assertSame(0, preg_match('/[^\x20-\x7E«»—…]/u', (string) $said));
            } else {
                self::assertStringNotContainsString('as an escape', (string) $said, $at === 0 ? 'in a value' : 'in a key');
            }
        }
    }
}
