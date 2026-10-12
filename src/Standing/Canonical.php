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
 * WHAT THIS SPELLING IS NOT: something every value survives. A number that is negative zero is written
 * with its sign and read back without it, and a structure nested deeper than JSON is read cannot be
 * read back at all. Nothing in this contract reads back what it digested, so its own digests hold; a
 * host that WRITES a line with this spelling and reads it later has to read it back before it trusts
 * it, and not write one that does not say the same.
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
     * @param string $json JSON that already parses: this reads its keys, it does not validate it
     *
     * @throws \InvalidArgumentException naming the key, and the object it is said twice in
     */
    public static function saidOnce(string $json, string $where): void
    {
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
                        '%s says «%s» twice%s: a document a person signs says each thing once — a reader would take the last one and never mention the first',
                        $where,
                        $key,
                        $open[$frame]['name'] === null ? '' : ', inside «' . $open[$frame]['name'] . '»',
                    ));
                }
                $open[$frame]['said']['«' . $key] = true;
                $open[$frame]['last'] = $key;
            } elseif ($byte === '{' || $byte === '[') {
                $parent = $open === [] ? null : $open[\count($open) - 1];
                $open[] = ['said' => [], 'last' => null, 'name' => $parent === null ? null : ($parent['last'] ?? $parent['name'])];
            } elseif ($byte === '}' || $byte === ']') {
                array_pop($open);
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
                    '%s carries «%s», which nothing reads (it reads: %s). A line the judge ignores is a limit nobody keeps — remove it.',
                    $where,
                    (string) $key,
                    implode(', ', $known),
                ));
            }
        }
    }

    /**
     * A required, non-blank string of a document, trimmed.
     *
     * @param array<array-key, mixed> $data
     */
    public static function text(array $data, string $key, string $where): string
    {
        $value = $data[$key] ?? null;
        if (!\is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException(\sprintf('%s needs «%s», in words', $where, $key));
        }

        return trim($value);
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    private static function sorted(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            if (\is_array($item)) {
                $value[$key] = self::sorted($item);
            }
        }

        return $value;
    }
}
