<?php
declare(strict_types=1);

function clean_return_page(string $value): string
{
    return $value === 'en/index.html' ? 'en/index.html' : 'index.html';
}

function redirect_with_status(string $status, string $reason = '', string $context = 'form', string $anchor = 'pilot-interest-form', string $page = 'index.html'): void
{
    $location = clean_return_page($page) . '?' . rawurlencode($context) . '=' . rawurlencode($status);

    if ($reason !== '') {
        $location .= '&reason=' . rawurlencode($reason);
    }

    $location .= '#' . $anchor;

    header('Location: ' . $location, true, 303);
    exit;
}

function redirect_to_presentation(string $returnPage = 'index.html'): void
{
    $file = private_storage_directory() . DIRECTORY_SEPARATOR . 'downloads' . DIRECTORY_SEPARATOR . 'rocahub' . DIRECTORY_SEPARATOR . 'Roca-Hub.pdf';

    if (!is_file($file)) {
        redirect_with_status('error', 'file', 'download', 'presentation-download-form', $returnPage);
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Roca-Hub.pdf"');
    header('Content-Length: ' . (string) filesize($file));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    readfile($file);
    exit;
}

function redirect_success(bool $isPresentationDownload, string $returnPage = 'index.html'): void
{
    if ($isPresentationDownload) {
        redirect_to_presentation($returnPage);
    }

    redirect_with_status('success', '', 'form', 'pilot-interest-form', $returnPage);
}

function redirect_error(bool $isPresentationDownload, string $reason, string $returnPage = 'index.html'): void
{
    if ($isPresentationDownload) {
        redirect_with_status('error', $reason, 'download', 'presentation-download-form', $returnPage);
    }

    redirect_with_status('error', $reason, 'form', 'pilot-interest-form', $returnPage);
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

function private_storage_directory(): string
{
    $configured = getenv('PARCA_PRIVATE_DIR');

    if ($configured !== false && trim($configured) !== '') {
        return rtrim(trim($configured), DIRECTORY_SEPARATOR . '/\\');
    }

    return dirname(__DIR__) . DIRECTORY_SEPARATOR . '_private';
}

function submission_directory(): ?string
{
    $directory = private_storage_directory() . DIRECTORY_SEPARATOR . 'submissions' . DIRECTORY_SEPARATOR . basename(__DIR__);

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        return null;
    }

    return $directory;
}

function save_submission(array $data): bool
{
    $directory = submission_directory();

    if ($directory === null) {
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

function save_presentation_download(array $data): bool
{
    $directory = submission_directory();

    if ($directory === null) {
        return false;
    }

    $file = $directory . DIRECTORY_SEPARATOR . 'presentation-downloads.csv';
    $handle = fopen($file, 'ab');

    if ($handle === false) {
        return false;
    }

    if (filesize($file) === 0) {
        fputcsv($handle, ['timestamp', 'nome', 'email', 'whatsapp', 'empresa_ou_cidade', 'perfil', 'comentario', 'ip']);
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
    $directory = submission_directory();

    if ($directory === null) {
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

function send_via_php_mail(array $config, string $subject, string $body, ?string $replyTo = null): bool
{
    if ($config['to_email'] === '' || $config['from_email'] === '') {
        throw new RuntimeException('Configuracao de e-mail incompleta.');
    }

    $headers = [
        'From: ' . $config['from_name'] . ' <' . $config['from_email'] . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    if ($replyTo !== null && $replyTo !== '') {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    $sent = mail($config['to_email'], $subject, $body, implode("\r\n", $headers));

    if (!$sent) {
        throw new RuntimeException('A funcao mail() retornou falha.');
    }

    return true;
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
$leadGoal = clean_text((string) ($_POST['lead_goal'] ?? 'pilot_interest'));
$isPresentationDownload = $leadGoal === 'presentation_download';
$returnPage = clean_return_page((string) ($_POST['return_page'] ?? ''));

if ($isPresentationDownload && (
    $nome === '' ||
    $email === '' ||
    $whatsapp === '' ||
    $cidade === '' ||
    $perfil === '' ||
    $comentario === ''
)) {
    redirect_error(true, 'missing', $returnPage);
}

if (!$isPresentationDownload && (
    $nome === '' ||
    $email === '' ||
    $whatsapp === '' ||
    $cidade === '' ||
    $perfil === '' ||
    $comentario === ''
)) {
    redirect_error(false, 'missing', $returnPage);
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_error($isPresentationDownload, 'invalid', $returnPage);
}

$emailHeader = str_replace(["\r", "\n"], '', $email);
$safeComment = trim(str_replace("\r", '', $comentario));
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'indisponivel');
$smtpConfig = load_smtp_config();

if ($isPresentationDownload) {
    $saved = save_presentation_download([
        'nome' => $nome,
        'email' => $emailHeader,
        'whatsapp' => $whatsapp,
        'cidade' => $cidade,
        'perfil' => $perfil,
        'comentario' => $safeComment,
        'ip' => $ip,
    ]);

    $subject = encode_subject('Roca Hub - Download da apresentacao');
    $message = implode("\n", [
        'Novo download da apresentacao do Roca Hub',
        '',
        'Nome: ' . $nome,
        'E-mail: ' . $emailHeader,
        'WhatsApp: ' . ($whatsapp !== '' ? $whatsapp : 'Nao informado'),
        'Empresa ou cidade: ' . ($cidade !== '' ? $cidade : 'Nao informada'),
        'Perfil: ' . ($perfil !== '' ? $perfil : 'Nao informado'),
        'Comentario:',
        $safeComment !== '' ? $safeComment : 'Sem comentario.',
        '',
        'PDF liberado: download privado apos formulario',
        'Origem: https://parcacorp.com.br/rocahub/',
        'IP: ' . $ip,
    ]);
} else {
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
}

if (!$saved) {
    redirect_error($isPresentationDownload, 'save', $returnPage);
}

if ($smtpConfig['password'] === '' || $smtpConfig['username'] === '' || $smtpConfig['host'] === '' || $smtpConfig['to_email'] === '') {
    try {
        if (send_via_php_mail($smtpConfig, $subject, $message, $emailHeader !== '' ? $emailHeader : null)) {
            redirect_success($isPresentationDownload, $returnPage);
        }
    } catch (Throwable $exception) {
        append_smtp_log('Configuracao SMTP incompleta e fallback mail() falhou.', [
            'error' => $exception->getMessage(),
            'host' => $smtpConfig['host'],
            'port' => $smtpConfig['port'],
            'security' => $smtpConfig['security'],
            'username' => $smtpConfig['username'],
            'to_email' => $smtpConfig['to_email'],
            'nome' => $nome,
            'whatsapp' => $whatsapp,
            'ip' => $ip,
        ]);

        redirect_error($isPresentationDownload, 'notify', $returnPage);
    }

    redirect_error($isPresentationDownload, 'send', $returnPage);
}

try {
    $sent = send_via_smtp($smtpConfig, $subject, $message, $emailHeader !== '' ? $emailHeader : null);

    if ($sent) {
        redirect_success($isPresentationDownload, $returnPage);
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

    try {
        if (send_via_php_mail($smtpConfig, $subject, $message, $emailHeader !== '' ? $emailHeader : null)) {
            redirect_success($isPresentationDownload, $returnPage);
        }
    } catch (Throwable $mailException) {
        append_smtp_log('Fallback mail() tambem falhou.', [
            'error' => $mailException->getMessage(),
            'to_email' => $smtpConfig['to_email'],
            'nome' => $nome,
            'email' => $emailHeader !== '' ? $emailHeader : 'Nao informado',
            'whatsapp' => $whatsapp,
            'ip' => $ip,
        ]);

        redirect_error($isPresentationDownload, 'notify', $returnPage);
    }

    redirect_error($isPresentationDownload, 'send', $returnPage);
}

redirect_error($isPresentationDownload, 'notify', $returnPage);
