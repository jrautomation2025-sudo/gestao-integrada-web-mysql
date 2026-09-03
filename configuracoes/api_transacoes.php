<?php
// =============================================================
// API DE TRANSAÇÕES (CORRIGIDA PARA TIPOS DO SISTEMA)
// =============================================================

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, x-api-key");

require 'config.php';
$api_key = getenv('JR_API_KEY');

// 1. SEGURANÇA
if (!defined('JR_API_KEY')) { define('JR_API_KEY', $api_key); }

$headers = getallheaders();
$receivedKey = $headers['x-api-key'] ?? $headers['X-Api-Key'] ?? $_GET['key'] ?? null;

if ($receivedKey !== JR_API_KEY) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'API Key inválida.']);
    exit;
}

// 2. RECEBE O INPUT
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Use método POST.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['telefone'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Telefone obrigatório.']);
    exit;
}

$telefone = preg_replace('/[^0-9]/', '', $input['telefone']);

// 3. CHECK USER (MANTIDO IGUAL)
if (isset($input['action']) && $input['action'] === 'check_user') {
    $stmt = $pdo->prepare("SELECT id, nome, email, plano FROM usuarios WHERE telefone LIKE ? LIMIT 1");
    $stmt->execute(["%{$telefone}%"]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $isVip = ($user['plano'] === 'vitalicio' || strpos($user['plano'], 'pro') !== false);
        echo json_encode(['status' => 'success', 'data' => ['id' => $user['id'], 'nome' => $user['nome'], 'is_vip' => $isVip]]);
    } else {
        echo json_encode(['status' => 'not_found', 'found' => false]);
    }
    exit; 
}

// 4. INSERIR TRANSAÇÃO
if (empty($input['valor']) || empty($input['descricao'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Informe valor e descricao.']);
    exit;
}

$valor     = (float) str_replace(',', '.', $input['valor']);
$descricao = trim($input['descricao']);
$data      = $input['data'] ?? date('Y-m-d');

// --- CORREÇÃO DO CONTEXTO ---
$raw_ctx   = $input['contexto'] ?? 'pessoal';
$contexto  = (strtolower($raw_ctx) === 'empresa') ? 'empresa' : 'pessoal';

// --- CORREÇÃO CRÍTICA DO TIPO (O ERRO ESTAVA AQUI) ---
// A IA manda "despesa", mas o banco espera "variavel" ou "fixo"
$raw_tipo = strtolower($input['tipo'] ?? 'despesa');

if ($raw_tipo === 'receita') {
    $tipo = 'receita';
} elseif ($raw_tipo === 'fixo') {
    $tipo = 'fixo';
} else {
    // Se vier "despesa" ou qualquer outra coisa, salvamos como VARIÁVEL
    $tipo = 'variavel';
}

try {
    $stmtUser = $pdo->prepare("SELECT id, nome, plano FROM usuarios WHERE telefone LIKE ? LIMIT 1");
    $stmtUser->execute(["%{$telefone}%"]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Usuário não encontrado.']);
        exit;
    }

    $isVip = ($user['plano'] === 'vitalicio' || strpos($user['plano'], 'pro') !== false);
    if (!$isVip) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Apenas assinantes PRO.']);
        exit;
    }

    $sql = "INSERT INTO transacoes (usuario_id, descricao, valor, tipo, data_transacao, contexto) VALUES (?, ?, ?, ?, ?, ?)";
    $stmtInsert = $pdo->prepare($sql);
    
    if ($stmtInsert->execute([$user['id'], $descricao, $valor, $tipo, $data, $contexto])) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Lançamento realizado!',
            'data' => [
                'usuario' => $user['nome'],
                'descricao' => $descricao,
                'valor' => number_format($valor, 2, ',', '.'),
                'tipo_registrado' => strtoupper($tipo), // Mostra como ficou no banco
                'contexto' => strtoupper($contexto)
            ]
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro interno: ' . $e->getMessage()]);
}
?>
