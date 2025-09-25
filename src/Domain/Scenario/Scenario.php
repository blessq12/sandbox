<?php

namespace Andrewmaster\Sandbox\Domain\Scenario;

class Scenario
{
    public function __construct(
        public string $name,
        /** @var array<int, array<string, mixed>> */
        public array $steps
    ) {}
}
