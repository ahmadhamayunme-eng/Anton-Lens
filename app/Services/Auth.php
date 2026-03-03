<?php
namespace App\Services;

use PDO;

class Auth {
    public static function user(PDO $pdo): ?array {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => (int) $_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    }

    public static function login(PDO $pdo, array $user): void {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $rawToken = bin2hex(random_bytes(32));
        $_SESSION['session_token'] = $rawToken;
        $stmt = $pdo->prepare('INSERT INTO sessions (user_id, session_token_hash, ip, user_agent, expires_at, created_at) VALUES (:u,:h,:ip,:ua,:exp,NOW())');
        $stmt->execute([
            'u' => (int) $user['id'],
            'h' => hash('sha256', $rawToken),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'exp' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);
    }

    public static function logout(PDO $pdo): void {
        if (!empty($_SESSION['session_token'])) {
            $stmt = $pdo->prepare('DELETE FROM sessions WHERE session_token_hash = :h');
            $stmt->execute(['h' => hash('sha256', $_SESSION['session_token'])]);
        }
        $_SESSION = [];
        session_destroy();
    }

    public static function requireAuth(PDO $pdo): array {
        $user = self::user($pdo);
        if (!$user) {
            header('Location: /login');
            exit;
        }
        return $user;
    }
}
