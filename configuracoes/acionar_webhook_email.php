<?php
// acionar_webhook_email.php

session_start();
header('Content-Type: application/json');

// 1. Importa as configurações e a função central
require_once 'config.php';
require_once 'webhook_helper_email.php';

$id = $_SESSION['tenant_id'];

$stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Segurança: Garante que apenas usuários logados acessem
if (!isset($_SESSION['tenant_id'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Acesso negado: tenant_id não encontrado na sessão.']);
    exit;
}

if ($_SESSION['is_admin'] != 1) {
    echo json_encode(['sucesso' => false, 'erro' => 'Acesso negado: Usuário não é administrador.']);
    exit;
}

// 3. Monta a URL completa juntando a constante do config.php com o endpoint específico
$webhookUrl = N8N_BASE_URL . 'valida-email';
$apikey = getenv('API_TOKEN');

// 4. Prepara os dados que serão enviados no Body do Webhook
$dados = [
    'tenant_id' => $_SESSION['tenant_id'],
    'user' => $user['nome'],
    'acao' => 'validar_base_emails'
];

// 5. Chama a função central usando a constante N8N_JWT_SECRET
$resultado = dispararWebhookN8n($webhookUrl, $dados, $apikey);

// 6. Retorna a resposta para o Javascript do Frontend
if ($resultado['sucesso']) {
    echo json_encode(['sucesso' => true]);
} else {
    echo json_encode(['sucesso' => false, 'erro' => "Falha no webhook. HTTP Status: " . $resultado['http_code']]);
}