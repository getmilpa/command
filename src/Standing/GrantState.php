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
 * What a host's ledger says of a standing grant right now (greenhouse decisions/0615).
 *
 * Never a field of the grant: what a person signed is not edited. A host DERIVES this from what was
 * appended after the grant — and «expired» is not here because it needs no ledger: the grant carries
 * its own date and the judge reads the clock.
 */
enum GrantState: string
{
    /** Nothing after it took it back. */
    case InForce = 'in_force';

    /** Its falsifier fired, its subject moved under an act, or someone asked for it to stop. Only a person lifts it. */
    case Suspended = 'suspended';

    /** A later grant, signed by a person, replaced it. */
    case Superseded = 'superseded';

    /** A person withdrew it. */
    case Revoked = 'revoked';
}
