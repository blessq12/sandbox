<?php

namespace Andrewmaster\Sandbox\Runner;

interface StepRunnerInterface
{
    /**
     * @param array<string, mixed> $step
     * @param array<string, mixed> $context общий контекст прогона (например baseUrl)
     * @return array<string, mixed> результат шага (время, статус, артефакты)
     */
    public function run(array $step, array $context = []): array;

    /** Возвращает поддерживаемый тип шага, например 'http', 'cli'. */
    public function getType(): string;
}
