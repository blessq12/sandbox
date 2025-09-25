<?php

/**
 * Laravel/Symfony генератор тест-кейсов
 * Автоматически генерирует тесты на основе роутов приложения
 */

class LaravelTestGenerator
{
    private string $projectPath;
    private array $routes = [];

    public function __construct(string $projectPath)
    {
        $this->projectPath = $projectPath;
    }

    public function scanRoutes(): void
    {
        echo "🔍 Сканируем роуты приложения...\n";

        // Ищем файлы роутов
        $routeFiles = [
            'routes/api.php',
            'routes/web.php',
            'config/routes.php',
            'src/Controller/',
            'app/Http/Controllers/'
        ];

        foreach ($routeFiles as $file) {
            $fullPath = $this->projectPath . '/' . $file;
            if (file_exists($fullPath)) {
                $this->parseRouteFile($fullPath);
            }
        }

        echo "✅ Найдено роутов: " . count($this->routes) . "\n";
    }

    private function parseRouteFile(string $filePath): void
    {
        $content = file_get_contents($filePath);

        // Простой парсинг роутов (можно улучшить)
        preg_match_all('/Route::(\w+)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);

        for ($i = 0; $i < count($matches[1]); $i++) {
            $method = strtoupper($matches[1][$i]);
            $path = $matches[2][$i];

            $this->routes[] = [
                'method' => $method,
                'path' => $path,
                'file' => basename($filePath)
            ];
        }
    }

    public function generateTestCases(string $outputDir): void
    {
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Группируем роуты по функциональности
        $groups = $this->groupRoutes();

        foreach ($groups as $groupName => $routes) {
            $this->generateGroupTest($groupName, $routes, $outputDir);
        }
    }

    private function groupRoutes(): array
    {
        $groups = [
            'auth' => [],
            'api' => [],
            'admin' => [],
            'user' => [],
            'other' => []
        ];

        foreach ($this->routes as $route) {
            $path = $route['path'];

            if (str_contains($path, 'auth') || str_contains($path, 'login') || str_contains($path, 'register')) {
                $groups['auth'][] = $route;
            } elseif (str_contains($path, 'admin')) {
                $groups['admin'][] = $route;
            } elseif (str_contains($path, 'user') || str_contains($path, 'profile')) {
                $groups['user'][] = $route;
            } elseif (str_contains($path, 'api')) {
                $groups['api'][] = $route;
            } else {
                $groups['other'][] = $route;
            }
        }

        return array_filter($groups, fn($routes) => !empty($routes));
    }

    private function generateGroupTest(string $groupName, array $routes, string $outputDir): void
    {
        $steps = [];
        $stepIndex = 0;

        // Добавляем авторизацию если нужно
        if ($groupName !== 'auth' && $this->hasAuthRoutes()) {
            $steps[] = $this->generateAuthStep();
            $stepIndex++;
        }

        // Генерируем шаги для каждого роута
        foreach ($routes as $route) {
            $step = $this->generateStepForRoute($route, $stepIndex);
            if ($step) {
                $steps[] = $step;
                $stepIndex++;
            }
        }

        if (empty($steps)) {
            return;
        }

        $yaml = "steps:\n";
        foreach ($steps as $step) {
            $yaml .= $this->stepToYaml($step);
        }

        $outputFile = $outputDir . "/{$groupName}.yaml";
        file_put_contents($outputFile, $yaml);
        echo "✅ Создан тест: $outputFile\n";
    }

    private function generateAuthStep(): array
    {
        return [
            'type' => 'http',
            'method' => 'POST',
            'path' => '/api/auth/login',
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'body' => [
                'email' => 'faker.email:user_email',
                'password' => 'faker.password:user_password'
            ],
            'extract' => [
                'token' => 'data.response.access_token',
                'user_id' => 'data.response.uuid'
            ]
        ];
    }

    private function generateStepForRoute(array $route, int $index): ?array
    {
        $method = $route['method'];
        $path = $route['path'];

        $step = [
            'type' => 'http',
            'method' => $method,
            'path' => $path,
            'headers' => [
                'Accept' => 'application/json'
            ]
        ];

        // Добавляем авторизацию для защищенных роутов
        if (!str_contains($path, 'auth') && !str_contains($path, 'public')) {
            $step['headers']['Authorization'] = 'Bearer {{token}}';
        }

        // Добавляем тело для POST/PUT запросов
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $step['headers']['Content-Type'] = 'application/json';
            $step['body'] = $this->generateBodyForRoute($path);
        }

        // Добавляем извлечение данных для GET запросов
        if ($method === 'GET' && str_contains($path, '{')) {
            $step['extract'] = [
                'item_id' => 'data.response.id'
            ];
        }

        return $step;
    }

    private function generateBodyForRoute(string $path): array
    {
        $body = [];

        if (str_contains($path, 'user') || str_contains($path, 'profile')) {
            $body = [
                'name' => 'faker.firstName:user_name',
                'email' => 'faker.email:user_email',
                'phone' => 'phone_number:user_phone'
            ];
        } elseif (str_contains($path, 'item') || str_contains($path, 'product')) {
            $body = [
                'title' => 'faker.sentence:item_title',
                'description' => 'faker.text:item_description',
                'price' => 'faker.numberBetween:item_price'
            ];
        } else {
            $body = [
                'title' => 'faker.sentence:title',
                'content' => 'faker.text:content'
            ];
        }

        return $body;
    }

    private function stepToYaml(array $step): string
    {
        $yaml = "  - type: {$step['type']}\n";
        $yaml .= "    method: {$step['method']}\n";
        $yaml .= "    path: {$step['path']}\n";

        if (isset($step['headers'])) {
            $yaml .= "    headers:\n";
            foreach ($step['headers'] as $key => $value) {
                $yaml .= "      $key: \"$value\"\n";
            }
        }

        if (isset($step['body'])) {
            $yaml .= "    body:\n";
            foreach ($step['body'] as $key => $value) {
                $yaml .= "      $key: \"$value\"\n";
            }
        }

        if (isset($step['extract'])) {
            $yaml .= "    extract:\n";
            foreach ($step['extract'] as $key => $value) {
                $yaml .= "      $key: \"$value\"\n";
            }
        }

        $yaml .= "\n";
        return $yaml;
    }

    private function hasAuthRoutes(): bool
    {
        foreach ($this->routes as $route) {
            if (str_contains($route['path'], 'auth') || str_contains($route['path'], 'login')) {
                return true;
            }
        }
        return false;
    }
}

// CLI интерфейс
if (php_sapi_name() === 'cli') {
    $options = getopt('', ['project:', 'output:']);

    if (!isset($options['project']) || !isset($options['output'])) {
        echo "Использование: php laravel-generator.php --project=PROJECT_PATH --output=OUTPUT_DIR\n";
        echo "\nПример:\n";
        echo "  php laravel-generator.php --project=/path/to/laravel --output=tests/scenarios/\n";
        exit(1);
    }

    $generator = new LaravelTestGenerator($options['project']);
    $generator->scanRoutes();
    $generator->generateTestCases($options['output']);

    echo "\n🚀 Теперь можно запустить тесты:\n";
    echo "  ./sandbox scenario:run PROJECT auth\n";
    echo "  ./sandbox scenario:run PROJECT api\n";
    echo "  ./sandbox scenario:run-all PROJECT\n";
}
