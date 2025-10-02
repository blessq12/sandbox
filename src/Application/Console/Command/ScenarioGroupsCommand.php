<?php

namespace Andrewmaster\Sandbox\Application\Console\Command;

use Andrewmaster\Sandbox\Domain\Scenario\ScenarioRepository;
use Andrewmaster\Sandbox\Infrastructure\Project\ProjectRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'scenario:groups', description: 'Список групп сценариев проекта')]
class ScenarioGroupsCommand extends Command
{
    public function __construct(
        private readonly ProjectRegistry $registry,
        private readonly ScenarioRepository $repository
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

        $groupPaths = $this->repository->getGroupPaths($project);

        if (empty($groupPaths)) {
            $io->info('Группы не найдены. Все сценарии находятся в корневой директории.');
            return Command::SUCCESS;
        }

        $io->section("Группы сценариев проекта: {$projectName}");

        $rows = [];
        foreach ($groupPaths as $groupPath) {
            $metadata = $this->repository->getGroupMetadata($project, $groupPath);
            if ($metadata) {
                $rows[] = [
                    $groupPath,
                    $metadata['scenarios_count'],
                    !empty($metadata['subgroups']) ? implode(', ', $metadata['subgroups']) : '-'
                ];
            }
        }

        $io->table(['Группа', 'Сценариев', 'Подгруппы'], $rows);

        return Command::SUCCESS;
    }
}

