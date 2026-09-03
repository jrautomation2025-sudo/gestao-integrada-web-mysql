<?php
session_start();
require '../configuracoes/config.php';
require '../configuracoes/utils/GoogleAuthenticator.php'; // Caminho correto para a classe

if (!isset($_SESSION['user_id'])) { header("Location: /gestao-financeira/login"); exit; }
$user_id = $_SESSION['user_id'];
$ga = new GoogleAuthenticator();

// Busca dados
$stmt = $pdo->prepare("SELECT secret_2fa, ativo_2fa, email FROM usuarios WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$secret = $user['secret_2fa'];
if (!$secret) {
    $secret = $ga->createSecret();
    $pdo->prepare("UPDATE usuarios SET secret_2fa = ? WHERE id = ?")->execute([$secret, $user_id]);
}

// Gera a URL do QR Code
$qrCodeUrl = $ga->getQRCodeUrl('JR Finance ('.$user['email'].')', $secret, 'JR Tech');

// --- AÇÕES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'];
    $codigo = preg_replace('/\s+/', '', $_POST['codigo'] ?? ''); // Remove espaços

    if ($acao === 'ativar') {
        if ($ga->verifyCode($secret, $codigo)) {
            $pdo->prepare("UPDATE usuarios SET ativo_2fa = 1 WHERE id = ?")->execute([$user_id]);
            header("Location: seguranca?msg=ativado"); exit;
        } else {
            $erro = "Código incorreto. Tente novamente.";
        }
    }
    
    if ($acao === 'desativar') {
        $pdo->prepare("UPDATE usuarios SET ativo_2fa = 0, secret_2fa = NULL WHERE id = ?")->execute([$user_id]);
        header("Location: seguranca?msg=desativado"); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Segurança - Gestão Integrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --bg-dark: #0f172a; --bg-card: #1e293b; --gold: #cfa34e; --text-light: #e2e8f0; }
        body { background-color: var(--bg-dark); color: var(--text-light); font-family: 'Segoe UI', sans-serif; }
        
        /* CORREÇÃO DO MENU LATERAL */

        .card-custom { background: var(--bg-card); border: 1px solid #334155; border-radius: 12px; padding: 30px; }
        .text-gold { color: var(--gold); }
        .btn-gold { background: var(--gold); border: none; font-weight: bold; color: #000; }
        .btn-gold:hover { background: #b8860b; color: #fff; }
        
        /* Ajuste da imagem do QR Code */
        .qr-container { background: #fff; padding: 10px; border-radius: 8px; display: inline-block; }
        .btn-voltar { position: absolute; top: 20px; left: 20px; text-decoration: none; color: #94a3b8; font-weight: 500; padding: 10px 15px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.1); transition: all 0.3s ease; background: rgba(15, 23, 42, 0.8); z-index: 1000; }
        .btn-voltar:hover { color: var(--gold); border-color: var(--gold); transform: translateX(-5px); }
    </style>
</head>
<body>
    
    <?php include 'menu.php'; ?>
    
    <div class="main-content">
         
    <div class="container-fluid py-5 px-4">
        
        <div>
            <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;">
                <i class="fas fa-shield-alt text-warning me-2"></i> Segurança 2FA
            </h2>
            <p class="text-warning mb-0">Configure seu duplo fator de segurança.</p>
        </div></br>
        
        <?php if(isset($erro)): ?>
            <div class="alert alert-danger"><?php echo $erro; ?></div>
        <?php endif; ?>

        <div class="card-custom">
            <?php if ($user['ativo_2fa']): ?>
                <div class="text-center py-4">
                    <i class="fas fa-check-circle text-success fa-5x mb-3"></i>
                    <h3 class="text-success fw-bold">Proteção Ativa!</h3>
                    <p class="text-muted">Sua conta está blindada. O código 2FA será exigido no próximo login.</p>
                    <form method="POST" onsubmit="return confirm('Tem certeza que deseja remover a proteção?');">
                        <input type="hidden" name="acao" value="desativar">
                        <button class="btn btn-outline-danger mt-3 px-4">Desativar 2FA</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="row align-items-center">
                    <div class="col-md-5 text-center mb-4 mb-md-0">
                        <div class="qr-container shadow">
                            <?php if($qrCodeUrl): ?>
                                <img src="<?php echo $qrCodeUrl; ?>" alt="QR Code" class="img-fluid" style="max-width: 200px;">
                            <?php else: ?>
                                <p class="text-dark m-0">Erro ao gerar QR Code</p>
                            <?php endif; ?>
                        </div>
                        <div class="mt-2 text-muted small">Use Google Authenticator ou Authy</div>
                    </div>
                    
                    <div class="col-md-7">
                        <h4 class="fw-bold text-white">Configure seu Autenticador</h4>
                        <ol class="text-muted mb-4 ps-3">
                            <li class="mb-2">Baixe o app <strong>Google Authenticator</strong> no celular.</li>
                            <li class="mb-2">Abra o app, toque em "+" e escolha "Ler código QR".</li>
                            <li class="mb-2">Aponte a câmera para o código ao lado.</li>
                            <li>Digite abaixo o código de 6 números que aparecer no app.</li>
                        </ol>
                        
                        <form method="POST">
                            <input type="hidden" name="acao" value="ativar">
                            <div class="mb-3">
                                <label class="form-label text-gold small fw-bold">CÓDIGO DO APP</label>
                                <input type="text" name="codigo" class="form-control bg-dark text-white border-secondary form-control-lg" 
                                       placeholder="000 000" maxlength="7" required 
                                       style="width: 200px; font-size: 1.5rem; letter-spacing: 3px; text-align: center;">
                            </div>
                            <button class="btn btn-warning px-4 py-2"><i class="fa-solid fa-user-shield"></i> Ativar Proteção</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    </div>
   <script>
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