<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../configuracoes/config.php';

// Garante que o usuário está logado
if (!isset($_SESSION['tenant_id']) && !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['user']['id'];

$mensagem = $_SESSION['mensagem'] ?? '';
$erro = $_SESSION['erro'] ?? '';
unset($_SESSION['mensagem'], $_SESSION['erro']);

// Busca os dados da Loja associada ao dono da conta (tenant_id)
// O tenant_id no seu sistema representa o ID do dono na tabela de usuários.
$stmtLodge = $pdo->prepare("
    SELECT l.nome, l.url_logo, l.endereco 
    FROM secretaria_lojas l
    JOIN usuarios u ON u.loja_id = l.id
    WHERE u.id = ? 
    LIMIT 1
");
$stmtLodge->execute([$tenant_id]);
$lodge = $stmtLodge->fetch(PDO::FETCH_ASSOC);

// Caso o usuário não tenha associado nenhuma loja ainda, evitamos erros
if (!$lodge) {
    $lodge = ['nome' => 'Loja não associada', 'url_logo' => '../configuracoes/icone.svg', 'endereco' => 'Endereço não informado'];
}

// 1. Processamento de Inserção / Edição de Expediente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['titulo_expediente'])) {
    $id = $_POST['id'] ?? null;
    $titulo = trim($_POST['titulo_expediente'] ?? '');
    $data_expediente = $_POST['data_expediente'] ?? '';
    $tipo = $_POST['tipo'] ?? 'Ofício';
    $destinatario = trim($_POST['destinatario'] ?? '');
    $conteudo = trim($_POST['conteudo'] ?? '');

    if (!empty($titulo) && !empty($data_expediente)) {
        try {
            if (!empty($id)) {
                // Atualizar
                $stmt = $pdo->prepare("UPDATE secretaria_expedientes SET titulo = ?, data_expediente = ?, tipo = ?, destinatario = ?, conteudo = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$titulo, $data_expediente, $tipo, $destinatario, $conteudo, $id, $tenant_id]);
                $_SESSION['mensagem'] = "Expediente atualizado com sucesso!";
            } else {
                // Inserir
                $stmt = $pdo->prepare("INSERT INTO secretaria_expedientes (tenant_id, titulo, data_expediente, tipo, destinatario, conteudo) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$tenant_id, $titulo, $data_expediente, $tipo, $destinatario, $conteudo]);
                $_SESSION['mensagem'] = "Expediente cadastrado com sucesso!";
            }
        } catch (PDOException $e) {
            $_SESSION['erro'] = "Erro ao salvar o expediente: " . $e->getMessage();
        }
    } else {
        $_SESSION['erro'] = "Preencha os campos obrigatórios.";
    }

    header("Location: expedientes");
    exit;
}

// 2. Exclusão de Expediente
if (isset($_GET['excluir'])) {
    $idExcluir = $_GET['excluir'];
    try {
        $stmt = $pdo->prepare("DELETE FROM secretaria_expedientes WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$idExcluir, $tenant_id]);
        $_SESSION['mensagem'] = "Expediente excluído com sucesso!";
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao excluir o expediente.";
    }
    header("Location: expedientes");
    exit;
}

// Filtro por Mês e Ano
$filtro_mes = isset($_GET['mes']) && $_GET['mes'] !== '' ? $_GET['mes'] : date('m');
$filtro_ano = isset($_GET['ano']) && $_GET['ano'] !== '' ? $_GET['ano'] : date('Y');

// Busca os expedientes cadastrados
$expedientes = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM secretaria_expedientes 
        WHERE tenant_id = ? 
        AND MONTH(data_expediente) = ? 
        AND YEAR(data_expediente) = ? 
        ORDER BY data_expediente DESC
    ");
    $stmt->execute([$tenant_id, $filtro_mes, $filtro_ano]);
    $expedientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $expedientes = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretaria - Gestão de Expedientes</title>
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
    <span class="text-white small">Expedientes</span>
</div>

<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>

<?php include 'menu.php'; ?>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;">
                <i class="fas fa-paper-plane text-warning me-2"></i> Gestão de Expedientes
            </h2>
            <p class="text-warning mb-0">Registre, consulte e gerencie os ofícios, circulares e documentos expedidos.</p>
        </div>
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalExpediente" onclick="limparFormulario()" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
            <i class="fas fa-plus me-2"></i> Novo Expediente
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
        <form method="GET" action="expedientes" class="row g-3 align-items-end">
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
                <a href="expedientes" class="btn btn-outline-secondary w-100 text-light">Limpar</a>
            </div>
        </form>
    </div>

    <!-- Tabela de Expedientes -->
    <div class="card-custom">
        <div class="table-responsive">
            <table class="table table-dark-custom">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Título / Assunto</th>
                        <th>Tipo</th>
                        <th>Destinatário</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($expedientes) > 0): ?>
                        <?php foreach ($expedientes as $e): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($e['data_expediente'])) ?></td>
                                <td class="fw-bold text-white"><?= htmlspecialchars($e['titulo']) ?></td>
                                <td><span class="badge bg-dark border border-secondary"><?= htmlspecialchars($e['tipo']) ?></span></td>
                                <td><?= htmlspecialchars($e['destinatario']) ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-light me-1" onclick="printExpediente(<?= htmlspecialchars(json_encode($e), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($lodge), ENT_QUOTES, 'UTF-8') ?>)" title="Imprimir Expedientes">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning me-1" onclick="editarExpediente(<?= htmlspecialchars(json_encode($e), ENT_QUOTES, 'UTF-8') ?>)" title="Editar" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="expedientes.php?excluir=<?= $e['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Deseja realmente excluir este expediente?')" title="Excluir" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-light">Nenhum expediente encontrado para este período.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal Cadastro / Edição de Expediente -->
<div class="modal fade" id="modalExpediente" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background-color: var(--bg-card); color: var(--text-main); border: 1px solid var(--border-color);">
            <form method="POST" action="expedientes">
                <input type="hidden" name="id" id="expediente_id">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title text-warning" id="modalTitulo" style="font-family: 'Cinzel', serif;">Novo Expediente</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Título / Assunto</label>
                        <input type="text" class="form-control" name="titulo_expediente" id="titulo_expediente" required placeholder="Ex: Ofício circular nº 12/2026">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Data do Expediente</label>
                            <input type="date" class="form-control" name="data_expediente" id="data_expediente" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Tipo de Documento</label>
                            <select class="form-select" name="tipo" id="tipo">
                                <option value="Ofício">Ofício</option>
                                <option value="Circular">Circular</option>
                                <option value="Decreto">Decreto</option>
                                <option value="Ato">Ato</option>
                                <option value="Mensagem">Mensagem</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Destinatário</label>
                        <input type="text" class="form-control" name="destinatario" id="destinatario" placeholder="Ex: Grande Secretaria / Outra Loja" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Texto / Conteúdo do Expediente</label>
                        <textarea class="form-control" name="conteudo" id="conteudo" rows="10" placeholder="Detalhes ou texto do documento..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>Cancelar</button>
                    <button type="submit" class="btn btn-warning" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>Salvar Expediente</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function limparFormulario() {
    document.getElementById('expediente_id').value = '';
    document.getElementById('titulo_expediente').value = '';
    document.getElementById('data_expediente').value = '<?= date('Y-m-d') ?>';
    document.getElementById('tipo').value = 'Ofício';
    document.getElementById('destinatario').value = '';
    document.getElementById('conteudo').value = '';
    document.getElementById('modalTitulo').innerText = 'Novo Expediente';
}

function editarExpediente(e) {
    document.getElementById('expediente_id').value = e.id;
    document.getElementById('titulo_expediente').value = e.titulo ?? e.titulo;
    document.getElementById('data_expediente').value = e.data_expediente;
    document.getElementById('tipo').value = e.tipo;
    document.getElementById('destinatario').value = e.destinatario;
    document.getElementById('conteudo').value = e.conteudo || '';
    document.getElementById('modalTitulo').innerText = 'Editar Expediente';
    
    var modal = new bootstrap.Modal(document.getElementById('modalExpediente'));
    modal.show();
}

function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar'); 
    const backdrop = document.getElementById('sidebarBackdrop');
    if (sidebar) sidebar.classList.toggle('show');
    if (backdrop) backdrop.classList.toggle('show');
}

function printExpediente(e, lodge) {
    const win = window.open('', '_blank', 'width=800,height=600');
    const dataBr = e.data_expediente.split('-').reverse().join('/');
    
    // Verifica se tem logo, senão ignora
    const logoHtml = lodge.url_logo ? `<img src="${lodge.url_logo}" style="max-width: 120px; margin-bottom: 10px;">` : '';

    win.document.write(`
        <html>
        <head>
            <title>Balaustre - ${e.titulo}</title>
            <style>
                body { font-family: 'Times New Roman', serif; padding: 40px; color: #000; line-height: 1.6; }
                .header { text-align: center; margin-bottom: 30px; }
                h1 { font-size: 20px; text-transform: uppercase; margin: 5px 0; }
                .lodge-name { font-size: 18px; font-weight: bold; }
                .document-title { font-size: 22px; font-weight: bold; text-decoration: underline; margin-top: 20px; }
                .meta { margin: 20px 0; font-style: italic; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
                .conteudo { white-space: pre-wrap; margin-top: 20px; text-align: justify; font-size: 16px; }
                .assinaturas { display: flex; justify-content: space-between; margin-top: 80px; text-align: center; }
                .assinatura-box { flex: 1; padding: 0 10px; }
                .linha-assinatura { border-top: 1px solid #000; width: 80%; margin: 0 auto 5px auto; }
                .titulo-assinatura { font-size: 13px; font-weight: bold; text-transform: uppercase; }
            </style>
        </head>
        <body>
            <div class="header">
                ${logoHtml}
                <div class="lodge-name">${lodge.nome}</div>
                <div class="lodge-name">${lodge.endereco}</div>
                <div class="document-title">EXPEDIENTE</div>
            </div>
            
            <div class="meta">
                Data: ${dataBr} | Tipo: ${e.tipo} 
            </div>
            
            <div class="conteudo"><strong>Título:</strong> ${e.titulo}<br><br>${e.conteudo}</div>
            
            <div class="assinaturas">
                <div class="assinatura-box">
                    <div class="linha-assinatura"></div>
                    <div class="titulo-assinatura">Venerável Mestre</div>
                </div>
                <div class="assinatura-box">
                    <div class="linha-assinatura"></div>
                    <div class="titulo-assinatura">Orador</div>
                </div>
                <div class="assinatura-box">
                    <div class="linha-assinatura"></div>
                    <div class="titulo-assinatura">Secretário</div>
                </div>
            </div>
        </body>
        </html>
    `);
    
    win.document.close();
    win.focus();
    setTimeout(() => {
        win.print();
        win.close();
    }, 500);
}
</script>
</body>
</html>