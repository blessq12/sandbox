<?php

namespace Andrewmaster\Sandbox\Infrastructure\Scenario;

use Andrewmaster\Sandbox\Domain\Project;
use Andrewmaster\Sandbox\Domain\Scenario\Scenario;
use Andrewmaster\Sandbox\Domain\Scenario\ScenarioGroup;
use Andrewmaster\Sandbox\Domain\Scenario\ScenarioLeaf;
use Symfony\Component\Yaml\Yaml;

class ScenarioLoader
{
    /**
     * Загрузить сценарии (legacy метод для обратной совместимости)
     * @return array<string, Scenario>
     */
    public function loadFromProject(Project $project): array
    {
        $dir = $project->testsScenariosDir
            ? getcwd() . DIRECTORY_SEPARATOR . ltrim($project->testsScenariosDir, DIRECTORY_SEPARATOR)
            : rtrim($project->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'scenarios';
        $scenarios = [];
        if (!is_dir($dir)) {
            return $scenarios;
        }

        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.yaml') as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $data = (array) Yaml::parseFile($file);
            $steps = (array) ($data['steps'] ?? []);
            $scenarios[$name] = new Scenario($name, $steps);
        }
        return $scenarios;
    }

    /**
     * Загрузить сценарии с иерархической структурой групп (Composite Pattern)
     */
    public function loadGroupedScenarios(Project $project): ScenarioGroup
    {
        $dir = $project->testsScenariosDir
            ? getcwd() . DIRECTORY_SEPARATOR . ltrim($project->testsScenariosDir, DIRECTORY_SEPARATOR)
            : rtrim($project->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'scenarios';

        $root = new ScenarioGroup($project->name, '');

        if (!is_dir($dir)) {
            return $root;
        }

        $this->loadDirectory($dir, $root, '');
        return $root;
    }

    /**
     * Рекурсивная загрузка директории со сценариями
     */
    private function loadDirectory(string $dir, ScenarioGroup $parentGroup, string $groupPath): void
    {
        // Загружаем YAML файлы из текущей директории
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.yaml') as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $data = (array) Yaml::parseFile($file);
            $steps = (array) ($data['steps'] ?? []);

            $scenario = new Scenario($name, $steps);
            $leaf = new ScenarioLeaf($scenario, $groupPath);
            $parentGroup->add($leaf);
        }

        // Рекурсивно обходим поддиректории
        $subdirs = glob($dir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        if ($subdirs === false) {
            return;
        }

        foreach ($subdirs as $subdir) {
            $groupName = basename($subdir);
            $newGroupPath = $groupPath ? $groupPath . '/' . $groupName : $groupName;

            $subGroup = new ScenarioGroup($groupName, $newGroupPath);
            $this->loadDirectory($subdir, $subGroup, $newGroupPath);

            // Добавляем группу только если в ней есть сценарии
            if ($subGroup->count() > 0) {
                $parentGroup->add($subGroup);
            }
        }
    }
}
