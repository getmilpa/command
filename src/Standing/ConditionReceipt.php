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

use Milpa\Command\Consent\OperationId;

/**
 * THE FACT: this operation ran over this content and said this (greenhouse decisions/0615).
 *
 * It is what joins a measurement to an act, and every field is load-bearing:
 *
 *   · `by` + `arguments`  which operation measured, called how — what a condition's `by` is matched against.
 *   · `subject`           the digest of the content it measured, computed by the host from the bytes. The
 *                         same digest tomorrow is still measured; one byte apart is another candidate.
 *   · `ok`                what the run itself answered. Never a field someone filled in: a verdict that
 *                         arrives as an argument is a saying.
 *   · `instrument`        the digest of the operation as it was when it measured
 *                         ({@see \Milpa\Command\Operation::instrumentDigest()}). What an older instrument
 *                         found does not hold for the one that replaced it.
 *   · `who`, `at`         for the reader of the record. The judge joins by content, not by clock.
 *
 * WHAT IT IS NOT: proof that the host wrote it. This is a value; whether a receipt is the house's own
 * is a question about the ledger it was read from, and it is asked there.
 */
final readonly class ConditionReceipt
{
    /**
     * @param array<string, mixed> $arguments the arguments the measuring call carried
     */
    public function __construct(
        public OperationId $by,
        public array $arguments,
        public string $subject,
        public bool $ok,
        public string $instrument,
        public ?string $who,
        public \DateTimeImmutable $at,
    ) {
        if (trim($subject) === '' || trim($instrument) === '') {
            throw new \InvalidArgumentException('a condition receipt is bound to the digest of what was measured and to the digest of what measured it; one without either binds nothing');
        }
    }

    /**
     * The receipt as data.
     *
     * @return array{by: string, arguments: array<string, mixed>, subject: string, ok: bool, instrument: string, who: ?string, at: string}
     */
    public function toArray(): array
    {
        return [
            'by' => $this->by->canonical,
            'arguments' => $this->arguments,
            'subject' => $this->subject,
            'ok' => $this->ok,
            'instrument' => $this->instrument,
            'who' => $this->who,
            'at' => $this->at->format(\DATE_ATOM),
        ];
    }

    /**
     * A receipt back from what a ledger stored, or `null` when the line is not one.
     *
     * `null` and not an exception: this is asked line by line, and it says of ONE line that it is not a
     * receipt. It decides nothing about the book that line is in.
     *
     * WHAT A HOST OWES A LINE IT CAN NO LONGER READ. The judge listens to the LAST receipt about a content,
     * so a red one that stops reading as a receipt leaves the green before it speaking. A host that kept a
     * line as a receipt and gets `null` for it here — because this reader, or its own writer, changed since
     * — treats that line as it treats a grant it can no longer read ({@see StandingGrant::fromJson()}): it
     * is a FAULT of its record, never «nothing was measured». The `at` of a receipt is for the reader of the
     * record and is read loosely; the judge joins by content, not by clock.
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $at = \is_string($data['at'] ?? null) ? date_create_immutable($data['at']) : false;
        if (!\is_string($data['by'] ?? null) || !\is_array($data['arguments'] ?? null) || !\is_string($data['subject'] ?? null)
            || !\is_bool($data['ok'] ?? null) || !\is_string($data['instrument'] ?? null) || $at === false
            || trim($data['subject']) === '' || trim($data['instrument']) === '') {
            return null;
        }
        $who = $data['who'] ?? null;

        return new self(new OperationId($data['by']), $data['arguments'], $data['subject'], $data['ok'], $data['instrument'], \is_string($who) ? $who : null, $at);
    }
}
