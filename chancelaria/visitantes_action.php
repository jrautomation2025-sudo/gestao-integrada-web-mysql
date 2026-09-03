<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../configuracoes/config.php';
header('Content-Type: application/json');

// Garante que só quem está logado acesse
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
        
        $stmt = $pdo->prepare("DELETE FROM chancelaria_visitantes WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);
        
        echo json_encode(['sucesso' => true]);
        exit;
    }

    // Variáveis comuns para Criar e Editar
    $sessao_id = !empty($dados['sessao_id']) ? (int)$dados['sessao_id'] : 0;
    $nome = trim($dados['nome'] ?? '');
    $cim = trim($dados['cim'] ?? '');
    $grau = (int)($dados['grau'] ?? 1);
    $loja_origem = trim($dados['loja_origem'] ?? '');
    $oriente = trim($dados['oriente'] ?? '');
    $potencia = trim($dados['potencia'] ?? '');
    $telefone = trim($dados['telefone'] ?? '');
    $email = trim($dados['email'] ?? '');

    // Validação básica
    if (empty($nome) || $sessao_id <= 0) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'O nome do visitante e a sessão são obrigatórios.']);
        exit;
    }

    // Validação de Segurança: Verifica se a sessão realmente pertence à Loja atual
    $stmtCheck = $pdo->prepare("SELECT id FROM chancelaria_sessoes WHERE id = ? AND tenant_id = ?");
    $stmtCheck->execute([$sessao_id, $tenant_id]);
    if ($stmtCheck->rowCount() === 0) {
         echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão inválida ou não pertence à sua Loja.']);
         exit;
    }

    // 2. AÇÃO: CRIAR
    if ($acao === 'criar') {
        $sql = "INSERT INTO chancelaria_visitantes 
                (tenant_id, sessao_id, nome, cim, grau, loja_origem, oriente, potencia, telefone, email) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenant_id, $sessao_id, $nome, $cim, $grau, $loja_origem, $oriente, $potencia, $telefone, $email]);
        
        echo json_encode(['sucesso' => true]);
        
    // 3. AÇÃO: EDITAR
    } elseif ($acao === 'editar') {
        if ($id <= 0) throw new Exception("ID inválido para edição.");

        $sql = "UPDATE chancelaria_visitantes 
                SET nome = ?, cim = ?, grau = ?, loja_origem = ?, oriente = ?, potencia = ?, telefone = ?, email = ? 
                WHERE id = ? AND tenant_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $cim, $grau, $loja_origem, $oriente, $potencia, $telefone, $email, $id, $tenant_id]);

        echo json_encode(['sucesso' => true]);
    } else {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Ação desconhecida.']);
    }

} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro BD: ' . $e->getMessage()]);
}
?>