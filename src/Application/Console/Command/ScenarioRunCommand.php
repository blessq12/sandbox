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
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectName = (string) $input->getArgument('project');
        $scenarioName = (string) $input->getArgument('scenario');

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

        $orchestrator = new Orchestrator([new HttpRunner(), new CliRunner()]);

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

        $result = $orchestrator->runScenario($scenario, $context);
        $file = $this->storage->save([
            'project' => $projectName,
            'scenario' => $scenarioName,
            'result' => $result,
            'ts' => date('c'),
        ]);

        $io->success('Сценарий выполнен. Результат: ' . $file);

        if ($this->servers && $startedByUs) {
            $this->servers->stop($project);
        }
        return $result['ok'] ? Command::SUCCESS : Command::FAILURE;
    }
}
