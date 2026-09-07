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

namespace Milpa\Command;

use Milpa\Command\Consent\OperationId;
use Milpa\Command\Effect\EffectProfile;
use Milpa\Command\Effect\Reversibility;

/**
 * Whether the inverses this app PROMISES are operations this app OFFERS.
 *
 * {@see \Milpa\Command\Effect\EffectProfile} refuses prose in a rollback contract, and that is as far as
 * one profile can see: `nothing-to-roll-back` is a well-formed name — one segment with hyphens, exactly
 * like `plugins.disable-unsafe` has to be — so nothing about the STRING says it names no operation. That
 * question is about the TABLE, and it is answered here, where the table exists.
 *
 * It is a question of GRADUATION, not of boot. An app whose catalogue is missing an inverse is not
 * broken — it is a house that promised something it cannot do, and the answer is a finding a person or
 * an agent reads (`coa doctor`), not a runtime that refuses to start. Greenhouse
 * `.milpa/promises/reversal-contract.yaml`: «an operation that promises reversibility and does not carry
 * it does not GRADUATE».
 *
 * Identity, never spelling: the promise may be written `plugins:disable` and the operation registered as
 * `plugins.disable`, and they are the same act ({@see OperationId}).
 */
final readonly class RollbackContracts
{
    /**
     * The promises this table cannot keep: operation name → the inverse it named and nobody offers.
     *
     * Private on purpose. It was public and nothing outside its own tests called it — a caller wants the
     * FINDINGS, which is what a report prints and what an agent can act on, and a second public shape for
     * the same answer is a second thing to keep true (greenhouse decisions/0213, caught by its gate).
     *
     * Empty is the answer a house wants. Order follows the table, so a report reads in the order the
     * operations were declared.
     *
     * @param iterable<Operation> $operations every operation this app offers
     *
     * @return array<string, string> the promising operation's name → the canonical id it named
     */
    private static function unresolved(iterable $operations): array
    {
        $offered = [];
        $promises = [];

        foreach ($operations as $operation) {
            $offered[(new OperationId($operation->name))->canonical] = true;

            $profile = $operation->effects;
            if (!$profile instanceof EffectProfile || $profile->reversibility !== Reversibility::Guaranteed) {
                continue;
            }

            $inverse = $profile->rollbackOperation();
            if ($inverse !== null) {
                $promises[$operation->name] = $inverse->canonical;
            }
        }

        // Resolved against the WHOLE table, not as it is walked: an operation may name an inverse that
        // a later provider contributes, and a house that reported that as broken would be reporting the
        // order of its own iteration.
        //
        // AN OPERATION IS NOT ITS OWN INVERSE, and it was answering as one.
        //
        // `$offered` is filled in the same walk that collects the promises, so an operation naming ITSELF
        // resolved against itself and could never produce a finding — in any host, wired or not. Measured
        // on `screen:set-state`, whose rollback contract is `screen:set-state`: the one shape this check
        // was blind to was the one shape that needs it most.
        //
        // Calling the same operation again with an earlier value MAY undo it, and that is a claim about
        // arguments — which this list cannot see and therefore cannot verify. A promise nothing can check
        // is the thing this class exists to refuse, so self-naming is reported like any inverse the table
        // does not answer for, with its own sentence saying why.
        return array_filter(
            $promises,
            static fn (string $inverse, string $operation): bool => $inverse === (new OperationId($operation))->canonical
                || !isset($offered[$inverse]),
            \ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * The same answer as a sentence per broken promise — what a report prints.
     *
     * @param iterable<Operation> $operations
     *
     * @return list<string>
     */
    public static function findings(iterable $operations): array
    {
        $lines = [];
        foreach (self::unresolved($operations) as $operation => $inverse) {
            $lines[] = $inverse === (new OperationId($operation))->canonical
                ? \sprintf(
                    '%s promises «guaranteed» and names ITSELF as its inverse: calling it again with an '
                    . 'earlier value may undo it, but that is a claim about ARGUMENTS, which nothing here '
                    . 'can check. Name the operation that undoes this, or declare what this one really is.',
                    $operation,
                )
                : \sprintf(
                    '%s promises «guaranteed» and names «%s» as its inverse, which this app does not offer: '
                    . 'a promise nobody can run is not a guarantee. Declare the inverse, or declare what this '
                    . 'operation really is.',
                    $operation,
                    $inverse,
                );
        }

        return $lines;
    }
}
