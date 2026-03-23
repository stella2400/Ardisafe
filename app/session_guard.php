<?php
// Esegue un controllo di inattività lato server su ogni richiesta
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

if (!defined('IDLE_MAX_MINUTES')) {
    define('IDLE_MAX_MINUTES', 15); // configura qui (minuti)
}

if (!empty($_SESSION['user'])) {
    $now  = time();
    $last = (int)($_SESSION['__last_activity'] ?? $now);
    if ($now - $last >= IDLE_MAX_MINUTES * 60) {
        // scade la sessione
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        header('Location: /Ardisafe2.0/login.php?timeout=1');
        exit;
    }
    // aggiorna ultimo tocco
    $_SESSION['__last_activity'] = $now;
}
