<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Vilnius');

function environment(string $name): string
{
    $value = getenv($name);
    return $value === false ? '' : $value;
}

function storagePath(): string
{
    return dirname(__DIR__) . '/storage/last-db-success.json';
}

function lastSuccessfulConnection(): ?string
{
    $contents = @file_get_contents(storagePath());
    if ($contents === false) {
        return null;
    }

    $data = json_decode($contents, true);
    return is_array($data) && isset($data['at']) ? (string) $data['at'] : null;
}

function rememberSuccessfulConnection(string $time): void
{
    $directory = dirname(storagePath());
    if (!is_dir($directory)) {
        @mkdir($directory, 0775, true);
    }

    @file_put_contents(storagePath(), json_encode(['at' => $time], JSON_THROW_ON_ERROR));
}

$now = new DateTimeImmutable('now');
$webServer = gethostname() ?: php_uname('n');
$databaseStatus = 'Nepavyko prisijungti';
$databaseError = null;
$lastSuccess = lastSuccessfulConnection();

try {
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        environment('DB_HOST'),
        environment('DB_PORT'),
        environment('DB_NAME')
    );
    $pdo = new PDO($dsn, environment('DB_USER'), environment('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $clientKey = hash('sha256', $clientIp . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    $upsert = $pdo->prepare(
        'INSERT INTO request_clients (client_key) VALUES (:client_key)\n'
        . 'ON CONFLICT (client_key) DO UPDATE\n'
        . 'SET last_request_at = NOW(), request_count = request_clients.request_count + 1\n'
        . 'RETURNING id'
    );
    $upsert->execute(['client_key' => $clientKey]);
    $clientId = $upsert->fetchColumn();

    $log = $pdo->prepare(
        'INSERT INTO request_log (client_id, request_method, request_path, remote_address, web_server)\n'
        . 'VALUES (:client_id, :method, :path, :remote_address, :web_server)'
    );
    $log->execute([
        'client_id' => $clientId,
        'method' => $method,
        'path' => $path,
        'remote_address' => filter_var($clientIp, FILTER_VALIDATE_IP) ? $clientIp : null,
        'web_server' => $webServer,
    ]);

    $lastSuccess = $now->format(DATE_ATOM);
    rememberSuccessfulConnection($lastSuccess);
    $databaseStatus = 'OK';
} catch (Throwable $error) {
    $databaseError = $error->getMessage();
}
?>
<!doctype html>
<html lang="lt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PostgreSQL ryšio testas</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, sans-serif; }
        body { max-width: 760px; margin: 3rem auto; padding: 0 1rem; background: #f7f8fa; color: #17202a; }
        h1 { margin-bottom: 1.5rem; }
        .card { padding: 1.4rem; margin: 1rem 0; background: #fff; border: 1px solid #dfe3e8; border-radius: .7rem; box-shadow: 0 1px 2px #0000000d; }
        .status { font-size: 1.35rem; font-weight: 700; }
        .ok { color: #147a36; }
        .error { color: #b42318; }
        dt { font-weight: 700; margin-top: .65rem; }
        dd { margin: .15rem 0; font-family: ui-monospace, monospace; overflow-wrap: anywhere; }
        .hint { color: #58606a; }
    </style>
</head>
<body>
    <h1>PostgreSQL ryšio testas</h1>

    <section class="card">
        <h2>WEB užklausa</h2>
        <dl>
            <dt>Serveris / host'as</dt>
            <dd><?= htmlspecialchars($webServer, ENT_QUOTES, 'UTF-8') ?></dd>
            <dt>Dabartinė data ir laikas</dt>
            <dd><?= htmlspecialchars($now->format('Y-m-d H:i:s T'), ENT_QUOTES, 'UTF-8') ?></dd>
        </dl>
    </section>

    <section class="card">
        <h2>PostgreSQL prisijungimas</h2>
        <?php if ($databaseStatus === 'OK'): ?>
            <p class="status ok">✓ OK</p>
            <p class="hint">Prisijungta ir užklausa įrašyta į DB.</p>
        <?php else: ?>
            <p class="status error">✗ Nepavyko prisijungti</p>
            <dl>
                <dt>Paskutinis sėkmingas prisijungimas</dt>
                <dd><?= htmlspecialchars($lastSuccess ?? 'Nėra duomenų', ENT_QUOTES, 'UTF-8') ?></dd>
                <dt>Klaida</dt>
                <dd class="error"><?= htmlspecialchars($databaseError ?? 'Nežinoma klaida', ENT_QUOTES, 'UTF-8') ?></dd>
            </dl>
        <?php endif; ?>
    </section>
</body>
</html>
