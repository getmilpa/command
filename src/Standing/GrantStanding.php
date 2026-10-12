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
 * What a host knows about one standing grant at the moment of a call (greenhouse decisions/0615).
 *
 * FACTS, gathered by the host from its own ledger and its own catalogue, and handed to
 * {@see StandingJudge} — which decides nothing about how they were obtained. Read at the moment of
 * the act: a state remembered from the start of a session is the defect this exists to close, and a
 * stored «valid» would be a grade that survives its evidence (greenhouse evidence/0254).
 */
final readonly class GrantStanding
{
    /** What a standing says of the signature when the host that built it said nothing: it fails closed. */
    public const NOT_VERIFIED = 'the host that asked did not say it verified the signature';

    /** What a standing says of the host's record when the host said nothing of it: it fails closed too. */
    public const NOT_READ = 'the host that asked did not say its record of this grant reads whole';

    /**
     * NOTHING HERE SAYS «YES» BY DEFAULT. Where the grant stands has to be said; the signature and the record are
     * faulted until the host says they hold. A host that forgets one of the three lends nothing.
     *
     * @param GrantState              $state           what the ledger derives for the grant now — said by the host, always
     * @param string|null             $because         for a state other than in force: what put it there and who may lift it, in words
     * @param string|null             $signatureFault  why the grant's signature does not hold NOW — or `null`: it was re-verified and it holds.
     *                                                 A host says which: the default is that nobody verified it,
     *                                                 and a grant nobody verified is not honoured
     * @param \DateTimeImmutable|null $watcherSignalAt the watcher's last sign of life since the grant was given or last reinstated, or `null`: none
     * @param list<ConditionReceipt>  $receipts        every receipt the host holds, in ledger order — the last one about a content says what is known of it now
     * @param array<string, string>   $instruments     operation (canonical id) => its instrument digest today
     * @param string|null             $recordFault     why what the host keeps of this grant cannot be trusted whole — a line it
     *                                                 wrote as a receipt, a sign of the watcher or a stop, and can no longer
     *                                                 read as one ({@see ConditionReceipt::fromArray()}) — or `null`: every
     *                                                 line read. A line that is skipped is the easy thing to do, and a red
     *                                                 receipt skipped leaves the green before it speaking; so the default is
     *                                                 that nobody checked
     */
    public function __construct(
        public GrantState $state,
        public ?string $because = null,
        public ?string $signatureFault = self::NOT_VERIFIED,
        public ?\DateTimeImmutable $watcherSignalAt = null,
        public array $receipts = [],
        public array $instruments = [],
        public ?string $recordFault = self::NOT_READ,
    ) {
    }
}
