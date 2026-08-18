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
 * The evidence that lets a descent lower a ceiling, and the proof of where that evidence came from.
 *
 * greenhouse decisions/0045 decided a descent is earned by being CERTIFIED, never declared;
 * decisions/0050 gave the certificate a shape a runtime can check on its own; decisions/0051 added
 * the only thing those two could not buy — provenance.
 *
 * WHAT EACH PART ANSWERS, and nobody has to be trusted for any of it:
 *
 *   · `operation` + `handlerSha256`  is this about the very call being made, and the very code about
 *                                    to run? Either alone lets a certificate slide.
 *   · `predicate`                    was it earned by THESE arguments? `dry=true` and `force=true`
 *                                    on one handler are two different descents.
 *   · `to`                           did the evidence justify THIS destination?
 *   · `covers`                       which axes did a control actually demonstrate? Never «certified»
 *                                    flat: `evidence/0245` showed an honest disk certificate being
 *                                    presentable as proof about the network.
 *   · `signature`                    did this exact payload come from the certifier, or from a text
 *                                    editor? `evidence/0249` deleted the artifact, rewrote it by hand
 *                                    with a digest computed by `sed | sha256sum`, and the ceiling
 *                                    came down.
 *
 * WHAT THE SIGNATURE DOES NOT DO. It adds no criterion. A signed certificate covering only
 * `mutation` still cannot lower `authority`, because the criterion lives in the verifier and stays
 * there. And it proves PROVENANCE, not INDEPENDENCE: whoever controls the repository and the
 * pipeline is still one actor. decisions/0051 declares that residue rather than hiding it.
 *
 * THE PUBLIC KEY MUST NOT COME FROM THE ARTIFACT. It belongs where the operation is declared —
 * reviewed code — because a key read from the same file as the signature lets a forger swap both and
 * sign their own lie, which is the exact attack this class exists to stop.
 */
final readonly class DescentCertificate
{
    /**
     * @param string               $verifier          who produced it, with its version
     * @param string               $operation         the operation this evidence is about
     * @param array<string, mixed> $predicate         the exact arguments exercised, as `[argument => value]`
     * @param list<string>         $covers            the EffectProfile axes a control demonstrated
     * @param EffectProfile        $to                the reduced ceiling this evidence justified
     * @param string|null          $handlerSha256     the digest of the handler body that was watched
     * @param array<string, mixed> $envelope          what the instruments saw and where they are blind
     * @param string|null          $verdict           what the verifier concluded, in its own words
     * @param string|null          $signature         base64 detached signature over {@see canonicalPayload()}
     * @param string|null          $verifierPublicKey base64 ed25519 key of the recognised certifier
     */
    public function __construct(
        public string $verifier,
        public string $operation,
        public array $predicate,
        public array $covers,
        public EffectProfile $to,
        public ?string $handlerSha256 = null,
        public array $envelope = [],
        public ?string $verdict = null,
        public ?string $signature = null,
        public ?string $verifierPublicKey = null,
    ) {
    }

    /**
     * The exact bytes that are signed and verified — never «an equivalent JSON».
     *
     * Two serialisations of one payload would be two signatures, and the ambiguity is the bug: the
     * certifier and the runtime must agree byte for byte. So the order is fixed here, in one place,
     * and every value is rendered explicitly rather than left to whatever the encoder felt like.
     */
    public function canonicalPayload(): string
    {
        $perfil = static fn (EffectProfile $p): array => [
            'authority' => $p->authority->value,
            'externality' => $p->externality->value,
            'mutation' => $p->mutation->value,
            'reversibility' => $p->reversibility->value,
            'subject' => $p->subject->value,
        ];

        $predicado = $this->predicate;
        ksort($predicado);
        $sobre = $this->envelope;
        $this->ordenaProfundo($sobre);
        $cubre = $this->covers;
        sort($cubre);

        return (string) json_encode([
            'certificate_subject' => [
                'handler_digest' => $this->handlerSha256,
                'operation' => $this->operation,
                'predicate' => $predicado,
                'to' => $perfil($this->to),
            ],
            'covers' => $cubre,
            'observation_envelope' => $sobre,
            'verdict' => $this->verdict,
            'verifier_identity' => $this->verifier,
        ], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }

    /** The same certificate, carrying a detached signature over its canonical bytes. */
    public function signedWith(string $secretKey): self
    {
        return new self(
            verifier: $this->verifier,
            operation: $this->operation,
            predicate: $this->predicate,
            covers: $this->covers,
            to: $this->to,
            handlerSha256: $this->handlerSha256,
            envelope: $this->envelope,
            verdict: $this->verdict,
            signature: base64_encode(sodium_crypto_sign_detached($this->canonicalPayload(), $secretKey)),
            verifierPublicKey: $this->verifierPublicKey,
        );
    }

    /**
     * Did THIS payload come from the certifier this deployment recognises?
     *
     * Everything missing or malformed answers no. An unsigned certificate is not a certificate with
     * an open question — it is a file someone wrote.
     */
    public function signedByItsVerifier(): bool
    {
        if ($this->signature === null || $this->verifierPublicKey === null) {
            return false;
        }

        $firma = base64_decode($this->signature, true);
        $llave = base64_decode($this->verifierPublicKey, true);
        if ($firma === false || $llave === false || \strlen($llave) !== \SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || \strlen($firma) !== \SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($firma, $this->canonicalPayload(), $llave);
    }

    /** Does this certificate speak about the very call being made — the operation AND its arguments? */
    public function speaksAbout(Descent $descent, ?CallSubject $subject): bool
    {
        return $subject !== null
            && $this->operation === $subject->operation
            && $this->predicate === [$descent->argument => $descent->whenValue];
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

    /** @param array<string, mixed> $bag */
    private function ordenaProfundo(array &$bag): void
    {
        ksort($bag);
        foreach ($bag as &$v) {
            if (\is_array($v) && ! array_is_list($v)) {
                /** @var array<string, mixed> $v */
                $this->ordenaProfundo($v);
            }
        }
    }
}
