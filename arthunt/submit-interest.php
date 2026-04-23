<?php
declare(strict_types=1);

function redirect_with_status(string $status, string $reason = ''): void
{
    $location = 'index.html?download=' . rawurlencode($status);

    if ($reason !== '') {
        $location .= '&reason=' . rawurlencode($reason);
    }

    $location .= '#presentation-download-form';

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

function load_mail_config(): array
{
    $config = [
        'from_email' => 'arthunt@parcacorp.com.br',
        'from_name' => 'Parca Corp',
        'to_email' => 'arthunt@parcacorp.com.br',
    ];

    $configFile = __DIR__ . DIRECTORY_SEPARATOR . 'smtp-config.php';

    if (is_file($configFile)) {
        $loaded = require $configFile;

        if (is_array($loaded)) {
            $config = array_merge($config, $loaded);
        }
    }

    foreach ([
        'from_email' => 'ARTHUNT_SMTP_FROM_EMAIL',
        'from_name' => 'ARTHUNT_SMTP_FROM_NAME',
        'to_email' => 'ARTHUNT_SMTP_TO_EMAIL',
    ] as $key => $envName) {
        $value = getenv($envName);

        if ($value !== false && $value !== '') {
            $config[$key] = $value;
        }
    }

    return $config;
}

function save_presentation_request(array $data): bool
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
        fputcsv($handle, ['timestamp', 'nome', 'email', 'whatsapp', 'empresa', 'perfil', 'comentario', 'ip']);
    }

    $written = fputcsv($handle, [
        date('c'),
        $data['nome'],
        $data['email'],
        $data['whatsapp'],
        $data['empresa'],
        $data['perfil'],
        $data['comentario'],
        $data['ip'],
    ]);

    fclose($handle);

    return $written !== false;
}

function append_error_log(string $message, array $context = []): void
{
    $directory = submission_directory();

    if ($directory === null) {
        return;
    }

    $lines = ['[' . date('c') . '] ' . $message];

    foreach ($context as $key => $value) {
        $lines[] = $key . ': ' . (string) $value;
    }

    $lines[] = str_repeat('-', 40);

    file_put_contents($directory . DIRECTORY_SEPARATOR . 'mail-error.log', implode(PHP_EOL, $lines) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function send_notification(array $config, string $subject, string $body, string $replyTo): bool
{
    if ($config['to_email'] === '' || $config['from_email'] === '') {
        throw new RuntimeException('Configuracao de e-mail incompleta.');
    }

    $headers = [
        'From: ' . $config['from_name'] . ' <' . $config['from_email'] . '>',
        'Reply-To: ' . $replyTo,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    return mail($config['to_email'], $subject, $body, implode("\r\n", $headers));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_status('error', 'send');
}

$nome = clean_text((string) ($_POST['nome'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$whatsapp = clean_text((string) ($_POST['whatsapp'] ?? ''));
$empresa = clean_text((string) ($_POST['empresa'] ?? ''));
$perfil = clean_text((string) ($_POST['perfil'] ?? ''));
$comentario = trim((string) ($_POST['comentario'] ?? ''));

if ($nome === '' || $email === '' || $whatsapp === '' || $empresa === '' || $perfil === '' || $comentario === '') {
    redirect_with_status('error', 'missing');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_with_status('error', 'invalid');
}

$emailHeader = str_replace(["\r", "\n"], '', $email);
$safeComment = trim(str_replace("\r", '', $comentario));
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'indisponivel');

$saved = save_presentation_request([
    'nome' => $nome,
    'email' => $emailHeader,
    'whatsapp' => $whatsapp,
    'empresa' => $empresa,
    'perfil' => $perfil,
    'comentario' => $safeComment,
    'ip' => $ip,
]);

if (!$saved) {
    redirect_with_status('error', 'send');
}

$config = load_mail_config();
$subject = encode_subject('Art Hunt - Solicitacao de PDF');
$message = implode("\n", [
    'Nova solicitacao do PDF do Art Hunt',
    '',
    'Nome: ' . $nome,
    'E-mail: ' . $emailHeader,
    'WhatsApp: ' . $whatsapp,
    'Empresa, instituicao ou cidade: ' . $empresa,
    'Perfil: ' . $perfil,
    'Comentario:',
    $safeComment !== '' ? $safeComment : 'Sem comentario.',
    '',
    'Origem: https://parcacorp.com.br/arthunt/',
    'IP: ' . $ip,
]);

try {
    if (!send_notification($config, $subject, $message, $emailHeader)) {
        throw new RuntimeException('A funcao mail() retornou falha.');
    }
} catch (Throwable $exception) {
    append_error_log('Falha ao enviar notificacao do Art Hunt.', [
        'error' => $exception->getMessage(),
        'to_email' => $config['to_email'] ?? '',
        'nome' => $nome,
        'email' => $emailHeader,
        'ip' => $ip,
    ]);

    redirect_with_status('error', 'notify');
}

redirect_with_status('success');
