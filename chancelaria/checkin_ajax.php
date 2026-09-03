<?php
// Garante saída estritamente em JSON e limpa qualquer eco anterior
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);

require_once 'conexao.php'; // Ajuste para o seu arquivo de conexão PDO

$token = $_GET['token'] ?? '';
$acao = $_GET['acao'] ?? '';

if (empty($token)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Token não fornecido.']);
    exit;
}

// Busca a sessão correspondente ao token
$stmt = $pdo->prepare("SELECT * FROM chancelaria_sessoes WHERE token_presenca = ?");
$stmt->execute([$token]);
$sessao = $stmt->fetch();

if (!$sessao) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Sessão não encontrada.']);
    exit;
}

$sessao_id = $sessao['id'];
$tenant_id = $sessao['tenant_id'];

// Ação: Buscar Obreiro
if ($acao === 'buscar_obreiro') {
    $cim = trim($_GET['cim'] ?? '');
    
    $stmt = $pdo->prepare("SELECT id, nome, cim, grau FROM obreiros WHERE cim = ? AND tenant_id = ?");
    $stmt->execute([$cim, $tenant_id]);
    $obreiro = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($obreiro) {
        // Verifica se já registrou presença nesta sessão
        $stmtCheck = $pdo->prepare("SELECT id FROM presencas WHERE sessao_id = ? AND obreiro_id = ?");
        $stmtCheck->execute([$sessao_id, $obreiro['id']]);
        if ($stmtCheck->fetch()) {
            echo json_encode(['status' => 'ja_registrado', 'mensagem' => 'Presença já foi registrada para este CIM nesta sessão!']);
        } else {
            echo json_encode(['status' => 'sucesso', 'dados' => $obreiro]);
        }
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'CIM não encontrado na base de dados.']);
    }
    exit;
}

// Ação: Buscar Visitante
if ($acao === 'buscar_visitante') {
    $termo = trim($_GET['termo'] ?? '');
    
    $stmt = $pdo->prepare("SELECT * FROM visitantes WHERE (cim = ? OR nome LIKE ?) AND tenant_id = ? LIMIT 1");
    $stmt->execute([$termo, "%$termo%", $tenant_id]);
    $visitante = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($visitante) {
        echo json_encode(['status' => 'encontrado', 'dados' => $visitante]);
    } else {
        echo json_encode(['status' => 'novo']);
    }
    exit;
}