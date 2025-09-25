<?php

namespace Andrewmaster\Sandbox\Infrastructure\Runner;

class CliRunner implements StepRunnerInterface
{
    public function getType(): string
    {
        return 'cli';
    }

    public function run(array $step, array $context = []): array
    {
        $started = microtime(true);
        $cmd = (string) ($step['cmd'] ?? 'echo noop');
        if (isset($context['env']) && is_array($context['env'])) {
            foreach ($context['env'] as $k => $v) {
                putenv($k . '=' . $v);
            }
        }
        $exitCode = 0;
        $output = shell_exec($cmd . ' 2>&1');
        if ($output === null) {
            $exitCode = 1;
            $output = '';
        }
        $durationMs = (int) ((microtime(true) - $started) * 1000);
        return [
            'ok' => $exitCode === 0,
            'exit_code' => $exitCode,
            'stdout' => $output,
            'duration_ms' => $durationMs,
        ];
    }
}
