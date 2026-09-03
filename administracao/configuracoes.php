<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../configuracoes/config.php';

if (!isset($_SESSION['tenant_id'])) {
    die("Acesso negado. Redirecionando para o login...");
}
$tenant_id = $_SESSION['tenant_id'];

try {
    // Busca as configurações atuais do usuário/tenant
    $stmt = $pdo->prepare("SELECT whatsapp_grupo_id FROM usuarios WHERE id = ?");
    $stmt->execute([$tenant_id]);
    $configAtual = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $whatsapp_grupo_id = $configAtual['whatsapp_grupo_id'] ?? '';
} catch (PDOException $e) {
    die("Erro ao carregar configurações: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chancelaria - Configurações</title>
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
        
        .form-control { background-color: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); }
        .form-control:focus { border-color: var(--gold); box-shadow: 0 0 0 0.25rem rgba(245, 192, 65, 0.25); color: var(--text-main); }
        .info-box { background-color: rgba(245, 192, 65, 0.1); border-left: 4px solid var(--gold); padding: 15px; border-radius: 4px; font-size: 0.9rem; }
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
        
    <div class="container-fluid mt-5 px-4">
        <div class="page-header mb-4">
            <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;"><i class="fas fa-cogs me-2 text-warning"></i> Configurações da Loja</h2>
            <p class="text-warning">Gerencie as integrações e parâmetros da sua conta</p>
        </div>

        <div class="row">
            <div class="col-md-7">
                <div class="card-custom">
                    <form id="formConfig">
                        <h5 class="mb-4 text-warning border-bottom pb-2" style="border-color: var(--border-color) !important;">
                            <i class="fab fa-whatsapp me-2"></i> Integração WhatsApp
                        </h5>

                        <div class="mb-4">
                            <label class="form-label text-white">ID do Grupo Oficial da Loja</label>
                            <input type="text" class="form-control" name="whatsapp_grupo_id" id="whatsapp_grupo_id" 
                                   value="<?= htmlspecialchars($whatsapp_grupo_id) ?>" 
                                   placeholder="Ex: 12036300000000000@g.us">
                            <small class="text-muted mt-2 d-block">Este ID será utilizado para disparar comunicados gerais aos obreiros.</small>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-warning" id="btnSalvar" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                                <i class="fas fa-save me-2"></i> Salvar Configurações
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-md-5">
                <div class="card-custom info-box">
                    <h6 class="text-warning fw-bold mb-3"><i class="fas fa-lightbulb me-2"></i> Como descobrir o ID do Grupo?</h6>
                    <p class="text-white mb-2">O ID de um grupo do WhatsApp geralmente termina em <code>@g.us</code>.</p>
                    <p class="text-white mb-2">Para descobri-lo, você pode:</p>
                    <ul class="text-white mb-0" style="padding-left: 20px;">
                        <!--<li class="mb-1">Utilizar a rota <code>/group/fetchAll</code> (ou similar) da sua API (Evolution/Z-API).</li>-->
                        <li class="mb-1">Inspecionar o WhatsApp Web no navegador.</li>
                        <!--<li>Ou enviar uma mensagem de teste no grupo e ler o JSON recebido no webhook do n8n.</li>-->
                    </ul>
                </div>
            </div>
        </div>
    </div>
    </main>

    <script>
        document.getElementById('formConfig').addEventListener('submit', async function(e) {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(this).entries());
            const btn = document.getElementById('btnSalvar');
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Salvando...';
            btn.disabled = true;

            try {
                const response = await fetch('configuracoes_action', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();

                if (result.sucesso) {
                    Swal.fire({ 
                        icon: 'success', 
                        title: 'Sucesso!', 
                        text: result.mensagem, 
                        background: '#222738', 
                        color: '#e2e8f0',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Erro', text: result.mensagem, background: '#222738', color: '#e2e8f0' });
                }
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha na comunicação com o servidor.', background: '#222738', color: '#e2e8f0' });
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });

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