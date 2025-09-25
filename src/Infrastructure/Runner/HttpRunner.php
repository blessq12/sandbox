<?php

namespace Andrewmaster\Sandbox\Infrastructure\Runner;

class HttpRunner implements StepRunnerInterface
{
    public function getType(): string
    {
        return 'http';
    }

    public function run(array $step, array $context = []): array
    {
        $started = microtime(true);
        $method = strtoupper((string) ($step['method'] ?? 'GET'));
        $path = (string) ($step['path'] ?? '/');
        $baseUrl = (string) ($context['baseUrl'] ?? 'http://127.0.0.1');
        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');

        $opts = ['http' => [
            'method' => $method,
            'timeout' => (float) ($step['timeout'] ?? 5),
            'ignore_errors' => true,
        ]];
        $requestHeaders = [];
        if (isset($step['headers']) && is_array($step['headers'])) {
            $hdrs = [];
            foreach ($step['headers'] as $k => $v) {
                $hdrs[] = $k . ': ' . $v;
                $requestHeaders[$k] = $v;
            }
            $opts['http']['header'] = implode("\r\n", $hdrs);
        }
        if (isset($step['body'])) {
            $body = is_array($step['body']) ? json_encode($step['body']) : (string) $step['body'];
            $opts['http']['content'] = $body;
            if (empty(($step['headers']['Content-Type'] ?? null))) {
                $opts['http']['header'] = ($opts['http']['header'] ?? '') . (empty($opts['http']['header']) ? '' : "\r\n") . 'Content-Type: application/json';
            }
        }
        $ctx = stream_context_create($opts);
        $body = @file_get_contents($url, false, $ctx);
        $status = 0;
        $respHeaders = [];
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('#HTTP/\S+\s+(\d{3})#', $line, $m)) {
                    $status = (int) $m[1];
                } elseif (strpos($line, ':') !== false) {
                    [$hk, $hv] = array_map('trim', explode(':', $line, 2));
                    // аккумулируем множественные заголовки
                    if (isset($respHeaders[$hk])) {
                        if (is_array($respHeaders[$hk])) {
                            $respHeaders[$hk][] = $hv;
                        } else {
                            $respHeaders[$hk] = [$respHeaders[$hk], $hv];
                        }
                    } else {
                        $respHeaders[$hk] = $hv;
                    }
                }
            }
        }
        $durationMs = (int) ((microtime(true) - $started) * 1000);

        // Парсим JSON ответ если возможно
        $parsedBody = null;
        if ($body !== false && $body !== '') {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $parsedBody = $decoded;
            }
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'url' => $url,
            'request' => [
                'headers' => $requestHeaders,
                'body' => isset($step['body']) ? (is_array($step['body']) ? $step['body'] : (string) $step['body']) : null,
            ],
            'headers' => $respHeaders,
            'body' => $body === false ? '' : $body,
            'data' => $parsedBody, // Парсированный JSON для извлечения переменных
            'duration_ms' => $durationMs,
        ];
    }
}
