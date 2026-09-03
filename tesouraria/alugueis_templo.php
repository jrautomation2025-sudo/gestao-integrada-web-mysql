<?php
session_start();
require '../configuracoes/config.php';

// Resgata mensagens da sessão (se houver) e limpa em seguida
$mensagem = $_SESSION['mensagem'] ?? '';
$erro = $_SESSION['erro'] ?? '';
unset($_SESSION['mensagem'], $_SESSION['erro']);

// Segurança de acesso (Login obrigatório)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) { 
    header("Location: login"); 
    exit; 
}

$user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['user']['id'];

// Verificação de Administrador
$isAdmin = (
    (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === 1) && (isset($_SESSION['tenant_id']) && $_SESSION['tenant_id'] === $_SESSION['user_id'] )
);

// Captura os filtros de Mês e Ano (Se vazio, assume '' para listar todos os meses)
$filtro_mes = isset($_GET['mes']) ? $_GET['mes'] : date('m');
$filtro_ano = isset($_GET['ano']) && $_GET['ano'] !== '' ? $_GET['ano'] : date('Y');

// Busca todos os responsaveis para popular o Modal
$stmtTodos = $pdo->prepare("SELECT * FROM tesouraria_alugueis_responsavel WHERE tenant_id = ? ORDER BY responsavel ASC");
$stmtTodos->execute([$user_id]);
$todosResp = $stmtTodos->fetchAll(PDO::FETCH_ASSOC);

// Processa o cadastro de novo aluguel / taxa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'cadastrar') {
    $loja_coirma = trim($_POST['loja_coirma']);
    $responsavel = trim($_POST['responsavel_id']);
    $valor = trim($_POST['valor']);
    $tipo = trim($_POST['tipo']);
    $vencimento = $_POST['vencimento'];
    $observacoes = trim($_POST['observacoes']);

    if (!empty($loja_coirma) && !empty($valor) && !empty($vencimento)) {
        try {
            $pdo->beginTransaction();

            // 1. Insere na tabela específica de aluguéis do templo
            $stmt = $pdo->prepare("
                INSERT INTO tesouraria_alugueis_templo (tenant_id, loja_coirma, responsavel_id, valor, tipo, vencimento, status, observacoes) 
                VALUES ( ?, ?, ?, ?, ?, ?, 'Pendente', ?)
            ");
            $stmt->execute([$user_id, $loja_coirma, $responsavel, $valor, $tipo, $vencimento, $observacoes]);

            // 2. Insere automaticamente na tabela oficial 'transacoes'
            $descricao_transacao = "Aluguel de Templo - " . $loja_coirma . (!empty($observacoes) ? " ({$observacoes})" : "");
            
            $stmt_trans = $pdo->prepare("
                INSERT INTO transacoes (usuario_id, descricao, valor, tipo, data_transacao, categoria, contexto) 
                VALUES (?, ?, ?, 'receita', ?, 'Geral', 'pessoal')
            ");
            $stmt_trans->execute([$user_id, $descricao_transacao, $valor, $vencimento]);

            $pdo->commit();
            // Grava mensagem na sessão e redireciona
            $_SESSION['mensagem'] = "Aluguel lançado e espelhado nas transações com sucesso!";
            $mensagem = $_SESSION['mensagem'];
            header("Location: alugueis_templo.php?mes=$filtro_mes&ano=$filtro_ano");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['erro'] = "Erro ao cadastrar e integrar: " . $e->getMessage();
            $erro = $_SESSION['erro'];
            header("Location: alugueis_templo.php?mes=$filtro_mes&ano=$filtro_ano");
            exit;
        }
    } else {
        $_SESSION['erro'] = "Preencha os campos obrigatórios (Loja, Valor e Vencimento).";
        $erro = $_SESSION['erro'];
        header("Location: alugueis_templo.php?mes=$filtro_mes&ano=$filtro_ano");
        exit;
    }
}

// Processa a edição do registro (Apenas Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'editar') {
    if (!$isAdmin) {
        $erro = "Acesso negado. Apenas administradores podem editar registros.";
    } else {
        $id = (int)$_POST['id'];
        $loja_coirma = trim($_POST['loja_coirma']);
        $responsavel = trim($_POST['responsavel_id']);
        $valor = trim($_POST['valor']);  
        $tipo = trim($_POST['tipo']);
        $vencimento = $_POST['vencimento'];
        $observacoes = trim($_POST['observacoes']);

        if (!empty($id) && !empty($loja_coirma) && !empty($valor) && !empty($vencimento)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE tesouraria_alugueis_templo 
                    SET loja_coirma = ?, responsavel_id = ?, valor = ?, tipo=?, vencimento = ?, observacoes = ? 
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([$loja_coirma, $responsavel, $valor, $tipo, $vencimento, $observacoes, $id, $user_id]);
                $_SESSION['mensagem'] = "Registro de aluguel atualizado com sucesso!";
                $mensagem = $_SESSION['mensagem'];
                header("Location: alugueis_templo.php?mes=$filtro_mes&ano=$filtro_ano");
                exit;
            } catch (Exception $e) {
                $_SESSION['erro'] = "Erro ao atualizar o registro: " . $e->getMessage();
                $erro = $_SESSION['erro'];
                header("Location: alugueis_templo.php?mes=$filtro_mes&ano=$filtro_ano");
                exit;
            }
        } else {
            $_SESSION['erro'] = "Preencha os campos obrigatórios para editar.";
            $erro = $_SESSION['erro'];
            header("Location: alugueis_templo.php?mes=$filtro_mes&ano=$filtro_ano");
            exit;
        }
    }
}

// Processa a exclusão do registro (Apenas Admin)
if (isset($_GET['excluir'])) {
    if (!$isAdmin) {
        $erro = "Acesso negado. Apenas administradores podem excluir registros.";
    } else {
        $id_excluir = (int)$_GET['excluir'];
        try {
            $stmt_del = $pdo->prepare("DELETE FROM tesouraria_alugueis_templo WHERE id = ? AND tenant_id = ?");
            $stmt_del->execute([$id_excluir, $user_id]);
            $_SESSION['mensagem'] = "Registro excluído com sucesso!";
            $mensagem = $_SESSION['mensagem'];
            header("Location: alugueis_templo.php?mes=$filtro_mes&ano=$filtro_ano");
            exit;
        } catch (Exception $e) {
            $_SESSION['erro']  = "Erro ao excluir o registro: " . $e->getMessage();
            $erro = $_SESSION['erro'];
            header("Location: alugueis_templo.php?mes=$filtro_mes&ano=$filtro_ano");
            exit;
        }
    }
}

// Processa a baixa (marcar como pago)
if (isset($_GET['baixar'])) {
    $id_baixa = (int)$_GET['baixar'];
    $data_hoje = date('Y-m-d');
    $stmt = $pdo->prepare("UPDATE tesouraria_alugueis_templo SET status = 'Pago', pagamento = ? WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$data_hoje, $id_baixa, $user_id]);
    header("Location: alugueis_templo.php?mes=$filtro_mes&ano=$filtro_ano");
    exit;
}

// Processa o Disparo do Webhook para o n8n (Enviar Recibo)
if (isset($_GET['enviar_recibo'])) {
    $id_recibo = (int)$_GET['enviar_recibo'];
    
    $stmt_rec = $pdo->prepare("SELECT tt.*, tr.* FROM tesouraria_alugueis_templo tt
                               LEFT JOIN tesouraria_alugueis_responsavel tr ON tt.responsavel_id = tr.id 
                               WHERE tt.id = ? AND tt.tenant_id = ?");
    $stmt_rec->execute([$id_recibo, $user_id]);
    $dado_aluguel = $stmt_rec->fetch(PDO::FETCH_ASSOC);
    
    $stmtLodge = $pdo->prepare("
    SELECT l.nome, l.url_logo, l.endereco, l.localizacao
    FROM secretaria_lojas l
    JOIN usuarios u ON u.loja_id = l.id
    WHERE u.id = ? 
    LIMIT 1
    ");
    $stmtLodge->execute([$user_id]);
    $lodge = $stmtLodge->fetch(PDO::FETCH_ASSOC);
    
    if ($dado_aluguel) {
        $webhook_url = "https://n8n-prod.jrtec.com.br/webhook/gerar-recibo-aluguel"; 
        
        $payload = json_encode([
            "acao" => "enviar_recibo_aluguel",
            "tenant_id" => $user_id,
            "id_aluguel" => $dado_aluguel['id'],
            "loja_coirma" => $dado_aluguel['loja_coirma'],
            "responsavel" => $dado_aluguel['responsavel'],
            "telefone" => $dado_aluguel['telefone'],
            "email" => $dado_aluguel['email'],
            "valor" => $dado_aluguel['valor'],
            "forma_pagamento" => $dado_aluguel['tipo'],
            "vencimento" => $dado_aluguel['vencimento'],
            "status" => $dado_aluguel['status'],
            "pagamento" => $dado_aluguel['pagamento'],
            "observacoes" => $dado_aluguel['observacoes'],
            "loja" => $lodge['nome'],
            "logo" => $lodge['url_logo'],
            "endereco" => $lodge['endereco'],
            "cidade" => $lodge['localizacao']
            
        ]);
        
        $ch = curl_init($webhook_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload),
            'x-gestao-api-key: ' . getenv('API_TOKEN')
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code >= 200 && $http_code < 300) {
            $_SESSION['mensagem'] = "Recibo enviado com sucesso para o responsável: " . ($dado_aluguel['responsavel'] ?: $dado_aluguel['loja_coirma']);
            $mensagem = $_SESSION['mensagem'];
            header("Location: alugueis_templo.php?mes=$filtro_mes&ano=$filtro_ano");
            exit;
        } else {
            $_SESSION['erro'] = "Falha ao comunicar com o webhook do n8n (Código HTTP: $http_code).";
            $erro = $_SESSION['erro'];
            header("Location: alugueis_templo.php?mes=$filtro_mes&ano=$filtro_ano");
            exit;
        }
    }
}

// 1. Resumo Financeiro (Se $filtro_mes estiver vazio, filtra apenas pelo ano)
$stmt_resumo = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN status = 'Pago' THEN valor ELSE 0 END) as total_recebido,
        SUM(CASE WHEN status = 'Pendente' THEN valor ELSE 0 END) as total_pendente
    FROM tesouraria_alugueis_templo 
    WHERE tenant_id = ? AND (? = '' OR MONTH(vencimento) = ?) AND YEAR(vencimento) = ?
");
$stmt_resumo->execute([$user_id, $filtro_mes, $filtro_mes, $filtro_ano]);
$resumo = $stmt_resumo->fetch(PDO::FETCH_ASSOC);

$total_recebido = $resumo['total_recebido'] ?? 0;
$total_pendente = $resumo['total_pendente'] ?? 0;

// 2. Lista de Aluguéis Registrados (Dinâmica para mês específico ou todos os meses do ano)
$stmt_lista = $pdo->prepare("
    SELECT tt.*, tr.responsavel, tr.telefone, tr.email FROM tesouraria_alugueis_templo tt
    LEFT JOIN tesouraria_alugueis_responsavel tr
    ON tt.responsavel_id = tr.id
    WHERE tt.tenant_id = ? AND (? = '' OR MONTH(tt.vencimento) = ?) AND YEAR(tt.vencimento) = ? 
    ORDER BY tt.vencimento DESC
");
$stmt_lista->execute([$user_id, $filtro_mes, $filtro_mes, $filtro_ano]);
$alugueis = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);

// Texto amigável para exibição do período selecionado
$texto_periodo = ($filtro_mes === '') ? "Ano de " . $filtro_ano : $filtro_mes . '/' . $filtro_ano;
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aluguel de Templo - Tesouraria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --bg-dark: #0f172a; --bg-card: #1e293b; --gold: #cfa34e; --text-light: #f1f5f9; }
        body { background-color: var(--bg-dark); color: var(--text-light); font-family: 'Segoe UI', sans-serif; overflow-x: hidden; }

        .card-custom { background: var(--bg-card); border: 1px solid #334155; border-radius: 12px; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .text-gold { color: var(--gold) !important; }
        .btn-gold { background: var(--gold); border: none; color: #000; font-weight: bold; transition: transform 0.2s; }
        .btn-gold:hover { background: #b8860b; color: #fff; }
        
        .sidebar { width: 260px; position: fixed; top: 0; left: 0; height: 100vh; background-color: var(--bg-card); border-right: 1px solid #334155; z-index: 1000; overflow-y: auto; }
        .main-content { margin-left: 260px; min-height: 100vh; width: calc(100% - 260px); padding: 20px; }
        .mobile-header { display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px; background-color: var(--bg-card); border-bottom: 1px solid #334155; z-index: 2000; align-items: center; padding: 0 20px; justify-content: space-between; }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); z-index: 3000; width: 280px; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 15px; padding-top: 80px; }
            .mobile-header { display: flex !important; }
        }

        @media print {
            body { background-color: #fff !important; color: #000 !important; }
            .sidebar, .mobile-header, .btn, form, .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 0 !important; }
            .card-custom { background: #fff !important; border: 1px solid #ddd !important; box-shadow: none !important; color: #000 !important; }
            .table-dark { background-color: #fff !important; color: #000 !important; }
            .table-dark th, .table-dark td { color: #000 !important; border-color: #ddd !important; background-color: transparent !important; }
            .text-gold { color: #856404 !important; }
            .badge { border: 1px solid #000; color: #000 !important; background: transparent !important; }
            .print-header { display: block !important; margin-bottom: 20px; text-align: center; }
        }
        .print-header { display: none; }
    </style>
</head>
<body>

<div class="mobile-header no-print">
    <div class="d-flex align-items-center">
        <button class="btn btn-outline-warning btn-sm me-3" onclick="toggleMobileMenu()"><i class="fas fa-bars"></i></button>
        <span style="font-family: 'Cinzel', serif; color: var(--gold); font-weight: bold;">ALUGUEL DE TEMPLO</span>
    </div>
</div>

<div class="sidebar-backdrop no-print" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>

<div class="no-print">
    <?php include 'menu.php'; ?>
</div>

<main class="main-content">
    <div class="container-fluid py-4 px-4">
        
        <div class="print-header">
            <h2 style="font-family: 'Cinzel', serif; font-weight: bold;">Relatório de Aluguel do Templo</h2>
            <p>Período Referência: <?= $texto_periodo ?> - Gerado em: <?= date('d/m/Y H:i') ?></p>
            <hr>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 no-print">
            <div>
                <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;">
                    <i class="fas fa-building me-2 text-warning"></i> Aluguel do Templo (Lojas Coirmãs)
                </h2>
                <p class="text-warning mb-0">Controle de taxas, relatórios e envio de recibos</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-light px-3 py-2" onclick="window.print()">
                    <i class="fas fa-print me-2"></i> Imprimir Relatório
                </button>
                <button class="btn btn-warning px-4 py-2" data-bs-toggle="modal" data-bs-target="#modalNovoAluguel">
                    <i class="fas fa-plus me-2"></i> Novo Lançamento
                </button>
            </div>
        </div>

        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                <?= $mensagem ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($erro)): ?>
            <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
                <?= $erro ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Formulário de Filtro por Mês e Ano -->
        <div class="card-custom mb-4 no-print">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-bold">MÊS</label>
                    <select name="mes" class="form-select bg-secondary text-white border-0">
                        <option value="" <?= $filtro_mes === '' ? 'selected' : '' ?>>Todos os Meses</option>
                        <?php 
                        $meses = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho','07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
                        foreach($meses as $num => $nome):
                        ?>
                            <option value="<?= $num ?>" <?= $filtro_mes === $num ? 'selected' : '' ?>><?= $nome ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-bold">ANO</label>
                    <select name="ano" class="form-select bg-secondary text-white border-0">
                        <?php for($y = date('Y'); $y >= 2024; $y--): ?>
                            <option value="<?= $y ?>" <?= $filtro_ano == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                        <i class="fas fa-filter me-2"></i> Filtrar
                    </button>
                    <a href="alugueis_templo.php" class="btn btn-outline-secondary w-50" title="Limpar Filtro">Limpar</a>
                </div>
            </form>
        </div>

        <!-- Cards de Resumo -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card-custom">
                    <h6 class="text-muted mb-1"><i class="fas fa-check-circle text-success me-2"></i> Total Recebido (<?= $texto_periodo ?>)</h6>
                    <h3 class="mb-0 fw-bold text-success">R$ <?= number_format($total_recebido, 2, ',', '.') ?></h3>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card-custom">
                    <h6 class="text-muted mb-1"><i class="fas fa-clock text-warning me-2"></i> Pendente de Recebimento (<?= $texto_periodo ?>)</h6>
                    <h3 class="mb-0 fw-bold text-warning">R$ <?= number_format($total_pendente, 2, ',', '.') ?></h3>
                </div>
            </div>
        </div>

        <!-- Tabela de Lançamentos -->
        <div class="card-custom">
            <h5 class="text-gold mb-3"><i class="fas fa-list me-2"></i> Lançamentos (<?= $texto_periodo ?>)</h5>
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Loja</th>
                            <th>Responsável</th>
                            <th>Vencimento</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <th>Pagamento</th>
                            <th class="text-end no-print">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($alugueis) > 0): ?>
                            <?php foreach ($alugueis as $a): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($a['loja_coirma']) ?></div>
                                        <?php if(!empty($a['observacoes'])): ?>
                                            <small class="text-muted"><?= htmlspecialchars($a['observacoes']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($a['responsavel'] ?: '-') ?></div>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($a['telefone'] ?: '') ?> <?= !empty($a['telefone']) && !empty($a['email']) ? '•' : '' ?> <?= htmlspecialchars($a['email'] ?: '') ?>
                                        </small>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($a['vencimento'])) ?></td>
                                    <td class="text-gold fw-bold">R$ <?= number_format($a['valor'], 2, ',', '.') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $a['status'] === 'Pago' ? 'success' : 'warning text-dark' ?>">
                                            <?= $a['status'] ?>
                                        </span>
                                    </td>
                                    <td><?= $a['pagamento'] ? date('d/m/Y', strtotime($a['pagamento'])) : '-' ?></td>
                                    <td class="text-end no-print">
                                        <div class="d-flex justify-content-end gap-1">
                                            
                                            <?php if ($isAdmin): ?>
                                                <button class="btn btn-sm btn-outline-warning" title="Editar Registro" onclick='abrirModalEditar(<?= json_encode($a, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <a href="?excluir=<?= $a['id'] ?>&mes=<?= $filtro_mes ?>&ano=<?= $filtro_ano ?>" class="btn btn-sm btn-outline-danger" title="Excluir Registro" onclick="return confirm('Tem certeza que deseja excluir este lançamento de aluguel?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($a['status'] === 'Pendente'): ?>
                                                <a href="?baixar=<?= $a['id'] ?>&mes=<?= $filtro_mes ?>&ano=<?= $filtro_ano ?>" class="btn btn-sm btn-outline-success" title="Dar baixa">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="btn btn-sm btn-outline-secondary text-muted"><i class="fas fa-check-double" title="Liquidado"></i></span>
                                            <?php endif; ?>
                                            
                                            <?php if ($a['status'] === 'Pago'): ?>
                                                <a href="?enviar_recibo=<?= $a['id'] ?>&mes=<?= $filtro_mes ?>&ano=<?= $filtro_ano ?>" class="btn btn-sm btn-outline-info" title="Enviar Recibo">
                                                    <i class="fas fa-paper-plane"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="btn btn-sm btn-outline-secondary text-muted"><i class="fas fa-paper-plane" title="Enviar Recibo"></i></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Nenhum registro encontrado para este período.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<!-- Modal Novo Lançamento -->
<div class="modal fade no-print" id="modalNovoAluguel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary">
            <form method="POST">
                <input type="hidden" name="acao" value="cadastrar">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-gold"><i class="fas fa-plus-circle me-2"></i> Lançar Aluguel de Templo</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted">Nome da Loja / Oriente *</label>
                        <input type="text" name="loja_coirma" class="form-control bg-secondary text-white border-0" required placeholder="Ex: Loja Fraternidade nº 05">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Responsável *</label>
                            <select class="form-control bg-secondary text-white border-0" name="responsavel_id" id="novo_responsavel_id" required onchange="preencherContatoNovo(this)">
                                <option value="">Selecione o responsável...</option>
                                <?php foreach ($todosResp as $r): ?>
                                    <option value="<?= $r['id'] ?>" data-telefone="<?= htmlspecialchars($r['telefone'] ?? '') ?>" data-email="<?= htmlspecialchars($r['email'] ?? '') ?>">
                                        <?= htmlspecialchars($r['responsavel']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Telefone / WhatsApp</label>
                            <input type="text" name="telefone" id="novo_telefone" class="form-control bg-secondary text-white border-0" placeholder="(81) 99999-9999">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">E-mail para envio de recibo</label>
                            <input type="email" name="email" id="novo_email" class="form-control bg-secondary text-white border-0" placeholder="email@loja.com">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Tipo / Forma de Pag. *</label>
                            <select name="tipo" class="form-control bg-secondary text-white border-0" required>
                                <option value="Pix">Pix</option>
                                <option value="Dinheiro">Dinheiro</option>
                                <option value="Transferência">Transferência</option>
                                <option value="Boleto">Boleto</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Valor (R$) *</label>
                            <input type="text" name="valor" class="form-control bg-secondary text-white border-0" required placeholder="0,00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Data de Vencimento *</label>
                            <input type="date" name="vencimento" class="form-control bg-secondary text-white border-0" required value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted">Observações (Mês de referência, etc.)</label>
                        <textarea name="observacoes" class="form-control bg-secondary text-white border-0" rows="2" placeholder="Ex: Sessão Magna"></textarea>
                    </div>
                </div>
                <div class="modal-header border-secondary justify-content-end">
                    <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning btn-sm px-4">Salvar e Lançar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Lançamento (Apenas Admin) -->
<?php if ($isAdmin): ?>
<div class="modal fade no-print" id="modalEditarAluguel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary">
            <form method="POST">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-gold"><i class="fas fa-edit me-2"></i> Editar Lançamento de Aluguel</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted">Nome da Loja / Oriente *</label>
                        <input type="text" name="loja_coirma" id="edit_loja_coirma" class="form-control bg-secondary text-white border-0" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Responsável *</label>
                            <select class="form-control bg-secondary text-white border-0" name="responsavel_id" id="edit_responsavel_id" required onchange="preencherContatoEditar(this)">
                                <option value="">Selecione o responsável...</option>
                                <?php foreach ($todosResp as $r): ?>
                                    <option value="<?= $r['id'] ?>" data-telefone="<?= htmlspecialchars($r['telefone'] ?? '') ?>" data-email="<?= htmlspecialchars($r['email'] ?? '') ?>">
                                        <?= htmlspecialchars($r['responsavel']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Telefone / WhatsApp</label>
                            <input type="text" id="edit_telefone" class="form-control bg-secondary text-white border-0" readonly>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">E-mail</label>
                            <input type="email" id="edit_email" class="form-control bg-secondary text-white border-0" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Tipo / Forma de Pag. *</label>
                            <select name="tipo" id="edit_tipo" class="form-control bg-secondary text-white border-0" required>
                                <option value="Pix">Pix</option>
                                <option value="Dinheiro">Dinheiro</option>
                                <option value="Transferência">Transferência</option>
                                <option value="Boleto">Boleto</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Valor (R$) *</label>
                            <input type="text" name="valor" id="edit_valor" class="form-control bg-secondary text-white border-0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Data de Vencimento *</label>
                            <input type="date" name="vencimento" id="edit_vencimento" class="form-control bg-secondary text-white border-0" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted">Observações</label>
                        <textarea name="observacoes" id="edit_observacoes" class="form-control bg-secondary text-white border-0" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-header border-secondary justify-content-end">
                    <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning btn-sm px-4">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    function toggleMobileMenu() {
        const sidebar = document.querySelector('.sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (sidebar) sidebar.classList.toggle('show');
        if (backdrop) backdrop.classList.toggle('show');
    }
    
    function preencherContatoNovo(select) {
        const selectedOption = select.options[select.selectedIndex];
        const telefone = selectedOption.getAttribute('data-telefone') || '';
        const email = selectedOption.getAttribute('data-email') || '';

        document.getElementById('novo_telefone').value = telefone;
        document.getElementById('novo_email').value = email;
    }
    
    function preencherContatoNovo(select) {
        const selectedOption = select.options[select.selectedIndex];
        document.getElementById('novo_telefone').value = selectedOption.getAttribute('data-telefone') || '';
        document.getElementById('novo_email').value = selectedOption.getAttribute('data-email') || '';
    }

    function preencherContatoEditar(select) {
        const selectedOption = select.options[select.selectedIndex];
        document.getElementById('edit_telefone').value = selectedOption.getAttribute('data-telefone') || '';
        document.getElementById('edit_email').value = selectedOption.getAttribute('data-email') || '';
    }

    function abrirModalEditar(dado) {
        document.getElementById('edit_id').value = dado.id;
        document.getElementById('edit_loja_coirma').value = dado.loja_coirma;
        document.getElementById('edit_responsavel_id').value = dado.responsavel_id || '';
        document.getElementById('edit_tipo').value = dado.tipo || 'Pix';
        document.getElementById('edit_valor').value = dado.valor;
        document.getElementById('edit_vencimento').value = dado.vencimento;
        document.getElementById('edit_observacoes').value = dado.observacoes || '';

        // Dispara o preenchimento automático do telefone/e-mail de acordo com o responsável salvo
        const selectResp = document.getElementById('edit_responsavel_id');
        preencherContatoEditar(selectResp);

        var myModal = new bootstrap.Modal(document.getElementById('modalEditarAluguel'));
        myModal.show();
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
