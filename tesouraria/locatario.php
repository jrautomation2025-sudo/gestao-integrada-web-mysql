<?php
session_start();
require '../configuracoes/config.php';

// Segurança
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['user']['id'];

// --- SALVAR (Novo ou Edição) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar') {
    $nome = trim($_POST['nome']);
    $telefone = preg_replace('/[^0-9]/', '', $_POST['telefone']);
    $email = trim($_POST['email']);
    $cliente_id = $_POST['cliente_id'] ?? '';

    if (!empty($nome)) {
        if ($cliente_id) {
            $stmt = $pdo->prepare("UPDATE tesouraria_alugueis_responsavel SET responsavel=?, telefone=?, email=? WHERE id=? AND tenant_id=?");
            $stmt->execute([$nome, $telefone, $email, $cliente_id, $user_id]);
        } else {
            // INSERE COM O CONTEXTO
            $stmt = $pdo->prepare("INSERT INTO tesouraria_alugueis_responsavel (tenant_id, responsavel, telefone, email) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $nome, $telefone, $email]);
        }
    }
    header("Location: locatario"); exit;
}

// DELETE
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM tesouraria_alugueis_responsavel WHERE id=? AND tenant_id=?");
    $stmt->execute([$_GET['delete'], $user_id]);
    header("Location: locatario"); exit;
}

// --- LISTAGEM FILTRADA PELO CONTEXTO ---
$busca = $_GET['busca'] ?? '';
$sql = "SELECT id, tenant_id, responsavel as nome, email, telefone, email_validado, created_at as data_cadastro FROM tesouraria_alugueis_responsavel WHERE tenant_id = ?"; // Filtro adicionado
$params = [$user_id];

if ($busca) { 
    $sql .= " AND (nome LIKE ? OR telefone LIKE ?)"; 
    $params[] = "%$busca%"; 
    $params[] = "%$busca%"; 
}

$sql .= " ORDER BY nome ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membros - Gestão Integrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --bg-dark: #0f172a; --bg-card: #1e293b; --gold: #cfa34e; --text-light: #f1f5f9; }
        body { background-color: var(--bg-dark); color: var(--text-light); font-family: 'Segoe UI', sans-serif; }

        .btn-gold { background: var(--gold); border: none; color: #000; font-weight: 600; padding: 8px 20px; }
        .btn-gold:hover { background: #b8860b; color: #fff; }
        .search-box { background: var(--bg-card); border: 1px solid #334155; color: #fff; padding: 10px; }
        .card-client { background: var(--bg-card); border: 1px solid #334155; border-radius: 12px; padding: 20px; transition: 0.3s; }
        .card-client:hover { border-color: var(--gold); transform: translateY(-3px); }
        .avatar-circle { width: 50px; height: 50px; background: rgba(207, 163, 78, 0.1); color: var(--gold); border: 1px solid var(--gold); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.2rem; }
        
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

    <div class="container-fluid py-4 px-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="page-header mb-4">
                <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;"><i class="fa-solid fa-building-user text-warning"></i> Controle de Locatários</h2>
                <p class="text-warning">Cadastre aqui os responsáveis pela locação do templo</p>
            </div>
            <?php if ($_SESSION['is_admin'] == 1): ?>
            <div class="d-flex gap-2 align-items-center">
            <button class="btn btn-warning shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCliente" onclick="limparModal()">
                + Novo Locatário
            </button>
            <!-- Novo botão de Validar Emails -->
            <button class="btn btn-outline-light" id="btnValidarEmails" onclick="dispararValidacaoEmail()">
                <i class="fas fa-envelope-circle-check me-2"></i> Validar Emails
            </button>
            </div>
            <?php endif; ?>
        </div>

        <div class="row mb-4">
            <div class="col-lg-6">
                <form method="GET" class="input-group">
                    <input type="text" name="busca" class="form-control search-box" placeholder="Buscar..." value="<?php echo htmlspecialchars($busca); ?>">
                    <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>

        <div class="row g-3">
            <?php if(count($clientes) > 0): ?>
                <?php foreach($clientes as $c): ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card-client h-100">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar-circle me-3"><?php echo strtoupper(substr($c['nome'], 0, 1)); ?></div>
                            <div>
                                <h5 class="mb-0 text-white"><?php echo htmlspecialchars($c['nome']); ?></h5>
                                <small class="text-muted">Cadastro: <?php echo date('d/m/y', strtotime($c['data_cadastro'])); ?></small>
                            </div>
                            <?php if ($_SESSION['is_admin'] == 1): ?>
                            <div class="dropdown ms-auto">
                                <button class="btn btn-link text-muted" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                <ul class="dropdown-menu dropdown-menu-dark">
                                    <li><a class="dropdown-item" href="#" onclick='editarCliente(<?php echo json_encode($c); ?>)'>Editar</a></li>
                                    <li><a class="dropdown-item text-danger" href="?delete=<?php echo $c['id']; ?>" onclick="return confirm('Excluir?');">Excluir</a></li>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if($c['telefone']): ?>
                            <div class="mb-2"><i class="fab fa-whatsapp text-success me-2"></i> <?php echo $c['telefone']; ?></div>
                        <?php endif; ?>
                        <?php if($c['email']): ?>
                            <div class="mb-2 small text-muted"><i class="far fa-envelope me-2" <?php echo ($c['email_validado'] == 'Sim') ? 'style="color: #cfa34e;"' : ''; ?>></i> <?php echo $c['email']; ?></div>
                        <?php endif; ?>
                        <?php if ($_SESSION['is_admin'] == 1): ?>
                        <div class="mt-3 pt-3 border-top border-secondary d-flex gap-2">
                            <a href="https://wa.me/55<?php echo preg_replace('/[^0-9]/', '', $c['telefone']); ?>" target="_blank" class="btn btn-sm btn-outline-success flex-grow-1">WhatsApp</a>
                            <!--<a href="criar_orcamento.php?cliente_id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-warning flex-grow-1">Orçamento</a>-->
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5 text-muted">
                    <p><strong>Nenhum membro cadastrado no sistema.</strong></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal fade" id="modalCliente" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-card border-secondary" style="background: var(--bg-card);">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title text-gold">Cadastro Novo Locatário</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="acao" value="salvar">
                        <input type="hidden" name="cliente_id" id="cliente_id">
                        <input type="hidden" name="contexto" value="<?php echo $contexto; ?>">
                        
                        <div class="mb-3"><label class="text-light">Nome</label><input type="text" name="nome" id="inputNome" class="form-control bg-dark text-light border-secondary" required></div>
                        <div class="mb-3"><label class="text-light">Telefone</label><input type="text" name="telefone" id="inputTelefone" class="form-control bg-dark text-light border-secondary"></div>
                        <div class="mb-3"><label class="text-light">Email</label><input type="email" name="email" id="inputEmail" class="form-control bg-dark text-light border-secondary"></div>
                    </div>
                    <div class="modal-footer border-top border-secondary">
                        <button type="submit" class="btn btn-warning">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>
    
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function limparModal() {
            document.getElementById('cliente_id').value = '';
            document.getElementById('inputNome').value = '';
            document.getElementById('inputTelefone').value = '';
            document.getElementById('inputEmail').value = '';
        }
        function editarCliente(c) {
            document.getElementById('cliente_id').value = c.id;
            document.getElementById('inputNome').value = c.nome;
            document.getElementById('inputTelefone').value = c.telefone;
            document.getElementById('inputEmail').value = c.email;
            new bootstrap.Modal(document.getElementById('modalCliente')).show();
        }
        
    function dispararValidacaoEmail() {
    const btn = document.getElementById('btnValidarEmails');
    const conteudoOriginal = btn.innerHTML;

    // Altera o visual do botão para loading
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Processando...';
    btn.disabled = true;

    // Chama o backend PHP
    fetch('../configuracoes/acionar_webhook_email', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if(data.sucesso) {
            alert('Validação iniciada com sucesso! O sistema está processando os emails em segundo plano.');
        } else {
            alert('Erro ao iniciar validação: ' + data.erro);
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
        alert('Ocorreu um erro de comunicação com o servidor.');
    })
    .finally(() => {
        // Restaura o botão ao estado original
        btn.innerHTML = conteudoOriginal;
        btn.disabled = false;
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
