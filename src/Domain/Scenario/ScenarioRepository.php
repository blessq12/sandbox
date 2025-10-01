<?php

namespace Andrewmaster\Sandbox\Domain\Scenario;

use Andrewmaster\Sandbox\Domain\Project;
use Andrewmaster\Sandbox\Infrastructure\Scenario\ScenarioLoader;

/**
 * Repository Pattern — централизованный доступ к сценариям
 * Инкапсулирует логику поиска и фильтрации сценариев
 */
class ScenarioRepository
{
    public function __construct(
        private readonly ScenarioLoader $loader
    ) {}

    /**
     * Получить все сценарии проекта
     * @return Scenario[]
     */
    public function findAll(Project $project): array
    {
        $root = $this->loader->loadGroupedScenarios($project);
        return $root->getScenarios();
    }

    /**
     * Получить сценарии по группе
     * @return Scenario[]
     */
    public function findByGroup(Project $project, string $groupPath): array
    {
        $root = $this->loader->loadGroupedScenarios($project);

        // Если запросили корневую группу - возвращаем всё
        if ($groupPath === '' || $groupPath === '/') {
            return $root->getScenarios();
        }

        // Поиск группы по пути
        $parts = explode('/', trim($groupPath, '/'));
        $current = $root;

        foreach ($parts as $part) {
            $found = $current->findGroup($part);
            if (!$found) {
                return [];
            }
            $current = $found;
        }

        return $current->getScenarios();
    }

    /**
     * Получить корневую группу с иерархией
     */
    public function getGroupTree(Project $project): ScenarioGroup
    {
        return $this->loader->loadGroupedScenarios($project);
    }

    /**
     * Получить список всех групп проекта
     * @return string[]
     */
    public function getGroupPaths(Project $project): array
    {
        $root = $this->loader->loadGroupedScenarios($project);
        $paths = $root->getAllGroupPaths();

        // Убираем корневую группу если она называется как проект
        return array_filter($paths, fn($p) => $p !== $root->getName());
    }

    /**
     * Получить метаданные группы (количество сценариев, подгруппы)
     */
    public function getGroupMetadata(Project $project, string $groupPath): ?array
    {
        $root = $this->loader->loadGroupedScenarios($project);

        if ($groupPath === '' || $groupPath === '/') {
            $group = $root;
        } else {
            $parts = explode('/', trim($groupPath, '/'));
            $group = $root;

            foreach ($parts as $part) {
                $group = $group->findGroup($part);
                if (!$group) {
                    return null;
                }
            }
        }

        $subgroups = [];
        foreach ($group->getChildren() as $child) {
            if ($child->isGroup()) {
                $subgroups[] = $child->getName();
            }
        }

        return [
            'name' => $group->getName(),
            'path' => $group->getPath(),
            'scenarios_count' => $group->count(),
            'subgroups' => $subgroups,
        ];
    }
}
