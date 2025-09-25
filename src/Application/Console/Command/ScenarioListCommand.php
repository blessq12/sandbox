<?php

namespace Andrewmaster\Sandbox\Application\Console\Command;

use Andrewmaster\Sandbox\Infrastructure\Project\ProjectRegistry;
use Andrewmaster\Sandbox\Infrastructure\Scenario\ScenarioLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'scenario:list', description: 'Список сценариев проекта')]
class ScenarioListCommand extends Command
{
    public function __construct(
        private readonly ProjectRegistry $registry,
        private readonly ScenarioLoader $loader
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('project', InputArgument::REQUIRED, 'Имя проекта');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectName = (string) $input->getArgument('project');
        $project = $this->registry->get($projectName);
        if (!$project) {
            $io->error('Проект не найден: ' . $projectName);
            return Command::FAILURE;
        }

        $scenarios = $this->loader->loadFromProject($project);
        $rows = [];
        foreach ($scenarios as $name => $scenario) {
            $rows[] = [$name, count($scenario->steps)];
        }
        $io->table(['name', 'steps'], $rows);
        return Command::SUCCESS;
    }
}
