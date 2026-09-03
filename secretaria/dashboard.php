<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../configuracoes/config.php';

// Garante que o usuário está logado
if (!isset($_SESSION['tenant_id']) && !isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['user']['id'];

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$tenant_id]);
$meuPerfil = $stmt->fetch(PDO::FETCH_ASSOC);

// Inicializa arrays para o gráfico (12 meses)
$dados_balaustres = array_fill(1, 12, 0);
$dados_expedientes = array_fill(1, 12, 0);
$dados_corresp = array_fill(1, 12, 0);
$ultimos_registros = [];

// Exemplo de consultas para os indicadores do Dashboard
try {
    // Total de Obreiros ativos
    $stmtObreiros = $pdo->prepare("SELECT COUNT(*) FROM chancelaria_membros WHERE tenant_id = ? AND status = 'Ativo'");
    $stmtObreiros->execute([$tenant_id]);
    $total_obreiros = $stmtObreiros->fetchColumn() ?: 0;

    // Total de Balaustres / Atas registrados
    $stmtBalaustres = $pdo->prepare("SELECT COUNT(*) FROM secretaria_balaustres WHERE tenant_id = ?");
    $stmtBalaustres->execute([$tenant_id]);
    $total_balaustres = $stmtBalaustres->fetchColumn() ?: 0;

    // Total de Expedientes
    $stmtExpedientes = $pdo->prepare("SELECT COUNT(*) FROM secretaria_expedientes WHERE tenant_id = ?");
    $stmtExpedientes->execute([$tenant_id]);
    $total_expedientes = $stmtExpedientes->fetchColumn() ?: 0;
    
    // Total de Correspondências
    $stmtCorresp = $pdo->prepare("SELECT COUNT(*) FROM secretaria_correspondencias WHERE tenant_id = ?");
    $stmtCorresp->execute([$tenant_id]);
    $total_correspondencias = $stmtCorresp->fetchColumn() ?: 0;

    // ==========================================
    // DADOS PARA O GRÁFICO (Agrupados por Mês do Ano Atual)
    // ==========================================
    $ano_atual = date('Y');
    
    // Gráfico: Balaustres
    $stmtGB = $pdo->prepare("SELECT MONTH(data_balaustre) as mes, COUNT(*) as total FROM secretaria_balaustres WHERE tenant_id = ? AND YEAR(data_balaustre) = ? GROUP BY MONTH(data_balaustre)");
    $stmtGB->execute([$tenant_id, $ano_atual]);
    while ($row = $stmtGB->fetch(PDO::FETCH_ASSOC)) { $dados_balaustres[$row['mes']] = $row['total']; }

    // Gráfico: Expedientes
    $stmtGE = $pdo->prepare("SELECT MONTH(data_expediente) as mes, COUNT(*) as total FROM secretaria_expedientes WHERE tenant_id = ? AND YEAR(data_expediente) = ? GROUP BY MONTH(data_expediente)");
    $stmtGE->execute([$tenant_id, $ano_atual]);
    while ($row = $stmtGE->fetch(PDO::FETCH_ASSOC)) { $dados_expedientes[$row['mes']] = $row['total']; }

    // Gráfico: Correspondências
    $stmtGC = $pdo->prepare("SELECT MONTH(data_recebimento) as mes, COUNT(*) as total FROM secretaria_correspondencias WHERE tenant_id = ? AND YEAR(data_recebimento) = ? GROUP BY MONTH(data_recebimento)");
    $stmtGC->execute([$tenant_id, $ano_atual]);
    while ($row = $stmtGC->fetch(PDO::FETCH_ASSOC)) { $dados_corresp[$row['mes']] = $row['total']; }

    // ==========================================
    // DADOS PARA A LISTA DE ÚLTIMOS REGISTROS (UNION)
    // ==========================================
    $sql_ultimos = "
        (SELECT id, titulo, data_balaustre AS data_registro, 'Balaustre' AS tipo FROM secretaria_balaustres WHERE tenant_id = :t1)
        UNION ALL
        (SELECT id, titulo, data_expediente AS data_registro, 'Expediente' AS tipo FROM secretaria_expedientes WHERE tenant_id = :t2)
        UNION ALL
        (SELECT id, titulo, data_recebimento AS data_registro, 'Correspondência' AS tipo FROM secretaria_correspondencias WHERE tenant_id = :t3)
        ORDER BY data_registro DESC LIMIT 6
    ";
    $stmtUltimos = $pdo->prepare($sql_ultimos);
    $stmtUltimos->execute([':t1' => $tenant_id, ':t2' => $tenant_id, ':t3' => $tenant_id]);
    $ultimos_registros = $stmtUltimos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Caso as tabelas ainda não existam, zera as variáveis
    $total_obreiros = $total_balaustres = $total_expedientes = $total_correspondencias = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretaria - Gestão Integrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- Chart.js para o Gráfico -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root { --bg-main: #141724; --bg-card: #1d2132; --text-main: #e2e8f0; --gold: #f5c041; --border-color: #333951; }
        body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 260px; padding: 30px 40px; width: calc(100% - 260px); }
        
        .card-custom { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        .text-gold { color: var(--gold) !important; }
        .btn-gold { background-color: var(--gold); color: #141724; font-weight: 600; border: none; }
        .btn-gold:hover { background-color: #dca732; color: #141724; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .greeting h2 { font-weight: 600; margin-bottom: 5px; font-size: 1.8rem; }
        .badge-pro { background-color: rgba(245, 192, 65, 0.2); color: var(--gold); padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; border: 1px solid rgba(245, 192, 65, 0.4); }
        
        .stat-card { transition: transform 0.2s; border-left: 4px solid var(--gold); }
        .stat-card:hover { transform: translateY(-3px); }
        
        .list-group-custom .list-group-item { background-color: transparent; border-color: var(--border-color); color: var(--text-main); padding: 12px 0; }
        .list-group-custom .list-group-item:first-child { border-top: none; }

        /* Responsividade Mobile */
        .mobile-topbar { display: none; height: 60px; background-color: var(--bg-card); border-bottom: 1px solid var(--border-color); align-items: center; padding: 0 20px; justify-content: space-between; position: fixed; top: 0; left: 0; right: 0; z-index: 2000; }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); z-index: 3000; width: 280px; transition: 0.3s; position: fixed; height: 100vh; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 15px; padding-top: 80px; }
            .mobile-topbar { display: flex !important; }
        }
    </style>
</head>
<body>

<!-- Barra Superior Mobile -->
<div class="mobile-topbar">
    <div class="d-flex align-items-center">
        <button class="btn btn-outline-warning btn-sm me-3" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <span style="font-family: 'Cinzel', serif; color: var(--gold); font-weight: bold;">SECRETARIA</span>
    </div>
    <span class="text-white small">Painel</span>
</div>

<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>

<?php include 'menu.php'; ?>

<main class="main-content">
    <div class="page-header">
        <div class="greeting">
            <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;"> Olá, Secretário!</h2>
            <span class="badge-pro"><i class="fas fa-gem me-1"></i> Loja Ativa <?php echo htmlspecialchars($meuPerfil['nome'] ?? ''); ?> </span>
        </div>
    </div>

    <!-- Cards de Indicadores -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card-custom stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase small mb-1">Obreiros Vinculados</h6>
                        <h3 class="fw-bold text-white mb-0"><?= $total_obreiros ?></h3>
                    </div>
                    <div class="fs-1 text-warning opacity-75"><i class="fas fa-users"></i></div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card-custom stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase small mb-1">Balaustres / Atas</h6>
                        <h3 class="fw-bold text-white mb-0"><?= $total_balaustres ?></h3>
                    </div>
                    <div class="fs-1 text-warning opacity-75"><i class="fas fa-file-alt"></i></div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card-custom stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase small mb-1">Expedientes & Corresp.</h6>
                        <h3 class="fw-bold text-white mb-0"><?= $total_expedientes + $total_correspondencias ?></h3>
                    </div>
                    <div class="fs-1 text-warning opacity-75"><i class="fas fa-envelope-open-text"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Seção de Ações Rápidas / Atalhos -->
    <div class="card-custom mb-4">
        <h5 class="text-warning mb-3"><i class="fas fa-bolt me-2"></i>Ações Rápidas</h5>
        <div class="d-flex flex-wrap gap-2">
            <a href="balaustres.php" class="btn btn-outline-warning btn-sm"><i class="fas fa-plus me-1"></i> Novo Balaustre</a>
            <a href="expedientes.php" class="btn btn-outline-warning btn-sm"><i class="fas fa-paper-plane me-1"></i> Registrar Expediente</a>
            <a href="corresp.php" class="btn btn-outline-warning btn-sm"><i class="fas fa-inbox me-1"></i> Ver Correspondências</a>
        </div>
    </div>

    <!-- Nova Seção: Gráfico e Últimos Registros -->
    <div class="row g-4">
        <!-- Coluna do Gráfico -->
        <div class="col-lg-8">
            <div class="card-custom h-100">
                <h5 class="text-warning mb-3"><i class="fas fa-chart-bar me-2"></i>Volume de Atividades (<?= $ano_atual ?>)</h5>
                <div style="height: 300px;">
                    <canvas id="graficoSecretaria"></canvas>
                </div>
            </div>
        </div>

        <!-- Coluna dos Últimos Registros -->
        <div class="col-lg-4">
            <div class="card-custom h-100">
                <h5 class="text-warning mb-3"><i class="fas fa-history me-2"></i>Últimos Registros</h5>
                <ul class="list-group list-group-flush list-group-custom">
                    <?php if(count($ultimos_registros) > 0): ?>
                        <?php foreach($ultimos_registros as $reg): 
                            // Define o ícone e a cor com base no tipo
                            $icon = 'fa-file'; $color = 'text-white';
                            if ($reg['tipo'] == 'Balaustre') { $icon = 'fa-file-alt'; $color = 'text-warning'; }
                            if ($reg['tipo'] == 'Expediente') { $icon = 'fa-paper-plane'; $color = 'text-info'; }
                            if ($reg['tipo'] == 'Correspondência') { $icon = 'fa-inbox'; $color = 'text-success'; }
                        ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center overflow-hidden">
                                    <div class="me-3 <?= $color ?> fs-4"><i class="fas <?= $icon ?>"></i></div>
                                    <div class="text-truncate">
                                        <h6 class="mb-0 text-white text-truncate" style="max-width: 180px;" title="<?= htmlspecialchars($reg['titulo']) ?>">
                                            <?= htmlspecialchars($reg['titulo']) ?>
                                        </h6>
                                        <small class="text-muted"><?= $reg['tipo'] ?></small>
                                    </div>
                                </div>
                                <span class="badge bg-dark border border-secondary" style="font-size: 0.75rem;">
                                    <?= date('d/m/y', strtotime($reg['data_registro'])) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="list-group-item text-center text-muted py-4">Nenhum registro encontrado.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script de Inicialização do Gráfico -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const ctx = document.getElementById('graficoSecretaria').getContext('2d');
        
        // Recebe os dados do PHP processado
        const dadosBalaustres = <?= json_encode(array_values($dados_balaustres)) ?>;
        const dadosExpedientes = <?= json_encode(array_values($dados_expedientes)) ?>;
        const dadosCorresp = <?= json_encode(array_values($dados_corresp)) ?>;

        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                datasets: [
                    {
                        label: 'Balaustres / Atas',
                        data: dadosBalaustres,
                        backgroundColor: '#f5c041', // Gold
                        borderRadius: 4
                    },
                    {
                        label: 'Expedientes',
                        data: dadosExpedientes,
                        backgroundColor: '#0dcaf0', // Info Blue
                        borderRadius: 4
                    },
                    {
                        label: 'Correspondências',
                        data: dadosCorresp,
                        backgroundColor: '#198754', // Success Green
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, color: '#8b92a5' },
                        grid: { color: 'rgba(255, 255, 255, 0.05)', drawBorder: false }
                    },
                    x: {
                        ticks: { color: '#8b92a5' },
                        grid: { display: false, drawBorder: false }
                    }
                },
                plugins: {
                    legend: {
                        labels: { color: '#e2e8f0', boxWidth: 12, padding: 20 }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(20, 23, 36, 0.9)',
                        titleColor: '#f5c041',
                        borderColor: '#333951',
                        borderWidth: 1
                    }
                }
            }
        });
    });

    function toggleMobileMenu() {
        const sidebar = document.querySelector('.sidebar'); 
        const backdrop = document.getElementById('sidebarBackdrop');
        if (sidebar) sidebar.classList.toggle('show');
        if (backdrop) backdrop.classList.toggle('show');
    }
</script>

<?php if (isset($_SESSION['show_2fa_alert']) && $_SESSION['show_2fa_alert'] === true): ?>
<!-- Modal 2FA omitido por brevidade visual (se já possuía, pode manter intacto) -->
<?php 
    unset($_SESSION['show_2fa_alert']); 
endif; 
?>
</body>
</html>