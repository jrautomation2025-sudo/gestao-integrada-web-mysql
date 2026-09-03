<?php
session_start();
require 'config.php';
require_once 'jwt.php'; // Certifique-se que o jwt.php está na mesma pasta
require_once 'webhook_helper.php'; // Nossa função dispararWebhookN8n()

// Cabeçalhos para aceitar JSON e CORS (Permitir App acessar)
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Se for apenas uma verificação OPTIONS (CORS), para por aqui
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Captura dados: aceita tanto JSON (App) quanto POST normal (Formulário Web)
$jsonInput = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? $jsonInput['action'] ?? $_POST['action'] ?? '';

// Helper para pegar dados de qualquer fonte
function getParam($key, $json, $post) {
    return $json[$key] ?? $post[$key] ?? null;
}

// ==========================================================
// 1. LOGIN API (PARA O FLUTTERFLOW / N8N) -> Retorna JWT
// ==========================================================
if ($action === 'login_api') {
    $email = getParam('email', $jsonInput, $_POST);
    $senha = getParam('senha', $jsonInput, $_POST);

    if (!$email || !$senha) {
        echo json_encode(['status' => 'error', 'message' => 'Preencha email e senha']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, nome, senha, plano, is_admin FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($senha, $user['senha'])) {
        // A. Gerar Access Token (15 Minutos)
        $issuedAt = time();
        $expirationTime = $issuedAt + (15 * 60); // 15 min
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'uid' => $user['id'],
            'role' => $user['is_admin'] ? 'admin' : 'user'
        ];
        $accessToken = JWT::encode($payload);

        // B. Gerar Refresh Token (7 Dias)
        $refreshToken = bin2hex(random_bytes(32)); 
        $refreshExp = date('Y-m-d H:i:s', strtotime('+7 days'));

        // Salvar Refresh Token no banco
        $pdo->prepare("UPDATE usuarios SET refresh_token = ?, refresh_token_exp = ? WHERE id = ?")
            ->execute([$refreshToken, $refreshExp, $user['id']]);

        echo json_encode([
            'status' => 'success',
            'token' => $accessToken,        
            'refresh_token' => $refreshToken, 
            'expires_in' => 900,
            'user' => [
                'id' => $user['id'], 
                'nome' => $user['nome'], 
                'plano' => $user['plano']
            ]
        ]);
    } else {
        http_response_code(401); 
        echo json_encode(['status' => 'error', 'message' => 'Email ou senha incorretos']);
    }
    exit;
}

// ==========================================================
// 2. LOGIN WEB (PARA O SITE) -> Cria Sessão PHP
// ==========================================================
if ($action === 'login') {
    require './utils/GoogleAuthenticator.php'; // Inclua a classe

    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'];
    $perfil = $_POST['perfil'];

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user['permissao'] === 'Inativo') {
        echo json_encode(['status' => 'error', 'message' => 'Acesso restrito. Verifique no seu email se seu acesso foi liberado, senão contate o administrador do sistema.']);
        exit;
    }
    
    if ($user['perfil'] != 'admin' && $user['perfil'] != $perfil) {
        echo json_encode(['status' => 'error', 'message' => 'Acesso restrito. Você não tem permissão para acessar esse modulo, se você acredita que não é um erro então contate o administrador do sistema.']);
        exit;
    }
    
    if ($user && password_verify($senha, $user['senha'])) {
        
        
        // ---------------------------------------------------------------------
    // NOVO BLOCO: 2FA VIA WHATSAPP (Apenas para quem tem ativo_2fa = 2)
    // ---------------------------------------------------------------------
    if ($user['ativo_2fa'] == 2 ) { // Garante que não é admin
        $token_2fa = sprintf("%06d", mt_rand(1, 999999));
        
        $stmtToken = $pdo->prepare("UPDATE usuarios SET token_2fa = ? WHERE id = ?");
        $stmtToken->execute([$token_2fa, $user['id']]);
        
        $telefone_limpo = preg_replace('/[^0-9]/', '', $user['telefone']);
        if (strlen($telefone_limpo) == 10 || strlen($telefone_limpo) == 11) $telefone_limpo = '55' . $telefone_limpo;

        // Dispara Webhook
        $webhook_url = 'https://n8n-prod.jrtec.com.br/webhook/token-2fa'; 
        $payload = json_encode(['acao' => 'enviar_token_2fa', 'nome' => $user['nome'], 'telefone_whatsapp' => $telefone_limpo, 'token_2fa' => $token_2fa]);
        
        $ch = curl_init($webhook_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'x-gestao-api-key: ' . getenv('API_TOKEN')]);
        curl_exec($ch);
        curl_close($ch);

        $_SESSION['temp_user_id'] = $user['id'];
        
        // RETORNA UM STATUS NOVO PARA NÃO CONFLITAR COM O GOOGLE AUTH
        echo json_encode(['status' => '2fa_whatsapp', 'message' => 'Código enviado para o WhatsApp.']);
        exit;
    }
        
        // --- NOVO: VERIFICAÇÃO 2FA ---
        if ($user['ativo_2fa'] == 1) {
            // Se tiver ativado, NÃO loga ainda. Retorna aviso.
            // Salvamos o ID em uma sessão temporária para validar no próximo passo
            $_SESSION['temp_2fa_user_id'] = $user['id'];
            echo json_encode(['status' => '2fa_required', 'message' => 'Código 2FA necessário']);
            exit;
        }

        // Login normal (sem 2FA)
        $tenant_id = !empty($user['dono_id']) ? $user['dono_id'] : $user['id'];
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['tenant_id'] = $tenant_id;
        $_SESSION['is_admin'] = $user['is_admin'];
        
        $_SESSION['user'] = [
            'nome' => $user['nome'],
            'email' => $user['email'],
            'id' => $user['id']
        ];
        
         // --- NOVO: Define se deve mostrar o alerta de 2FA ---
        // Mostra SE: (2FA desligado) E (Usuário NÃO pediu para ignorar)
        if ($user['ativo_2fa'] == 0 && $user['lembrete_2fa_ignorado'] == 0) {
            $_SESSION['show_2fa_alert'] = true;
        }
        
        echo json_encode(['status' => 'success']);
        
        $_SESSION['is_superadmin'] = $user['is_superadmin'];
        
        // Pegar o IP do usuário
        // Pega o IP e gera a data/hora exata usando o fuso do PHP
        $ip = pegar_ip_usuario();
        $data_agora = date('Y-m-d H:i:s'); 

        // Adicionamos a coluna data_acesso no INSERT para sobrescrever o padrão do banco
        $stmt_log = $pdo->prepare("INSERT INTO logs_acesso (tenant_id, usuario_id, modulo, ip, data_acesso) VALUES (:tenant, :user, :modulo, :ip, :data)");
        $stmt_log->execute([
            ':tenant' => $_SESSION['tenant_id'],
            ':user'   => $_SESSION['user_id'],
            ':modulo'     => $perfil,
            ':ip'     => $ip,
            ':data'   => $data_agora // Enviamos a data do PHP
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'E-mail ou senha incorretos']);
    }
    exit;
}

// --- NOVO ACTION: VALIDAR O CÓDIGO 2FA ---
if ($action === 'verify_2fa') {
    require './utils/GoogleAuthenticator.php';
    
    $codigo = $_POST['codigo'];
    $tempUserId = $_SESSION['temp_2fa_user_id'] ?? null;

    if (!$tempUserId) {
        echo json_encode(['status' => 'error', 'message' => 'Sessão expirou. Faça login novamente.']); exit;
    }

    // Busca o usuário completo
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$tempUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $ga = new GoogleAuthenticator();
    if ($ga->verifyCode($user['secret_2fa'], $codigo)) {
        // CÓDIGO CORRETO! Finaliza o login
        $tenant_id = !empty($user['dono_id']) ? $user['dono_id'] : $user['id'];
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['tenant_id'] = $tenant_id;
        $_SESSION['is_admin'] = $user['is_admin'];
        
        $_SESSION['user'] = [
            'nome' => $user['nome'],
            'email' => $user['email'],
            'id' => $user['id']
        ];
        
        unset($_SESSION['temp_2fa_user_id']); // Limpa temp
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Código inválido']);
    }
    exit;
}

// ==========================================================
// 3. REFRESH TOKEN (PARA O FLUTTERFLOW RENOVAR)
// ==========================================================
if ($action === 'refresh_token') {
    $refreshToken = getParam('refresh_token', $jsonInput, $_POST);

    if (!$refreshToken) {
        http_response_code(400); 
        echo json_encode(['status' => 'error', 'message' => 'Refresh token ausente']); 
        exit;
    }

    // Busca usuário pelo refresh token VÁLIDO
    $stmt = $pdo->prepare("SELECT id, is_admin FROM usuarios WHERE refresh_token = ? AND refresh_token_exp > NOW()");
    $stmt->execute([$refreshToken]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Gera NOVO Access Token
        $issuedAt = time();
        $expirationTime = $issuedAt + (15 * 60); 
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'uid' => $user['id'],
            'role' => $user['is_admin'] ? 'admin' : 'user'
        ];
        $newAccessToken = JWT::encode($payload);

        echo json_encode([
            'status' => 'success',
            'token' => $newAccessToken,
            'expires_in' => 900
        ]);
    } else {
        http_response_code(401); 
        echo json_encode(['status' => 'error', 'message' => 'Sessão expirada. Faça login novamente.']);
    }
    exit;
}

// ==========================================================
// 4. CADASTRO (REGISTER) - FUNCIONA PRA WEB E APP
// ==========================================================
if ($action === 'register') {
    $nome = $_POST['nome'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $telefone = $_POST['telefone'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $plano = $_POST['plano_selecionado'] ?? 'free';

    try {
    // Validação Básica
    if (!$email || !$senha || !$nome || !$telefone) {
        echo json_encode(['status' => 'error', 'message' => 'Preencha todos os campos obrigatórios']);
        exit;
    }

    // Verifica se email já existe
    $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmtCheck->execute([$email]);
    if ($stmtCheck->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Email já cadastrado']);
        exit;
    }

    // Lógica de Expiração do Plano
    $validade = null;
    $hoje = new DateTime();
    
    if ($plano == 'pro_mensal') {
        $hoje->modify('+30 days');
        $validade = $hoje->format('Y-m-d');
    } elseif ($plano == 'pro_anual') {
        $hoje->modify('+365 days');
        $validade = $hoje->format('Y-m-d');
    } elseif ($plano == 'free') {
        $hoje->modify('+182 days');
        $validade = $hoje->format('Y-m-d');
    }

    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
    
    // Insere no Banco
    // Nota: creditos_ia começa com 10 de cortesia
    // Como é um cadastro novo (Dono da conta), dono_id é NULL automaticamente
    $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, telefone, senha, plano, plano_expiracao, creditos_ia, is_admin) VALUES (?, ?, ?, ?, ?, ?, 10, 1)");
    $stmt->execute([$nome, $email, $telefone, $senhaHash, $plano, $validade]);

        // =====================================================================
        // DISPARO DO WEBHOOK COM JWT (SOMENTE APÓS SALVAR NO BANCO COM SUCESSO)
        // =====================================================================
        
        // 1. Defina para qual webhook enviar (se a base for a mesma do painel)
        // Você pode montar assim: N8N_BASE_URL . 'controle-acessos'
        // Ou fixar a URL se for diferente:
        $urlWebhookN8N = N8N_BASE_URL . 'gestao-integrada-acessos';
        
        // 2. Prepara os dados limpos para enviar
        $dadosParaN8n = [
            'email'    => $email,
            'nome'     => $nome,
            'telefone' => $telefone,
            'plano' => $plano,
            'validade' => $validade
        ];

        // 3. Dispara a função usando a senha secreta JWT central
        // A função vai encriptar e comunicar silenciosamente nos bastidores
        dispararWebhookN8n($urlWebhookN8N, $dadosParaN8n, N8N_JWT_SECRET);
        
        // =====================================================================

        // Retorna sucesso para liberar a tela de login
        echo json_encode(['status' => 'success', 'message' => 'Conta criada com sucesso!']);
        exit;

    } catch (Exception $e) {
        // Tratamento de e-mails duplicados, etc.
        echo json_encode(['status' => 'error', 'message' => 'Erro ao processar cadastro: ' . $e->getMessage()]);
        exit;
    }
}

// =========================================================================
// 1. VERIFICAÇÃO DO WHATSAPP
// =========================================================================
if ($action === 'verify_2fa_whatsapp') {
    $codigo_digitado = trim($_POST['codigo'] ?? '');
    $user_id = $_SESSION['temp_user_id'] ?? null;

    if (!$user_id) {
        echo json_encode(['status' => 'error', 'message' => 'Sessão expirada.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['token_2fa'] === $codigo_digitado) {
        // Limpa o token do WhatsApp
        $stmtToken = $pdo->prepare("UPDATE usuarios SET token_2fa = NULL WHERE id = ?");
        $stmtToken->execute([$user_id]);

        // Verifica se é o primeiro acesso usando o novo campo 'first_access'
        if ($user['first_access'] == 1) {
            $_SESSION['aguardando_troca_senha'] = true;
            // Retorna um status específico para o JS abrir o modal de senha
            echo json_encode(['status' => 'need_password_change']);
            exit;
        } else {
            // Se não for primeiro acesso, faz o login normal (ativo_2fa continua sendo 2)
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['tenant_id'] = $user['dono_id']; 
            $_SESSION['is_admin'] = $user['is_admin'];
            unset($_SESSION['temp_user_id']);

            echo json_encode(['status' => 'success']);
            exit;
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Código incorreto.']);
        exit;
    }
}

// =========================================================================
// 2. ATUALIZAÇÃO DA SENHA NO PRIMEIRO ACESSO
// =========================================================================
// =========================================================================
// ATUALIZAÇÃO DA SENHA NO PRIMEIRO ACESSO (COM TRATAMENTO DE ERRO JSON)
// =========================================================================
if ($action === 'update_first_password') {
    // Garante que o header seja sempre JSON, mesmo se houver erro fatal
    header('Content-Type: application/json');

    try {
        $nova_senha = $_POST['nova_senha'] ?? '';
        $user_id = $_SESSION['temp_user_id'] ?? null;

        if (!$user_id || empty($_SESSION['aguardando_troca_senha'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sessão inválida ou expirada. Faça login novamente.']);
            exit;
        }

        // --- VALIDAÇÃO DE SEGURANÇA DA SENHA ---
        $erros = [];
        if (strlen($nova_senha) < 8) { $erros[] = "mínimo de 8 caracteres"; }
        if (!preg_match('/[A-Z]/', $nova_senha)) { $erros[] = "pelo menos 1 letra maiúscula"; }
        if (!preg_match('/[a-z]/', $nova_senha)) { $erros[] = "pelo menos 1 letra minúscula"; }
        if (!preg_match('/[0-9]/', $nova_senha)) { $erros[] = "pelo menos 1 número"; }
        if (!preg_match('/[^a-zA-Z0-9]/', $nova_senha)) { $erros[] = "pelo menos 1 caractere especial"; }

        if (!empty($erros)) {
            echo json_encode([
                'status' => 'error', 
                'message' => 'A senha deve conter: ' . implode(', ', $erros) . '.'
            ]);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['status' => 'error', 'message' => 'Usuário não encontrado.']);
            exit;
        }
        
        $hash_salvo = $user['senha'];
        
        if (password_verify($nova_senha, $hash_salvo)) {
            echo json_encode(['status' => 'error', 'message' => 'A nova senha não pode ser igual a senha temporária.']);
            exit;
        }

        // Atualiza a senha e zera o first_access. O ativo_2fa permanece 2.
        $stmtUpdate = $pdo->prepare("UPDATE usuarios SET senha = ?, first_access = 0 WHERE id = ?");
        $stmtUpdate->execute([password_hash($nova_senha, PASSWORD_DEFAULT), $user_id]);

        // Efetiva a sessão de login definitiva
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['tenant_id'] = $user['dono_id']; 
        $_SESSION['is_admin'] = $user['is_admin'];

        unset($_SESSION['temp_user_id'], $_SESSION['aguardando_troca_senha']);

        echo json_encode(['status' => 'success']);
        exit;

    } catch (Exception $e) {
        // Retorna o erro formatado em JSON para o SweetAlert exibir bonitinho, sem dar Erro 500
        echo json_encode(['status' => 'error', 'message' => 'Erro no servidor: ' . $e->getMessage()]);
        exit;
    }
}

// ==========================================================
// 5. LOGOUT (SAIR)
// ==========================================================
if ($action === 'logout') {
    $uid = $_SESSION['user_id'] ?? getParam('user_id', $jsonInput, $_POST);

    if ($uid) {
        // SEGURANÇA: Invalida o refresh token no banco
        $stmt = $pdo->prepare("UPDATE usuarios SET refresh_token = NULL, refresh_token_exp = NULL WHERE id = ?");
        $stmt->execute([$uid]);
    }
    
        // Pega o IP e gera a data/hora exata usando o fuso do PHP
        $ip = pegar_ip_usuario();
        $data_agora = date('Y-m-d H:i:s');

        // Adicionamos a coluna data_acesso no INSERT para sobrescrever o padrão do banco
        $stmt_log = $pdo->prepare("INSERT INTO logs_acesso (tenant_id, usuario_id, ip, acao, data_acesso) VALUES (:tenant, :user, :ip, :acao, :data)");
        $stmt_log->execute([
            ':tenant' => $_SESSION['tenant_id'],
            ':user'   => $_SESSION['user_id'],
            ':ip'     => $ip,
            ':acao'   => $action,
            ':data'   => $data_agora // Enviamos a data do PHP
        ]);

    // Destrói sessão Web
    session_destroy();
    
    echo json_encode(['status' => 'success', 'message' => 'Deslogado com sucesso']);
    exit;
}

// Se nenhuma action bateu
if (!$action) {
    echo json_encode(['status' => 'error', 'message' => 'Nenhuma ação definida']);
}

// --- AÇÃO: IGNORAR LEMBRETE 2FA ---
if ($action === 'ignore_2fa') {
    if (!isset($_SESSION['user_id'])) exit;
    
    $userId = $_SESSION['user_id'];
    
    // Atualiza no banco para não mostrar mais
    $pdo->prepare("UPDATE usuarios SET lembrete_2fa_ignorado = 1 WHERE id = ?")->execute([$userId]);
    
    // Limpa a variável da sessão também
    unset($_SESSION['show_2fa_alert']);
    
    echo json_encode(['status' => 'success']);
    exit;
}

// --- AÇÃO: SOLICITAR UPGRADE DE PLANO ---
if ($action === 'upgrade_plan') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Usuário não logado']);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $novoPlano = $_POST['plano']; // pro_mensal, pro_anual, vitalicio

    try {
        // 1. Busca dados do usuário (Nome e Telefone)
        $stmt = $pdo->prepare("SELECT nome, telefone, email FROM usuarios WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode(['status' => 'error', 'message' => 'Usuário não encontrado']);
            exit;
        }

        // 2. Prepara dados para o N8N
        $payload = [
            'nome' => $user['nome'],
            'telefone' => $user['telefone'], // Importante: formato 5511999999999
            'email' => $user['email'],
            'plano_desejado' => $novoPlano,
            'origem' => 'dashboard_upgrade_button'
        ];

        // 3. Envia para o Webhook (CURL)
        $ch = curl_init(N8N_WEBHOOK_SALES);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 4. Retorno
        if ($httpCode >= 200 && $httpCode < 300) {
            echo json_encode(['status' => 'success', 'message' => 'Solicitação enviada! Verifique seu WhatsApp/E-mail.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Falha ao conectar com servidor de vendas.']);
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro interno: ' . $e->getMessage()]);
    }
    exit;
}
// pegar ip do usuario logado
function pegar_ip_usuario() {
    $ip = '';
    
    // Verifica se o tráfego passa por Cloudflare
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } 
    // Verifica cabeçalhos de proxy reverso e balanceadores de carga
    elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // HTTP_X_FORWARDED_FOR pode retornar uma lista de IPs separados por vírgula. Pega o primeiro.
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ipList[0]);
    } 
    elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } 
    // Fallback para a conexão direta padrão
    else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    return trim($ip);
}
?>
