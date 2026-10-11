<?php

declare(strict_types=1);

namespace Milpa\Command\Tests\Standing;

use Milpa\Command\Consent\OperationId;
use Milpa\Command\Effect\Authority;
use Milpa\Command\Effect\EffectProfile;
use Milpa\Command\Effect\Externality;
use Milpa\Command\Effect\Mutation;
use Milpa\Command\Effect\Reversibility;
use Milpa\Command\Effect\Subject;
use Milpa\Command\Standing\ConditionReceipt;
use Milpa\Command\Standing\GrantStanding;
use Milpa\Command\Standing\StandingCall;
use Milpa\Command\Standing\StandingGrant;

/**
 * The one grant these tests reason about: a made-up release line with one condition, written the way a
 * person would write it. Every test starts from a call that passes and changes exactly one fact.
 */
final class Grants
{
    public const SEAT = 'SEAT0000000000000000000000000000000000AA';

    public const CANDIDATE = 'sha256:candidate-a';

    public const INSTRUMENT = 'sha256:the-scanner-as-it-is-today';

    /**
     * The document, as data. `$change` replaces top-level keys; a `null` value removes the key.
     *
     * @param array<string, mixed> $change
     *
     * @return array<string, mixed>
     */
    public static function document(array $change = []): array
    {
        $document = [
            'id' => 'release-line',
            'to' => [self::SEAT],
            'covers' => [['operation' => 'lab:publish', 'arguments' => ['channel' => 'stable']]],
            'ceiling' => [
                'mutation' => 'persistent',
                'externality' => 'public',
                'reversibility' => 'compensatable',
                'authority' => 'write_as_user',
                'subject' => 'executable',
            ],
            'conditions' => [['name' => 'no-host-names', 'statement' => 'what is published names no machine', 'by' => ['operation' => 'lab:scan']]],
            'undo' => 'withdraw the release and publish the one before it',
            'falsifier' => ['name' => 'a-published-name', 'statement' => 'a published release names a machine', 'watcher' => ['operation' => 'lab:watch'], 'within_seconds' => 3600],
        ];
        foreach ($change as $key => $value) {
            if ($value === null) {
                unset($document[$key]);
            } else {
                $document[$key] = $value;
            }
        }

        return $document;
    }

    /** @param array<string, mixed> $change */
    public static function grant(array $change = []): StandingGrant
    {
        return StandingGrant::fromArray(self::document($change));
    }

    public static function at(string $time = '2026-10-11T12:00:00Z'): \DateTimeImmutable
    {
        return new \DateTimeImmutable($time);
    }

    /** What the lab's publishing operation declares: exactly at the grant's ceiling. */
    public static function effect(Reversibility $reversibility = Reversibility::Compensatable, Externality $externality = Externality::Public): EffectProfile
    {
        return new EffectProfile(Mutation::Persistent, $externality, $reversibility, Authority::WriteAsUser, subject: Subject::Executable);
    }

    /** @param array<string, mixed> $arguments */
    public static function call(?string $subject = self::CANDIDATE, array $arguments = ['channel' => 'stable', 'path' => 'candidate.tar'], string $seat = self::SEAT, ?EffectProfile $effect = null, ?string $at = null): StandingCall
    {
        return new StandingCall('lab:publish', $arguments, $seat, $subject, $effect ?? self::effect(), self::at($at ?? '2026-10-11T12:00:00Z'));
    }

    /** @param array<string, mixed> $arguments */
    public static function receipt(string $subject = self::CANDIDATE, bool $ok = true, string $instrument = self::INSTRUMENT, string $by = 'lab:scan', array $arguments = ['path' => 'candidate.tar']): ConditionReceipt
    {
        return new ConditionReceipt(new OperationId($by), $arguments, $subject, $ok, $instrument, 'key:' . self::SEAT, self::at('2026-10-11T11:50:00Z'));
    }

    /**
     * The house as it stands when everything is in order: in force, watched ten minutes ago, the candidate
     * measured green by today's scanner.
     *
     * @param list<ConditionReceipt>|null $receipts
     */
    public static function standing(?array $receipts = null, ?string $watcherSignalAt = '2026-10-11T11:50:00Z'): GrantStanding
    {
        return new GrantStanding(
            watcherSignalAt: $watcherSignalAt === null ? null : self::at($watcherSignalAt),
            receipts: $receipts ?? [self::receipt()],
            instruments: ['lab.scan' => self::INSTRUMENT],
        );
    }
}
