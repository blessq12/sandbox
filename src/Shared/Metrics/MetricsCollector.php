<?php

namespace Andrewmaster\Sandbox\Shared\Metrics;

class MetricsCollector
{
    /** @var array<string, float|int> */
    private array $counters = [];

    public function incr(string $name, int $value = 1): void
    {
        $this->counters[$name] = ($this->counters[$name] ?? 0) + $value;
    }

    /**
     * @return array<string, float|int>
     */
    public function all(): array
    {
        return $this->counters;
    }
}
