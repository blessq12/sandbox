<?php

namespace Andrewmaster\Sandbox\Application\Console\Command;

use Andrewmaster\Sandbox\Application\Orchestrator\Orchestrator;
use Andrewmaster\Sandbox\Domain\Scenario\ScenarioRepository;
use Andrewmaster\Sandbox\Infrastructure\Project\ProjectRegistry;
use Andrewmaster\Sandbox\Infrastructure\Runner\CliRunner;
use Andrewmaster\Sandbox\Infrastructure\Runner\HttpRunner;
use Andrewmaster\Sandbox\Infrastructure\Server\ServerManager;
use Andrewmaster\Sandbox\Infrastructure\Storage\RunStorage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'scenario:run-group', description: 'Запустить группу сценариев')]
class ScenarioRunGroupCommand extends Command
{
    public function __construct(
        private readonly ProjectRegistry $registry,
        private readonly ScenarioRepository $repository,
        private readonly RunStorage $storage,
        private readonly ?ServerManager $servers = null
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('project', InputArgument::REQUIRED, 'Имя проекта');
        $this->addArgument('group', InputArgument::REQUIRED, 'Путь к группе (например: profiles или auth/registration)');
        $this->addOption('save-reports', null, InputOption::VALUE_NONE, 'Сохранить детальные отчеты');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectName = (string) $input->getArgument('project');
        $groupPath = (string) $input->getArgument('group');
        $saveReports = $input->getOption('save-reports');

        $project = $this->registry->get($projectName);
        if (!$project) {
            $io->error('Проект не найден: ' . $projectName);
            return Command::FAILURE;
        }

        $scenarios = $this->repository->findByGroup($project, $groupPath);

        if (empty($scenarios)) {
            $io->warning("Сценарии не найдены в группе: {$groupPath}");
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

        $io->section("Запуск группы: {$groupPath}");
        $io->text('Найдено сценариев: ' . count($scenarios));

        $orchestrator = new Orchestrator([new HttpRunner(), new CliRunner()]);
        $successfulScenarios = 0;
        $failedScenarios = 0;

        foreach ($scenarios as $scenario) {
            $io->newLine();
            $io->text("<info>Выполнение: {$scenario->name}</info>");

            $progressBar = $io->createProgressBar(count($scenario->steps));
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
            $progressBar->start();

            $result = $orchestrator->runScenario($scenario, $context, function ($stepIndex, $stepResult, $stepType) use ($progressBar) {
                $status = $stepResult['ok'] ? '✓' : '✗';
                $duration = $stepResult['duration_ms'] ?? 0;
                $progressBar->setMessage("Шаг {$stepIndex}: {$stepType} {$status} ({$duration}ms)");
                $progressBar->advance();
            });

            $progressBar->finish();
            $io->newLine();

            $metrics = $result['metrics'] ?? [];
            $scenarioSuccess = $result['ok'] ? '✓' : '✗';
            $io->text("Результат: {$scenarioSuccess} | Время: " . ($metrics['scenario']['total_duration_seconds'] ?? 0) . 'с');

            if ($result['ok']) {
                $successfulScenarios++;
            } else {
                $failedScenarios++;
            }

            if ($saveReports) {
                $file = $this->storage->save([
                    'project' => $projectName,
                    'scenario' => $scenario->name,
                    'group' => $groupPath,
                    'result' => $result,
                    'ts' => date('c'),
                ]);
                $io->text("Отчет сохранен: {$file}");
            }
        }

        if ($this->servers && $startedByUs) {
            $this->servers->stop($project);
        }

        $io->newLine();
        $io->section('Итоги:');
        $total = count($scenarios);
        $io->table(['Метрика', 'Значение'], [
            ['Всего', $total],
            ['Успешных', $successfulScenarios],
            ['Неудачных', $failedScenarios],
            ['Успех', $total > 0 ? round(($successfulScenarios / $total) * 100, 1) . '%' : '0%'],
        ]);

        return $failedScenarios === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}

