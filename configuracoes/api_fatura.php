<?php
// =================================================================
// 1. PROTEÇÃO CONTRA CORRUPÇÃO (Inicia o buffer para reter lixo)
// =================================================================
ob_start(); 
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Impede que o PHP jogue erros na tela e quebre o JSON

// Permite que o n8n acesse esta API
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require 'dompdf/autoload.inc.php';
require 'Pix.php';

// Tenta ler como JSON puro
$input_bruto = file_get_contents("php://input");
$dados_json = json_decode($input_bruto);

// Descobre de onde vieram os dados
if ($dados_json && isset($dados_json->token)) {
    $dados = $dados_json; // Veio certinho como JSON
} elseif (!empty($_POST)) {
    $dados = (object) $_POST; // O servidor converteu para formulário
} else {
    $dados = new stdClass(); // Chegou tudo vazio
}

$meu_token_secreto = "dnpf|cY94r0#\iALN4oa"; 

// Verifica o Token
if (!isset($dados->token) || $dados->token !== $meu_token_secreto) {
    http_response_code(401);
    
    if (ob_get_length()) ob_clean(); // Limpa o lixo antes de enviar o JSON
    echo json_encode([
        "erro" => "Acesso negado. Token invalido.",
        "oque_chegou_no_json" => $input_bruto,
        "oque_chegou_no_post" => $_POST,
        "token_lido" => isset($dados->token) ? $dados->token : "Vazio"
    ]);
    exit;
}

// 2. VERIFICA SE OS DADOS CHEGARAM (Agora exige o usuario_id)
if (!empty($dados->nome_cliente) && !empty($dados->valor) && !empty($dados->usuario_id)) {
    
    $usuario_id = (int)$dados->usuario_id; // ID do dono do sistema/tenant

    require 'config.php';

    // Busca as configurações ESPECÍFICAS do usuário dono da fatura
    $stmt = $pdo->prepare("SELECT * FROM configuracoes_pix WHERE usuario_id = :tenant_id");
    $stmt->execute([':tenant_id' => $usuario_id]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config || empty($config['chave_pix'])) {
        http_response_code(400);
        
        if (ob_get_length()) ob_clean(); // Limpa o lixo antes de enviar o JSON
        echo json_encode(["erro" => "O usuario ID {$usuario_id} ainda não configurou o PIX no painel."]);
        exit;
    }

    // =================================================================
    // BUSCA OS DADOS DA LOJA (PARA O PAPEL TIMBRADO)
    // =================================================================
    $stmtLodge = $pdo->prepare("
        SELECT l.nome, l.url_logo, l.endereco
        FROM secretaria_lojas l
        JOIN usuarios u ON u.loja_id = l.id
        WHERE u.id = ? 
        LIMIT 1
    ");
    $stmtLodge->execute([$usuario_id]);
    $lodge = $stmtLodge->fetch(PDO::FETCH_ASSOC);

    $nome_loja = $lodge ? htmlspecialchars($lodge['nome']) : 'Loja Maçônica';
    $endereco_loja = $lodge ? htmlspecialchars($lodge['endereco']) : 'Endereço não informado';
    $logoHtml = '';
    if ($lodge && !empty($lodge['url_logo'])) {
        $logoHtml = '<img src="' . $lodge['url_logo'] . '" style="max-width: 120px; display: block; margin: 0 auto 15px auto;">';
    }
    // =================================================================

    // =================================================================
    // PREPARAÇÃO DOS DADOS DO PIX (HIGIENIZAÇÃO ANTES DE GERAR)
    // =================================================================
    
    // 1. A chave PIX não pode ter pontos ou traços se for CNPJ/CPF/Celular.
    $chave_pix = trim($config['chave_pix']);
    
    // Se não for um e-mail e não for uma chave aleatória (32 caracteres), removemos a formatação deixando só números
    if (strpos($chave_pix, '@') === false && strlen(preg_replace('/[^a-zA-Z0-9]/', '', $chave_pix)) !== 32) {
        $chave_pix = preg_replace('/[^0-9]/', '', $chave_pix); 
    }

    // 2. O Titular e Cidade não podem ter acentos, caracteres especiais, ou ultrapassar o limite EMVCo
    function limpaStringPix($str) {
        $str = preg_replace('/[áàãâä]/ui', 'a', $str);
        $str = preg_replace('/[éèêë]/ui', 'e', $str);
        $str = preg_replace('/[íìîï]/ui', 'i', $str);
        $str = preg_replace('/[óòõôö]/ui', 'o', $str);
        $str = preg_replace('/[úùûü]/ui', 'u', $str);
        $str = preg_replace('/[ç]/ui', 'c', $str);
        return preg_replace('/[^a-zA-Z0-9 ]/', '', trim($str)); // Remove tudo que não for letra, número ou espaço
    }
    
    $titular = substr(limpaStringPix($config['titular']), 0, 25);
    $cidade = substr(limpaStringPix($config['cidade']), 0, 15);

    // Dados recebidos do n8n
    $nome_cliente = $dados->nome_cliente;
    $valor_cobranca = (float)$dados->valor;
    $vencimento = !empty($dados->vencimento) ? $dados->vencimento : date('d/m/Y', strtotime('+5 days'));
    $descricao = !empty($dados->descricao) ? $dados->descricao : 'Mensalidade / Contribuicao';

    // =================================================================
    // 3. GERA O CÓDIGO PIX E O QR CODE
    // =================================================================
    $pix = new Pix($chave_pix, $titular, $cidade, $valor_cobranca);
    
    // ATENÇÃO MÁXIMA: NUNCA altere a string retornada pelo getPayload()!
    // Ela contém o cálculo matemático (CRC16). Se você modificá-la agora, o app do banco rejeita.
    $codigo_pix = $pix->getPayload();
    
    $qr_code_url = "https://quickchart.io/qr?size=300&text=" . urlencode($codigo_pix);
    
    // Hardcode da chave apenas para exibição visual no PDF (não afeta o QR Code)
    $chave_pix_exibicao = '11.486.875/0001-61';

    // 4. MONTA O HTML DA FATURA (Agora com layout timbrado)
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Helvetica, Arial, sans-serif; color: #333; margin: 0; padding: 20px; }
            .cabecalho { text-align: center; border-bottom: 2px solid #cfa34e; padding-bottom: 20px; margin-bottom: 30px; }
            .cabecalho h1 { color: #0f172a; margin: 0; font-size: 20px; text-transform: uppercase; }
            .cabecalho p { color: #666; margin: 5px 0 0 0; font-size: 12px; }
            .cabecalho .titular-info { font-style: italic; color: #64748b; font-size: 10px; margin-top: 10px; }
            .box-info { background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
            .box-info table { width: 100%; text-align: left; }
            .box-info th { color: #64748b; font-size: 12px; text-transform: uppercase; padding-bottom: 5px; }
            .box-info td { font-size: 12px; font-weight: bold; color: #0f172a; }
            .area-pix { text-align: center; border: 2px dashed #cbd5e1; padding: 30px; border-radius: 12px; }
            .area-pix h2 { color: #0f172a; margin-top: 0; }
            .qr-code { width: 200px; height: 200px; margin: 15px 0; }
            
            .copia-cola { 
                background: #f1f5f9; 
                padding: 15px; 
                border-radius: 8px; 
                font-family: monospace; 
                font-size: 7px; 
                color: #475569; 
                line-height: 1.5;
                display: block;
                width: 90%; 
                margin: 0 auto; 
                box-sizing: border-box;
                word-wrap: break-word;
                word-break: break-all;
                overflow-wrap: break-word;
            }
            .copia-cnpj { 
                background: #f1f5f9; 
                padding: 15px; 
                border-radius: 6px; 
                font-family: monospace; 
                font-size: 11px; 
                color: #475569; 
                line-height: 1.5;
                display: block;
                width: 90%; 
                margin: 0 auto; 
                box-sizing: border-box;
                word-wrap: break-word;
                word-break: break-all;
                overflow-wrap: break-word;
            }
        </style>
    </head>
    <body>
        <div class="cabecalho">
            ' . $logoHtml . '
            <h1>' . $nome_loja . '</h1>
            <p>' . $endereco_loja .'</p>
            <p>Fatura Oficial para Pagamento</p>
            <div class="titular-info">Recebedor: ' . $titular . '</div>
        </div>
        
        <div style="border: 1px solid #E2E8F0; border-radius: 8px; padding: 20px; background-color: #F8FAFC; margin-bottom: 20px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="font-family: sans-serif; font-size: 14px;">
        <!-- PRIMEIRA LINHA: Membro, Vencimento e Valor -->
        <tr>
            <td width="40%" align="left" style="padding-bottom: 15px;">
                <span style="color: #64748B; font-size: 10px; font-weight: bold; text-transform: uppercase;">Membro</span><br>
                <strong style="font-size: 12px; color: #0F172A;">' . $dados->nome_cliente . '</strong>
            </td>
            
            <td width="30%" align="center" style="padding-bottom: 15px;">
                <span style="color: #64748B; font-size: 10px; font-weight: bold; text-transform: uppercase;">Vencimento</span><br>
                <strong style="font-size: 12px; color: #0F172A;">' . date("d/m/Y", strtotime($vencimento)) . '</strong>
            </td>
            
            <td width="30%" align="right" style="padding-bottom: 15px;">
                <span style="color: #64748B; font-size: 10px; font-weight: bold; text-transform: uppercase;">Valor</span><br>
                <strong style="font-size: 15px; color: #DDA15E;">R$ ' . number_format($dados->valor, 2, ',', '.') . '</strong>
            </td>
        </tr>
        
        <!-- SEGUNDA LINHA: Descrição da Cobrança -->
        <tr>
            <td colspan="3" align="left" style="padding-top: 15px; border-top: 1px solid #E2E8F0;">
                <span style="color: #64748B; font-size: 10px; font-weight: bold; text-transform: uppercase;">Descrição da Cobrança</span><br>
                <strong style="font-size: 12px; color: #0F172A;">' . $dados->descricao . '</strong>
            </td>
        </tr>
    </table>
    </div>
        <div class="area-pix">
            <h2>Pague via PIX</h2>
            <p style="color: #64748b; font-size: 12px;">Abra o app do seu banco e escaneie o código abaixo:</p>
            <img src="' . $qr_code_url . '" class="qr-code">
            <p style="color: #64748b; font-size: 12px; margin-top: 20px;">Ou utilize a opção PIX Copia e Cola:</p>
            <div class="copia-cola">' . $codigo_pix . '</div>
            <p style="color: #64748b; font-size: 12px; margin-top: 20px;">Caso não funcione as opções acima, utilize a chave PIX CNPJ:</p>
            <div class="copia-cnpj">' . $chave_pix_exibicao . '</div>
        </div>
    </body>
    </html>';

    // 5. GERA O PDF EM MEMÓRIA
    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true); 
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Pega o conteúdo gerado do PDF
    $saida_pdf = $dompdf->output();

    // Converte o PDF diretamente para uma string Base64
    $pdf_base64 = base64_encode($saida_pdf);

    // 6. DEVOLVE A RESPOSTA PARA O n8n (EM JSON)
    http_response_code(200);
    
    if (ob_get_length()) ob_clean(); // A MÁGICA: Limpa o lixo antes de enviar o JSON final
    echo json_encode([
        "sucesso" => true,
        "pdf_base64" => $pdf_base64,
        "pix_copia_cola" => $codigo_pix, // Retorna a versão limpa (sem quebras)
        "nome_arquivo" => 'Fatura_' . preg_replace('/[^A-Za-z0-9]/', '', $nome_cliente) . '.pdf'
    ]);
    exit;

} else {
    http_response_code(400);
    if (ob_get_length()) ob_clean(); // Limpa o lixo antes de enviar o JSON
    echo json_encode(["erro" => "Faltam dados obrigatórios. Envie nome_cliente, valor e usuario_id."]);
    exit;
}
?>
