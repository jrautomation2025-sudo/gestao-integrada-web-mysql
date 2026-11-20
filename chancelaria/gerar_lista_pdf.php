<?php
//ini_set('display_errors', 1);
//error_reporting(E_ALL);
require_once '../configuracoes/config.php';
require_once '../vendor/tecnickcom/tcpdf/tcpdf.php'; // Ajuste o caminho do TCPDF se necessário

$sessao_id = filter_input(INPUT_GET, 'sessao_id', FILTER_VALIDATE_INT) ?: 0;
if ($sessao_id <= 0) {
    die("Sessão inválida.");
}

// 1. Validação do filtro (whitelist)
$filtroPermitidos = ['todos', 'membros', 'visitantes'];
$filtro = strtolower(trim((string)($_GET['filtro'] ?? 'todos')));
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
    SELECT *
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
$participantes = [];

// 3. Busca membros em chancelaria_presencas (quando filtro é 'todos' ou 'membros')
if ($filtro === 'todos' || $filtro === 'membros') {
    $sqlMembros = "
        SELECT
            p.id AS ordem_id,
            'Membro do Quadro' AS categoria_tipo,
            m.nome,
            m.cim,
            'Quadro da Loja' AS origem_label,
            COALESCE(p.codigo_assinatura, '-') AS codigo_assinatura,
            COALESCE(p.assinado_em_utc, COALESCE(p.created_at, '-')) AS assinado_em_utc,
            COALESCE(p.ip_origem, '-') AS ip_origem,
            COALESCE(p.hash_assinatura, '-') AS hash_assinatura
        FROM chancelaria_presencas p
        INNER JOIN chancelaria_membros m
            ON m.id = p.membro_id
           AND m.tenant_id = p.tenant_id
        WHERE p.sessao_id = ?
          AND p.tenant_id = ?
          AND p.status_presenca = 'P'
          AND (p.justificativa IS NULL OR TRIM(p.justificativa) = '')
          AND NOT EXISTS (
              SELECT 1
              FROM chancelaria_presencas pj
              WHERE pj.tenant_id = p.tenant_id
                AND pj.sessao_id = p.sessao_id
                AND pj.membro_id = p.membro_id
                AND pj.status_presenca IN ('J', 'Justificado')
          )
        ORDER BY p.id ASC
    ";
    $stmtM = $pdo_read->prepare($sqlMembros);
    $stmtM->execute([$sessao_id, $tenant_id]);
    foreach ($stmtM->fetchAll(PDO::FETCH_ASSOC) as $rowM) {
        $participantes[] = $rowM;
    }
}

// 4. Busca visitantes em chancelaria_visitantes (quando filtro é 'todos' ou 'visitantes')
if ($filtro === 'todos' || $filtro === 'visitantes') {
    $sqlVisitantes = "
        SELECT
            v.id AS ordem_id,
            'Visitante' AS categoria_tipo,
            v.nome,
            v.cim,
            TRIM(BOTH ' - ' FROM CONCAT_WS(' - ', NULLIF(v.loja_origem, ''), NULLIF(v.oriente, ''))) AS origem_label,
            COALESCE(v.codigo_assinatura, '-') AS codigo_assinatura,
            COALESCE(v.assinado_em_utc, COALESCE(v.created_at, '-')) AS assinado_em_utc,
            COALESCE(v.ip_origem, '-') AS ip_origem,
            COALESCE(v.hash_assinatura, '-') AS hash_assinatura
        FROM chancelaria_visitantes v
        WHERE v.sessao_id = ?
          AND v.tenant_id = ?
        ORDER BY v.id ASC
    ";
    $stmtV = $pdo_read->prepare($sqlVisitantes);
    $stmtV->execute([$sessao_id, $tenant_id]);
    foreach ($stmtV->fetchAll(PDO::FETCH_ASSOC) as $rowV) {
        $rowV['origem_label'] = $rowV['origem_label'] !== '' ? $rowV['origem_label'] : 'Visitante';
        $participantes[] = $rowV;
    }
}

// 5. Tenta carregar certificado digital A1 (.pfx) opcionalmente
$assinaturaPfxAplicada = false;
$statusPfxTexto = "Sem certificado A1 (.pfx) configurado — Manifesto HMAC-SHA256 ativo";
$certPem = '';
$pkeyPem = '';
$extraCertsPem = '';

$pfxPath = defined('ICP_CERT_PFX_PATH') ? (string)ICP_CERT_PFX_PATH : '';
$pfxPass = defined('ICP_CERT_PFX_PASS') ? (string)ICP_CERT_PFX_PASS : '';

if ($pfxPath !== '' && is_readable($pfxPath)) {
    $pfxConteudo = @file_get_contents($pfxPath);
    $certs = [];
    if ($pfxConteudo !== false && openssl_pkcs12_read($pfxConteudo, $certs, $pfxPass)) {
        $certPem = $certs['cert'] ?? '';
        $pkeyPem = $certs['pkey'] ?? '';
        $extraCertsPem = !empty($certs['extracerts']) ? implode("\n", $certs['extracerts']) : '';
        if ($certPem !== '' && $pkeyPem !== '') {
            $assinaturaPfxAplicada = true;
            $statusPfxTexto = "Assinado digitalmente com Certificado A1 (.pfx / ICP-Brasil)";
        }
    } else {
        $statusPfxTexto = "Certificado A1 (.pfx) encontrado, mas não foi possível abrir (verifique senha)";
    }
}

// 6. Instancia TCPDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Chancelaria - Gestão Integrada');
$pdf->SetAuthor('Loja Maçônica / Chancelaria');
$pdf->SetTitle('Lista de Presença (' . $rotuloFiltro . ') - ' . ($sessao['titulo'] ?? 'Sessão'));
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(12, 14, 12);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

if ($assinaturaPfxAplicada) {
    $infoAssinatura = [
        'Name'        => 'Chancelaria / Presidência da Sessão',
        'Location'    => 'Carpina, PE - Brasil',
        'Reason'      => 'Lista de Presença (' . $rotuloFiltro . ') e Manifesto de Assinaturas',
        'ContactInfo' => 'Chancelaria Oficial'
    ];
    $pdf->setSignature($certPem, $pkeyPem, '', $extraCertsPem, 2, $infoAssinatura);
}

$dataSessaoFmt = !empty($sessao['data_sessao']) ? date('d/m/Y', strtotime((string)$sessao['data_sessao'])) : '-';
$horaSessaoFmt = !empty($sessao['hora_sessao']) ? substr((string)$sessao['hora_sessao'], 0, 5) : '-';

$html = '
<h2 style="text-align:center; color:#141724; margin-bottom:2px;">LISTA OFICIAL DE PRESENÇA</h2>
<p style="text-align:center; font-size:9.5pt; font-weight:bold; color:#8a6400; margin:2px 0 6px 0;">
    ESCOPO: ' . htmlspecialchars($rotuloFiltro) . '
</p>
<p style="text-align:center; font-size:8.5pt; color:#444; margin-top:0;">
    <strong>Sessão:</strong> ' . htmlspecialchars((string)($sessao['titulo'] ?? '')) . ' &nbsp;|&nbsp;
    <strong>Data:</strong> ' . htmlspecialchars($dataSessaoFmt) . ' às ' . htmlspecialchars($horaSessaoFmt) . ' &nbsp;|&nbsp;
    <strong>Registros:</strong> ' . count($participantes) . '<br>
    <strong>Status Criptográfico:</strong> ' . htmlspecialchars($statusPfxTexto) . '
</p>
<hr>
<h4 style="color:#141724; margin-bottom:6px;">1. Relação de Presentes</h4>
<table border="1" cellpadding="4" cellspacing="0" style="width:100%; font-size:8pt; border-collapse:collapse;">
    <thead>
        <tr style="background-color:#1d2132; color:#ffffff; font-weight:bold;">
            <th style="width:6%;">#</th>
            <th style="width:30%;">Nome</th>
            <th style="width:12%;">CIM</th>
            <th style="width:20%;">Origem / Tipo</th>
            <th style="width:32%;">Código da Assinatura</th>
        </tr>
    </thead>
    <tbody>';

$idx = 1;
foreach ($participantes as $row) {
    $html .= '
        <tr>
            <td>' . $idx++ . '</td>
            <td>' . htmlspecialchars((string)$row['nome']) . '</td>
            <td>' . htmlspecialchars((string)$row['cim']) . '</td>
            <td>' . htmlspecialchars((string)$row['origem_label']) . '</td>
            <td style="font-family:courier; font-size:7pt;">' . htmlspecialchars((string)$row['codigo_assinatura']) . '</td>
        </tr>';
}

if (empty($participantes)) {
    $html .= '<tr><td colspan="5" style="text-align:center; color:#666;">Nenhum registro encontrado para este filtro.</td></tr>';
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

foreach ($participantes as $row) {
    $html .= '
        <tr>
            <td style="font-family:courier;">' . htmlspecialchars((string)$row['codigo_assinatura']) . '</td>
            <td style="font-family:courier;">' . htmlspecialchars((string)$row['assinado_em_utc']) . '</td>
            <td style="font-family:courier;">' . htmlspecialchars((string)$row['ip_origem']) . '</td>
            <td style="font-family:courier;">' . htmlspecialchars((string)$row['hash_assinatura']) . '</td>
        </tr>';
}

if (empty($participantes)) {
    $html .= '<tr><td colspan="4" style="text-align:center; color:#666;">Sem registros para exibir no manifesto.</td></tr>';
}

$html .= '
    </tbody>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');

// 7. QR Code apontando para o validador público
$urlValidacaoBase = 'https://seu-dominio.com.br/chancelaria/validar_assinatura.php'; // Ajuste sua URL
$primeiroCodigo = '';
foreach ($participantes as $pItem) {
    if (!empty($pItem['codigo_assinatura']) && $pItem['codigo_assinatura'] !== '-') {
        $primeiroCodigo = (string)$pItem['codigo_assinatura'];
        break;
    }
}
$qrUrl = $urlValidacaoBase . ($primeiroCodigo !== '' ? '?codigo=' . urlencode($primeiroCodigo) : '');

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

$notaRodape = $assinaturaPfxAplicada
    ? "Documento assinado digitalmente com certificado ICP-Brasil (A1) — Escopo: " . $rotuloFiltro . ".\nConferência pública via QR Code ou em:\n" . $qrUrl
    : "Documento com manifesto de integridade HMAC-SHA256 e registro de auditoria — Escopo: " . $rotuloFiltro . ".\nConferência pública via QR Code ou em:\n" . $qrUrl;

$pdf->write2DBarcode($qrUrl, 'QRCODE,M', 12, $yAtual, 28, 28, $styleQr, 'N');
$pdf->SetXY(44, $yAtual + 2);
$pdf->SetFont('helvetica', '', 8);
$pdf->MultiCell(154, 4, $notaRodape, 0, 'L');

if ($assinaturaPfxAplicada) {
    $pdf->setSignatureAppearance(12, $yAtual + 32, 80, 18);
}

// 8. Salva arquivo temporário, calcula SHA-256 final e grava na sessão
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
