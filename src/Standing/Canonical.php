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
     * Refuse a JSON text that says a key twice in one object.
     *
     * A reader of JSON takes the last one and says nothing of the first: the text a person is shown and the
     * document the judge reads are then not the same, and whoever drafts it chooses which the person sees. A
     * document a person signs says each thing once — at every depth, and however a key is spelled.
     *
     * It answers for any text. One that does not parse — or that is nested deeper than a document is read — is
     * refused before a key is looked at: the scan below walks a text it can trust to be closed, and it used to walk
     * one it could not, and never came back. And it costs what the text is long: a frame is asked for its name, never
     * kept by a second hand, which copied every key an object had said each time one more was read.
     *
     * @throws \InvalidArgumentException naming the key, and the object it is said twice in — or saying the text is not JSON
     */
    public static function saidOnce(string $json, string $where): void
    {
        if (!json_validate($json)) {
            throw new \InvalidArgumentException($where . ' is not JSON this contract reads: it does not parse, or it is nested deeper than a document is read');
        }
        // One frame per object or list that is open: the keys an object has said, and the name it is known by.
        $open = [];
        $text = null;
        for ($at = 0, $length = \strlen($json); $at < $length; ++$at) {
            $byte = $json[$at];
            if ($byte === '"') {
                $from = $at;
                for (++$at; $json[$at] !== '"'; ++$at) {
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
                $open[$frame]['said']['«' . $key] = true;
                $open[$frame]['last'] = $key;
            } elseif ($byte === '{' || $byte === '[') {
                $last = \count($open) - 1;
                $open[] = ['said' => [], 'last' => null, 'name' => $last < 0 ? null : ($open[$last]['last'] ?? $open[$last]['name'])];
            } elseif ($byte === '}' || $byte === ']') {
                array_pop($open);
            }
        }
    }

    /**
     * What a drafter wrote, the way a refusal may say it back: spelled as JSON spells it, in plain characters, and
     * at most a line of it.
     *
     * A refusal is read by a person, often at a terminal, and what it quotes was written by whoever drafted the
     * document — who may not be that person. Said back raw, a key or an id can clear the line it is printed on and
     * leave «OK: … is in force» in its place, or run for three hundred thousand bytes. So nothing a terminal obeys
     * and no character that reorders what is read is ever said back as it came: every one is written as its escape.
     */
    public static function shown(mixed $written): string
    {
        // Whatever cannot be spelled is spelled in part: this is asked of what a document held, never refused. JSON
        // writes every control character as its escape but one — DEL — so that one is written as its escape here.
        $spelled = str_replace("\x7f", '\u007f', (string) json_encode($written, \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_PARTIAL_OUTPUT_ON_ERROR | \JSON_UNESCAPED_SLASHES));

        return \strlen($spelled) <= 96 ? $spelled : substr($spelled, 0, 96) . '… (' . \strlen($spelled) . ' bytes as written)';
    }

    /**
     * Refuse a JSON text that writes a list as an object whose keys are "0", "1", "2"…
     *
     * Read into an array, such an object IS a list: a second spelling of the same document, with the same digest,
     * in a contract that asks for one. Which of the two was written can only be told from the text, so it is told
     * here, from the text as it parses.
     *
     * @param mixed $read the text, decoded with objects kept as objects
     *
     * @throws \InvalidArgumentException naming where the list is
     */
    public static function listsAreLists(mixed $read, string $where, ?string $name = null): void
    {
        if (\is_object($read)) {
            $keys = array_keys(get_object_vars($read));
            if ($keys !== [] && array_map(strval(...), $keys) === array_map(strval(...), array_keys($keys))) {
                throw new \InvalidArgumentException(\sprintf('%s writes a list as an object with the keys "0", "1"…%s: a list is written as a list', $where, $name === null ? '' : ', in ' . self::shown($name)));
            }
        }
        if (\is_object($read) || \is_array($read)) {
            foreach ((array) $read as $key => $item) {
                self::listsAreLists($item, $where, \is_object($read) ? (string) $key : $name);
            }
        }
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
     * A required, non-blank string of a document, trimmed — and one line of plain text ({@see self::oneLine()}).
     *
     * @param array<array-key, mixed> $data
     */
    public static function text(array $data, string $key, string $where): string
    {
        $value = $data[$key] ?? null;
        if (!\is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException(\sprintf('%s needs «%s», in words', $where, $key));
        }
        if (!self::oneLine(trim($value))) {
            throw new \InvalidArgumentException(self::notOneLine("{$where} gives «{$key}»", $value));
        }

        return trim($value);
    }

    /**
     * Whether a text is ONE LINE OF PLAIN TEXT: read as it is written (decided by Rod, 2026-10-12).
     *
     * A grant is read by a person before they sign it, and again every time a house lists it or a judge names it in
     * a refusal. A text that carried a line break, a tab, an escape or any other control character — or one of the
     * characters that turn or isolate the direction text is read in, or a line or paragraph separator — would be
     * read otherwise than its bytes: a condition's name could clear the line a terminal printed it on and leave «OK»
     * in its place. So every text a grant keeps is refused when it is read if it carries one:
     *
     *   · the C0 controls, DEL and the C1 controls (U+0000–U+001F, U+007F–U+009F);
     *   · the direction controls (U+061C, U+200E, U+200F, U+202A–U+202E, U+2066–U+2069);
     *   · the line and the paragraph separator (U+2028, U+2029);
     *   · and bytes that are not text at all.
     *
     * Letters written right to left are text and are taken; what is refused is the control that turns them.
     */
    public static function oneLine(string $text): bool
    {
        return preg_match('/[\x{0000}-\x{001F}\x{007F}-\x{009F}\x{061C}\x{200E}\x{200F}\x{2028}-\x{202E}\x{2066}-\x{2069}]/u', $text) === 0;
    }

    /** What a refusal says of a text that is not one line of plain text; `$what` names it, as «X gives «key»». */
    public static function notOneLine(string $what, string $text): string
    {
        return \sprintf('%s in one line of plain text — no line break, no tab, no control character, and nothing that turns the direction it is read in: what a person signs is read as it is written. Got %s', $what, self::shown($text));
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
