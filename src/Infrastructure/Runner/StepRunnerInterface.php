<?php

namespace Andrewmaster\Sandbox\Infrastructure\Runner;

interface StepRunnerInterface
{
    public function getType(): string;

    /**
     * @param array<string,mixed> $step
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function run(array $step, array $context = []): array;
}
