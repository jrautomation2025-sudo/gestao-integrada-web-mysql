<?php
session_start();
require '../configuracoes/config.php'; // Verifique se o nome do seu arquivo de conexão é este

// 1. Bloqueia se não estiver logado ou se não for administrador
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: usuarios?erro=acesso_negado");
    exit;
}

// 2. Verifica se a requisição é POST e se o ID foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id_usuario'])) {
    
    $id_usuario = (int) $_POST['id_usuario'];
    $tenant_id = $_SESSION['tenant_id'];

    // 3. Executa o DELETE com a trava de segurança do Tenant (dono_id)
    try {
        // A cláusula dono_id = :tenant é crucial no seu SaaS para isolar os dados
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = :id AND dono_id = :tenant");
        $stmt->execute([
            ':id' => $id_usuario,
            ':tenant' => $tenant_id
        ]);

        // Redireciona de volta com sucesso
        header("Location: usuarios?msg=sucesso_excluir");
        exit;

    } catch (PDOException $e) {
        // Trata erros (ex: se o usuário estiver vinculado a outros registros e houver restrição de chave estrangeira)
        header("Location: usuarios?erro=falha_banco");
        exit;
    }
} else {
    // Redireciona se tentarem acessar a página diretamente via URL (GET)
    header("Location: usuarios");
    exit;
}
?>