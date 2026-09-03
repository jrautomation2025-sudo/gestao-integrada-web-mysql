<?php
session_start();
require '../configuracoes/config.php';

// Segurança de acesso
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) { 
    header("Location: ../login"); 
    exit; 
}

//$user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['user']['id'];

$tenant_id = $_SESSION['tenant_id']; // Em produção, virá da $_SESSION['tenant_id']

// 1. Busca Acompanhamentos Ativos
$stmt1 = $pdo->prepare("SELECT COUNT(*) as total FROM hospitalaria_acompanhamentos WHERE tenant_id = :tenant_id AND status = 'Em Acompanhamento'");
$stmt1->execute([':tenant_id' => $tenant_id]);
$acompanhamentos_ativos = $stmt1->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 2. Calcula Saldo do Tronco (Entradas - Saídas)
$stmt2 = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN tipo = 'Entrada' THEN valor ELSE 0 END) as total_entradas,
        SUM(CASE WHEN tipo = 'Saída' THEN valor ELSE 0 END) as total_saidas
    FROM hospitalaria_beneficencia 
    WHERE tenant_id = :tenant_id
");
$stmt2->execute([':tenant_id' => $tenant_id]);
$financeiro = $stmt2->fetch(PDO::FETCH_ASSOC);

$total_entradas = $financeiro['total_entradas'] ?? 0;
$total_saidas = $financeiro['total_saidas'] ?? 0;
$saldo_atual = $total_entradas - $total_saidas;

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$tenant_id]);
$meuPerfil = $stmt->fetch(PDO::FETCH_ASSOC);

// 3. Dados para o Gráfico (Evolução mensal)
$stmt_chart = $pdo->prepare("SELECT MONTH(data_inicio) as mes, COUNT(*) as total FROM hospitalaria_acompanhamentos WHERE tenant_id = :tenant_id AND YEAR(data_inicio) = YEAR(CURDATE()) GROUP BY MONTH(data_inicio)");
$stmt_chart->execute([':tenant_id' => $tenant_id]);
$dados_meses = $stmt_chart->fetchAll(PDO::FETCH_ASSOC);
$chart_data = array_fill(1, 12, 0);
foreach($dados_meses as $d) { $chart_data[$d['mes']] = $d['total']; }

// 4. Últimos 5 Lançamentos Financeiros
$stmt_list = $pdo->prepare("SELECT * FROM hospitalaria_beneficencia WHERE tenant_id = ? ORDER BY id DESC LIMIT 5");
$stmt_list->execute([$tenant_id]);
$ultimos_lancamentos = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospitalaria - Gestão Integrada</title>
    <link rel="icon" href="../configuracoes/icone.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root { --bg-dark: #0f172a; --bg-card: #1e293b; --gold: #cfa34e; --text-light: #f1f5f9; }
        body { background-color: var(--bg-dark); color: var(--text-light); font-family: 'Segoe UI', sans-serif; overflow-x: hidden; }

        .card-custom { background: var(--bg-card); border: 1px solid #334155; border-radius: 12px; padding: 20px; height: 100%; transition: transform 0.2s; }
        .card-custom:hover { transform: translateY(-5px); border-color: var(--gold); }
        .text-gold { color: var(--gold) !important; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .greeting h2 { font-weight: 600; margin-bottom: 5px; font-size: 1.8rem; }
        .badge-pro { background-color: rgba(245, 192, 65, 0.2); color: var(--gold); padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; border: 1px solid rgba(245, 192, 65, 0.4); }
        /* ==========================================
           CORREÇÃO DO LAYOUT (MENU + CONTEÚDO)
           ========================================== */
        .sidebar {
            width: 260px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            background-color: var(--bg-card);
            border-right: 1px solid #334155;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .main-content {
            margin-left: 260px; /* Empurra a tela para não ficar atrás do menu */
            min-height: 100vh;
            width: calc(100% - 260px);
        }
        
        .icon-circle {
            width: 60px; height: 60px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; background-color: rgba(207, 163, 78, 0.1);
            color: var(--gold);
        }

        /* HEADER MOBILE */
        .mobile-header {
            display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px;
            background-color: var(--bg-card); border-bottom: 1px solid #334155;
            z-index: 2000; align-items: center; padding: 0 20px; justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); z-index: 3000; width: 280px; box-shadow: 5px 0 15px rgba(0,0,0,0.5); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 15px; padding-top: 80px; }
            .mobile-header { display: flex !important; }
        }
    </style>
</head>
<body>

<div class="mobile-topbar">
    <div class="d-flex align-items-center">
        <button class="btn btn-outline-warning btn-sm me-3" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <span style="font-family: 'Cinzel', serif; color: var(--gold); font-weight: bold;">CHANCELARIA</span>
    </div>
    <span class="text-white small">Painel</span>
</div>

<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>

    <?php include 'menu.php'; ?>


<div class="main-content">
    <div class="container-fluid py-4 px-4">
        
         <div class="page-header">
            <div class="greeting">
                <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;"> Olá, Hospitaleiro!</h2>
                <span class="badge-pro"><i class="fas fa-gem me-1"></i> Loja Ativa <?php echo htmlspecialchars($meuPerfil['nome']); ?> </span>
            </div>
        </div>

        <!-- Cards de Resumo -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card-custom d-flex align-items-center">
                    <div class="icon-circle me-3"><i class="fas fa-user-injured"></i></div>
                    <div>
                        <h6 class="text-muted mb-1">Acompanhamentos Ativos</h6>
                        <h3 class="mb-0 fw-bold"><?= $acompanhamentos_ativos ?></h3>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card-custom d-flex align-items-center">
                    <div class="icon-circle me-3"><i class="fas fa-hand-holding-medical"></i></div>
                    <div>
                        <h6 class="text-muted mb-1">Visitas Pendentes</h6>
                        <h3 class="mb-0 fw-bold">0</h3>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
               <div class="card-custom d-flex align-items-center">
                    <div class="icon-circle me-3"><i class="fas fa-coins"></i></div>
                    <div>
                        <h6 class="text-muted mb-1">Fundo de Beneficência</h6>
                        <h3 class="mb-0 fw-bold <?= $saldo_atual >= 0 ? 'text-success' : 'text-danger' ?>">
                            R$ <?= number_format($saldo_atual, 2, ',', '.') ?>
                        </h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Área de Ações Rápidas -->
        <h5 class="text-gold mb-3 fw-bold"><i class="fas fa-bolt me-2"></i> Ações Rápidas</h5>
        <div class="row g-3">
            <div class="col-md-4">
                <a href="acompanhamentos.php" class="btn btn-outline-warning w-100 py-3 text-start fw-bold">
                    <i class="fas fa-plus-circle me-2"></i> Registrar Novo Acompanhamento
                </a>
            </div>
            <div class="col-md-4">
                <a href="beneficencia.php" class="btn btn-outline-success w-100 py-3 text-start fw-bold">
                    <i class="fas fa-hand-holding-usd me-2"></i> Gerenciar Tronco/Auxílios
                </a>
            </div>
            <div class="col-md-4">
                <a href="relatorios.php" class="btn btn-outline-info w-100 py-3 text-start fw-bold">
                    <i class="fas fa-file-alt me-2"></i> Relatório do Hospitaleiro
                </a>
            </div>
        </div>
        </br>
        <!-- Gráficos e Últimos Lançamentos -->
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card-custom">
                    <h5 class="mb-3 text-gold"><i class="fas fa-chart-bar me-2"></i> Evolução de Acompanhamentos (<?= date('Y') ?>)</h5>
                    <canvas id="chartEvolucao" style="max-height: 300px;"></canvas>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card-custom">
                    <h5 class="mb-3 text-gold"><i class="fas fa-history me-2"></i> Últimos Lançamentos</h5>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm align-middle">
                            <?php foreach($ultimos_lancamentos as $l): ?>
                                <tr>
                                    <td><?= date('d/m', strtotime($l['data_registro'])) ?></td>
                                    <td><?= htmlspecialchars($l['descricao']) ?></td>
                                    <td class="text-end fw-bold <?= $l['tipo']=='Entrada'?'text-success':'text-danger' ?>">
                                        <?= $l['tipo']=='Entrada'?'+':'-' ?> R$ <?= number_format($l['valor'], 2, ',', '.') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    function toggleMobileMenu() {
        const sidebar = document.querySelector('.sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (sidebar) sidebar.classList.toggle('show');
        if (backdrop) backdrop.classList.toggle('show');
    }

    const ctx = document.getElementById('chartEvolucao').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
            datasets: [{
                label: 'Novos Acompanhamentos',
                data: <?= json_encode(array_values($chart_data)) ?>,
                backgroundColor: '#cfa34e'
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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
                    background: '#1e293b', 
                    color: '#fff',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });
    });
</script>
<?php 
    unset($_SESSION['show_2fa_alert']); 
endif; 
?>

</body>
</html>