<?php
declare(strict_types=1);

// ──────────────────────────────────────────────────────────────────────────────
// Bootstrap
// ──────────────────────────────────────────────────────────────────────────────
define('ROOT',       __DIR__);
define('DATA_DIR',   ROOT . '/data');
define('UPLOADS_DIR', ROOT . '/uploads');

// Constant defaults (config.local.php may override)
defined('APP_NAME')       or define('APP_NAME',       'Band Manager');
defined('MAIL_FROM')      or define('MAIL_FROM',      'noreply@example.com');
defined('MAIL_FROM_NAME') or define('MAIL_FROM_NAME', APP_NAME);
defined('SMTP_HOST')      or define('SMTP_HOST',      '');
defined('SMTP_PORT')      or define('SMTP_PORT',      587);
defined('SMTP_USER')      or define('SMTP_USER',      '');
defined('SMTP_PASS')      or define('SMTP_PASS',      '');

if (file_exists(ROOT . '/config.local.php'))       require ROOT . '/config.local.php';
if (file_exists(ROOT . '/vendor/autoload.php'))    require ROOT . '/vendor/autoload.php';

require ROOT . '/JsonStore.php';
require ROOT . '/Auth.php';

JsonStore::init(DATA_DIR);
session_start();

// ──────────────────────────────────────────────────────────────────────────────
// Helper functions
// ──────────────────────────────────────────────────────────────────────────────

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function render(string $template, array $vars = []): never
{
    global $user, $myBands;
    // Merge global user/myBands so layout/nav always have them
    $vars = array_merge(['user' => $user ?? [], 'myBands' => $myBands ?? []], $vars);
    extract($vars);
    $content_template = $template;
    require ROOT . '/layout.php';
    exit;
}

function json_out(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function newId(string $prefix = 'x'): string
{
    return $prefix . '-' . bin2hex(random_bytes(8));
}

function userBands(array $currentUser): array
{
    $bandIds = $currentUser['bands'] ?? [];
    if (empty($bandIds)) return [];
    $allBands = JsonStore::read('bands.json');
    return array_values(array_filter($allBands, fn($b) => in_array($b['id'] ?? '', $bandIds, true)));
}

function bandMemberIds(array $userBandIds): array
{
    if (empty($userBandIds)) return [];
    $allBands = JsonStore::read('bands.json');
    $ids = [];
    foreach ($allBands as $band) {
        if (in_array($band['id'] ?? '', $userBandIds, true)) {
            foreach ($band['members'] ?? [] as $mid) {
                $ids[] = $mid;
            }
        }
    }
    return array_values(array_unique($ids));
}

// ──────────────────────────────────────────────────────────────────────────────
// Routing — parse method + URI path
// ──────────────────────────────────────────────────────────────────────────────

$method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawUri  = $_SERVER['REQUEST_URI']    ?? '/';
$path    = strtok($rawUri, '?');          // strip query string
$path    = '/' . trim($path, '/');        // normalise

// Global user/bands (populated after auth for app routes)
$user    = null;
$myBands = [];

// ──────────────────────────────────────────────────────────────────────────────
// Auth routes (no session required)
// ──────────────────────────────────────────────────────────────────────────────

// GET /login
if ($method === 'GET' && $path === '/login') {
    $sent  = isset($_GET['sent']);
    $error = $_GET['error'] ?? null;
    require ROOT . '/login.php';
    exit;
}

// POST /login
if ($method === 'POST' && $path === '/login') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect('/login?error=invalid_email');
    }
    Auth::sendMagicLink($email); // always redirect to sent page (don't leak existence)
    redirect('/login?sent=1');
}

// GET /login/verify?token=...
if ($method === 'GET' && $path === '/login/verify') {
    $token = $_GET['token'] ?? '';
    if (!$token) {
        redirect('/login?error=invalid_token');
    }
    $verifiedUser = Auth::verifyToken($token);
    if ($verifiedUser === null) {
        redirect('/login?error=invalid_token');
    }
    Auth::login($verifiedUser);
    redirect('/');
}

// GET /logout
if ($method === 'GET' && $path === '/logout') {
    Auth::logout();
    redirect('/login');
}

// ──────────────────────────────────────────────────────────────────────────────
// All routes below require authentication
// ──────────────────────────────────────────────────────────────────────────────

$user    = Auth::currentUser();
if ($user === null) {
    redirect('/login');
}
$myBands = userBands($user);

// ──────────────────────────────────────────────────────────────────────────────
// App routes
// ──────────────────────────────────────────────────────────────────────────────

// GET /
if ($method === 'GET' && $path === '/') {
    render('calendar', ['userBands' => $myBands]);
}

// GET /band/{id}
if ($method === 'GET' && preg_match('#^/band/([^/]+)$#', $path, $m)) {
    $bandId = $m[1];
    $band   = JsonStore::findById('bands.json', $bandId);
    if ($band === null) {
        http_response_code(404);
        echo '404 Band not found';
        exit;
    }
    // Must be member or admin
    $isMember = in_array($bandId, $user['bands'] ?? [], true);
    if (!$isMember && empty($user['is_admin'])) {
        http_response_code(403);
        echo '403 Forbidden';
        exit;
    }
    // Resolve member objects
    $allUsers = JsonStore::read('users.json');
    $members  = array_values(array_filter($allUsers, fn($u) => in_array($u['id'], $band['members'] ?? [], true)));
    render('band', ['band' => $band, 'members' => $members]);
}

// GET /admin
if ($method === 'GET' && $path === '/admin') {
    Auth::requireAdmin();
    $allUsers = JsonStore::read('users.json');
    $allBands = JsonStore::read('bands.json');
    render('admin', ['users' => $allUsers, 'bands' => $allBands]);
}

// ──────────────────────────────────────────────────────────────────────────────
// API: Events
// ──────────────────────────────────────────────────────────────────────────────

// GET /api/events
if ($method === 'GET' && $path === '/api/events') {
    $allEvents  = JsonStore::read('events.json');
    $allBands   = JsonStore::read('bands.json');
    $allUsers   = JsonStore::read('users.json');

    $userBandIds   = $user['bands'] ?? [];
    $memberUserIds = bandMemberIds($userBandIds);

    $start         = $_GET['start']   ?? null;
    $end           = $_GET['end']     ?? null;
    $filterBandId  = $_GET['band_id'] ?? null;

    $colors = [
        'gig'      => '#E24B4A',
        'practice' => '#378ADD',
        'block'    => '#888780',
    ];
    $emojis = [
        'gig'      => '🎸',
        'practice' => '🥁',
        'block'    => '🚫',
    ];

    $output = [];
    foreach ($allEvents as $ev) {
        $type = $ev['type'] ?? '';

        // Visibility check
        if (in_array($type, ['gig', 'practice'], true)) {
            if (!in_array($ev['band_id'] ?? '', $userBandIds, true)) continue;
        } elseif ($type === 'block') {
            if (!in_array($ev['user_id'] ?? '', $memberUserIds, true)) continue;
        } else {
            continue;
        }

        // Date range filter
        if ($start && ($ev['datetime'] ?? '') < $start) continue;
        if ($end   && ($ev['datetime'] ?? '') > $end)   continue;

        // Band filter
        if ($filterBandId && ($ev['band_id'] ?? '') !== $filterBandId) continue;

        // Build title
        $bandName = null;
        $userName = null;
        if (!empty($ev['band_id'])) {
            foreach ($allBands as $b) {
                if ($b['id'] === $ev['band_id']) { $bandName = $b['name']; break; }
            }
        }
        if (!empty($ev['user_id'])) {
            foreach ($allUsers as $u) {
                if ($u['id'] === $ev['user_id']) { $userName = $u['name']; break; }
            }
        }

        $emoji  = $emojis[$type]  ?? '';
        $color  = $colors[$type]  ?? '#888';
        $titleParts = [$emoji];
        if ($bandName) $titleParts[] = $bandName;
        if (!empty($ev['location'])) $titleParts[] = '@ ' . $ev['location'];
        $title = implode(' ', $titleParts);

        $isOwn = ($ev['created_by'] ?? '') === $user['id'] || !empty($user['is_admin']);

        $output[] = [
            'id'              => $ev['id'],
            'title'           => $title,
            'start'           => $ev['datetime'],
            'end'             => $ev['datetime_end'] ?? null,
            'backgroundColor' => $color,
            'borderColor'     => $color,
            'extendedProps'   => [
                'type'      => $type,
                'band_id'   => $ev['band_id']   ?? null,
                'user_id'   => $ev['user_id']   ?? null,
                'band_name' => $bandName,
                'user_name' => $userName,
                'location'  => $ev['location']  ?? null,
                'comments'  => $ev['comments']  ?? null,
                'is_own'    => $isOwn,
            ],
        ];
    }
    json_out($output);
}

// POST /api/events  (create)
if ($method === 'POST' && $path === '/api/events') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $type = $body['type'] ?? '';
    if (!in_array($type, ['gig', 'practice', 'block'], true)) {
        json_out(['error' => 'Invalid event type.'], 422);
    }
    if (empty($body['datetime'])) {
        json_out(['error' => 'datetime is required.'], 422);
    }

    $event = [
        'id'           => newId('e'),
        'type'         => $type,
        'band_id'      => ($type !== 'block') ? ($body['band_id']    ?? null) : null,
        'user_id'      => ($type === 'block') ? $user['id']           : null,
        'datetime'     => $body['datetime']     ?? '',
        'datetime_end' => $body['datetime_end'] ?? null,
        'location'     => $body['location']     ?? null,
        'comments'     => $body['comments']     ?? null,
        'created_by'   => $user['id'],
        'created_at'   => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
    ];

    // Validate band membership for gig/practice
    if ($type !== 'block' && !empty($event['band_id'])) {
        if (!in_array($event['band_id'], $user['bands'] ?? [], true) && empty($user['is_admin'])) {
            json_out(['error' => 'Not a member of this band.'], 403);
        }
    }

    JsonStore::upsert('events.json', $event);
    json_out(['id' => $event['id']], 201);
}

// POST /api/events/{id}  (update)
if ($method === 'POST' && preg_match('#^/api/events/([^/]+)$#', $path, $m)) {
    $eventId = $m[1];
    $ev      = JsonStore::findById('events.json', $eventId);
    if ($ev === null) json_out(['error' => 'Event not found.'], 404);

    $isOwner = ($ev['created_by'] ?? '') === $user['id'];
    if (!$isOwner && empty($user['is_admin'])) {
        json_out(['error' => 'Forbidden.'], 403);
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $type = $body['type'] ?? $ev['type'];
    if (!in_array($type, ['gig', 'practice', 'block'], true)) {
        json_out(['error' => 'Invalid event type.'], 422);
    }

    $ev['type']         = $type;
    $ev['band_id']      = ($type !== 'block') ? ($body['band_id']    ?? $ev['band_id'])    : null;
    $ev['user_id']      = ($type === 'block') ? $user['id']                                : null;
    $ev['datetime']     = $body['datetime']     ?? $ev['datetime'];
    $ev['datetime_end'] = $body['datetime_end'] ?? null;
    $ev['location']     = $body['location']     ?? null;
    $ev['comments']     = $body['comments']     ?? null;

    JsonStore::upsert('events.json', $ev);
    json_out(['ok' => true]);
}

// POST /api/events/{id}/delete
if ($method === 'POST' && preg_match('#^/api/events/([^/]+)/delete$#', $path, $m)) {
    $eventId = $m[1];
    $ev      = JsonStore::findById('events.json', $eventId);
    if ($ev === null) json_out(['error' => 'Event not found.'], 404);

    $isOwner = ($ev['created_by'] ?? '') === $user['id'];
    if (!$isOwner && empty($user['is_admin'])) {
        json_out(['error' => 'Forbidden.'], 403);
    }

    JsonStore::delete('events.json', $eventId);
    json_out(['ok' => true]);
}

// ──────────────────────────────────────────────────────────────────────────────
// API: Bands
// ──────────────────────────────────────────────────────────────────────────────

// POST /api/bands/{id}  (update band info)
if ($method === 'POST' && preg_match('#^/api/bands/([^/]+)$#', $path, $m)) {
    $bandId = $m[1];
    $band   = JsonStore::findById('bands.json', $bandId);
    if ($band === null) json_out(['error' => 'Band not found.'], 404);

    $isMember = in_array($bandId, $user['bands'] ?? [], true);
    if (!$isMember && empty($user['is_admin'])) {
        json_out(['error' => 'Forbidden.'], 403);
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $allowed = ['name', 'website', 'instagram', 'youtube', 'facebook'];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $body)) {
            $band[$field] = $body[$field] ?? null;
        }
    }

    JsonStore::upsert('bands.json', $band);
    json_out(['ok' => true]);
}

// POST /api/bands/{id}/upload/epk
// POST /api/bands/{id}/upload/dossier
if ($method === 'POST' && preg_match('#^/api/bands/([^/]+)/upload/(epk|dossier)$#', $path, $m)) {
    $bandId   = $m[1];
    $fileType = $m[2]; // 'epk' or 'dossier'
    $band     = JsonStore::findById('bands.json', $bandId);
    if ($band === null) json_out(['error' => 'Band not found.'], 404);

    $isMember = in_array($bandId, $user['bands'] ?? [], true);
    if (!$isMember && empty($user['is_admin'])) {
        json_out(['error' => 'Forbidden.'], 403);
    }

    if (empty($_FILES['file'])) {
        json_out(['error' => 'No file uploaded.'], 422);
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_out(['error' => 'Upload error code: ' . $file['error']], 422);
    }

    // Validate size (20 MB)
    $maxBytes = 20 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        json_out(['error' => 'File exceeds 20 MB limit.'], 422);
    }

    // Validate MIME type via finfo
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if ($mimeType !== 'application/pdf') {
        json_out(['error' => 'Only PDF files are allowed.'], 422);
    }

    // Ensure target directory exists
    $bandUploadDir = UPLOADS_DIR . DIRECTORY_SEPARATOR . $bandId;
    if (!is_dir($bandUploadDir)) {
        mkdir($bandUploadDir, 0750, true);
    }

    $filename    = $fileType . '.pdf';
    $destination = $bandUploadDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        json_out(['error' => 'Failed to store uploaded file.'], 500);
    }

    $relativePath = $bandId . '/' . $filename;
    $band[$fileType . '_file'] = $relativePath;
    JsonStore::upsert('bands.json', $band);

    json_out(['ok' => true, 'path' => $relativePath]);
}

// GET /uploads/{band_id}/{filename}  (auth-gated file serve)
if ($method === 'GET' && preg_match('#^/uploads/([^/]+)/([^/]+)$#', $path, $m)) {
    $bandId   = $m[1];
    $filename = $m[2];

    // Sanitise filename — only allow alphanumeric, dash, underscore, dot
    if (!preg_match('/^[a-zA-Z0-9._\-]+$/', $filename)) {
        http_response_code(400);
        echo 'Invalid filename.';
        exit;
    }

    $band = JsonStore::findById('bands.json', $bandId);
    if ($band === null) {
        http_response_code(404);
        echo 'Band not found.';
        exit;
    }

    // Membership check
    $isMember = in_array($bandId, $user['bands'] ?? [], true);
    if (!$isMember && empty($user['is_admin'])) {
        http_response_code(403);
        echo 'Forbidden.';
        exit;
    }

    $filePath = UPLOADS_DIR . DIRECTORY_SEPARATOR . $bandId . DIRECTORY_SEPARATOR . $filename;
    // Prevent directory traversal
    $realPath = realpath($filePath);
    $realUploads = realpath(UPLOADS_DIR);
    if ($realPath === false || $realUploads === false || strpos($realPath, $realUploads) !== 0) {
        http_response_code(403);
        echo 'Forbidden.';
        exit;
    }

    if (!file_exists($realPath)) {
        http_response_code(404);
        echo 'File not found.';
        exit;
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . addslashes($filename) . '"');
    header('Content-Length: ' . filesize($realPath));
    readfile($realPath);
    exit;
}

// ──────────────────────────────────────────────────────────────────────────────
// API: Admin — Users
// ──────────────────────────────────────────────────────────────────────────────

// POST /api/admin/users  (create or update user)
if ($method === 'POST' && $path === '/api/admin/users') {
    Auth::requireAdmin();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $name    = trim($body['name']    ?? '');
    $email   = trim($body['email']   ?? '');
    $bands   = is_array($body['bands'] ?? null) ? $body['bands'] : [];
    $isAdmin = !empty($body['is_admin']);

    if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_out(['error' => 'Name and valid email are required.'], 422);
    }

    $allUsers = JsonStore::read('users.json');
    $allBands = JsonStore::read('bands.json');

    // Find existing user by ID (edit) or email (create conflict check)
    $existingId   = $body['id'] ?? null;
    $existingUser = null;
    foreach ($allUsers as $u) {
        if ($existingId && $u['id'] === $existingId) {
            $existingUser = $u;
            break;
        }
        if (!$existingId && strtolower($u['email']) === strtolower($email)) {
            $existingUser = $u;
            break;
        }
    }

    if ($existingUser) {
        $existingUser['name']     = $name;
        $existingUser['email']    = $email;
        $existingUser['bands']    = $bands;
        $existingUser['is_admin'] = $isAdmin;
        $savedUser = $existingUser;
    } else {
        $savedUser = [
            'id'       => newId('u'),
            'name'     => $name,
            'email'    => $email,
            'bands'    => $bands,
            'is_admin' => $isAdmin,
        ];
    }

    JsonStore::upsert('users.json', $savedUser);

    // Sync bands.json membership arrays bidirectionally
    $userId = $savedUser['id'];
    foreach ($allBands as &$b) {
        $currentMembers = $b['members'] ?? [];
        $shouldBeMember = in_array($b['id'], $bands, true);
        $isMemberNow    = in_array($userId, $currentMembers, true);

        if ($shouldBeMember && !$isMemberNow) {
            $b['members'][] = $userId;
        } elseif (!$shouldBeMember && $isMemberNow) {
            $b['members'] = array_values(array_filter($currentMembers, fn($id) => $id !== $userId));
        }
    }
    unset($b);
    JsonStore::write('bands.json', $allBands);

    json_out(['ok' => true, 'id' => $userId]);
}

// POST /api/admin/users/{id}/delete
if ($method === 'POST' && preg_match('#^/api/admin/users/([^/]+)/delete$#', $path, $m)) {
    Auth::requireAdmin();
    $deleteId = $m[1];

    if ($deleteId === $user['id']) {
        json_out(['error' => 'Cannot delete yourself.'], 422);
    }

    JsonStore::delete('users.json', $deleteId);

    // Remove user from all band member arrays
    $allBands = JsonStore::read('bands.json');
    foreach ($allBands as &$b) {
        $b['members'] = array_values(array_filter($b['members'] ?? [], fn($id) => $id !== $deleteId));
    }
    unset($b);
    JsonStore::write('bands.json', $allBands);

    json_out(['ok' => true]);
}

// ──────────────────────────────────────────────────────────────────────────────
// API: Admin — Bands
// ──────────────────────────────────────────────────────────────────────────────

// POST /api/admin/bands  (create band)
if ($method === 'POST' && $path === '/api/admin/bands') {
    Auth::requireAdmin();
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $name    = trim($body['name'] ?? '');
    $members = is_array($body['members'] ?? null) ? $body['members'] : [];

    if (!$name) {
        json_out(['error' => 'Band name is required.'], 422);
    }

    $band = [
        'id'           => newId('b'),
        'name'         => $name,
        'website'      => null,
        'instagram'    => null,
        'youtube'      => null,
        'facebook'     => null,
        'members'      => $members,
        'epk_file'     => null,
        'dossier_file' => null,
    ];
    JsonStore::upsert('bands.json', $band);

    // Sync users.json — add this band to each member's bands array
    $allUsers = JsonStore::read('users.json');
    foreach ($allUsers as &$u) {
        if (in_array($u['id'], $members, true)) {
            if (!in_array($band['id'], $u['bands'] ?? [], true)) {
                $u['bands'][] = $band['id'];
            }
        }
    }
    unset($u);
    JsonStore::write('users.json', $allUsers);

    json_out(['ok' => true, 'id' => $band['id']], 201);
}

// POST /api/admin/bands/{id}/delete
if ($method === 'POST' && preg_match('#^/api/admin/bands/([^/]+)/delete$#', $path, $m)) {
    Auth::requireAdmin();
    $bandId = $m[1];

    JsonStore::delete('bands.json', $bandId);

    // Remove band from all user bands arrays
    $allUsers = JsonStore::read('users.json');
    foreach ($allUsers as &$u) {
        $u['bands'] = array_values(array_filter($u['bands'] ?? [], fn($id) => $id !== $bandId));
    }
    unset($u);
    JsonStore::write('users.json', $allUsers);

    json_out(['ok' => true]);
}

// ──────────────────────────────────────────────────────────────────────────────
// 404 fallback
// ──────────────────────────────────────────────────────────────────────────────
http_response_code(404);
echo '<!DOCTYPE html><html><body><h1>404 Not Found</h1></body></html>';
exit;
