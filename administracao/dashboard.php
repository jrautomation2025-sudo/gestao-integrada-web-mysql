<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../configuracoes/config.php';

date_default_timezone_set('America/Sao_Paulo');

// Segurança: Apenas administradores podem acessar
if (!isset($_SESSION['tenant_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    echo "<script>alert('Acesso negado. Área restrita ao administrador do sistema.'); window.location.href='/';</script>";
    exit;
}

$tenant_id = $_SESSION['tenant_id'];

$mensagem = $_SESSION['mensagem'] ?? '';
$erro = $_SESSION['erro'] ?? '';
unset($_SESSION['mensagem'], $_SESSION['erro']);

// 1. Processamento: Bloquear / Desbloquear Usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_usuario'])) {
    $alvo_id = (int)$_POST['usuario_id'];
    $nova_situacao = $_POST['acao_usuario'] === 'bloquear' ? 'Inativo' : 'Ativo';
    
    // Evita que o admin bloqueie a si mesmo
    if ($alvo_id === $_SESSION['user_id'] ?? $_SESSION['user']['id']) {
        $_SESSION['erro'] = "Você não pode bloquear o próprio usuário.";
    } else {
        try {
            // Ajuste o nome da sua tabela de usuários caso seja diferente (ex: 'users', 'usuarios_sistema')
            $stmt = $pdo->prepare("UPDATE usuarios SET permissao = ? WHERE id = ?");
            $stmt->execute([$nova_situacao, $alvo_id]);
            $_SESSION['mensagem'] = "Status do usuário atualizado para: $nova_situacao.";
        } catch (PDOException $e) {
            $_SESSION['erro'] = "Erro ao atualizar usuário.";
        }
    }
    header("Location: dashboard");
    exit;
}

// ======================================================================
// VERIFICA SE É O SUPER ADMIN (Dono do Sistema)
// ======================================================================
$is_superadmin = isset($_SESSION['is_superadmin']) && $_SESSION['is_superadmin'] == 1;

// 2. Coleta de Métricas
try {
    // ---------------------------------------------------------
    // Total de usuários na Loja/Tenant (ou em todo o sistema)
    // ---------------------------------------------------------
    $sqlUsers = "SELECT COUNT(*) FROM usuarios WHERE 1=1";
    if (!$is_superadmin) $sqlUsers .= " AND id = :tenant_id OR dono_id = :tenant_id";
    
    $stmtUsers = $pdo->prepare($sqlUsers);
    if (!$is_superadmin) $stmtUsers->execute([':tenant_id' => $tenant_id]);
    else $stmtUsers->execute();
    
    $total_usuarios = $stmtUsers->fetchColumn() ?: 0;

    // ---------------------------------------------------------
    // Usuários online nas últimas 24h
    // ---------------------------------------------------------
    $sqlOnline = "SELECT COUNT(DISTINCT usuario_id) FROM logs_acesso WHERE acao = 'login' AND data_acesso >= NOW() - INTERVAL 1 DAY";
    if (!$is_superadmin) $sqlOnline .= " AND tenant_id = :tenant_id";
    
    $stmtOnline = $pdo->prepare($sqlOnline);
    if (!$is_superadmin) $stmtOnline->execute([':tenant_id' => $tenant_id]);
    else $stmtOnline->execute();
    
    $usuarios_24h = $stmtOnline->fetchColumn() ?: 0;

    // ---------------------------------------------------------
    // Buscando a lista de usuários para controle de acesso
    // ---------------------------------------------------------
    // Adicionei 'is_superadmin' e 'dono_id' no SELECT para usar no HTML se quiser
    $sqlLista = "SELECT id, nome, email, permissao, is_admin, is_superadmin, dono_id FROM usuarios WHERE 1=1";
    if (!$is_superadmin) $sqlLista .= " AND id = :tenant_id OR dono_id = :tenant_id";
    $sqlLista .= " ORDER BY nome ASC";
    
    $stmtLista = $pdo->prepare($sqlLista);
    if (!$is_superadmin) $stmtLista->execute([':tenant_id' => $tenant_id]);
    else $stmtLista->execute();
    
    $lista_usuarios = $stmtLista->fetchAll(PDO::FETCH_ASSOC);

    // ---------------------------------------------------------
    // Buscando últimos 5 logs de acesso
    // ---------------------------------------------------------
    $sqlLogs = "SELECT l.acao, l.ip, l.data_acesso, u.nome, l.tenant_id 
                FROM logs_acesso l
                LEFT JOIN usuarios u ON l.usuario_id = u.id
                WHERE 1=1";
    if (!$is_superadmin) $sqlLogs .= " AND l.tenant_id = :tenant_id";
    $sqlLogs .= " ORDER BY l.data_acesso DESC LIMIT 5";
    
    $stmtLogs = $pdo->prepare($sqlLogs);
    if (!$is_superadmin) $stmtLogs->execute([':tenant_id' => $tenant_id]);
    else $stmtLogs->execute();
    
    $ultimos_logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Fallback caso ocorra algum erro (como coluna ausente antes de atualizar o banco)
    $total_usuarios = $usuarios_24h = 0;
    $lista_usuarios = $ultimos_logs = [];
}

// =========================================================
// 3. DADOS DO PERFIL E BADGE
// =========================================================
$stmt = $pdo->prepare("SELECT nome, telefone, plano, creditos_ia, plano_expiracao FROM usuarios WHERE id = ?");
$stmt->execute([$tenant_id]);
$meuPerfil = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - Gestão Integrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
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

        .table-dark-custom { color: var(--text-main); vertical-align: middle; }
        .table-dark-custom thead th { background-color: rgba(0,0,0,0.2); color: var(--gold); border-bottom: 2px solid var(--border-color); font-weight: 600; text-transform: uppercase; font-size: 0.85rem; }
        .table-dark-custom tbody td { border-bottom: 1px solid var(--border-color); padding: 15px 10px; background: transparent; color: #e2e8f0 !important; }

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

<div class="mobile-topbar">
    <div class="d-flex align-items-center">
        <button class="btn btn-outline-warning btn-sm me-3" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <span style="font-family: 'Cinzel', serif; color: var(--gold); font-weight: bold;">ADMINISTRAÇÃO</span>
    </div>
    <span class="text-white small">Painel Geral</span>
</div>

<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>

<?php include 'menu.php'; ?>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
         <div class="page-header">
            <div class="greeting">
                <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;"> Olá, Administrador!</h2>
                <span class="badge-pro"><i class="fas fa-gem me-1"></i> Loja Ativa <?php echo htmlspecialchars($meuPerfil['nome']); ?> </span>
            </div>
        </div>
    </div>

    <?php if (!empty($mensagem)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensagem) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($erro)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($erro) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Cards de Métricas -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card-custom stat-card border-left-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase small mb-1">Total de Usuários</h6>
                        <h3 class="fw-bold text-white mb-0"><?= $total_usuarios ?></h3>
                    </div>
                    <div class="fs-1 text-info opacity-75"><i class="fas fa-users-cog"></i></div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card-custom stat-card border-left-success" style="border-left-color: #198754 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase small mb-1">Logados nas últimas 24h</h6>
                        <h3 class="fw-bold text-white mb-0"><?= $usuarios_24h ?></h3>
                    </div>
                    <div class="fs-1 text-success opacity-75"><i class="fas fa-user-check"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Controle de Usuários (Acessos) -->
        <div class="col-lg-7 mb-4">
            <div class="card-custom h-100">
                <h5 class="text-warning mb-3"><i class="fas fa-users-slash me-2"></i>Controle de Acessos</h5>
                <div class="table-responsive">
                    <table class="table table-dark-custom">
                        <thead>
                            <tr>
                                <th>Usuário</th>
                                <th>Nível</th>
                                <th>Status</th>
                                <th class="text-end">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($lista_usuarios as $u): ?>
                                <?php 
                                    $isSelf = ($u['id'] == ($_SESSION['user_id'] ?? $_SESSION['user']['id'])); 
                                    $badgeColor = ($u['permissao'] == 'Ativo') ? 'bg-success' : 'bg-danger';
                                    
                                    // Define o rótulo do papel
                                    if ($u['is_superadmin'] == 1) {
                                        $papel = '<span class="text-warning fw-bold"><i class="fas fa-crown"></i> Super Admin</span>';
                                    } elseif ($u['is_admin'] == 1) {
                                        $papel = '<span class="text-primary fw-bold"><i class="fa-solid fa-user-pen"></i> Admin</span>';
                                    } else {
                                        $papel = '<span class="text-success fw-bold"><i class="fas fa-user"></i> User</span>';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <span class="fw-bold text-light"><?= htmlspecialchars($u['nome']) ?></span>
                                        <br><small class="text-muted"><?= htmlspecialchars($u['email']) ?></small>
                                    </td>
                                    <td><?= $papel ?></td>
                                    <td><span class="badge <?= $badgeColor ?>"><?= htmlspecialchars($u['permissao'] ?? 'Ativo') ?></span></td>
                                    <td class="text-end">
                                        <?php if (!$isSelf): ?>
                                            <form method="POST" action="dashboard" style="display:inline-block;">
                                                <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                                                <?php if (($u['permissao'] ?? 'Ativo') == 'Ativo'): ?>
                                                    <input type="hidden" name="acao_usuario" value="bloquear">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Deseja realmente BLOQUEAR o acesso deste usuário?')" title="Bloquear Acesso">
                                                        <i class="fas fa-lock"></i> Bloquear
                                                    </button>
                                                <?php else: ?>
                                                    <input type="hidden" name="acao_usuario" value="desbloquear">
                                                    <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('Deseja DESBLOQUEAR o acesso deste usuário?')" title="Liberar Acesso">
                                                        <i class="fas fa-lock-open"></i> Liberar
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge bg-primary ms-1">Você</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Monitor de Logs -->
        <div class="col-lg-5 mb-4">
            <div class="card-custom h-100">
                <h5 class="text-warning mb-3"><i class="fas fa-history me-2"></i>Acessos Recentes</h5>
                <div class="table-responsive">
                    <table class="table table-dark-custom">
                        <thead>
                            <tr>
                                <th>Usuário</th>
                                <th>Ação / IP</th>
                                <th class="text-end">Data / Hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($ultimos_logs) > 0): ?>
                                <?php foreach($ultimos_logs as $log): ?>
                                <?php $bgColor = ($log['acao'] == 'login') ? 'bg-success' : 'bg-danger';?>
                                    <tr>
                                        <td class="fw-bold text-light"><?= htmlspecialchars($log['nome'] ?: 'Sistema') ?></td>
                                        <td>
                                            <span class="badge <?= $bgColor ?>"><?= htmlspecialchars($log['acao'] ?? 'login') ?></span>
                                            <br><small class="text-info"><?= htmlspecialchars($log['ip']) ?></small>
                                        </td>
                                        <td class="text-end text-muted small">
                                            <?= date('d/m/y', strtotime($log['data_acesso'])) ?>
                                            <br><small><?= date('H:i:s', strtotime($log['data_acesso'])) ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">Nenhum log de acesso recente.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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