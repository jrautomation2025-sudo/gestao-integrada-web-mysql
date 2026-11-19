<?php
require_once '../configuracoes/config.php';
session_start();

$sessao_id = (int)($_GET['sessao_id'] ?? 0);
if ($sessao_id <= 0) {
    die("Sessão não informada.");
}

$stmt = $pdo_read->prepare("
    SELECT id, titulo, COALESCE(data, data_sessao) AS data_ref, hora_sessao, lista_emitida, lista_emitida_em
    FROM chancelaria_sessoes
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$sessao_id]);
$sessao = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sessao) {
    die("Sessão não encontrada.");
}

$stmtCount = $pdo_read->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN membro_id IS NOT NULL THEN 1 ELSE 0 END) AS total_membros,
        SUM(CASE WHEN membro_id IS NULL THEN 1 ELSE 0 END) AS total_visitantes
    FROM chancelaria_presencas
    WHERE sessao_id = ?
");
$stmtCount->execute([$sessao_id]);
$totais = $stmtCount->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emitir Lista de Presença - Chancelaria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #141724; color: #e2e8f0; padding: 24px; }
        .card-custom { background-color: #1d2132; border: 1px solid #333951; border-radius: 12px; padding: 24px; max-width: 560px; margin: auto; }
        .btn-gold { background-color: #f5c041; color: #141724; font-weight: 600; }
        .btn-gold:hover { background-color: #dca732; color: #141724; }
        .form-select { background-color: #141724; border: 1px solid #333951; color: #fff; }
        .form-select:focus { background-color: #141724; border-color: #f5c041; color: #fff; box-shadow: none; }
    </style>
</head>
<body>

<div class="card-custom">
    <h4 class="text-warning mb-1"><i class="fas fa-file-pdf me-2"></i>Emitir Lista Oficial em PDF</h4>
    <p class="small text-secondary mb-3">
        <?= htmlspecialchars((string)$sessao['titulo']) ?> —
        <?= !empty($sessao['data_ref']) ? date('d/m/Y', strtotime($sessao['data_ref'])) : '' ?>
    </p>

    <?php if ((int)$sessao['lista_emitida'] === 1): ?>
        <div class="alert alert-warning small py-2">
            <i class="fas fa-lock me-1"></i> Lista já emitida em <?= !empty($sessao['lista_emitida_em']) ? date('d/m/Y H:i', strtotime($sessao['lista_emitida_em'])) : '' ?>. Novos check-ins públicos estão bloqueados.
        </div>
    <?php endif; ?>

    <div class="row text-center g-2 mb-4">
        <div class="col-4">
            <div class="p-2 rounded bg-dark border border-secondary">
                <div class="small text-secondary">Membros</div>
                <div class="fw-bold text-warning"><?= (int)($totais['total_membros'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="p-2 rounded bg-dark border border-secondary">
                <div class="small text-secondary">Visitantes</div>
                <div class="fw-bold text-info"><?= (int)($totais['total_visitantes'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="p-2 rounded bg-dark border border-secondary">
                <div class="small text-secondary">Completa</div>
                <div class="fw-bold text-success"><?= (int)($totais['total'] ?? 0) ?></div>
            </div>
        </div>
    </div>

    <form method="GET" action="gerar_lista_pdf.php" target="_blank">
        <input type="hidden" name="sessao_id" value="<?= (int)$sessao_id ?>">

        <div class="mb-4">
            <label for="filtroLista" class="form-label small text-white">Selecione o tipo de lista a gerar:</label>
            <select name="filtro" id="filtroLista" class="form-select form-select-lg">
                <option value="todos" selected>Lista Completa (Membros + Visitantes)</option>
                <option value="membros">Apenas Membros (Obreiros do Quadro)</option>
                <option value="visitantes">Apenas Visitantes</option>
            </select>
        </div>

        <button type="submit" class="btn btn-gold w-100 py-2">
            <i class="fas fa-certificate me-2"></i>Gerar e Assinar PDF
        </button>
    </form>
</div>

</body>
</html>
