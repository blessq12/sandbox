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

#[AsCommand(name: 'scenario:run', description: 'Запустить сценарий проекта')]
class ScenarioRunCommand extends Command
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
        $this->addArgument('scenario', InputArgument::REQUIRED, 'Имя сценария');
        $this->addOption('save-report', null, InputOption::VALUE_NONE, 'Сохранить детальный отчет в файл');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectName = (string) $input->getArgument('project');
        $scenarioName = (string) $input->getArgument('scenario');
        $saveReport = $input->getOption('save-report');

        // Очищаем папку result перед запуском
        $this->cleanResultDirectory();

        $project = $this->registry->get($projectName);
        if (!$project) {
            $io->error('Проект не найден: ' . $projectName);
            return Command::FAILURE;
        }

        $scenarios = $this->loader->loadFromProject($project);
        $scenario = $scenarios[$scenarioName] ?? null;
        if (!$scenario) {
            $io->error('Сценарий не найден: ' . $scenarioName);
            return Command::FAILURE;
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

        // Показываем прогресс выполнения
        $io->section("Выполнение сценария: {$projectName}/{$scenarioName}");
        $progressBar = $io->createProgressBar(count($scenario->steps));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $progressBar->start();

        $stepResults = [];
        $orchestrator = new Orchestrator([new HttpRunner(), new CliRunner()]);
        $result = $orchestrator->runScenario($scenario, $context, function ($stepIndex, $stepResult, $stepType) use ($progressBar, &$stepResults, $io) {
            $status = $stepResult['ok'] ? '✓' : '✗';
            $duration = $stepResult['duration_ms'] ?? 0;

            // Собираем детали для краткого отчета
            $stepInfo = [
                'index' => $stepIndex,
                'type' => $stepType,
                'status' => $status,
                'duration' => $duration,
                'ok' => $stepResult['ok'] ?? false
            ];

            // Добавляем HTTP статус если есть
            if (isset($stepResult['status'])) {
                $stepInfo['http_status'] = $stepResult['status'];
            }

            // Добавляем ошибку если есть
            if (isset($stepResult['error'])) {
                $stepInfo['error'] = $stepResult['error'];
            }

            $stepResults[] = $stepInfo;

            $progressBar->setMessage("Шаг {$stepIndex}: {$stepType} {$status} ({$duration}ms)");
            $progressBar->advance();
        });

        $progressBar->finish();
        $io->newLine(2);

        // Показываем краткий отчет по шагам
        $io->section('Результаты выполнения шагов:');
        $stepTable = [];
        foreach ($stepResults as $step) {
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

        $io->table(['Шаг', 'Тип', 'Статус', 'Время', 'HTTP', 'Ошибка'], $stepTable);

        // Показываем краткую статистику
        $metrics = $result['metrics'] ?? [];
        $io->table(['Метрика', 'Значение'], [
            ['Общее время', ($metrics['scenario']['total_duration_seconds'] ?? 0) . 'с'],
            ['Шагов выполнено', ($metrics['steps']['count'] ?? 0)],
            ['Успешных', ($metrics['steps']['successful'] ?? 0)],
            ['Неудачных', ($metrics['steps']['failed'] ?? 0)],
            ['Среднее время шага', ($metrics['steps']['average_duration_ms'] ?? 0) . 'мс'],
        ]);

        // Сохраняем отчет только если указан флаг
        if ($saveReport) {
            $file = $this->storage->save([
                'project' => $projectName,
                'scenario' => $scenarioName,
                'result' => $result,
                'ts' => date('c'),
            ]);
            $io->success('Отчет сохранен: ' . $file);
        }

        if ($this->servers && $startedByUs) {
            $this->servers->stop($project);
        }

        $success = $result['ok'] ? '✓' : '✗';
        $io->text("Результат: {$success}");
        return $result['ok'] ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Очистка папки result перед запуском тестов
     */
    private function cleanResultDirectory(): void
    {
        $resultDir = __DIR__ . '/../../../../result';

        if (!is_dir($resultDir)) {
            return;
        }

        $items = glob($resultDir . '/*');
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if (is_dir($item)) {
                $this->removeDirectory($item);
            } else {
                @unlink($item);
            }
        }
    }

    /**
     * Рекурсивное удаление директории
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = glob($dir . '/{,.}*', GLOB_BRACE);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if (basename($item) === '.' || basename($item) === '..') {
                continue;
            }

            if (is_dir($item)) {
                $this->removeDirectory($item);
            } else {
                @unlink($item);
            }
        }

        @rmdir($dir);
    }
}
