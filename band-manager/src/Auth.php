<?php
declare(strict_types=1);

class Auth
{
    public static function currentUser(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return null;
        }
        return JsonStore::findById('users.json', $userId);
    }

    public static function require(): array
    {
        $user = self::currentUser();
        if ($user === null) {
            header('Location: /login');
            exit;
        }
        return $user;
    }

    public static function requireAdmin(): array
    {
        $user = self::require();
        if (empty($user['is_admin'])) {
            http_response_code(403);
            echo '403 Forbidden';
            exit;
        }
        return $user;
    }

    public static function sendMagicLink(string $email): bool
    {
        // Find user case-insensitively
        $users = JsonStore::read('users.json');
        $found = null;
        foreach ($users as $u) {
            if (strtolower($u['email'] ?? '') === strtolower(trim($email))) {
                $found = $u;
                break;
            }
        }

        // Silent fail — don't reveal whether the email is registered
        if ($found === null) {
            return false;
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = (new DateTimeImmutable('+1 hour'))->format(DateTimeInterface::ATOM);

        $session = [
            'id'         => $token,
            'token'      => $token,
            'user_id'    => $found['id'],
            'expires_at' => $expiresAt,
            'used'       => false,
        ];
        JsonStore::upsert('sessions.json', $session);

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $link   = "$scheme://$host/login/verify?token=$token";

        $subject = 'Your ' . (defined('APP_NAME') ? APP_NAME : 'Band Manager') . ' login link';
        $body    = "Click the link below to log in (expires in 1 hour):\n\n$link\n\nIf you did not request this, please ignore this email.";

        $fromEmail = defined('MAIL_FROM') ? MAIL_FROM : 'noreply@example.com';
        $fromName  = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Band Manager';
        $smtpHost  = defined('SMTP_HOST') ? SMTP_HOST : '';

        if ($smtpHost !== '') {
            return self::sendViaSMTP($found['email'], $found['name'] ?? '', $fromEmail, $fromName, $subject, $body);
        }

        // Fallback to PHP mail()
        $headers = "From: $fromName <$fromEmail>\r\nContent-Type: text/plain; charset=UTF-8";
        return mail($found['email'], $subject, $body, $headers);
    }

    private static function sendViaSMTP(
        string $toEmail,
        string $toName,
        string $fromEmail,
        string $fromName,
        string $subject,
        string $body
    ): bool {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log('PHPMailer error: ' . $e->getMessage());
            return false;
        }
    }

    public static function verifyToken(string $token): ?array
    {
        $sessions = JsonStore::read('sessions.json');
        $found    = null;
        foreach ($sessions as $s) {
            if (
                ($s['token'] ?? '') === $token &&
                ($s['used'] ?? true) === false &&
                strtotime($s['expires_at'] ?? '0') > time()
            ) {
                $found = $s;
                break;
            }
        }

        if ($found === null) {
            return null;
        }

        // Mark as used
        $found['used'] = true;
        JsonStore::upsert('sessions.json', $found);

        return JsonStore::findById('users.json', $found['user_id']);
    }

    public static function login(array $user): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
    }

    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    public static function cleanupSessions(): void
    {
        $sessions = JsonStore::read('sessions.json');
        $now      = time();
        $sessions = array_values(array_filter($sessions, function ($s) use ($now) {
            // Keep sessions that are not used AND not expired
            return !($s['used'] ?? false) && strtotime($s['expires_at'] ?? '0') > $now;
        }));
        JsonStore::write('sessions.json', $sessions);
    }
}
