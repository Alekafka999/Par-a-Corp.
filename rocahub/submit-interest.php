<?php
declare(strict_types=1);

function redirect_with_status(string $status, string $reason = ''): void
{
    $location = 'index.html?form=' . rawurlencode($status);

    if ($reason !== '') {
        $location .= '&reason=' . rawurlencode($reason);
    }

    $location .= '#pilot-interest-form';

    header('Location: ' . $location, true, 303);
    exit;
}

function clean_text(string $value): string
{
    $value = trim($value);
    $value = str_replace(["\r", "\n"], [' ', ' '], $value);

    return preg_replace('/\s+/', ' ', $value) ?? '';
}

function encode_subject(string $subject): string
{
    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}

function save_submission(array $data): bool
{
    $directory = __DIR__ . DIRECTORY_SEPARATOR . 'submissions';

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        return false;
    }

    $file = $directory . DIRECTORY_SEPARATOR . 'pilot-interest.csv';
    $handle = fopen($file, 'ab');

    if ($handle === false) {
        return false;
    }

    if (filesize($file) === 0) {
        fputcsv($handle, ['timestamp', 'nome', 'email', 'whatsapp', 'cidade', 'perfil', 'comentario', 'ip']);
    }

    $written = fputcsv($handle, [
        date('c'),
        $data['nome'],
        $data['email'],
        $data['whatsapp'],
        $data['cidade'],
        $data['perfil'],
        $data['comentario'],
        $data['ip'],
    ]);

    fclose($handle);

    return $written !== false;
}

function append_smtp_log(string $message, array $context = []): void
{
    $directory = __DIR__ . DIRECTORY_SEPARATOR . 'submissions';

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        return;
    }

    $file = $directory . DIRECTORY_SEPARATOR . 'smtp-error.log';
    $lines = [
        '[' . date('c') . '] ' . $message,
    ];

    foreach ($context as $key => $value) {
        $lines[] = $key . ': ' . (string) $value;
    }

    $lines[] = str_repeat('-', 40);

    file_put_contents($file, implode(PHP_EOL, $lines) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function load_smtp_config(): array
{
    $defaults = [
        'host' => 'mail.parcacorp.com.br',
        'port' => 465,
        'security' => 'ssl',
        'username' => 'rocahub@parcacorp.com.br',
        'password' => '',
        'from_email' => 'rocahub@parcacorp.com.br',
        'from_name' => 'Parca Corp',
        'to_email' => 'rocahub@parcacorp.com.br',
        'timeout' => 20,
    ];

    $configFile = __DIR__ . DIRECTORY_SEPARATOR . 'smtp-config.php';

    if (is_file($configFile)) {
        $loaded = require $configFile;

        if (is_array($loaded)) {
            $defaults = array_merge($defaults, $loaded);
        }
    }

    $envMap = [
        'host' => 'ROCAHUB_SMTP_HOST',
        'port' => 'ROCAHUB_SMTP_PORT',
        'security' => 'ROCAHUB_SMTP_SECURITY',
        'username' => 'ROCAHUB_SMTP_USERNAME',
        'password' => 'ROCAHUB_SMTP_PASSWORD',
        'from_email' => 'ROCAHUB_SMTP_FROM_EMAIL',
        'from_name' => 'ROCAHUB_SMTP_FROM_NAME',
        'to_email' => 'ROCAHUB_SMTP_TO_EMAIL',
        'timeout' => 'ROCAHUB_SMTP_TIMEOUT',
    ];

    foreach ($envMap as $key => $envName) {
        $value = getenv($envName);

        if ($value !== false && $value !== '') {
            $defaults[$key] = $value;
        }
    }

    $defaults['port'] = (int) $defaults['port'];
    $defaults['timeout'] = (int) $defaults['timeout'];
    $defaults['security'] = strtolower((string) $defaults['security']);

    return $defaults;
}

function smtp_expect($socket, array $codes): array
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;

        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    if ($response === '') {
        throw new RuntimeException('SMTP sem resposta.');
    }

    $code = (int) substr($response, 0, 3);

    if (!in_array($code, $codes, true)) {
        throw new RuntimeException('SMTP respondeu com erro: ' . trim($response));
    }

    return [$code, $response];
}

function smtp_command($socket, string $command, array $codes): array
{
    fwrite($socket, $command . "\r\n");

    return smtp_expect($socket, $codes);
}

function send_via_smtp(array $config, string $subject, string $body, ?string $replyTo = null): bool
{
    if ($config['password'] === '' || $config['username'] === '' || $config['host'] === '' || $config['to_email'] === '') {
        throw new RuntimeException('Configuracao SMTP incompleta.');
    }

    $transport = $config['host'];

    if ($config['security'] === 'ssl') {
        $transport = 'ssl://' . $config['host'];
    }

    $socket = @stream_socket_client(
        $transport . ':' . $config['port'],
        $errorNumber,
        $errorMessage,
        $config['timeout']
    );

    if ($socket === false) {
        throw new RuntimeException('Nao foi possivel conectar ao SMTP: ' . $errorMessage . ' (' . $errorNumber . ').');
    }

    stream_set_timeout($socket, $config['timeout']);

    try {
        smtp_expect($socket, [220]);
        smtp_command($socket, 'EHLO parcacorp.com.br', [250]);

        if ($config['security'] === 'tls') {
            smtp_command($socket, 'STARTTLS', [220]);

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Falha ao iniciar TLS no SMTP.');
            }

            smtp_command($socket, 'EHLO parcacorp.com.br', [250]);
        }

        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode((string) $config['username']), [334]);
        smtp_command($socket, base64_encode((string) $config['password']), [235]);
        smtp_command($socket, 'MAIL FROM:<' . $config['from_email'] . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $config['to_email'] . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $config['from_name'] . ' <' . $config['from_email'] . '>',
            'To: ' . $config['to_email'],
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        if ($replyTo !== null && $replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $data = implode("\r\n", $headers) . "\r\n\r\n" . str_replace(["\r\n.", "\n."], ["\r\n..", "\n.."], $body);
        fwrite($socket, $data . "\r\n.\r\n");
        smtp_expect($socket, [250]);
        smtp_command($socket, 'QUIT', [221]);
        fclose($socket);

        return true;
    } catch (Throwable $exception) {
        fclose($socket);
        throw $exception;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_status('error', 'send');
}

$nome = clean_text((string) ($_POST['nome'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$whatsapp = clean_text((string) ($_POST['whatsapp'] ?? ''));
$cidade = clean_text((string) ($_POST['cidade'] ?? ''));
$perfil = clean_text((string) ($_POST['perfil'] ?? ''));
$comentario = trim((string) ($_POST['comentario'] ?? ''));

if ($nome === '' || $whatsapp === '') {
    redirect_with_status('error', 'missing');
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_with_status('error', 'invalid');
}

$emailHeader = str_replace(["\r", "\n"], '', $email);
$safeComment = trim(str_replace("\r", '', $comentario));
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'indisponivel');
$smtpConfig = load_smtp_config();

$saved = save_submission([
    'nome' => $nome,
    'email' => $emailHeader,
    'whatsapp' => $whatsapp,
    'cidade' => $cidade,
    'perfil' => $perfil,
    'comentario' => $safeComment,
    'ip' => $ip,
]);

$subject = encode_subject('Roca Hub - Interesse no piloto');
$message = implode("\n", [
    'Novo interesse no piloto do Roca Hub',
    '',
    'Nome: ' . $nome,
    'E-mail: ' . ($emailHeader !== '' ? $emailHeader : 'Nao informado'),
    'WhatsApp: ' . $whatsapp,
    'Cidade: ' . ($cidade !== '' ? $cidade : 'Nao informada'),
    'Perfil: ' . ($perfil !== '' ? $perfil : 'Nao informado'),
    'Comentario:',
    $safeComment !== '' ? $safeComment : 'Sem comentario.',
    '',
    'Origem: https://parcacorp.com.br/rocahub/',
    'IP: ' . $ip,
]);

if ($smtpConfig['password'] === '' || $smtpConfig['username'] === '' || $smtpConfig['host'] === '' || $smtpConfig['to_email'] === '') {
    append_smtp_log('Configuracao SMTP incompleta.', [
        'host' => $smtpConfig['host'],
        'port' => $smtpConfig['port'],
        'security' => $smtpConfig['security'],
        'username' => $smtpConfig['username'],
        'to_email' => $smtpConfig['to_email'],
        'nome' => $nome,
        'whatsapp' => $whatsapp,
        'ip' => $ip,
    ]);

    if ($saved) {
        redirect_with_status('error', 'smtp_config');
    }

    redirect_with_status('error', 'send');
}

try {
    $sent = send_via_smtp($smtpConfig, $subject, $message, $emailHeader !== '' ? $emailHeader : null);

    if ($sent) {
        redirect_with_status('success');
    }
} catch (Throwable $exception) {
    append_smtp_log('Falha no envio SMTP.', [
        'error' => $exception->getMessage(),
        'host' => $smtpConfig['host'],
        'port' => $smtpConfig['port'],
        'security' => $smtpConfig['security'],
        'username' => $smtpConfig['username'],
        'to_email' => $smtpConfig['to_email'],
        'nome' => $nome,
        'email' => $emailHeader !== '' ? $emailHeader : 'Nao informado',
        'whatsapp' => $whatsapp,
        'ip' => $ip,
    ]);

    if ($saved) {
        redirect_with_status('error', 'send_saved');
    }

    redirect_with_status('error', 'send');
}

if ($saved) {
    redirect_with_status('error', 'send_saved');
}

redirect_with_status('error', 'send');
