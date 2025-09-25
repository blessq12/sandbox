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
            $contentType = $step['headers']['Content-Type'] ?? null;

            if ($contentType === 'multipart/form-data') {
                // Обработка multipart/form-data для загрузки файлов
                $boundary = '----WebKitFormBoundary' . uniqid();
                $multipartBody = '';

                foreach ($step['body'] as $key => $value) {
                    if (is_array($value)) {
                        // Обработка массивов
                        foreach ($value as $index => $item) {
                            $multipartBody .= "--{$boundary}\r\n";

                            if (strpos($item, 'file:') === 0) {
                                // Файл по пути
                                $filePath = substr($item, 5);
                                $fileName = basename($filePath);
                                $fileContent = file_get_contents($filePath);

                                $multipartBody .= "Content-Disposition: form-data; name=\"{$key}[]\"; filename=\"{$fileName}\"\r\n";
                                $multipartBody .= "Content-Type: " . mime_content_type($filePath) . "\r\n\r\n";
                                $multipartBody .= $fileContent;
                            } else {
                                // Обычное текстовое поле в массиве
                                $multipartBody .= "Content-Disposition: form-data; name=\"{$key}[]\"\r\n\r\n";
                                $multipartBody .= $item;
                            }
                            $multipartBody .= "\r\n";
                        }
                    } else {
                        // Обычная обработка
                        $multipartBody .= "--{$boundary}\r\n";

                        if (strpos($value, 'data:') === 0) {
                            // Base64 данные
                            $multipartBody .= "Content-Disposition: form-data; name=\"{$key}\"\r\n";
                            $multipartBody .= "Content-Type: application/octet-stream\r\n\r\n";
                            $multipartBody .= base64_decode(substr($value, strpos($value, ',') + 1));
                        } elseif (strpos($value, 'file:') === 0) {
                            // Файл по пути
                            $filePath = substr($value, 5);
                            $fileName = basename($filePath);
                            $fileContent = file_get_contents($filePath);

                            $multipartBody .= "Content-Disposition: form-data; name=\"{$key}\"; filename=\"{$fileName}\"\r\n";
                            $multipartBody .= "Content-Type: " . mime_content_type($filePath) . "\r\n\r\n";
                            $multipartBody .= $fileContent;
                        } else {
                            // Обычное текстовое поле
                            $multipartBody .= "Content-Disposition: form-data; name=\"{$key}\"\r\n\r\n";
                            $multipartBody .= $value;
                        }
                        $multipartBody .= "\r\n";
                    }
                }
                $multipartBody .= "--{$boundary}--\r\n";

                $opts['http']['content'] = $multipartBody;
                $opts['http']['header'] = ($opts['http']['header'] ?? '') . (empty($opts['http']['header']) ? '' : "\r\n") . "Content-Type: multipart/form-data; boundary={$boundary}";
            } else {
                // Обычная JSON обработка
                $body = is_array($step['body']) ? json_encode($step['body']) : (string) $step['body'];
                $opts['http']['content'] = $body;
                if (empty($contentType)) {
                    $opts['http']['header'] = ($opts['http']['header'] ?? '') . (empty($opts['http']['header']) ? '' : "\r\n") . 'Content-Type: application/json';
                }
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
