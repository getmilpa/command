<?php

/**
 * This file is part of milpa/command — the atom: one declared Operation, projected by every surface.
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
 * The evidence that lets a descent lower a ceiling — greenhouse decisions/0050.
 *
 * `decisions/0045` decided a descent is not earned by being DECLARED but by being CERTIFIED, after
 * `evidence/0238` ran a handler that did exactly what its descent denied and got the lowered ceiling
 * plus the gate's silence. This is the artifact that replaces the belief.
 *
 * WHAT IT CARRIES IS ONLY WHAT A RUNTIME CAN CHECK ON ITS OWN, which is the whole design. Anything
 * else would be a second sentence to believe:
 *
 *   · `predicate`     the exact argument and value that were exercised — a certificate earned by
 *                     `--dry-run=true` says nothing about any other call;
 *   · `handlerSha256` the body that was watched, so a handler that moved leaves its certificate
 *                     stale instead of silently inheriting it;
 *   · `covers`        the AXES a control actually demonstrated, never «certified» flat. This is what
 *                     `evidence/0245` paid for: an honest certificate about the DISK was presentable
 *                     as if it proved absence of NETWORK, so `decisions/0046` made the envelope
 *                     travel with the claim;
 *   · `to`            the reduced profile that evidence bought, so nobody can point a certificate at
 *                     a lighter destination than the one it justified.
 *
 * WHAT IT DOES NOT DO, said here rather than discovered later: THIS ARTIFACT IS NOT SIGNED. Whoever
 * writes the handler can write a certificate with the right digest and buy the privilege. What ends
 * is the absent certificate, the stale one, the borrowed one and the one that claims wider than its
 * proof. Signing is the next slice, and it is what turns «bound to the code» into «unforgeable».
 */
final readonly class DescentCertificate
{
    /**
     * @param string               $verifier      who produced it, with its version — so a verifier that turned out to be blind can be traced to what it certified
     * @param array<string, mixed> $predicate     the exact arguments exercised, as `[argument => value]`
     * @param list<string>         $covers        the EffectProfile axes a control demonstrated: `mutation`, `externality`, `reversibility`, `authority`, `subject`
     * @param EffectProfile        $to            the reduced ceiling this evidence justified
     * @param string|null          $handlerSha256 the digest of the handler body that was watched
     */
    public function __construct(
        public string $verifier,
        public array $predicate,
        public array $covers,
        public EffectProfile $to,
        public ?string $handlerSha256 = null,
    ) {
    }

    /** Does this certificate speak about the very call being made? */
    public function speaksAbout(Descent $descent): bool
    {
        return $this->predicate === [$descent->argument => $descent->whenValue];
    }

    /**
     * Is this still the handler the verifier watched?
     *
     * A caller that cannot say which handler is about to run gets `false`, not the benefit of the
     * doubt: not being able to look is not the same as having looked and found nothing.
     */
    public function watched(?string $handlerDigest): bool
    {
        if ($this->handlerSha256 === null) {
            return false;
        }

        return $handlerDigest !== null && hash_equals($this->handlerSha256, $handlerDigest);
    }

    /**
     * Did a control demonstrate every axis this descent actually lowers?
     *
     * Axes that do not move need no evidence — the claim is only as wide as the reduction. An axis
     * that comes down without a control behind it is a claim wider than its proof, and it takes the
     * whole descent with it.
     *
     * @param list<string> $loweredAxes
     */
    public function coversAll(array $loweredAxes): bool
    {
        foreach ($loweredAxes as $eje) {
            if (! \in_array($eje, $this->covers, true)) {
                return false;
            }
        }

        return true;
    }
}
