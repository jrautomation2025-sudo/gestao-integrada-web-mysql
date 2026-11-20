<?php
//ini_set('display_errors', 1);
//error_reporting(E_ALL);
require_once '../configuracoes/config.php';

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

$colunaHash = match ($filtro) {
    'membros'    => 'hash_pdf_membros',
    'visitantes' => 'hash_pdf_visitantes',
    default      => 'hash_pdf_completa'
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

// 3. Busca membros em chancelaria_presencas
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

// 4. Busca visitantes em chancelaria_visitantes
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

// 5. Calcula hash SHA-256 do manifesto/lista gerado
$payloadManifesto = json_encode([
    'sessao_id'     => $sessao_id,
    'tenant_id'     => $tenant_id,
    'filtro'        => $filtro,
    'titulo'        => $sessao['titulo'] ?? '',
    'data_sessao'   => $sessao['data_sessao'] ?? '',
    'participantes' => $participantes
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$hashDocumento = hash('sha256', (string)$payloadManifesto);

// Marca emissão e salva hash de conferência na sessão
$stmtUpd = $pdo_write->prepare("
    UPDATE chancelaria_sessoes
    SET lista_emitida = 1,
        lista_emitida_em = COALESCE(lista_emitida_em, NOW()),
        {$colunaHash} = ?
    WHERE id = ? AND tenant_id = ?
");
$stmtUpd->execute([$hashDocumento, $sessao_id, $tenant_id]);

$dataSessaoFmt = !empty($sessao['data_sessao']) ? date('d/m/Y', strtotime((string)$sessao['data_sessao'])) : '-';
$horaSessaoFmt = !empty($sessao['hora_sessao']) ? substr((string)$sessao['hora_sessao'], 0, 5) : '-';
$primeiroCodigo = '';
foreach ($participantes as $pItem) {
    if (!empty($pItem['codigo_assinatura']) && $pItem['codigo_assinatura'] !== '-') {
        $primeiroCodigo = (string)$pItem['codigo_assinatura'];
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Presença - <?= htmlspecialchars((string)($sessao['titulo'] ?? 'Sessão')) ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; color: #141724; background: #f8fafc; margin: 0; padding: 20px; }
        .sheet { max-width: 900px; margin: 0 auto; background: #fff; padding: 28px; border: 1px solid #cbd5e1; border-radius: 8px; }
        .toolbar { max-width: 900px; margin: 0 auto 14px; display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
        .btn { padding: 8px 14px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-primary { background: #1d2132; color: #f5c041; }
        .btn-secondary { background: #e2e8f0; color: #141724; }
        h1 { font-size: 18px; text-align: center; margin: 0 0 4px; }
        .sub { text-align: center; font-size: 12px; color: #475569; margin-bottom: 14px; }
        .escopo { text-align: center; font-size: 12px; font-weight: bold; color: #8a6400; margin-bottom: 8px; }
        h2 { font-size: 14px; margin: 18px 0 8px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px; text-align: left; }
        th { background: #1d2132; color: #fff; }
        .mono { font-family: monospace; font-size: 10px; word-break: break-all; }
        .footer-box { margin-top: 18px; padding-top: 12px; border-top: 1px solid #cbd5e1; display: flex; align-items: center; gap: 16px; }
        @media print {
            .toolbar { display: none !important; }
            body { background: #fff; padding: 0; }
            .sheet { border: none; padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <a href="emissao_lista.php?sessao_id=<?= (int)$sessao_id ?>" class="btn btn-secondary">Voltar</a>
    <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir / Salvar como PDF</button>
</div>

<div class="sheet">
    <h1>LISTA OFICIAL DE PRESENÇA E MANIFESTO</h1>
    <div class="escopo">ESCOPO: <?= htmlspecialchars($rotuloFiltro) ?></div>
    <div class="sub">
        <strong>Sessão:</strong> <?= htmlspecialchars((string)($sessao['titulo'] ?? '')) ?> |
        <strong>Data:</strong> <?= htmlspecialchars($dataSessaoFmt) ?> às <?= htmlspecialchars($horaSessaoFmt) ?> |
        <strong>Registros:</strong> <?= count($participantes) ?>
    </div>

    <h2>1. Relação de Presentes</h2>
    <table>
        <thead>
            <tr>
                <th style="width:6%;">#</th>
                <th style="width:30%;">Nome</th>
                <th style="width:12%;">CIM</th>
                <th style="width:20%;">Origem / Tipo</th>
                <th style="width:32%;">Código da Assinatura</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($participantes)): ?>
                <tr><td colspan="5" style="text-align:center;">Nenhum registro encontrado para este filtro.</td></tr>
            <?php else: ?>
                <?php foreach ($participantes as $i => $row): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars((string)$row['nome']) ?></td>
                        <td><?= htmlspecialchars((string)$row['cim']) ?></td>
                        <td><?= htmlspecialchars((string)$row['origem_label']) ?></td>
                        <td class="mono"><?= htmlspecialchars((string)$row['codigo_assinatura']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <h2>2. Manifesto de Integridade (HMAC-SHA256 e Carimbo UTC)</h2>
    <table>
        <thead>
            <tr>
                <th style="width:24%;">Código Assinatura</th>
                <th style="width:18%;">Data/Hora UTC</th>
                <th style="width:14%;">IP Origem</th>
                <th style="width:44%;">Hash HMAC-SHA256</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($participantes)): ?>
                <tr><td colspan="4" style="text-align:center;">Sem registros no manifesto.</td></tr>
            <?php else: ?>
                <?php foreach ($participantes as $row): ?>
                    <tr>
                        <td class="mono"><?= htmlspecialchars((string)$row['codigo_assinatura']) ?></td>
                        <td class="mono"><?= htmlspecialchars((string)$row['assinado_em_utc']) ?></td>
                        <td class="mono"><?= htmlspecialchars((string)$row['ip_origem']) ?></td>
                        <td class="mono"><?= htmlspecialchars((string)$row['hash_assinatura']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer-box">
        <div id="qrValidador"></div>
        <div style="font-size:11px;">
            <div><strong>Hash SHA-256 de Emissão:</strong> <span class="mono"><?= htmlspecialchars($hashDocumento) ?></span></div>
            <div style="margin-top:4px;"><strong>Validação Pública:</strong> <span class="mono" id="urlValTexto"></span></div>
        </div>
    </div>
</div>

<script>
    const baseValidador = window.location.origin + "/chancelaria/validar_assinatura.php";
    const codigoRef = <?= json_encode($primeiroCodigo) ?>;
    const urlFinal = codigoRef ? (baseValidador + "?codigo=" + encodeURIComponent(codigoRef)) : baseValidador;
    document.getElementById("urlValTexto").innerText = urlFinal;
    new QRCode(document.getElementById("qrValidador"), {
        text: urlFinal,
        width: 96,
        height: 96
    });
</script>
</body>
</html>
