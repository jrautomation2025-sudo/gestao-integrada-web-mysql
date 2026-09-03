<?php
session_start();
require 'config.php'; 

// Redireciona para o login se tentar acessar direto pela URL
if (!isset($_SESSION['tenant_id'])) {
    header("Location: login");
    exit;
}

$usuario_id = $_SESSION['tenant_id'];

// Busca os dados APENAS do usuário logado
//$stmt = $pdo->prepare("SELECT * FROM configuracoes_pix WHERE usuario_id = :usuario_id");
//$stmt->execute([':usuario_id' => $usuario_id]);
$stmt = $pdo->prepare("SELECT * FROM configuracoes_pix WHERE usuario_id = :tenant_id");
$stmt->execute([':tenant_id' => $_SESSION['tenant_id']]);
$config = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Configurações do PIX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Mantendo seu padrão visual */
        body {
            background-color: #0f172a; /* Azul bem escuro, padrão do seu layout */
            color: #f8fafc;
            font-family: 'Segoe UI', sans-serif; overflow-x: hidden;
        }
        @media (min-width: 768px) { body { padding-left: 250px; } }
        .card { background-color: #1e293b; border: 1px solid #334155; }
        .text-primary-custom { color: #cfa34e; } /* Seu dourado/accent */
        .btn-custom { background-color: #cfa34e; color: #0f172a; font-weight: bold; }
        .btn-custom:hover { background-color: #b58c3f; color: #0f172a; }
        .form-control { background-color: #0f172a; border-color: #334155; color: #fff; }
        .form-control:focus { border-color: #cfa34e; box-shadow: 0 0 0 0.25rem rgba(207, 163, 78, 0.25); }
        
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
    
<div class="mobile-header">
    <span class="fw-bold text-gold">Menu</span>
    <button class="btn btn-sm btn-outline-light" onclick="toggleSidebar()">☰</button>
</div>

<?php include 'menu.php'; ?>

<div class="container mt-5">
    
    <div class="row justify-content-center">
        <div class="col-md-6">
            
            <h2 class="mb-4"><i class="fa-brands fa-pix text-primary-custom"></i> Configurações de Recebimento</h2>
            
            <?php if(isset($_SESSION['msg_sucesso'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?= $_SESSION['msg_sucesso']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['msg_sucesso']); endif; ?>

            <div class="card shadow-lg">
                <div class="card-body p-4">
                    <form action="admin_pix_action" method="POST">
                        
                        <div class="mb-3">
                            <label for="chave_pix" class="form-label">Chave PIX (Telefone, CPF/CNPJ, E-mail ou Aleatória)</label>
    
                            <!-- Grupo do Input + Botão (note que tirei o mb-3 daqui) -->
                            <div class="input-group mb-1">
                                <input type="password" class="form-control" name="chave_pix" id="chave_pix" value="<?= $config['chave_pix'] ?? '' ?>" required>
                                <button class="btn btn-outline-secondary" type="button" id="btnTogglePix">
                                    👁️
                                </button>
                            </div>
    
                            <!-- Texto de ajuda embaixo do grupo -->
                            <small class="text-secondary">Para celular, use o +55. Para CPF/CNPJ, apenas números.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted">Nome do Titular / Empresa</label>
                            <input type="text" name="titular" class="form-control" 
                                   value="<?= htmlspecialchars($config['titular'] ?? '') ?>" required maxlength="50">
                            <small class="text-muted">Máximo 50 caracteres. Evite acentos.</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-muted">Cidade da Conta</label>
                            <input type="text" name="cidade" class="form-control" 
                                value="<?= htmlspecialchars($config['cidade'] ?? '') ?>" required maxlength="15">
                        </div>

                        <button type="submit" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?> class="btn btn-custom w-100">
                            <i class="fas fa-save"></i> Salvar Configurações
                        </button>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Script rapidinho para o botão de mostrar/ocultar funcionar -->
<script>
document.getElementById('btnTogglePix').addEventListener('click', function() {
    var inputPix = document.getElementById('chave_pix');
    if (inputPix.type === 'password') {
        inputPix.type = 'text'; // Mostra o texto
        this.innerHTML = '🙈'; // Troca o ícone (opcional)
    } else {
        inputPix.type = 'password'; // Esconde o texto
        this.innerHTML = '👁️'; // Volta o ícone
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>