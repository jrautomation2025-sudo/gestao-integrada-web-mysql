<?php
// api.php - VERSÃO FINAL (APP + WEB + ADMIN + CONTEXTO EMPRESA)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 1. Início de Sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key");

require 'config.php';

// 2. Leitura da Ação e Inputs
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';

// ==================================================================
// NOVO: RECUPERA O CONTEXTO (PESSOAL OU EMPRESA)
// ==================================================================
// Se não houver sessão (App/API externa), assume 'pessoal' por padrão
$contexto = $_SESSION['contexto_atual'] ?? 'pessoal';

// ==================================================================
// 3. ROTAS PÚBLICAS (IA / ZAP / WEBHOOKS) - MANTIDAS IGUAIS
// ==================================================================

// Adicionar via WhatsApp/N8N
if ($action === 'add_remote') {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($apiKey !== API_SECRET) { http_response_code(403); echo json_encode(['error' => 'API Key inválida']); exit; }

    $desc = $input['descricao'] ?? $_POST['descricao'] ?? 'Via API';
    $val = $input['valor'] ?? $_POST['valor'] ?? 0;
    $tipo = $input['tipo'] ?? $_POST['tipo'] ?? 'variavel';
    $tel = preg_replace('/[^0-9]/', '', $input['telefone'] ?? $_POST['telefone'] ?? '');
    $data = $input['data'] ?? $_POST['data'] ?? date('Y-m-d');
    
    // Webhooks externos salvam sempre como PESSOAL por padrão (ou mude aqui se quiser)
    $ctx_remote = 'pessoal'; 

    $u = $pdo->prepare("SELECT id FROM usuarios WHERE telefone = ?");
    $u->execute([$tel]);
    $user = $u->fetch(PDO::FETCH_ASSOC);

    if (!$user) { echo json_encode(['error' => 'User not found']); exit; }

    $pdo->prepare("INSERT INTO transacoes (usuario_id, tipo, descricao, valor, data_transacao, contexto) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$user['id'], $tipo, $desc, $val, $data, $ctx_remote]);
    
    echo json_encode(['status' => 'success']); exit;
}

// Relatórios IA
if ($action === 'ai_report') {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($apiKey !== API_SECRET) { http_response_code(403); echo json_encode(['error' => 'API Key inválida']); exit; }

    $tel = preg_replace('/[^0-9]/', '', $input['telefone'] ?? $_GET['telefone'] ?? '');
    $mes = $input['mes'] ?? $_GET['mes'] ?? date('Y-m');
    
    $u = $pdo->prepare("SELECT id FROM usuarios WHERE telefone = ?");
    $u->execute([$tel]);
    $user = $u->fetch(PDO::FETCH_ASSOC);
    if (!$user) { echo json_encode(['error' => 'User not found']); exit; }

    // IA vê tudo (soma pessoal + empresa) ou filtra? Aqui deixei somando tudo por enquanto.
    $sql = "SELECT SUM(CASE WHEN tipo in ('receita','saldo') THEN valor ELSE 0 END) as rec, SUM(CASE WHEN tipo not in ('receita','saldo') THEN valor ELSE 0 END) as desp FROM transacoes WHERE usuario_id=? AND DATE_FORMAT(data_transacao, '%Y-%m')=?";
    $st = $pdo->prepare($sql); $st->execute([$user['id'], $mes]);
    $tot = $st->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['financeiro' => ['receitas'=>$tot['rec'], 'despesas'=>$tot['desp'], 'saldo'=>$tot['rec']-$tot['desp']]]);
    exit;
}

if ($action === 'inv_report_remote') {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($apiKey !== API_SECRET) { http_response_code(403); echo json_encode(['error' => 'API Key inválida']); exit; }
    $tel = preg_replace('/[^0-9]/', '', $input['telefone'] ?? $_GET['telefone'] ?? '');
    $u = $pdo->prepare("SELECT id FROM usuarios WHERE telefone = ?");
    $u->execute([$tel]);
    $user = $u->fetch(PDO::FETCH_ASSOC);
    if (!$user) { echo json_encode(['error' => 'User not found']); exit; }
    $st = $pdo->prepare("SELECT SUM(valor_investido) as inv, SUM(valor_atual) as atu FROM investimentos WHERE usuario_id=?");
    $st->execute([$user['id']]);
    $res = $st->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['resumo' => ['investido'=>$res['inv'], 'atual'=>$res['atu']]]);
    exit;
}

require 'jwt.php'; 

// ==================================================================
// 4. AUTENTICAÇÃO HÍBRIDA (JWT + SESSÃO)
// ==================================================================
$user_id = null;

// A. Tenta Sessão (Site Web)
if (isset($_SESSION['tenant_id'])) {
    $user_id = $_SESSION['tenant_id'];
} 
// B. Tenta Token JWT (App / N8N)
else {
    $authHeader = null;
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? null;
    }

    if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
        $decoded = JWT::decode($token);

        if ($decoded === 'expired') {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Token expirado', 'code' => 'token_expired']);
            exit;
        } elseif ($decoded === false) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Token inválido']);
            exit;
        } else {
            $user_id = $decoded['uid'];
            // Se for via App (JWT), o contexto padrão é sempre PESSOAL por enquanto
            $contexto = 'pessoal'; 
        }
    }
}

// Bloqueio
if (!$user_id && $action !== 'login_api' && $action !== 'refresh_token') {
    http_response_code(403); echo json_encode(['status' => 'error', 'message' => 'Não autorizado']); exit;
}

// ==================================================================
// 5. ROTAS DE USUÁRIO (ATUALIZADAS COM CONTEXTO)
// ==================================================================

// LISTA: AGORA FILTRA PELO CONTEXTO
if ($action === 'list') {
    $stmt = $pdo->prepare("SELECT * FROM transacoes WHERE usuario_id = ? AND contexto = ? ORDER BY data_transacao DESC LIMIT 10");
    $stmt->execute([$user_id, $contexto]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// RESUMO: AGORA FILTRA PELO CONTEXTO
if ($action === 'summary') {
    $stmt = $pdo->prepare("SELECT SUM(CASE WHEN tipo in('receita','mensalidade','saldo','tronco') THEN valor ELSE 0 END) as receitas, SUM(CASE WHEN tipo='fixo' THEN valor ELSE 0 END) as fixo, SUM(CASE WHEN tipo='variavel' THEN valor ELSE 0 END) as variavel FROM transacoes WHERE usuario_id = ? AND contexto = ?");
    $stmt->execute([$user_id, $contexto]);
    $d = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode([
        'receitas' => (float)$d['receitas'],
        'fixo' => (float)$d['fixo'],
        'variavel' => (float)$d['variavel'],
        'total_despesas' => (float)$d['fixo'] + (float)$d['variavel'],
        'saldo' => (float)$d['receitas'] - ((float)$d['fixo'] + (float)$d['variavel'])
    ]);
    exit;
}

// ADICIONAR: AGORA SALVA O CONTEXTO
if ($action === 'add') {
    $tipo = $input['tipo'] ?? $_POST['tipo'];
    $desc = $input['descricao'] ?? $_POST['descricao'];
    $valor = $input['valor'] ?? $_POST['valor'];
    $data = $input['data'] ?? $_POST['data'] ?? date('Y-m-d');
    
    // Pega o contexto enviado no POST (do input hidden) ou usa o da sessão
    $ctx_salvar = $input['contexto'] ?? $_POST['contexto'] ?? $contexto;

    // 1. Verifica o Plano e Créditos
    $stmtU = $pdo->prepare("SELECT plano, creditos_ia, plano_expiracao, telefone FROM usuarios WHERE id = ?");
    $stmtU->execute([$user_id]);
    $uData = $stmtU->fetch(PDO::FETCH_ASSOC);

    $usarIA = false;
    $plano = $uData['plano'];
    $validade = $uData['plano_expiracao'];
    $hoje = new DateTime();

    if ($plano == 'vitalicio') {
        $usarIA = true;
    } 
    elseif (strpos($plano, 'pro') !== false && $validade && new DateTime($validade) > $hoje) {
        $usarIA = true;
    } 
    elseif ($uData['creditos_ia'] > 0) {
        $usarIA = true;
    }

    // 2. Salva no Banco (COM CONTEXTO)
    $stmt = $pdo->prepare("INSERT INTO transacoes (usuario_id, tipo, descricao, valor, data_transacao, contexto) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$user_id, $tipo, $desc, $valor, $data, $ctx_salvar])) {
        
        // 3. IA (Mantido igual)
        if ($usarIA) {
            $ehAssinante = ($plano == 'vitalicio') || (strpos($plano, 'pro') !== false && $validade && new DateTime($validade) > $hoje);
            if (!$ehAssinante) {
                $pdo->prepare("UPDATE usuarios SET creditos_ia = creditos_ia - 1 WHERE id = ?")->execute([$user_id]);
            }
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Salvo em modo ' . strtoupper($ctx_salvar)]);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

// GRÁFICO: AGORA FILTRA PELO CONTEXTO
if ($action === 'chart_data') {
    $sql = "SELECT DATE_FORMAT(data_transacao, '%Y-%m') as mes_ano, SUM(CASE WHEN tipo in ('receita','saldo','mensalidade','tronco') THEN valor ELSE 0 END) as receita, SUM(CASE WHEN tipo not in ('receita','saldo','mensalidade','tronco') THEN valor ELSE 0 END) as despesa FROM transacoes WHERE usuario_id = ? AND contexto = ? AND data_transacao >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY mes_ano ORDER BY mes_ano ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $contexto]);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $l=[]; $r=[]; $d=[];
    
    // Pequeno ajuste para formatar rótulos bonitos se quiser
    $mesesPT = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun','07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];

    foreach($dados as $row) { 
        $mesNum = substr($row['mes_ano'], 5, 2); // Pega o mm de AAAA-mm
        $l[] = $mesesPT[$mesNum] ?? $row['mes_ano']; // Tenta nome ou usa original
        $r[]=$row['receita']; 
        $d[]=$row['despesa']; 
    }
    echo json_encode(['labels'=>$l, 'receitas'=>$r, 'despesas'=>$d]);
    exit;
}

// ==================================================================
// INVESTIMENTOS (AGORA COM CONTEXTO)
// ==================================================================

// LISTA
if ($action === 'inv_list') {
    $stmt = $pdo->prepare("SELECT * FROM investimentos WHERE usuario_id = ? AND contexto = ? ORDER BY valor_atual DESC");
    $stmt->execute([$user_id, $contexto]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// RESUMO
if ($action === 'inv_summary') {
    $stmt = $pdo->prepare("SELECT SUM(valor_investido) as inv, SUM(valor_atual) as atu FROM investimentos WHERE usuario_id = ? AND contexto = ?");
    $stmt->execute([$user_id, $contexto]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    $inv = (float)$res['inv']; 
    $atu = (float)$res['atu']; 
    $lucro = $atu - $inv;
    $rent = ($inv > 0) ? ($lucro / $inv) * 100 : 0;
    
    echo json_encode([
        'investido' => $inv, 
        'atual' => $atu, 
        'lucro' => $lucro, 
        'rentabilidade' => round($rent, 2)
    ]);
    exit;
}

// GRÁFICO DE ALOCAÇÃO
if ($action === 'inv_chart') {
    $stmt = $pdo->prepare("SELECT categoria, SUM(valor_atual) as total FROM investimentos WHERE usuario_id = ? AND contexto = ? GROUP BY categoria");
    $stmt->execute([$user_id, $contexto]);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $l = []; $v = []; 
    foreach($dados as $d){ $l[] = $d['categoria']; $v[] = (float)$d['total']; }
    echo json_encode(['labels' => $l, 'data' => $v]);
    exit;
}

// Editar APORTE
if ($action === 'inv_add') {
    $cat = $input['categoria'] ?? $_POST['categoria'];
    $ativo = $input['ativo'] ?? $_POST['ativo'];
    $inv = str_replace(',', '.', $input['valor_investido'] ?? $_POST['valor_investido']);
    $atu = str_replace(',', '.', $input['valor_atual'] ?? $_POST['valor_atual']);
    $data = $input['data'] ?? $_POST['data'];
    
    // Pega o contexto (para saber se é investimento da Empresa ou Pessoal)
    $ctx_salvar = $input['contexto'] ?? $_POST['contexto'] ?? $contexto;

    try {
        $pdo->beginTransaction();
        
        // 1. Salva o Investimento com o contexto certo
        $pdo->prepare("INSERT INTO investimentos (usuario_id, categoria, ativo, valor_investido, valor_atual, data_aporte, contexto) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$user_id, $cat, $ativo, $inv, $atu, $data, $ctx_salvar]);
        
        // 2. Gera a despesa (saída do caixa) também com o mesmo contexto
        //$pdo->prepare("INSERT INTO transacoes (usuario_id, tipo, descricao, valor, data_transacao, contexto) VALUES (?, 'fixo', ?, ?, ?, ?)")
            //->execute([$user_id, "Aporte: $ativo", $inv, $data, $ctx_salvar]);
        
        $pdo->commit(); 
        echo json_encode(['status'=>'success', 'message' => 'Investimento salvo em modo '.strtoupper($ctx_salvar)]);
    } catch(Exception $e) { 
        $pdo->rollBack(); 
        echo json_encode(['status'=>'error', 'message' => $e->getMessage()]); 
    }
    exit;
}

// ADICIONAR APORTE
if ($action === 'inv_edit') {
    $id = $input['id'] ?? $_POST['id'];
    $cat = $input['categoria'] ?? $_POST['categoria'];
    $ativo = $input['ativo'] ?? $_POST['ativo'];
    $inv = str_replace(',', '.', $input['valor_investido'] ?? $_POST['valor_investido']);
    $atu = str_replace(',', '.', $input['valor_atual'] ?? $_POST['valor_atual']);
    $data = $input['data'] ?? $_POST['data'];
    
    // Pega o contexto (para saber se é investimento da Empresa ou Pessoal)
    $ctx_salvar = $input['contexto'] ?? $_POST['contexto'] ?? $contexto;

    try {
        $pdo->beginTransaction();
        
        // 1. Salva o Investimento com o contexto certo
        $pdo->prepare("Update investimentos set categoria = ?, ativo = ?, valor_investido = ?, valor_atual = ?, data_aporte = ? WHERE usuario_id = ? AND id = ?")
            ->execute([$cat, $ativo, $inv, $atu, $data, $user_id, $id]);
        
        // 2. Gera a despesa (saída do caixa) também com o mesmo contexto
        //$pdo->prepare("INSERT INTO transacoes (usuario_id, tipo, descricao, valor, data_transacao, contexto) VALUES (?, 'fixo', ?, ?, ?, ?)")
            //->execute([$user_id, "Aporte: $ativo", $inv, $data, $ctx_salvar]);
        
        $pdo->commit(); 
        echo json_encode(['status'=>'success', 'message' => 'Investimento salvo em modo '.strtoupper($ctx_salvar)]);
    } catch(Exception $e) { 
        $pdo->rollBack(); 
        echo json_encode(['status'=>'error', 'message' => $e->getMessage()]); 
    }
    exit;
}

// EXCLUIR
if ($action === 'inv_delete') {
    $id = $input['id'] ?? $_POST['id'];
    // Só permite deletar se pertencer ao usuário (contexto não precisa filtrar aqui, pois ID é único)
    $pdo->prepare("DELETE FROM investimentos WHERE id = ? AND usuario_id = ?")->execute([$id, $user_id]);
    echo json_encode(['status'=>'success']); 
    exit;
}

// ==================================================================
// 6. ROTAS ADMIN (MANTIDAS IGUAIS)
// ==================================================================

if (strpos($action, 'admin_') === 0) {
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        http_response_code(403); echo json_encode(['error' => 'Admin only']); exit;
    }

    if ($action === 'admin_users') {
        $sql = "SELECT u.id, u.nome, u.email, u.telefone, u.plano, u.creditos_ia, u.plano_expiracao, (SELECT COUNT(*) FROM transacoes t WHERE t.usuario_id = u.id) as total_transacoes, (SELECT SUM(valor) FROM transacoes t WHERE t.usuario_id = u.id AND t.tipo = 'receita') as receita_total FROM usuarios u ORDER BY u.id DESC";
        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'admin_stats') {
        $users = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
        $trans = $pdo->query("SELECT COUNT(*) FROM transacoes")->fetchColumn();
        $receitaGlobal = $pdo->query("SELECT SUM(valor) FROM transacoes WHERE tipo in ('receita','saldo')")->fetchColumn(); 
        echo json_encode(['users' => $users, 'transacoes' => $trans, 'receita_global' => (float)$receitaGlobal]);
        exit;
    }

    if ($action === 'admin_update_user') {
        $id = $input['id'] ?? $_POST['id'];
        $tel = $input['telefone'] ?? $_POST['telefone'];
        $plano = $input['plano'] ?? $_POST['plano'];
        $sql = "UPDATE usuarios SET telefone = ?, plano = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$tel, $plano, $id])) { echo json_encode(['status' => 'success']); } else { echo json_encode(['status' => 'error']); }
        exit;
    }

    if ($action === 'admin_delete_user') {
        $id = $input['id'] ?? $_POST['id'];
        $pdo->prepare("DELETE FROM transacoes WHERE usuario_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM investimentos WHERE usuario_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
        echo json_encode(['status' => 'success']);
        exit;
    }
}
?>