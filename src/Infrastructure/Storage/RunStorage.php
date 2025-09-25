<?php

namespace Andrewmaster\Sandbox\Infrastructure\Storage;

class RunStorage
{
    public function __construct(private string $directory)
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
    }

    /** @param array<string, mixed> $data */
    public function save(array $data): string
    {
        $project = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) ($data['project'] ?? 'project')) ?? 'project';
        $scenario = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) ($data['scenario'] ?? 'scenario')) ?? 'scenario';
        $stamp = date('Ymd_His');

        $runDirName = $project . '_' . $scenario . '_' . $stamp;
        $runDir = $this->directory . DIRECTORY_SEPARATOR . $runDirName;
        if (!is_dir($runDir)) {
            @mkdir($runDir, 0775, true);
        }

        if (isset($data['result']['steps']) && is_array($data['result']['steps'])) {
            foreach ($data['result']['steps'] as $idx => &$step) {
                if (!is_array($step)) {
                    continue;
                }
                $body = $step['body'] ?? null;
                if ($body === null) {
                    continue;
                }
                $bodyStr = is_string($body) ? $body : (string) $body;
                $ext = 'txt';
                $pretty = $bodyStr;
                $decoded = json_decode($bodyStr, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $ext = 'json';
                    $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                }
                $bodyFile = $runDir . DIRECTORY_SEPARATOR . 'step_' . $idx . '_body.' . $ext;
                @file_put_contents($bodyFile, $pretty);
                $step['body_file'] = $bodyFile;
            }
            unset($step);
        }

        $file = $runDir . DIRECTORY_SEPARATOR . 'result.json';
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $file;
    }
}
