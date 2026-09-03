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

try {
    // Busca todos os obreiros da Loja
    $stmtMembros = $pdo->prepare("SELECT * FROM chancelaria_membros WHERE tenant_id = ? ORDER BY nome ASC");
    $stmtMembros->execute([$tenant_id]);
    $membros = $stmtMembros->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Obreiros - Gestão Integrada</title>
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
                <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;"><i class="fas fa-users me-2 text-warning"></i> Quadro de Obreiros</h2>
                <p class="text-warning">Gerencie os membros da sua Oficina</p>
            </div>
            <button class="btn-gold" data-bs-toggle="modal" data-bs-target="#modalObreiro" onclick="prepararModalNovo()" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                <i class="fas fa-user-plus me-2"></i> Adicionar Obreiro
            </button>
        </div>

        <div class="card-custom table-responsive">
            <table class="table table-dark-custom table-hover">
                <thead>
                    <tr>
                        <th width="20%">Nome / CIM</th>
                        <th width="15%">Grau</th>
                        <th width="15%">Cargo</th>
                        <th width="20%">Contato</th>
                        <th width="10%">Status</th>
                        <th width="10%">Presença</th>
                        <th width="10%" class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($membros) > 0): ?>
                        <?php foreach ($membros as $m): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-white"><?= htmlspecialchars($m['nome']) ?></div>
                                    <small class="text-white">CIM: <?= htmlspecialchars($m['cim'] ?: 'Não inf.') ?></small>
                                </td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($m['grau']) ?></span></td>
                                <td class="text-white">
                                    <?php if ($m['cargo'] === 'veneravel'): ?>
                                        <span>Venerável Mestre</span>
                                    <?php elseif ($m['cargo'] === 'vigilante_1'): ?>
                                        <span>1° Vigilante</span>
                                    <?php elseif ($m['cargo'] === 'vigilante_2'): ?>
                                        <span>2° Vigilante</span>
                                    <?php elseif ($m['cargo'] === 'orador'): ?>
                                        <span>Orador</span>
                                    <?php elseif ($m['cargo'] === 'secretario'): ?>
                                        <span>Secretário</span>
                                    <?php elseif ($m['cargo'] === 'chanceler'): ?>
                                        <span>Chanceler</span>
                                    <?php elseif ($m['cargo'] === 'tesoureiro'): ?>
                                        <span>Tesoureiro</span>
                                    <?php else: ?>
                                        <span><?= '-' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-white">
                                    <?php if(!empty($m['telefone'])): ?>
                                        <small class="text-white"><i class="fab fa-whatsapp text-success"></i> <?= htmlspecialchars($m['telefone']) ?></small><br>
                                    <?php endif; ?>
                                    <?php if(!empty($m['email'])): ?>
                                        <small class="text-white"><i class="fas fa-envelope"></i> <?= htmlspecialchars($m['email']) ?></small>
                                    <?php endif; ?>
                                    <?php if(empty($m['telefone']) && empty($m['email'])): ?>
                                        <span class="text-white">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($m['status'] == 'Ativo'): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><?= htmlspecialchars($m['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($m['presenca'] === 'obrigatoria'): ?>
                                        <span class="badge bg-success">Mandatória</span>
                                    <?php elseif ($m['presenca'] === 'isento'): ?>
                                        <span class="badge bg-warning">Isento</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Não Aplicavél</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-warning" title="Editar" 
                                        data-id="<?= $m['id'] ?>"
                                        data-nome="<?= htmlspecialchars($m['nome']) ?>"
                                        data-cim="<?= htmlspecialchars($m['cim']) ?>"
                                        data-grau="<?= htmlspecialchars($m['grau']) ?>"
                                        data-cargo="<?= htmlspecialchars($m['cargo']) ?>"
                                        data-status="<?= htmlspecialchars($m['status']) ?>"
                                        data-presenca="<?= htmlspecialchars($m['presenca']) ?>"
                                        data-telefone="<?= htmlspecialchars($m['telefone'] ?? '') ?>"
                                        data-email="<?= htmlspecialchars($m['email'] ?? '') ?>"
                                        data-nascimento="<?= htmlspecialchars($m['data_nascimento'] ?? '') ?>"
                                        data-iniciacao="<?= htmlspecialchars($m['data_iniciacao'] ?? '') ?>"
                                        onclick="abrirModalEditar(this)" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" title="Remover" onclick="removerObreiro(<?= $m['id'] ?>)" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-white py-4">Nenhum obreiro registrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Modal Obreiro -->
    <div class="modal fade" id="modalObreiro" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo" style="font-family: 'Cinzel', serif; color: var(--gold);">
                        <i class="fas fa-user-plus me-2"></i> Adicionar Obreiro
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form id="formObreiro">
                    <input type="hidden" name="acao" id="formAcao" value="criar">
                    <input type="hidden" name="id" id="formId" value="">

                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label text-white">Nome Completo *</label>
                                <input type="text" class="form-control" name="nome" id="formNome" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-white">CIM *</label>
                                <input type="tel" class="form-control" name="cim" id="formCim" oninput="this.value = this.value.replace(/\D/g, '')" required>
                            </div>
                            
                            <!-- Datas Restauradas -->
                            <div class="col-md-6">
                                <label class="form-label text-white">Data de Nascimento *</label>
                                <input type="date" class="form-control" name="data_nascimento" id="formDataNascimento">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-white">Data de Iniciação</label>
                                <input type="date" class="form-control" name="data_iniciacao" id="formDataIniciacao">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label text-white">Grau *</label>
                                <select class="form-select" name="grau" id="formGrau" required>
                                    <option value="Aprendiz">Aprendiz</option>
                                    <option value="Companheiro">Companheiro</option>
                                    <option value="Mestre">Mestre</option>
                                    <option value="Mestre Instalado">Mestre Instalado</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-white">Cargo Atual</label>
                                <select class="form-select" name="cargo" id="formCargo">
                                    <option value="veneravel">Venerável Mestre</option>
                                    <option value="vigilante_1">1° Vigilante</option>
                                    <option value="vigilante_2">2° Vigilante</option>
                                    <option value="secretario">Secretário</option>
                                    <option value="orador">Orador</option>
                                    <option value="chanceler">Chanceler</option>
                                    <option value="tesoureiro">Tesoureiro</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-white">Status *</label>
                                <select class="form-select" name="status" id="formStatus" required>
                                    <option value="Ativo">Ativo (Regular)</option>
                                    <option value="Irregular">Irregular</option>
                                    <option value="Adormecido">Adormecido</option>
                                    <option value="Desligado">Desligado</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label text-white">Presença *</label>
                                <select class="form-select" name="presenca" id="formPresenca" required>
                                    <option value="obrigatoria">Obrigatório</option>
                                    <option value="isento">Isento</option>
                                    <option value="inativo">Inativo</option>
                                </select>
                            </div>

                            <!-- Campos de Contato -->
                            <div class="col-md-4">
                                <label class="form-label text-white"><i class="fab fa-whatsapp text-success"></i> Telefone / WhatsApp *</label>
                                <input type="tel" class="form-control" name="telefone" id="formTelefone" oninput="this.value = this.value.replace(/\D/g, '')" placeholder="(00) 00000-0000">
                                <small class="text-warning" style="font-size: 0.75rem;">Necessário para disparos das mensagens</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-white"><i class="fas fa-envelope"></i> E-mail</label>
                                <input type="email" class="form-control" name="email" id="formEmail" placeholder="irmao@email.com">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: #333951; border: none;">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Salvar Obreiro</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const modalObreiro = new bootstrap.Modal(document.getElementById('modalObreiro'));

        function prepararModalNovo() {
            document.getElementById('formObreiro').reset();
            document.getElementById('formAcao').value = 'criar';
            document.getElementById('formId').value = '';
            document.getElementById('formStatus').value = 'Ativo';
            document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-user-plus me-2"></i> Adicionar Obreiro';
        }

        function abrirModalEditar(btn) {
            document.getElementById('formAcao').value = 'editar';
            document.getElementById('formId').value = btn.getAttribute('data-id');
            document.getElementById('formNome').value = btn.getAttribute('data-nome');
            document.getElementById('formCim').value = btn.getAttribute('data-cim');
            document.getElementById('formGrau').value = btn.getAttribute('data-grau');
            document.getElementById('formCargo').value = btn.getAttribute('data-cargo');
            document.getElementById('formStatus').value = btn.getAttribute('data-status');
            document.getElementById('formPresenca').value = btn.getAttribute('data-presenca');
            document.getElementById('formTelefone').value = btn.getAttribute('data-telefone');
            document.getElementById('formEmail').value = btn.getAttribute('data-email');
            
            // Lendo as datas
            document.getElementById('formDataNascimento').value = btn.getAttribute('data-nascimento');
            document.getElementById('formDataIniciacao').value = btn.getAttribute('data-iniciacao');
            
            document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-user-edit me-2"></i> Editar Obreiro';
            modalObreiro.show();
        }

        document.getElementById('formObreiro').addEventListener('submit', async function(e) {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(this).entries());
            const btnSubmit = this.querySelector('button[type="submit"]');
            const textoOriginal = btnSubmit.innerHTML;
            
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
            btnSubmit.disabled = true;

            try {
                const response = await fetch('obreiro_action', {
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

        function removerObreiro(id) {
            Swal.fire({
                title: 'Remover Obreiro?',
                text: "Atenção: Ao remover, o histórico de presenças dele também será impactado.",
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
                        const response = await fetch('obreiro_action', {
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