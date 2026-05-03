<?php
declare(strict_types=1);

function parca_honeypot_is_clean(array $post, array $fields = ['website']): bool
{
    foreach ($fields as $field) {
        if (trim((string) ($post[$field] ?? '')) !== '') {
            return false;
        }
    }

    return true;
}

function parca_text_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    return strlen($value);
}

function parca_fields_within_limits(array $values, array $limits): bool
{
    foreach ($limits as $field => $limit) {
        if (isset($values[$field]) && parca_text_length((string) $values[$field]) > (int) $limit) {
            return false;
        }
    }

    return true;
}

function parca_csv_value(string $value): string
{
    if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
        return "'" . $value;
    }

    return $value;
}

function parca_csv_row(array $values): array
{
    return array_map(static fn ($value): string => parca_csv_value((string) $value), $values);
}

function parca_ip_fingerprint(string $ip): string
{
    return hash('sha256', 'parca-log|' . $ip);
}

function parca_safe_log_context(array $context): array
{
    $personalKeys = ['nome', 'email', 'whatsapp'];
    $safe = [];

    foreach ($context as $key => $value) {
        if (in_array((string) $key, $personalKeys, true)) {
            continue;
        }

        if ($key === 'ip') {
            $safe['ip_hash'] = parca_ip_fingerprint((string) $value);
            continue;
        }

        $safe[$key] = $value;
    }

    return $safe;
}

function parca_rate_limit(string $storageDirectory, string $scope, string $ip, int $maxAttempts = 5, int $windowSeconds = 900): bool
{
    $directory = rtrim($storageDirectory, DIRECTORY_SEPARATOR . '/\\') . DIRECTORY_SEPARATOR . 'rate-limit';

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        return false;
    }

    $key = hash('sha256', $scope . '|' . $ip);
    $file = $directory . DIRECTORY_SEPARATOR . $key . '.txt';
    $handle = fopen($file, 'c+');

    if ($handle === false) {
        return false;
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return false;
        }

        $now = time();
        $cutoff = $now - $windowSeconds;
        $contents = stream_get_contents($handle);
        $timestamps = [];

        foreach (preg_split('/\R/', (string) $contents) as $line) {
            $timestamp = (int) trim($line);

            if ($timestamp >= $cutoff) {
                $timestamps[] = $timestamp;
            }
        }

        if (count($timestamps) >= $maxAttempts) {
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, implode(PHP_EOL, $timestamps) . PHP_EOL);
            fflush($handle);
            flock($handle, LOCK_UN);
            fclose($handle);

            return false;
        }

        $timestamps[] = $now;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, implode(PHP_EOL, $timestamps) . PHP_EOL);
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return true;
    } catch (Throwable $exception) {
        flock($handle, LOCK_UN);
        fclose($handle);

        return false;
    }
}

function parca_download_token_directory(string $storageDirectory, string $project): string
{
    return rtrim($storageDirectory, DIRECTORY_SEPARATOR . '/\\')
        . DIRECTORY_SEPARATOR . 'download-tokens'
        . DIRECTORY_SEPARATOR . preg_replace('/[^a-z0-9_-]/i', '', $project);
}

function parca_create_download_token(string $storageDirectory, string $project, int $ttlSeconds = 600): ?string
{
    $directory = parca_download_token_directory($storageDirectory, $project);

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        return null;
    }

    try {
        $token = bin2hex(random_bytes(32));
    } catch (Throwable $exception) {
        return null;
    }

    $payload = json_encode([
        'expires_at' => time() + $ttlSeconds,
        'created_at' => time(),
    ]);

    if ($payload === false) {
        return null;
    }

    $file = $directory . DIRECTORY_SEPARATOR . $token . '.json';

    if (file_put_contents($file, $payload, LOCK_EX) === false) {
        return null;
    }

    return $token;
}

function parca_consume_download_token(string $storageDirectory, string $project, string $token): bool
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return false;
    }

    $directory = parca_download_token_directory($storageDirectory, $project);
    $file = $directory . DIRECTORY_SEPARATOR . $token . '.json';

    if (!is_file($file)) {
        return false;
    }

    $payload = json_decode((string) file_get_contents($file), true);
    @unlink($file);

    if (!is_array($payload)) {
        return false;
    }

    return (int) ($payload['expires_at'] ?? 0) >= time();
}
