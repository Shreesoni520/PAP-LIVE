<?php
date_default_timezone_set('Europe/Lisbon');

$secretsFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'secrets' . DIRECTORY_SEPARATOR . 'pap-secrets.php';

if (!is_readable($secretsFile)) {
    die('Ficheiro de segredos em falta. Crie: ' . $secretsFile);
}

$secrets = require $secretsFile;

$servername = $secrets['DB_HOST'] ?? 'localhost';
$username   = $secrets['DB_USER'] ?? 'root';
$password   = $secrets['DB_PASS'] ?? '';
$dbname     = $secrets['DB_NAME'] ?? 'pap';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die('Falha na ligação: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

if (!defined('ADMIN_EMAIL')) {
    define('ADMIN_EMAIL', $secrets['ADMIN_EMAIL'] ?? '');
}

if (!defined('TWILIO_SID')) {
    define('TWILIO_SID', $secrets['TWILIO_SID'] ?? '');
}
if (!defined('TWILIO_TOKEN')) {
    define('TWILIO_TOKEN', $secrets['TWILIO_TOKEN'] ?? '');
}
if (!defined('TWILIO_FROM')) {
    define('TWILIO_FROM', $secrets['TWILIO_FROM'] ?? '');
}
if (!defined('TWILIO_TO')) {
    define('TWILIO_TO', $secrets['TWILIO_TO'] ?? '');
}
