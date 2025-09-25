<?php

namespace Andrewmaster\Sandbox\Application\Console\Command;

use Andrewmaster\Sandbox\Infrastructure\Project\ProjectRegistry;
use Andrewmaster\Sandbox\Application\Orchestrator\Orchestrator;
use Andrewmaster\Sandbox\Infrastructure\Runner\CliRunner;
use Andrewmaster\Sandbox\Infrastructure\Runner\HttpRunner;
use Andrewmaster\Sandbox\Infrastructure\Scenario\ScenarioLoader;
use Andrewmaster\Sandbox\Infrastructure\Server\ServerManager;
use Andrewmaster\Sandbox\Infrastructure\Storage\RunStorage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'scenario:run-all', description: 'Запустить все сценарии проекта')]
class ScenarioRunAllCommand extends Command
{
    public function __construct(
        private readonly ProjectRegistry $registry,
        private readonly ScenarioLoader $loader,
        private readonly RunStorage $storage,
        private readonly ?ServerManager $servers = null
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('project', InputArgument::REQUIRED, 'Имя проекта');
        $this->addOption('save-reports', null, InputOption::VALUE_NONE, 'Сохранить детальные отчеты в файлы');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectName = (string) $input->getArgument('project');
        $saveReports = $input->getOption('save-reports');

        $project = $this->registry->get($projectName);
        if (!$project) {
            $io->error('Проект не найден: ' . $projectName);
            return Command::FAILURE;
        }

        $scenarios = $this->loader->loadFromProject($project);
        if (empty($scenarios)) {
            $io->warning('Сценарии не найдены для проекта: ' . $projectName);
            return Command::SUCCESS;
        }

        $context = [];
        $startedByUs = false;
        if ($this->servers) {
            $status = $this->servers->status($project);
            if (!$status['pid']) {
                $started = $this->servers->start($project, null);
                $status = $started;
                $startedByUs = true;
            }
            if (!empty($status['baseUrl'])) {
                $context['baseUrl'] = $status['baseUrl'];
            }
        }

        $io->section("Запуск всех сценариев проекта: {$projectName}");
        $io->text('Найдено сценариев: ' . count($scenarios));

        $orchestrator = new Orchestrator([new HttpRunner(), new CliRunner()]);
        $allResults = [];
        $totalScenarios = count($scenarios);
        $successfulScenarios = 0;
        $failedScenarios = 0;

        foreach ($scenarios as $scenarioName => $scenario) {
            $io->newLine();
            $io->text("<info>Выполнение сценария: {$scenarioName}</info>");

            // Показываем прогресс выполнения для каждого сценария
            $progressBar = $io->createProgressBar(count($scenario->steps));
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
            $progressBar->start();

            $stepResults = [];
            $result = $orchestrator->runScenario($scenario, $context, function ($stepIndex, $stepResult, $stepType) use ($progressBar, &$stepResults) {
                $status = $stepResult['ok'] ? '✓' : '✗';
                $duration = $stepResult['duration_ms'] ?? 0;

                $stepInfo = [
                    'index' => $stepIndex,
                    'type' => $stepType,
                    'status' => $status,
                    'duration' => $duration,
                    'ok' => $stepResult['ok'] ?? false
                ];

                if (isset($stepResult['status'])) {
                    $stepInfo['http_status'] = $stepResult['status'];
                }

                if (isset($stepResult['error'])) {
                    $stepInfo['error'] = $stepResult['error'];
                }

                $stepResults[] = $stepInfo;
                $progressBar->setMessage("Шаг {$stepIndex}: {$stepType} {$status} ({$duration}ms)");
                $progressBar->advance();
            });

            $progressBar->finish();
            $io->newLine();

            // Краткий отчет по сценарию
            $metrics = $result['metrics'] ?? [];
            $scenarioSuccess = $result['ok'] ? '✓' : '✗';
            $io->text("Результат: {$scenarioSuccess} | Время: " . ($metrics['scenario']['total_duration_seconds'] ?? 0) . 'с | Успешных: ' . ($metrics['steps']['successful'] ?? 0) . '/' . ($metrics['steps']['count'] ?? 0));

            // Показываем детальную таблицу только для неуспешных шагов если есть ошибки
            if (!$result['ok'] && !empty($stepResults)) {
                $failedSteps = array_filter($stepResults, function ($step) {
                    return !$step['ok'];
                });

                if (!empty($failedSteps)) {
                    $stepTable = [];
                    foreach ($failedSteps as $step) {
                        $row = [
                            'Шаг ' . $step['index'],
                            $step['type'],
                            $step['status'],
                            $step['duration'] . 'мс'
                        ];

                        if (isset($step['http_status'])) {
                            $row[] = 'HTTP ' . $step['http_status'];
                        } else {
                            $row[] = '-';
                        }

                        if (isset($step['error'])) {
                            $row[] = substr($step['error'], 0, 50) . (strlen($step['error']) > 50 ? '...' : '');
                        } else {
                            $row[] = '-';
                        }

                        $stepTable[] = $row;
                    }

                    $io->text('<fg=red>Неуспешные шаги:</fg=red>');
                    $io->table(['Шаг', 'Тип', 'Статус', 'Время', 'HTTP', 'Ошибка'], $stepTable);
                }
            }

            if ($result['ok']) {
                $successfulScenarios++;
            } else {
                $failedScenarios++;
            }

            $allResults[$scenarioName] = [
                'result' => $result,
                'stepResults' => $stepResults
            ];

            // Сохраняем отчет только если указан флаг
            if ($saveReports) {
                $file = $this->storage->save([
                    'project' => $projectName,
                    'scenario' => $scenarioName,
                    'result' => $result,
                    'ts' => date('c'),
                ]);
                $io->text("Отчет сохранен: {$file}");
            }
        }

        $io->newLine();
        $io->section('Итоговые результаты:');

        // Основная статистика
        $io->table(['Метрика', 'Значение'], [
            ['Всего сценариев', $totalScenarios],
            ['Успешных', $successfulScenarios],
            ['Неудачных', $failedScenarios],
            ['Процент успеха', $totalScenarios > 0 ? round(($successfulScenarios / $totalScenarios) * 100, 1) . '%' : '0%'],
        ]);

        // Сводная таблица метрик по всем сценариям
        $io->newLine();
        $io->section('Сводная таблица метрик:');
        $metricsTable = [];
        foreach ($allResults as $scenarioName => $data) {
            $metrics = $data['result']['metrics'] ?? [];
            $stepMetrics = $metrics['steps'] ?? [];

            $status = $data['result']['ok'] ? '✓' : '✗';
            $totalTime = round($metrics['scenario']['total_duration_seconds'] ?? 0, 3);
            $stepsCount = $stepMetrics['count'] ?? 0;
            $successfulSteps = $stepMetrics['successful'] ?? 0;
            $failedSteps = $stepMetrics['failed'] ?? 0;
            $avgStepTime = round($stepMetrics['average_duration_ms'] ?? 0);

            $metricsTable[] = [
                $scenarioName,
                $status,
                $totalTime . 'с',
                $successfulSteps . '/' . $stepsCount,
                $avgStepTime . 'мс'
            ];
        }

        // Сортируем по времени выполнения (по убыванию)
        usort($metricsTable, function ($a, $b) {
            $timeA = (float)str_replace('с', '', $a[2]);
            $timeB = (float)str_replace('с', '', $b[2]);
            return $timeB <=> $timeA;
        });

        $io->table(['Сценарий', 'Статус', 'Время', 'Успешных шагов', 'Ср. время шага'], $metricsTable);

        if ($this->servers && $startedByUs) {
            $this->servers->stop($project);
        }

        $overallSuccess = $failedScenarios === 0 ? '✓' : '✗';
        $io->text("Общий результат: {$overallSuccess}");

        return $failedScenarios === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
