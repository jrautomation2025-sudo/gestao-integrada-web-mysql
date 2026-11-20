<?php
//ini_set('display_errors', 1);
//error_reporting(E_ALL);
require_once '../configuracoes/config.php';
session_start();

date_default_timezone_set('America/Sao_Paulo');

$sessao_id = filter_input(INPUT_GET, 'sessao_id', FILTER_VALIDATE_INT) ?: 0;
if ($sessao_id <= 0) {
    die("Sessão não informada.");
}

$stmt = $pdo_read->prepare("
    SELECT id, tenant_id, titulo, data_sessao, hora_sessao, lista_emitida, lista_emitida_em,
           hash_pdf_completa, hash_pdf_membros, hash_pdf_visitantes
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
        SUM(CASE WHEN p.membro_id IS NOT NULL THEN 1 ELSE 0 END) AS total_membros,
        (SELECT count(1) FROM chancelaria_visitantes v WHERE v.sessao_id = p.sessao_id) AS total_visitantes
    FROM chancelaria_presencas p
    WHERE p.sessao_id = ?
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
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --bg-main: #141724; --bg-card: #1d2132; --text-main: #e2e8f0; --gold: #f5c041; --border-color: #333951; }
        body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Inter', sans-serif; padding: 15px; }
        .wrapper-responsivo { max-width: 680px; margin: 0 auto; }
        .card-custom { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        .btn-gold { background-color: var(--gold); color: #141724; font-weight: 600; }
        .btn-gold:hover { background-color: #dca732; color: #141724; }
        .form-select { background-color: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); }
        .form-select:focus { background-color: var(--bg-main); border-color: var(--gold); color: var(--text-main); box-shadow: 0 0 0 0.25rem rgba(245, 192, 65, 0.25); }
        .stat-box { background-color: var(--bg-main); border: 1px solid var(--border-color); border-radius: 10px; padding: 12px 8px; }
        @media (min-width: 768px) {
            body { padding: 30px; }
            .card-custom { padding: 28px; }
        }
    </style>
</head>
<body>

<div class="wrapper-responsivo">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <a href="?sessao_id=<?= (int)$sessao_id ?>" onclick="if(document.referrer.includes('painel') || history.length > 1){ history.back(); return false; }" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Voltar
        </a>
        <a href="conferir_pdf_sessao.php?sessao_id=<?= (int)$sessao_id ?>" class="btn btn-outline-warning btn-sm">
            <i class="fas fa-file-shield me-1"></i> Conferir SHA-256
        </a>
    </div>

    <div class="card-custom">
        <h4 style="font-family: 'Cinzel', serif; color: var(--gold);" class="mb-1">
            <i class="fas fa-file-pdf me-2"></i>Emitir Lista Oficial em PDF
        </h4>
        <p class="small text-secondary mb-3">
            <?= htmlspecialchars((string)$sessao['titulo']) ?> —
            <?= !empty($sessao['data_sessao']) ? date('d/m/Y', strtotime($sessao['data_sessao'])) : '' ?>
            <?= !empty($sessao['hora_sessao']) ? ' às ' . htmlspecialchars(substr((string)$sessao['hora_sessao'], 0, 5)) : '' ?>
        </p>

        <?php if ((int)($sessao['lista_emitida'] ?? 0) === 1): ?>
            <div class="alert alert-warning small py-2 mb-3">
                <i class="fas fa-lock me-1"></i>
                Lista emitida em <?= !empty($sessao['lista_emitida_em']) ? date('d/m/Y H:i', strtotime($sessao['lista_emitida_em'])) : '' ?>. Novos check-ins públicos estão bloqueados.
            </div>
        <?php endif; ?>

        <div class="row text-center g-2 mb-4">
            <div class="col-4">
                <div class="stat-box">
                    <div class="small text-secondary">Membros</div>
                    <div class="fs-5 fw-bold text-warning"><?= (int)($totais['total_membros'] ?? 0) ?></div>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-box">
                    <div class="small text-secondary">Visitantes</div>
                    <div class="fs-5 fw-bold text-info"><?= (int)($totais['total_visitantes'] ?? 0) ?></div>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-box">
                    <div class="small text-secondary">Completa</div>
                    <div class="fs-5 fw-bold text-success"><?= (int)($totais['total_membros'] + (int)($totais['total_visitantes'] ?? 0) ?></div>
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

            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="submit" class="btn btn-gold px-4 py-2 w-100 w-md-auto">
                    <i class="fas fa-certificate me-2"></i>Gerar e Assinar PDF
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
