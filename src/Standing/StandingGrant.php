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
use Milpa\Command\Effect\Authority;
use Milpa\Command\Effect\EffectProfile;
use Milpa\Command\Effect\Externality;
use Milpa\Command\Effect\Mutation;
use Milpa\Command\Effect\Reversibility;
use Milpa\Command\Effect\Subject;

/**
 * A STANDING GRANT: what a person signs once so that a seat may repeat an act without asking — under
 * a ceiling, on conditions, and only while its falsifier stays quiet (greenhouse decisions/0613, 0615).
 *
 * ── WHAT IT REPLACES ────────────────────────────────────────────────────────────────────────────
 *
 * Not the signature on each call. Measured on the published family (greenhouse decisions/0615): what
 * makes an act «the person's» is the scope a seat lacks, and the only way to let a seat repeat that
 * act was to hand it the scope whole — no ceiling, no condition, nothing to take it back when the
 * evidence changes. A grant is narrower than that on every count, and it is the first thing in the
 * family that can say «only this candidate, and only measured».
 *
 * ── WHAT IT CARRIES, AND WHY EACH PART ──────────────────────────────────────────────────────────
 *
 *   · `to`         the seats it lends to. Never «anyone».
 *   · `covers`     the operations it names, with the arguments it fixes. A positive, closed list: what
 *                  is not named is not covered, and nothing here is a pattern.
 *   · `ceiling`    the widest effect a covered call may have, on every axis.
 *   · `conditions` what must have been MEASURED on the very content an act touches, each by the
 *                  operation that measures it.
 *   · `undo`       how an absorbed act is taken back, in words, for the record of each one.
 *   · `falsifier`  what would show the grant wrong, and the operation that watches for it.
 *   · `expiresAt`  optional; it only ever narrows.
 *
 * ── THE FLOORS NO GRANT CROSSES (Rod, 2026-10-11) ───────────────────────────────────────────────
 *
 * Refused HERE, at construction, so that no such document can exist to be signed:
 *
 *   · a ceiling with an unclassified axis — measured: an unclassified ceiling admits everything;
 *   · a ceiling above «compensatable» — a grant never lends what cannot at least be compensated;
 *   · a ceiling whose AUTHORITY is above «write_as_user» — nothing a grant lends may itself give
 *     authority. The floor is the axis that says it, not a list of words: measured on the published
 *     catalogue, thirteen operations declare institutional authority and four of them ask for the word
 *     a list would have looked for (greenhouse decisions/0615, «Lo que la revisión encontró», 4);
 *   · covering any `standing.*` operation — nothing a grant lends may give, restore or withdraw a grant;
 *   · no condition at all — that is the scope handed over whole, with a watcher;
 *   · a falsifier nothing watches — {@see Falsifier}.
 *
 * The other half of the first floor — never an operation that gives authority by the HOST's own words —
 * needs the host's catalogue and is asked there.
 *
 * ── WHAT IT CANNOT SAY ──────────────────────────────────────────────────────────────────────────
 *
 * MONEY. There is no axis for it, so no ceiling here can bound what a call spends, and nothing in an
 * operation's declaration says that it spends. «Do not grant over an operation that spends» is a rule
 * for whoever signs, not something this class or any judge of it can hold.
 *
 * ── WHAT IT IS NOT ──────────────────────────────────────────────────────────────────────────────
 *
 * An authority. This is the document; whether a person signed it, whether it is still in force and
 * whether a given call passes are asked elsewhere ({@see StandingJudge}), with facts a host gathers.
 * Its state is never a field of it: what was signed is not edited.
 */
final readonly class StandingGrant
{
    /** The highest reversibility a ceiling may carry. */
    public const Reversibility CEILING_REVERSIBILITY = Reversibility::Compensatable;

    /** The highest authority a ceiling may carry: acting as a user. Above it an act decides what the house believes. */
    public const Authority CEILING_AUTHORITY = Authority::WriteAsUser;

    private const ID = '/^[a-z0-9][a-z0-9._-]{0,62}$/D';

    private const KEYS = ['id', 'supersedes', 'lifts', 'to', 'covers', 'ceiling', 'conditions', 'undo', 'falsifier', 'expires_at'];

    /**
     * The longest text read as a standing grant, in bytes (256 KB).
     *
     * A grant is a page a person reads before signing it. A text a thousand times that long is not one, and reading
     * it is work whoever wrote it chose for the reader: keys picked to land in one bucket of PHP's own table held
     * this reader for twenty seconds at 2.4 MB. So a longer text is refused before a byte of it is looked at.
     */
    public const LONGEST = 262_144;

    /** @var list<string> */
    public array $to;

    /** @var list<NamedCall> */
    public array $covers;

    /** @var list<GrantCondition> */
    public array $conditions;

    /**
     * @param array<array-key, mixed> $to         the seats it lends to, each by the key the host knows it by
     * @param array<array-key, mixed> $covers     the calls it names
     * @param array<array-key, mixed> $conditions what must be measured on the content an act touches
     * @param string|null             $lifts      for a grant that supersedes a SUSPENDED one: the suspension it lifts,
     *                                            by the digest the host shows for it. Taking a suspended grant out of
     *                                            the way is growing back, and growing back names what it lifts
     */
    public function __construct(
        public string $id,
        array $to,
        array $covers,
        public EffectProfile $ceiling,
        array $conditions,
        public string $undo,
        public Falsifier $falsifier,
        public ?string $supersedes = null,
        public ?\DateTimeImmutable $expiresAt = null,
        public ?string $lifts = null,
    ) {
        self::known($id);
        if ($supersedes !== null && (preg_match(self::ID, $supersedes) !== 1 || $supersedes === $id)) {
            throw new \InvalidArgumentException("standing grant «{$id}» supersedes another grant by its id, and never itself; got " . Canonical::shown($supersedes));
        }

        if ($lifts !== null && ($supersedes === null || trim($lifts) === '')) {
            throw new \InvalidArgumentException("standing grant «{$id}» says «lifts» only together with «supersedes»: it names the suspension of the grant it replaces, by its digest");
        }
        if ($lifts !== null && !Canonical::oneLine($lifts)) {
            throw new \InvalidArgumentException(Canonical::notOneLine("standing grant «{$id}» gives «lifts»", $lifts));
        }

        $seats = [];
        foreach ($to as $seat) {
            if (\is_string($seat) && !Canonical::oneLine($seat)) {
                throw new \InvalidArgumentException(Canonical::notOneLine("standing grant «{$id}» gives a seat in «to»", $seat));
            }
            if (!\is_string($seat) || trim($seat) === '' || preg_match('/[\s*]/', $seat) === 1) {
                throw new \InvalidArgumentException("standing grant «{$id}» lends to the seats it names, each by its key — never to «anyone», and never to a pattern");
            }
            $seats[$seat] = true;
        }
        if ($seats === []) {
            throw new \InvalidArgumentException("standing grant «{$id}» lends to nobody: name at least one seat in «to»");
        }
        $seats = array_map(strval(...), array_keys($seats));
        sort($seats, \SORT_STRING);

        if ($covers === []) {
            throw new \InvalidArgumentException("standing grant «{$id}» covers nothing: name at least one operation in «covers»");
        }
        foreach ($covers as $act) {
            if (!$act instanceof NamedCall) {
                throw new \InvalidArgumentException("standing grant «{$id}» covers named calls, one by one");
            }
            if ($act->free !== []) {
                $named = Canonical::short($act->operation->canonical);
                throw new \InvalidArgumentException("standing grant «{$id}» gives «free» for «{$named}» in «covers», where nothing reads it: a covered act already leaves free every argument it does not fix");
            }
            if ($act->operation->canonical === 'standing' || str_starts_with($act->operation->canonical, 'standing.')) {
                throw new \InvalidArgumentException(\sprintf(
                    'standing grant «%s» cannot cover «%s»: nothing a grant lends may give, restore, withdraw or stop a grant. Those stay acts of a person, or of anyone who only narrows.',
                    $id,
                    Canonical::short($act->operation->canonical),
                ));
            }
        }

        if (!$ceiling->isFullyClassified()) {
            throw new \InvalidArgumentException("standing grant «{$id}» leaves an axis of its ceiling unclassified, and an unclassified ceiling admits everything: classify mutation, externality, reversibility, authority and subject");
        }
        // The inverse a ceiling names is the name of ONE operation, as it reads — under any ceiling, not only one
        // that says «guaranteed»: a sentence there is a line nothing can run, check or put through a gate.
        if ($ceiling->rollbackContract !== null && NamedCall::named($ceiling->rollbackContract) === null) {
            throw new \InvalidArgumentException("standing grant «{$id}» gives the inverse its ceiling names («rollback_contract») as the name of one operation, as it reads — or does not give it; got " . Canonical::shown($ceiling->rollbackContract));
        }
        if ($ceiling->reversibility->weight() > self::CEILING_REVERSIBILITY->weight()) {
            throw new \InvalidArgumentException(\sprintf(
                'standing grant «%s» sets a ceiling of reversibility «%s». A standing grant never lends what cannot at least be compensated: the highest it carries is «%s». An act above it stays a person\'s, call by call.',
                $id,
                $ceiling->reversibility->value,
                self::CEILING_REVERSIBILITY->value,
            ));
        }

        if ($ceiling->authority->weight() > self::CEILING_AUTHORITY->weight()) {
            throw new \InvalidArgumentException(\sprintf(
                'standing grant «%s» sets a ceiling of authority «%s». An act above «%s» itself gives authority — it decides what the house believes or does — and no grant lends one. It stays a person\'s act, signed each time.',
                $id,
                $ceiling->authority->value,
                self::CEILING_AUTHORITY->value,
            ));
        }

        if ($conditions === []) {
            throw new \InvalidArgumentException("standing grant «{$id}» asks nothing of what an act touches: without a condition it is the scope handed over whole. Name at least one, with the operation that measures it");
        }
        $names = [];
        foreach ($conditions as $condition) {
            if (!$condition instanceof GrantCondition || isset($names[$condition->name])) {
                throw new \InvalidArgumentException("standing grant «{$id}» lists its conditions one by one, each under its own name");
            }
            $names[$condition->name] = true;
        }

        if (trim($undo) === '') {
            throw new \InvalidArgumentException("standing grant «{$id}» needs «undo»: how an act absorbed under it is taken back, in words");
        }
        if (!Canonical::oneLine($undo)) {
            throw new \InvalidArgumentException(Canonical::notOneLine("standing grant «{$id}» gives «undo»", $undo));
        }
        // A grant built by hand ends at an instant its own spelling says: to the second, in a year of four digits.
        // Built with half a second more, it lent for half a second after the document it writes said it had ended.
        if ($expiresAt !== null && ($expiresAt->format('u') !== '000000' || self::instant($expiresAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z')) === null)) {
            throw new \InvalidArgumentException("standing grant «{$id}» expires at an instant its own spelling can say: to the whole second, in a year of four digits");
        }

        $this->to = $seats;
        $this->covers = array_values($covers);
        $this->conditions = array_values($conditions);
    }

    /** Whether this grant lends to that seat, by the key exactly as the grant spells it. */
    public function lendsTo(string $seat): bool
    {
        return \in_array($seat, $this->to, true);
    }

    /** Whether this grant names that operation at all, whatever arguments it fixes for it. */
    public function namesOperation(OperationId|string $operation): bool
    {
        foreach ($this->covers as $act) {
            if ($act->operation->is($operation)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The named call that covers this one, or `null` when none does.
     *
     * @param array<string, mixed> $arguments
     */
    public function covering(OperationId|string $operation, array $arguments): ?NamedCall
    {
        foreach ($this->covers as $act) {
            if ($act->matches($operation, $arguments)) {
                return $act;
            }
        }

        return null;
    }

    /**
     * The grant as data — the document a person reads and signs.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'supersedes' => $this->supersedes,
            'lifts' => $this->lifts,
            'to' => $this->to,
            'covers' => array_map(static fn (NamedCall $act): array => $act->toArray(), $this->covers),
            'ceiling' => [
                'mutation' => $this->ceiling->mutation->value,
                'externality' => $this->ceiling->externality->value,
                'reversibility' => $this->ceiling->reversibility->value,
                'authority' => $this->ceiling->authority->value,
                'subject' => $this->ceiling->subject->value,
            ] + ($this->ceiling->rollbackContract === null ? [] : ['rollback_contract' => $this->ceiling->rollbackContract]),
            'conditions' => array_map(static fn (GrantCondition $condition): array => $condition->toArray(), $this->conditions),
            'undo' => $this->undo,
            'falsifier' => $this->falsifier->toArray(),
            'expires_at' => $this->expiresAt?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * The instant a text names when it is a date and time with its offset, in ISO 8601 — the date, a `T`, the time
     * to the second, then `Z` or the offset as hours and minutes with its sign — and `null` for anything else.
     *
     * ONE FORM, AND NOTHING THE CLOCK OR THE MACHINE DECIDES (greenhouse decisions/0615, «Lo que la segunda revisión
     * encontró», 6). A reader that takes whatever reads as a date takes «+1 hour»: a grant whose expiry is another
     * instant at every reading, and so another grant than the one a person signed. It takes a day that does not exist
     * and reads the days after it; a date with no offset and reads it in the zone of whichever process asks. A person
     * who wrote any of those did not say when the grant ends — so it is refused, and they are told the form.
     */
    private static function instant(string $text): ?\DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/D', $text) !== 1) {
            return null;
        }
        $instant = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sP', $text);

        // A day, an hour or a second that does not exist is read by rolling over to one that does: what it reads
        // back as is then not what was written.
        if ($instant === false || $instant->format('Y-m-d\TH:i:s') !== substr($text, 0, 19)) {
            return null;
        }

        // AND IT IS AN INSTANT THIS READER TAKES IN ITS OWN SPELLING. A grant spells its expiry in UTC, and the last
        // second of year 9999 written west of UTC is year 10000 there: a text this very reader refuses. A grant that
        // could be read and whose own spelling could not would be a document its house cannot read back.
        return preg_match('/^\d{4}$/D', $instant->setTimezone(new \DateTimeZone('UTC'))->format('Y')) === 1 ? $instant : null;
    }

    /**
     * Refuse an id that is not one: a short name in plain lowercase, and nothing after it.
     *
     * @throws \InvalidArgumentException
     */
    private static function known(string $id): void
    {
        if (preg_match(self::ID, $id) !== 1) {
            throw new \InvalidArgumentException('a standing grant is known by a short id — lowercase letters, digits, dot, dash or underscore, e.g. «m-01»; got ' . Canonical::shown($id));
        }
    }

    /** The exact bytes that identify this grant — one spelling, whatever order its author wrote it in. */
    public function canonical(): string
    {
        return Canonical::json($this->toArray());
    }

    /** `sha256:` of {@see self::canonical()}: what a record of this grant is checked against. */
    public function digest(): string
    {
        return 'sha256:' . hash('sha256', $this->canonical());
    }

    /**
     * A grant from the document a person wrote, already read into an array. Every refusal names what is missing or
     * what is not read.
     *
     * WHAT AN ARRAY NO LONGER SAYS. A text that says a key twice, or writes a list as an object, is refused by
     * {@see self::fromJson()}, which reads the text: this cannot see a key said twice, because the array holds the
     * last one only. Whoever holds the text a person signed reads it with `fromJson()`.
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        Canonical::only($data, self::KEYS, 'a standing grant');
        $id = Canonical::text($data, 'id', 'a standing grant');
        // The id is looked at before a sentence is built around it: every refusal below names the grant by it.
        self::known($id);
        $where = "standing grant «{$id}»";

        $ceiling = $data['ceiling'] ?? null;
        if (!\is_array($ceiling)) {
            throw new \InvalidArgumentException("{$where} needs «ceiling»: the widest effect a covered call may have, on all five axes");
        }
        Canonical::only($ceiling, ['mutation', 'externality', 'reversibility', 'authority', 'subject', 'rollback_contract'], "the ceiling of {$where}");
        $read = self::ceiling($ceiling, $where);

        $expires = $data['expires_at'] ?? null;
        $expiresAt = \is_string($expires) ? self::instant($expires) : null;
        if ($expires !== null && $expiresAt === null) {
            throw new \InvalidArgumentException("{$where} gives «expires_at» as a date and time with its offset, in ISO 8601");
        }

        $supersedes = $data['supersedes'] ?? null;
        if ($supersedes !== null && !\is_string($supersedes)) {
            throw new \InvalidArgumentException("{$where} supersedes another grant by its id");
        }
        $lifts = $data['lifts'] ?? null;
        if ($lifts !== null && !\is_string($lifts)) {
            throw new \InvalidArgumentException("{$where} says what it «lifts» by the digest of that suspension");
        }

        return new self(
            id: $id,
            to: self::listOf($data, 'to', $where),
            covers: array_map(static fn (mixed $act): NamedCall => NamedCall::fromArray($act, "{$where}, in «covers»,"), self::listOf($data, 'covers', $where)),
            ceiling: $read,
            conditions: array_map(GrantCondition::fromArray(...), self::listOf($data, 'conditions', $where)),
            undo: Canonical::text($data, 'undo', $where),
            falsifier: Falsifier::fromArray($data['falsifier'] ?? null),
            supersedes: $supersedes,
            expiresAt: $expiresAt,
            lifts: $lifts,
        );
    }

    /**
     * A grant from its JSON text — the text a person signed.
     *
     * ONE READER, FOR A NEW DOCUMENT AND FOR ONE ALREADY SIGNED. What this refuses, it refuses the same way whether
     * the text was never a grant or was one under an earlier version of this contract: a host that keeps signed
     * documents and reads them again with a later contract cannot tell the two apart here. So such a host treats a
     * document it kept and can no longer read as a FAULT of its record — never as a grant that is not there, which
     * would bring back whatever that grant had replaced or stopped.
     *
     * A text longer than {@see self::LONGEST} bytes is refused before it is read.
     */
    public static function fromJson(string $json): self
    {
        if (\strlen($json) > self::LONGEST) {
            throw new \InvalidArgumentException(\sprintf('a text of %d bytes is longer than a standing grant is: at most %d are read — a grant is a page a person reads before signing it', \strlen($json), self::LONGEST));
        }
        $data = json_decode($json, true);
        if (!\is_array($data) || array_is_list($data)) {
            throw new \InvalidArgumentException('a standing grant is one JSON object: {"id", "to", "covers", "ceiling", "conditions", "undo", "falsifier"}');
        }
        // One reading of the text says both what an array no longer can: a key said twice, and a list written as an
        // object — read into an array, `{"0": …, "1": …}` is one.
        Canonical::saidOnce($json, 'a standing grant');

        return self::fromArray($data);
    }

    /**
     * The ceiling a document writes — read as it is written, or refused.
     *
     * A VALUE THE READER DOES NOT KEEP IS A LINE NOBODY KEEPS (greenhouse decisions/0615, «Lo que la cuarta revisión
     * encontró», L2). The reader of a stored profile is lenient on purpose: it reads history, and it normalises what
     * an older rule wrote. A grant is not history — a person is signing it now — so whatever that reader would drop
     * or change is refused here instead: an inverse that is not the name of one operation in words, and an axis
     * that is read as another than the one written. Otherwise two documents a person reads differently would be
     * one grant, with one digest, and the record could not say which was signed.
     *
     * @param array<array-key, mixed> $written
     */
    private static function ceiling(array $written, string $where): EffectProfile
    {
        $axes = ['mutation' => Mutation::class, 'externality' => Externality::class, 'reversibility' => Reversibility::class, 'authority' => Authority::class, 'subject' => Subject::class];
        foreach ($axes as $axis => $enum) {
            $value = $written[$axis] ?? null;
            if (!\is_string($value) || $enum::tryFrom($value) === null) {
                throw new \InvalidArgumentException(\sprintf(
                    '%s gives «%s» of its ceiling as one of: %s; got %s',
                    $where,
                    $axis,
                    implode(', ', array_map(static fn (\BackedEnum $case): string => (string) $case->value, $enum::cases())),
                    Canonical::shown($value),
                ));
            }
        }
        $inverse = $written['rollback_contract'] ?? null;
        if ($inverse !== null && !\is_string($inverse)) {
            throw new \InvalidArgumentException("{$where} gives «rollback_contract» of its ceiling as the name of the one operation that undoes, in words — or does not give it; got " . Canonical::shown($inverse));
        }
        if ($inverse !== null && !Canonical::oneLine($inverse)) {
            throw new \InvalidArgumentException(Canonical::notOneLine("{$where} gives «rollback_contract» of its ceiling", $inverse));
        }
        $read = EffectProfile::fromArray($written);
        if ($read->rollbackContract !== $inverse) {
            throw new \InvalidArgumentException("{$where} gives «rollback_contract» of its ceiling, and nothing of it is kept: a line the judge ignores is a limit nobody keeps — remove it");
        }
        foreach (['mutation' => $read->mutation, 'externality' => $read->externality, 'reversibility' => $read->reversibility, 'authority' => $read->authority, 'subject' => $read->subject] as $axis => $kept) {
            if ($kept->value !== $written[$axis]) {
                throw new \InvalidArgumentException(\sprintf('%s writes «%s» of its ceiling as «%s», and it is read as «%s»: a grant says what it is read as — write that', $where, $axis, (string) $written[$axis], $kept->value));
            }
        }

        return $read;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<mixed>
     */
    private static function listOf(array $data, string $key, string $where): array
    {
        $value = $data[$key] ?? null;
        if (!\is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException("{$where} gives «{$key}» as a list");
        }

        return $value;
    }
}
