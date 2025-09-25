<?php

namespace Andrewmaster\Sandbox\Application\Orchestrator;

use Andrewmaster\Sandbox\Infrastructure\Runner\StepRunnerInterface;
use Andrewmaster\Sandbox\Domain\Scenario\Scenario;
use Andrewmaster\Sandbox\Domain\Scenario\FakerResolver;

class Orchestrator
{
    /** @var array<string, StepRunnerInterface> */
    private array $runnersByType = [];

    /** @param StepRunnerInterface[] $runners */
    public function __construct(array $runners)
    {
        foreach ($runners as $runner) {
            $this->runnersByType[$runner->getType()] = $runner;
        }
    }

    /** @return array<string, mixed> */
    public function runScenario(Scenario $scenario, array $context = []): array
    {
        $results = [];
        $faker = new FakerResolver($context['locale'] ?? null);
        $stepContext = $context; // Контекст для передачи между шагами

        foreach ($scenario->steps as $index => $step) {
            // Разрешаем переменные в шаге с учетом текущего контекста
            $resolvedStep = $this->resolveStepVariables($step, $stepContext, $faker);

            // Добавляем переменные Faker в контекст
            $fakerVars = $faker->getVariables();
            $stepContext = array_merge($stepContext, $fakerVars);

            $type = (string) ($resolvedStep['type'] ?? 'cli');
            $runner = $this->runnersByType[$type] ?? null;
            if ($runner === null) {
                $results[] = ['ok' => false, 'error' => 'Unknown step type: ' . $type];
                continue;
            }

            $result = $runner->run($resolvedStep, $stepContext);
            $results[] = $result;

            // Извлекаем переменные из ответа для следующих шагов
            if ($result['ok'] && isset($step['extract'])) {
                $extracted = $this->extractVariables($result, $step['extract']);
                $stepContext = array_merge($stepContext, $extracted);
            }
        }
        return [
            'ok' => !in_array(false, array_column($results, 'ok'), true),
            'steps' => $results,
            'context' => $stepContext, // Возвращаем финальный контекст
        ];
    }

    /**
     * Разрешает переменные в шаге с учетом контекста
     * @param array<string, mixed> $step
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function resolveStepVariables(array $step, array $context, FakerResolver $faker): array
    {
        // Сначала разрешаем переменные контекста {{variable}}
        $contextResolved = $this->resolveContextVariables($step, $context);

        // Затем разрешаем Faker переменные
        return $faker->resolve($contextResolved);
    }

    /**
     * Разрешает переменные контекста в формате {{variable}}
     * @param mixed $data
     * @param array<string, mixed> $context
     * @return mixed
     */
    private function resolveContextVariables(mixed $data, array $context): mixed
    {
        if (is_string($data)) {
            return $this->resolveStringContextVariables($data, $context);
        }

        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = $this->resolveContextVariables($value, $context);
            }
            return $result;
        }

        return $data;
    }

    /**
     * Разрешает переменные контекста в строке
     */
    private function resolveStringContextVariables(string $value, array $context): string
    {
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) use ($context) {
            $varName = trim($matches[1]);
            return (string) ($context[$varName] ?? $matches[0]);
        }, $value);
    }

    /**
     * Извлекает переменные из ответа HTTP
     * @param array<string, mixed> $result
     * @param array<string, string> $extractConfig
     * @return array<string, mixed>
     */
    private function extractVariables(array $result, array $extractConfig): array
    {
        $extracted = [];

        foreach ($extractConfig as $varName => $path) {
            $value = $this->extractValueByPath($result, $path);
            if ($value !== null) {
                $extracted[$varName] = $value;
            }
        }

        return $extracted;
    }

    /**
     * Извлекает значение по пути (например, "body.data.token")
     */
    private function extractValueByPath(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $current = $data;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }
}
