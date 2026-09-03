<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../configuracoes/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['tenant_id'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$dados = json_decode(file_get_contents('php://input'), true);

if (!$dados) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum dado recebido.']);
    exit;
}

$acao = $dados['acao'] ?? '';
$id = !empty($dados['id']) ? (int)$dados['id'] : 0;

try {
    // 1. AÇÃO: EXCLUIR
    if ($acao === 'excluir') {
        if ($id <= 0) throw new Exception("ID inválido.");
        
        $stmt = $pdo->prepare("DELETE FROM chancelaria_membros WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);
        
        echo json_encode(['sucesso' => true]);
        exit;
    }

    // Variáveis para Criar e Editar
    $nome = trim($dados['nome'] ?? '');
    $cim = trim($dados['cim'] ?? '');
    $grau = trim($dados['grau'] ?? '');
    $cargo = trim($dados['cargo'] ?? '');
    $status = trim($dados['status'] ?? 'Ativo');
    $presenca = trim($dados['presenca'] ?? 'obrigatoria');
    $telefone = trim($dados['telefone'] ?? '');
    $email = trim($dados['email'] ?? '');
    
    // Tratamento das datas para permitir NULL caso não sejam informadas
    $data_nascimento = !empty($dados['data_nascimento']) ? $dados['data_nascimento'] : null;
    $data_iniciacao = !empty($dados['data_iniciacao']) ? $dados['data_iniciacao'] : null;

    if (empty($nome) || empty($cim)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'O nome e o CIM são obrigatórios.']);
        exit;
    }

    // 2. AÇÃO: CRIAR
    if ($acao === 'criar') {
        $sql = "INSERT INTO chancelaria_membros (tenant_id, nome, cim, grau, cargo, status, presenca, telefone, email, data_nascimento, data_iniciacao) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenant_id, $nome, $cim, $grau, $cargo, $status, $presenca, $telefone, $email, $data_nascimento, $data_iniciacao]);
        
        echo json_encode(['sucesso' => true]);
        
    // 3. AÇÃO: EDITAR
    } elseif ($acao === 'editar') {
        if ($id <= 0) throw new Exception("ID inválido para edição.");

        $sql = "UPDATE chancelaria_membros 
                SET nome = ?, cim = ?, grau = ?, cargo = ?, status = ?, presenca = ?, telefone = ?, email = ?, data_nascimento = ?, data_iniciacao = ? 
                WHERE id = ? AND tenant_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $cim, $grau, $cargo, $status, $presenca, $telefone, $email, $data_nascimento, $data_iniciacao, $id, $tenant_id]);

        echo json_encode(['sucesso' => true]);
    } else {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Ação desconhecida.']);
    }

} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro BD: ' . $e->getMessage()]);
}
?>