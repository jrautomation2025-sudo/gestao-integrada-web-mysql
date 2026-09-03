<?php
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");

// Defina o seu token secreto (você também pode guardar isso no seu config.php ou variável de ambiente)
$n8n_token_membros = getenv('API_TOKEN_MEMBROS');
define('API_TOKEN_MEMBROS', $n8n_token_membros);

// ==========================================
// VALIDAÇÃO DO TOKEN DE AUTORIZAÇÃO
// ==========================================
$headers = apache_request_headers();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches) || $matches[1] !== API_TOKEN_MEMBROS) {
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
    echo json_encode(['erro' => 'Conexão com o banco de dados não encontrada nas configurações']);
    exit;
}

$metodo = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

// ==========================================
// MÉTODO 1: BUSCAR DADOS DO VISITANTE (GET)
// ==========================================
if ($metodo === 'GET' && $action === 'recibo') {

    $user_id = $_GET['user_id'] ?? null;
    $mes = $_GET['mes'] ?? null;

    try {
        if ($user_id && $mes) {
            $stmt = $pdo->prepare("SELECT c.id, c.nome, c.telefone, c.email, c.situacao, c.recolhe, c.valor_mensalidade, m.mes, m.status, m.recibo_enviado, l.nome AS loja_nome, l.url_logo AS loja_logo, l.endereco AS loja_endereco, l.email AS loja_email
                                        FROM clientes c
                                        LEFT JOIN mensalidades m ON c.id = m.cliente_id
                                        JOIN usuarios u ON c.usuario_id = u.id
                                        JOIN secretaria_lojas l ON u.loja_id = l.id
                                    WHERE c.usuario_id = ?
                                    AND c.recolhe = 'Sim'
                                    AND m.status = 'OK'
                                    AND m.mes <= ?
                                    AND m.recibo_enviado <> 1
                                    ORDER BY c.nome ASC, m.mes ASC");
            $stmt->execute([$user_id,$mes]);
            $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($membros ?: ['erro' => 'Dados não encontrado']);
        } else {
            echo json_encode(['erro' => 'Informe o user_id e o mes para a pesquisa']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['erro' => 'Erro na consulta: ' . $e->getMessage()]);
    }
}

elseif ($metodo === 'GET' && $action === 'recibo_individual') {

    $user_id = $_GET['user_id'] ?? null;
    $cliente_id = $_GET['cliente_id'] ?? null;
    $mes = $_GET['mes'] ?? null;

    try {
        if ($user_id && $mes) {
            $stmt = $pdo->prepare("SELECT c.id, c.nome, c.telefone, c.email, c.situacao, c.recolhe, c.valor_mensalidade, m.mes, m.status, m.recibo_enviado, l.nome AS loja_nome, l.url_logo AS loja_logo, l.endereco AS loja_endereco, l.email AS loja_email
                                        FROM clientes c
                                        LEFT JOIN mensalidades m ON c.id = m.cliente_id
                                        JOIN usuarios u ON c.usuario_id = u.id
                                        JOIN secretaria_lojas l ON u.loja_id = l.id
                                    WHERE c.usuario_id = ?
                                    AND c.recolhe = 'Sim'
                                    AND m.status = 'OK'
                                    AND m.cliente_id = ?
                                    AND m.mes = ?
                                    ORDER BY c.nome ASC, m.mes ASC");
            $stmt->execute([$user_id,$cliente_id,$mes]);
            $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($membros ?: ['erro' => 'Dados não encontrado']);
        } else {
            echo json_encode(['erro' => 'Informe o user_id e o mes para a pesquisa']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['erro' => 'Erro na consulta: ' . $e->getMessage()]);
    }
}

elseif ($metodo === 'GET' && $action === 'individual') {

    $ano = $_GET['ano'] ?? '2026';
    $user_id = $_GET['user_id'] ?? null;
    $membro = $_GET['membro'] ?? null;
    $parcela = (int) ($_GET['parcela'] ?? 0);

    try {
        if ($ano && $user_id &&  $membro && $parcela) {
            
            $sql = "SELECT c.usuario_id, c.id, c.nome, c.telefone, c.situacao, c.recolhe, c.valor_mensalidade, m.mes, m.status, m.recibo_enviado
                    FROM clientes c
                    LEFT JOIN mensalidades m ON c.id = m.cliente_id AND m.ano = :ano
                    WHERE c.usuario_id = :user_id
                    AND m.cliente_id = :membro
                    AND m.status in ('NOK','Pendente')
                    LIMIT :parcela";

            $stmt = $pdo->prepare($sql);
            
            // Fazemos o bind explícito, garantindo os tipos corretos
            $stmt->bindValue(':ano', $ano, PDO::PARAM_STR); 
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':membro', $membro, PDO::PARAM_INT);
            
            // O segredo está aqui: Forçamos o PDO a enviar como Inteiro, sem aspas
            $stmt->bindValue(':parcela', $parcela, PDO::PARAM_INT); 
            
            $stmt->execute();
            
            $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($membros ?: ['erro' => 'Dados não encontrados']);
            
        } else {
            echo json_encode(['erro' => 'Informe os dados de entrada para a pesquisa']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['erro' => 'Erro na consulta: ' . $e->getMessage()]);
    }
}

elseif ($metodo === 'GET' && $action === 'membros') {

    $user_id = $_GET['user_id'] ?? null;
    $mes = $_GET['mes'] ?? null;

    try {
        if ($user_id && $mes) {
            $stmt = $pdo->prepare("SELECT c.id, c.nome, c.telefone, c.situacao, c.recolhe, c.valor_mensalidade, m.mes, m.status, m.recibo_enviado, c.usuario_id
                                    FROM clientes c
                                    LEFT JOIN mensalidades m ON c.id = m.cliente_id
                                    WHERE c.usuario_id = ?
                                    AND c.recolhe = 'Sim'
                                    AND m.status in ('NOK','Pendente')
                                    AND m.mes < ?
                                    AND c.duplo_filiado = 'Não'
                                    ORDER BY c.nome ASC, m.mes ASC");
            $stmt->execute([$user_id,$mes]);
            $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($membros ?: ['erro' => 'Dados não encontrado']);
        } else {
            echo json_encode(['erro' => 'Informe o user_id e o mes para a pesquisa']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['erro' => 'Erro na consulta: ' . $e->getMessage()]);
    }
}

elseif ($metodo === 'GET' && $action === 'filiado') {

    $user_id = $_GET['user_id'] ?? null;
    $mes = $_GET['mes'] ?? null;

    try {
        if ($user_id && $mes) {
            $stmt = $pdo->prepare("SELECT c.id, c.nome, c.telefone, c.situacao, c.recolhe, c.valor_mensalidade, m.mes, m.status, m.recibo_enviado, c.usuario_id
                                    FROM clientes c
                                    LEFT JOIN mensalidades m ON c.id = m.cliente_id
                                    WHERE c.usuario_id = ?
                                    AND c.recolhe = 'Sim'
                                    AND m.status in ('NOK','Pendente')
                                    AND m.mes < ?
                                    AND c.duplo_filiado = 'Sim'
                                    ORDER BY c.nome ASC, m.mes ASC");
            $stmt->execute([$user_id,$mes]);
            $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($membros ?: ['erro' => 'Dados não encontrado']);
        } else {
            echo json_encode(['erro' => 'Informe o user_id e o mes para a pesquisa']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['erro' => 'Erro na consulta: ' . $e->getMessage()]);
    }
}

// ==========================================
// MÉTODO 2: ATUALIZAR VISITANTE (POST / PUT)
// ==========================================
elseif ($metodo === 'PUT' || $metodo === 'POST') {
    
    $data = json_decode(file_get_contents('php://input'), true);

    $id = $data['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID do membro não informado.']);
        exit;
    }

    try {
        $sql = "UPDATE mensalidades SET recibo_enviado = '1' WHERE cliente_id = ? AND recibo_enviado = '0' AND status = 'OK'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);

        echo json_encode([
            'sucesso' => true, 
            'mensagem' => 'Dados atualizados com sucesso!',
            'dados_atualizados' => ['id' => $id]
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
