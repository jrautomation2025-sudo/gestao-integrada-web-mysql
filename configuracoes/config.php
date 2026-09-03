<?php
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'banco_local';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$port = getenv('DB_PORT') ?: '';

// ==========================================
// VARIÁVEIS DE AMBIENTE (WEBHOOKS E APIS)
// ==========================================
// Pega do Easypanel. Se não existir (localhost), usa a string depois do '?:'
$webhook_base_url = getenv('N8N_BASE_URL');
$n8n_api_key = getenv('API_TOKEN');
$n8n_secret_key = getenv('API_SECRET');
$n8n_jwt_key = getenv('N8N_JWT_SECRET');
$n8n_base_url = getenv('BASE_URL');

// Define como constantes para poder usar em qualquer arquivo do sistema
define('N8N_BASE_URL', $webhook_base_url);
define('API_TOKEN', $n8n_api_key);
define('API_SECRET', $n8n_secret_key);
define('N8N_JWT_SECRET', $n8n_jwt_key);
define('BASE_URL', $n8n_base_url);


try {
    //$pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão com o banco: " . $e->getMessage());
}

?>
