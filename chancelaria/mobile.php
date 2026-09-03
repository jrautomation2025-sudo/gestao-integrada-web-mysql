<?php
session_start();
require_once '../configuracoes/config.php';

$erro = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    // Ajuste o nome da tabela de usuários/chanceleres conforme a sua base se necessário (ex: usuarios ou chancelaria_usuarios)
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($senha, $usuario['senha'])) {
        $_SESSION['chanceler_logado'] = $usuario['id'];
        $_SESSION['tenant_id'] = $usuario['id'];
        header('Location: sessoes_mobile.php');
        exit;
    } else {
        $erro = "E-mail ou senha incorretos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chancelaria Mobile - Gestão Integrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" href="./configuracoes/icone.svg" type="image/svg+xml">
    <style>
        body { background-color: #141724; color: #e2e8f0; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: height: 100vh; margin: 0; }
        .card-custom { background-color: #1d2132; border: 1px solid #333951; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); width: 100%; max-width: 400px; }
        .btn-gold { background-color: #f5c041; color: #141724; font-weight: 600; }
        .btn-gold:hover { background-color: #dca732; color: #141724; }
        .form-control { background-color: #141724; border: 1px solid #333951; color: #fff; }
        .form-control:focus { background-color: #141724; border-color: #f5c041; color: #fff; box-shadow: none; }
        /* Para navegadores modernos (Chrome, Firefox, Edge, Safari) */
        input::placeholder {
        color: #a0aec0 !important; /* Um cinza claro bem visível */
        opacity: 0.5 !important;     /* Remove a transparência padrão do navegador */
        }

        /* Para garantir compatibilidade com versões antigas do Firefox */
        input:-moz-placeholder {
        color: #a0aec0 !important;
        opacity: 1 !important;
        }

        /* Para garantir compatibilidade com versões antigas do Internet Explorer/Edge */
        input::-ms-input-placeholder {
        color: #a0aec0 !important;
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center vh-100">

<div class="card-custom">
    <div class="text-center mb-4">
        <i class="fas fa-gavel text-warning mb-2" style="font-size: 2.5rem;"></i>
        <h3 style="font-family: 'Cinzel', serif; color: #f5c041;">Chancelaria</h3>
        <p class="text-white small">Acesso Mobile</p>
    </div>

    <?php if (!empty($erro)): ?>
        <div class="alert alert-danger text-center py-2 small" role="alert"><?= $erro ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label text-white small">E-mail</label>
            <input type="email" name="email" class="form-control" placeholder="chanceler@lodge.com" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label text-white small">Senha</label>
            <input type="password" name="senha" class="form-control" placeholder="********" required>
        </div>
        <button type="submit" class="btn btn-gold w-100 py-2">Entrar</button>
    </form>
</div>

</body>
</html>