<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../configuracoes/config.php';

if (!isset($_SESSION['tenant_id']) && !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['user']['id'];

$mensagem = $_SESSION['mensagem'] ?? '';
$erro = $_SESSION['erro'] ?? '';
unset($_SESSION['mensagem'], $_SESSION['erro']);

// 1. Processamento de Inserção / Edição
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['frequency'])) {
    $id = $_POST['id'] ?? null;
    $frequency = $_POST['frequency'] ?? 'once';
    $descricao = trim($_POST['descricao'] ?? '');
    $acao = trim($_POST['action'] ?? '');
    $valor = floatval($_POST['valor'] ?? 0);

    $data_hora_agendada = date('Y-m-d H:i:s');
    $day_of_month = null;
    $time_of_day = null;

    if ($frequency === 'once') {
        $data_unica = $_POST['data_unica'] ?? '';
        if (!empty($data_unica)) $data_hora_agendada = str_replace('T', ' ', $data_unica) . ':00';
    } else {
        $day_of_month = !empty($_POST['day_of_month']) ? intval($_POST['day_of_month']) : null;
        $time_of_day = !empty($_POST['time_of_day']) ? $_POST['time_of_day'] . ':00' : null;
    }

    if (!empty($descricao)) {
        try {
            $payload = json_encode(['descricao' => $descricao, 'valor' => $valor]);
            if (!empty($id)) {
                $stmt = $pdo->prepare("UPDATE secretaria_agendamentos SET data_hora_agendada = ?, payload_json = ?, frequency = ?, acao = ?, day_of_month = ?, time_of_day = ? WHERE id = ? AND tenant_id = ? AND tipo = 'tesouraria'");
                $stmt->execute([$data_hora_agendada, $payload, $frequency, $acao, $day_of_month, $time_of_day, $id, $tenant_id]);
                $_SESSION['mensagem'] = "Agendamento atualizado!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO secretaria_agendamentos (tenant_id, data_hora_agendada, tipo, payload_json, status, frequency, acao, day_of_month, time_of_day) VALUES (?, ?, 'tesouraria', ?, 'pendente', ?, ?, ?, ?)");
                $stmt->execute([$tenant_id, $data_hora_agendada, $payload, $frequency, $acao, $day_of_month, $time_of_day]);
                $_SESSION['mensagem'] = "Agendamento registrado!";
            }
        } catch (PDOException $e) {
            $_SESSION['erro'] = "Erro ao salvar: " . $e->getMessage();
        }
    } else {
        $_SESSION['erro'] = "Preencha a descrição obrigatória.";
    }
    header("Location: agendamentos_tesouraria");
    exit;
}

// 2. Exclusão
if (isset($_GET['excluir'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM secretaria_agendamentos WHERE id = ? AND tenant_id = ? AND tipo = 'tesouraria'");
        $stmt->execute([$_GET['excluir'], $tenant_id]);
        $_SESSION['mensagem'] = "Agendamento excluído!";
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao excluir.";
    }
    header("Location: agendamentos_tesouraria");
    exit;
}

// Busca agendamentos
$agendamentos = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM secretaria_agendamentos WHERE tenant_id = ? AND tipo = 'tesouraria' ORDER BY data_hora_agendada ASC");
    $stmt->execute([$tenant_id]);
    $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tesouraria - Agendamentos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --bg-main: #141724; --bg-card: #1d2132; --text-main: #e2e8f0; --gold: #f5c041; --border-color: #333951; }
        body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 260px; padding: 30px 40px; width: calc(100% - 260px); }
        .card-custom { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        .table-dark-custom { color: var(--text-main); vertical-align: middle; }
        .table-dark-custom thead th { background-color: rgba(0,0,0,0.2); color: var(--gold); border-bottom: 2px solid var(--border-color); font-weight: 600; text-transform: uppercase; font-size: 0.85rem; }
        .table-dark-custom tbody td { border-bottom: 1px solid var(--border-color); padding: 15px 10px; background: transparent; }
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
        <button class="btn btn-outline-warning btn-sm me-3" onclick="toggleMobileMenu()"><i class="fas fa-bars"></i></button>
        <span style="font-family: 'Cinzel', serif; color: var(--gold); font-weight: bold;">TESOURARIA</span>
    </div>
</div>
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>
<?php include 'menu.php'; ?>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white;">
                <i class="fas fa-calendar-alt me-2 text-warning"></i> Agendamentos
            </h2>
            <p class="text-warning mb-0">Gerencie disparos automáticos, lembretes ou ações financeiras programadas.</p>
        </div>
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalAgendamento" onclick="limparModal()">
            <i class="fas fa-plus me-2"></i> Novo Agendamento
        </button>
    </div>

    <?php if (!empty($mensagem)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensagem) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card-custom">
        <table class="table table-dark-custom">
            <thead>
                <tr>
                    <th>Agendamento</th>
                    <th>Descrição</th>
                    <th>Ação</th>
                    <!--<th>Valor</th>-->
                    <th>Status</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($agendamentos as $a): 
                    $p = json_decode($a['payload_json'], true);
                    $info = ($a['frequency'] == 'monthly') ? 'Mensal (Todo Dia ' . $a['day_of_month'] . ')' : date('d/m/Y H:i', strtotime($a['data_hora_agendada']));
                ?>
                <tr>
                    <td><i class="fas fa-clock text-warning me-1"></i> <?= $info ?></td>
                    <td class="fw-bold"><?= htmlspecialchars($p['descricao'] ?? '') ?></td>
                    <td class="fw-bold"><?= htmlspecialchars($a['acao']) == 'cobranca' ? 'Enviar Notificação' : ( htmlspecialchars($a['acao']) == 'recibo' ? 'Enviar Comprovante' : 'Enviar Mensagem') ?></td>
                    <!--<td>R$ <?= number_format($p['valor'] ?? 0, 2, ',', '.') ?></td>-->
                    <td><span class="badge bg-<?= $a['status'] == 'pendente' ? 'warning text-dark' : ( $a['status'] == 'agendado' ? 'primary' : ( $a['status'] == 'processando' ? 'secondary' : ( $a['status'] == 'erro' ? 'danger' : 'success'))) ?>"><?= ucfirst($a['status']) ?></span></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-warning me-1" onclick="editarAgendamento(<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>, '<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>')"><i class="fas fa-edit"></i></button>
                        <a href="agendamentos_tesouraria?excluir=<?= $a['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Modal -->
<div class="modal fade" id="modalAgendamento" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="agendamentos_tesouraria" class="modal-content" style="background: var(--bg-card); border: 1px solid var(--border-color);">
            <input type="hidden" name="id" id="agendamento_id">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-warning" id="modalTitle">Novo Agendamento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Frequência</label>
                    <select name="frequency" class="form-select" id="frequency" onchange="toggleFrequency(this.value)">
                        <option value="once">Uma única vez</option>
                        <option value="monthly">Mensal (Recorrente)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Ação</label>
                    <select name="action" class="form-select" id="action" placeholder="Escolha uma ação">
                        <option value="">-- Escolha uma ação --</option>
                        <option value="cobranca">Ação de Cobrança</option>
                        <option value="recibo">Envio de Recibo</option>
                        <option value="notifica">Envio de Mensagem</option>
                    </select>
                </div>
                <div id="div_once">
                    <label class="form-label fw-bold">Data e Hora Específica</label>
                    <input type="datetime-local" name="data_unica" id="data_unica" class="form-control"></div>
                <div id="div_monthly" class="d-none row">
                    <div class="col-6 mb-3"><label class="form-label fw-bold">Dia do Mês (1-28)</label>
                        <input type="number" name="day_of_month" id="day_of_month" class="form-control" min="1" max="28" placeholder="Ex: 10">
                    </div>
                    <div class="col-6 mb-3"><label class="form-label fw-bold">Hora</label>
                        <input type="time" name="time_of_day" id="time_of_day" class="form-control" placeholder="08:00">
                    </div>
                </div></br>
                <div class="mb-3"><label class="form-label fw-bold">Descrição</label>
                    <input type="text" name="descricao" id="descricao" class="form-control" placeholder="Ex: Envio de relatório financeiro" required>
                </div>
                <!--
                <div class="mb-3"><label class="form-label fw-bold">Valor</label>
                    <input type="number" step="0.01" name="valor" id="valor" class="form-control" placeholder="150.00">
                </div>-->
            </div>
            <div class="modal-footer border-secondary">
                <button type="submit" class="btn btn-warning w-100">Salvar Agendamento</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function limparModal() {
    // Reseta ID e Título
    document.getElementById('agendamento_id').value = '';
    document.getElementById('modalTitle').innerText = 'Novo Agendamento';

    // Reseta a frequência
    document.getElementById('frequency').value = 'once';
    toggleFrequency('once');

    // Limpa todos os campos de input
    document.getElementById('action').value = '';
    document.getElementById('data_unica').value = '';
    document.getElementById('day_of_month').value = '';
    document.getElementById('time_of_day').value = '';
    document.getElementById('descricao').value = '';
    //document.getElementById('valor').value = '';
}

function editarAgendamento(a, pJson) {
    let p = typeof pJson === 'string' ? JSON.parse(pJson) : pJson;
    
    document.getElementById('agendamento_id').value = a.id;
    document.getElementById('modalTitle').innerText = 'Editar Agendamento';
    
    document.getElementById('frequency').value = a.frequency;
    toggleFrequency(a.frequency);
    
    document.getElementById('action').value = a.acao;
    
    if(a.frequency === 'once') { 
        document.getElementById('data_unica').value = a.data_hora_agendada.replace(' ', 'T').substring(0, 16); 
    } else { 
        document.getElementById('day_of_month').value = a.day_of_month; 
        document.getElementById('time_of_day').value = a.time_of_day.substring(0, 5); 
    }
    
    document.getElementById('descricao').value = p.descricao;
    //document.getElementById('valor').value = p.valor;
    
    new bootstrap.Modal(document.getElementById('modalAgendamento')).show();
}

function toggleMobileMenu() {
    document.querySelector('.sidebar')?.classList.toggle('show');
    document.getElementById('sidebarBackdrop')?.classList.toggle('show');
}

function toggleFrequency(val) {
    document.getElementById('div_once').classList.toggle('d-none', val === 'monthly');
    document.getElementById('div_monthly').classList.toggle('d-none', val === 'once');
}
</script>
</body>
</html>