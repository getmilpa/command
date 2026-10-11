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
