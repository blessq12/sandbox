<?php

namespace Andrewmaster\Sandbox\Domain\Scenario;

/**
 * Leaf — конечный элемент дерева (отдельный сценарий)
 */
class ScenarioLeaf implements ScenarioComponent
{
    public function __construct(
        private readonly Scenario $scenario,
        private readonly string $group = ''
    ) {}

    public function getName(): string
    {
        return $this->scenario->name;
    }

    public function getPath(): string
    {
        return $this->group
            ? $this->group . '/' . $this->scenario->name
            : $this->scenario->name;
    }

    public function getScenarios(): array
    {
        return [$this->scenario];
    }

    public function count(): int
    {
        return 1;
    }

    public function isGroup(): bool
    {
        return false;
    }

    public function getScenario(): Scenario
    {
        return $this->scenario;
    }

    public function getGroup(): string
    {
        return $this->group;
    }
}
