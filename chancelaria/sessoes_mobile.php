<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
session_start();
require_once '../configuracoes/config.php';

date_default_timezone_set('America/Sao_Paulo');

// 1. Garante que o usuário está logado
if (!isset($_SESSION['chanceler_logado']) || empty($_SESSION['tenant_id'])) {
    header('Location: mobile.php');
    exit;
}

// 2. Captura o ID do tenant/usuário logado na sessão atual
$tenant_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['user']['id']; 

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$tenant_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$tenant_id_logado = !empty($user['dono_id']) ? $user['dono_id'] : $tenant_id;

// 3. Processa o Cadastro da Sessão caso venha via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['titulo'])) {
    $titulo = $_POST['titulo'] ?? '';
    $data_sessao = $_POST['data_sessao'] ?? date('Y-m-d');
    $hora_sessao = $_POST['hora_sessao'] ?? date('H:i');
    $grau = (int)($_POST['grau'] ?? 1);
    $tipo = $_POST['tipo'] ?? 'Ordinária';
    $status = $_POST['status'] ?? 'Agendada';
    $valor_arrecadado = !empty($_POST['valor_arrecadado']) ? str_replace(',', '.', $_POST['valor_arrecadado']) : 0.00;
    
    // Gera o token de presença único para o QR Code
    $token_presenca = bin2hex(random_bytes(16));

    try {
        $stmtInsert = $pdo->prepare("
            INSERT INTO chancelaria_sessoes (tenant_id, titulo, data_sessao, hora_sessao, grau, tipo, status, valor, token_presenca) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtInsert->execute([$tenant_id_logado, $titulo, $data_sessao, $hora_sessao, $grau, $tipo, $status, $valor_arrecadado, $token_presenca]);
        
        // Redireciona para evitar reenvio do formulário e atualizar a lista
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (PDOException $e) {
        $erro_cadastro = "Erro ao salvar sessão: " . $e->getMessage();
    }
}

// 4. Consulta estritamente filtrada pelo tenant logado
$stmtSessoes = $pdo->prepare("
    SELECT * FROM chancelaria_sessoes 
    WHERE tenant_id = ? AND status = 'Agendada'
    AND data_sessao >= date_trunc('month', CURRENT_DATE)
    AND data_sessao < date_trunc('month', CURRENT_DATE) + INTERVAL '1 month'
    ORDER BY data_sessao ASC
");
$stmtSessoes->execute([$tenant_id_logado]);
$sessoes = $stmtSessoes->fetchAll(PDO::FETCH_ASSOC);

// Sessão selecionada para ver detalhes
$sessao_ativa = null;
$presencas_membros = [];
$presencas_visitantes = [];

if (isset($_GET['sessao_id'])) {
    $sessao_id = $_GET['sessao_id'];
    
    // Pega dados da sessão escolhida
    $stmtS = $pdo->prepare("SELECT * FROM chancelaria_sessoes WHERE id = ?");
    $stmtS->execute([$sessao_id]);
    $sessao_ativa = $stmtS->fetch();

    if ($sessao_ativa) {
        // Membros presentes
        $stmtM = $pdo->prepare("
            SELECT m.nome, m.cim, m.grau, p.created_at 
            FROM chancelaria_presencas p 
            JOIN chancelaria_membros m ON p.membro_id = m.id 
            WHERE p.sessao_id = ?
        ");
        $stmtM->execute([$sessao_id]);
        $presencas_membros = $stmtM->fetchAll(PDO::FETCH_ASSOC);

        // Visitantes presentes
        $stmtV = $pdo->prepare("
            SELECT nome, cim, loja_origem, potencia, grau, telefone, email, created_at 
            FROM chancelaria_visitantes 
            WHERE sessao_id = ?
        ");
        $stmtV->execute([$sessao_id]);
        $presencas_visitantes = $stmtV->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Chanceler - Gestão Integrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Biblioteca para gerar QR Code direto na tela via JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root { --bg-main: #141724; --bg-card: #1d2132; --text-main: #e2e8f0; --gold: #f5c041; --border-color: #333951; }
        body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Inter', sans-serif; padding: 15px; }
        .card-custom { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; margin-bottom: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        .btn-gold { background-color: var(--gold); color: #141724; font-weight: 600; }
        .btn-gold:hover { background-color: #dca732; color: #141724; }
        .badge-custom { background-color: var(--border-color); color: var(--gold); }
        .form-select, .form-control { background-color: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); }
        .form-select:focus, .form-control:focus { border-color: var(--gold); box-shadow: 0 0 0 0.25rem rgba(245, 192, 65, 0.25); color: var(--text-main); background-color: var(--bg-main); }
        table { color: var(--text-main) !important; font-size: 0.9rem; }
        #relatorio-impressao { display: none; }
        @media print {
            body > .container { display: none !important; }
            #relatorio-impressao { display: block !important; color: #000; background: #fff; padding: 24px; }
            #relatorio-impressao table { width: 100%; border-collapse: collapse; color: #000 !important; }
            #relatorio-impressao th, #relatorio-impressao td { border: 1px solid #999; padding: 8px; text-align: left; }
            #relatorio-impressao h1 { font-size: 22px; margin: 0 0 8px; }
            #relatorio-impressao p { margin: 4px 0; }
        }
    </style>
</head>
<body>

<div class="container" style="max-width: 600px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 style="font-family: 'Cinzel', serif; color: var(--gold);" class="mb-0">Painel do Chanceler</h4>
            <small class="text-white">Controle de Frequência Mobile</small>
        </div>
        <a href="mobile.php?logout=1" class="btn btn-outline-danger btn-sm"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>

    <?php if (isset($erro_cadastro)): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($erro_cadastro) ?></div>
    <?php endif; ?>

    <?php if (!$sessao_ativa): ?>
        <!-- LISTA DE SESSÕES -->
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="text-warning mb-0"><i class="fas fa-calendar-alt me-2"></i>Selecione a Sessão</h5>
                <!-- Botão que abre o Modal de Cadastro -->
                <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#modalSessao">
                    <i class="fas fa-plus"></i> Nova Sessão
                </button>
            </div>

            <div class="list-group list-group-flush bg-transparent">
                <?php foreach ($sessoes as $s): ?>
                    <a href="?sessao_id=<?= $s['id'] ?>" class="list-group-item list-group-item-action bg-transparent text-white border-secondary d-flex justify-content-between align-items-center py-3">
                        <div>
                            <strong><?= htmlspecialchars($s['titulo'] ?? 'Reunião Ordinária') ?></strong>
                            <br><small class="text-white"><?= date('d/m/Y', strtotime($s['data_sessao'] ?? 'now')) ?> | Grau <?= $s['grau'] ?></small>
                            <br><small class="text-warning"><?= htmlspecialchars($s['tipo']) ?> - <?= htmlspecialchars($s['status']) ?></small>
                        </div>
                        <i class="fas fa-chevron-right text-warning"></i>
                    </a>
                <?php endforeach; ?>
                
                <?php if (empty($sessoes)): ?>
                    <div class="text-center py-4">
                        <p class="text-white mb-3">Nenhuma sessão encontrada para este mês.</p>
                        <!--<button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#modalSessao">
                            <i class="fas fa-plus-circle me-1"></i> Cadastrar Nova Sessão
                        </button>
                        -->
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- DETALHES DA SESSÃO SELECIONADA -->
        <div class="d-flex justify-content-between mb-3">
            <a href="?" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Voltar às Sessões
            </a>

            <button class="btn btn-outline-light" onclick="location.reload()">
                <i class="fas fa-sync-alt me-2"></i> Atualizar
            </button>
        </div>

        <div class="card-custom text-center">
            <h5 class="text-warning mb-1"><?= htmlspecialchars($sessao_ativa['titulo'] ?? 'Reunião') ?> </h5>
            <p class="text-white small mb-1">Data: <?= date('d/m/Y', strtotime($sessao_ativa['data_sessao'] ?? 'now')) ?></p>
            <p class="text-white small mb-2">Hora: <?= date('H:i', strtotime($sessao_ativa['hora_sessao'] ?? 'now')) ?></p>
            
            <!-- QR CODE DA SESSÃO -->
            <div class="bg-white p-3 rounded d-inline-block mb-3">
                <div id="qrcode"></div>
            </div>
            <p class="small text-white mb-0">Peça para o irmão escanear para assinar a frequência.</p>
            <div class="mt-2">
                <a href="checkin.php?token=<?= $sessao_ativa['token_presenca'] ?>" target="_blank" class="btn btn-sm btn-outline-warning">Abrir Link de Check-in</a>
            </div>
        </div>

        <!-- LISTA DE PRESENÇAS REGISTRADAS -->
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                <h5 class="text-warning mb-0"><i class="fas fa-users me-2"></i>Presenças Registradas (<?= count($presencas_membros) + count($presencas_visitantes)  ?>)</h5>
                <button type="button" class="btn btn-sm btn-outline-warning" onclick="imprimirRelatorioPresenca()">
                    <i class="fas fa-print me-1"></i> Imprimir relatório
                </button>
            </div>
            
            <ul class="nav nav-pills nav-fill mb-3" id="presencaTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active btn-sm" id="membros-tab" data-bs-toggle="pill" data-bs-target="#membros" type="button" role="tab">Membros (<?= count($presencas_membros) ?>)</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link btn-sm" id="visitantes-tab" data-bs-toggle="pill" data-bs-target="#visitantes" type="button" role="tab">Visitantes (<?= count($presencas_visitantes) ?>)</button>
                </li>
            </ul>

            <div class="tab-content" id="presencaTabContent">
                <!-- TAB MEMBROS -->
                <div class="tab-pane fade show active" id="membros" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-dark table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Irmão</th>
                                    <th>CIM</th>
                                    <th>Horário</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($presencas_membros as $m): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($m['nome']) ?></td>
                                        <td><span class="badge badge-custom"><?= htmlspecialchars($m['cim']) ?></span></td>
                                        <td><small class="text-white"><?= date('H:i', strtotime($m['created_at'] ?? 'now')) ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($presencas_membros)): ?>
                                    <tr><td colspan="3" class="text-center text-white py-3">Nenhum membro presente ainda.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB VISITANTES -->
                <div class="tab-pane fade" id="visitantes" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-dark table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Visitante / Loja</th>
                                    <th>Contato</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($presencas_visitantes as $v): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($v['nome']) ?></strong><br>
                                            <small class="text-warning"><?= htmlspecialchars($v['loja_origem']) ?> (<?= htmlspecialchars($v['potencia']) ?>)</small>
                                        </td>
                                        <td>
                                            <small class="text-white">
                                                <i class="fas fa-phone"></i> <?= htmlspecialchars($v['telefone'] ?? 'Não inf.') ?><br>
                                                <i class="fas fa-envelope"></i> <?= htmlspecialchars($v['email'] ?? 'Não inf.') ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($presencas_visitantes)): ?>
                                    <tr><td colspan="2" class="text-center text-white py-3">Nenhum visitante presente ainda.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div id="relatorio-impressao"></div>

<!-- MODAL DE CADASTRO DE NOVA SESSÃO -->
<div class="modal fade" id="modalSessao" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="background-color: var(--bg-card); color: var(--text-main); border: 1px solid var(--border-color);">
            <form method="POST" action="">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title text-warning" id="modalTitulo" style="font-family: 'Cinzel', serif;">Nova Sessão</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Título da Sessão</label>
                        <input type="text" class="form-control" name="titulo" required placeholder="Ex: Iniciação de Aprendiz / Sessão Magna">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Data da Sessão</label>
                            <input type="date" class="form-control" name="data_sessao" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Data da Sessão</label>
                            <input type="time" class="form-control" name="hora_sessao" value="20:00" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Grau</label>
                            <select class="form-select" name="grau">
                                <option value="1">1º Grau (Aprendiz)</option>
                                <option value="2">2º Grau (Companheiro)</option>
                                <option value="3">3º Grau (Mestre)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Tipo</label>
                            <select class="form-select" name="tipo">
                                <option value="Administrativa">Administrativa</option>
                                <option value="Especial">Especial</option>
                                <option value="Magna de Iniciação">Magna de Iniciação</option>
                                <option value="Magna de Elevação">Magna de Elevação</option>
                                <option value="Magna de Exaltação">Magna de Exaltação</option>
                                <option value="Ordinária" selected>Ordinária</option>
                                <option value="Pompas Fúnebres">Pompas Fúnebres</option>
                            </select>
                        </div>
                        
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Status</label>
                            <select class="form-select" name="status">
                                <option value="Realizada">Realizada</option>
                                <option value="Agendada" selected>Agendada</option>
                                <option value="Cancelada">Cancelada</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Tronco de Beneficência</label>
                            <div class="input-group">
                                <span class="input-group-text bg-secondary text-light border-secondary">R$</span>
                                <input type="number" step="0.01" min="0.00" class="form-control" name="valor_arrecadado" placeholder="0,00">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Salvar Sessão</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    <?php if ($sessao_ativa): ?>
    function imprimirRelatorioPresenca() {
        const abaAtiva = document.querySelector('#presencaTab .nav-link.active');
        const tipo = abaAtiva?.id === 'visitantes-tab' ? 'Visitantes' : 'Membros';
        const tabelaAtiva = document.querySelector(tipo === 'Visitantes' ? '#visitantes table' : '#membros table');
        const relatorio = document.getElementById('relatorio-impressao');

        if (!tabelaAtiva || !relatorio) {
            return;
        }

        relatorio.innerHTML = `
            <h1>Relatório de Presença - ${tipo}</h1>
            <p><strong>Sessão:</strong> <?= json_encode($sessao_ativa['titulo'] ?? 'Reunião', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></p>
            <p><strong>Data:</strong> <?= date('d/m/Y', strtotime($sessao_ativa['data_sessao'] ?? 'now')) ?>
                <strong>Hora:</strong> <?= date('H:i', strtotime($sessao_ativa['hora_sessao'] ?? 'now')) ?></p>
            <br>
            ${tabelaAtiva.outerHTML}
        `;

        window.print();
    }

    // Gera o QR Code automaticamente com o link absoluto de check-in
    const linkCheckin = window.location.origin + "/chancelaria/checkin.php?token=<?= $sessao_ativa['token_presenca'] ?>";
    new QRCode(document.getElementById("qrcode"), {
        text: linkCheckin,
        width: 180,
        height: 180,
        colorDark : "#141724",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });
    <?php endif; ?>
</script>
</body>
</html>
