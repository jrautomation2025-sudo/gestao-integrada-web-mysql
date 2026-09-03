<?php
session_start();
require '../configuracoes/config.php';

// Segurança de acesso
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) { 
    header("Location: login"); 
    exit; 
}

$user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['user']['id'];

// =========================================================================
// PROCESSAMENTO DO FORMULÁRIO (SALVAR / EDITAR / EXCLUIR)
// =========================================================================
$msg_sucesso = '';
$msg_erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar') {
        $id = $_POST['id'] ?? '';
        $nome_assistido = trim($_POST['nome_assistido'] ?? '');
        $tipo_relacao = $_POST['tipo_relacao'] ?? '';
        $motivo = trim($_POST['motivo'] ?? '');
        $status = $_POST['status'] ?? 'Em Acompanhamento';
        $data_inicio = $_POST['data_inicio'] ?? date('Y-m-d');
        $ultima_visita = !empty($_POST['ultima_visita']) ? $_POST['ultima_visita'] : null;
        $observacoes = trim($_POST['observacoes'] ?? '');

        try {
            if (empty($id)) {
                // Novo Registro
                $stmt = $pdo->prepare("INSERT INTO hospitalaria_acompanhamentos (tenant_id, nome_assistido, tipo_relacao, motivo, status, data_inicio, ultima_visita, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $nome_assistido, $tipo_relacao, $motivo, $status, $data_inicio, $ultima_visita, $observacoes]);
                $msg_sucesso = "Acompanhamento registrado com sucesso!";
            } else {
                // Atualizar Registro Existente
                $stmt = $pdo->prepare("UPDATE hospitalaria_acompanhamentos SET nome_assistido=?, tipo_relacao=?, motivo=?, status=?, data_inicio=?, ultima_visita=?, observacoes=? WHERE id=? AND tenant_id=?");
                $stmt->execute([$nome_assistido, $tipo_relacao, $motivo, $status, $data_inicio, $ultima_visita, $observacoes, $id, $user_id]);
                $msg_sucesso = "Acompanhamento atualizado com sucesso!";
            }
        } catch (PDOException $e) {
            $msg_erro = "Erro ao salvar: " . $e->getMessage();
        }
    } elseif ($acao === 'excluir') {
        $id_excluir = $_POST['id_excluir'] ?? '';
        try {
            $stmt = $pdo->prepare("DELETE FROM hospitalaria_acompanhamentos WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id_excluir, $user_id]);
            $msg_sucesso = "Registro excluído com sucesso!";
        } catch (PDOException $e) {
            $msg_erro = "Erro ao excluir: " . $e->getMessage();
        }
    }
}

// =========================================================================
// BUSCA OS REGISTROS PARA LISTAGEM
// =========================================================================
$stmt = $pdo->prepare("SELECT * FROM hospitalaria_acompanhamentos WHERE tenant_id = ? ORDER BY FIELD(status, 'Em Acompanhamento', 'Finalizado', 'Óbito'), data_inicio DESC");
$stmt->execute([$user_id]);
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acompanhamentos - Gestão Integrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { --bg-dark: #0f172a; --bg-card: #1e293b; --gold: #cfa34e; --text-light: #f1f5f9; }
        body { background-color: var(--bg-dark); color: var(--text-light); font-family: 'Segoe UI', sans-serif; overflow-x: hidden; }

        .card-custom { background: var(--bg-card); border: 1px solid #334155; border-radius: 12px; padding: 20px; }
        .text-gold { color: var(--gold) !important; }
        .btn-gold { background: var(--gold); border: none; color: #000; font-weight: bold; }
        .btn-gold:hover { background: #b8860b; color: #fff; }
        
        .form-control, .form-select { background-color: #0f172a; border: 1px solid #334155; color: #f1f5f9; }
        .form-control:focus, .form-select:focus { background-color: #0f172a; border-color: var(--gold); color: #f1f5f9; box-shadow: 0 0 0 0.25rem rgba(207, 163, 78, 0.25); }
        .modal-content { background-color: var(--bg-card); border: 1px solid #334155; }
        .table-dark { background-color: transparent; }

        /* LAYOUT DESKTOP */
        .sidebar { width: 260px; position: fixed; top: 0; left: 0; height: 100vh; background-color: var(--bg-card); border-right: 1px solid #334155; z-index: 1000; overflow-y: auto; }
        .main-content { margin-left: 260px; min-height: 100vh; width: calc(100% - 260px); }

        /* HEADER MOBILE */
        .mobile-header { display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px; background-color: var(--bg-card); border-bottom: 1px solid #334155; z-index: 2000; align-items: center; padding: 0 20px; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); z-index: 3000; width: 280px; box-shadow: 5px 0 15px rgba(0,0,0,0.5); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 15px; padding-top: 80px; }
            .mobile-header { display: flex !important; }
        }
    </style>
</head>
<body>

<div class="mobile-topbar mobile-header">
    <div class="d-flex align-items-center">
        <button class="btn btn-outline-warning btn-sm me-3" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <span style="font-family: 'Cinzel', serif; color: var(--gold); font-weight: bold;">HOSPITALARIA</span>
    </div>
</div>

<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>

<?php include 'menu.php'; ?>

<div class="main-content">
    <div class="container-fluid py-4 px-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div class="page-header">
                <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;">
                    <i class="fas fa-user-injured me-2 text-warning"></i> Acompanhamentos
                </h2>
                <p class="text-warning mb-0">Gestão de assistidos, luto e visitas</p>
            </div>
            <button class="btn btn-warning" onclick="abrirModal()">
                <i class="fas fa-plus me-2"></i> Novo Registro
            </button>
        </div>

        <?php if ($msg_sucesso): ?>
            <script>Swal.fire({ icon: 'success', title: 'Sucesso!', text: '<?= $msg_sucesso ?>', background: '#1e293b', color: '#fff', confirmButtonColor: '#cfa34e' });</script>
        <?php endif; ?>
        <?php if ($msg_erro): ?>
            <script>Swal.fire({ icon: 'error', title: 'Erro!', text: '<?= $msg_erro ?>', background: '#1e293b', color: '#fff' });</script>
        <?php endif; ?>

        <div class="card-custom">
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead>
                        <tr class="border-bottom border-secondary">
                            <th>Assistido</th>
                            <th>Relação</th>
                            <th>Motivo</th>
                            <th>Início</th>
                            <th>Última Visita</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lista)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Nenhum acompanhamento registrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lista as $item): ?>
                                <tr>
                                    <td class="fw-bold text-white"><?= htmlspecialchars($item['nome_assistido']) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($item['tipo_relacao']) ?></span></td>
                                    <td><?= htmlspecialchars($item['motivo']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($item['data_inicio'])) ?></td>
                                    <td>
                                        <?php if ($item['ultima_visita']): ?>
                                            <span class="text-info"><i class="fas fa-calendar-check me-1"></i> <?= date('d/m/Y', strtotime($item['ultima_visita'])) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Pendente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $cor = 'warning';
                                            if ($item['status'] == 'Finalizado') $cor = 'success';
                                            if ($item['status'] == 'Óbito') $cor = 'dark';
                                        ?>
                                        <span class="badge bg-<?= $cor ?>"><?= $item['status'] ?></span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-warning me-1" onclick='editarRegistro(<?= json_encode($item) ?>)' title="Editar/Registrar Visita">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirmarExclusao(event, this)">
                                            <input type="hidden" name="acao" value="excluir">
                                            <input type="hidden" name="id_excluir" value="<?= $item['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Modal Formulario -->
<div class="modal fade" id="modalForm" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title fw-bold text-gold" id="modalTitle"><i class="fas fa-notes-medical me-2"></i> Registrar Acompanhamento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="id" id="form_id">

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label text-light">Nome do Assistido (Irmão, Cunhada, etc.)</label>
                            <input type="text" name="nome_assistido" id="form_nome" class="form-control" required placeholder="Nome completo">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-light">Relação</label>
                            <select name="tipo_relacao" id="form_relacao" class="form-select" required>
                                <option value="Irmão">Irmão (Obreiro)</option>
                                <option value="Cunhada">Cunhada</option>
                                <option value="Sobrinho(a)">Sobrinho(a)</option>
                                <option value="Parente/Externo">Parente/Externo</option>
                            </select>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label text-light">Motivo do Acompanhamento</label>
                            <input type="text" name="motivo" id="form_motivo" class="form-control" required placeholder="Ex: Cirurgia, Luto, Dificuldade Financeira...">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-light">Data de Início</label>
                            <input type="date" name="data_inicio" id="form_inicio" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-warning fw-bold">Data da Última Visita</label>
                            <input type="date" name="ultima_visita" id="form_visita" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-light">Status Atual</label>
                            <select name="status" id="form_status" class="form-select">
                                <option value="Em Acompanhamento">Em Acompanhamento</option>
                                <option value="Finalizado">Finalizado / Recuperado</option>
                                <option value="Óbito">Óbito</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label text-light">Observações (Histórico, Necessidades, Endereço de repouso...)</label>
                            <textarea name="observacoes" id="form_obs" class="form-control" rows="4" placeholder="Descreva os detalhes importantes aqui..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Salvar Registro</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 1º: CARREGA O BOOTSTRAP PRIMEIRO -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- 2º: DEPOIS RODA O NOSSO SCRIPT -->
<script>
    function toggleMobileMenu() {
        const sidebar = document.querySelector('.sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (sidebar) sidebar.classList.toggle('show');
        if (backdrop) backdrop.classList.toggle('show');
    }

    // Agora o bootstrap já existe quando ele tenta rodar isso aqui!
    const modalForm = new bootstrap.Modal(document.getElementById('modalForm'));

    function abrirModal() {
        document.getElementById('form_id').value = '';
        document.getElementById('form_nome').value = '';
        document.getElementById('form_relacao').value = 'Irmão';
        document.getElementById('form_motivo').value = '';
        document.getElementById('form_inicio').value = new Date().toISOString().split('T')[0];
        document.getElementById('form_visita').value = '';
        document.getElementById('form_status').value = 'Em Acompanhamento';
        document.getElementById('form_obs').value = '';
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-notes-medical me-2"></i> Novo Acompanhamento';
        modalForm.show();
    }

    function editarRegistro(dados) {
        document.getElementById('form_id').value = dados.id;
        document.getElementById('form_nome').value = dados.nome_assistido;
        document.getElementById('form_relacao').value = dados.tipo_relacao;
        document.getElementById('form_motivo').value = dados.motivo;
        document.getElementById('form_inicio').value = dados.data_inicio;
        document.getElementById('form_visita').value = dados.ultima_visita || '';
        document.getElementById('form_status').value = dados.status;
        document.getElementById('form_obs').value = dados.observacoes || '';
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i> Atualizar Acompanhamento';
        modalForm.show();
    }

    function confirmarExclusao(e, form) {
        e.preventDefault();
        Swal.fire({
            title: 'Tem certeza?',
            text: "Você não poderá reverter a exclusão deste registro!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar',
            background: '#1e293b',
            color: '#fff'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
        return false;
    }
</script>
</body>
</html>
</body>
</html>