<?php
// Carrega o autoloader do Composer (para o DomPDF)
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

// Carrega o autoloader nativo do DomPDF (instalação manual)
require 'dompdf/autoload.inc.php';
require 'Pix.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 1. DADOS DA COBRANÇA (Você pode puxar isso do seu banco de dados)
$chave_pix = '11486875000161'; // Sua chave PIX real (Email, CPF, Telefone ou Aleatória)
$titular = 'LOJA MACONICA FRATERNIDADE CARPINENSE';
$cidade = 'Carpina'; // Sua cidade sem acentos
$valor_cobranca = 160.00;
$nome_cliente = 'Everaldo Junior';
$vencimento = date('d/m/Y', strtotime('+3 days'));
$descricao = 'Mensalidade  - Julho de 2026';

// 2. GERA O CÓDIGO PIX (COPIA E COLA)
$pix = new Pix($chave_pix, $titular, $cidade, $valor_cobranca);
$codigo_pix = $pix->getPayload();

// 3. GERA A IMAGEM DO QR CODE (Usando API do QuickChart)
// URL Encode é necessário para não quebrar a URL da imagem
$qr_code_url = "https://quickchart.io/qr?size=300&text=" . urlencode($codigo_pix);

// 4. MONTA O HTML DA FATURA (Template limpo e profissional para PDF)
$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; color: #333; margin: 0; padding: 20px; }
        .cabecalho { border-bottom: 2px solid #cfa34e; padding-bottom: 15px; margin-bottom: 30px; }
        .cabecalho h1 { color: #0f172a; margin: 0; font-size: 28px; }
        .cabecalho p { color: #666; margin: 5px 0 0 0; font-size: 14px; }
        .box-info { background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .box-info table { width: 100%; text-align: left; }
        .box-info th { color: #64748b; font-size: 12px; text-transform: uppercase; padding-bottom: 5px; }
        .box-info td { font-size: 16px; font-weight: bold; color: #0f172a; }
        .area-pix { text-align: center; border: 2px dashed #cbd5e1; padding: 30px; border-radius: 12px; }
        .area-pix h2 { color: #0f172a; margin-top: 0; }
        .qr-code { width: 200px; height: 200px; margin: 15px 0; }
        .copia-cola { background: #f1f5f9; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 12px; word-break: break-all; color: #475569; }
    </style>
</head>
<body>
    <div class="cabecalho">
        <h1>Gestão Financeira</h1>
        <p>Recibo / Fatura de Cobrança</p>
    </div>

    <div class="box-info">
        <table>
            <tr>
                <th>Cliente</th>
                <th>Vencimento</th>
                <th>Valor (R$)</th>
            </tr>
            <tr>
                <td>' . $nome_cliente . '</td>
                <td>' . $vencimento . '</td>
                <td style="color: #cfa34e; font-size: 20px;">R$ ' . number_format($valor_cobranca, 2, ',', '.') . '</td>
            </tr>
            <tr>
                <th colspan="3" style="padding-top: 20px;">Descrição da Cobrança</th>
            </tr>
            <tr>
                <td colspan="3">' . $descricao . '</td>
            </tr>
        </table>
    </div>

    <div class="area-pix">
        <h2>Pague via PIX</h2>
        <p style="color: #64748b; font-size: 14px;">Abra o app do seu banco e escaneie o código abaixo:</p>
        
        <img src="' . $qr_code_url . '" class="qr-code">
        
        <p style="color: #64748b; font-size: 14px; margin-top: 20px;">Ou utilize a opção PIX Copia e Cola:</p>
        <div class="copia-cola">
            ' . $codigo_pix . '
        </div>
    </div>
</body>
</html>
';

// 5. INICIA O DOMPDF E GERA O ARQUIVO
$options = new Options();
// Permite que o DomPDF faça o download da imagem do QR Code gerada pela API
$options->set('isRemoteEnabled', true); 

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// Define o tamanho e orientação (A4, retrato)
$dompdf->setPaper('A4', 'portrait');

// Renderiza o HTML como PDF
$dompdf->render();

// Envia o PDF para o navegador (Para baixar direto, mude Attachment para 1)
$dompdf->stream("Fatura_" . $nome_cliente . ".pdf", ["Attachment" => 0]);