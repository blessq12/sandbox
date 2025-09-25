<?php

/**
 * Генератор тест-кейсов для Sandbox
 * Использование: php generator.php --project=myapi --type=auth --output=tests/myapi/scenarios/
 */

class TestCaseGenerator
{
    private array $templates = [
        'auth' => [
            'name' => 'auth',
            'description' => 'Авторизация пользователя',
            'steps' => [
                [
                    'type' => 'http',
                    'method' => 'POST',
                    'path' => '/api/v1/auth/register',
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json'
                    ],
                    'body' => [
                        'email' => 'faker.email:user_email',
                        'password' => 'faker.password:user_password',
                        'name' => 'first_name:user_name',
                        'phone' => 'phone_number:user_phone'
                    ],
                    'extract' => [
                        'token' => 'data.response.access_token',
                        'user_id' => 'data.response.uuid'
                    ]
                ],
                [
                    'type' => 'http',
                    'method' => 'POST',
                    'path' => '/api/v1/auth/login',
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json'
                    ],
                    'body' => [
                        'email' => '{{user_email}}',
                        'password' => '{{user_password}}'
                    ],
                    'extract' => [
                        'login_token' => 'data.response.access_token'
                    ]
                ]
            ]
        ],

        'crud' => [
            'name' => 'crud',
            'description' => 'CRUD операции',
            'steps' => [
                [
                    'type' => 'http',
                    'method' => 'POST',
                    'path' => '/api/v1/items',
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer {{token}}'
                    ],
                    'body' => [
                        'title' => 'faker.sentence:item_title',
                        'description' => 'faker.text:item_description',
                        'price' => 'faker.numberBetween:item_price'
                    ],
                    'extract' => [
                        'item_id' => 'data.response.id'
                    ]
                ],
                [
                    'type' => 'http',
                    'method' => 'GET',
                    'path' => '/api/v1/items',
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer {{token}}'
                    ]
                ],
                [
                    'type' => 'http',
                    'method' => 'GET',
                    'path' => '/api/v1/items/{{item_id}}',
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer {{token}}'
                    ]
                ],
                [
                    'type' => 'http',
                    'method' => 'PUT',
                    'path' => '/api/v1/items/{{item_id}}',
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer {{token}}'
                    ],
                    'body' => [
                        'title' => 'Updated {{item_title}}',
                        'description' => 'Updated {{item_description}}'
                    ]
                ],
                [
                    'type' => 'http',
                    'method' => 'DELETE',
                    'path' => '/api/v1/items/{{item_id}}',
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer {{token}}'
                    ]
                ]
            ]
        ],

        'upload' => [
            'name' => 'upload',
            'description' => 'Загрузка файлов',
            'steps' => [
                [
                    'type' => 'http',
                    'method' => 'POST',
                    'path' => '/api/v1/upload',
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'multipart/form-data',
                        'Authorization' => 'Bearer {{token}}'
                    ],
                    'body' => [
                        'file' => 'file:/absolute/path/to/file.png',
                        'description' => 'faker.sentence:upload_desc'
                    ],
                    'extract' => [
                        'file_id' => 'data.response.file_id',
                        'file_url' => 'data.response.url'
                    ]
                ],
                [
                    'type' => 'http',
                    'method' => 'GET',
                    'path' => '/api/v1/files/{{file_id}}',
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer {{token}}'
                    ]
                ]
            ]
        ]
    ];

    public function generate(string $project, string $type, string $outputDir): void
    {
        if (!isset($this->templates[$type])) {
            throw new InvalidArgumentException("Неизвестный тип: $type");
        }

        $template = $this->templates[$type];
        $yaml = $this->arrayToYaml($template);

        $outputFile = rtrim($outputDir, '/') . "/{$template['name']}.yaml";

        if (!is_dir(dirname($outputFile))) {
            mkdir(dirname($outputFile), 0755, true);
        }

        file_put_contents($outputFile, $yaml);
        echo "✅ Создан тест-кейс: $outputFile\n";
    }

    private function arrayToYaml(array $data): string
    {
        $yaml = "steps:\n";

        foreach ($data['steps'] as $step) {
            $yaml .= "  - type: {$step['type']}\n";

            if (isset($step['method'])) {
                $yaml .= "    method: {$step['method']}\n";
            }

            if (isset($step['path'])) {
                $yaml .= "    path: {$step['path']}\n";
            }

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
        }

        return $yaml;
    }

    public function listTypes(): void
    {
        echo "Доступные типы тест-кейсов:\n";
        foreach ($this->templates as $type => $template) {
            echo "  $type - {$template['description']}\n";
        }
    }
}

// CLI интерфейс
if (php_sapi_name() === 'cli') {
    $options = getopt('', ['project:', 'type:', 'output:', 'list']);

    if (isset($options['list'])) {
        $generator = new TestCaseGenerator();
        $generator->listTypes();
        exit(0);
    }

    if (!isset($options['project']) || !isset($options['type']) || !isset($options['output'])) {
        echo "Использование: php generator.php --project=PROJECT --type=TYPE --output=OUTPUT_DIR\n";
        echo "              php generator.php --list\n";
        echo "\nПримеры:\n";
        echo "  php generator.php --project=myapi --type=auth --output=tests/myapi/scenarios/\n";
        echo "  php generator.php --project=myapi --type=crud --output=tests/myapi/scenarios/\n";
        exit(1);
    }

    try {
        $generator = new TestCaseGenerator();
        $generator->generate($options['project'], $options['type'], $options['output']);
    } catch (Exception $e) {
        echo "❌ Ошибка: " . $e->getMessage() . "\n";
        exit(1);
    }
}
