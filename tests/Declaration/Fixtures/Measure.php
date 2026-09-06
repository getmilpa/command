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

namespace Milpa\Command\Tests\Declaration\Fixtures;

use Milpa\Command\Declaration\Because;
use Milpa\Command\Declaration\Operation;
use Milpa\Command\Declaration\Reads;

/** Every scalar shape a surface can coerce, plus a pure enum and a class-level purpose. */
#[Operation(name: 'lab:measure', description: 'Measure a thing.', version: '2', path: '/measure', surfaces: ['cli'])]
#[Because('so the catalogue explains WHY, not just what.')]
#[Reads]
final readonly class Measure
{
    /** @param list<string> $tags */
    public function __construct(
        public float $ratio,
        public bool $dry,
        public array $tags,
        public Priority $priority = Priority::Low,
    ) {
    }

    public function run(): Reading
    {
        return new Reading($this->ratio, $this->dry, $this->tags, $this->priority->name);
    }
}
