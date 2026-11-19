<?php
//ini_set('display_errors', 1);
//error_reporting(E_ALL);
require_once '../configuracoes/config.php';
require_once '../vendor/tecnickcom/tcpdf/tcpdf.php'; // Ajuste o caminho do TCPDF se necessário

$sessao_id = (int)($_GET['sessao_id'] ?? 0);
if ($sessao_id <= 0) {
    die("Sessão inválida.");
}

// 1. Validação do filtro (whitelist)
$filtroPermitidos = ['todos', 'membros', 'visitantes'];
$filtro = strtolower(trim($_GET['filtro'] ?? 'todos'));
if (!in_array($filtro, $filtroPermitidos, true)) {
    $filtro = 'todos';
}

$rotuloFiltro = match ($filtro) {
    'membros'    => 'OBREIROS DO QUADRO (MEMBROS)',
    'visitantes' => 'IRMÃOS VISITANTES',
    default      => 'LISTA COMPLETA (MEMBROS E VISITANTES)'
};

$sufixoArquivo = match ($filtro) {
    'membros'    => 'membros',
    'visitantes' => 'visitantes',
    default      => 'completa'
};

// 2. Busca dados da sessão
$stmtSessao = $pdo_read->prepare("
    SELECT *, COALESCE(data, data_sessao) AS data_ref
    FROM chancelaria_sessoes
    WHERE id = ?
    LIMIT 1
");
$stmtSessao->execute([$sessao_id]);
$sessao = $stmtSessao->fetch(PDO::FETCH_ASSOC);

if (!$sessao) {
    die("Sessão não encontrada.");
}

$tenant_id = (int)$sessao['tenant_id'];

// 3. Busca presenças filtradas
$whereFiltro = "";
if ($filtro === 'membros') {
    $whereFiltro = " AND p.membro_id IS NOT NULL ";
} elseif ($filtro === 'visitantes') {
    $whereFiltro = " AND p.membro_id IS NULL ";
}

$sqlPresencas = "
    SELECT
        p.id,
        p.membro_id,
        p.visitante_id,
        p.snapshot_nome,
        p.snapshot_cim,
        p.status_presenca,
        p.codigo_assinatura,
        p.hash_assinatura,
        p.assinado_em_utc,
        p.ip_origem,
        m.nome AS membro_nome,
        m.cim AS membro_cim,
        m.grau AS membro_grau,
        v.nome AS vis_nome,
        v.cim AS vis_cim,
        v.loja_origem AS vis_loja,
        v.oriente AS vis_oriente
    FROM chancelaria_presencas p
    LEFT JOIN chancelaria_membros m ON m.id = p.membro_id
    LEFT JOIN chancelaria_visitantes v ON v.id = p.visitante_id
    WHERE p.sessao_id = ? AND p.tenant_id = ?
    {$whereFiltro}
    ORDER BY p.id ASC
";
$stmtPres = $pdo_read->prepare($sqlPresencas);
$stmtPres->execute([$sessao_id, $tenant_id]);
$presencas = $stmtPres->fetchAll(PDO::FETCH_ASSOC);

// 4. Carrega certificado digital A1 (.pfx)
$pfxConteudo = @file_get_contents(ICP_CERT_PFX_PATH);
$certs = [];
if ($pfxConteudo === false || !openssl_pkcs12_read($pfxConteudo, $certs, ICP_CERT_PFX_PASS)) {
    die("Falha ao carregar o certificado digital A1 (.pfx). Verifique caminho e senha no config.php.");
}

$certPem = $certs['cert'];
$pkeyPem = $certs['pkey'];
$extraCertsPem = !empty($certs['extracerts']) ? implode("\n", $certs['extracerts']) : '';

// 5. Instancia TCPDF e assina digitalmente
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Chancelaria - Gestão Integrada');
$pdf->SetAuthor('Loja Maçônica / Chancelaria');
$pdf->SetTitle('Lista de Presença (' . $rotuloFiltro . ') - ' . ($sessao['titulo'] ?? 'Sessão'));
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(12, 14, 12);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

$infoAssinatura = [
    'Name'        => 'Chancelaria / Presidência da Sessão',
    'Location'    => 'Carpina, PE - Brasil',
    'Reason'      => 'Lista de Presença (' . $rotuloFiltro . ') e Manifesto de Assinaturas',
    'ContactInfo' => 'Chancelaria Oficial'
];
$pdf->setSignature($certPem, $pkeyPem, '', $extraCertsPem, 2, $infoAssinatura);

$dataSessaoFmt = !empty($sessao['data_ref']) ? date('d/m/Y', strtotime($sessao['data_ref'])) : '-';
$horaSessaoFmt = !empty($sessao['hora_sessao']) ? substr((string)$sessao['hora_sessao'], 0, 5) : '-';

$html = '
<h2 style="text-align:center; color:#141724; margin-bottom:2px;">LISTA OFICIAL DE PRESENÇA</h2>
<p style="text-align:center; font-size:9.5pt; font-weight:bold; color:#8a6400; margin:2px 0 6px 0;">
    ESCOPO: ' . htmlspecialchars($rotuloFiltro) . '
</p>
<p style="text-align:center; font-size:8.8pt; color:#444; margin-top:0;">
    <strong>Sessão:</strong> ' . htmlspecialchars((string)($sessao['titulo'] ?? '')) . ' &nbsp;|&nbsp;
    <strong>Data:</strong> ' . htmlspecialchars($dataSessaoFmt) . ' às ' . htmlspecialchars($horaSessaoFmt) . ' &nbsp;|&nbsp;
    <strong>Registros:</strong> ' . count($presencas) . '
</p>
<hr>
<h4 style="color:#141724; margin-bottom:6px;">1. Relação de Presentes</h4>
<table border="1" cellpadding="4" cellspacing="0" style="width:100%; font-size:8pt; border-collapse:collapse;">
    <thead>
        <tr style="background-color:#1d2132; color:#ffffff; font-weight:bold;">
            <th style="width:6%;">#</th>
            <th style="width:32%;">Nome</th>
            <th style="width:12%;">CIM</th>
            <th style="width:16%;">Tipo / Origem</th>
            <th style="width:34%;">Código da Assinatura</th>
        </tr>
    </thead>
    <tbody>';

$idx = 1;
foreach ($presencas as $row) {
    $isObreiro = !empty($row['membro_id']);
    $nome = $isObreiro
        ? ($row['membro_nome'] ?? '')
        : (!empty($row['snapshot_nome']) ? $row['snapshot_nome'] : ($row['vis_nome'] ?? ''));

    $cim = $isObreiro
        ? ($row['membro_cim'] ?? '')
        : (!empty($row['snapshot_cim']) ? $row['snapshot_cim'] : ($row['vis_cim'] ?? ''));

    $origem = $isObreiro
        ? 'Quadro da Loja'
        : trim(($row['vis_loja'] ?? '') . ' - ' . ($row['vis_oriente'] ?? ''), ' -');

    $html .= '
        <tr>
            <td>' . $idx++ . '</td>
            <td>' . htmlspecialchars((string)$nome) . '</td>
            <td>' . htmlspecialchars((string)$cim) . '</td>
            <td>' . htmlspecialchars((string)$origem) . '</td>
            <td style="font-family:courier; font-size:7pt;">' . htmlspecialchars((string)$row['codigo_assinatura']) . '</td>
        </tr>';
}

$html .= '
    </tbody>
</table>

<h4 style="color:#141724; margin-top:14px; margin-bottom:6px;">2. Manifesto de Integridade (HMAC-SHA256 e Carimbo UTC)</h4>
<table border="1" cellpadding="3" cellspacing="0" style="width:100%; font-size:6.8pt; border-collapse:collapse;">
    <thead>
        <tr style="background-color:#2d344d; color:#ffffff; font-weight:bold;">
            <th style="width:24%;">Código Assinatura</th>
            <th style="width:18%;">Data/Hora UTC</th>
            <th style="width:14%;">IP Origem</th>
            <th style="width:44%;">Hash HMAC-SHA256</th>
        </tr>
    </thead>
    <tbody>';

foreach ($presencas as $row) {
    $html .= '
        <tr>
            <td style="font-family:courier;">' . htmlspecialchars((string)$row['codigo_assinatura']) . '</td>
            <td style="font-family:courier;">' . htmlspecialchars((string)$row['assinado_em_utc']) . '</td>
            <td style="font-family:courier;">' . htmlspecialchars((string)$row['ip_origem']) . '</td>
            <td style="font-family:courier;">' . htmlspecialchars((string)$row['hash_assinatura']) . '</td>
        </tr>';
}

$html .= '
    </tbody>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');

// QR Code apontando para o validador público
$urlValidacaoBase = 'https://seu-dominio.com.br/chancelaria/validar_assinatura.php'; // Ajuste sua URL
$primeiroCodigo = $presencas[0]['codigo_assinatura'] ?? '';
$qrUrl = $urlValidacaoBase . ($primeiroCodigo ? '?codigo=' . urlencode($primeiroCodigo) : '');

$styleQr = [
    'border'        => 1,
    'vpadding'      => 'auto',
    'hpadding'      => 'auto',
    'fgcolor'       => [20, 23, 36],
    'bgcolor'       => false,
    'module_width'  => 2,
    'module_height' => 2
];

$yAtual = $pdf->GetY() + 6;
if ($yAtual > 245) {
    $pdf->AddPage();
    $yAtual = 20;
}

$pdf->write2DBarcode($qrUrl, 'QRCODE,M', 12, $yAtual, 28, 28, $styleQr, 'N');
$pdf->SetXY(44, $yAtual + 2);
$pdf->SetFont('helvetica', '', 8);
$pdf->MultiCell(154, 4, "Documento assinado digitalmente com certificado ICP-Brasil (A1) — Escopo: " . $rotuloFiltro . ".\nConferência pública via QR Code ou em:\n" . $qrUrl, 0, 'L');

$pdf->setSignatureAppearance(12, $yAtual + 32, 80, 18);

// 6. Salva arquivo temporário, calcula SHA-256 final e trava a sessão no banco
$nomeArquivo = 'lista_presenca_sessao_' . $sessao_id . '_' . $sufixoArquivo . '.pdf';
$caminhoArquivo = sys_get_temp_dir() . '/' . $nomeArquivo;

$pdf->Output($caminhoArquivo, 'F');
$hashPdfFinal = hash_file('sha256', $caminhoArquivo);

$colunaHash = match ($filtro) {
    'membros'    => 'hash_pdf_membros',
    'visitantes' => 'hash_pdf_visitantes',
    default      => 'hash_pdf_completa'
};

$stmtUpd = $pdo_write->prepare("
    UPDATE chancelaria_sessoes
    SET lista_emitida = 1,
        lista_emitida_em = COALESCE(lista_emitida_em, NOW()),
        {$colunaHash} = ?
    WHERE id = ? AND tenant_id = ?
");
$stmtUpd->execute([$hashPdfFinal, $sessao_id, $tenant_id]);

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $nomeArquivo . '"');
header('X-Document-SHA256: ' . $hashPdfFinal);
readfile($caminhoArquivo);
@unlink($caminhoArquivo);
exit;
