<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../configuracoes/config.php';

// Garante que o usuário está logado
if (!isset($_SESSION['tenant_id']) && !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['user']['id'];
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretaria - Modelo do Certificado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root { --bg-main: #141724; --bg-card: #1d2132; --text-main: #e2e8f0; --gold: #f5c041; --border-color: #333951; }
        body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 260px; padding: 30px 40px; width: calc(100% - 260px); }
        
        .card-custom { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        .btn-gold { background-color: var(--gold); color: #141724; font-weight: 600; border: none; }
        .btn-gold:hover { background-color: #dca732; color: #141724; }
        
        /* Estilo para destacar a imagem na tela escura */
        .preview-imagem {
            max-width: 100%;
            height: auto;
            border: 3px solid #b07d1b;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.7);
        }

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
        <span style="font-family: 'Cinzel', serif; color: var(--gold); font-weight: bold;">SECRETARIA</span>
    </div>
    <span class="text-white small">Modelo de Certificado</span>
</div>

<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>

<?php include 'menu.php'; ?>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;">
                <i class="fas fa-certificate text-warning me-2"></i> Modelo do Certificado de Presença
            </h2>
            <p class="text-warning mb-0">Visualize o layout do certificado que será gerado e enviado aos Irmãos visitantes.</p>
        </div>
        <!-- Ajuste o link do href abaixo para a tela onde você efetivamente gera os certificados -->
        <a href="presenca" class="btn btn-outline-warning">
            <i class="fas fa-arrow-left me-2"></i> Voltar para Emissão
        </a>
    </div>

    <div class="card-custom text-center">
        <div class="mb-4">
            <p class="text-muted small">
                <i class="fas fa-info-circle me-1"></i> Os dados como Nome do Irmão, Loja de Origem, Grau, Título da Sessão, Brasão, Data e Assinaturas serão substituídos dinamicamente pelo sistema no momento da emissão.
            </p>
        </div>
        
        <!-- IMAGEM DE PREVIEW -->
        <img src="imagem_certificado.png" alt="Preview do Certificado" class="preview-imagem">
        
        <div class="mt-4">
            <!-- Ajuste o link do href abaixo para a tela onde você efetivamente gera os certificados -->
            <a href="presenca" class="btn btn-warning btn-lg px-5">
                <i class="fas fa-file-signature me-2"></i> Ir para Emissão de Certificados
            </a>
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