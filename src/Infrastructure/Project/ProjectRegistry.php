<?php

namespace Andrewmaster\Sandbox\Infrastructure\Project;

use Andrewmaster\Sandbox\Domain\Project;
use Symfony\Component\Yaml\Yaml;

class ProjectRegistry
{
    /** @var array<string, Project> */
    private array $projects = [];

    public function __construct(private string $configDirectory)
    {
        $this->load();
    }

    private function load(): void
    {
        if (!is_dir($this->configDirectory)) {
            return;
        }

        foreach (glob($this->configDirectory . DIRECTORY_SEPARATOR . '*.yaml') as $file) {
            $data = (array) Yaml::parseFile($file);
            $name = (string) ($data['name'] ?? pathinfo($file, PATHINFO_FILENAME));
            $title = (string) ($data['title'] ?? $name);
            $path = (string) ($data['projectRoot'] ?? ($data['path'] ?? ''));
            if ($name !== '' && $path !== '') {
                $entry = isset($data['entryPoint']) ? (string) $data['entryPoint'] : (isset($data['entry']) ? (string) $data['entry'] : null);
                $tests = (array) ($data['tests'] ?? []);
                $testsScenarios = isset($tests['scenariosDir']) ? (string) $tests['scenariosDir'] : null;
                $testsRoutes = isset($tests['routesDir']) ? (string) $tests['routesDir'] : null;
                $env = (array) ($data['env'] ?? []);
                $this->projects[$name] = new Project($name, $title, $path, $entry, $testsScenarios, $testsRoutes, $env);
            }
        }
    }

    /** @return array<string, Project> */
    public function all(): array
    {
        return $this->projects;
    }

    public function get(string $name): ?Project
    {
        return $this->projects[$name] ?? null;
    }
}
