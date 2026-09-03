<?php
session_start();
require '../configuracoes/config.php';

// Segurança
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) { header("Location: ./login"); exit; }
$user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['user']['id'];

// 1. CAPTURA O CONTEXTO (PESSOAL OU EMPRESA)
$contexto = $_SESSION['contexto_atual'] ?? 'pessoal';

// 2. FILTRO DE DATA (Padrão: Mês Atual)
//$dia_filtro = $_GET['dia'] ?? date('d');
$mes_filtro = $_GET['mes'] ?? date('m');
$ano_filtro = $_GET['ano'] ?? date('Y');

// =========================================================
// LÓGICA DO FILTRO (Específico ou Todos os Meses)
// =========================================================
if ($mes_filtro === 'todos') {
    $filtroMesSql = ""; // Não filtra por mês
    $paramsSQL = [$user_id, $contexto, $ano_filtro];
    // A data limite para o Saldo Anterior é o primeiro dia do ano selecionado
    $dataLimiteSaldoAnterior = $ano_filtro . '-01-01'; 
} else {
    $filtroMesSql = " AND MONTH(data_transacao) = ?";
    $paramsSQL = [$user_id, $contexto, $ano_filtro, $mes_filtro];
    // A data limite para o Saldo Anterior é o primeiro dia do mês selecionado
    $dataLimiteSaldoAnterior = $ano_filtro . '-' . str_pad($mes_filtro, 2, '0', STR_PAD_LEFT) . '-01';
}

// =========================================================
// CONSULTAS SQL
// =========================================================

// 0. CALCULA O SALDO ANTERIOR (Tudo antes da data limite)
$sqlAnterior = "SELECT SUM(CASE WHEN tipo in ('receita','saldo','mensalidade','tronco') THEN valor ELSE -valor END) as saldo_anterior 
                FROM transacoes 
                WHERE usuario_id = ? AND contexto = ? AND data_transacao >= ?";
$stmtAnterior = $pdo->prepare($sqlAnterior);
$stmtAnterior->execute([$user_id, $contexto, $dataLimiteSaldoAnterior]);
$saldoAnterior = $stmtAnterior->fetchColumn() ?: 0;

// 1. BUSCA O DETALHAMENTO DOS LANÇAMENTOS DO PERÍODO
$sqlDetalhes = "SELECT data_transacao, descricao, valor, tipo 
                FROM transacoes 
                WHERE usuario_id = ? 
                AND contexto = ? 
                AND YEAR(data_transacao) = ?" . $filtroMesSql . " 
                ORDER BY data_transacao ASC";
$stmtDetalhes = $pdo->prepare($sqlDetalhes);
$stmtDetalhes->execute($paramsSQL);
$lancamentos_detalhados = $stmtDetalhes->fetchAll(PDO::FETCH_ASSOC);

// --- SEPARANDO RECEITAS E DESPESAS (FIXAS E VARIÁVEIS) PELO CAMPO 'TIPO' ---
$listaReceitas = [];
$listaDespesasFixas = [];
$listaDespesasVariaveis = [];

$subtotalReceitas = 0;
$subtotalDespesasFixas = 0;
$subtotalDespesasVariaveis = 0;

foreach ($lancamentos_detalhados as $l) {
    $tipo = strtolower(trim($l['tipo'])); 
    
    if ($tipo === 'receita' || $tipo === 'saldo' || $tipo === 'mensalidade' || $tipo === 'tronco') {
        $listaReceitas[] = $l;
        $subtotalReceitas += $l['valor'];
    } elseif ($tipo === 'fixo') {
        $listaDespesasFixas[] = $l;
        $subtotalDespesasFixas += $l['valor'];
    } else {
        $listaDespesasVariaveis[] = $l;
        $subtotalDespesasVariaveis += $l['valor'];
    }
}
// --------------------------------------------------------------------------

// --- BUSCA DE INVESTIMENTOS COM FILTRO DE MÊS/ANO ---
// Assumindo que você já tem as variáveis $mes e $ano definidas no topo do seu relatório
$sql_inv = "SELECT ativo, categoria, valor_investido, valor_atual, data_aporte 
            FROM investimentos 
            WHERE usuario_id = ? 
            AND contexto = ?
            ORDER BY data_aporte ASC";

$stmt_inv = $pdo->prepare($sql_inv);
// Passamos o $mes e $ano para a consulta
$stmt_inv->execute([$user_id, $contexto]);
$lista_investimentos = $stmt_inv->fetchAll(PDO::FETCH_ASSOC);

// Vamos somar os dois valores separadamente
$total_investido = 0;
$total_atual = 0;

foreach ($lista_investimentos as $inv) {
    $total_investido += $inv['valor_investido'];
    $total_atual += $inv['valor_atual'];
}

// Mantemos o subtotal para a tabela de detalhamento lá de baixo usar
$subtotal_investimentos = $total_investido;

// A. TOTAIS DO PERÍODO SELECIONADO
$sqlResumo = "SELECT 
                SUM(CASE WHEN tipo in ('receita','saldo','mensalidade','tronco') THEN valor ELSE 0 END) as receitas,
                SUM(CASE WHEN tipo not in ('receita','saldo','mensalidade','tronco') THEN valor ELSE 0 END) as despesas
              FROM transacoes 
              WHERE usuario_id = ? AND contexto = ? 
              AND YEAR(data_transacao) = ?" . $filtroMesSql;
$stmt = $pdo->prepare($sqlResumo);
$stmt->execute($paramsSQL);
$resumo = $stmt->fetch(PDO::FETCH_ASSOC);

$receitas = $resumo['receitas'] ?? 0;
$despesas = $resumo['despesas'] ?? 0;
$saldo_periodo = $receitas - $despesas;

// Saldo Acumulado Real (Anterior + Período Atual)
$saldoFinalAcumulado = $saldoAnterior + $saldo_periodo;

$totalSaldoAnteriorAcumulado = $saldo_periodo;


// B. DESPESAS POR CATEGORIA (Mantido apenas para preencher a Tabela de Detalhamento)
$sqlCat = "SELECT categoria, SUM(valor) as total 
           FROM transacoes 
           WHERE usuario_id = ? AND contexto = ? AND tipo not in ('receita','saldo','mensalidade','tronco')
           AND YEAR(data_transacao) = ?" . $filtroMesSql . "
           GROUP BY categoria ORDER BY total DESC";
$stmt = $pdo->prepare($sqlCat);
$stmt->execute($paramsSQL);
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);


// --- NOVO CÓDIGO: DADOS MACRO PARA O GRÁFICO DE ROSCA ---
$macroLabels = ['Investimentos', 'Saldo', 'Receitas', 'Despesas'];
// Evita erro no JS caso o saldo seja negativo (Gráfico de pizza não aceita número negativo)
$saldoGrafico = ($totalSaldoAnteriorAcumulado > 0) ? $totalSaldoAnteriorAcumulado : 0;
$macroValues = [$subtotal_investimentos, $saldoGrafico, $receitas, $despesas];
// --------------------------------------------------------


// C. EVOLUÇÃO ÚLTIMOS 6 MESES (Para o Gráfico de Barras - Independe do filtro)

// 1. Criar a base dos últimos 6 meses para garantir o alinhamento no gráfico
$mesesPT = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun','07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];
$dadosGrafico = [];

// Gera os últimos 6 meses a partir de hoje
for ($i = 5; $i >= 0; $i--) {
    $mes_ano = date('Y-m', strtotime("-$i month"));
    $dadosGrafico[$mes_ano] = [
        'label' => $mesesPT[substr($mes_ano, 5, 2)],
        'rec' => 0, 'desp' => 0, 'sald' => 0, 'inv' => 0
    ];
}

// Determina a data de corte (1º dia de 5 meses atrás)
$dataCorte = date('Y-m-01', strtotime("-5 month"));

// 2. Busca Transações dos últimos 6 meses
$sqlEvo = "SELECT 
            DATE_FORMAT(data_transacao, '%Y-%m') as mes_ano,
            SUM(CASE WHEN tipo in ('receita','mensalidade','tronco') THEN valor ELSE 0 END) as rec,
            SUM(CASE WHEN tipo in ('fixo','variavel','doação') THEN valor ELSE 0 END) as desp,
            SUM(CASE WHEN tipo = 'saldo' THEN valor ELSE 0 END) as sald
           FROM transacoes 
           WHERE usuario_id = ? AND contexto = ? 
           AND data_transacao >= ?
           GROUP BY mes_ano";
$stmt = $pdo->prepare($sqlEvo);
$stmt->execute([$user_id, $contexto, $dataCorte]);
$evolucao = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Preenche a base com os dados das transações
foreach($evolucao as $e) {
    $ma = $e['mes_ano'];
    if(isset($dadosGrafico[$ma])) {
        $dadosGrafico[$ma]['rec'] = $e['rec'];
        $dadosGrafico[$ma]['desp'] = $e['desp'];
        $dadosGrafico[$ma]['sald'] = $e['sald'];
    }
}

// 3. Busca Investimentos dos últimos 6 meses
$sqlEvoInv = "SELECT 
            DATE_FORMAT(data_aporte, '%Y-%m') as mes_ano,
            SUM(CASE WHEN categoria is not null THEN valor_atual ELSE 0 END) as inv
           FROM investimentos 
           WHERE usuario_id = ? AND contexto = ? 
           AND data_aporte >= ?
           GROUP BY mes_ano";
$stmt = $pdo->prepare($sqlEvoInv);
$stmt->execute([$user_id, $contexto, $dataCorte]);
$evolucaoInv = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Preenche a base com os dados dos investimentos
foreach($evolucaoInv as $e) {
    $ma = $e['mes_ano'];
    if(isset($dadosGrafico[$ma])) {
        $dadosGrafico[$ma]['inv'] = $e['inv'];
    }
}

// 4. Monta os arrays finais isolados exigidos pelo Chart.js
$evoLabels = []; $evoRec = []; $evoDesp = []; $evoSald = []; $evoInv = [];
foreach($dadosGrafico as $d) {
    $evoLabels[] = $d['label'];
    $evoRec[]    = $d['rec'];
    $evoDesp[]   = $d['desp'];
    $evoSald[]   = $d['sald'];
    //$evoInv[]    = $d['inv'];
}
?>


<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório - Gestão Integrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { --bg-dark: #0f172a; --bg-card: #1e293b; --gold: #cfa34e; --text-light: #f1f5f9; }
        body { background-color: var(--bg-dark); color: var(--text-light); font-family: 'Segoe UI', sans-serif; }

        .card-custom { background: var(--bg-card); border: 1px solid #334155; border-radius: 12px; padding: 20px; height: 100%; }
        .text-gold { color: var(--gold) !important; }
        .bg-gold { background-color: var(--gold) !important; color: #000; }
        .btn-gold { background: var(--gold); border: none; color: #000; font-weight: bold; }
        .btn-gold:hover { background: #b8860b; color: #fff; }

        /* Estilo para Impressão */
        @media print {
            .sidebar, .no-print, .btn, form { display: none !important; }
            body { padding-left: 0 !important; background-color: #fff !important; color: #000 !important; }
            .card-custom { border: 1px solid #ccc !important; background: #fff !important; color: #000 !important; box-shadow: none !important; }
            .text-white { color: #000 !important; }
            .text-muted { color: #555 !important; }
            canvas { max-height: 300px !important; }
            
            @page { margin: 1cm; }
            body { padding: 0 !important; margin: 0 !important; }
            .col-md-9, .col-lg-10 { width: 100% !important; max-width: 100% !important; }
            .card-custom { margin-bottom: 20px !important; page-break-inside: avoid; }
            
            .text-gold, .text-light { color: #000 !important; }
            .text-success { color: #198754 !important; }
            .text-danger { color: #dc3545 !important; }
            
            .table-responsive { overflow: visible !important; }
            table { border-collapse: collapse !important; width: 100% !important; }
            th, td { border: 1px solid #ddd !important; color: #000 !important; padding: 8px !important; }
            
            .badge { background-color: transparent !important; color: #000 !important; border: 1px solid #000 !important; }
        }
        
     /* HEADER MOBILE */
    .mobile-header {
        display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px;
        background-color: var(--bg-surface); border-bottom: 1px solid var(--border-color);
        z-index: 2000; align-items: center; padding: 0 20px; justify-content: space-between;
        box-shadow: 0 2px 10px rgba(0,0,0,0.3);
    }

    /* --- MOBILE & TABLET (Até 992px) --- */
    @media (max-width: 992px) {
        .sidebar { transform: translateX(-100%); z-index: 3000; width: 280px; box-shadow: 5px 0 15px rgba(0,0,0,0.5); }
        .sidebar.show { transform: translateX(0); }
        .main-content { margin-left: 0 !important; width: 100% !important; padding: 15px; padding-top: 80px; }
        .mobile-header { display: flex !important; }
    }
    </style>
</head>
<body>

   <!-- Barra Superior Mobile (Visível apenas em celulares) -->
<div class="mobile-topbar">
    <div class="d-flex align-items-center">
        <button class="btn btn-outline-warning btn-sm me-3" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <span style="font-family: 'Cinzel', serif; color: var(--gold); font-weight: bold;">TESOURARIA</span>
    </div>
    <span class="text-white small">Painel</span>
</div>

<!-- Backdrop escuro para fechar o menu ao clicar fora -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>

    <?php include 'menu.php'; ?>
    
    <div class="main-content">
    
    <div class="container-fluid py-4 px-4">
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            
             <div class="page-header mb-4">
                <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;"><i class="fas fa-file-invoice-dollar me-2 text-warning"></i> Relatórios Financeiros</h2>
                <p class="text-warning">Visualize seus resultados</p>
            </div>

            <form method="GET" class="d-flex gap-2 align-items-center bg-card p-2 rounded border border-secondary">
                <!--
                <select name="dia" class="form-select form-select-sm bg-dark text-light border-secondary">
                    <option value="todos" <?php echo (isset($dia_filtro) && 'todos' == $dia_filtro) ? 'selected' : ''; ?>>Todos os Dias</option>
                    <?php for($i=1; $i<=31; $i++): $d = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                        <option value="<?php echo $d; ?>" <?php echo (isset($dia_filtro) && $d == $dia_filtro) ? 'selected' : ''; ?>>
                            <?php echo $d; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                -->
                <select name="mes" class="form-select form-select-sm bg-dark text-light border-secondary">
                    <option value="todos" <?php echo ('todos' == $mes_filtro) ? 'selected' : ''; ?>>Todos os Meses</option>
                    <?php for($i=1; $i<=12; $i++): $m = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                        <option value="<?php echo $m; ?>" <?php echo ($m == $mes_filtro) ? 'selected' : ''; ?>>
                            <?php echo $mesesPT[$m]; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <select name="ano" class="form-select form-select-sm bg-dark text-light border-secondary">
                    <?php for($i=date('Y'); $i>=2024; $i--): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($i == $ano_filtro) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-gold"><i class="fas fa-filter"></i></button>
                <?php if ($_SESSION['is_admin'] == 1): ?>
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-light no-print" title="Imprimir Relatório"><i class="fas fa-print"></i></button>
                
                <!-- NOVO BOTÃO DE EMAIL -->
                <button type="button" onclick="enviarRelatorioEmail()" class="btn btn-sm btn-outline-info no-print" title="Enviar Relatório por E-mail aos Membros">
                    <i class="fas fa-envelope"></i>
                </button>
                <?php endif; ?>
            </form>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card-custom border-success" style="border-left: 5px solid #22c55e;">
                    <small class="text-muted text-uppercase fw-bold">Entradas (Período)</small>
                    <h3 class="fw-bold mt-2 text-success">R$ <?php echo number_format($receitas, 2, ',', '.'); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-custom border-danger" style="border-left: 5px solid #ef4444;">
                    <small class="text-muted text-uppercase fw-bold">Saídas (Período)</small>
                    <h3 class="fw-bold mt-2 text-danger">R$ <?php echo number_format($despesas, 2, ',', '.'); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <?php 
                    $corSaldoFinal = ($saldoFinalAcumulado >= 0) ? 'text-warning' : 'text-danger'; 
                    $corBordaFinal = ($saldoFinalAcumulado >= 0) ? 'border-gold' : 'border-danger';
                ?>
                <div class="card-custom <?php echo $corBordaFinal; ?>" style="border-left: 5px solid <?php echo ($saldoFinalAcumulado >= 0) ? 'var(--gold)' : '#ef4444'; ?>;">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <small class="text-muted text-uppercase fw-bold">Saldo Atual (Período)</small>
                    </div>
                    <h3 class="fw-bold <?php echo $corSaldoFinal; ?> mt-2">
                        R$ <?php echo number_format($saldo_periodo, 2, ',', '.'); ?>
                    </h3>
                </div>
            </div>
            <div class="col-md-3">
                <!-- CARD DE INVESTIMENTOS NO TOPO -->
                <!-- Adapte as classes da div externa (como col-md-3) se necessário para manter o seu grid -->
                <div class="card-custom border-primary p-3 h-100" style="border: 1px solid #334155; border-left: 4px solid #0d6efd !important; border-radius: 8px; background: #1e293b;">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <small class="text-muted text-uppercase fw-bold">Investimentos (Período)</small>
                    </div>
    
                    <!-- Linha com o Valor Atual -->
                    <div class="small text-muted mb-1">
                        Valor Investido: 
                        <span class="text-info fw-bold">R$ <?= number_format($total_investido, 2, ',', '.') ?></span>
                    </div>
    
                    <!-- Linha principal com o Valor Investido -->
                    <h4 class="text-primary fw-bold mb-0">
                        R$ <?= number_format($total_atual, 2, ',', '.') ?>
                    </h4>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card-custom">
                    <!-- Título Atualizado -->
                    <h6 class="fw-bold text-white mb-4">Visão Geral (Investimentos, Saldo, Receitas e Despesas)</h6>
                    <?php if($subtotal_investimentos > 0 || $saldoGrafico > 0 ||$receitas > 0 || $despesas > 0 ): ?>
                        <div style="height: 250px;">
                            <canvas id="catChart"></canvas>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-5">Sem dados neste período.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card-custom">
                    <h6 class="fw-bold text-white mb-4">Evolução (Últimos 6 meses de lançamentos)</h6>
                    <div style="height: 250px;">
                        <canvas id="evoChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-12">
                <div class="card-custom">
                    <h6 class="fw-bold text-white mb-3">Detalhamento de Custos</h6>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Categoria</th>
                                    <th>Total Gasto</th>
                                    <th style="width: 50%;">Representatividade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($categorias as $c): 
                                    // Calcula o percentual com base nas RECEITAS (o total de 100%)
                                    // Se não houver receita no mês, usa o total de despesas como base para evitar erro
                                    $baseCalculo = ($receitas > 0) ? $receitas : (($despesas > 0) ? $despesas : 1);
                                    
                                    $perc = ($c['total'] / $baseCalculo) * 100;
                                    
                                    // Trava a barra visualmente em 100% (caso você gaste mais do que ganhou no mês)
                                    $larguraBarra = ($perc > 100) ? 100 : $perc;
                                ?>
                                <tr>
                                    <td><?php echo $c['categoria']; ?></td>
                                    <td class="fw-bold text-danger">R$ <?php echo number_format($c['total'], 2, ',', '.'); ?></td>
                                    <td>
                                        <div class="progress" style="height: 6px; background: #334155;">
                                            <!-- Trocamos bg-gold por bg-danger para ficar vermelho -->
                                            <div class="progress-bar bg-danger" style="width: <?php echo $larguraBarra; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo number_format($perc, 1); ?>% da Receita</small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12 mb-3">
                <h4 class="text-gold fw-bold"><i class="fas fa-list me-2"></i> Detalhamento de Lançamentos</h4>
            </div>
            
            <!-- QUADRO DE APORTES E INVESTIMENTOS -->
            <div class="col-12 mb-4">
                <div class="card-custom border-primary" style="border-top: 4px solid #0d6efd;">
                    <h5 class="text-primary mb-3 fw-bold"><i class="fas fa-chart-line me-2"></i> Aportes e Investimentos</h5>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle border-secondary mb-0">
                        <thead class="text-light">
                            <tr>
                                <th>Data</th>
                                <th>Descrição</th>
                                <th>Categoria</th>
                                <th class="text-end">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($lista_investimentos)): ?>
                            <?php foreach ($lista_investimentos as $inv): ?>
                            <tr>
                                <td class="text-muted"><?php echo date('d/m/Y', strtotime($inv['data_aporte'])); ?></td>
                                <td><?php echo htmlspecialchars($inv['ativo']); ?></td>
                                <td><span class="badge bg-primary bg-opacity-25 text-primary border border-primary"><?php echo ucfirst(htmlspecialchars($inv['categoria'])); ?></span></td>
                                <td class="text-end fw-bold text-primary">+ R$ <?php echo number_format($inv['valor_investido'], 2, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">Nenhum aporte no período.</td></tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end text-uppercase">Subtotal Aportes:</th>
                                <th class="text-end text-primary fw-bold">+ R$ <?php echo number_format($subtotal_investimentos, 2, ',', '.'); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                </div>
            </div>

            <!-- QUADRO DE RECEITAS -->
            <div class="col-12 mb-4">
                <div class="card-custom border-success" style="border-top: 4px solid #22c55e;">
                    <h5 class="text-success mb-3 fw-bold"><i class="fas fa-arrow-up me-2"></i> Saldo e Receitas</h5>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle border-secondary mb-0">
                            <thead class="text-light">
                                <tr>
                                    <th>Data</th>
                                    <th>Descrição</th>
                                    <th>Categoria</th>
                                    <th class="text-end">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($listaReceitas)): ?>
                                    <?php foreach ($listaReceitas as $l): ?>
                                        <tr>
                                            <td class="text-muted"><?php echo date('d/m/Y', strtotime($l['data_transacao'])); ?></td>
                                            <td><?php echo htmlspecialchars($l['descricao']); ?></td>
                                            <td><span class="badge bg-success bg-opacity-25 text-success border border-success"><?php echo ucfirst(htmlspecialchars($l['tipo'])); ?></span></td>
                                            <td class="text-end fw-bold text-success">+ R$ <?php echo number_format($l['valor'], 2, ',', '.'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">Nenhuma receita no período.</td></tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-end text-uppercase">Subtotal Receitas:</th>
                                    <th class="text-end text-success fw-bold">+ R$ <?php echo number_format($subtotalReceitas, 2, ',', '.'); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- QUADRO DE DESPESAS FIXAS (AMARELO) -->
            <div class="col-12 mb-4">
                <div class="card-custom border-warning" style="border-top: 4px solid #f59e0b;">
                    <h5 class="text-warning mb-3 fw-bold"><i class="fas fa-thumbtack me-2"></i> Despesas Fixas</h5>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle border-secondary mb-0">
                            <thead class="text-light">
                                <tr>
                                    <th>Data</th>
                                    <th>Descrição</th>
                                    <th>Categoria</th>
                                    <th class="text-end">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($listaDespesasFixas)): ?>
                                    <?php foreach ($listaDespesasFixas as $l): ?>
                                        <tr>
                                            <td class="text-muted"><?php echo date('d/m/Y', strtotime($l['data_transacao'])); ?></td>
                                            <td><?php echo htmlspecialchars($l['descricao']); ?></td>
                                            <td><span class="badge bg-warning bg-opacity-25 text-warning border border-warning"><?php echo ucfirst(htmlspecialchars($l['tipo'])); ?></span></td>
                                            <td class="text-end fw-bold text-warning">- R$ <?php echo number_format($l['valor'], 2, ',', '.'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">Nenhuma despesa fixa no período.</td></tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-end text-uppercase">Subtotal Fixas:</th>
                                    <th class="text-end text-warning fw-bold">- R$ <?php echo number_format($subtotalDespesasFixas, 2, ',', '.'); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- QUADRO DE DESPESAS VARIÁVEIS (VERMELHO) -->
            <div class="col-12 mb-4">
                <div class="card-custom border-danger" style="border-top: 4px solid #ef4444;">
                    <h5 class="text-danger mb-3 fw-bold"><i class="fas fa-random me-2"></i> Despesas Variáveis</h5>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle border-secondary mb-0">
                            <thead class="text-light">
                                <tr>
                                    <th>Data</th>
                                    <th>Descrição</th>
                                    <th>Categoria</th>
                                    <th class="text-end">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($listaDespesasVariaveis)): ?>
                                    <?php foreach ($listaDespesasVariaveis as $l): ?>
                                        <tr>
                                            <td class="text-muted"><?php echo date('d/m/Y', strtotime($l['data_transacao'])); ?></td>
                                            <td><?php echo htmlspecialchars($l['descricao']); ?></td>
                                            <td><span class="badge bg-danger bg-opacity-25 text-danger border border-danger"><?php echo ucfirst(htmlspecialchars($l['tipo'])); ?></span></td>
                                            <td class="text-end fw-bold text-danger">- R$ <?php echo number_format($l['valor'], 2, ',', '.'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">Nenhuma despesa variável no período.</td></tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-end text-uppercase">Subtotal Variáveis:</th>
                                    <th class="text-end text-danger fw-bold">- R$ <?php echo number_format($subtotalDespesasVariaveis, 2, ',', '.'); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- QUADRO DE RESUMO / TOTAL GERAL -->
            <?php 
            // Garanta que você está somando os dois tipos de despesa. 
            // Se as suas variáveis tiverem nomes diferentes (ex: $totalFixas e $totalVariaveis), ajuste aqui:
            $total_despesas_mes = ($subtotalDespesasFixas ?? 0) + ($subtotalDespesasVariaveis ?? 0); 
    
            // A matemática que você pediu: Receitas + Investimentos - Despesas
            $total_geral = $total_atual + $saldo_periodo;
    
            // Define se a cor do resultado final será verde (positivo) ou vermelho (negativo)
            $cor_total = ($total_geral >= 0) ? 'text-success' : 'text-danger';
            ?>

            <div class="col-12 mb-5">
                <div class="card-custom border-light" style="border-top: 4px solid #f8fafc; background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);">
                    <h5 class="text-light mb-3 fw-bold"><i class="fas fa-balance-scale me-2"></i> Balanço Final do Período</h5>
                <div class="table-responsive">
                    <table class="table table-dark align-middle border-secondary mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted text-uppercase"><i class="fas fa-chart-line text-primary me-2"></i> Total de Aportes/Investimentos</td>
                                <td class="text-end fw-bold text-primary">+ R$ <?php echo number_format($total_atual, 2, ',', '.'); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted text-uppercase"><i class="fas fa-arrow-up text-success me-2"></i> Total de Receitas (Saldos + Mensalidades + Tronco)</td>
                                <td class="text-end fw-bold text-success">+ R$ <?php echo number_format($subtotalReceitas, 2, ',', '.'); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted text-uppercase"><i class="fas fa-arrow-down text-danger me-2"></i> Total de Despesas (Fixas + Variáveis)</td>
                                <td class="text-end fw-bold text-danger">- R$ <?php echo number_format($total_despesas_mes, 2, ',', '.'); ?></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr style="background: rgba(255,255,255,0.05);">
                                <th class="text-end text-uppercase fs-5 pt-3 pb-3">Total Geral:</th>
                                <th class="text-end fw-bold fs-5 pt-3 pb-3 <?php echo $cor_total; ?>">
                                    R$ <?php echo number_format($total_geral, 2, ',', '.'); ?>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                </div>
            </div>
            
        </div>

    </div>
    </div>
    
    <script>
        // --- GRÁFICO DE VISÃO GERAL (ROSCA) ---
        <?php if($subtotal_investimentos > 0 || $receitas > 0 || $despesas > 0 || $saldoGrafico > 0): ?>
        const ctxCat = document.getElementById('catChart').getContext('2d');
        new Chart(ctxCat, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($macroLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($macroValues); ?>,
                    // Verde (Receita), Vermelho (Despesa), Dourado (Saldo)
                    backgroundColor: ['#0000FF', '#DAA520', '#008000', '#FF0000'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { color: '#e2e8f0', boxWidth: 12 } }
                }
            }
        });
        <?php endif; ?>

        // --- GRÁFICO DE EVOLUÇÃO (BARRAS) ---
        const ctxEvo = document.getElementById('evoChart').getContext('2d');
        new Chart(ctxEvo, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($evoLabels); ?>,
                datasets: [
                    //{ label: 'Investimentos', data: <?php echo json_encode($evoInv); ?>, backgroundColor: '#0000FF' , borderRadius: 4 },
                    { label: 'Saldos', data: <?php echo json_encode($evoSald); ?>, backgroundColor: '#DAA520' , borderRadius: 4 },
                    { label: 'Receitas', data: <?php echo json_encode($evoRec); ?>, backgroundColor: '#008000' , borderRadius: 4 },
                    { label: 'Despesas', data: <?php echo json_encode($evoDesp); ?>, backgroundColor: '#FF0000' , borderRadius: 4 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#94a3b8' } } },
                scales: {
                    y: { grid: { color: '#334155' }, ticks: { color: '#94a3b8' } },
                    x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
                }
            }
        });
    

    function enviarRelatorioEmail() {
    // Captura os valores atuais do filtro selecionado na tela
    const mes = document.querySelector('select[name="mes"]').value;
    const ano = document.querySelector('select[name="ano"]').value;

    // Confirmação antes de enviar
    Swal.fire({
        title: 'Enviar Relatório?',
        text: "Deseja gerar e enviar o relatório de " + mes + "/" + ano + " para os e-mails dos membros?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#22c55e',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Sim, enviar!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            
            // Mostra o Loading
            Swal.fire({
                title: 'Gerando e Enviando...',
                html: 'Isso pode levar alguns segundos.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Payload exigido pelo PHP via POST
            const payload = {
                acao_interna: 'prestacao_contas',
                mes: mes,
                ano: ano
            };

            // Faz a requisição POST com o JSON no corpo
            fetch('enviar_relatorio_email', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                if (data.sucesso) {
                    Swal.fire('Sucesso!', data.mensagem, 'success');
                } else {
                    Swal.fire('Erro', data.mensagem, 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro', 'Falha na comunicação com o servidor.', 'error');
            });
        }
    });
}
    

    function toggleMobileMenu() {
        const sidebar = document.querySelector('.sidebar'); // Certifique-se de que a sua tag <nav> ou <div class="sidebar"> do menu tenha essa classe
        const backdrop = document.getElementById('sidebarBackdrop');
        
        if (sidebar) {
            sidebar.classList.toggle('show');
        }
        if (backdrop) {
            backdrop.classList.toggle('show');
        }
    }

    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>