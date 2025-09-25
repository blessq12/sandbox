<?php

namespace Andrewmaster\Sandbox\Infrastructure\Scenario;

use Andrewmaster\Sandbox\Domain\Project;
use Andrewmaster\Sandbox\Domain\Scenario\Scenario;
use Symfony\Component\Yaml\Yaml;

class ScenarioLoader
{
    /**
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
}
