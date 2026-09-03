<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Requisita as configurações e o helper de webhook
require_once '../configuracoes/config.php';
require_once '../configuracoes/webhook_helper.php';

header('Content-Type: application/json');

// Garante que só quem está logado acesse
if (!isset($_SESSION['tenant_id'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$dados = json_decode(file_get_contents('php://input'), true);

if (!$dados) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum dado recebido.']);
    exit;
}

// O Path do Webhook (ajuste conforme o seu n8n)
$webhook_path = 'chancelaria-mensagens';

$publico = $dados['publico_alvo'] ?? '';
$sessao_id = (int)($dados['sessao_id'] ?? 0);
$mensagem_base = trim($dados['mensagem_texto'] ?? '');

if (empty($publico) || empty($mensagem_base)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Preencha os campos obrigatórios.']);
    exit;
}

try {
    $payload_n8n = [];

    // =========================================================
    // LÓGICA 1: ENVIO PARA O GRUPO
    // =========================================================
    if ($publico === 'grupo') {
        
        // Busca o ID do grupo na tabela de usuários vinculada ao tenant_id
        $stmtGrupo = $pdo->prepare("SELECT whatsapp_grupo_id FROM usuarios WHERE id = ?");
        $stmtGrupo->execute([$tenant_id]);
        $usuario = $stmtGrupo->fetch(PDO::FETCH_ASSOC);

        $id_grupo = $usuario['whatsapp_grupo_id'] ?? '';
        
        if (empty($id_grupo)) {
            throw new Exception("O ID do Grupo do WhatsApp não está configurado. Por favor, atualize as configurações da sua conta.");
        }

        // Remove a tag {nome} para envios em grupo (substitui por "Irmãos" caso tenha sido usada na interface)
        $msg_limpa = str_replace('{nome}', 'Irmãos', $mensagem_base);

        $payload_n8n[] = [
            'tipo' => 'grupo',
            'destino' => $id_grupo,
            'mensagem' => $msg_limpa
        ];

    // =========================================================
    // LÓGICA 2: ENVIO INDIVIDUAL (Massa)
    // =========================================================
    } else {
        $destinatarios = [];

        if ($publico === 'ativos') {
            $stmt = $pdo->prepare("SELECT nome, telefone FROM chancelaria_membros WHERE tenant_id = ? AND status = 'Ativo' AND telefone IS NOT NULL AND telefone != ''");
            $stmt->execute([$tenant_id]);
            $destinatarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($publico === 'faltosos') {
            if ($sessao_id <= 0) throw new Exception("Sessão não informada.");
            $stmt = $pdo->prepare("
                SELECT m.nome, m.telefone 
                FROM chancelaria_membros m
                INNER JOIN chancelaria_presencas p ON m.id = p.membro_id
                WHERE m.tenant_id = ? AND p.sessao_id = ? AND p.status IN ('F', 'J') AND m.telefone IS NOT NULL AND m.telefone != ''
            ");
            $stmt->execute([$tenant_id, $sessao_id]);
            $destinatarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($publico === 'visitantes') {
            if ($sessao_id <= 0) throw new Exception("Sessão não informada.");
            $stmt = $pdo->prepare("SELECT nome, telefone FROM chancelaria_visitantes WHERE tenant_id = ? AND sessao_id = ? AND telefone IS NOT NULL AND telefone != ''");
            $stmt->execute([$tenant_id, $sessao_id]);
            $destinatarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if (count($destinatarios) === 0) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum contato com telefone encontrado para este público.']);
            exit;
        }

        // Monta o Payload iterando os destinatários
        foreach ($destinatarios as $dest) {
            $numero_limpo = preg_replace('/[^0-9]/', '', $dest['telefone']);
            
            if (strlen($numero_limpo) == 10 || strlen($numero_limpo) == 11) {
                $numero_limpo = "55" . $numero_limpo;
            }

            $primeiro_nome = explode(' ', trim($dest['nome']))[0];
            $msg_personalizada = str_replace('{nome}', $primeiro_nome, $mensagem_base);

            $payload_n8n[] = [
                'tipo' => 'individual',
                'nome' => $dest['nome'],
                'destino' => $numero_limpo,
                'mensagem' => $msg_personalizada
            ];
        }
    }

    // =========================================================
    // DISPARO VIA HELPER
    // =========================================================
    
    // Dispara utilizando a função do seu webhook_helper.php
    $resposta = enviar_para_webhook($webhook_path, ['disparos' => $payload_n8n]);

    if ($resposta && isset($resposta['sucesso']) && $resposta['sucesso'] === true) {
        $qtd = count($payload_n8n);
        $texto = $publico === 'grupo' ? 'Mensagem enviada para o Grupo com sucesso!' : "{$qtd} mensagens processadas para envio!";
        echo json_encode(['sucesso' => true, 'mensagem' => $texto]);
    } else {
        echo json_encode(['sucesso' => false, 'mensagem' => 'O Helper do n8n retornou um erro ao processar a requisição.']);
    }

} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
}
?>