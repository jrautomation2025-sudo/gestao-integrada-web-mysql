<?php
session_start();
require_once '../configuracoes/config.php';

date_default_timezone_set('America/Sao_Paulo');

// ======================================================================
// 1. BLOQUEIO DE SEGURANÇA: Apenas Admin pode acessar esta página
// ======================================================================
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    // Redireciona para o painel principal se não for admin
    header("Location: login"); 
    exit;
}

$tenant_id = $_SESSION['tenant_id'];

// ======================================================================
// 2. FILTROS DE MÊS E ANO
// ======================================================================
$filtro_mes = isset($_GET['mes']) && $_GET['mes'] !== '' ? $_GET['mes'] : date('m');
$filtro_ano = isset($_GET['ano']) && $_GET['ano'] !== '' ? $_GET['ano'] : date('Y');

// ======================================================================
// 3. BUSCA DOS DADOS COM BASE NOS FILTROS
// ======================================================================
/*
$sql = "SELECT l.data_acesso, l.ip, l.acao, l.modulo, u.nome, u.email 
        FROM logs_acesso l
        JOIN usuarios u ON l.usuario_id = u.id
        WHERE l.tenant_id = :tenant_id 
        AND MONTH(l.data_acesso) = :mes 
        AND YEAR(l.data_acesso) = :ano
        ORDER BY l.data_acesso DESC";
        
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':tenant_id' => $tenant_id,
    ':mes'       => $filtro_mes,
    ':ano'       => $filtro_ano
]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
*/
// ======================================================================
// 3. BUSCA DOS DADOS COM BASE NOS FILTROS E NÍVEL DE ACESSO
// ======================================================================
$is_superadmin = isset($_SESSION['is_superadmin']) && $_SESSION['is_superadmin'] == 1;

$sql = "SELECT l.data_acesso, l.ip, l.acao, l.modulo, u.nome, u.email, l.tenant_id 
        FROM logs_acesso l
        JOIN usuarios u ON l.usuario_id = u.id
        WHERE MONTH(l.data_acesso) = :mes 
        AND YEAR(l.data_acesso) = :ano";

// Se NÃO for super admin, filtra apenas os logs da loja (tenant) dele
if (!$is_superadmin) {
    $sql .= " AND l.tenant_id = :tenant_id";
}

$sql .= " ORDER BY l.data_acesso DESC";
        
$stmt = $pdo->prepare($sql);

// Monta os parâmetros dinamicamente
$params = [
    ':mes' => $filtro_mes,
    ':ano' => $filtro_ano
];

if (!$is_superadmin) {
    $params[':tenant_id'] = $tenant_id;
}

$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ======================================================================
// 4. EXPORTAÇÃO PARA CSV
// ======================================================================
if (isset($_GET['exportar_csv'])) {
    // Define os cabeçalhos para forçar o download do arquivo
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=logs_acesso_' . $filtro_ano . '_' . $filtro_mes . '.csv');
    
    // Abre o buffer de saída
    $saida = fopen('php://output', 'w');
    
    // Adiciona o BOM (Byte Order Mark) para o Excel reconhecer os acentos (UTF-8) corretamente
    fprintf($saida, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Escreve o cabeçalho das colunas no CSV
    fputcsv($saida, ['Data e Hora', 'Nome do Usuário', 'E-mail', 'Ação', 'Módulo Acessado', 'Endereço IP'], ';');
    
    // Escreve os dados
    foreach ($logs as $log) {
        fputcsv($saida, [
            date('d/m/Y H:i:s', strtotime($log['data_acesso'])),
            $log['nome'],
            $log['email'],
            $log['acao'],
            $log['modulo'],
            $log['ip']
        ], ';');
    }
    
    // Fecha o arquivo e encerra a execução para não imprimir o HTML
    fclose($saida);
    exit;
}

// Array auxiliar para os meses no select
$mesesFull = [1=>'Janeiro', 2=>'Fevereiro', 3=>'Março', 4=>'Abril', 5=>'Maio', 6=>'Junho', 7=>'Julho', 8=>'Agosto', 9=>'Setembro', 10=>'Outubro', 11=>'Novembro', 12=>'Dezembro'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Acesso - Gestão Integrada</title>
    
    <!-- Inclua aqui o seu CSS do Bootstrap ou outros estilos (ajuste o caminho se necessário) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* Ajuste simples caso você use a sidebar fixada à esquerda */ 
        body {
            background-color: #0f172a; /* Azul bem escuro, padrão do seu layout */
            color: #f8fafc;
            font-family: 'Segoe UI', sans-serif; overflow-x: hidden;
        }

        .content-area {
            padding: 20px;
            /* margin-left: 250px; /* Descomente se sua sidebar tiver 250px de largura e for fixa */
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
    
    /* Estilos extras para o filtro */
    .form-control, .form-select { background-color: #1e293b; border: 1px solid #334155; color: #f8fafc; }
    .form-control:focus, .form-select:focus { background-color: #1e293b; border-color: #f5c041; color: #f8fafc; box-shadow: none; }
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

    <?php include 'menu.php'; ?>
    
    <main class="main-content">

    <!-- ====================================================================== -->
    <!-- INCLUA AQUI O SEU HEADER / SIDEBAR (Ex: include 'sidebar.php'; )       -->
    <!-- ====================================================================== -->

    <div class="content-area">
        <div class="container-fluid mt-5 px-4">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="page-header mb-0">
                    <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;"><i class="fas fa-history me-2 text-warning"></i> Logs de Acesso</h2>
                    <p class="text-warning">Visualize os usuários que acessam o sistema</p>
                </div>
                <!-- Botão de Exportar CSV -->
                <a href="?mes=<?= $filtro_mes ?>&ano=<?= $filtro_ano ?>&exportar_csv=1" class="btn btn-success me-2 fw-bold">
                    <i class="fas fa-file-csv me-2"></i> Exportar CSV
                </a>
            </div>

            <!-- Filtros de Mês e Ano -->
            <div class="card bg-secondary border-0 shadow mb-4 p-3" style="background-color: #1e293b !important;">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-warning small">Filtrar por Mês</label>
                        <select name="mes" class="form-select">
                            <?php foreach($mesesFull as $num => $nome): ?>
                                <option value="<?= $num ?>" <?= $filtro_mes == $num ? 'selected' : '' ?>><?= $nome ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-warning small">Filtrar por Ano</label>
                        <select name="ano" class="form-select">
                            <?php 
                            $anoAtual = date('Y');
                            for ($a = $anoAtual - 1; $a <= $anoAtual + 1; $a++): 
                            ?>
                                <option value="<?= $a ?>" <?= $filtro_ano == $a ? 'selected' : '' ?>><?= $a ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-warning w-100 fw-bold">
                            <i class="fas fa-search me-1"></i> Buscar
                        </button>
                    </div>
                </form>
            </div>

            <div class="card bg-secondary border-0 shadow">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-striped table-hover m-0">
                            <thead>
                                <tr>
                                    <th class="py-3 px-4">Data e Hora</th>
                                    <th class="py-3 px-4">Nome do Usuário</th>
                                    <th class="py-3 px-4">E-mail</th>
                                    <th class="py-3 px-4">Ação</th>
                                    <th class="py-3 px-4">Módulo Acessado</th>
                                    <th class="py-3 px-4">Endereço IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($logs) > 0): ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td class="align-middle px-4">
                                                <i class="far fa-clock text-danger me-2"></i>
                                                <?= date('d/m/Y \à\s H:i:s', strtotime($log['data_acesso'])) ?>
                                            </td>
                                            <td class="align-middle px-4 fw-bold">
                                                <?= htmlspecialchars($log['nome']) ?>
                                            </td>
                                            <td class="align-middle px-4 text-white">
                                                <?= htmlspecialchars($log['email']) ?>
                                            </td>
                                            <td class="align-middle px-4 text-white">
                                                <?= htmlspecialchars($log['acao']) ?>
                                            </td>
                                            <td class="align-middle px-4 text-white">
                                                <?= htmlspecialchars($log['modulo'] ?? 'Não informado') ?>
                                            </td>
                                            <td class="align-middle px-4">
                                                <span class="badge bg-dark border border-secondary">
                                                    <?= htmlspecialchars($log['ip']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="fas fa-info-circle mb-2" style="font-size: 2rem;"></i><br>
                                            Nenhum acesso registrado no período de <?= $mesesFull[(int)$filtro_mes] ?> de <?= $filtro_ano ?>.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-dark border-secondary text-white text-center py-3">
                    Exibindo os logs do mês selecionado.
                </div>
            </div>

        </div>
    </div>
    
    </main>

    <!-- ====================================================================== -->
    <!-- INCLUA AQUI O SEU FOOTER / SCRIPTS (Ex: include 'footer.php'; )        -->
    <!-- ====================================================================== -->
    
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