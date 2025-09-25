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
        return match ($class) {
            __NAMESPACE__ . '\\Command\\ProjectListCommand' => new \Andrewmaster\Sandbox\Application\Console\Command\ProjectListCommand(
                new \Andrewmaster\Sandbox\Infrastructure\Project\ProjectRegistry(getcwd() . '/config/projects')
            ),
            __NAMESPACE__ . '\\Command\\ScenarioListCommand' => new \Andrewmaster\Sandbox\Application\Console\Command\ScenarioListCommand(
                new \Andrewmaster\Sandbox\Infrastructure\Project\ProjectRegistry(getcwd() . '/config/projects'),
                new \Andrewmaster\Sandbox\Infrastructure\Scenario\ScenarioLoader()
            ),
            __NAMESPACE__ . '\\Command\\ScenarioRunCommand' => new \Andrewmaster\Sandbox\Application\Console\Command\ScenarioRunCommand(
                new \Andrewmaster\Sandbox\Infrastructure\Project\ProjectRegistry(getcwd() . '/config/projects'),
                new \Andrewmaster\Sandbox\Infrastructure\Scenario\ScenarioLoader(),
                new \Andrewmaster\Sandbox\Infrastructure\Storage\RunStorage(getcwd() . '/result'),
                new \Andrewmaster\Sandbox\Infrastructure\Server\ServerManager(getcwd() . '/var/servers')
            ),
            __NAMESPACE__ . '\\Command\\AddProjectCommand' => new \Andrewmaster\Sandbox\Application\Console\Command\AddProjectCommand(),
            __NAMESPACE__ . '\\Command\\ScenarioRunAllCommand' => new \Andrewmaster\Sandbox\Application\Console\Command\ScenarioRunAllCommand(
                new \Andrewmaster\Sandbox\Infrastructure\Project\ProjectRegistry(getcwd() . '/config/projects'),
                new \Andrewmaster\Sandbox\Infrastructure\Scenario\ScenarioLoader(),
                new \Andrewmaster\Sandbox\Infrastructure\Storage\RunStorage(getcwd() . '/result'),
                new \Andrewmaster\Sandbox\Infrastructure\Server\ServerManager(getcwd() . '/var/servers')
            ),
            default => new $class(),
        };
    }
}
