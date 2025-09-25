<?php

namespace Andrewmaster\Sandbox\Command;

use Andrewmaster\Sandbox\Domain\ProjectRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'project:list', description: 'Список проектов')]
class ProjectListCommand extends Command
{
    public function __construct(private readonly ProjectRegistry $registry)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = [];
        foreach ($this->registry->all() as $p) {
            $rows[] = [$p->name, $p->title, $p->path];
        }
        $io->table(['name', 'title', 'path'], $rows);
        return Command::SUCCESS;
    }
}
