<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../configuracoes/config.php';

// Verificação de segurança
if (!isset($_SESSION['tenant_id'])) {
    die("Acesso negado. Redirecionando para o login...");
}
$tenant_id = $_SESSION['tenant_id'];
$sessao_id = isset($_GET['sessao_id']) ? (int)$_GET['sessao_id'] : 0;

try {
    // Busca todas as sessões da Loja para o Select
    $stmtSessoes = $pdo->prepare("SELECT id, data_sessao, tipo, grau FROM chancelaria_sessoes WHERE tenant_id = ? ORDER BY data_sessao DESC");
    $stmtSessoes->execute([$tenant_id]);
    $sessoes = $stmtSessoes->fetchAll(PDO::FETCH_ASSOC);

    // Se uma sessão foi escolhida, busca os visitantes dela
    $visitantes = [];
    if ($sessao_id > 0) {
        $stmtVis = $pdo->prepare("SELECT * FROM chancelaria_visitantes WHERE tenant_id = ? AND sessao_id = ? ORDER BY nome ASC");
        $stmtVis->execute([$tenant_id, $sessao_id]);
        $visitantes = $stmtVis->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitantes - Gestão Integrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { --bg-main: #141724; --bg-card: #222738; --text-main: #e2e8f0; --gold: #f5c041; --gold-hover: #dca732; --border-color: #333951; }
        body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 260px; padding: 30px 40px; width: calc(100% - 260px); }
        .card-custom { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; padding: 25px; }
        .btn-gold { background-color: var(--gold); color: #000; font-weight: 600; padding: 10px 20px; border: none; border-radius: 6px; }
        .btn-gold:hover { background-color: var(--gold-hover); }
        
        .form-select-dark { background-color: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); }
        .form-select-dark:focus { border-color: var(--gold); box-shadow: 0 0 0 0.25rem rgba(245, 192, 65, 0.25); color: var(--text-main); }
        
        .table-dark-custom { color: var(--text-main); vertical-align: middle; }
        .table-dark-custom thead th { background-color: rgba(0,0,0,0.2); color: var(--gold); border-bottom: 2px solid var(--border-color); font-weight: 600; text-transform: uppercase; font-size: 0.85rem; }
        .table-dark-custom tbody td { border-bottom: 1px solid var(--border-color); padding: 15px 10px; background: transparent; }

        /* Estilo do Modal */
        .modal-content { background-color: var(--bg-card) !important; border: 1px solid var(--border-color) !important; color: var(--text-main) !important; }
        .modal-header, .modal-footer { border-color: var(--border-color) !important; }
        .form-control, .form-select { background-color: var(--bg-main) !important; border: 1px solid var(--border-color) !important; color: var(--text-main) !important; }
        .form-control:focus, .form-select:focus { border-color: var(--gold) !important; box-shadow: 0 0 0 0.25rem rgba(245, 192, 65, 0.25) !important; color: var(--text-main) !important; background-color: var(--bg-main) !important; }
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
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;"><i class="fas fa-suitcase-rolling me-2 text-warning"></i> Livro de Visitantes</h2>
                <p class="text-warning">Registre os Irmãos de outras oficinas</p>
            </div>
            
            <?php if ($sessao_id > 0): ?>
            <button class="btn-gold" data-bs-toggle="modal" data-bs-target="#modalVisitante" onclick="prepararModalNovo()" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                <i class="fas fa-plus me-2"></i> Adicionar Visitante
            </button>
            <?php endif; ?>
        </div>

        <div class="card-custom mb-4">
            <div class="row align-items-end">
                <div class="col-md-6">
                    <label class="form-label text-white">Selecione a Sessão</label>
                    <select class="form-select form-select-dark" onchange="window.location.href='visitantes.php?sessao_id='+this.value">
                        <option value="0">Escolha uma sessão...</option>
                        <?php foreach ($sessoes as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $s['id'] == $sessao_id ? 'selected' : '' ?>>
                                <?= date('d/m/Y', strtotime($s['data_sessao'])) ?> - <?= htmlspecialchars($s['tipo']) ?> (Grau <?= $s['grau_trabalho'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Mensagem de orientação caso nenhuma sessão esteja selecionada -->
            <?php if ($sessao_id == 0): ?>
                <div class="text-center text-white py-5 mt-3 border-top" style="border-color: var(--border-color) !important;">
                    <i class="fas fa-arrow-up fa-2x mb-3 text-white"></i><br>
                    Selecione uma sessão acima para registrar ou listar os visitantes.
                </div>
            <?php endif; ?>
        </div>

        <?php if ($sessao_id > 0): ?>
        <div class="card-custom table-responsive">
            <table class="table table-dark-custom table-hover">
                <thead>
                    <tr>
                        <th width="25%">Nome do Visitante</th>
                        <th width="10%">Grau</th>
                        <th width="20%">Loja / Oriente</th>
                        <th width="15%">Potência</th>
                        <th width="15%">Contato</th>
                        <th width="15%" class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($visitantes) > 0): ?>
                        <?php foreach ($visitantes as $v): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold; text-white"><?= htmlspecialchars($v['nome']) ?></div>
                                    <small class="text-white">CIM: <?= htmlspecialchars($v['cim'] ?: 'Não inf.') ?></small>
                                </td>
                                <!--<td><span class="badge bg-secondary"><?= ($v['grau'] == 1) ? 'Aprendiz' : (($v['grau'] == 2) ? 'Companheiro' : (($v['grau'] == 3) ? 'Mestre' : 'Mestre Instalado')) ?></span></td>-->
                                <td><span class="badge bg-secondary"><?= match($v['grau']) {
                                            1 => 'Aprendiz',
                                            2 => 'Companheiro',
                                            3 => 'Mestre',
                                            default => 'Mestre Instalado'
                                } ?></span></td>
                                <td class="text-white">
                                    <?= htmlspecialchars($v['loja_origem'] ?: '-') ?><br> 
                                    <small class="text-white"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($v['oriente'] ?: 'Não inf.') ?></small>
                                </td>
                                <td class="text-white"><?= htmlspecialchars($v['potencia'] ?: '-') ?></td>
                                <td>
                                    <?php if(!empty($v['telefone'])): ?>
                                        <small class="text-white"><i class="fab fa-whatsapp text-success"></i> <?= htmlspecialchars($v['telefone']) ?></small><br>
                                    <?php endif; ?>
                                    <?php if(!empty($v['email'])): ?>
                                        <small class="text-white"><i class="fas fa-envelope"></i> <?= htmlspecialchars($v['email']) ?></small>
                                    <?php endif; ?>
                                    <?php if(empty($v['telefone']) && empty($v['email'])): ?>
                                        <span class="text-white">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-warning" title="Editar" 
                                        data-id="<?= $v['id'] ?>"
                                        data-nome="<?= htmlspecialchars($v['nome']) ?>"
                                        data-cim="<?= htmlspecialchars($v['cim']) ?>"
                                        data-grau="<?= htmlspecialchars($v['grau']) ?>"
                                        data-loja="<?= htmlspecialchars($v['loja_origem']) ?>"
                                        data-oriente="<?= htmlspecialchars($v['oriente']) ?>"
                                        data-potencia="<?= htmlspecialchars($v['potencia']) ?>"
                                        data-telefone="<?= htmlspecialchars($v['telefone'] ?? '') ?>"
                                        data-email="<?= htmlspecialchars($v['email'] ?? '') ?>"
                                        onclick="abrirModalEditar(this)" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" title="Remover" onclick="removerVisitante(<?= $v['id'] ?>)" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-white py-4">Nenhum visitante registrado nesta sessão.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </main>

    <!-- Modal Visitante -->
    <div class="modal fade" id="modalVisitante" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo" style="font-family: 'Cinzel', serif; color: var(--gold);">
                        <i class="fas fa-user-plus me-2"></i> Adicionar Visitante
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form id="formVisitante">
                    <input type="hidden" name="acao" id="formAcao" value="criar">
                    <input type="hidden" name="id" id="formId" value="">
                    <input type="hidden" name="sessao_id" value="<?= $sessao_id ?>">

                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label text-white">Nome Completo *</label>
                                <input type="text" class="form-control" name="nome" id="formNome" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-white">CIM</label>
                                <input type="text" class="form-control" name="cim" id="formCim">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label text-white">Grau *</label>
                                <select class="form-select" name="grau" id="formGrau" required>
                                    <option value="1">Aprendiz</option>
                                    <option value="2">Companheiro</option>
                                    <option value="3">Mestre</option>
                                    <option value="4">Mestre Instalado</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label text-white">Loja de Origem</label>
                                <input type="text" class="form-control" name="loja_origem" id="formLoja" placeholder="Ex: Fraternidade e Luz nº 123">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-white">Oriente (Cidade/UF) *</label>
                                <input type="text" class="form-control" name="oriente" id="formOriente" placeholder="Ex: São Paulo / SP" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-white">Potência</label>
                                <input type="text" class="form-control" name="potencia" id="formPotencia" placeholder="Ex: GOB, GLMEP, COMAB..." list="potenciasList">
                                <datalist id="potenciasList">
                                    <option value="GOB">
                                    <option value="Grande Loja (CMSB)">
                                    <option value="COMAB">
                                </datalist>
                            </div>

                            <!-- Novos campos para certificado -->
                            <div class="col-md-6">
                                <label class="form-label text-white"><i class="fab fa-whatsapp text-success"></i> Telefone / WhatsApp</label>
                                <input type="tel" class="form-control" name="telefone" id="formTelefone" placeholder="(00) 00000-0000" oninput="this.value = this.value.replace(/\D/g, '')">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-white"><i class="fas fa-envelope"></i> E-mail</label>
                                <input type="email" class="form-control" name="email" id="formEmail" placeholder="irmao@email.com">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: #333951; border: none;">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Salvar Visitante</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const modalVis = new bootstrap.Modal(document.getElementById('modalVisitante'));

        function prepararModalNovo() {
            document.getElementById('formVisitante').reset();
            document.getElementById('formAcao').value = 'criar';
            document.getElementById('formId').value = '';
            document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-user-plus me-2"></i> Adicionar Visitante';
        }

        // Lê os atributos data-* do botão para Editar de forma segura
        function abrirModalEditar(btn) {
            document.getElementById('formAcao').value = 'editar';
            document.getElementById('formId').value = btn.getAttribute('data-id');
            document.getElementById('formNome').value = btn.getAttribute('data-nome');
            document.getElementById('formCim').value = btn.getAttribute('data-cim');
            document.getElementById('formGrau').value = btn.getAttribute('data-grau');
            document.getElementById('formLoja').value = btn.getAttribute('data-loja');
            document.getElementById('formOriente').value = btn.getAttribute('data-oriente');
            document.getElementById('formPotencia').value = btn.getAttribute('data-potencia');
            document.getElementById('formTelefone').value = btn.getAttribute('data-telefone');
            document.getElementById('formEmail').value = btn.getAttribute('data-email');
            
            document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-user-edit me-2"></i> Editar Visitante';
            modalVis.show();
        }

        // Submissão do Form (Criar/Editar)
        document.getElementById('formVisitante').addEventListener('submit', async function(e) {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(this).entries());
            const btnSubmit = this.querySelector('button[type="submit"]');
            const textoOriginal = btnSubmit.innerHTML;
            
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
            btnSubmit.disabled = true;

            try {
                const response = await fetch('visitantes_action', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();

                if (result.sucesso) {
                    location.reload(); 
                } else {
                    Swal.fire({ icon: 'error', title: 'Erro', text: result.mensagem, background: '#222738', color: '#e2e8f0' });
                }
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha na comunicação.', background: '#222738', color: '#e2e8f0' });
            } finally {
                btnSubmit.innerHTML = textoOriginal;
                btnSubmit.disabled = false;
            }
        });

        // Remoção
        function removerVisitante(id) {
            Swal.fire({
                title: 'Remover Visitante?',
                text: "O registro deste irmão será apagado desta sessão.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#333951',
                confirmButtonText: 'Sim, remover',
                cancelButtonText: 'Cancelar',
                background: '#222738',
                color: '#e2e8f0'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const response = await fetch('visitantes_action', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ acao: 'excluir', id: id })
                        });
                        const res = await response.json();
                        
                        if (res.sucesso) {
                            location.reload();
                        } else {
                            Swal.fire({ icon: 'error', title: 'Erro', text: res.mensagem, background: '#222738', color: '#e2e8f0' });
                        }
                    } catch (error) {
                        Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha ao remover.', background: '#222738', color: '#e2e8f0' });
                    }
                }
            });
        }
        
    
    function toggleMobileMenu() {
        const sidebar = document.querySelector('.sidebar'); // Certifique-se de que a sua tag <nav> ou <div class="sidebar"> do menu tenha essa classe
        const backdrop = document.getElementById('sidebarBackdrop');
        
        if (sidebar) {
            sidebar.classList.toggle('show');
        }
        if (backdrop) {
            backdrop.classList.toggle('show');
        }
    }
    </script>
</body>
</html>