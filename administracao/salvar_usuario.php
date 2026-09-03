<?php
// ATIVANDO EXIBIÇÃO DE ERROS PARA DEBUG
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../configuracoes/config.php';

$meu_id = $_SESSION['user_id'] ?? $_SESSION['tenant_id'] ?? null;
$meu_tenant_id = $_SESSION['tenant_id'] ?? $meu_id;

if (!$meu_id) {
    die("Sessão expirada. Faça login novamente.");
}

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    die("Acesso negado.");
}

$is_superadmin = isset($_SESSION['is_superadmin']) && $_SESSION['is_superadmin'] == 1;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['acao'] ?? '';

    // =========================================================================
    // 1. EXCLUSÃO DE USUÁRIO (Apenas se acao == 'excluir')
    // =========================================================================
    if ($acao === 'excluir') {
        $id_excluir = (int) ($_POST['id_usuario'] ?? 0);

        if ($id_excluir === (int)$meu_id) {
            $_SESSION['erro'] = "Você não pode excluir sua própria conta.";
            header("Location: usuarios");
            exit;
        }

        try {
            if (!$is_superadmin) {
                $check = $pdo->prepare("SELECT id FROM usuarios WHERE id = :id AND dono_id = :tenant_id");
                $check->execute([':id' => $id_excluir, ':tenant_id' => $meu_tenant_id]);
                if ($check->rowCount() === 0) {
                    $_SESSION['erro'] = "Você não tem permissão para excluir este usuário.";
                    header("Location: usuarios");
                    exit;
                }
            }

            $stmtDel = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmtDel->execute([$id_excluir]);

            // Se for chamada via AJAX/Fetch, pode retornar JSON, mas como é form normal:
            header("Location: usuarios?msg=sucesso_excluir");
            exit;
        } catch (PDOException $e) {
            die("Erro ao excluir: " . $e->getMessage());
        }
    }
    
    // =========================================================================
    // 2. RESET DE SENHA E PRIMEIRO ACESSO (Apenas se acao == 'resetar_senha')
    // =========================================================================
    elseif ($acao === 'resetar_senha') {
        // Limpa qualquer saída anterior (espaços, warnings) para garantir JSON puro
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');

        $id_usuario = (int) ($_POST['id_usuario'] ?? 0);

        try {
            if (!$is_superadmin) {
                $check = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id AND dono_id = :tenant_id");
                $check->execute([':id' => $id_usuario, ':tenant_id' => $meu_tenant_id]);
                $usuarioAlvo = $check->fetch(PDO::FETCH_ASSOC);
                if (!$usuarioAlvo) {
                    echo json_encode(['status' => 'error', 'message' => 'Permissão negada.']);
                    exit;
                }
            } else {
                $check = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
                $check->execute([$id_usuario]);
                $usuarioAlvo = $check->fetch(PDO::FETCH_ASSOC);
            }

            if (!$usuarioAlvo) {
                echo json_encode(['status' => 'error', 'message' => 'Usuário não encontrado.']);
                exit;
            }

            $senha_padrao = "Mudar@4028"; 
            $senha_hash = password_hash($senha_padrao, PASSWORD_DEFAULT);

            // Reseta senha, marca first_access = 1 e ativo_2fa = 2
            $stmtUpdate = $pdo->prepare("UPDATE usuarios SET senha = ?, first_access = 1, ativo_2fa = 2, token_2fa = NULL WHERE id = ?");
            $stmtUpdate->execute([$senha_hash, $id_usuario]);

            // Webhook do n8n
            $webhook_url = 'https://n8n-prod.jrtec.com.br/webhook/enviar-dados-usuario'; 
            $payload = json_encode([
                'acao' => 'reset_senha_usuario',
                'nome' => $usuarioAlvo['nome'],
                'email' => $usuarioAlvo['email'],
                'telefone' => $usuarioAlvo['telefone'],
                'senha_limpa' => $senha_padrao,
                'perfil' => $usuarioAlvo['perfil']
            ]);

            $ch = curl_init($webhook_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json','x-gestao-api-key: ' . getenv('API_TOKEN')]);
            curl_exec($ch);
            curl_close($ch);

            echo json_encode(['status' => 'success', 'message' => 'Senha resetada com sucesso! O usuário foi configurado para o primeiro acesso.']);
            exit;

        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Erro: ' . $e->getMessage()]);
            exit;
        }
        
    } else {
    
    // =========================================================================
    // 2. CADASTRO E EDIÇÃO (Via POST)
    // =========================================================================
    $id = $_POST['id'] ?? '';
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $is_admin = isset($_POST['is_admin']) ? (int) $_POST['is_admin'] : 0;
    $senha = $_POST['senha'] ?? '';
    $perfil = $_POST['perfil'] ?? 'usuario';
    $permissao = 'Sim';
    $ativo2fa = 2; // Mantém o WhatsApp 2FA (longe do Google Auth = 1)
    $first_access = 1; // Indica que ele precisa trocar a senha no primeiro acesso

    try {
        if (empty($id)) {
            // --- CRIANDO UM NOVO SUB-USUÁRIO ---
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO usuarios (nome, email, telefone, senha, is_admin, perfil, permissao, ativo_2fa, first_access, dono_id) 
                        VALUES (:nome, :email, :telefone, :senha, :is_admin, :perfil, :permissao, :ativo_2fa, :first_access, :dono_id)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':nome' => $nome,
                    ':email' => $email,
                    ':telefone' => $telefone,
                    ':senha' => $senha_hash,
                    ':is_admin' => $is_admin,
                    ':perfil' => $perfil,
                    ':permissao' => $permissao,
                    ':ativo_2fa' => $ativo2fa,
                    ':first_access' => $first_access,
                    ':dono_id' => $meu_tenant_id 
            ]);
            
            // --- DISPARO DE WEBHOOK PARA O N8N (ENVIO DE EMAIL) ---
            $webhook_url = 'https://n8n-prod.jrtec.com.br/webhook/enviar-dados-usuario'; 
            
            $payload = json_encode([
                'acao' => 'novo_usuario_email',
                'nome' => $nome,
                'email' => $email,
                'telefone' => $telefone,
                'senha_limpa' => $senha, 
                'perfil' => $perfil
            ]);

            $ch = curl_init($webhook_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'x-gestao-api-key: ' . getenv('API_TOKEN')
            ]);
            curl_exec($ch);
            curl_close($ch);
            
        } else {
            // --- EDITANDO UM USUÁRIO ---
            if (!$is_superadmin) {
                $check = $pdo->prepare("SELECT id FROM usuarios WHERE id = :id AND (id = :meu_id OR dono_id = :tenant_id)");
                $check->execute([':id' => $id, ':meu_id' => $meu_id, ':tenant_id' => $meu_tenant_id]);
                if ($check->rowCount() === 0) {
                    die("Você não tem permissão para editar este usuário.");
                }
            }

            if (!empty($senha)) {
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $sql = "UPDATE usuarios SET nome = :nome, email = :email, telefone = :telefone, senha = :senha, is_admin = :is_admin, perfil = :perfil WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':nome'=>$nome, ':email'=>$email, ':telefone'=>$telefone, ':senha'=>$senha_hash, ':is_admin'=>$is_admin, ':perfil'=>$perfil, ':id'=>$id]);
            } else {
                $sql = "UPDATE usuarios SET nome = :nome, email = :email, telefone = :telefone, is_admin = :is_admin, perfil = :perfil WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':nome'=>$nome, ':email'=>$email, ':telefone'=>$telefone, ':is_admin'=>$is_admin, ':perfil'=>$perfil, ':id'=>$id]);
            }
        }
        
        header("Location: usuarios?msg=sucesso_salvar");
        exit;

    } catch (PDOException $e) {
        // TRATAMENTO AMIGÁVEL PARA E-MAIL DUPLICADO (Código SQLSTATE 23000 / Erro 1062)
        if ($e->getCode() == '23000' || strpos($e->getMessage(), '1062 Duplicate entry') !== false) {
            $_SESSION['erro'] = "O e-mail informado já está cadastrado no sistema para outro usuário.";
        } else {
            $_SESSION['erro'] = "Erro no banco de dados: " . $e->getMessage();
        }
        
        // Redireciona de volta para a tela de usuários exibindo o erro na sessão
        header("Location: usuarios");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['erro'] = "Erro de Execução: " . $e->getMessage();
        header("Location: usuarios");
        exit;
    }
  }
}
?>