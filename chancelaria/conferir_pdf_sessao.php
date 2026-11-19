<?php
//ini_set('display_errors', 1);
//error_reporting(E_ALL);
require_once '../configuracoes/config.php';
session_start();

$sessaoId = (int)($_REQUEST['sessao_id'] ?? 0);
$escopoSelecionado = strtolower(trim($_REQUEST['escopo'] ?? 'completa'));
if (!in_array($escopoSelecionado, ['completa', 'membros', 'visitantes'], true)) {
    $escopoSelecionado = 'completa';
}

$hashInformado = strtolower(preg_replace('/[^a-fA-F0-9]/', '', trim($_POST['hash_manual'] ?? '')));
$hashCalculadoUpload = '';
$hashParaComparar = '';
$sessao = null;
$statusConferencia = null; // 'confere', 'divergente', 'nao_encontrado', 'sem_hash'
$colunaHashBanco = match ($escopoSelecionado) {
    'membros'    => 'hash_pdf_membros',
    'visitantes' => 'hash_pdf_visitantes',
    default      => 'hash_pdf_completa'
};

// Carrega dados da sessão se sessao_id foi informado
$stmtSessoes = $pdo_read->query("
    SELECT id, titulo, COALESCE(data, data_sessao) AS data_ref,
           hash_pdf_completa, hash_pdf_membros, hash_pdf_visitantes, lista_emitida_em
    FROM chancelaria_sessoes
    ORDER BY id DESC
    LIMIT 100
");
$listaSessoes = $stmtSessoes->fetchAll(PDO::FETCH_ASSOC);

if ($sessaoId > 0) {
    $stmt = $pdo_read->prepare("
        SELECT id, tenant_id, titulo, COALESCE(data, data_sessao) AS data_ref, hora_sessao,
               lista_emitida, lista_emitida_em,
               hash_pdf_completa, hash_pdf_membros, hash_pdf_visitantes
        FROM chancelaria_sessoes
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$sessaoId]);
    $sessao = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$sessao) {
        $statusConferencia = 'nao_encontrado';
    } else {
        // Prioriza upload do arquivo PDF caso um arquivo tenha sido enviado
        if (!empty($_FILES['arquivo_pdf']['tmp_name']) && is_uploaded_file($_FILES['arquivo_pdf']['tmp_name'])) {
            if ($_FILES['arquivo_pdf']['error'] === UPLOAD_ERR_OK) {
                $hashCalculadoUpload = strtolower(hash_file('sha256', $_FILES['arquivo_pdf']['tmp_name']));
                $hashParaComparar = $hashCalculadoUpload;
            }
        } elseif ($hashInformado !== '') {
            $hashParaComparar = $hashInformado;
        }

        $hashSalvoBanco = strtolower((string)($sessao[$colunaHashBanco] ?? ''));

        if ($hashParaComparar === '') {
            $statusConferencia = 'sem_hash_entrada';
        } elseif ($hashSalvoBanco === '') {
            $statusConferencia = 'sem_hash_banco';
        } elseif (hash_equals($hashSalvoBanco, $hashParaComparar)) {
            $statusConferencia = 'confere';
        } else {
            $statusConferencia = 'divergente';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conferir SHA-256 do PDF - Chancelaria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { background-color: #141724; color: #e2e8f0; font-family: 'Inter', sans-serif; padding: 24px; }
        .card-custom { background-color: #1d2132; border: 1px solid #333951; border-radius: 12px; padding: 24px; max-width: 720px; margin: auto; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        .btn-gold { background-color: #f5c041; color: #141724; font-weight: 600; }
        .btn-gold:hover { background-color: #dca732; color: #141724; }
        .form-control, .form-select { background-color: #141724; border: 1px solid #333951; color: #fff; }
        .form-control:focus, .form-select:focus { background-color: #141724; border-color: #f5c041; color: #fff; box-shadow: none; }
        .hash-box { background-color: #141724; border: 1px solid #333951; border-radius: 6px; padding: 10px; font-family: monospace; font-size: 0.78rem; word-break: break-all; }
    </style>
</head>
<body>

<div class="card-custom">
    <div class="text-center mb-4">
        <i class="fas fa-file-shield text-warning mb-2" style="font-size: 2.3rem;"></i>
        <h3 style="font-family: 'Cinzel', serif; color: #f5c041;">Conferência de Integridade do PDF</h3>
        <p class="text-secondary small mb-0">Compare o hash SHA-256 de um arquivo PDF ou hash informado com o registro salvo na sessão</p>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <div class="row g-3 mb-3">
            <div class="col-md-8">
                <label for="sessao_id" class="form-label small text-white">Sessão</label>
                <select name="sessao_id" id="sessao_id" class="form-select" required>
                    <option value="">Selecione a sessão...</option>
                    <?php foreach ($listaSessoes as $s): ?>
                        <?php
                            $dtFmt = !empty($s['data_ref']) ? date('d/m/Y', strtotime($s['data_ref'])) : '';
                            $sel = ((int)$s['id'] === $sessaoId) ? 'selected' : '';
                        ?>
                        <option value="<?= (int)$s['id'] ?>" <?= $sel ?>>
                            #<?= (int)$s['id'] ?> — <?= htmlspecialchars((string)$s['titulo']) ?> <?= $dtFmt ? "({$dtFmt})" : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label for="escopo" class="form-label small text-white">Tipo de Lista</label>
                <select name="escopo" id="escopo" class="form-select">
                    <option value="completa" <?= $escopoSelecionado === 'completa' ? 'selected' : '' ?>>Completa</option>
                    <option value="membros" <?= $escopoSelecionado === 'membros' ? 'selected' : '' ?>>Membros</option>
                    <option value="visitantes" <?= $escopoSelecionado === 'visitantes' ? 'selected' : '' ?>>Visitantes</option>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label for="arquivo_pdf" class="form-label small text-white">1. Enviar Arquivo PDF (calcula SHA-256 automaticamente)</label>
            <input type="file" name="arquivo_pdf" id="arquivo_pdf" accept="application/pdf,.pdf" class="form-control">
        </div>

        <div class="mb-4">
            <label for="hash_manual" class="form-label small text-white">2. Ou informe o Hash SHA-256 manualmente (64 caracteres hex)</label>
            <input type="text" name="hash_manual" id="hash_manual" maxlength="64" class="form-control" style="font-family: monospace;" value="<?= htmlspecialchars($hashInformado) ?>" placeholder="Ex: e3b0c44298fc1c149afbf4c8996fb924...">
        </div>

        <button type="submit" class="btn btn-gold w-100 py-2">
            <i class="fas fa-check-double me-2"></i>Conferir Hash SHA-256
        </button>
    </form>

    <?php if ($statusConferencia !== null): ?>
        <hr class="border-secondary my-4">

        <?php if ($statusConferencia === 'nao_encontrado'): ?>
            <div class="alert alert-danger text-center mb-0">
                <i class="fas fa-times-circle me-2"></i>Sessão não encontrada no banco de dados.
            </div>

        <?php elseif ($statusConferencia === 'sem_hash_entrada'): ?>
            <div class="alert alert-warning text-dark text-center mb-0">
                <i class="fas fa-exclamation-circle me-2"></i>Envie um arquivo PDF ou digite um hash SHA-256 para realizar a comparação.
            </div>

        <?php elseif ($statusConferencia === 'sem_hash_banco'): ?>
            <div class="alert alert-warning text-dark text-center mb-0">
                <i class="fas fa-info-circle me-2"></i>Ainda não há hash SHA-256 salvo para o escopo <strong><?= htmlspecialchars($escopoSelecionado) ?></strong> nesta sessão.
            </div>

        <?php elseif ($statusConferencia === 'confere'): ?>
            <div class="alert alert-success mb-3">
                <i class="fas fa-check-circle me-2"></i><strong>Hash Conferido!</strong> O PDF/hash corresponde exatamente ao registro oficial salvo na sessão.
            </div>

        <?php elseif ($statusConferencia === 'divergente'): ?>
            <div class="alert alert-danger mb-3">
                <i class="fas fa-exclamation-triangle me-2"></i><strong>Hash Divergente!</strong> O valor informado/calculado não bate com o hash salvo no banco de dados.
            </div>
        <?php endif; ?>

        <?php if ($sessao): ?>
            <?php $hashBancoExibicao = (string)($sessao[$colunaHashBanco] ?? ''); ?>
            <div class="mt-3">
                <div class="small text-secondary mb-1">Hash SHA-256 Salvo na Sessão (<code><?= htmlspecialchars($colunaHashBanco) ?></code>):</div>
                <div class="hash-box text-warning mb-3"><?= $hashBancoExibicao !== '' ? htmlspecialchars($hashBancoExibicao) : 'Não registrado' ?></div>

                <div class="small text-secondary mb-1">Hash SHA-256 Analisado (Upload ou Manual):</div>
                <div class="hash-box <?= $statusConferencia === 'confere' ? 'text-success' : 'text-info' ?>">
                    <?= $hashParaComparar !== '' ? htmlspecialchars($hashParaComparar) : 'Nenhum' ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>
