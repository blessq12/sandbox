<?php

namespace Andrewmaster\Sandbox\Domain\Scenario;

/**
 * Component интерфейс для Composite Pattern
 * Позволяет единообразно работать с отдельными сценариями и группами
 */
interface ScenarioComponent
{
    /**
     * Получить имя компонента (сценария или группы)
     */
    public function getName(): string;

    /**
     * Получить полный путь компонента (для групп включает родительские группы)
     */
    public function getPath(): string;

    /**
     * Получить все сценарии (для группы - все вложенные, для сценария - сам)
     * @return Scenario[]
     */
    public function getScenarios(): array;

    /**
     * Получить количество сценариев
     */
    public function count(): int;

    /**
     * Является ли компонент группой
     */
    public function isGroup(): bool;
}
