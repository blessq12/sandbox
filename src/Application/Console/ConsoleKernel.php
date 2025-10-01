<?php

namespace Andrewmaster\Sandbox\Application\Console;

use Symfony\Component\Console\Application;

class ConsoleKernel
{
    public static function boot(Application $application): void
    {
        $commandsNamespace = __NAMESPACE__ . '\\Command\\';
        $commandsPath = __DIR__ . '/Command';

        if (!is_dir($commandsPath)) {
            return;
        }

        $directoryIterator = new \RecursiveDirectoryIterator($commandsPath, \FilesystemIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($directoryIterator);

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $filename = $fileInfo->getFilename();
            if (!str_ends_with($filename, 'Command.php')) {
                continue;
            }

            $realPath = $fileInfo->getRealPath();
            $relative = str_replace($commandsPath . DIRECTORY_SEPARATOR, '', $realPath);
            $class = $commandsNamespace . str_replace(['/', '.php'], ['\\', ''], $relative);

            if (class_exists($class)) {
                $instance = self::make($class);
                $application->add($instance);
            }
        }
    }

    private static function make(string $class): object
    {
        $registry = new \Andrewmaster\Sandbox\Infrastructure\Project\ProjectRegistry(getcwd() . '/config/projects');
        $loader = new \Andrewmaster\Sandbox\Infrastructure\Scenario\ScenarioLoader();
        $repository = new \Andrewmaster\Sandbox\Domain\Scenario\ScenarioRepository($loader);
        $storage = new \Andrewmaster\Sandbox\Infrastructure\Storage\RunStorage(getcwd() . '/result');
        $serverManager = new \Andrewmaster\Sandbox\Infrastructure\Server\ServerManager(getcwd() . '/var/servers');

        return match ($class) {
            __NAMESPACE__ . '\\Command\\ProjectListCommand' => new \Andrewmaster\Sandbox\Application\Console\Command\ProjectListCommand($registry),
            __NAMESPACE__ . '\\Command\\ScenarioListCommand' => new \Andrewmaster\Sandbox\Application\Console\Command\ScenarioListCommand($registry, $repository),
            __NAMESPACE__ . '\\Command\\ScenarioRunCommand' => new \Andrewmaster\Sandbox\Application\Console\Command\ScenarioRunCommand($registry, $loader, $storage, $serverManager),
            __NAMESPACE__ . '\\Command\\AddProjectCommand' => new \Andrewmaster\Sandbox\Application\Console\Command\AddProjectCommand(),
            __NAMESPACE__ . '\\Command\\ScenarioRunAllCommand' => new \Andrewmaster\Sandbox\Application\Console\Command\ScenarioRunAllCommand($registry, $loader, $storage, $serverManager),
            __NAMESPACE__ . '\\Command\\ScenarioGroupsCommand' => new \Andrewmaster\Sandbox\Application\Console\Command\ScenarioGroupsCommand($registry, $repository),
            __NAMESPACE__ . '\\Command\\ScenarioRunGroupCommand' => new \Andrewmaster\Sandbox\Application\Console\Command\ScenarioRunGroupCommand($registry, $repository, $storage, $serverManager),
            default => new $class(),
        };
    }
}
