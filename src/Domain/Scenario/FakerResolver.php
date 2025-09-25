<?php

namespace Andrewmaster\Sandbox\Domain\Scenario;

use Faker\Factory;

class FakerResolver
{
    private \Faker\Generator $faker;
    /** @var array<string,string> */
    private array $vars = [];

    public function __construct(?string $locale = null)
    {
        $this->faker = Factory::create($locale ?: 'ru_RU');
    }

    /**
     * Возвращает все сохраненные переменные
     * @return array<string,string>
     */
    public function getVariables(): array
    {
        return $this->vars;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function resolve(mixed $value): mixed
    {
        if (is_array($value)) {
            if (isset($value['_faker'])) {
                $directive = (string) $value['_faker'];
                return match ($directive) {
                    'object' => $this->buildObjectFromSchema((array) ($value['schema'] ?? [])),
                    'array' => $this->buildArrayFromSchema((array) ($value['of'] ?? []), (int) ($value['count'] ?? 1)),
                    default => $this->resolveArray($value),
                };
            }
            return $this->resolveArray($value);
        }

        if (is_string($value)) {
            return $this->resolveString($value);
        }

        return $value;
    }

    /** @param array<string,mixed> $arr */
    private function resolveArray(array $arr): array
    {
        $result = [];
        foreach ($arr as $k => $v) {
            $result[$k] = $this->resolve($v);
        }
        return $result;
    }

    /** @param array<string,mixed> $schema */
    private function buildObjectFromSchema(array $schema): array
    {
        $result = [];
        foreach ($schema as $field => $spec) {
            if (is_string($spec)) {
                $result[$field] = $this->resolveString($this->normalizeFakerSpec($spec));
            } else {
                $result[$field] = $this->resolve($spec);
            }
        }
        return $result;
    }

    /** @param array<string,mixed> $itemSchema */
    private function buildArrayFromSchema(array $itemSchema, int $count): array
    {
        $out = [];
        $count = max(0, $count);
        for ($i = 0; $i < $count; $i++) {
            $out[] = $this->buildObjectFromSchema($itemSchema);
        }
        return $out;
    }

    private function normalizeFakerSpec(string $value): string
    {
        if (str_starts_with($value, 'faker.')) {
            return $value;
        }
        return 'faker.' . $value;
    }

    private function resolveString(string $value): string
    {
        if (str_contains($value, ':')) {
            [$baseValue, $varKey] = explode(':', $value, 2);
            $varKey = (string) $varKey;
            if ($varKey !== '' && isset($this->vars[$varKey])) {
                return $this->vars[$varKey];
            }

            $generated = $this->resolveString($baseValue);
            if ($generated !== $baseValue) {
                $this->vars[$varKey] = $generated;
                return $generated;
            }
        }

        if (str_starts_with($value, 'faker.')) {
            $methodRaw = substr($value, strlen('faker.')) ?: '';
            $method = $this->toCamelCase($methodRaw);
            if ($method !== '') {
                try {
                    /** @var string $generated */
                    $generated = (string) $this->faker->{$method}();
                    return $generated;
                } catch (\Throwable) {
                    return $value;
                }
            }
        }

        return match ($value) {
            'email' => $this->faker->email(),
            'name' => $this->faker->name(),
            'username' => $this->faker->userName(),
            'password' => $this->faker->password(),
            'phone', 'phone_number' => '+7' . $this->faker->numerify('##########'),
            'city' => $this->faker->city(),
            'country' => $this->faker->country(),
            'address' => $this->faker->address(),
            'surname', 'last_name' => $this->generateSimpleSurname(),
            'first_name', 'firstname' => $this->generateSimpleName(),
            default => $value,
        };
    }

    private function toCamelCase(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name) ?? $name;
        $parts = explode('_', $name);
        $camel = array_shift($parts) ?: '';
        foreach ($parts as $p) {
            $camel .= ucfirst($p);
        }
        return $camel;
    }

    private function generateSimpleSurname(): string
    {
        $surnames = [
            'Иванов',
            'Петров',
            'Сидоров',
            'Козлов',
            'Новиков',
            'Морозов',
            'Петухов',
            'Волков',
            'Соловьев',
            'Васильев',
            'Зайцев',
            'Павлов',
            'Семенов',
            'Голубев',
            'Виноградов',
            'Богданов',
            'Воробьев',
            'Федоров',
            'Михайлов',
            'Белов',
            'Тарасов',
            'Беляев'
        ];
        return $surnames[array_rand($surnames)];
    }

    private function generateSimpleName(): string
    {
        $names = [
            'Александр',
            'Алексей',
            'Андрей',
            'Антон',
            'Борис',
            'Вадим',
            'Валентин',
            'Валерий',
            'Василий',
            'Виктор',
            'Владимир',
            'Владислав',
            'Вячеслав',
            'Геннадий',
            'Георгий',
            'Дмитрий',
            'Евгений',
            'Егор',
            'Иван',
            'Игорь',
            'Илья',
            'Кирилл',
            'Константин',
            'Максим',
            'Михаил',
            'Николай',
            'Олег',
            'Павел',
            'Петр',
            'Роман',
            'Сергей',
            'Станислав',
            'Степан',
            'Юрий',
            'Ярослав'
        ];
        return $names[array_rand($names)];
    }
}
