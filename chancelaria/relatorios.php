<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../configuracoes/config.php';

if (!isset($_SESSION['tenant_id'])) {
    die("Acesso negado. Redirecionando para o login...");
}
$tenant_id = $_SESSION['tenant_id'];

$tipoRelatorio = $_GET['tipo'] ?? 'obreiros';

// Se for relatório de sessões, precisamos do ano
$anoSelecionado = $_GET['ano'] ?? date('Y');
$anosDisponiveis = [];

try {
    // Busca os anos disponíveis para o select de filtro (independente do relatório escolhido, para o caso de precisar depois)
    $stmtAnos = $pdo->prepare("SELECT DISTINCT YEAR(data_sessao) as ano FROM chancelaria_sessoes WHERE tenant_id = ? ORDER BY ano DESC");
    $stmtAnos->execute([$tenant_id]);
    $anosDisponiveis = $stmtAnos->fetchAll(PDO::FETCH_COLUMN);
    
    // Se não houver nenhum ano registrado, coloca o ano atual como padrão
    if (empty($anosDisponiveis)) {
        $anosDisponiveis = [date('Y')];
    }

    if ($tipoRelatorio === 'obreiros') {
        $stmt = $pdo->prepare("SELECT * FROM chancelaria_membros WHERE tenant_id = ? ORDER BY grau DESC, nome ASC");
        $stmt->execute([$tenant_id]);
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($tipoRelatorio === 'sessoes') {
        // Filtro de ano adicionado na Query de Sessões
        $stmt = $pdo->prepare("SELECT s.*, 
                               (SELECT COUNT(*) FROM chancelaria_presencas p WHERE p.sessao_id = s.id AND p.status_presenca = 'P') as presentes,
                               (SELECT COUNT(*) FROM chancelaria_visitantes p WHERE p.sessao_id = s.id ) as visitantes 
                               FROM chancelaria_sessoes s 
                               WHERE s.tenant_id = ?
                               AND s.status = 'Realizada'
                               AND YEAR(s.data_sessao) = ?
                               ORDER BY s.data_sessao DESC");
        $stmt->execute([$tenant_id, $anoSelecionado]);
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($tipoRelatorio === 'frequencia') {
        $stmtTotalSessoes = $pdo->prepare("SELECT COUNT(*) FROM chancelaria_sessoes WHERE tenant_id = ? AND status = 'Realizada'");
        $stmtTotalSessoes->execute([$tenant_id]);
        $totalSessoesGeral = (int)$stmtTotalSessoes->fetchColumn();

        $sqlFreq = "SELECT m.id, m.nome, m.cim, m.grau,
                    (SELECT COUNT(*) FROM chancelaria_presencas p 
                     JOIN chancelaria_sessoes s ON p.sessao_id = s.id 
                     WHERE p.membro_id = m.id AND s.tenant_id = ? AND p.status_presenca = 'P') as total_presencas
                    FROM chancelaria_membros m 
                    WHERE m.tenant_id = ? AND m.status = 'Ativo' AND m.presenca = 'obrigatoria'
                    ORDER BY m.nome ASC";
        
        $stmtFreq = $pdo->prepare($sqlFreq);
        $stmtFreq->execute([$tenant_id, $tenant_id]);
        $dados = $stmtFreq->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die("Erro ao gerar relatório: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Gestão Integrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root { --bg-main: #141724; --bg-card: #222738; --text-main: #e2e8f0; --gold: #f5c041; --border-color: #333951; }
        body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 260px; padding: 30px 40px; width: calc(100% - 260px); }
        
        .card-custom { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; padding: 25px; }
        .btn-gold { background-color: var(--gold); color: #000; font-weight: 600; padding: 10px 20px; border: none; border-radius: 6px; }
        .btn-gold:hover { background-color: #dca732; }
        
        .form-select { background-color: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); }
        .form-select:focus { border-color: var(--gold); box-shadow: 0 0 0 0.25rem rgba(245, 192, 65, 0.25); color: var(--text-main); }

        /* Garantindo texto claro nas tabelas */
        .table-dark-custom { color: var(--text-main); vertical-align: middle; }
        .table-dark-custom thead th { background-color: rgba(0,0,0,0.2); color: var(--gold); border-bottom: 2px solid var(--border-color); font-weight: 600; text-transform: uppercase; font-size: 0.85rem; }
        .table-dark-custom tbody td { border-bottom: 1px solid var(--border-color); padding: 15px 10px; background: transparent; color: #e2e8f0 !important; }

        @media print {
            body { background-color: #fff !important; color: #000 !important; }
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 0 !important; }
            .no-print { display: none !important; }
            .card-custom { background-color: #fff !important; border: none !important; padding: 0 !important; }
            .table-dark-custom { color: #000 !important; }
            .table-dark-custom thead th { background-color: #f1f5f9 !important; color: #000 !important; border-bottom: 2px solid #000 !important; }
            .table-dark-custom tbody td { border-bottom: 1px solid #cbd5e1 !important; color: #000 !important; }
            .badge { border: 1px solid #000; color: #000 !important; background: transparent !important; }
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
        <span style="font-family: 'Cinzel', serif; color: var(--gold); font-weight: bold;">CHANCELARIA</span>
    </div>
    <span class="text-white small">Painel</span>
</div>

<!-- Backdrop escuro para fechar o menu ao clicar fora -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>

    <div class="no-print">
        <?php include 'menu.php'; ?>
    </div>

    <main class="main-content">
        <div class="page-header d-flex justify-content-between align-items-center mb-4 no-print">
            <div>
                <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;"><i class="fas fa-print me-2 text-warning"></i> Relatórios do Sistema</h2>
                <p class="text-warning">Gere listagens e relatórios analíticos da Oficina.</p>
            </div>
            <button onclick="window.print()" class="btn btn-warning">
                <i class="fas fa-print me-2"></i> Imprimir / Salvar PDF
            </button>
        </div>

        <div class="card-custom mb-4 no-print">
            <form method="GET" action="relatorios.php" class="row align-items-end g-3">
                <div class="col-md-6">
                    <label class="form-label text-white fw-bold">Selecione o Relatório</label>
                    <select name="tipo" class="form-select" onchange="this.form.submit()">
                        <option value="obreiros" <?= $tipoRelatorio === 'obreiros' ? 'selected' : '' ?>>Quadro de Obreiros Ativos</option>
                        <option value="sessoes" <?= $tipoRelatorio === 'sessoes' ? 'selected' : '' ?>>Histórico de Sessões e Frequência</option>
                        <option value="frequencia" <?= $tipoRelatorio === 'frequencia' ? 'selected' : '' ?>>Frequência Individual e Percentual de Faltas</option>
                    </select>
                </div>
                
                <!-- Filtro de Ano: Só aparece quando o relatório é de sessões -->
                <?php if ($tipoRelatorio === 'sessoes' || $tipoRelatorio === 'frequencia'): ?>
                <div class="col-md-3">
                    <label class="form-label text-white fw-bold">Selecione o Ano</label>
                    <select name="ano" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($anosDisponiveis as $ano): ?>
                            <option value="<?= $ano ?>" <?= $ano == $anoSelecionado ? 'selected' : '' ?>><?= $ano ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <div class="card-custom">
            <div class="text-center mb-4 d-none d-print-block">
                <h3 style="font-family: 'Cinzel', serif; font-weight: bold;">GESTÃO DE CHANCELARIA</h3>
                <h5 class="text-muted">
                    Relatório Oficial: 
                    <?= $tipoRelatorio === 'obreiros' ? 'Quadro de Obreiros' : ($tipoRelatorio === 'sessoes' ? 'Histórico de Sessões (' . $anoSelecionado . ')' : 'Frequência e Percentual de Faltas') ?>
                </h5>
                <hr>
            </div>

            <div class="table-responsive">
                <?php if ($tipoRelatorio === 'obreiros'): ?>
                    <table class="table table-dark-custom">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>CIM</th>
                                <th>Grau</th>
                                <th>Cargo</th>
                                <th>Status</th>
                                <th>Contato</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($dados) > 0): ?>
                                <?php foreach ($dados as $m): ?>
                                    <tr>
                                        <td class="fw-bold text-white"><?= htmlspecialchars($m['nome']) ?></td>
                                        <td class="text-light"><?= htmlspecialchars($m['cim'] ?: '-') ?></td>
                                        <td class="text-light">Grau <?= htmlspecialchars($m['grau']) ?></td>
                                        <td class="text-light"><?= htmlspecialchars($m['cargo'] ?: '-') ?></td>
                                        <td class="text-light"><?= htmlspecialchars($m['status']) ?></td>
                                        <td class="text-light">
                                            <?= htmlspecialchars($m['telefone'] ?: '') ?> 
                                            <?= !empty($m['email']) ? ' / ' . htmlspecialchars($m['email']) : '' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-light">Nenhum registro encontrado.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                <?php elseif ($tipoRelatorio === 'sessoes'): ?>
                    <table class="table table-dark-custom">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Título da Sessão</th>
                                <th>Tipo</th>
                                <th>Grau</th>
                                <th>Tronco</th>
                                <th class="text-center">Obreiros</th>
                                <th class="text-center">Visitantes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($dados) > 0): ?>
                                <?php foreach ($dados as $s): ?>
                                    <tr>
                                        <td class="text-light"><?= date('d/m/Y', strtotime($s['data_sessao'])) ?></td>
                                        <td class="fw-bold text-white"><?= htmlspecialchars($s['titulo']) ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($s['tipo']) ?></span></td>
                                        <td class="text-light">Grau <?= htmlspecialchars($s['grau']) ?></td>
                                        <td class="fw-bold; text-white">R$ <?php echo number_format($s['valor'] ?? 0, 2, ',', '.'); ?></td>
                                        <td class="text-center fw-bold text-warning"><?= $s['presentes'] ?> Obreiros</td>
                                        <td class="text-center fw-bold text-warning"><?= $s['visitantes'] ?> Visitantes</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-light">Nenhuma sessão registrada no ano de <?= $anoSelecionado ?>.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                <?php elseif ($tipoRelatorio === 'frequencia'): ?>
                    <table class="table table-dark-custom">
                        <thead>
                            <tr>
                                <th>Obreiro / CIM</th>
                                <th class="text-center">Total Sessões</th>
                                <th class="text-center">Presenças</th>
                                <th class="text-center">Faltas</th>
                                <th class="text-center">% de Frequência</th>
                                <th class="text-center">% de Faltas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($dados) > 0): ?>
                                <?php foreach ($dados as $f): ?>
                                    <?php 
                                        $presencas = (int)$f['total_presencas'];
                                        $faltas = max(0, $totalSessoesGeral - $presencas);
                                        $percFreq = $totalSessoesGeral > 0 ? ($presencas / $totalSessoesGeral) * 100 : 0;
                                        $percFaltas = $totalSessoesGeral > 0 ? ($faltas / $totalSessoesGeral) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-white"><?= htmlspecialchars($f['nome']) ?></div>
                                            <small class="text-light opacity-75">CIM: <?= htmlspecialchars($f['cim'] ?: '-') ?></small>
                                        </td>
                                        <td class="text-center text-light"><?= $totalSessoesGeral ?></td>
                                        <td class="text-center text-success fw-bold"><?= $presencas ?></td>
                                        <td class="text-center text-danger fw-bold"><?= $faltas ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= $percFreq >= 75 ? 'success' : ($percFreq >= 50 ? 'warning text-dark' : 'danger') ?>">
                                                <?= number_format($percFreq, 1, ',', '.') ?>%
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= $percFaltas > 50 ? 'danger' : ($percFreq <= 50 ? 'warning text-dark' : 'success') ?>">
                                                <?= number_format($percFaltas, 1, ',', '.') ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-light">Nenhuma presença resgistrada foi encontrada no ano de <?= $anoSelecionado ?>.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <script>
    function toggleMobileMenu() {
        const sidebar = document.querySelector('.sidebar'); 
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