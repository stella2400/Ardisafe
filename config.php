<?php
// Avvia la sessione PRIMA di qualsiasi output
session_start();

// Includi l’autoloader che hai già creato
require_once __DIR__ . '/autoloader.php';

// Config DB (adatta ai tuoi valori)
const DB_DSN  = 'mysql:host=localhost;dbname=ardisafe;charset=utf8mb4';
const DB_USER = 'root';
const DB_PASS = '';

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Errore connessione al database.');
}

// Helper: utente loggato?
function is_authenticated(): bool {
    return !empty($_SESSION['user']);
}

// Helper: forza login
function require_auth(): void {
    if (!is_authenticated()) {
        header('Location: /login.php');
        exit;
    }
}
