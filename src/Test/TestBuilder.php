<?php

namespace Andrewmaster\Sandbox\Test;

use Andrewmaster\Sandbox\Tests\Integration\RouteTest;
use Andrewmaster\Sandbox\Tests\Integration\ScenarioTest;
use Andrewmaster\Sandbox\Tests\Support\Factories\TestFactory;

/**
 * Builder для создания тестов из YAML конфигураций
 * Использует Builder паттерн из "банды четырех"
 */
class TestBuilder
{
    private TestConfigParser $parser;
    private TestFactory $factory;

    public function __construct()
    {
        $this->factory = new TestFactory();
    }

    public function fromConfig(string $configPath): self
    {
        $this->parser = (new TestConfigParser())->loadFromFile($configPath);
        return $this;
    }

    public function fromYamlString(string $yamlContent): self
    {
        $this->parser = (new TestConfigParser())->loadFromString($yamlContent);
        return $this;
    }

    /**
     * Создать тесты маршрутов из конфигурации
     */
    public function buildRouteTests(): array
    {
        $routeTests = [];
        $routes = $this->parser->getRouteTests();

        foreach ($routes as $routeName => $routeConfig) {
            $routeTest = $this->factory->createRouteTest($routeName, [
                'baseUrl' => $this->parser->getBaseUrl(),
                'headers' => $this->mergeHeaders($routeConfig['headers'] ?? []),
            ]);

            $routeTests[$routeName] = [
                'test' => $routeTest,
                'config' => $routeConfig,
            ];
        }

        return $routeTests;
    }

    /**
     * Создать тесты сценариев из конфигурации
     */
    public function buildScenarioTests(): array
    {
        $scenarioTests = [];
        $scenarios = $this->parser->getScenarioTests();

        foreach ($scenarios as $scenarioName => $scenarioConfig) {
            $scenarioTest = $this->factory->createScenarioTest($scenarioName, [
                'baseUrl' => $this->parser->getBaseUrl(),
                'project' => $scenarioConfig['project'] ?? 'test-project',
                'timeout' => $scenarioConfig['timeout'] ?? $this->parser->getDefaultTimeout(),
            ]);

            $scenarioTests[$scenarioName] = [
                'test' => $scenarioTest,
                'config' => $scenarioConfig,
            ];
        }

        return $scenarioTests;
    }

    /**
     * Создать все тесты
     */
    public function buildAll(): array
    {
        return [
            'routes' => $this->buildRouteTests(),
            'scenarios' => $this->buildScenarioTests(),
        ];
    }

    /**
     * Выполнить тест маршрута по конфигурации
     */
    public function executeRouteTest(string $routeName): array
    {
        $routeTests = $this->buildRouteTests();

        if (!isset($routeTests[$routeName])) {
            throw new \InvalidArgumentException("Тест маршрута не найден: {$routeName}");
        }

        $routeTest = $routeTests[$routeName]['test'];
        $config = $routeTests[$routeName]['config'];

        return $this->executeRouteByConfig($routeTest, $config);
    }

    /**
     * Выполнить тест сценария по конфигурации
     */
    public function executeScenarioTest(string $scenarioName): array
    {
        $scenarioTests = $this->buildScenarioTests();

        if (!isset($scenarioTests[$scenarioName])) {
            throw new \InvalidArgumentException("Тест сценария не найден: {$scenarioName}");
        }

        $scenarioTest = $scenarioTests[$scenarioName]['test'];
        $config = $scenarioTests[$scenarioName]['config'];

        return $this->executeScenarioByConfig($scenarioTest, $config);
    }

    /**
     * Выполнить маршрут по конфигурации
     */
    private function executeRouteByConfig(RouteTest $routeTest, array $config): array
    {
        $method = strtoupper($config['method'] ?? 'GET');
        $path = $config['path'];
        $body = $config['body'] ?? [];
        $expectedStatus = $config['expectedStatus'] ?? [200];

        return match ($method) {
            'GET' => $routeTest->testGet($path, $expectedStatus),
            'POST' => $routeTest->testPost($path, $body, $expectedStatus),
            'PUT' => $routeTest->testPut($path, $body, $expectedStatus),
            'DELETE' => $routeTest->testDelete($path, $expectedStatus),
            'PATCH' => $routeTest->testPost($path, $body, $expectedStatus), // Используем POST для PATCH
            default => throw new \InvalidArgumentException("Неподдерживаемый HTTP метод: {$method}")
        };
    }

    /**
     * Выполнить сценарий по конфигурации
     */
    private function executeScenarioByConfig(ScenarioTest $scenarioTest, array $config): array
    {
        $type = $config['type'] ?? 'http';
        $steps = $config['steps'] ?? [];

        return match ($type) {
            'http' => $scenarioTest->testHttpScenario($steps),
            'cli' => $scenarioTest->testCliScenario($steps),
            'mixed' => $scenarioTest->testMixedScenario($steps),
            default => throw new \InvalidArgumentException("Неподдерживаемый тип сценария: {$type}")
        };
    }

    /**
     * Объединить заголовки с дефолтными
     */
    private function mergeHeaders(array $routeHeaders): array
    {
        return array_merge($this->parser->getDefaultHeaders(), $routeHeaders);
    }
}
