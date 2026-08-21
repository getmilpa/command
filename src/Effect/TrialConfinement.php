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

namespace Milpa\Command\Effect;

/**
 * The fact that a call is CONFINED to a disposable trial workspace — the producer of one axis.
 *
 * A trial workspace is a copy of the app, outside the app, run as a separate process under a bound
 * the runner IMPOSES (the host read-only, no network, no shared pids). Inside it an operation's writes
 * land in a tree whose destruction is part of the contract: what it writes dies with the workspace.
 * That is exactly {@see Mutation::Ephemeral}, so composition may lower `mutation` to Ephemeral for a
 * confined call — and NOTHING else. A copy isolates files, not the world: what crosses the network,
 * what spends authority, what a service remembers, none of it is undone by discarding a folder, so
 * externality, authority, reversibility and subject stay exactly as the operation declared them
 * (greenhouse decisions/0068: zero descent on third_party).
 *
 * It is a LIVE producer, like the authority policy, not a signed artefact: the lab cannot pre-sign
 * every run, and a signing key the app could read is a key a forgery could use. Its trust is the
 * bound actually imposed, recorded here as provenance so an auditor can check the claim after the
 * fact — and so a runner that could not impose the bound produces no confinement at all.
 */
final readonly class TrialConfinement
{
    /**
     * @param string                $workspaceId     the disposable workspace the call ran in
     * @param string                $argumentsDigest the exact call, by canonical digest
     * @param array<string, string> $bounds          what the runner IMPOSED, e.g. fs/net/pid
     * @param string                $because         why this call was routed to a trial
     */
    public function __construct(
        public string $workspaceId,
        public string $argumentsDigest,
        public array $bounds,
        public string $because,
    ) {
        if (trim($workspaceId) === '' || trim($argumentsDigest) === '' || trim($because) === '') {
            throw new \InvalidArgumentException('a trial confinement names its workspace, the exact call and why — a confinement without them is a claim nobody can check');
        }
    }

    /** The provenance string composition records on the reduction: workspace, call, bounds. */
    public function provenance(): string
    {
        $bounds = [];
        foreach ($this->bounds as $k => $v) {
            $bounds[] = $k . ':' . $v;
        }

        return sprintf('trial:%s args:%s bounds:{%s}', $this->workspaceId, $this->argumentsDigest, implode(',', $bounds));
    }
}
