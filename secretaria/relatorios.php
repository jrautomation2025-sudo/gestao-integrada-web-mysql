<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../configuracoes/config.php';

// Garante que o usuário está logado
if (!isset($_SESSION['tenant_id']) && !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['user']['id'];

// Filtros
$tipo_relatorio = $_GET['tipo_relatorio'] ?? 'balaustres';
$filtro_mes = isset($_GET['mes']) && $_GET['mes'] !== '' ? $_GET['mes'] : date('m');
$filtro_ano = isset($_GET['ano']) && $_GET['ano'] !== '' ? $_GET['ano'] : date('Y');

$mesesFull = [1=>'Janeiro', 2=>'Fevereiro', 3=>'Março', 4=>'Abril', 5=>'Maio', 6=>'Junho', 7=>'Julho', 8=>'Agosto', 9=>'Setembro', 10=>'Outubro', 11=>'Novembro', 12=>'Dezembro'];
$nome_mes = $mesesFull[(int)$filtro_mes];

$resultados = [];
$titulo_impresso = "";

// Consulta de acordo com o tipo de relatório
try {
    if ($tipo_relatorio === 'balaustres') {
        $titulo_impresso = "Relatório de Balaustres e Atas - $nome_mes / $filtro_ano";
        $stmt = $pdo->prepare("SELECT * FROM secretaria_balaustres WHERE tenant_id = ? AND MONTH(data_balaustre) = ? AND YEAR(data_balaustre) = ? ORDER BY data_balaustre ASC");
        $stmt->execute([$tenant_id, $filtro_mes, $filtro_ano]);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($tipo_relatorio === 'expedientes') {
        $titulo_impresso = "Relatório de Expedientes - $nome_mes / $filtro_ano";
        $stmt = $pdo->prepare("SELECT * FROM secretaria_expedientes WHERE tenant_id = ? AND MONTH(data_expediente) = ? AND YEAR(data_expediente) = ? ORDER BY data_expediente ASC");
        $stmt->execute([$tenant_id, $filtro_mes, $filtro_ano]);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($tipo_relatorio === 'correspondencias') {
        $titulo_impresso = "Relatório de Correspondências - $nome_mes / $filtro_ano";
        $stmt = $pdo->prepare("SELECT * FROM secretaria_correspondencias WHERE tenant_id = ? AND MONTH(data_recebimento) = ? AND YEAR(data_recebimento) = ? ORDER BY data_recebimento ASC");
        $stmt->execute([$tenant_id, $filtro_mes, $filtro_ano]);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($tipo_relatorio === 'inventario') {
        // Inventário geralmente é geral, então ignoramos o mês/ano na query
        $titulo_impresso = "Relatório Geral de Inventário e Patrimônio";
        $stmt = $pdo->prepare("SELECT * FROM secretaria_inventario WHERE tenant_id = ? ORDER BY categoria ASC, nome ASC");
        $stmt->execute([$tenant_id]);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $resultados = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretaria - Relatórios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root { --bg-main: #141724; --bg-card: #1d2132; --text-main: #e2e8f0; --gold: #f5c041; --border-color: #333951; }
        body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 260px; padding: 30px 40px; width: calc(100% - 260px); }
        
        .card-custom { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        .btn-gold { background-color: var(--gold); color: #141724; font-weight: 600; border: none; }
        .btn-gold:hover { background-color: #dca732; color: #141724; }
        
        .form-control, .form-select { background-color: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); }
        .form-control:focus, .form-select:focus { border-color: var(--gold); box-shadow: 0 0 0 0.25rem rgba(245, 192, 65, 0.25); color: var(--text-main); background-color: var(--bg-main); }

        .table-dark-custom { color: var(--text-main); vertical-align: middle; }
        .table-dark-custom thead th { background-color: rgba(0,0,0,0.2); color: var(--gold); border-bottom: 2px solid var(--border-color); font-weight: 600; text-transform: uppercase; font-size: 0.85rem; }
        .table-dark-custom tbody td { border-bottom: 1px solid var(--border-color); padding: 15px 10px; background: transparent; color: #e2e8f0 !important; }

        .mobile-topbar { display: none; height: 60px; background-color: var(--bg-card); border-bottom: 1px solid var(--border-color); align-items: center; padding: 0 20px; justify-content: space-between; position: fixed; top: 0; left: 0; right: 0; z-index: 2000; }
        
        /* Regras para Impressão */
        .print-header { display: none; }
        @media print {
            @page { margin: 10mm; }
            body { background-color: #fff !important; color: #000 !important; }
            .sidebar, .mobile-topbar, .sidebar-backdrop, .no-print { display: none !important; }
            .main-content { margin: 0 !important; width: 100% !important; padding: 0 !important; }
            .card-custom { border: none !important; box-shadow: none !important; padding: 0 !important; background: #fff !important; }
            
            .print-header { display: block; text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
            .print-header h2 { font-family: 'Cinzel', serif; font-weight: bold; color: #000 !important; margin-bottom: 5px; }
            .print-header p { font-size: 0.9rem; margin: 0; }
            
            .table-dark-custom { width: 100% !important; border-collapse: collapse !important; }
            .table-dark-custom th, .table-dark-custom td { border: 1px solid #999 !important; background: transparent !important; color: #000 !important; padding: 6px !important; }
            .table-dark-custom thead th { background-color: #eee !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .badge { border: 1px solid #000 !important; color: #000 !important; }
        }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); z-index: 3000; width: 280px; transition: 0.3s; position: fixed; height: 100vh; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 15px; padding-top: 80px; }
            .mobile-topbar { display: flex !important; }
        }
    </style>
</head>
<body>

<div class="mobile-topbar no-print">
    <div class="d-flex align-items-center">
        <button class="btn btn-outline-warning btn-sm me-3" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <span style="font-family: 'Cinzel', serif; color: var(--gold); font-weight: bold;">SECRETARIA</span>
    </div>
    <span class="text-white small">Relatórios</span>
</div>

<div class="sidebar-backdrop no-print" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>

<div class="no-print">
    <?php include 'menu.php'; ?>
</div>

<main class="main-content">
    
    <!-- Cabeçalho Exclusivo para Impressão -->
    <div class="print-header">
        <h2>Loja Maçônica</h2>
        <p><strong><?= htmlspecialchars($titulo_impresso) ?></strong></p>
        <p><small>Gerado em: <?= date('d/m/Y H:i') ?></small></p>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;">
                <i class="fas fa-print text-warning me-2"></i> Relatórios da Secretaria
            </h2>
            <p class="text-warning mb-0">Selecione o tipo de relatório e o período desejado.</p>
        </div>
        <button type="button" onclick="window.print()" class="btn btn-warning" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
            <i class="fas fa-print me-2"></i> Imprimir
        </button>
    </div>

    <!-- Filtros de Busca -->
    <div class="card-custom mb-4 no-print">
        <form method="GET" action="relatorios.php" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-bold text-warning">Tipo de Relatório</label>
                <select name="tipo_relatorio" class="form-select" onchange="verificarInventario(this.value)">
                    <option value="balaustres" <?= $tipo_relatorio == 'balaustres' ? 'selected' : '' ?>>Balaustres e Atas</option>
                    <option value="expedientes" <?= $tipo_relatorio == 'expedientes' ? 'selected' : '' ?>>Expedientes</option>
                    <option value="correspondencias" <?= $tipo_relatorio == 'correspondencias' ? 'selected' : '' ?>>Correspondências</option>
                    <option value="inventario" <?= $tipo_relatorio == 'inventario' ? 'selected' : '' ?>>Inventário de Patrimônio</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold text-warning">Mês</label>
                <select name="mes" id="filtro_mes" class="form-select" <?= $tipo_relatorio == 'inventario' ? 'disabled' : '' ?>>
                    <?php foreach($mesesFull as $num => $nome): ?>
                        <option value="<?= $num ?>" <?= $filtro_mes == $num ? 'selected' : '' ?>><?= $nome ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold text-warning">Ano</label>
                <select name="ano" id="filtro_ano" class="form-select" <?= $tipo_relatorio == 'inventario' ? 'disabled' : '' ?>>
                    <?php 
                    $anoAtual = date('Y');
                    for ($a = $anoAtual - 2; $a <= $anoAtual + 2; $a++): 
                    ?>
                        <option value="<?= $a ?>" <?= $filtro_ano == $a ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-warning w-100">
                    <i class="fas fa-search me-1"></i> Gerar
                </button>
            </div>
        </form>
    </div>

    <!-- Tabela de Resultados Dinâmica -->
    <div class="card-custom">
        <h5 class="text-warning mb-3 no-print"><?= htmlspecialchars($titulo_impresso) ?></h5>
        
        <div class="table-responsive">
            <table class="table table-dark-custom mb-0">
                
                <?php if ($tipo_relatorio === 'balaustres'): ?>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Título da Ata / Balaustre</th>
                            <th>Grau</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($resultados as $r): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($r['data_balaustre'])) ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($r['titulo']) ?></td>
                                <td><?= ($r['grau'] == 1) ? 'Aprendiz' : (($r['grau'] == 2) ? 'Companheiro' : (($r['grau'] == 3) ? 'Mestre' : (($r['grau'] == 4) ? 'Administrativa' : (($r['grau'] == 5) ? 'Especial' : 'Magna')))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                
                <?php elseif ($tipo_relatorio === 'expedientes'): ?>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Título / Assunto</th>
                            <th>Tipo</th>
                            <th>Destinatário</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($resultados as $r): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($r['data_expediente'])) ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($r['titulo']) ?></td>
                                <td><?= htmlspecialchars($r['tipo']) ?></td>
                                <td><?= htmlspecialchars($r['destinatario']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>

                <?php elseif ($tipo_relatorio === 'correspondencias'): ?>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Título</th>
                            <th>Fluxo</th>
                            <th>Origem / Destino</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($resultados as $r): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($r['data_recebimento'])) ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($r['titulo']) ?></td>
                                <td><?= htmlspecialchars($r['tipo']) ?></td>
                                <td><?= htmlspecialchars($r['remetente']) ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($r['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>

                <?php elseif ($tipo_relatorio === 'inventario'): ?>
                    <thead>
                        <tr>
                            <th>Cód.</th>
                            <th>Item</th>
                            <th>Categoria</th>
                            <th class="text-center">Qtd</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($resultados as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['codigo_patrimonio'] ?: '-') ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($r['nome']) ?></td>
                                <td><?= htmlspecialchars($r['categoria']) ?></td>
                                <td class="text-center"><?= $r['quantidade'] ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($r['estado_conservacao']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php endif; ?>

                <?php if (empty($resultados)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">Nenhum registro encontrado para os filtros selecionados.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Desabilita mês/ano se for inventário (pois inventário é geral)
function verificarInventario(tipo) {
    const isInventario = (tipo === 'inventario');
    document.getElementById('filtro_mes').disabled = isInventario;
    document.getElementById('filtro_ano').disabled = isInventario;
}

function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar'); 
    const backdrop = document.getElementById('sidebarBackdrop');
    if (sidebar) sidebar.classList.toggle('show');
    if (backdrop) backdrop.classList.toggle('show');
}
</script>
</body>
</html>