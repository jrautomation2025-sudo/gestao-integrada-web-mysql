<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
session_start();

// 1. INCLUI O ARQUIVO DE BANCO DE DADOS
// (Ajuste o caminho do config.php de acordo com a pasta onde está este arquivo de logout)
require_once 'config.php'; 

// 2. CAPTURA OS DADOS ANTES DE DESTRUIR A SESSÃO
$usuario_id = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
$tenant_id  = $_SESSION['tenant_id'] ?? null;
$ip_usuario = $_SERVER['REMOTE_ADDR'] ?? 'Desconhecido';
$data_agora = date('Y-m-d H:i:s'); 

// 3. REGISTRA O LOGOUT NO BANCO DE DADOS
if ($usuario_id && $tenant_id && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("INSERT INTO logs_acesso (tenant_id, usuario_id, acao, ip, data_acesso) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$tenant_id, $usuario_id, 'logout', $ip_usuario, $data_agora]);
    } catch (PDOException $e) {
        // Ignora silenciosamente. Se falhar o log, o usuário ainda deve conseguir sair.
    }
}

// ==========================================
// DAQUI PARA BAIXO É O SEU CÓDIGO ORIGINAL
// ==========================================

// Destroi todas as variáveis de sessão
$_SESSION = array();

// Se quiser matar o cookie da sessão também (limpeza profunda)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redireciona para o login
header("Location: /sistema");
exit;
?>
