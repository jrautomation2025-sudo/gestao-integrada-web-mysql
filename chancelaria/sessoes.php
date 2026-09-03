<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../configuracoes/config.php';

if (!isset($_SESSION['tenant_id'])) {
    die("Acesso negado. Redirecionando para o login...");
}
$tenant_id = $_SESSION['tenant_id'];

$mensagem = $_SESSION['mensagem'] ?? '';
$erro = $_SESSION['erro'] ?? '';
unset($_SESSION['mensagem'], $_SESSION['erro']);

// Processamento de Inserção / Edição
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['titulo'])) {
    $id = $_POST['id'] ?? null;
    $titulo = trim($_POST['titulo'] ?? '');
    $data_sessao = $_POST['data_sessao'] ?? '';
    $hora_sessao = $_POST['hora_sessao'] ?? '';
    $tipo = $_POST['tipo'] ?? 'Ordinária';
    $grau = $_POST['grau'] ?? '1';
    $status = $_POST['status'] ?? 'Realizada';
    $valor = $_POST['valor_arrecadado'] ?? 0.0;
    $token_presenca = bin2hex(random_bytes(16));

    if (!empty($titulo) && !empty($data_sessao)) {
        try {
            if (!empty($id)) {
                // Atualizar
                $stmt = $pdo->prepare("UPDATE chancelaria_sessoes SET titulo = ?, data_sessao = ?, hora_sessao = ?, tipo = ?, grau = ?, status = ?, valor = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$titulo, $data_sessao, $hora_sessao, $tipo, $grau, $status, $valor, $id, $tenant_id]);
                $_SESSION['mensagem'] = "Sessão atualizada com sucesso!";
            } else {
                // Inserir
                $stmt = $pdo->prepare("INSERT INTO chancelaria_sessoes (tenant_id, titulo, data_sessao, hora_sessao, tipo, grau, status, valor, token_presenca) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$tenant_id, $titulo, $data_sessao, $hora_sessao, $tipo, $grau, $status, $valor, $token_presenca]);
                $_SESSION['mensagem'] = "Sessão cadastrada com sucesso!";
            }
        } catch (PDOException $e) {
            $_SESSION['erro'] = "Erro ao salvar no banco de dados: " . $e->getMessage();
        }
    } else {
        $_SESSION['erro'] = "Preencha os campos obrigatórios.";
    }

    header("Location: sessoes");
    exit;
}

// Exclusão de Sessão
if (isset($_GET['excluir'])) {
    $idExcluir = $_GET['excluir'];
    try {
        $stmt = $pdo->prepare("DELETE FROM chancelaria_sessoes WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$idExcluir, $tenant_id]);
        $_SESSION['mensagem'] = "Sessão excluída com sucesso!";
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao excluir sessão. Verifique se há presenças vinculadas.";
    }
    header("Location: sessoes");
    exit;
}

// Captura o Mês e o Ano do Filtro (se não enviados, assume o mês e ano atuais)
$filtro_mes = isset($_GET['mes']) && $_GET['mes'] !== '' ? $_GET['mes'] : date('m');
$filtro_ano = isset($_GET['ano']) && $_GET['ano'] !== '' ? $_GET['ano'] : date('Y');

// Busca as sessões cadastradas aplicando o filtro de Mês e Ano
$sessoes = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM chancelaria_sessoes 
        WHERE tenant_id = ? 
        AND MONTH(data_sessao) = ? 
        AND YEAR(data_sessao) = ? 
        ORDER BY data_sessao ASC
    ");
    $stmt->execute([$tenant_id, $filtro_mes, $filtro_ano]);
    $sessoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $erro = "Erro ao buscar sessões: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Sessões - Gestão Integrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root { --bg-main: #141724; --bg-card: #222738; --text-main: #e2e8f0; --gold: #f5c041; --border-color: #333951; }
        body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 260px; padding: 30px 40px; width: calc(100% - 260px); }
        
        .card-custom { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; padding: 25px; }
        .btn-gold { background-color: var(--gold); color: #000; font-weight: 600; border: none; }
        .btn-gold:hover { background-color: #dca732; }
        
        .form-control, .form-select { background-color: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); }
        .form-control:focus, .form-select:focus { border-color: var(--gold); box-shadow: 0 0 0 0.25rem rgba(245, 192, 65, 0.25); color: var(--text-main); background-color: var(--bg-main); }

        .table-dark-custom { color: var(--text-main); vertical-align: middle; }
        .table-dark-custom thead th { background-color: rgba(0,0,0,0.2); color: var(--gold); border-bottom: 2px solid var(--border-color); font-weight: 600; text-transform: uppercase; font-size: 0.85rem; }
        .table-dark-custom tbody td { border-bottom: 1px solid var(--border-color); padding: 15px 10px; background: transparent; color: #e2e8f0 !important; }
    </style>
</head>
<body>
    <!-- Barra Superior Mobile -->
<div class="mobile-topbar">
    <div class="d-flex align-items-center">
        <button class="btn btn-outline-warning btn-sm me-3" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <span style="font-family: 'Cinzel', serif; color: var(--gold); font-weight: bold;">CHANCELARIA</span>
    </div>
    <span class="text-white small">Painel</span>
</div>

<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>
    
    <?php include 'menu.php'?>

    <main class="main-content">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;"><i class="fas fa-calendar-alt me-2 text-warning"></i> Gestão de Sessões</h2>
                <p class="text-warning mb-0">Cadastre, acompanhe e altere o status das sessões da Oficina.</p>
            </div>
            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalSessao" onclick="limparFormulario()" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                <i class="fas fa-plus me-2"></i> Nova Sessão
            </button>
        </div>

        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($mensagem) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($erro)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($erro) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- FILTRO POR MÊS E ANO -->
        <div class="card-custom mb-4">
            <form method="GET" action="sessoes" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold text-warning">Mês</label>
                    <select name="mes" class="form-select">
                        <option value="1" <?= $filtro_mes == '1' ? 'selected' : '' ?>>Janeiro</option>
                        <option value="2" <?= $filtro_mes == '2' ? 'selected' : '' ?>>Fevereiro</option>
                        <option value="3" <?= $filtro_mes == '3' ? 'selected' : '' ?>>Março</option>
                        <option value="4" <?= $filtro_mes == '4' ? 'selected' : '' ?>>Abril</option>
                        <option value="5" <?= $filtro_mes == '5' ? 'selected' : '' ?>>Maio</option>
                        <option value="6" <?= $filtro_mes == '6' ? 'selected' : '' ?>>Junho</option>
                        <option value="7" <?= $filtro_mes == '7' ? 'selected' : '' ?>>Julho</option>
                        <option value="8" <?= $filtro_mes == '8' ? 'selected' : '' ?>>Agosto</option>
                        <option value="9" <?= $filtro_mes == '9' ? 'selected' : '' ?>>Setembro</option>
                        <option value="10" <?= $filtro_mes == '10' ? 'selected' : '' ?>>Outubro</option>
                        <option value="11" <?= $filtro_mes == '11' ? 'selected' : '' ?>>Novembro</option>
                        <option value="12" <?= $filtro_mes == '12' ? 'selected' : '' ?>>Dezembro</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold text-warning">Ano</label>
                    <select name="ano" class="form-select">
                        <?php 
                        $anoAtual = date('Y');
                        for ($a = $anoAtual - 2; $a <= $anoAtual + 2; $a++): 
                        ?>
                            <option value="<?= $a ?>" <?= $filtro_ano == $a ? 'selected' : '' ?>><?= $a ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-warning w-100">
                        <i class="fas fa-filter me-1"></i> Filtrar
                    </button>
                    <a href="sessoes" class="btn btn-outline-secondary w-100 text-light">Limpar</a>
                </div>
            </form>
        </div>

        <!-- Tabela de Sessões -->
        <div class="card-custom">
            <div class="table-responsive">
                <table class="table table-dark-custom">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Horário</th>
                            <th>Título da Sessão</th>
                            <th>Tipo</th>
                            <th>Grau</th>
                            <th>Status</th>
                            <th>Tronco</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($sessoes) > 0): ?>
                            <?php foreach ($sessoes as $s): ?>
                                <?php 
                                    $statusClass = 'bg-secondary';
                                    $statusVal = $s['status'] ?? 'Realizada';
                                    if ($statusVal === 'Realizada') $statusClass = 'bg-success';
                                    elseif ($statusVal === 'Agendada') $statusClass = 'bg-warning text-dark';
                                    elseif ($statusVal === 'Cancelada') $statusClass = 'bg-danger';
                                ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($s['data_sessao'])) ?></td>
                                    <td><?= date('H:i', strtotime($s['hora_sessao'] ?? '00:00')) ?></td>
                                    <td class="fw-bold text-white"><?= htmlspecialchars($s['titulo']) ?></td>
                                    <td><span class="badge bg-dark border border-secondary"><?= htmlspecialchars($s['tipo']) ?></span></td>
                                    <td><?= htmlspecialchars($s['grau']) ?>° Grau</td>
                                    <td>
                                        <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($statusVal) ?></span>
                                    </td>
                                    <td class="fw-bold text-white">R$ <?php echo number_format($s['valor'], 2, ',', '.'); ?></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-info me-1" onclick="abrirQrCode('<?= $s['token_presenca'] ?>', '<?= htmlspecialchars($s['titulo'], ENT_QUOTES) ?>')" title="Gerar QR Code de Frequência" <?= ($s['status'] == 'Realizada' || $s['status'] == 'Cancelada') ? 'disabled' : '' ?> <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                                            <i class="fas fa-qrcode"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning me-1" onclick="editarSessao(<?= htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8') ?>)" title="Editar" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="sessoes?excluir=<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Deseja realmente excluir esta sessão?')" title="Excluir" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-light">Nenhuma sessão encontrada para este período.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal Cadastro / Edição -->
<div class="modal fade" id="modalSessao" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="background-color: var(--bg-card); color: var(--text-main); border: 1px solid var(--border-color);">
            <form method="POST" action="sessoes">
                <input type="hidden" name="id" id="sessao_id">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title text-warning" id="modalTitulo" style="font-family: 'Cinzel', serif;">Nova Sessão</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Título da Sessão</label>
                        <input type="text" class="form-control" name="titulo" id="titulo" required placeholder="Ex: Iniciação de Aprendiz / Sessão Magna">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Data da Sessão</label>
                            <input type="date" class="form-control" name="data_sessao" id="data_sessao" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Horário da Sessão</label>
                            <input type="time" class="form-control" name="hora_sessao" id="hora_sessao" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Grau</label>
                            <select class="form-select" name="grau" id="grau">
                                <option value="1">1º Grau (Aprendiz)</option>
                                <option value="2">2º Grau (Companheiro)</option>
                                <option value="3">3º Grau (Mestre)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Tipo</label>
                            <select class="form-select" name="tipo" id="tipo">
                                <option value="Administrativa">Administrativa</option>
                                <option value="Especial">Especial</option>
                                <option value="Magna de Iniciação">Magna de Iniciação</option>
                                <option value="Magna de Elevação">Magna de Elevação</option>
                                <option value="Magna de Exaltação">Magna de Exaltação</option>
                                <option value="Ordinária">Ordinária</option>
                                <option value="Pompas Fúnebres">Pompas Fúnebres</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Status</label>
                            <select class="form-select" name="status" id="status">
                                <option value="Realizada">Realizada</option>
                                <option value="Agendada">Agendada</option>
                                <option value="Cancelada">Cancelada</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Tronco de Beneficência</label>
                            <div class="input-group">
                                <span class="input-group-text bg-secondary text-light border-secondary">R$</span>
                                <input type="number" step="0.01" min="0.00" class="form-control" name="valor_arrecadado" id="valor_arrecadado" placeholder="0,00">
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
        function limparFormulario() {
            document.getElementById('sessao_id').value = '';
            document.getElementById('titulo').value = '';
            document.getElementById('data_sessao').value = '';
            document.getElementById('hora_sessao').value = '';
            document.getElementById('grau').value = '1';
            document.getElementById('tipo').value = 'Ordinária';
            document.getElementById('status').value = 'Realizada';
            document.getElementById('valor_arrecadado').value = 0.0;
            document.getElementById('modalTitulo').innerText = 'Nova Sessão';
        }

        function editarSessao(sessao) {
            document.getElementById('sessao_id').value = sessao.id;
            document.getElementById('titulo').value = sessao.titulo;
            document.getElementById('data_sessao').value = sessao.data_sessao;
            document.getElementById('hora_sessao').value = sessao.hora_sessao;
            document.getElementById('grau').value = sessao.grau;
            document.getElementById('tipo').value = sessao.tipo;
            document.getElementById('status').value = sessao.status || 'Realizada';
            document.getElementById('valor_arrecadado').value = sessao.valor;
            document.getElementById('modalTitulo').innerText = 'Editar Sessão';
            
            var modal = new bootstrap.Modal(document.getElementById('modalSessao'));
            modal.show();
        }
        
    function toggleMobileMenu() {
        const sidebar = document.querySelector('.sidebar'); 
        const backdrop = document.getElementById('sidebarBackdrop');
        
        if (sidebar) {
            sidebar.classList.toggle('show');
        }
        if (backdrop) {
            backdrop.classList.toggle('show');
        }
    }
    </script>
    
<!-- Modal do QR Code -->
<div class="modal fade" id="modalQrCode" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background-color: #1d2132; color: #e2e8f0; border: 1px solid #333951;">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title" style="font-family: 'Cinzel', serif; color: #f5c041;">
                    <i class="fas fa-qrcode me-2"></i> Check-in da Sessão
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <p id="modalSessaoTitulo" class="text-white mb-3"></p>
                
                <div id="qrcodeContainer" class="d-flex justify-content-center bg-white p-3 rounded mx-auto" style="width: fit-content;"></div>
                
                <p class="text-white small mt-3 mb-0">Aponte a câmera do celular para registrar a frequência.</p>
            </div>
            <div class="modal-footer border-top border-secondary">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
let qrCodeInstance = null;

function abrirQrCode(token, tituloSessao) {
    document.getElementById('modalSessaoTitulo').innerText = tituloSessao;
    
    const container = document.getElementById('qrcodeContainer');
    container.innerHTML = "";

    const urlCheckin = window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/') + 'checkin.php?token=' + token;

    qrCodeInstance = new QRCode(container, {
        text: urlCheckin,
        width: 200,
        height: 200,
        colorDark : "#000000",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });

    const modal = new bootstrap.Modal(document.getElementById('modalQrCode'));
    modal.show();
}
</script>
</body>
</html>
