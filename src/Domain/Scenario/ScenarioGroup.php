<?php

namespace Andrewmaster\Sandbox\Domain\Scenario;

/**
 * Composite — группа сценариев
 * Может содержать как отдельные сценарии, так и другие группы
 */
class ScenarioGroup implements ScenarioComponent
{
    /** @var ScenarioComponent[] */
    private array $children = [];

    public function __construct(
        private readonly string $name,
        private readonly string $path = ''
    ) {}

    /**
     * Добавить компонент в группу
     */
    public function add(ScenarioComponent $component): void
    {
        $this->children[] = $component;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return $this->path ?: $this->name;
    }

    /**
     * Получить все дочерние компоненты
     * @return ScenarioComponent[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Рекурсивно собрать все сценарии из группы и подгрупп
     * @return Scenario[]
     */
    public function getScenarios(): array
    {
        $scenarios = [];
        foreach ($this->children as $child) {
            $scenarios = array_merge($scenarios, $child->getScenarios());
        }
        return $scenarios;
    }

    public function count(): int
    {
        $count = 0;
        foreach ($this->children as $child) {
            $count += $child->count();
        }
        return $count;
    }

    public function isGroup(): bool
    {
        return true;
    }

    /**
     * Найти подгруппу по имени
     */
    public function findGroup(string $name): ?ScenarioGroup
    {
        foreach ($this->children as $child) {
            if ($child->isGroup() && $child->getName() === $name) {
                return $child instanceof ScenarioGroup ? $child : null;
            }
            if ($child instanceof ScenarioGroup) {
                $found = $child->findGroup($name);
                if ($found) {
                    return $found;
                }
            }
        }
        return null;
    }

    /**
     * Получить список всех групп (плоский список)
     * @return string[]
     */
    public function getAllGroupPaths(): array
    {
        $paths = [$this->getPath()];
        foreach ($this->children as $child) {
            if ($child instanceof self) {
                /** @var ScenarioGroup $child */
                $paths = array_merge($paths, $child->getAllGroupPaths());
            }
        }
        return $paths;
    }
}
