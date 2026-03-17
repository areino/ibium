#!/usr/bin/env php
<?php
declare(strict_types=1);

// Guard: CLI only
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "This script can only be run from the command line.\n";
    exit(1);
}

define('ROOT',     dirname(__DIR__));
define('DATA_DIR', ROOT . '/data');

require ROOT . '/src/JsonStore.php';

JsonStore::init(DATA_DIR);

// Argument validation
$argv = $argv ?? [];
if (count($argv) < 3) {
    echo "Usage: php bin/create-admin.php \"Full Name\" \"email@example.com\"\n";
    exit(1);
}

$name  = trim($argv[1]);
$email = trim($argv[2]);

if (!$name) {
    echo "Error: Name cannot be empty.\n";
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Error: Invalid email address.\n";
    exit(1);
}

// Helper to generate an ID
function newId(string $prefix = 'x'): string
{
    return $prefix . '-' . bin2hex(random_bytes(8));
}

$users = JsonStore::read('users.json');

// Check if user already exists (case-insensitive email match)
$existing = null;
foreach ($users as $u) {
    if (strtolower($u['email'] ?? '') === strtolower($email)) {
        $existing = $u;
        break;
    }
}

if ($existing) {
    // Upgrade to admin
    $existing['is_admin'] = true;
    JsonStore::upsert('users.json', $existing);
    echo "✅ Existing user upgraded to admin.\n";
    echo "   Name:  {$existing['name']}\n";
    echo "   Email: {$existing['email']}\n";
    echo "   ID:    {$existing['id']}\n";
    exit(0);
}

// Create new admin user
$user = [
    'id'       => newId('u'),
    'name'     => $name,
    'email'    => $email,
    'bands'    => [],
    'is_admin' => true,
];

JsonStore::upsert('users.json', $user);

echo "✅ Admin user created successfully.\n";
echo "   Name:  {$user['name']}\n";
echo "   Email: {$user['email']}\n";
echo "   ID:    {$user['id']}\n";
exit(0);
