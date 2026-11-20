<?php
//ini_set('display_errors', 1);
//error_reporting(E_ALL);
require_once '../configuracoes/config.php';

// ==========================================
// FUNÇÕES DE RECONSTITUIÇÃO E MASCARAMENTO
// ==========================================
function recalcularHashAssinatura(array $registro, string $origemTabela): string {
    if ($origemTabela === 'membro') {
        $dados = [
            'tipo_presenca' => 'obreiro',
            'tenant_id'     => (int)$registro['tenant_id'],
            'sessao_id'     => (int)$registro['sessao_id'],
            'membro_id'     => (int)$registro['membro_id'],
            'status'        => (string)($registro['status_presenca'] ?? 'P'),
            'ip_origem'     => (string)($registro['ip_origem'] ?? ''),
            'user_agent'    => (string)($registro['user_agent'] ?? '')
        ];
    } else {
        $dados = [
            'tipo_presenca' => 'visitante',
            'tenant_id'     => (int)$registro['tenant_id'],
            'sessao_id'     => (int)$registro['sessao_id'],
            'cim_visitante' => (string)($registro['cim'] ?? ''),
            'nome'          => (string)($registro['nome'] ?? ''),
            'ip_origem'     => (string)($registro['ip_origem'] ?? ''),
            'user_agent'    => (string)($registro['user_agent'] ?? '')
        ];
    }

    $payload = json_encode([
        'codigo_assinatura' => (string)$registro['codigo_assinatura'],
        'assinado_em_utc'   => (string)$registro['assinado_em_utc'],
        'dados'             => $dados
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $secret = defined('APP_SIGNATURE_SECRET') ? APP_SIGNATURE_SECRET : '';
    return hash_hmac('sha256', (string)$payload, $secret);
}

function mascararIp(string $ip): string {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $partes = explode('.', $ip);
        return $partes[0] . '.' . $partes[1] . '.***.***';
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return substr($ip, 0, 9) . ':****:****';
    }
    return '***';
}

function mascararCim(string $cim): string {
    $len = strlen($cim);
    if ($len === 0) return '-';
    if ($len <= 3) return str_repeat('*', $len);
    return substr($cim, 0, 2) . str_repeat('*', $len - 4) . substr($cim, -2);
}

// ==========================================
// PROCESSAMENTO DA CONSULTA
// ==========================================
$codigo = trim((string)($_GET['codigo'] ?? $_POST['codigo'] ?? ''));
$codigo = preg_replace('/[^a-fA-F0-9]/', '', $codigo);

$resultado = null;
$origemTabela = null; // 'membro' ou 'visitante'
$statusValidacao = null; // 'valido', 'adulterado', 'nao_encontrado'
$hashRecalculado = '';

if ($codigo !== '') {
    // 1. Busca em chancelaria_presencas (obreiros da Loja)
    $sqlMembro = "
        SELECT
            p.id,
            p.tenant_id,
            p.sessao_id,
            p.membro_id,
            p.status_presenca,
            p.codigo_assinatura,
            p.hash_assinatura,
            p.assinado_em_utc,
            p.ip_origem,
            p.user_agent,
            p.termo_aceite,
            p.nivel_assinatura,
            s.titulo AS sessao_titulo,
            s.data_sessao AS sessao_data,
            s.hora_sessao AS sessao_hora,
            m.nome,
            m.cim,
            m.grau
        FROM chancelaria_presencas p
        INNER JOIN chancelaria_sessoes s ON s.id = p.sessao_id
        INNER JOIN chancelaria_membros m ON m.id = p.membro_id AND m.tenant_id = p.tenant_id
        WHERE p.codigo_assinatura = ?
        LIMIT 1
    ";
    $stmtM = $pdo_read->prepare($sqlMembro);
    $stmtM->execute([$codigo]);
    $rowMembro = $stmtM->fetch(PDO::FETCH_ASSOC);

    if ($rowMembro) {
        $resultado = $rowMembro;
        $origemTabela = 'membro';
    } else {
        // 2. Busca em chancelaria_visitantes (irmãos visitantes)
        $sqlVisitante = "
            SELECT
                v.id,
                v.tenant_id,
                v.sessao_id,
                v.nome,
                v.cim,
                v.grau,
                v.loja_origem,
                v.oriente,
                v.potencia,
                v.codigo_assinatura,
                v.hash_assinatura,
                v.assinado_em_utc,
                v.ip_origem,
                v.user_agent,
                s.titulo AS sessao_titulo,
                s.data_sessao AS sessao_data,
                s.hora_sessao AS sessao_hora
            FROM chancelaria_visitantes v
            INNER JOIN chancelaria_sessoes s ON s.id = v.sessao_id
            WHERE v.codigo_assinatura = ?
            LIMIT 1
        ";
        $stmtV = $pdo_read->prepare($sqlVisitante);
        $stmtV->execute([$codigo]);
        $rowVisitante = $stmtV->fetch(PDO::FETCH_ASSOC);

        if ($rowVisitante) {
            $resultado = $rowVisitante;
            $origemTabela = 'visitante';
        }
    }

    if ($resultado && $origemTabela) {
        $hashRecalculado = recalcularHashAssinatura($resultado, $origemTabela);

        if (!empty($resultado['hash_assinatura']) && hash_equals((string)$resultado['hash_assinatura'], $hashRecalculado)) {
            $statusValidacao = 'valido';
        } else {
            $statusValidacao = 'adulterado';
        }
    } else {
        $statusValidacao = 'nao_encontrado';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validador de Assinatura Eletrônica - Chancelaria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { background-color: #141724; color: #e2e8f0; font-family: 'Inter', sans-serif; padding: 20px; }
        .card-custom { background-color: #1d2132; border: 1px solid #333951; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        .btn-gold { background-color: #f5c041; color: #141724; font-weight: 600; }
        .btn-gold:hover { background-color: #dca732; color: #141724; }
        .form-control { background-color: #141724; border: 1px solid #333951; color: #fff; font-family: monospace; }
        .form-control:focus { background-color: #141724; border-color: #f5c041; color: #fff; box-shadow: none; }
        .hash-box { background-color: #141724; border: 1px solid #333951; border-radius: 6px; padding: 10px; font-family: monospace; font-size: 0.78rem; word-break: break-all; color: #cbd5e1; }
        .table-dark-custom { --bs-table-bg: transparent; --bs-table-border-color: #333951; color: #e2e8f0; }
    </style>
</head>
<body>

<div class="container" style="max-width: 680px; margin-top: 25px;">
    <div class="text-center mb-4">
        <i class="fas fa-shield-alt text-warning mb-2" style="font-size: 2.6rem;"></i>
        <h3 style="font-family: 'Cinzel', serif; color: #f5c041;">Validador de Assinatura Eletrônica</h3>
        <p class="text-secondary small mb-0">Conferência Pública de Autenticidade e Integridade (Lei nº 14.063/2020)</p>
    </div>

    <!-- FORMULÁRIO DE CONSULTA -->
    <div class="card-custom mb-4">
        <form method="GET" action="">
            <label for="codigo" class="form-label text-white small">Código de Verificação da Assinatura (32 caracteres)</label>
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary text-warning"><i class="fas fa-fingerprint"></i></span>
                <input type="text" id="codigo" name="codigo" maxlength="64" class="form-control" value="<?= htmlspecialchars($codigo) ?>" placeholder="Ex: 9f86d081884c7d659a2feaa0c55ad015" required>
                <button type="submit" class="btn btn-gold px-4">Verificar</button>
            </div>
        </form>
    </div>

    <?php if ($statusValidacao === 'nao_encontrado'): ?>
        <div class="alert alert-danger border-0 shadow-sm text-center p-4">
            <i class="fas fa-times-circle fa-2x mb-2"></i>
            <h5 class="fw-bold mb-1">Registro Não Localizado</h5>
            <p class="small mb-0">Nenhum registro de membro ou visitante corresponde ao código informado.</p>
        </div>

    <?php elseif ($statusValidacao === 'adulterado'): ?>
        <div class="alert alert-danger border-0 shadow-sm p-4">
            <div class="text-center mb-3">
                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                <h5 class="fw-bold mb-1">Falha na Integridade Criptográfica!</h5>
                <p class="small mb-0">O hash recalculado não confere com o registro original armazenado. Este dado pode ter sido alterado após a gravação.</p>
            </div>
            <div class="small">
                <div class="mb-1"><strong>Hash Armazenado:</strong></div>
                <div class="hash-box text-danger mb-2"><?= htmlspecialchars((string)$resultado['hash_assinatura']) ?></div>
                <div class="mb-1"><strong>Hash Recalculado:</strong></div>
                <div class="hash-box text-warning"><?= htmlspecialchars($hashRecalculado) ?></div>
            </div>
        </div>

    <?php elseif ($statusValidacao === 'valido' && $resultado): ?>
        <?php
            $isMembro = ($origemTabela === 'membro');
            $dataFormatada = !empty($resultado['sessao_data']) ? date('d/m/Y', strtotime((string)$resultado['sessao_data'])) : '-';
            $horaFormatada = !empty($resultado['sessao_hora']) ? substr((string)$resultado['sessao_hora'], 0, 5) : '-';
        ?>
        <div class="card-custom border-success">
            <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
                <i class="fas fa-check-circle fa-2x me-3"></i>
                <div>
                    <h5 class="alert-heading fw-bold mb-0">Registro Válido e Íntegro</h5>
                    <small>O selo criptográfico HMAC-SHA256 foi verificado com sucesso e os dados originais permanecem inalterados.</small>
                </div>
            </div>

            <h6 class="text-warning text-uppercase small fw-bold mb-3">Dados do Participante e da Sessão</h6>
            <table class="table table-sm table-dark-custom mb-4">
                <tbody>
                    <tr>
                        <th style="width: 38%;" class="text-secondary fw-normal">Nome:</th>
                        <td class="fw-semibold text-white"><?= htmlspecialchars((string)($resultado['nome'] ?? '')) ?></td>
                    </tr>
                    <tr>
                        <th class="text-secondary fw-normal">Categoria:</th>
                        <td><?= $isMembro ? 'Membro do Quadro (chancelaria_presencas)' : 'Irmão Visitante (chancelaria_visitantes)' ?></td>
                    </tr>
                    <tr>
                        <th class="text-secondary fw-normal">CIM:</th>
                        <td><?= htmlspecialchars(mascararCim((string)($resultado['cim'] ?? ''))) ?></td>
                    </tr>
                    <?php if (!$isMembro): ?>
                    <tr>
                        <th class="text-secondary fw-normal">Loja / Oriente:</th>
                        <td>
                            <?= htmlspecialchars((string)($resultado['loja_origem'] ?? '-')) ?>
                            <?php if (!empty($resultado['oriente'])): ?> — <?= htmlspecialchars((string)$resultado['oriente']) ?><?php endif; ?>
                            <?php if (!empty($resultado['potencia'])): ?> (<?= htmlspecialchars((string)$resultado['potencia']) ?>)<?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th class="text-secondary fw-normal">Sessão:</th>
                        <td><?= htmlspecialchars((string)($resultado['sessao_titulo'] ?? '')) ?></td>
                    </tr>
                    <tr>
                        <th class="text-secondary fw-normal">Data / Hora da Sessão:</th>
                        <td><?= htmlspecialchars($dataFormatada) ?> às <?= htmlspecialchars($horaFormatada) ?></td>
                    </tr>
                </tbody>
            </table>

            <h6 class="text-warning text-uppercase small fw-bold mb-3">Metadados Técnicos de Auditoria</h6>
            <table class="table table-sm table-dark-custom mb-3">
                <tbody>
                    <tr>
                        <th style="width: 38%;" class="text-secondary fw-normal">Carimbo de Tempo (UTC):</th>
                        <td class="font-monospace"><?= htmlspecialchars((string)($resultado['assinado_em_utc'] ?? '-')) ?></td>
                    </tr>
                    <tr>
                        <th class="text-secondary fw-normal">IP de Origem (Parcial):</th>
                        <td class="font-monospace"><?= htmlspecialchars(mascararIp((string)($resultado['ip_origem'] ?? ''))) ?></td>
                    </tr>
                    <?php if ($isMembro): ?>
                    <tr>
                        <th class="text-secondary fw-normal">Nível Legal:</th>
                        <td><span class="badge bg-warning text-dark"><?= htmlspecialchars((string)($resultado['nivel_assinatura'] ?? 'AVANCADA')) ?></span></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="mb-2">
                <span class="small text-secondary d-block mb-1">Código de Verificação:</span>
                <div class="hash-box text-warning"><?= htmlspecialchars((string)$resultado['codigo_assinatura']) ?></div>
            </div>

            <div>
                <span class="small text-secondary d-block mb-1">Hash Criptográfico (HMAC-SHA256):</span>
                <div class="hash-box text-success"><?= htmlspecialchars((string)$resultado['hash_assinatura']) ?></div>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
