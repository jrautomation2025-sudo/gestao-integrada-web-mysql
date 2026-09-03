<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../configuracoes/config.php';

if (!isset($_SESSION['tenant_id']) && !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['user']['id'];

$mensagem = $_SESSION['mensagem'] ?? '';
$erro = $_SESSION['erro'] ?? '';
unset($_SESSION['mensagem'], $_SESSION['erro']);

// Busca os dados da Loja associada ao dono da conta (tenant_id) para o Papel Timbrado
$stmtLodge = $pdo->prepare("
    SELECT l.nome, l.url_logo, l.endereco, l.email, l.localizacao
    FROM secretaria_lojas l
    JOIN usuarios u ON u.loja_id = l.id
    WHERE u.id = ? 
    LIMIT 1
");
$stmtLodge->execute([$tenant_id]);
$lodge = $stmtLodge->fetch(PDO::FETCH_ASSOC);

if (!$lodge) {
    $lodge = ['nome' => 'Loja não associada', 'url_logo' => '../configuracoes/icone.svg', 'endereco' => 'Endereço não informado', 'email' => 'Email não informado'];
}

// 1. Processamento de Inserção / Edição
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['titulo_corresp'])) {
    $id = $_POST['id'] ?? null;
    $titulo = trim($_POST['titulo_corresp'] ?? '');
    $data_rec = $_POST['data_recebimento'] ?? '';
    $remetente = trim($_POST['remetente'] ?? '');
    $tipo = $_POST['tipo'] ?? 'Entrada';
    $status = $_POST['status'] ?? 'Pendente';
    $conteudo = trim($_POST['conteudo'] ?? '');

    if (!empty($titulo) && !empty($data_rec)) {
        try {
            if (!empty($id)) {
                $stmt = $pdo->prepare("UPDATE secretaria_correspondencias SET titulo = ?, data_recebimento = ?, remetente = ?, tipo = ?, status = ?, conteudo = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$titulo, $data_rec, $remetente, $tipo, $status, $conteudo, $id, $tenant_id]);
                $_SESSION['mensagem'] = "Correspondência atualizada!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO secretaria_correspondencias (tenant_id, titulo, data_recebimento, remetente, tipo, status, conteudo) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$tenant_id, $titulo, $data_rec, $remetente, $tipo, $status, $conteudo]);
                $_SESSION['mensagem'] = "Correspondência registrada!";
            }
        } catch (PDOException $e) {
            $_SESSION['erro'] = "Erro ao salvar: " . $e->getMessage();
        }
    } else {
        $_SESSION['erro'] = "Preencha os campos obrigatórios.";
    }
    header("Location: corresp");
    exit;
}

// 2. Processamento de Envio via Webhook do n8n
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_envio']) && $_POST['acao_envio'] === 'disparar_webhook') {
    $corresp_id = $_POST['corresp_id'] ?? null;
    $destinatario_email = trim($_POST['destinatario_email'] ?? '');

    // Busca a correspondência para garantir que pertence ao tenant
    $stmtC = $pdo->prepare("SELECT * FROM secretaria_correspondencias WHERE id = ? AND tenant_id = ?");
    $stmtC->execute([$corresp_id, $tenant_id]);
    $dados_corresp = $stmtC->fetch(PDO::FETCH_ASSOC);
    
    $stmtC = $pdo->prepare("SELECT * FROM chancelaria_membros WHERE tenant_id = ? AND cargo in ('veneravel','orador','secretario')");
    $stmtC->execute([$tenant_id]);
    $dados_membros = $stmtC->fetchAll(PDO::FETCH_ASSOC);
    
    if ($dados_membros) {
        foreach ($dados_membros as $membro) {
            // Verifica o cargo e atribui à variável correta
            if (strtolower($membro['cargo']) === 'veneravel') {
                $nomeVeneravel = $membro['nome'];
            } elseif (strtolower($membro['cargo']) === 'orador') {
                $nomeOrador = $membro['nome'];
            } elseif (strtolower($membro['cargo']) === 'secretario') {
                $nomeSecretario = $membro['nome'];
            }
        }
    }

    if ($dados_corresp && !empty($destinatario_email)) {
        try {
            // URL do seu Webhook do n8n (substitua pela sua URL real)
            $webhook_url = 'https://n8n-prod.jrtec.com.br/webhook/enviar-emails'; 

            $payload = json_encode([
                'tenant_id' => $tenant_id,
                'loja_nome' => $lodge['nome'],
                'loja_logo' => $lodge['url_logo'],
                'loja_endereco' => $lodge['endereco'],
                'loja_cidade' => $lodge['localizacao'],
                'loja_email' => $lodge['email'],
                'destinatario_email' => $destinatario_email,
                'titulo' => $dados_corresp['titulo'],
                'data_recebimento' => $dados_corresp['data_recebimento'],
                'tipo' => $dados_corresp['tipo'],
                'remetente' => $dados_corresp['remetente'],
                'status' => $dados_corresp['status'],
                'conteudo' => $dados_corresp['conteudo'],
                'veneravel' => $nomeVeneravel,
                'orador' => $nomeOrador,
                'secretario' => $nomeSecretario,
                'data_envio' => date('d/m/Y H:i')
            ]);

            $ch = curl_init($webhook_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'x-gestao-api-key: ' . getenv('API_TOKEN')
            ]);
            
            $response = curl_exec($ch);
            curl_close($ch);

            $_SESSION['mensagem'] = "Correspondência disparada com sucesso para o n8n!";
        } catch (Exception $e) {
            $_SESSION['erro'] = "Erro ao disparar webhook: " . $e->getMessage();
        }
    } else {
        $_SESSION['erro'] = "E-mail de destino inválido ou correspondência não encontrada.";
    }
    header("Location: corresp");
    exit;
}

// 3. Exclusão
if (isset($_GET['excluir'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM secretaria_correspondencias WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$_GET['excluir'], $tenant_id]);
        $_SESSION['mensagem'] = "Correspondência excluída!";
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao excluir.";
    }
    header("Location: corresp");
    exit;
}

// Filtros
$filtro_mes = isset($_GET['mes']) && $_GET['mes'] !== '' ? $_GET['mes'] : date('m');
$filtro_ano = isset($_GET['ano']) && $_GET['ano'] !== '' ? $_GET['ano'] : date('Y');

$corresp = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM secretaria_correspondencias WHERE tenant_id = ? AND MONTH(data_recebimento) = ? AND YEAR(data_recebimento) = ? ORDER BY data_recebimento DESC");
    $stmt->execute([$tenant_id, $filtro_mes, $filtro_ano]);
    $corresp = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretaria - Correspondências</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --bg-main: #141724; --bg-card: #1d2132; --text-main: #e2e8f0; --gold: #f5c041; --border-color: #333951; }
        body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 260px; padding: 30px 40px; width: calc(100% - 260px); }
        .card-custom { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        .btn-gold { background-color: var(--gold); color: #141724; font-weight: 600; border: none; }
        .btn-gold:hover { background-color: #dca732; color: #141724; }
        
        .form-control, .form-select { background-color: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); }
        .form-control:focus, .form-select:focus { border-color: var(--gold); box-shadow: 0 0 0 0.25rem rgba(245, 192, 65, 0.25); color: var(--text-main); background-color: var(--bg-main); }

        .table-dark-custom { color: var(--text-main); vertical-align: middle; }
        .table-dark-custom thead th { background-color: rgba(0,0,0,0.2); color: var(--gold); border-bottom: 2px solid var(--border-color); font-weight: 600; text-transform: uppercase; font-size: 0.85rem; }
        .table-dark-custom tbody td { border-bottom: 1px solid var(--border-color); padding: 15px 10px; background: transparent; color: #e2e8f0 !important; }

        .mobile-topbar { display: none; height: 60px; background-color: var(--bg-card); border-bottom: 1px solid var(--border-color); align-items: center; padding: 0 20px; justify-content: space-between; position: fixed; top: 0; left: 0; right: 0; z-index: 2000; }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); z-index: 3000; width: 280px; transition: 0.3s; position: fixed; height: 100vh; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 15px; padding-top: 80px; }
            .mobile-topbar { display: flex !important; }
        }
    </style>
</head>
<body>

<!-- Barra Superior Mobile -->
<div class="mobile-topbar">
    <div class="d-flex align-items-center">
        <button class="btn btn-outline-warning btn-sm me-3" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <span style="font-family: 'Cinzel', serif; color: var(--gold); font-weight: bold;">SECRETARIA</span>
    </div>
    <span class="text-white small">Correspondências</span>
</div>

<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>

<?php include 'menu.php'; ?>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;">
                <i class="fas fa-inbox me-2 text-warning"></i> Gestão de Correspondências
            </h2>
            <p class="text-warning mb-0">Controle de entrada e saída de convites, cartas e documentos.</p>
        </div>
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalCorresp" onclick="limparFormulario()" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
            <i class="fas fa-plus me-2"></i> Nova Correspondência
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

    <!-- Filtro por Mês e Ano -->
    <div class="card-custom mb-4">
        <form method="GET" action="corresp" class="row g-3 align-items-end">
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
                <a href="corresp.php" class="btn btn-outline-secondary w-100 text-light">Limpar</a>
            </div>
        </form>
    </div>

    <!-- Tabela de Correspondências -->
    <div class="card-custom">
        <div class="table-responsive">
            <table class="table table-dark-custom">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Título / Assunto</th>
                        <th>Tipo</th>
                        <th>Origem / Destino</th>
                        <th>Status</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($corresp) > 0): ?>
                        <?php foreach($corresp as $c): ?>
                        <?php
                            $statusClass = 'bg-secondary';
                            if ($c['status'] == 'Respondido') $statusClass = 'bg-success';
                            if ($c['status'] == 'Pendente') $statusClass = 'bg-warning text-dark';
                            
                            $tipoClass = $c['tipo'] == 'Entrada' ? 'text-info' : 'text-danger';
                            $tipoIcon = $c['tipo'] == 'Entrada' ? 'fa-arrow-down' : 'fa-arrow-up';
                        ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($c['data_recebimento'])) ?></td>
                            <td class="fw-bold text-white"><?= htmlspecialchars($c['titulo']) ?></td>
                            <td>
                                <span class="<?= $tipoClass ?>"><i class="fas <?= $tipoIcon ?> me-1"></i> <?= $c['tipo'] ?></span>
                            </td>
                            <td><?= htmlspecialchars($c['remetente']) ?></td>
                            <td><span class="badge <?= $statusClass ?>"><?= $c['status'] ?></span></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-light me-1" onclick="abrirModalEnvio(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)" title="Enviar por E-mail (n8n)" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                                    <i class="fa-solid fa-envelope"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-warning me-1" onclick="editarCorresp(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)" title="Editar" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="corresp.php?excluir=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Deseja realmente excluir esta correspondência?')" title="Excluir" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-light">Nenhuma correspondência encontrada para este período.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal Cadastro / Edição -->
<div class="modal fade" id="modalCorresp" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background-color: var(--bg-card); color: var(--text-main); border: 1px solid var(--border-color);">
            <form method="POST" action="corresp">
                <input type="hidden" name="id" id="corresp_id">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title text-warning" id="modalTitulo" style="font-family: 'Cinzel', serif;">Nova Correspondência</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Título / Assunto</label>
                        <input type="text" class="form-control" name="titulo_corresp" id="titulo_corresp" required placeholder="Ex: Convite de Sessão Magna">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Data (Recebimento / Envio)</label>
                            <input type="date" class="form-control" name="data_recebimento" id="data_recebimento" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Tipo de Fluxo</label>
                            <select class="form-select" name="tipo" id="tipo">
                                <option value="Entrada">Entrada (Recebida)</option>
                                <option value="Saída">Saída (Enviada)</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Origem / Destino</label>
                            <input type="text" class="form-control" name="remetente" id="remetente" required placeholder="Ex: Grande Loja / Loja Co-irmã">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Status</label>
                            <select class="form-select" name="status" id="status">
                                <option value="Pendente">Pendente</option>
                                <option value="Respondido">Respondido</option>
                                <option value="Arquivado">Arquivado</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Conteúdo / Observações</label>
                        <textarea class="form-control" name="conteudo" id="conteudo" rows="10" placeholder="Detalhes da correspondência..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Salvar Correspondência</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Envio por E-mail (Webhook n8n) -->
<div class="modal fade" id="modalEnvioEmail" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="background-color: var(--bg-card); color: var(--text-main); border: 1px solid var(--border-color);">
            <form method="POST" action="corresp">
                <input type="hidden" name="acao_envio" value="disparar_webhook">
                <input type="hidden" name="corresp_id" id="envio_corresp_id">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title text-warning" style="font-family: 'Cinzel', serif;"><i class="fas fa-paper-plane me-2"></i> Enviar Correspondência Oficial</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-secondary py-2 small mb-3">
                        <i class="fas fa-info-circle text-warning me-1"></i> O documento será enviado em formato de papel timbrado da loja: <strong><?= htmlspecialchars($lodge['nome']) ?></strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Título selecionado:</label>
                        <input type="text" class="form-control" id="envio_titulo_display" readonly disabled>
                    </div>
                    <!-- Dentro do Modal Envio por E-mail -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">E-mail(s) de Destino <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="destinatario_email" required 
                            placeholder="email1@exemplo.com, email2@exemplo.com">
                            <small class="text-white-50">Use vírgulas para separar múltiplos destinatários.</small>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-paper-plane me-1"></i> Enviar E-mail</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function limparFormulario() {
    document.getElementById('corresp_id').value = '';
    document.getElementById('titulo_corresp').value = '';
    document.getElementById('data_recebimento').value = '<?= date('Y-m-d') ?>';
    document.getElementById('tipo').value = 'Entrada';
    document.getElementById('remetente').value = '';
    document.getElementById('status').value = 'Pendente';
    document.getElementById('conteudo').value = '';
    document.getElementById('modalTitulo').innerText = 'Nova Correspondência';
}

function editarCorresp(c) {
    document.getElementById('corresp_id').value = c.id;
    document.getElementById('titulo_corresp').value = c.titulo;
    document.getElementById('data_recebimento').value = c.data_recebimento;
    document.getElementById('tipo').value = c.tipo;
    document.getElementById('remetente').value = c.remetente;
    document.getElementById('status').value = c.status;
    document.getElementById('conteudo').value = c.conteudo || '';
    document.getElementById('modalTitulo').innerText = 'Editar Correspondência';
    
    var modal = new bootstrap.Modal(document.getElementById('modalCorresp'));
    modal.show();
}

function abrirModalEnvio(c) {
    document.getElementById('envio_corresp_id').value = c.id;
    document.getElementById('envio_titulo_display').value = c.titulo;
    
    var modal = new bootstrap.Modal(document.getElementById('modalEnvioEmail'));
    modal.show();
}

function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar'); 
    const backdrop = document.getElementById('sidebarBackdrop');
    if (sidebar) sidebar.classList.toggle('show');
    if (backdrop) backdrop.classList.toggle('show');
}
</script>
</body>
</html>