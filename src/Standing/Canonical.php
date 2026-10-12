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
 * One spelling for a structure that gets signed or digested (greenhouse decisions/0615).
 *
 * JSON has many spellings of the same object and a signature is over bytes, not over meaning: a
 * grant a person signed as `{"id":…,"to":…}` and a host rebuilt as `{"to":…,"id":…}` would be two
 * documents. So every map is sorted at every depth and every list is left alone — in a map, key
 * order carries nothing; in a list it is the meaning.
 *
 * Part of the contract, not a convenience: a host that keeps a record of grants and receipts digests
 * its lines with the same spelling, so that a judge written anywhere reads the same bytes.
 *
 * THE ORDER IS THE ORDER OF THE BYTES. A map is sorted by the bytes of its keys, the way any language
 * sorts text — never the way one language compares what looks like a number: `"10"` comes before `"9"`,
 * and `"1.0"` and `"1.00"` are two keys with one place each. (Sorted as numbers they were equal, so the
 * order a document was written in showed in its digest: one grant, two or three digests.)
 *
 * WHAT THIS SPELLING IS NOT: something every value survives. A number that is negative zero is written
 * with its sign and read back without it; a whole float comes back a whole number; a float is written
 * with the precision of the process that writes it (`serialize_precision`), so two processes may spell
 * one differently; a map whose keys are 0, 1, 2… in that order comes back a list; a structure nested
 * deeper than JSON is read cannot be read back at all; and what is not a number or not text — `INF`,
 * `NAN`, bytes that are not UTF-8 — is not spelled: it throws `JsonException`. A grant carries none of
 * these: its values are text, whole numbers and true or false. Nothing in this contract reads back
 * what it digested, so its own digests hold; a host that WRITES a line with this spelling and reads it
 * later has to read it back before it trusts it, and not write one that does not say the same.
 */
final class Canonical
{
    /** How many bytes of what a drafter wrote a refusal says back, spelled out ({@see self::shown()}). */
    private const SHOWN = 96;

    /** How many characters of a host's own sentence a verdict repeats ({@see self::said()}). */
    private const SAID = 480;

    /**
     * What one line of plain text never carries ({@see self::oneLine()} lists it). By Unicode's general categories
     * where there is one — controls, format characters, private use, unassigned, the line and paragraph separators,
     * and every space but U+0020 — and by code point where there is none: the variation selectors, the combining
     * grapheme joiner, and the vowels and fillers that print as nothing. A surrogate needs no place here: UTF-8 has
     * no spelling for one, so it arrives as bytes that are not text, and those match nothing and are refused for that.
     *
     * Together they leave out NO code point Unicode calls `Default_Ignorable_Code_Point`. That property is the rule,
     * and a test asks every code point there is for it — of this list ALONE too. The name of the property is added
     * to the list where the PCRE knows it ({@see self::unseen()}); it cannot stand in for the list, because a PCRE
     * older than 10.40 does not know the name, and a pattern that does not compile refuses every text there is.
     *
     * This is the class of characters, still open: what closes it is put after it.
     */
    private const UNSEEN = '[\p{Cc}\p{Cf}\p{Co}\p{Cn}\p{Zl}\p{Zp}\x{034F}\x{180B}-\x{180F}\x{FE00}-\x{FE0F}\x{E0100}-\x{E01EF}\x{17B4}\x{17B5}\x{115F}\x{1160}\x{3164}\x{FFA0}\x{2800}\x{13441}\x{13442}\x{16FE4}\x{1D159}';

    /**
     * PROPOSED, NOT DECIDED (the sixth review, L2; greenhouse decisions/0615): a text does not begin with a combining
     * mark, and carries no more than eight of them in a row.
     *
     * Every mark is text, in any number and anywhere: a letter under three hundred stacked marks is drawn over the
     * lines above and below it wherever glyphs are not clipped, and a mark with nothing before it sits on whatever a
     * host printed just before the text — its own label, its opening quote. Ordinary writing stacks few: Vietnamese
     * two, Thai three, pointed Hebrew four or five. To take the proposal out: this constant, and where it is used.
     */
    private const MARKS = '^\p{M}|\p{M}{9}';

    /**
     * The canonical bytes of a structure.
     *
     * @param array<array-key, mixed> $value
     */
    public static function json(array $value): string
    {
        return json_encode(self::sorted($value), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    /**
     * Refuse a JSON text that says a key twice in one object — or that writes a list as an object.
     *
     * A reader of JSON takes the last one and says nothing of the first: the text a person is shown and the
     * document the judge reads are then not the same, and whoever drafts it chooses which the person sees. A
     * document a person signs says each thing once — at every depth, and however a key is spelled.
     *
     * AND A LIST IS WRITTEN AS A LIST. An object whose keys are "0", "1", "2"… in that order IS a list once it is
     * read into an array: a second spelling of the same document, with the same digest, in a contract that asks for
     * one. Which of the two was written can only be told from the text, and this scan is the one reading of the
     * text there is — so it is told here, and nothing decodes the text a second time to ask.
     *
     * It answers for any text. One that does not parse — or that is nested deeper than a document is read — is
     * refused before a key is looked at: the scan below walks a text it can trust to be closed, and it used to walk
     * one it could not, and never came back. And it costs what the text is long: a frame is asked for its name, never
     * kept by a second hand, which copied every key an object had said each time one more was read.
     *
     * @throws \InvalidArgumentException naming the key, and the object it is said twice in; naming where the list
     *                                   is; or saying the text is not JSON
     */
    public static function saidOnce(string $json, string $where): void
    {
        if (!json_validate($json)) {
            throw new \InvalidArgumentException($where . ' is not JSON this contract reads: it does not parse, or it is nested deeper than a document is read');
        }
        // One frame per object or list that is open: the keys an object has said, the name it is known by, and
        // whether its keys so far are the places of a list — "0", then "1", then "2".
        $open = [];
        $text = null;
        for ($at = 0, $length = \strlen($json); $at < $length; ++$at) {
            $byte = $json[$at];
            if ($byte === '"') {
                $from = $at;
                for (++$at; $json[$at] !== '"'; ++$at) {
                    // PROPOSED, NOT DECIDED (the sixth review, L5, as the seventh adjusted it; greenhouse
                    // decisions/0615): what a person signs spells plainly what NAMES are made of. A text is read after
                    // its escapes are, so `lab:` followed by seven `\u00..` groups was shown as escapes and read as
                    // `lab:publish`, with the digest of the plain text. No text a grant keeps needs such an escape.
                    // To take the proposal out: this `if`, and {@see self::spellsWhatNamesAreMadeOf()}.
                    if ($json[$at] === '\\' && $json[$at + 1] === 'u' && self::spellsWhatNamesAreMadeOf(substr($json, $at + 2, 4))) {
                        throw new \InvalidArgumentException(\sprintf(
                            '%s writes %s as an escape (\\u%s): what a person signs spells plainly what a name is made of — a reader would show the escape and read the character',
                            $where,
                            self::shown(\chr((int) hexdec(substr($json, $at + 2, 4)))),
                            strtolower(substr($json, $at + 2, 4)),
                        ));
                    }
                    $at += $json[$at] === '\\' ? 1 : 0;
                }
                $text = substr($json, $from, $at - $from + 1);
            } elseif ($byte === ':') {
                // Outside a text, a colon follows a key and nothing else.
                $key = (string) json_decode((string) $text);
                $frame = \count($open) - 1;
                if (isset($open[$frame]['said']['«' . $key])) {
                    throw new \InvalidArgumentException(\sprintf(
                        '%s says %s twice%s: a document a person signs says each thing once — a reader would take the last one and never mention the first',
                        $where,
                        self::shown($key),
                        $open[$frame]['name'] === null ? '' : ', inside ' . self::shown($open[$frame]['name']),
                    ));
                }
                $open[$frame]['places'] = $open[$frame]['places'] && $key === (string) \count($open[$frame]['said']);
                $open[$frame]['said']['«' . $key] = true;
                $open[$frame]['last'] = $key;
            } elseif ($byte === '{' || $byte === '[') {
                $last = \count($open) - 1;
                $open[] = ['said' => [], 'places' => true, 'last' => null, 'name' => $last < 0 ? null : ($open[$last]['last'] ?? $open[$last]['name'])];
            } elseif ($byte === '}' || $byte === ']') {
                $closed = array_pop($open);
                // Only an object says keys, so only an object can have said the places of a list.
                if ($closed['said'] !== [] && $closed['places']) {
                    throw new \InvalidArgumentException(\sprintf('%s writes a list as an object with the keys "0", "1"…%s: a list is written as a list', $where, $closed['name'] === null ? '' : ', in ' . self::shown($closed['name'])));
                }
            }
        }
    }

    /**
     * PROPOSED, NOT DECIDED ({@see self::saidOnce()}): whether four hexadecimal digits name a character NAMES ARE MADE
     * OF — a letter, a digit, the colon, the dot, the underscore, the dash, or the space.
     *
     * Those are what an id, an operation, the name of an argument, an axis and an expiry are spelled with: written as
     * escapes, a name is shown as noise and read as itself. What is spared is what ordinary writers DO escape. A
     * writer that is «safe for HTML» writes the apostrophe, `<`, `>`, `&` — and some `=`, `+` and the backtick — as
     * escapes: PHP's own `json_encode` with `JSON_HEX_APOS`, `JSON_HEX_TAG` or `JSON_HEX_AMP`, and Go, Gson and
     * System.Text.Json by default. None of those characters can spell a name, and a sentence that carries one is
     * read.
     *
     * THE STRICT ALTERNATIVE, for whoever decides: refuse the escape of EVERY printable ASCII character but the quote
     * and the backslash — 93 of them — which a JSON text can only say as escapes. It is one line,
     * `return $code >= 0x20 && $code <= 0x7E && $code !== 0x22 && $code !== 0x5C;`, and it costs the documents of
     * those writers: a sentence with an apostrophe or an ampersand, written by one of them, is then not read.
     */
    private static function spellsWhatNamesAreMadeOf(string $hex): bool
    {
        $code = (int) hexdec($hex);

        return $code <= 0x7F && preg_match('/^[A-Za-z0-9:._ -]$/D', \chr($code)) === 1;
    }

    /**
     * What a drafter wrote, the way a refusal may say it back: spelled as JSON spells it, in plain characters, and
     * at most a line of it.
     *
     * A refusal is read by a person, often at a terminal, and what it quotes was written by whoever drafted the
     * document — who may not be that person. Said back raw, a key or an id can clear the line it is printed on and
     * leave «OK: … is in force» in its place, or run for three hundred thousand bytes. So nothing a terminal obeys
     * and no character that reorders what is read is ever said back as it came: every one is written as its escape
     * — every letter that is not plain ASCII too, so that nothing said back can pass for this contract's own «…».
     * And the line it is cut to ends where a character ends: half an escape reads as something else.
     */
    public static function shown(mixed $written): string
    {
        // Whatever cannot be spelled is spelled in part: this is asked of what a document held, never refused. JSON
        // writes every control character as its escape but one — DEL — so that one is written as its escape here.
        $spelled = str_replace("\x7f", '\u007f', (string) json_encode($written, \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_PARTIAL_OUTPUT_ON_ERROR | \JSON_UNESCAPED_SLASHES));
        if (\strlen($spelled) <= self::SHOWN) {
            return $spelled;
        }
        // The longest run of WHOLE characters within the line: a backslash alone, or `\u00`, is left out.
        preg_match('/^(?:\\\\u[0-9a-f]{4}|\\\\[^u]|[^\\\\])*/', substr($spelled, 0, self::SHOWN), $whole);

        return $whole[0] . '… (' . \strlen($spelled) . ' bytes as written)';
    }

    /**
     * A name this contract already holds to plain characters — the identity of an operation — the way a sentence says
     * it: whole when it is at most a line, and the start of it with its length when it is longer.
     *
     * Such a name needs no escape: its own pattern leaves nothing in it to obey. It has no length of its own though —
     * a hundred thousand letters are one name — and every sentence that said «run `…`» repeated them all.
     */
    public static function short(string $plain): string
    {
        return \strlen($plain) <= self::SHOWN ? $plain : substr($plain, 0, self::SHOWN) . '… (' . \strlen($plain) . ' bytes as written)';
    }

    /**
     * A sentence a HOST hands the judge — why a grant is stopped, why its signature does not hold — the way a
     * verdict repeats it: as it came when it is one line of plain text, and at most {@see self::SAID} characters of
     * it; spelled out by {@see self::shown()} when it is anything else.
     *
     * A reason is what a person reads to know what to do next, and it names things they have to cite — the digest
     * of a suspension, the key that left: cut to the line `shown()` allows and written in escapes, it would no
     * longer say them. So a sentence that is plain is repeated plain. What it never does is reach a terminal with a
     * byte the terminal obeys, or run for as long as whoever wrote it chose.
     */
    public static function said(string $sentence): string
    {
        if (!self::oneLine($sentence)) {
            return self::shown($sentence);
        }
        if (preg_match('/^.{' . self::SAID . '}(?=.)/us', $sentence, $start) !== 1) {
            return $sentence;
        }

        return $start[0] . '… (' . \strlen($sentence) . ' bytes as written)';
    }

    /**
     * Refuse a key this contract does not read.
     *
     * A document a person signs must not carry a line the judge ignores: someone who writes
     * `"budget": 50` believes they bounded something, and a reader that skipped it in silence would
     * let them keep believing it.
     *
     * @param array<array-key, mixed> $data
     * @param list<string>            $known
     */
    public static function only(array $data, array $known, string $where): void
    {
        foreach (array_keys($data) as $key) {
            if (!\in_array($key, $known, true)) {
                throw new \InvalidArgumentException(\sprintf(
                    '%s carries %s, which nothing reads (it reads: %s). A line the judge ignores is a limit nobody keeps — remove it.',
                    $where,
                    self::shown((string) $key),
                    implode(', ', $known),
                ));
            }
        }
    }

    /**
     * A required, non-blank string of a document, AS IT WAS WRITTEN — and one line of plain text
     * ({@see self::oneLine()}).
     *
     * Nothing is taken off its ends. A reader that trims a text repairs it: the document a person signed and the
     * one that is kept are then two, and what was trimmed — a line break, a tab, a null byte — was written by
     * someone. A text that is not as it reads is refused, and whoever drafted it writes it again.
     *
     * @param array<array-key, mixed> $data
     */
    public static function text(array $data, string $key, string $where): string
    {
        $value = $data[$key] ?? null;
        if (!\is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException(\sprintf('%s needs «%s», in words', $where, $key));
        }
        if (!self::oneLine($value)) {
            throw new \InvalidArgumentException(self::notOneLine("{$where} gives «{$key}»", $value));
        }

        return $value;
    }

    /**
     * Whether a text is ONE LINE OF PLAIN TEXT: read as it is written, with nothing in it that is not seen
     * (decided by Rod, 2026-10-12).
     *
     * A grant is read by a person before they sign it, and again every time a house lists it or a judge names it in
     * a refusal. A text that carried a line break, an escape, a character that turns the direction text is read in —
     * or one that is not shown at all — would be read otherwise than its bytes: a condition's name could clear the
     * line a terminal printed it on and leave «OK» in its place, and two names that read the same could be two.
     *
     * WHAT IS KEPT: the letters of every language, their accents, digits, punctuation and symbols — and the ordinary
     * space (U+0020), as many as the sentence has, inside it.
     *
     * WHAT IS REFUSED, exactly — every text a grant keeps is refused when it is read if it carries one:
     *
     *   · a control character: the C0 controls, DEL and the C1 controls (U+0000–U+001F, U+007F–U+009F) — the line
     *     break and the tab are two of them;
     *   · a FORMAT character, every one (general category Cf): the direction controls (U+061C, U+200E, U+200F,
     *     U+202A–U+202E, U+2066–U+2069), the zero-width space, non-joiner and joiner (U+200B–U+200D), the soft
     *     hyphen (U+00AD), the word joiner and the invisible operators (U+2060–U+2064), U+206A–U+206F, the byte order
     *     mark (U+FEFF), the interlinear annotation marks (U+FFF9–U+FFFB), the tags (U+E0001, U+E0020–U+E007F), the
     *     Mongolian vowel separator (U+180E) — and the rest of that category, some of which do draw something:
     *     U+0600–U+0605, U+06DD, U+070F, U+0890–U+0891, U+08E2, U+110BD, U+110CD, U+13430–U+1343F, U+1BCA0–U+1BCA3,
     *     U+1D173–U+1D17A;
     *   · the line and the paragraph separator (U+2028, U+2029);
     *   · every space that is not U+0020 (general category Zs): U+00A0, U+1680, U+2000–U+200A, U+202F, U+205F,
     *     U+3000;
     *   · a variation selector (U+180B–U+180D, U+180F, U+FE00–U+FE0F, U+E0100–U+E01EF) and the combining grapheme
     *     joiner (U+034F);
     *   · a vowel or a filler that prints as nothing: U+17B4, U+17B5 (the Khmer inherent vowels), U+115F, U+1160,
     *     U+3164, U+FFA0, U+2800, and four that are blank by their own definition — U+13441 and U+13442 (the full and
     *     the half blank of Egyptian hieroglyphs), U+16FE4 (the Khitan small script filler), U+1D159 (the musical
     *     null notehead);
     *   · a private-use character (Co), a place nothing is assigned to (Cn — the noncharacters and the rest of the
     *     tag block among them), and bytes that are not text at all — a surrogate among them, which UTF-8 does not
     *     spell;
     *   · and a SPACE AT EITHER END: a text is given as it reads, and nothing trims it.
     *
     * PROPOSED, NOT DECIDED ({@see self::MARKS}): a text that begins with a combining mark, or carries more than
     * eight of them in a row, is refused too.
     *
     * WHAT THAT COSTS, and was accepted: a no-break space, a soft hyphen, a zero-width joiner or non-joiner and a
     * variation selector are part of how some languages and some emoji are written, and a text that needs one is
     * not taken.
     *
     * WHICH PLACES ARE ASSIGNED, and which characters are format characters, is read from the Unicode tables of the
     * PCRE the host runs (the lists above are those of Unicode 16). A host with older tables refuses a character a
     * newer one takes — a letter added to Unicode since — and a document that uses none of those is read the same
     * everywhere.
     *
     * THE RULE BEHIND THE LIST: no code point Unicode itself says is shown as nothing — its property
     * `Default_Ignorable_Code_Point` — is taken. The list above covers that whole set, and more; the fillers are
     * not of that set, and are named one by one.
     *
     * WHAT THIS IS NOT: a promise that two texts that look the same are one. A letter of another alphabet that looks
     * the same is text, and so is a mark that draws nothing of its own after a letter of another script.
     */
    public static function oneLine(string $text): bool
    {
        return preg_match(self::unseen(), $text) === 0 && !str_starts_with($text, ' ') && !str_ends_with($text, ' ');
    }

    /**
     * The pattern that finds what one line of plain text never carries: the list, and — where this PCRE knows its
     * name — Unicode's own property for «shown as nothing» beside it.
     *
     * The list alone leaves out no code point of that property under the tables it was written against. The property
     * is there for the tables that come later: a character added to the set is refused the day a house runs on a
     * PCRE that knows it, not the day this package is read again.
     *
     * @param bool|null $byProperty whether to name the property; `null` asks this PCRE whether it knows it
     */
    private static function unseen(?bool $byProperty = null): string
    {
        static $known = null;
        $byProperty ??= $known ??= self::knows('DI');

        return '/' . self::UNSEEN . ($byProperty ? '\p{DI}' : '') . ']|(?! )\p{Zs}|' . self::MARKS . '/u';
    }

    /** Whether the PCRE this runs on knows a Unicode property by that name: one it does not is a pattern that does not compile. */
    private static function knows(string $property): bool
    {
        return @preg_match('/\p{' . $property . '}/u', '') !== false;
    }

    /** What a refusal says of a text that is not one line of plain text; `$what` names it, as «X gives «key»». */
    public static function notOneLine(string $what, string $text): string
    {
        return \sprintf('%s in one line of plain text — no line break, no tab, no control or format character, nothing that is not shown, no space but the ordinary one and none at its ends: what a person signs is read as it is written. Got %s', $what, self::shown($text));
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    private static function sorted(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value, \SORT_STRING);
        }
        foreach ($value as $key => $item) {
            if (\is_array($item)) {
                $value[$key] = self::sorted($item);
            }
        }

        return $value;
    }
}
