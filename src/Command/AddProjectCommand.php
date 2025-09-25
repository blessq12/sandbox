<?php

namespace Andrewmaster\Sandbox\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'project:add',
    description: 'Создает symlink на директорию проекта в ./test-projects и YAML-конфиг в ./config/projects',
)]
class AddProjectCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Системное имя проекта (slug)')
            ->addArgument('path', InputArgument::REQUIRED, 'Путь к директории проекта')
            ->addOption('title', null, InputOption::VALUE_OPTIONAL, 'Человекочитаемый заголовок', null)
            ->addOption('entry-point', null, InputOption::VALUE_OPTIONAL, 'Относительный путь к entry point (index.php)')
            ->addOption('entry', null, InputOption::VALUE_OPTIONAL, 'DEPRECATED: используйте --entry-point')
            ->addOption('no-link', null, InputOption::VALUE_NONE, 'Не создавать symlink в test-projects')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Перезаписать существующие артефакты (конфиг, symlink, директории)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = (string) $input->getArgument('name');
        $pathArg = (string) $input->getArgument('path');
        $title = (string) ($input->getOption('title') ?? $name);
        $entryPointOpt = $input->getOption('entry-point');
        $entryDeprecated = $input->getOption('entry');
        $entry = $entryPointOpt ? (string) $entryPointOpt : ($entryDeprecated ? (string) $entryDeprecated : 'public/index.php');
        $noLink = (bool) $input->getOption('no-link');
        $force = (bool) $input->getOption('force');
        $absolutePath = realpath($pathArg) ?: $pathArg;

        if (!is_dir($absolutePath)) {
            $io->error('Указанный путь не является директорией: ' . $absolutePath);
            return Command::FAILURE;
        }

        // 0) Подготовка путей
        $projectsDir = getcwd() . DIRECTORY_SEPARATOR . 'test-projects';
        $symlinkPath = $projectsDir . DIRECTORY_SEPARATOR . $name;

        // 1) ./test-projects symlink (если не отключен флагом)
        if (!$noLink) {
            if (!is_dir($projectsDir)) {
                if (!mkdir($projectsDir, 0775, true) && !is_dir($projectsDir)) {
                    $io->error('Не удалось создать директорию test-projects');
                    return Command::FAILURE;
                }
            }

            if (file_exists($symlinkPath) || is_link($symlinkPath)) {
                if ($force) {
                    $this->removePath($symlinkPath);
                } else {
                    $io->error('Путь уже существует: ' . $symlinkPath . '. Используйте --force для перезаписи.');
                    return Command::FAILURE;
                }
            }
            if (!@symlink($absolutePath, $symlinkPath)) {
                $io->error('Не удалось создать symlink ' . $symlinkPath . ' -> ' . $absolutePath);
                return Command::FAILURE;
            }
        }

        // 2) ./config/projects YAML
        $configDir = getcwd() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'projects';
        if (!is_dir($configDir)) {
            if (!mkdir($configDir, 0775, true) && !is_dir($configDir)) {
                $io->error('Не удалось создать директорию конфигов');
                return Command::FAILURE;
            }
        }

        $configFile = $configDir . DIRECTORY_SEPARATOR . $name . '.yaml';
        if (file_exists($configFile) && !$force) {
            $io->error('Конфиг уже существует: ' . $configFile . '. Используйте --force для перезаписи.');
            return Command::FAILURE;
        }

        // 3) tests/{project}/{routes,scenarios}
        $testsBaseDir = getcwd() . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . $name;
        $routesDir = $testsBaseDir . DIRECTORY_SEPARATOR . 'routes';
        $scenariosDir = $testsBaseDir . DIRECTORY_SEPARATOR . 'scenarios';

        foreach ([$testsBaseDir, $routesDir, $scenariosDir] as $dir) {
            if (is_dir($dir)) {
                if ($force) {
                    // оставляем существующие, но гарантируем наличие
                } else {
                    // ок, директория уже есть
                }
            } else {
                if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                    $io->error('Не удалось создать директорию: ' . $dir);
                    return Command::FAILURE;
                }
            }
        }

        // .gitkeep файлы
        foreach ([$routesDir, $scenariosDir] as $dir) {
            $gitkeep = $dir . DIRECTORY_SEPARATOR . '.gitkeep';
            if (!file_exists($gitkeep)) {
                @file_put_contents($gitkeep, "");
            }
        }

        // Формирование конфига проекта по новой схеме
        $config = [
            'name' => $name,
            'title' => $title,
            'projectRoot' => $absolutePath,
            'entryPoint' => $entry,
            'tests' => [
                'scenariosDir' => 'tests' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'scenarios',
                'routesDir' => 'tests' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'routes',
            ],
            'env' => new \ArrayObject([], \ArrayObject::ARRAY_AS_PROPS),
            'added_at' => date('c'),
        ];

        // Принудительно нормализуем DIRECTORY_SEPARATOR в YAML до '/' для переносимости
        $config['tests']['scenariosDir'] = str_replace(DIRECTORY_SEPARATOR, '/', $config['tests']['scenariosDir']);
        $config['tests']['routesDir'] = str_replace(DIRECTORY_SEPARATOR, '/', $config['tests']['routesDir']);

        // Преобразуем пустой env в {} вместо []
        if ($config['env'] instanceof \ArrayObject) {
            $config['env'] = (object) [];
        }

        $yaml = Yaml::dump($config, 4, 2, Yaml::DUMP_OBJECT_AS_MAP);
        if (false === file_put_contents($configFile, $yaml)) {
            $io->error('Не удалось записать YAML-конфиг');
            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Проект "%s" добавлен. %sКонфиг: %s; tests: %s',
            $name,
            $noLink ? '' : sprintf('Symlink: %s -> %s; ', $symlinkPath, $absolutePath),
            $configFile,
            $testsBaseDir
        ));
        return Command::SUCCESS;
    }

    private function removePath(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        if (is_dir($path)) {
            $items = scandir($path) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $this->removePath($path . DIRECTORY_SEPARATOR . $item);
            }
            @rmdir($path);
        }
    }
}
