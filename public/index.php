<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Vilnius');

$language = $_COOKIE['site_language'] ?? 'lt';
if (!in_array($language, ['lt', 'en'], true)) {
    $language = 'lt';
}

function translate(string $key): string
{
    global $language;

    static $translations = [
        'lt' => [
            'page_title' => 'PostgreSQL ryšio testas',
            'language' => 'Kalba',
            'web_request' => 'WEB užklausa',
            'server_host' => "Serveris / host'as",
            'current_datetime' => 'Dabartinė data ir laikas',
            'postgres_connection' => 'PostgreSQL prisijungimas',
            'last_db_entry' => 'Paskutinis įrašas į DB',
            'no_data' => 'Nėra duomenų',
            'db_node' => 'DB node',
            'not_specified' => 'Nenurodyta',
            'postgres_hostname' => 'PostgreSQL server hostname',
            'connected_and_logged' => 'Prisijungta ir užklausa įrašyta į DB.',
            'connection_failed' => 'Nepavyko prisijungti',
            'error' => 'Klaida',
            'last_connection_file' => 'Paskutinio prisijungimo failas',
            'file_open_failed' => 'Nepavyko atidaryti `last-db-success.json`',
            'unknown_error' => 'Nežinoma klaida',
            'storage_directory_failed' => 'Nepavyko sukurti aplikacijos saugyklos katalogo.',
            'storage_save_failed' => 'Nepavyko išsaugoti paskutinio DB įrašo informacijos.',
            'schema_empty' => 'PostgreSQL schemos pavadinimas negali būti tuščias.',
            'missing_db_result' => 'DB negrąžino paskutinio įrašo informacijos.',
        ],
        'en' => [
            'page_title' => 'PostgreSQL connection test',
            'language' => 'Language',
            'web_request' => 'WEB request',
            'server_host' => 'Server / host',
            'current_datetime' => 'Current date and time',
            'postgres_connection' => 'PostgreSQL connection',
            'last_db_entry' => 'Last entry in the database',
            'no_data' => 'No data',
            'db_node' => 'DB node',
            'not_specified' => 'Not specified',
            'postgres_hostname' => 'PostgreSQL server hostname',
            'connected_and_logged' => 'Connected and request logged in the database.',
            'connection_failed' => 'Connection failed',
            'error' => 'Error',
            'last_connection_file' => 'Last connection file',
            'file_open_failed' => 'Could not open `last-db-success.json`',
            'unknown_error' => 'Unknown error',
            'storage_directory_failed' => 'Could not create the application storage directory.',
            'storage_save_failed' => 'Could not save the last database entry information.',
            'schema_empty' => 'The PostgreSQL schema name cannot be empty.',
            'missing_db_result' => 'The database did not return the last entry information.',
        ],
    ];

    return $translations[$language][$key] ?? $translations['lt'][$key] ?? $key;
}

function appRootPath(): string
{
    return dirname(__DIR__);
}

function loadDotenv(string $path): array
{
    $data = [];
    if (!is_file($path) || !is_readable($path)) {
        return $data;
    }

    $contents = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($contents === false) {
        return $data;
    }

    foreach ($contents as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value);

        if ($key === '') {
            continue;
        }

        if (str_starts_with($value, '"') && str_ends_with($value, '"')
            || str_starts_with($value, "'") && str_ends_with($value, "'")) {
            $value = substr($value, 1, -1);
        }

        $data[$key] = $value;
    }

    return $data;
}

function environment(string $name, ?string $default = null): string
{
    static $dotenv;

    if ($dotenv === null) {
        $dotenv = loadDotenv(appRootPath() . '/.env');
    }

    $value = getenv($name);
    if ($value !== false && $value !== '') {
        return $value;
    }

    if (isset($dotenv[$name]) && $dotenv[$name] !== '') {
        return $dotenv[$name];
    }

    return $default ?? '';
}

function storagePath(): string
{
    return appRootPath() . '/storage/last-db-success.json';
}

function quotePgIdentifier(string $identifier): string
{
    $identifier = trim($identifier);
    if ($identifier === '') {
        throw new InvalidArgumentException(translate('schema_empty'));
    }

    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
        return $identifier;
    }

    return '"' . str_replace('"', '""', $identifier) . '"';
}

function lastSuccessfulDbInsert(?string &$storageError = null): ?array
{
    $storageError = null;
    $contents = @file_get_contents(storagePath());
    if ($contents === false) {
        $error = error_get_last();
        $storageError = $error['message'] ?? translate('file_open_failed');
        return null;
    }

    $data = json_decode($contents, true);
    if (!is_array($data) || !isset($data['at'])) {
        return null;
    }

    return [
        'at' => (string) $data['at'],
        'node' => isset($data['node']) ? (string) $data['node'] : null,
        'hostname' => isset($data['hostname']) ? (string) $data['hostname'] : null,
    ];
}

function rememberSuccessfulDbInsert(
    string $time,
    string $node,
    string $hostname,
    ?string &$storageError = null
): bool
{
    $storageError = null;
    $directory = dirname(storagePath());
    if (!is_dir($directory)) {
        if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
            $storageError = translate('storage_directory_failed');
            return false;
        }
    }

    try {
        $contents = json_encode([
            'at' => $time,
            'node' => $node,
            'hostname' => $hostname,
        ], JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        $storageError = $error->getMessage();
        return false;
    }

    if (@file_put_contents(storagePath(), $contents, LOCK_EX) === false) {
        $error = error_get_last();
        $storageError = $error['message'] ?? translate('storage_save_failed');
        return false;
    }

    return true;
}

$now = new DateTimeImmutable('now');
$webServer = gethostname() ?: php_uname('n');
$databaseConnected = false;
$databaseError = null;
$storageError = null;
$lastDbInsert = lastSuccessfulDbInsert($storageError);

try {
    $dbHost = environment('DB_HOST', environment('POSTGRES_HOST', '127.0.0.1'));
    $dbPort = environment('DB_PORT', environment('POSTGRES_PORT', '5432'));
    $dbName = environment('DB_NAME', environment('POSTGRES_DB', 'web_db_play'));
    $dbSchema = environment('DB_SCHEMA', environment('POSTGRES_SCHEMA', 'public'));
    $dbUser = environment('DB_USER', environment('POSTGRES_USER', 'web_app'));
    $dbPassword = environment('DB_PASSWORD', environment('POSTGRES_PASSWORD', ''));

    $quotedSchema = quotePgIdentifier($dbSchema);
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $dbHost,
        $dbPort,
        $dbName
    );
    $pdo = new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->beginTransaction();

    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $clientKey = hash('sha256', $clientIp . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    $upsertSql = sprintf(
        "INSERT INTO %s.request_clients (client_key) VALUES (:client_key)\n"
        . "ON CONFLICT (client_key) DO UPDATE\n"
        . "SET last_request_at = NOW(), request_count = %s.request_clients.request_count + 1\n"
        . "RETURNING id",
        $quotedSchema,
        $quotedSchema
    );

    $upsert = $pdo->prepare($upsertSql);
    $upsert->execute(['client_key' => $clientKey]);
    $clientId = $upsert->fetchColumn();

    $logSql = sprintf(
        "INSERT INTO %s.request_log (client_id, request_method, request_path, remote_address, web_server, db_node, db_hostname)\n"
        . "VALUES (:client_id, :method, :path, :remote_address, :web_server,\n"
        . "COALESCE(NULLIF(current_setting('app.node_name', true), ''), inet_server_addr()::text, 'unknown'),\n"
        . "COALESCE(NULLIF(current_setting('app.node_hostname', true), ''), inet_server_addr()::text, 'unknown'))\n"
        . "RETURNING requested_at, db_node, db_hostname",
        $quotedSchema
    );

    $log = $pdo->prepare($logSql);
    $log->execute([
        'client_id' => $clientId,
        'method' => $method,
        'path' => $path,
        'remote_address' => filter_var($clientIp, FILTER_VALIDATE_IP) ? $clientIp : null,
        'web_server' => $webServer,
    ]);

    $dbInsert = $log->fetch();
    if (!is_array($dbInsert)
        || !isset($dbInsert['requested_at'], $dbInsert['db_node'], $dbInsert['db_hostname'])) {
        throw new RuntimeException(translate('missing_db_result'));
    }

    $pdo->commit();
    $lastDbInsert = [
        'at' => (string) $dbInsert['requested_at'],
        'node' => (string) $dbInsert['db_node'],
        'hostname' => (string) $dbInsert['db_hostname'],
    ];
    rememberSuccessfulDbInsert(
        $lastDbInsert['at'],
        $lastDbInsert['node'],
        $lastDbInsert['hostname'],
        $storageError
    );
    $databaseConnected = true;
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $databaseError = $error->getMessage();
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars($language, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(translate('page_title'), ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        :root { color-scheme: light; font-family: system-ui, sans-serif; }
        body { max-width: 760px; margin: 3rem auto; padding: 0 1rem; background: #f7f8fa; color: #17202a; }
        .topbar { display: flex; justify-content: flex-end; align-items: center; gap: .65rem; margin-bottom: 1.25rem; }
        .language-label { color: #58606a; font-size: .9rem; }
        .language-switcher { display: inline-flex; gap: .2rem; padding: .2rem; background: #e8ebef; border-radius: .5rem; }
        .language-button { border: 0; border-radius: .3rem; padding: .35rem .65rem; background: transparent; color: #58606a; cursor: pointer; font: inherit; font-size: .85rem; font-weight: 700; }
        .language-button:hover, .language-button:focus-visible { color: #17202a; }
        .language-button.active { background: #fff; color: #17202a; box-shadow: 0 1px 2px #0000001a; }
        h1 { margin: 0 0 1.5rem; }
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
    <div class="topbar">
        <span class="language-label"><?= htmlspecialchars(translate('language'), ENT_QUOTES, 'UTF-8') ?>:</span>
        <div class="language-switcher" aria-label="<?= htmlspecialchars(translate('language'), ENT_QUOTES, 'UTF-8') ?>">
            <button class="language-button<?= $language === 'lt' ? ' active' : '' ?>" type="button" data-language="lt" aria-pressed="<?= $language === 'lt' ? 'true' : 'false' ?>">LT</button>
            <button class="language-button<?= $language === 'en' ? ' active' : '' ?>" type="button" data-language="en" aria-pressed="<?= $language === 'en' ? 'true' : 'false' ?>">EN</button>
        </div>
    </div>

    <h1><?= htmlspecialchars(translate('page_title'), ENT_QUOTES, 'UTF-8') ?></h1>

    <section class="card">
        <h2><?= htmlspecialchars(translate('web_request'), ENT_QUOTES, 'UTF-8') ?></h2>
        <dl>
            <dt><?= htmlspecialchars(translate('server_host'), ENT_QUOTES, 'UTF-8') ?></dt>
            <dd><?= htmlspecialchars($webServer, ENT_QUOTES, 'UTF-8') ?></dd>
            <dt><?= htmlspecialchars(translate('current_datetime'), ENT_QUOTES, 'UTF-8') ?></dt>
            <dd><?= htmlspecialchars($now->format('Y-m-d H:i:s T'), ENT_QUOTES, 'UTF-8') ?></dd>
        </dl>
    </section>

    <section class="card">
        <h2><?= htmlspecialchars(translate('postgres_connection'), ENT_QUOTES, 'UTF-8') ?></h2>
        <?php if ($databaseConnected): ?>
            <p class="status ok">✓ OK</p>
            <dl>
                <dt><?= htmlspecialchars(translate('last_db_entry'), ENT_QUOTES, 'UTF-8') ?></dt>
                <dd><?= htmlspecialchars($lastDbInsert['at'] ?? translate('no_data'), ENT_QUOTES, 'UTF-8') ?></dd>
                <dt><?= htmlspecialchars(translate('db_node'), ENT_QUOTES, 'UTF-8') ?></dt>
                <dd><?= htmlspecialchars($lastDbInsert['node'] ?? translate('not_specified'), ENT_QUOTES, 'UTF-8') ?></dd>
                <dt><?= htmlspecialchars(translate('postgres_hostname'), ENT_QUOTES, 'UTF-8') ?></dt>
                <dd><?= htmlspecialchars($lastDbInsert['hostname'] ?? translate('not_specified'), ENT_QUOTES, 'UTF-8') ?></dd>
            </dl>
            <p class="hint"><?= htmlspecialchars(translate('connected_and_logged'), ENT_QUOTES, 'UTF-8') ?></p>
        <?php else: ?>
            <p class="status error">✗ <?= htmlspecialchars(translate('connection_failed'), ENT_QUOTES, 'UTF-8') ?></p>
            <dl>
                <dt><?= htmlspecialchars(translate('last_db_entry'), ENT_QUOTES, 'UTF-8') ?></dt>
                <dd><?= htmlspecialchars($lastDbInsert['at'] ?? translate('no_data'), ENT_QUOTES, 'UTF-8') ?></dd>
                <dt><?= htmlspecialchars(translate('db_node'), ENT_QUOTES, 'UTF-8') ?></dt>
                <dd><?= htmlspecialchars($lastDbInsert['node'] ?? translate('not_specified'), ENT_QUOTES, 'UTF-8') ?></dd>
                <dt><?= htmlspecialchars(translate('postgres_hostname'), ENT_QUOTES, 'UTF-8') ?></dt>
                <dd><?= htmlspecialchars($lastDbInsert['hostname'] ?? translate('not_specified'), ENT_QUOTES, 'UTF-8') ?></dd>
                <dt><?= htmlspecialchars(translate('error'), ENT_QUOTES, 'UTF-8') ?></dt>
                <dd class="error"><?= htmlspecialchars($databaseError ?? translate('unknown_error'), ENT_QUOTES, 'UTF-8') ?></dd>
            </dl>
        <?php endif; ?>
    </section>

    <?php if ($storageError !== null): ?>
        <section class="card">
            <h2><?= htmlspecialchars(translate('last_connection_file'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="status error">✗ <?= htmlspecialchars(translate('file_open_failed'), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="error"><?= htmlspecialchars($storageError, ENT_QUOTES, 'UTF-8') ?></p>
        </section>
    <?php endif; ?>

    <script>
        (() => {
            const storageKey = 'webDbPlayLanguage';
            const serverLanguage = <?= json_encode($language, JSON_THROW_ON_ERROR) ?>;
            const supportedLanguages = ['lt', 'en'];
            const savedLanguage = window.localStorage.getItem(storageKey);

            function rememberLanguage(language) {
                window.localStorage.setItem(storageKey, language);
                document.cookie = `site_language=${language}; path=/; max-age=31536000; samesite=lax`;
            }

            if (supportedLanguages.includes(savedLanguage) && savedLanguage !== serverLanguage) {
                rememberLanguage(savedLanguage);
                window.location.reload();
                return;
            }

            document.querySelectorAll('[data-language]').forEach((button) => {
                button.addEventListener('click', () => {
                    const language = button.dataset.language;
                    if (supportedLanguages.includes(language) && language !== serverLanguage) {
                        rememberLanguage(language);
                        window.location.reload();
                    }
                });
            });

            if (supportedLanguages.includes(serverLanguage) && savedLanguage !== serverLanguage) {
                window.localStorage.setItem(storageKey, serverLanguage);
            }
        })();
    </script>
</body>
</html>
