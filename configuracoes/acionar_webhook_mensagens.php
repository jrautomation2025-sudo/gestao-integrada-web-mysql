<?php
// acionar_webhook_mensagens.php

session_start();
header('Content-Type: application/json');

// 1. Importa as configurações e a função central
require_once 'config.php';
require_once 'webhook_helper.php';

// 2. Segurança: Garante que apenas usuários logados (com tenant_id) acessem este arquivo
if (!isset($_SESSION['tenant_id'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Acesso negado: Usuário não autenticado.']);
    exit;
}

// 3. Pega o JSON enviado pelo JavaScript (usando fetch)
$conteudoRecebido = file_get_contents('php://input');
$dadosRecebidos = json_decode($conteudoRecebido, true);

// Verifica se os dados vieram corretamente
if (!$dadosRecebidos || !isset($dadosRecebidos['acao_interna'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos ou requisição mal formatada.']);
    exit;
}

// 4. Prepara o payload que será enviado ao n8n
// Já incluímos o tenant_id da sessão para manter a estrutura multitenant segura
$payloadN8n = [
    'tenant_id' => $_SESSION['tenant_id']
];

// 5. Roteamento: Define qual endpoint do n8n será chamado com base na ação enviada pelo JS
$endpoint = '';
$ano_filtro = date('Y');

if ($dadosRecebidos['acao_interna'] === 'grupo') {
    $endpoint = 'enviar-grupo';
    
    // Adiciona os dados específicos do envio para grupo
    $payloadN8n['mensagem'] = $dadosRecebidos['mensagem'] ?? '';
    $payloadN8n['usuario_id'] = $dadosRecebidos['usuario_id'] ?? '';
    $payloadN8n['tipo'] = $dadosRecebidos['tipo'] ?? 'grupo';
    $payloadN8n['ano'] = $ano_filtro;
    
} elseif ($dadosRecebidos['acao_interna'] === 'membros') {
    $endpoint = 'enviar-membros';
    
    // Adiciona os dados específicos do envio para membros
    $payloadN8n['loja'] = $dadosRecebidos['loja'] ?? '';
    $payloadN8n['tesoureiro'] = $dadosRecebidos['tesoureiro'] ?? '';
    $payloadN8n['usuario_id'] = $dadosRecebidos['usuario_id'] ?? '';
    $payloadN8n['ano'] = $ano_filtro;

} elseif ($dadosRecebidos['acao_interna'] === 'individual') {
    $endpoint = 'enviar-individual';
    
    // Adiciona os dados específicos do envio para individual
    $payloadN8n['mensagem'] = $dadosRecebidos['mensagem'] ?? '';
    $payloadN8n['membro'] = $dadosRecebidos['membro'] ?? '';
    $payloadN8n['mes'] = $dadosRecebidos['mes'] ?? '';
    $payloadN8n['parcela'] = $dadosRecebidos['parcela'] ?? '';
    $payloadN8n['usuario_id'] = $dadosRecebidos['usuario_id'] ?? '';
    $payloadN8n['tipo'] = $dadosRecebidos['tipo'] ?? 'individual';
    $payloadN8n['ano'] = (int)($ano_filtro);
    
} elseif ($dadosRecebidos['acao_interna'] === 'certificados') {
    $endpoint = 'enviar-certificados';
    
    // Adiciona os dados específicos do envio para membros
    $payloadN8n['action'] = $dadosRecebidos['action'] ?? '';
    $payloadN8n['sessao_id'] = $dadosRecebidos['sessao_id'] ?? '';
    $payloadN8n['tenant_id'] = $dadosRecebidos['tenant_id'] ?? '';
    $payloadN8n['cim'] = $dadosRecebidos['cim'] ?? '';
    
} elseif ($dadosRecebidos['acao_interna'] === 'felicitacoes') {
    $endpoint = 'enviar-aniversario';
    
    // Adiciona os dados específicos do envio para membros
    $payloadN8n['action'] = $dadosRecebidos['action'] ?? '';
    $payloadN8n['nome'] = $dadosRecebidos['nome'] ?? '';
    $payloadN8n['telefone'] = $dadosRecebidos['telefone'] ?? '';
    $payloadN8n['email'] = $dadosRecebidos['email'] ?? '';
    $payloadN8n['tenant_id'] = $dadosRecebidos['tenant_id'] ?? '';
    
} elseif ($dadosRecebidos['acao_interna'] === 'recibo-individual') {
    $endpoint = 'enviar-recibo';
    
    // Adiciona os dados específicos do envio para membros
    $payloadN8n['cliente_id'] = $dadosRecebidos['cliente_id'] ?? '';
    $payloadN8n['telefone'] = $dadosRecebidos['telefone'] ?? '';
    $payloadN8n['nome'] = $dadosRecebidos['nome'] ?? '';
    $payloadN8n['mesNome'] = $dadosRecebidos['mesNome'] ?? '';
    $payloadN8n['ano'] = $dadosRecebidos['ano'] ?? '';
    $payloadN8n['nomeLoja'] = $dadosRecebidos['nomeLoja'] ?? '';
    $payloadN8n['tesoureiro'] = $dadosRecebidos['tesoureiro'] ?? '';
    $payloadN8n['usuario_id'] = $dadosRecebidos['usuario_id'] ?? '';
    
} else {
    // Se tentarem mandar uma ação que não existe
    echo json_encode(['sucesso' => false, 'erro' => 'Ação desconhecida.']);
    exit;
}

// 6. Monta a URL final (N8N_BASE_URL vem do config.php)
$webhookUrl = N8N_BASE_URL . $endpoint;

// 7. Dispara usando a nossa função centralizada com proteção JWT
$resultado = dispararWebhookN8n($webhookUrl, $payloadN8n, N8N_JWT_SECRET);

// 8. Retorna a resposta final para o JavaScript do Frontend
if ($resultado['sucesso']) {
    echo json_encode(['sucesso' => true]);
} else {
    echo json_encode(['sucesso' => false, 'erro' => "Falha de comunicação com o webhook. HTTP: " . $resultado['http_code']]);
}
?>