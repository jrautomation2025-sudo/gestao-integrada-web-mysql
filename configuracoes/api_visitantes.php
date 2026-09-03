<?php
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, PUT");

// Defina o seu token secreto (você também pode guardar isso no seu config.php ou variável de ambiente)
$n8n_token_visitantes = getenv('API_TOKEN_VISITANTES');
define('API_TOKEN_VISITANTES', $n8n_token_visitantes);

// ==========================================
// VALIDAÇÃO DO TOKEN DE AUTORIZAÇÃO
// ==========================================
$headers = apache_request_headers();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches) || $matches[1] !== API_TOKEN_VISITANTES) {
    http_response_code(401);
    echo json_encode(['erro' => 'Acesso não autorizado. Token inválido ou ausente.']);
    exit;
}

// ==========================================
// CONEXÃO COM O BANCO
// ==========================================
require_once __DIR__ . '/config.php';

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['erro' => 'Conexão com o banco de dados não encontrada no config.php']);
    exit;
}

$metodo = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

// ==========================================
// MÉTODO 1: BUSCAR DADOS DO VISITANTE (GET)
// ==========================================
if ($metodo === 'GET' && $action === 'individual') {

    $sessao_id = $_GET['sessao_id'] ?? null;
    $tenant_id = $_GET['tenant_id'] ?? null;
    $cim = $_GET['cim'] ?? null;

    try {
        if ($sessao_id && $tenant_id && $cim) {
            $stmt = $pdo->prepare("SELECT v.loja_origem, v.nome, v.grau, v.telefone, v.email, s.titulo, s.tipo, s.data_sessao,
                                    (select nome from secretaria_lojas where id in (select loja_id from usuarios where id = v.tenant_id )) as loja_visitada,
                                    (select url_logo from secretaria_lojas where id in (select loja_id from usuarios where id = v.tenant_id )) as logo_loja,
                                    (select nome from chancelaria_membros where tenant_id = v.tenant_id and cargo = 'secretario') as secretario,
                                    (select nome from chancelaria_membros where tenant_id = v.tenant_id and cargo = 'veneravel') as veneravel,
                                    (select nome from chancelaria_membros where tenant_id = v.tenant_id and cargo = 'chanceler') as chanceller
                                FROM chancelaria_visitantes v 
                                LEFT JOIN chancelaria_sessoes s ON s.id = v.sessao_id 
                                WHERE v.tenant_id = ? AND v.sessao_id = ? AND v.cim = ?");
            $stmt->execute([$tenant_id,$sessao_id,$cim]);
            $visitante = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($visitante ?: ['erro' => 'Visitante não encontrado']);
        } else {
            echo json_encode(['erro' => 'Parametros obrigatórios não fornecidos']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['erro' => 'Erro na consulta: ' . $e->getMessage()]);
    }
}

// ==========================================
// MÉTODO 2: BUSCAR DADOS DO VISITANTE (GET)
// ==========================================
elseif ($metodo === 'GET' && $action === 'todos') {
    $id = $_GET['id'] ?? null;
    $sessao_id = $_GET['sessao_id'] ?? null;
    $tenant_id = $_GET['tenant_id'] ?? null;

    try {
        if ($sessao_id) {
            $stmt = $pdo->prepare("SELECT v.loja_origem, v.nome, v.grau, v.telefone, v.email, s.titulo, s.tipo, s.data_sessao,
                                    (select nome from secretaria_lojas where id in (select loja_id from usuarios where id = v.tenant_id )) as loja_visitada,
                                    (select url_logo from secretaria_lojas where id in (select loja_id from usuarios where id = v.tenant_id )) as logo_loja,
                                    (select nome from chancelaria_membros where tenant_id = v.tenant_id and cargo = 'secretario') as secretario,
                                    (select nome from chancelaria_membros where tenant_id = v.tenant_id and cargo = 'veneravel') as veneravel,
                                    (select nome from chancelaria_membros where tenant_id = v.tenant_id and cargo = 'chanceler') as chanceller
                                FROM chancelaria_visitantes v 
                                LEFT JOIN chancelaria_sessoes s ON s.id = v.sessao_id 
                                WHERE v.tenant_id = ? AND v.sessao_id = ?");
            $stmt->execute([$tenant_id,$sessao_id]);
            $visitante = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($visitante ?: ['erro' => 'Visitante não encontrado']);
        } elseif ($id) {
            $stmt = $pdo->prepare("SELECT * FROM chancelaria_visitantes WHERE id = ?");
            $stmt->execute([$id]);
            $visitantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($visitantes);
        } else {
            $stmt = $pdo->query("SELECT * FROM chancelaria_visitantes");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['erro' => 'Erro na consulta: ' . $e->getMessage()]);
    }
}

// ==========================================
// MÉTODO 3: ATUALIZAR VISITANTE (POST / PUT)
// ==========================================
elseif ($metodo === 'POST' || $metodo === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);

    $id = $data['id'] ?? null;
    $telefone = $data['telefone'] ?? null;
    $email = $data['email'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID do visitante não informado.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE chancelaria_visitantes SET telefone = ?, email = ? WHERE id = ?");
        $stmt->execute([$telefone, $email, $id]);

        echo json_encode([
            'sucesso' => true, 
            'mensagem' => 'Dados atualizados com sucesso!',
            'dados_atualizados' => ['id' => $id, 'telefone' => $telefone, 'email' => $email]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao atualizar: ' . $e->getMessage()]);
    }
}
else {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
}
