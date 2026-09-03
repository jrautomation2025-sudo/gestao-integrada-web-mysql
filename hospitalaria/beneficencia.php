<?php
session_start();
require '../configuracoes/config.php';

// Segurança de acesso
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) { 
    header("Location: ../login"); 
    exit; 
}

$user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['user']['id'];

$msg_sucesso = '';
$msg_erro = '';

// =========================================================================
// PROCESSAMENTO DO FORMULÁRIO (SALVAR / EDITAR / EXCLUIR)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar') {
        $id = $_POST['id'] ?? ''; // Pega o ID (se for edição)
        $tipo = $_POST['tipo'] ?? 'Entrada';
        $data_registro = $_POST['data_registro'] ?? date('Y-m-d');
        // Converte valor de moeda brasileira (R$ 1.500,00) para decimal do banco (1500.00)
        $valor = str_replace(['.', ','], ['', '.'], $_POST['valor'] ?? '0'); 
        $descricao = trim($_POST['descricao'] ?? '');
        $membro_id = !empty($_POST['membro_id']) ? $_POST['membro_id'] : null;

        try {
            if (empty($id)) {
                // Novo Registro
                $stmt = $pdo->prepare("INSERT INTO hospitalaria_beneficencia (tenant_id, tipo, data_registro, valor, descricao, membro_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $tipo, $data_registro, $valor, $descricao, $membro_id]);
                $msg_sucesso = "Registro financeiro salvo com sucesso!";
            } else {
                // Atualizar Registro Existente
                $stmt = $pdo->prepare("UPDATE hospitalaria_beneficencia SET tipo=?, data_registro=?, valor=?, descricao=?, membro_id=? WHERE id=? AND tenant_id=?");
                $stmt->execute([$tipo, $data_registro, $valor, $descricao, $membro_id, $id, $user_id]);
                $msg_sucesso = "Registro financeiro atualizado com sucesso!";
            }
        } catch (PDOException $e) {
            $msg_erro = "Erro ao salvar: " . $e->getMessage();
        }
    } elseif ($acao === 'excluir') {
        $id_excluir = $_POST['id_excluir'] ?? '';
        try {
            $stmt = $pdo->prepare("DELETE FROM hospitalaria_beneficencia WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id_excluir, $user_id]);
            $msg_sucesso = "Registro excluído com sucesso!";
        } catch (PDOException $e) {
            $msg_erro = "Erro ao excluir: " . $e->getMessage();
        }
    }
}

// =========================================================================
// BUSCA OS REGISTROS E CALCULA SALDOS
// =========================================================================
$stmt = $pdo->prepare("SELECT b.*, c.nome as nome_membro FROM hospitalaria_beneficencia b LEFT JOIN clientes c ON b.membro_id = c.id WHERE b.tenant_id = ? ORDER BY b.data_registro DESC, b.id DESC");
$stmt->execute([$user_id]);
$movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtMembros = $pdo->prepare("SELECT id, nome FROM clientes WHERE usuario_id = ? ORDER BY nome ASC");
$stmtMembros->execute([$user_id]);
$membros = $stmtMembros->fetchAll(PDO::FETCH_ASSOC);

$total_entradas = 0;
$total_saidas = 0;
foreach ($movimentacoes as $mov) {
    if ($mov['tipo'] == 'Entrada') {
        $total_entradas += $mov['valor'];
    } else {
        $total_saidas += $mov['valor'];
    }
}
$saldo_atual = $total_entradas - $total_saidas;
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beneficência - Gestão Integrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { --bg-dark: #0f172a; --bg-card: #1e293b; --gold: #cfa34e; --text-light: #f1f5f9; }
        body { background-color: var(--bg-dark); color: var(--text-light); font-family: 'Segoe UI', sans-serif; overflow-x: hidden; }

        .card-custom { background: var(--bg-card); border: 1px solid #334155; border-radius: 12px; padding: 20px; }
        .card-resumo { border-radius: 12px; padding: 20px; color: #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .bg-entrada { background: linear-gradient(135deg, #059669 0%, #10b981 100%); }
        .bg-saida { background: linear-gradient(135deg, #be123c 0%, #e11d48 100%); }
        .bg-saldo { background: linear-gradient(135deg, #b45309 0%, #d97706 100%); }

        .text-gold { color: var(--gold) !important; }
        .btn-gold { background: var(--gold); border: none; color: #000; font-weight: bold; }
        .btn-gold:hover { background: #b8860b; color: #fff; }
        
        .form-control, .form-select { background-color: #0f172a; border: 1px solid #334155; color: #f1f5f9; }
        .form-control:focus, .form-select:focus { background-color: #0f172a; border-color: var(--gold); color: #f1f5f9; box-shadow: 0 0 0 0.25rem rgba(207, 163, 78, 0.25); }
        .modal-content { background-color: var(--bg-card); border: 1px solid #334155; }
        .table-dark { background-color: transparent; }

        .sidebar { width: 260px; position: fixed; top: 0; left: 0; height: 100vh; background-color: var(--bg-card); border-right: 1px solid #334155; z-index: 1000; overflow-y: auto; }
        .main-content { margin-left: 260px; min-height: 100vh; width: calc(100% - 260px); }
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
                    <i class="fas fa-hand-holding-usd me-2 text-warning"></i> Beneficência
                </h2>
                <p class="text-warning mb-0">Gestão do Tronco de Solidariedade e Auxílios</p>
            </div>
            <button class="btn btn-warning" onclick="abrirModal()">
                <i class="fas fa-plus me-2"></i> Lançar Movimentação
            </button>
        </div>

        <?php if ($msg_sucesso): ?>
            <script>Swal.fire({ icon: 'success', title: 'Sucesso!', text: '<?= $msg_sucesso ?>', background: '#1e293b', color: '#fff', confirmButtonColor: '#cfa34e' });</script>
        <?php endif; ?>
        <?php if ($msg_erro): ?>
            <script>Swal.fire({ icon: 'error', title: 'Erro!', text: '<?= $msg_erro ?>', background: '#1e293b', color: '#fff' });</script>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card-resumo bg-entrada">
                    <h6 class="text-white-50 fw-bold"><i class="fas fa-arrow-down me-2"></i> TotaL Arrecadado</h6>
                    <h3 class="mb-0 fw-bold">R$ <?= number_format($total_entradas, 2, ',', '.') ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card-resumo bg-saida">
                    <h6 class="text-white-50 fw-bold"><i class="fas fa-arrow-up me-2"></i> Total de Auxílios (Saídas)</h6>
                    <h3 class="mb-0 fw-bold">R$ <?= number_format($total_saidas, 2, ',', '.') ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card-resumo bg-saldo">
                    <h6 class="text-white-50 fw-bold"><i class="fas fa-piggy-bank me-2"></i> Saldo Disponível</h6>
                    <h3 class="mb-0 fw-bold">R$ <?= number_format($saldo_atual, 2, ',', '.') ?></h3>
                </div>
            </div>
        </div>

        <div class="card-custom">
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead>
                        <tr class="border-bottom border-secondary">
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Descrição / Destinação</th>
                            <th>Irmão Vinculado</th>
                            <th class="text-end">Valor</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($movimentacoes)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma movimentação registrada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($movimentacoes as $mov): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($mov['data_registro'])) ?></td>
                                    <td>
                                        <?php if ($mov['tipo'] == 'Entrada'): ?>
                                            <span class="badge bg-success"><i class="fas fa-plus-circle me-1"></i> Entrada</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="fas fa-minus-circle me-1"></i> Saída</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-white"><?= htmlspecialchars($mov['descricao']) ?></td>
                                    <td class="text-muted small">
                                        <?= $mov['nome_membro'] ? htmlspecialchars($mov['nome_membro']) : '-' ?>
                                    </td>
                                    <td class="text-end fw-bold <?= $mov['tipo'] == 'Entrada' ? 'text-success' : 'text-danger' ?>">
                                        R$ <?= number_format($mov['valor'], 2, ',', '.') ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <!-- NOVO: Botão de Editar -->
                                        <button class="btn btn-sm btn-outline-warning me-1" onclick='editarRegistro(<?= json_encode($mov) ?>)' title="Editar Lançamento">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirmarExclusao(event, this)">
                                            <input type="hidden" name="acao" value="excluir">
                                            <input type="hidden" name="id_excluir" value="<?= $mov['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir Lançamento">
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
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title fw-bold text-gold" id="modalTitle"><i class="fas fa-plus me-2"></i> Lançar Movimentação</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="acao" value="salvar">
                    <!-- NOVO: Campo oculto para o ID -->
                    <input type="hidden" name="id" id="form_id" value="">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-light">Tipo de Lançamento</label>
                            <select name="tipo" id="form_tipo" class="form-select" required onchange="mudarCores()">
                                <option value="Entrada">Entrada (Arrecadação)</option>
                                <option value="Saída">Saída (Auxílio / Gasto)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-light">Data</label>
                            <input type="date" name="data_registro" id="form_data" class="form-control" required value="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label text-light">Valor (R$)</label>
                            <input type="text" name="valor" id="form_valor" class="form-control fw-bold fs-5" required placeholder="0,00" onkeyup="formatarMoeda(this)">
                        </div>

                        <div class="col-12">
                            <label class="form-label text-light">Descrição / Destinação</label>
                            <input type="text" name="descricao" id="form_descricao" class="form-control" required placeholder="Ex: Arrecadação Sessão Magna, Cesta Básica...">
                        </div>

                        <div class="col-12">
                            <label class="form-label text-light">Vincular a um Irmão (Opcional)</label>
                            <select name="membro_id" id="form_membro" class="form-select">
                                <option value="">-- Não vinculado --</option>
                                <?php foreach ($membros as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Use caso seja um auxílio direto a um obreiro.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning" id="btnSalvar">Confirmar Lançamento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleMobileMenu() {
        const sidebar = document.querySelector('.sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (sidebar) sidebar.classList.toggle('show');
        if (backdrop) backdrop.classList.toggle('show');
    }

    const modalForm = new bootstrap.Modal(document.getElementById('modalForm'));

    function abrirModal() {
        document.querySelector('form').reset();
        document.getElementById('form_id').value = ''; // Limpa o ID para garantir que é um novo registro
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus me-2"></i> Lançar Movimentação';
        document.getElementById('form_data').value = new Date().toISOString().split('T')[0];
        mudarCores();
        modalForm.show();
    }

    // NOVO: Função para carregar os dados no formulário e editar
    function editarRegistro(dados) {
        document.getElementById('form_id').value = dados.id;
        document.getElementById('form_tipo').value = dados.tipo;
        document.getElementById('form_data').value = dados.data_registro;
        document.getElementById('form_descricao').value = dados.descricao;
        document.getElementById('form_membro').value = dados.membro_id || '';
        
        // Formatar o valor que vem do banco (ex: 1500.00) para o padrão brasileiro (1.500,00)
        let valorNum = parseFloat(dados.valor).toFixed(2);
        let valorBR = valorNum.replace('.', ',');
        valorBR = valorBR.replace(/\B(?=(\d{3})+(?!\d))/g, "."); 
        document.getElementById('form_valor').value = valorBR;

        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i> Editar Movimentação';
        mudarCores(); // Atualiza a cor do botão baseado no tipo (Entrada/Saída)
        modalForm.show();
    }

    function mudarCores() {
        const tipo = document.getElementById('form_tipo').value;
        const btnSalvar = document.getElementById('btnSalvar');
        
        if(tipo === 'Entrada') {
            btnSalvar.className = 'btn btn-success fw-bold';
            btnSalvar.innerHTML = '<i class="fas fa-arrow-down me-1"></i> Salvar Entrada';
        } else {
            btnSalvar.className = 'btn btn-danger fw-bold';
            btnSalvar.innerHTML = '<i class="fas fa-arrow-up me-1"></i> Salvar Saída';
        }
    }

    function formatarMoeda(elemento) {
        let valor = elemento.value.replace(/\D/g, '');
        valor = (valor/100).toFixed(2) + '';
        valor = valor.replace(".", ",");
        valor = valor.replace(/(\d)(\d{3})(\d{3}),/g, "$1.$2.$3,");
        valor = valor.replace(/(\d)(\d{3}),/g, "$1.$2,");
        elemento.value = valor;
    }

    function confirmarExclusao(e, form) {
        e.preventDefault();
        Swal.fire({
            title: 'Tem certeza?',
            text: "Esta exclusão afetará o Saldo Disponível!",
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