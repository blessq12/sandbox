<?php

namespace Andrewmaster\Sandbox\Test;

use Symfony\Component\Yaml\Yaml;

/**
 * Парсер YAML конфигураций для тестов
 * Использует Builder паттерн для создания тестов из конфигов
 */
class TestConfigParser
{
    private array $config = [];

    public function loadFromFile(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Файл конфигурации не найден: {$filePath}");
        }

        $this->config = Yaml::parseFile($filePath);
        return $this;
    }

    public function loadFromString(string $yamlContent): self
    {
        $this->config = Yaml::parse($yamlContent);
        return $this;
    }

    /**
     * Получить конфигурацию для тестирования маршрутов
     */
    public function getRouteTests(): array
    {
        return $this->config['routes'] ?? [];
    }

    /**
     * Получить конфигурацию для тестирования сценариев
     */
    public function getScenarioTests(): array
    {
        return $this->config['scenarios'] ?? [];
    }

    /**
     * Получить общие настройки тестов
     */
    public function getSettings(): array
    {
        return $this->config['settings'] ?? [];
    }

    /**
     * Получить базовый URL
     */
    public function getBaseUrl(): string
    {
        return $this->getSettings()['baseUrl'] ?? 'http://127.0.0.1:8000';
    }

    /**
     * Получить таймаут по умолчанию
     */
    public function getDefaultTimeout(): int
    {
        return $this->getSettings()['timeout'] ?? 30;
    }

    /**
     * Получить заголовки по умолчанию
     */
    public function getDefaultHeaders(): array
    {
        return $this->getSettings()['headers'] ?? [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Получить все тесты
     */
    public function getAllTests(): array
    {
        return [
            'routes' => $this->getRouteTests(),
            'scenarios' => $this->getScenarioTests(),
            'settings' => $this->getSettings(),
        ];
    }
}
