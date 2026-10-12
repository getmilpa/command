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
    public OperationId $by;

    /** What the run itself answered: true or false, and nothing that stands for one. */
    public bool $ok;

    /**
     * @param mixed                $by        the operation that measured, by its name as TEXT, as it reads — never an
     *                                        identity built elsewhere, which has already had its ends taken off:
     *                                        anything but a text is a `TypeError`, whatever the caller's file declares
     * @param array<string, mixed> $arguments the arguments the measuring call carried
     * @param mixed                $ok        what the run answered: `true` or `false`. THE TYPE IS ASKED HERE, NOT LEFT
     *                                        TO THE CALLER'S FILE: from PHP's default mode a parameter typed `bool`
     *                                        reads every text but "" and "0" as true — the word «false», «no»,
     *                                        «failed» — before a constructor looks, and a no that was said would be
     *                                        kept as a yes. Anything but a boolean is a `TypeError` for every caller
     *
     * @throws \TypeError when the operation is not given as text, or the answer is not a boolean
     */
    public function __construct(
        mixed $by,
        public array $arguments,
        public string $subject,
        mixed $ok,
        public string $instrument,
        public ?string $who,
        public \DateTimeImmutable $at,
    ) {
        if (!\is_bool($ok)) {
            throw new \TypeError(\sprintf('what a run answered is given as true or false, never as a word or a number that stands for one; got %s', get_debug_type($ok)));
        }
        $this->ok = $ok;
        if (trim($subject) === '' || trim($instrument) === '') {
            throw new \InvalidArgumentException('a condition receipt is bound to the digest of what was measured and to the digest of what measured it; one without either binds nothing');
        }
        // NOTHING IS TRIMMED HERE EITHER. The judge joins a receipt to an act by the text of these two digests: one
        // with a line break after it is another text, and a red receipt spelled that way said nothing of the content
        // it measured — the green before it went on speaking.
        foreach (['the content that was measured' => $subject, 'what measured it' => $instrument] as $which => $digest) {
            if (!Canonical::oneLine($digest)) {
                throw new \InvalidArgumentException(Canonical::notOneLine("a condition receipt gives the digest of {$which}", $digest));
            }
        }
        $this->by = NamedCall::named($by)
            ?? throw new \InvalidArgumentException('a condition receipt says which operation measured, by its name as it reads; got ' . Canonical::shown($by));
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
     * is a FAULT of its record, never «nothing was measured», and it says so to the judge in
     * {@see GrantStanding::$recordFault} — which the judge refuses on, and which a host that says nothing is
     * read as not having checked. The `at` of a receipt is for the reader of the record and is read loosely; the
     * judge joins by content, not by clock.
     *
     * `null` IS NOT A RECEIPT, AND THE JUDGE DOES NOT SKIP IT. A host that builds its list with
     * `array_map(ConditionReceipt::fromArray(...), $lines)` hands the judge that `null`, and the judge refuses on it
     * ({@see StandingJudge::judge()}): anything in the list that is not a receipt is a line that was not read.
     *
     * A line whose digests are not as they read — a space or a line break around one, a character nobody sees in
     * one — is not a receipt either: nothing is trimmed, and the judge compares them as text.
     *
     * A line whose `by` does not name one operation is not a receipt: it would be matched against no condition and
     * no instrument, and a red one kept that way stops speaking.
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $at = \is_string($data['at'] ?? null) ? date_create_immutable($data['at']) : false;
        if (!\is_string($data['by'] ?? null) || !\is_array($data['arguments'] ?? null) || !\is_string($data['subject'] ?? null)
            || !\is_bool($data['ok'] ?? null) || !\is_string($data['instrument'] ?? null) || $at === false
            || trim($data['subject']) === '' || trim($data['instrument']) === ''
            || !Canonical::oneLine($data['subject']) || !Canonical::oneLine($data['instrument'])) {
            return null;
        }
        if (NamedCall::named($data['by']) === null) {
            return null;
        }
        $who = $data['who'] ?? null;

        return new self($data['by'], $data['arguments'], $data['subject'], $data['ok'], $data['instrument'], \is_string($who) ? $who : null, $at);
    }
}
