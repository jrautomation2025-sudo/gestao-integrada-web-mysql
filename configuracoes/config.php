<?php
// ==========================================
// CONFIGURAÇÕES DO BANCO DE DADOS (MASTER / SLAVE)
// ==========================================
$db_host_write = getenv('DB_HOST_WRITE') ?: (getenv('DB_HOST') ?: 'localhost');
$db_host_read  = getenv('DB_HOST_READ')  ?: (getenv('DB_HOST') ?: 'localhost');
$dbname        = getenv('DB_NAME')       ?: 'banco_local';
$user          = getenv('DB_USER')       ?: 'root';
$pass          = getenv('DB_PASS')       ?: '';
$port          = getenv('DB_PORT')       ?: '3306';

// ==========================================
// VARIÁVEIS DE AMBIENTE (WEBHOOKS E APIS)
// ==========================================
$webhook_base_url = getenv('N8N_BASE_URL');
$n8n_api_key      = getenv('API_TOKEN');
$n8n_secret_key   = getenv('API_SECRET');
$n8n_jwt_key      = getenv('N8N_JWT_SECRET');
$n8n_base_url     = getenv('BASE_URL');

// Define como constantes
define('N8N_BASE_URL', $webhook_base_url);
define('API_TOKEN', $n8n_api_key);
define('API_SECRET', $n8n_secret_key);
define('N8N_JWT_SECRET', $n8n_jwt_key);
define('BASE_URL', $n8n_base_url);

try {
    // 1. Conexão de ESCRITA (Master - Aponta para o IP da Tailscale de Gravação)
    $pdo_write = new PDO("mysql:host=$db_host_write;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // 2. Conexão de LEITURA (Slave - Aponta para o IP da Tailscale de Leitura)
    $pdo_read = new PDO("mysql:host=$db_host_read;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Mantém uma variável global padrão '$pdo' apontando para a escrita 
    // para evitar quebrar códigos antigos que usem apenas '$pdo'
    $pdo = $pdo_write;

} catch (PDOException $e) {
    die("Erro na conexão com o banco: " . $e->getMessage());
}
?>
