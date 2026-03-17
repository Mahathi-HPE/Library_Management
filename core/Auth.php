<?php
declare(strict_types=1);

class Auth
{
    public static function login(array $user): void
    {
        $_SESSION['user'] = $user;
    }

    public static function logout(): void
    {
        unset($_SESSION['user']);
        unset($_SESSION['flash']);
    }

    public static function user(): ?array { return $_SESSION['user'] ?? null; }

    public static function check(): bool { return isset($_SESSION['user']); }

    public static function role(): ?string { return $_SESSION['user']['role'] ?? null; }

    public static function requireRole(string $role): void
    {
        if (!self::check() || self::role() !== $role) {
            header('Location: index.php?c=auth&a=login');
            exit;
        }
    }
}
