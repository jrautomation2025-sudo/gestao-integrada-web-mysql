<?php
session_start();
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
require_once '../configuracoes/config.php'; // Ajuste se o nome do seu arquivo de conexão for diferente

$meu_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['user']['id'];

$is_superadmin = isset($_SESSION['is_superadmin']) && $_SESSION['is_superadmin'] == 1;

//$meu_id = $_SESSION['user_id'] ?? 1; // Ajuste para pegar da sessão corretamente

$sqlUsers = "SELECT * FROM usuarios WHERE 1=1";
    if (!$is_superadmin) $sqlUsers .= " AND id = :meu_id OR dono_id = :meu_id";
    
    $stmtUsers = $pdo->prepare($sqlUsers);
    if (!$is_superadmin) $stmtUsers->execute([':meu_id' => $meu_id]);
    else $stmtUsers->execute();
    
    $equipe = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários - Gestão Integrada</title>
    
    <!-- CSS do Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Estilo para manter o padrão escuro do seu sistema -->
    <style>
        /*body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Segoe UI', sans-serif; overflow-x: hidden; }*/
        body {
            background-color: #0f172a; /* Azul bem escuro, padrão do seu layout */
            color: #f8fafc;
            font-family: 'Segoe UI', sans-serif; overflow-x: hidden;
        }
        .card {
            background-color: #1e293b !important;
            border-color: #334155 !important;
        }
        .table-dark {
            background-color: #1e293b;
        }
        .modal-content {
            background-color: #1e293b !important;
            border-color: #334155 !important;
        }
        .form-control, .form-select {
            background-color: #0f172a;
            border: 1px solid #334155;
            color: #f8fafc;
        }
        .form-control:focus, .form-select:focus {
            background-color: #0f172a;
            color: #f8fafc;
            border-color: #eab308;
            box-shadow: 0 0 0 0.25rem rgba(234, 179, 8, 0.25);
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
    </style>
</head>
<body>

    <!-- Barra Superior Mobile (Visível apenas em celulares) -->
<div class="mobile-topbar">
    <div class="d-flex align-items-center">
        <button class="btn btn-outline-warning btn-sm me-3" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <span style="font-family: 'Cinzel', serif; color: var(--gold); font-weight: bold;">TESOURARIA</span>
    </div>
    <span class="text-white small">Painel</span>
</div>

<!-- Backdrop escuro para fechar o menu ao clicar fora -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>

    <?php include 'menu.php'; ?>
    
    <main class="main-content">

    <!--<div class="container-fluid mt-5 px-4" style="margin-left: 200px; width: calc(100% - 260px);">-->
    <div class="container-fluid mt-5 px-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="page-header mb-4">
                <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;"><i class="fas fa-user-plus me-2 text-warning"></i> Minha Conta e Equipe</h2>
                <p class="text-warning">Aqui você concede acesso para outros membros</p>
            </div>
            <?php if ($_SESSION['is_admin'] == 1): ?>
            <button class="btn btn-warning fw-bold text-dark" data-bs-toggle="modal" data-bs-target="#modalUsuario" onclick="novoUsuario()">
                + Novo Usuário
            </button>
            <?php endif; ?>
        </div>
        
        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'sucesso_excluir'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> Usuário excluído com sucesso!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'sucesso_salvar'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> Usuário criado ou atualizado com sucesso!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0">
                        <thead>
                            <tr class="border-bottom border-secondary">
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Telefone</th>
                                <th>Acesso</th>
                                <th>Modulos</th>
                                <?php if ($_SESSION['is_admin'] == 1): ?>
                                <th>Ações</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($equipe as $u): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($u['nome']) ?>
                                    <?php if($u['id'] == $meu_id) echo ' <span class="badge bg-primary ms-1">Você</span>'; ?>
                                </td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['telefone'] ?? '---') ?></td>
                                <td>
                                    <?php if($u['is_admin'] == 1): ?>
                                        <span class="badge bg-success">Administrador</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark">Somente Leitura</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($u['perfil'] == 'admin'): ?>
                                        <span class="badge bg-success">Todos os Modulos</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark">Restrito a Função</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($_SESSION['is_admin'] == 1): ?>
                                    <button class="btn btn-sm btn-outline-warning"
                                        onclick="editarUsuario(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nome']) ?>', '<?= htmlspecialchars($u['email']) ?>', '<?= htmlspecialchars($u['telefone'] ?? '') ?>', <?= $u['is_admin'] ?>, '<?= htmlspecialchars($u['perfil']) ?>')" title="Editar usuário">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <!-- Botão Resetar Senha -->
                                    <button type="button" class="btn btn-sm btn-outline-info me-1" onclick="resetarSenha(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nome']) ?>')" title="Resetar Senha">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <!-- Botão Excluir -->
                                    <form action="salvar_usuario" method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir o usuário <?= htmlspecialchars($u['nome']) ?>? Esta ação não pode ser desfeita.');">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id_usuario" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir usuário">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Cadastro/Edição -->
    <div class="modal fade" id="modalUsuario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content text-white">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title" id="tituloModal">Gerenciar Usuário</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="salvar_usuario" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="usuario_id">
                        
                        <div class="mb-3">
                            <label class="form-label text-light">Nome Completo</label>
                            <input type="text" class="form-control" name="nome" id="nome" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-light">E-mail (Login)</label>
                            <input type="email" class="form-control" name="email" id="email" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-light">Telefone / WhatsApp</label>
                            <input type="text" class="form-control" name="telefone" id="telefone">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-light">Senha</label>
                            <input type="password" class="form-control" name="senha" id="senha">
                            <small class="text-secondary mt-1 d-block">Deixe em branco para manter a senha atual ao editar.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-light">Permissão</label>
                            <select class="form-select" name="is_admin" id="is_admin" required>
                                <option value="0">Somente Leitura</option>
                                <option value="1">Administrador (Acesso Total)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-light">Modulo de Acesso</label>
                            <select class="form-select" name="perfil" id="perfil" required>
                                <option value="tesoureiro">Tesouraria</option>
                                <option value="chanceler">Chancelaria</option>
                                <option value="secretario">Secretaria</option>
                                <option value="hospitaleiro">Hospitalaria</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-top border-secondary">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning fw-bold text-dark">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </main>

    <!-- Scripts do Bootstrap (Necessário para abrir o Modal) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    function novoUsuario() {
        document.getElementById('usuario_id').value = '';
        document.getElementById('nome').value = '';
        document.getElementById('email').value = '';
        document.getElementById('telefone').value = '';
        document.getElementById('is_admin').value = '0';
        document.getElementById('senha').value = '';
        document.getElementById('perfil').value = '';
        document.getElementById('tituloModal').innerText = 'Novo Membro da Equipe';
    }

    function editarUsuario(id, nome, email, telefone, is_admin, perfil) {
        document.getElementById('usuario_id').value = id;
        document.getElementById('nome').value = nome;
        document.getElementById('email').value = email;
        document.getElementById('telefone').value = telefone;
        document.getElementById('is_admin').value = is_admin;
        document.getElementById('senha').value = ''; // Sempre deixa a senha limpa na edição
        document.getElementById('perfil').value = perfil;
        document.getElementById('tituloModal').innerText = 'Editar Perfil';
        
        var myModal = new bootstrap.Modal(document.getElementById('modalUsuario'));
        myModal.show();
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
    
    function resetarSenha(idUsuario, nomeUsuario) {
        Swal.fire({
            title: 'Resetar Senha?',
            text: `Deseja enviar uma senha padrão temporária para ${nomeUsuario}? Ele precisará alterá-la no próximo login.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, resetar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#eab308',
            cancelButtonColor: '#64748b',
            background: '#1e293b',
            color: '#fff',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                let fd = new FormData();
                fd.append('acao', 'resetar_senha');
                fd.append('id_usuario', idUsuario);

                return fetch('salvar_usuario', {
                    method: 'POST',
                    body: fd
                })
                .then(response => response.json())
                .then(resp => {
                    if (resp.status !== 'success') {
                        throw new Error(resp.message || 'Erro ao processar requisição.');
                    }
                    return resp;
                })
                .catch(error => {
                    Swal.showValidationMessage(`Erro: ${error.message}`);
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Sucesso!',
                    text: result.value.message,
                    icon: 'success',
                    background: '#1e293b',
                    color: '#fff',
                    confirmButtonColor: '#eab308'
                }).then(() => {
                    location.reload(); // Recarrega a página para atualizar o status se necessário
                });
            }
        });
    }
    </script>
</body>
</html>