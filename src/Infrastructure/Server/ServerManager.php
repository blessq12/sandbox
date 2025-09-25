<?php

namespace Andrewmaster\Sandbox\Infrastructure\Server;

use Andrewmaster\Sandbox\Domain\Project;
use Symfony\Component\Process\Process;

class ServerManager
{
    public function __construct(private string $stateDir)
    {
        if (!is_dir($this->stateDir)) {
            @mkdir($this->stateDir, 0775, true);
        }
    }

    private function stateFile(Project $project): string
    {
        return $this->stateDir . DIRECTORY_SEPARATOR . $project->name . '.json';
    }

    /** @return array{pid:int|null,port:int|null,baseUrl:string|null} */
    public function status(Project $project): array
    {
        $file = $this->stateFile($project);
        if (!is_file($file)) {
            return ['pid' => null, 'port' => null, 'baseUrl' => null];
        }
        $data = json_decode((string) file_get_contents($file), true) ?: [];
        return [
            'pid' => $data['pid'] ?? null,
            'port' => $data['port'] ?? null,
            'baseUrl' => $data['baseUrl'] ?? null,
        ];
    }

    public function start(Project $project, ?int $port = null, int $timeoutSec = 10): array
    {
        $entry = $this->resolveEntry($project);
        $docroot = dirname($entry);
        $port = $port ?: $this->findFreePort();
        $host = '127.0.0.1';
        $baseUrl = 'http://' . $host . ':' . $port;

        $cmd = ['php', '-S', $host . ':' . $port, '-t', $docroot];
        $process = new Process($cmd, $docroot, null, null);
        $process->disableOutput();
        $process->start();

        $startedAt = time();
        while (time() - $startedAt < $timeoutSec) {
            if ($this->isPortOpen($host, $port)) {
                $this->saveState($project, $process->getPid(), $port, $baseUrl);
                return ['pid' => $process->getPid(), 'port' => $port, 'baseUrl' => $baseUrl];
            }
            usleep(200_000);
        }

        $process->stop(1);
        throw new \RuntimeException('Не удалось запустить сервер для проекта: ' . $project->name);
    }

    public function stop(Project $project, int $timeoutSec = 5): void
    {
        $status = $this->status($project);
        if (!$status['pid']) {
            return;
        }
        $pid = (int) $status['pid'];
        if (stripos(PHP_OS, 'WIN') === 0) {
            exec('taskkill /F /PID ' . $pid);
        } else {
            posix_kill($pid, SIGTERM);
        }

        $startedAt = time();
        while (time() - $startedAt < $timeoutSec) {
            if (!$this->isPidAlive($pid)) {
                break;
            }
            usleep(200_000);
        }

        @unlink($this->stateFile($project));
    }

    private function resolveEntry(Project $project): string
    {
        if ($project->entry) {
            $candidate = rtrim($project->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($project->entry, DIRECTORY_SEPARATOR);
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        $candidates = [
            $project->path . '/public/index.php',
            $project->path . '/index.php',
        ];
        foreach ($candidates as $file) {
            if (is_file($file)) {
                return $file;
            }
        }
        throw new \InvalidArgumentException('Не найден entry point (index.php) в проекте: ' . $project->path);
    }

    private function checkHealth(string $url): bool
    {
        $ctx = stream_context_create(['http' => ['timeout' => 1]]);
        $res = @file_get_contents($url, false, $ctx);
        return $res !== false;
    }

    private function isPortOpen(string $host, int $port): bool
    {
        $errno = 0;
        $errstr = '';
        $conn = @fsockopen($host, $port, $errno, $errstr, 0.5);
        if (is_resource($conn)) {
            fclose($conn);
            return true;
        }
        return false;
    }

    private function saveState(Project $project, ?int $pid, int $port, string $baseUrl): void
    {
        file_put_contents($this->stateFile($project), json_encode([
            'pid' => $pid,
            'port' => $port,
            'baseUrl' => $baseUrl,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function findFreePort(): int
    {
        $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (!$sock) {
            return random_int(49152, 65535);
        }
        $name = stream_socket_get_name($sock, false);
        fclose($sock);
        if (preg_match('/:(\d+)$/', (string) $name, $m)) {
            return (int) $m[1];
        }
        return random_int(49152, 65535);
    }

    private function isPidAlive(int $pid): bool
    {
        if (stripos(PHP_OS, 'WIN') === 0) {
            exec('tasklist /FI "PID eq ' + $pid + '"', $out, $code);
            return str_contains(implode("\n", $out), (string) $pid);
        }
        return posix_kill($pid, 0);
    }
}
