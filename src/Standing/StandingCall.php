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
use Milpa\Command\Effect\EffectProfile;

/**
 * The call a standing grant is asked about, as facts (greenhouse decisions/0615).
 *
 * Each one is the host's reading, never the caller's word: `subject` is what
 * {@see \Milpa\Command\Operation::actsOnDigest()} computed from the bytes, and `effect` is what
 * {@see \Milpa\Command\Operation::ceilingForCall()} composed for these arguments.
 */
final readonly class StandingCall
{
    public OperationId $operation;

    /**
     * @param array<string, mixed> $arguments the arguments of the call, as the handler will receive them
     * @param string               $seat      who calls, by the key the grant would name it by
     * @param string|null          $subject   the digest of the content this call acts on, or `null` when the operation cannot say
     * @param EffectProfile        $effect    the ceiling THIS call carries
     * @param \DateTimeImmutable   $at        the moment of the act
     */
    public function __construct(
        OperationId|string $operation,
        public array $arguments,
        public string $seat,
        public ?string $subject,
        public EffectProfile $effect,
        public \DateTimeImmutable $at,
    ) {
        $this->operation = $operation instanceof OperationId ? $operation : new OperationId($operation);
    }
}
