<?php

/**
 * Mediadev Monitor — Auth: login con sesión PHP nativa (single-user).
 */

declare(strict_types=1);

namespace MediadevMonitor\Auth;

use MediadevMonitor\Infra\Config;

final class Auth
{
    public function __construct(private Config $config)
    {
    }

    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function attempt(string $username, string $password): bool
    {
        $auth = $this->config->auth();

        if ($auth['username'] === '' || $auth['password_hash'] === '') {
            return false;
        }

        if (!hash_equals($auth['username'], $username)) {
            return false;
        }

        if (!password_verify($password, $auth['password_hash'])) {
            return false;
        }

        $this->startSession();
        session_regenerate_id(true);
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = $auth['username'];
        return true;
    }

    public function check(): bool
    {
        $this->startSession();
        return isset($_SESSION['user_id']);
    }

    public function logout(): void
    {
        $this->startSession();
        session_unset();
        session_destroy();
    }
}
