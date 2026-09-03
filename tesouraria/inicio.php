<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require '../configuracoes/config.php';

// =========================================================
// 1. SEGURANÇA E DEFINIÇÃO DO USUÁRIO
// =========================================================
if (!isset($_SESSION['tenant_id']) && !isset($_SESSION['user_id']) && !isset($_SESSION['user'])) { 
    header("Location: ./login.php"); 
    exit; 
}

// --- DEFINIR A VARIÁVEL $user_id ANTES DE USAR ---
$user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['user']['id'];

// =========================================================
// 2. DEFINE O CONTEXTO E O FILTRO DE DATA
// =========================================================
$contexto = $_SESSION['contexto_atual'] ?? 'pessoal';

// Captura os filtros via GET (Padrão: Mês e Ano Atual)
$mes_filtro = $_GET['mes'] ?? date('m');
$ano_filtro = $_GET['ano'] ?? date('Y');

// Array de meses para popular o select
$meses = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março',
    '04' => 'Abril', '05' => 'Maio', '06' => 'Junho',
    '07' => 'Julho', '08' => 'Agosto', '09' => 'Setembro',
    '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
];

// =========================================================
// 3. CÁLCULOS INICIAIS (FILTRADOS PELO CONTEXTO, MÊS E ANO)
// =========================================================

// saldos
$stmt = $pdo->prepare("SELECT SUM(valor) FROM transacoes WHERE usuario_id = ? AND tipo IN ('saldo','tronco') AND contexto = ? AND MONTH(data_transacao) = ? AND YEAR(data_transacao) = ?");
$stmt->execute([$user_id, $contexto, $mes_filtro, $ano_filtro]);
$total_saldos = $stmt->fetchColumn() ?: 0;

// Receitas
$stmt = $pdo->prepare("SELECT SUM(valor) FROM transacoes WHERE usuario_id = ? AND tipo = 'receita' AND contexto = ? AND MONTH(data_transacao) = ? AND YEAR(data_transacao) = ?");
$stmt->execute([$user_id, $contexto, $mes_filtro, $ano_filtro]);
$total_receitas = $stmt->fetchColumn() ?: 0;

// Despesas Totais
$stmt = $pdo->prepare("SELECT SUM(valor) FROM transacoes WHERE usuario_id = ? AND tipo IN ('fixo', 'variavel','doação') AND contexto = ? AND MONTH(data_transacao) = ? AND YEAR(data_transacao) = ?");
$stmt->execute([$user_id, $contexto, $mes_filtro, $ano_filtro]);
$total_despesas = $stmt->fetchColumn() ?: 0;

// Fixo
$stmt = $pdo->prepare("SELECT SUM(valor) FROM transacoes WHERE usuario_id = ? AND tipo = 'fixo' AND contexto = ? AND MONTH(data_transacao) = ? AND YEAR(data_transacao) = ?");
$stmt->execute([$user_id, $contexto, $mes_filtro, $ano_filtro]);
$total_fixo = $stmt->fetchColumn() ?: 0;

// Variável
$stmt = $pdo->prepare("SELECT SUM(valor) FROM transacoes WHERE usuario_id = ? AND tipo IN ('variavel','doação') AND contexto = ? AND MONTH(data_transacao) = ? AND YEAR(data_transacao) = ?");
$stmt->execute([$user_id, $contexto, $mes_filtro, $ano_filtro]);
$total_variavel = $stmt->fetchColumn() ?: 0;

// mensalidades
$stmt = $pdo->prepare("SELECT SUM(valor) FROM transacoes WHERE usuario_id = ? AND tipo = 'mensalidade' AND contexto = ? AND MONTH(data_transacao) = ? AND YEAR(data_transacao) = ?");
$stmt->execute([$user_id, $contexto, $mes_filtro, $ano_filtro]);
$total_mensalidades = $stmt->fetchColumn() ?: 0;

// investimentos (mantido sem filtro de data, pois reflete o patrimônio atual)
$stmt = $pdo->prepare("SELECT SUM(valor_atual) FROM investimentos WHERE usuario_id = ?");
$stmt->execute([$user_id]);
$total_investimentos = $stmt->fetchColumn() ?: 0;

// Saldo

$total =  $total_saldos + $total_receitas + $total_mensalidades;

$diferenca = $total - $total_despesas;

$saldo = ($total + $total_investimentos) - $total_despesas;

// =========================================================
// 4. DADOS DO PERFIL E BADGE
// =========================================================
$stmt = $pdo->prepare("SELECT nome, telefone, plano, creditos_ia, plano_expiracao FROM usuarios WHERE id = ?");
$stmt->execute([$user_id]);
$meuPerfil = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finanças - Gestão Integrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
    /* --- TEMA DARK & GOLD --- */
    :root {
        --bg-main: #0f172a; --bg-surface: #1e293b; --color-gold: #cfa34e; --color-gold-hover: #b8860b;
        --text-main: #e2e8f0; --text-muted: #94a3b8; --border-color: #334155;
        --success: #22c55e; --danger: #ef4444; --info: #38bdf8; --warning: #f59e0b;
    }

    body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Segoe UI', sans-serif; overflow-x: hidden; }
    
    .nav-link { color: var(--text-muted); padding: 12px; border-radius: 8px; margin-bottom: 5px; text-decoration: none; transition: 0.3s; display: flex; align-items: center; gap: 10px; }
    .nav-link:hover { background-color: rgba(255,255,255,0.05); color: var(--text-main); }
    .nav-link.active { background-color: var(--color-gold); color: var(--bg-main); font-weight: bold; }
    .main-content { margin-left: 250px; padding: 30px; width: calc(100% - 250px); padding-bottom: 100px; }
    .badge-pro { background-color: rgba(245, 192, 65, 0.2); color: var(--color-gold); padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; border: 1px solid rgba(245, 192, 65, 0.4); }

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

    /* --- OUTROS ESTILOS --- */
    .text-gold { color: var(--color-gold) !important; }
    .bg-gold { background-color: var(--color-gold) !important; color: var(--bg-main); }
    .btn-gold { background: var(--color-gold); border: none; color: var(--bg-main); font-weight: 600; }
    .btn-gold:hover { background: var(--color-gold-hover); color: var(--bg-main); }
    .card-resumo { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; height: 100%; padding: 15px; }
    .card-saldo { border: 1px solid var(--color-gold); background: linear-gradient(145deg, rgba(207,163,78,0.1) 0%, rgba(30,41,59,1) 100%); }
    
    /* Overlay */
    #overlay { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); z-index: 2500; backdrop-filter: blur(2px); }

    /* Chat e Modais */
    .floating-chat-btn { position: fixed; bottom: 20px; right: 20px; width: 60px; height: 60px; border-radius: 50%; font-size: 24px; display: flex; align-items: center; justify-content: center; background: var(--color-gold); color: var(--bg-main); border: none; box-shadow: 0 4px 15px rgba(207, 163, 78, 0.3); z-index: 1000; }
    #chat-window { position: fixed; bottom: 90px; right: 20px; width: 350px; height: 500px; z-index: 9999; display: flex; flex-direction: column; border: 1px solid var(--color-gold); background: var(--bg-surface); }
    @media (max-width: 576px) { #chat-window { width: 90%; right: 5%; bottom: 90px; height: 50vh; } }
    
    .list-group-item { background: transparent; border-color: var(--border-color); color: var(--text-main); padding: 12px 0; }
    .list-group-item span.fw-bold { white-space: nowrap; text-align: right; min-width: 100px; }
    .list-group-item div.fw-bold { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }
    .link-hover:hover { background-color: rgba(255, 255, 255, 0.05); color: var(--color-gold) !important; border-radius: 8px; transition: all 0.2s ease-in-out; }
    .link-hover:hover i { transform: scale(1.1); transition: transform 0.2s; }
    
    /* SWEETALERT Z-INDEX FIX */
    div.swal2-container { z-index: 20000 !important; }
    div.swal2-backdrop-show { z-index: 19999 !important; }
    
    .menu-header { font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: bold; letter-spacing: 1px; margin-bottom: 5px; padding-left: 10px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 5px; }
    </style>
</head>
<body>

<!-- Barra Superior Mobile (Visível apenas em celulares) -->
<div class="mobile-topbar">
    <div class="d-flex align-items-center">
        <button class="btn btn-outline-warning btn-sm me-3" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <span style="font-family: 'Cinzel', serif; color: var(--color-gold); font-weight: bold;">TESOURARIA</span>
    </div>
    <span class="text-white small">Painel</span>
</div>

<!-- Backdrop escuro para fechar o menu ao clicar fora -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>

<?php include 'menu.php'; ?>

<div class="main-content">
    
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="page-header">
            <div class="greeting">
                <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;"> Olá, Tesoureiro!</h2>
                <span class="badge-pro"><i class="fas fa-gem me-1"></i> Loja Ativa <?php echo htmlspecialchars($meuPerfil['nome']); ?> </span>
            </div>
        </div>

        <!-- INÍCIO DO NOVO FILTRO DE DATA -->
        <div class="d-flex align-items-center gap-2">
            <form method="GET" class="d-flex gap-2 p-2 bg-surface border border-secondary rounded align-items-center mb-0">
                <i class="fas fa-calendar-alt text-muted ms-1"></i>
                <select name="mes" class="form-select form-select-sm bg-dark text-white border-secondary" style="width: auto;" onchange="this.form.submit()">
                    <?php foreach ($meses as $num => $nome): ?>
                        <option value="<?= $num ?>" <?= $mes_filtro == $num ? 'selected' : '' ?>><?= $nome ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select name="ano" class="form-select form-select-sm bg-dark text-white border-secondary" style="width: auto;" onchange="this.form.submit()">
                    <?php 
                    $anoBase = date('Y');
                    for ($a = $anoBase - 3; $a <= $anoBase + 2; $a++): 
                    ?>
                        <option value="<?= $a ?>" <?= $ano_filtro == $a ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </form>
            
            <?php if ($_SESSION['is_admin'] == 1): ?>
            <button class="btn btn-warning shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAdd" style="height: 42px;">
                + Novo Lançamento
            </button>
            <?php endif; ?>
        </div>
        <!-- FIM DO NOVO FILTRO DE DATA -->
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="card-resumo">
                <div class="d-flex justify-content-between">
                    <small class="text-primary">Total Saldo Anterior</small> <span class="text-muted small">↗</span>
                </div>
                <h4 id="valCaixas" class="val-caixa">R$ <?php echo number_format($total_saldos, 2, ',', '.'); ?></h4>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card-resumo">
                <div class="d-flex justify-content-between">
                    <small class="text-success">Total Receitas</small> <span class="text-muted small">↗</span>
                </div>
                <h4 id="valTotalReceitas" class="val-receita">R$ <?php echo number_format($total, 2, ',', '.'); ?></h4>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card-resumo">
                <div class="d-flex justify-content-between">
                    <small class="text-danger">Total Despesas</small> <span class="text-muted small">↘</span>
                </div>
                <h4 id="valTotalDespesas" class="val-despesa">R$ <?php echo number_format($total_despesas, 2, ',', '.'); ?></h4>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card-resumo">
                <small class="text-warning">Diferença (Receita - Despesa)</small>
                <h5 id="valSaldoDiferencas" class="text-white">R$ <?php echo number_format($diferenca, 2, ',', '.'); ?></h5>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card-resumo">
                <small class="val-fixo">Despesas Fixas</small>
                <h5 id="valFixos" class="text-white">R$ <?php echo number_format($total_fixo, 2, ',', '.'); ?></h5>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card-resumo">
                <small class="val-variavel">Despesas Variáveis</small>
                <h5 id="valVariavels" class="text-white">R$ <?php echo number_format($total_variavel, 2, ',', '.'); ?></h5>
            </div>
        </div>
        
        <div class="col-12">
            <div class="card-resumo card-saldo text-center py-4">
                <small class="text-gold" style="font-size: 0.9rem;">SALDO DISPONÍVEL</small><p class="text-gold"> (Receitas + Investimentos)</p>
                <h2 id="valSaldos" class="val-saldo m-0 fw-bold <?php echo ($saldo < 0) ? 'text-danger' : ''; ?>" style="font-size: 2.5rem;">
                    R$ <?php echo number_format($saldo, 2, ',', '.'); ?>
                </h2>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card-dash h-100">
                <div class="p-3 border-bottom border-secondary d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-gold">📊 Evolução Anual</span>
                    <small class="text-muted">12 Meses</small>
                </div>
                <div class="p-3">
                    <div style="position: relative; height: 300px; width: 100%;">
                        <canvas id="financeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card-dash h-100">
                <div class="p-3 border-bottom border-secondary">
                    <span class="fw-bold text-gold">Últimos Lançamentos (<?= $meses[$mes_filtro] ?>/<?= $ano_filtro ?>)</span>
                </div>
                <ul class="list-group list-group-flush" id="listaTransacoes" style="max-height: 330px; overflow-y: auto;">
                    </ul>
            </div>
        </div>
    </div>

</div> 

<button id="chat-toggle" class="floating-chat-btn">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-stars" viewBox="0 0 16 16">
        <path d="M7.657 6.247c.11-.33.576-.33.686 0l.645 1.937a2.89 2.89 0 0 0 1.829 1.828l1.936.645c.33.11.33.576 0 .686l-1.937.645a2.89 2.89 0 0 0-1.828 1.829l-.645 1.936a.361.361 0 0 1-.686 0l-.645-1.937a2.89 2.89 0 0 0-1.828-1.828l-1.937-.645a.361.361 0 0 1 0-.686l1.937-.645a2.89 2.89 0 0 0 1.828-1.828l.645-1.937zM3.794 1.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387A1.734 1.734 0 0 0 4.593 5.69l-.387 1.162a.217.217 0 0 1-.412 0L3.407 5.69A1.734 1.734 0 0 0 2.31 4.593l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387A1.734 1.734 0 0 0 3.407 2.31l.387-1.162zM10.863.099a.145.145 0 0 1 .274 0l.258.774c.115.346.386.617.732.732l.774.258a.145.145 0 0 1 0 .274l-.774.258a1.156 1.156 0 0 0-.732.732l-.258.774a.145.145 0 0 1-.274 0l-.258-.774a1.156 1.156 0 0 0-.732-.732L9.1 2.137a.145.145 0 0 1 0-.274l.774-.258c.346-.115.617-.386.732-.732L10.863.1z"/>
    </svg>
</button>

<div id="chat-window" class="card shadow-lg d-none">
    <div id="chat-header" class="card-header d-flex justify-content-between align-items-center bg-gold text-dark-theme">
        <span class="fw-bold">Assistente IA</span>
        <button onclick="toggleChat()" class="btn btn-sm btn-link text-dark-theme text-decoration-none fw-bold">✕</button>
    </div>
    <div id="chat-messages" class="card-body overflow-auto" style="flex: 1; font-size: 14px;">
        <div class="text-center text-muted mt-3">
            <small>Olá! Pergunte sobre seus gastos.<br>Ex: "Quanto gastei em Uber?"</small>
        </div>
    </div>
    <div class="card-footer p-2 bg-surface">
        <form id="chat-form" class="d-flex gap-2">
            <input type="text" id="chat-input" class="form-control form-control-sm" placeholder="Digite sua pergunta..." required>
            <button type="submit" class="btn btn-sm btn-gold">➤</button>
        </form>
    </div>
</div>

<div class="modal fade" id="modalAdd" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-gold">Novo Lançamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
            </div>
            <div class="modal-body">
                <form id="formTransacao">
                    <input type="hidden" name="contexto" value="<?php echo $contexto; ?>">

                    <div class="mb-3">
                        <label class="text-muted small">Tipo de Lançamento</label>
                        <select class="form-select" name="tipo" required>
                        <?php if($contexto === 'empresa'): ?>
                            <option value="saldo" class="text-success fw-bold">⬆ Saldo (Conta Corrente, Poupança)</option>
                            <option value="receita" class="text-success fw-bold">⬆ Faturamento / Vendas</option>
                            <option value="fixo" class="text-warning fw-bold">⬇ Custo Fixo (Folha, Aluguel, Software)</option>
                            <option value="variavel" class="text-danger fw-bold">⬇ Despesa Variável (Fornecedores, Impostos)</option>
                        <?php else: ?>
                            <option value="saldo" class="text-success fw-bold">⬆ Saldo (Conta Corrente, Poupança)</option>
                            <option value="receita" class="text-success fw-bold">⬆ Receita (Aluguel, Recebivéis)</option>
                            <option value="mensalidade" class="text-success fw-bold">⬆ Mensalidade (Mensalidades, Taxas)</option>
                            <option value="tronco" class="text-success fw-bold">⬆ Tronco (Doações, Beneficência)</option>
                            <option value="fixo" class="text-warning fw-bold">⬇ Gasto Fixo (Energia, Internet)</option>
                            <option value="variavel" class="text-danger fw-bold">⬇ Gasto Variável (Expediente, Mercado)</option>
                            <option value="doação" class="text-danger fw-bold">⬇ Gasto Doações (Movimentos, ajudas)</option>
                        <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Descrição</label>
                        <input type="text" class="form-control" name="descricao" placeholder="Ex: Jantar fora" required>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Valor (R$)</label>
                        <input type="number" step="0.01" class="form-control" name="valor" placeholder="0.00" required>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Data</label>
                        <input type="date" class="form-control" name="data" id="dataAtual" required>
                    </div>
                    <button type="submit" class="btn btn-warning w-100 py-2">Salvar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="overlay" onclick="toggleSidebar()" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:900;"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('dataAtual').valueAsDate = new Date();
    
    // Sidebar Mobile Logic
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('show');
        const overlay = document.getElementById('overlay');
        overlay.style.display = overlay.style.display === 'none' ? 'block' : 'none';
    }

    const fmtBRL = (valor) => parseFloat(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

    function carregarDados() {
        // PEGANDO OS PARÂMETROS DO FILTRO DA URL
        const pMes = '<?= $mes_filtro ?>';
        const pAno = '<?= $ano_filtro ?>';
        const queryString = `&mes=${pMes}&ano=${pAno}`;

        // Lista
        fetch('../configuracoes/api?action=list' + queryString)
            .then(res => res.json())
            .then(data => {
                const lista = document.getElementById('listaTransacoes');
                lista.innerHTML = '';
                
                if (data.length === 0) {
                    lista.innerHTML = '<li class="list-group-item text-center text-muted py-4">Nenhum lançamento encontrado neste período.</li>';
                    return;
                }

                data.forEach(item => {
                    let cor = 'text-white'; let sinal = '-';
                    if (item.tipo === 'receita') { cor = 'text-success'; sinal = '+'; }
                    else if (item.tipo === 'fixo') { cor = 'text-warning'; }
                    else if (item.tipo === 'variavel') { cor = 'text-danger'; }
                    else if (item.tipo === 'saldo') { cor = 'text-primary'; sinal = '+'; }
                    else if (item.tipo === 'mensalidade') { cor = 'text-secondary'; sinal = '+'; }
                    else if (item.tipo === 'tronco') { cor = 'text-success'; sinal = '+'; }
                    else if (item.tipo === 'doação') { cor = 'text-danger'; sinal = '-'; }

                    lista.innerHTML += `
                    <li class="list-group-item d-flex justify-content-between align-items-center py-3 gap-3">
                    <div style="overflow: hidden;"> <div class="fw-bold text-main text-truncate">${item.descricao}</div>
                        <small class="text-muted" style="text-transform:uppercase; font-size:0.7rem">
                            ${item.tipo} • ${item.data_transacao}
                        </small>
                    </div>
                    <span class="${cor} fw-bold fs-6">${sinal} ${fmtBRL(item.valor)}</span>
                    </li>
                    `;
                });
            });

        // Resumo (Usando IDs corrigidos para não conflitar com a tela)
        fetch('../configuracoes/api?action=summary' + queryString)
            .then(res => res.json())
            .then(data => {
                if(document.getElementById('valTotalReceita')) document.getElementById('valTotalReceita').innerText = fmtBRL(data.receitas || 0);
                if(document.getElementById('valTotalDespesa')) document.getElementById('valTotalDespesa').innerText = fmtBRL(data.total_despesas || 0);
                if(document.getElementById('valFixo')) document.getElementById('valFixo').innerText = fmtBRL(data.fixo || 0);
                if(document.getElementById('valVariavel')) document.getElementById('valVariavel').innerText = fmtBRL(data.variavel || 0);
                
                let elSaldo = document.getElementById('valSaldo');
                if(elSaldo) {
                    elSaldo.innerText = fmtBRL(data.saldo || 0);
                    if(data.saldo < 0) elSaldo.classList.replace('val-saldo', 'text-danger');
                    else elSaldo.classList.replace('text-danger', 'val-saldo');
                }
            });
            
        carregarGrafico(queryString);
    }

    let myChart = null;
    function carregarGrafico(queryString) {
        fetch('../configuracoes/api?action=chart_data' + queryString)
            .then(res => res.json())
            .then(data => {
                const ctx = document.getElementById('financeChart').getContext('2d');
                if(myChart) myChart.destroy();
                myChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [
                            { label: 'Receitas', data: data.receitas, backgroundColor: '#22c55e', borderRadius: 4 },
                            { label: 'Despesas', data: data.despesas, backgroundColor: '#ef4444', borderRadius: 4 }
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { labels: { color: '#94a3b8' } } },
                        scales: {
                            y: { beginAtZero: true, grid: { color: '#334155' }, ticks: { color: '#94a3b8' } },
                            x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
                        }
                    }
                });
            });
    }

    // Submit Add
    document.getElementById('formTransacao').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('../configuracoes/api?action=add', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('modalAdd')).hide();
                this.reset();
                carregarDados();
                atualizarPagina();
                Swal.fire({ title: 'Sucesso!', text: data.message, icon: 'success', background: '#1e293b', color: '#fff', confirmButtonColor: '#cfa34e' });
            } else { 
                Swal.fire({ title: 'Erro!', text: 'Erro ao salvar', icon: 'error', background: '#1e293b', color: '#fff' }); 
            }
        });
    });

    carregarDados();
    
    function atualizarPagina() {
        location.reload();
    }

    // Chat
    const WEBHOOK_CHAT_URL = 'https://n8n-prod.jrtec.com.br/webhook/financeiro-site'; 
    const USER_ID = <?php echo $user_id; ?>;
    const USER_TEL = "<?php echo $meuPerfil['telefone'] ?? ''; ?>";
    
    function toggleChat() { document.getElementById('chat-window').classList.toggle('d-none'); }
    document.getElementById('chat-toggle').addEventListener('click', toggleChat);
    
    function addMessage(text, sender) {
        const div = document.createElement('div');
        div.className = `d-flex mb-2 ${sender === 'user' ? 'justify-content-end' : 'justify-content-start'}`;
        const bubble = sender === 'user' ? 'user-bubble' : 'bot-bubble';
        div.innerHTML = `<div class="p-3 shadow-sm ${bubble}" style="max-width: 85%;">${text.replace(/\n/g, '<br>')}</div>`;
        const c = document.getElementById('chat-messages'); c.appendChild(div); c.scrollTop = c.scrollHeight;
    }

    document.getElementById('chat-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        const inp = document.getElementById('chat-input');
        const msg = inp.value.trim(); if(!msg) return;
        addMessage(msg, 'user'); inp.value = ''; inp.disabled = true;
        
        // Loading
        const lDiv = document.createElement('div'); lDiv.id = 'loading'; lDiv.className='text-muted ms-2 small'; lDiv.innerText='IA pensando...';
        document.getElementById('chat-messages').appendChild(lDiv);

        try {
            const res = await fetch(WEBHOOK_CHAT_URL, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: msg, user_id: USER_ID, telefone: USER_TEL })
            });
            const data = await res.json();
            document.getElementById('loading').remove();
            addMessage(data.output || "Erro.", 'bot');
        } catch { document.getElementById('loading').remove(); addMessage("Erro de conexão.", 'bot'); }
        finally { inp.disabled = false; inp.focus(); }
    });
    
</script>
<?php if (isset($_SESSION['show_2fa_alert']) && $_SESSION['show_2fa_alert'] === true): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            title: '🔒 Aumente sua Segurança!',
            html: "Notamos que você ainda não ativou a <strong>Autenticação de Dois Fatores (2FA)</strong>.<br>Isso protege sua conta mesmo que sua senha seja roubada.",
            icon: 'info',
            background: '#1e293b',
            color: '#fff',
            showCancelButton: true,
            confirmButtonText: '🛡️ Ativar Agora',
            confirmButtonColor: '#cfa34e',
            cancelButtonText: 'Agora não',
            cancelButtonColor: '#64748b',
            showDenyButton: true,
            denyButtonText: 'Não lembrar mais',
            denyButtonColor: '#ef4444',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '../configuracoes/seguranca.php';
            } else if (result.isDenied) {
                fetch('../configuracoes/auth.php?action=ignore_2fa');
                Swal.fire({
                    title: 'Entendido!', 
                    text: 'Não vamos mais te incomodar com isso.', 
                    icon: 'success',
                    background: '#1e293b', color: '#fff',
                    timer: 2000, showConfirmButton: false
                });
            }
        });
    });
</script>
<?php 
    unset($_SESSION['show_2fa_alert']); 
?>
<?php endif; ?>

<script>
    function toggleMobileMenu() {
        const sidebar = document.querySelector('.sidebar'); 
        const backdrop = document.getElementById('sidebarBackdrop');
        if (sidebar) sidebar.classList.toggle('show');
        if (backdrop) backdrop.classList.toggle('show');
    }
</script>
</body>
</html>