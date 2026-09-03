<?php
session_start();
require '../configuracoes/config.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) { 
    header("Location: ../login"); exit; 
}
$user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['user']['id'];

// Filtros
$tipo_relatorio = $_GET['tipo_relatorio'] ?? 'beneficencia';
$mes = $_GET['mes'] ?? date('m');
$ano = $_GET['ano'] ?? date('Y');

// Construção da Query dinâmica
$movimentacoes = [];
$total_entradas = 0;
$total_saidas = 0;

if ($tipo_relatorio === 'beneficencia') {
    $sql = "SELECT b.*, c.nome as nome_membro FROM hospitalaria_beneficencia b 
            LEFT JOIN clientes c ON b.membro_id = c.id 
            WHERE b.tenant_id = ? AND MONTH(b.data_registro) = ? AND YEAR(b.data_registro) = ? 
            ORDER BY b.data_registro DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $mes, $ano]);
    $movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($movimentacoes as $m) {
        if($m['tipo'] == 'Entrada') $total_entradas += $m['valor'];
        else $total_saidas += $m['valor'];
    }
} else {
    $sql = "SELECT * FROM hospitalaria_acompanhamentos 
            WHERE tenant_id = ? AND MONTH(data_inicio) = ? AND YEAR(data_inicio) = ? 
            ORDER BY data_inicio DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $mes, $ano]);
    $movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Relatórios - Hospitaleiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-dark: #0f172a; --bg-card: #1e293b; --gold: #cfa34e; --text-light: #f1f5f9; }
        body { background-color: var(--bg-dark); color: var(--text-light); font-family: 'Segoe UI', sans-serif; }
        .card-custom { background: var(--bg-card); border: 1px solid #334155; border-radius: 12px; padding: 20px; }
        .sidebar { width: 260px; position: fixed; top: 0; left: 0; height: 100vh; background-color: var(--bg-card); border-right: 1px solid #334155; z-index: 1000; overflow-y: auto; }
        .main-content { margin-left: 260px; min-height: 100vh; width: calc(100% - 260px); padding: 20px; }
        /* ESTILOS DE IMPRESSÃO - Oculta tudo que não é o relatório na hora de imprimir */
        @media print {
            .no-print, .sidebar, .mobile-header { 
                display: none !important; 
            }
            body { background-color: #fff; color: #000; }
            .card-custom { 
                border: 1px solid #ccc; 
                background-color: #fff; 
                box-shadow: none; 
            }
            .main-content { 
                margin-left: 0 !important; 
                width: 100% !important; 
                padding: 0; 
            }
            .table { color: #000; }
            .text-gold { color: #000 !important; }
            .badge { border: 1px solid #000; color: #000; }
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<main class="main-content">
    <div class="container-fluid">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div class="page-header">
                <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;">
                    <i class="fas fa-book me-2 text-warning"></i> Relatórios
                </h2>
                <p class="text-warning mb-0">Gere relatorios de assistidos, troncos, luto e visitas</p>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                <button onclick="window.print()" class="btn btn-outline-light"><i class="fas fa-print me-2"></i> Imprimir</button>
            </div>
        </div>
        
        <!-- Formulário de Filtro -->
        <div class="card-custom mb-4 no-print">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label text-gold fw-bold">Tipo de Relatório</label>
                    <select name="tipo_relatorio" class="form-select bg-dark border-secondary">
                        <option value="beneficencia" <?= $tipo_relatorio == 'beneficencia' ? 'selected' : '' ?>>Beneficência (Financeiro)</option>
                        <option value="acompanhamentos" <?= $tipo_relatorio == 'acompanhamentos' ? 'selected' : '' ?>>Acompanhamentos (Visitas)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-gold fw-bold">Mês</label>
                    <select name="mes" class="form-select bg-dark border-secondary">
                        <?php for($i=1; $i<=12; $i++): $m = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                            <option value="<?= $m ?>" <?= $mes == $m ? 'selected' : '' ?>><?= $m ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-gold fw-bold">Ano</label>
                    <select name="ano" class="form-select bg-dark border-secondary">
                        <?php for($y=date('Y'); $y>=2024; $y--): ?>
                            <option value="<?= $y ?>" <?= $ano == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-warning w-100"><i class="fas fa-search me-2"></i> Filtrar</button>
                </div>
            </form>
        </div>

        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                <h4 class="text-gold fw-bold m-0"><i class="fas fa-file-invoice me-2"></i> Resultados: <?= ucfirst($tipo_relatorio) ?></h4>
            </div>

            <table class="table table-hover align-middle">
                <thead class="border-secondary text-gold">
                    <tr>
                        <?php if ($tipo_relatorio == 'beneficencia'): ?>
                            <th>Data</th><th>Descrição</th><th>Irmão</th><th>Tipo</th><th class="text-end">Valor (R$)</th>
                        <?php else: ?>
                            <th>Data Início</th><th>Assistido</th><th>Motivo</th><th>Status</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="text-light">
                    <?php foreach($movimentacoes as $m): ?>
                    <tr>
                        <?php if ($tipo_relatorio == 'beneficencia'): ?>
                            <td><?= date('d/m/Y', strtotime($m['data_registro'])) ?></td>
                            <td><?= htmlspecialchars($m['descricao']) ?></td>
                            <td><?= $m['nome_membro'] ?? '-' ?></td>
                            <td><span class="badge <?= $m['tipo']=='Entrada'?'bg-success':'bg-danger' ?>"><?= $m['tipo'] ?></span></td>
                            <td class="text-end fw-bold">R$ <?= number_format($m['valor'], 2, ',', '.') ?></td>
                        <?php else: ?>
                            <td><?= date('d/m/Y', strtotime($m['data_inicio'])) ?></td>
                            <td><?= htmlspecialchars($m['nome_assistido']) ?></td>
                            <td><?= htmlspecialchars($m['motivo']) ?></td>
                            <td><span class="badge bg-secondary"><?= $m['status'] ?></span></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($tipo_relatorio == 'beneficencia'): ?>
                <div class="mt-4 pt-3 border-top border-secondary text-end fw-bold">
                    <div class="text-success">ENTRADAS: R$ <?= number_format($total_entradas, 2, ',', '.') ?></div>
                    <div class="text-danger">SAÍDAS: R$ <?= number_format($total_saidas, 2, ',', '.') ?></div>
                    <div class="text-gold fs-5">SALDO: R$ <?= number_format($total_entradas - $total_saidas, 2, ',', '.') ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>