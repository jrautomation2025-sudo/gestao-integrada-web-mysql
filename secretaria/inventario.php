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

// ==========================================
// ROTA AJAX: GERAR PRÓXIMO CÓDIGO SEQUENCIAL
// ==========================================
if (isset($_GET['acao']) && $_GET['acao'] === 'proximo_codigo') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    try {
        // Busca o último ID cadastrado para este tenant
        $stmt = $pdo->prepare("SELECT id FROM secretaria_inventario WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$tenant_id]);
        $ultimoId = $stmt->fetchColumn() ?: 0;
        
        $proximoNum = $ultimoId + 1;
        // Gera no formato PAT-001, PAT-002, etc.
        $codigoGerado = 'PAT-' . str_pad($proximoNum, 3, '0', STR_PAD_LEFT);
        
        // Garante unicidade caso já exista por algum motivo
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM secretaria_inventario WHERE tenant_id = ? AND codigo_patrimonio = ?");
        $stmtCheck->execute([$tenant_id, $codigoGerado]);
        if ($stmtCheck->fetchColumn() > 0) {
            $codigoGerado = 'PAT-' . time(); // Fallback único com timestamp se houver conflito
        }
        
        echo json_encode(['status' => 'sucesso', 'codigo' => $codigoGerado]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'erro', 'codigo' => 'PAT-' . rand(100, 999)]);
    }
    exit;
}

// 1. Processamento de Inserção / Edição de Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nome_item'])) {
    $id = $_POST['id'] ?? null;
    $codigo = trim($_POST['codigo_patrimonio'] ?? '');
    $nome = trim($_POST['nome_item'] ?? '');
    $categoria = $_POST['categoria'] ?? 'Outros';
    $quantidade = (int)($_POST['quantidade'] ?? 1);
    $estado = $_POST['estado_conservacao'] ?? 'Bom';
    $data_aquisicao = !empty($_POST['data_aquisicao']) ? $_POST['data_aquisicao'] : null;
    $observacoes = trim($_POST['observacoes'] ?? '');

    // Se o código de patrimônio veio vazio (caso o usuário tente burlar o HTML), geramos automaticamente
    if (empty($codigo)) {
        $stmtUlt = $pdo->prepare("SELECT id FROM secretaria_inventario WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
        $stmtUlt->execute([$tenant_id]);
        $codigo = 'PAT-' . str_pad(($stmtUlt->fetchColumn() ?: 0) + 1, 3, '0', STR_PAD_LEFT);
    }

    if (!empty($nome)) {
        try {
            if (!empty($id)) {
                // Atualizar
                $stmt = $pdo->prepare("UPDATE secretaria_inventario SET codigo_patrimonio = ?, nome = ?, categoria = ?, quantidade = ?, estado_conservacao = ?, data_aquisicao = ?, observacoes = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$codigo, $nome, $categoria, $quantidade, $estado, $data_aquisicao, $observacoes, $id, $tenant_id]);
                $_SESSION['mensagem'] = "Item atualizado com sucesso!";
            } else {
                // Inserir
                $stmt = $pdo->prepare("INSERT INTO secretaria_inventario (tenant_id, codigo_patrimonio, nome, categoria, quantidade, estado_conservacao, data_aquisicao, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$tenant_id, $codigo, $nome, $categoria, $quantidade, $estado, $data_aquisicao, $observacoes]);
                $_SESSION['mensagem'] = "Item adicionado ao inventário!";
            }
        } catch (PDOException $e) {
            $_SESSION['erro'] = "Erro ao salvar o item: " . $e->getMessage();
        }
    } else {
        $_SESSION['erro'] = "O nome do item é obrigatório.";
    }

    header("Location: inventario.php");
    exit;
}

// 2. Exclusão de Item
if (isset($_GET['excluir'])) {
    $idExcluir = $_GET['excluir'];
    try {
        $stmt = $pdo->prepare("DELETE FROM secretaria_inventario WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$idExcluir, $tenant_id]);
        $_SESSION['mensagem'] = "Item removido do inventário!";
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao excluir o item.";
    }
    header("Location: inventario.php");
    exit;
}

// Filtros
$filtro_categoria = $_GET['categoria'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';

// Busca os itens do inventário
$inventario = [];
try {
    $sql = "SELECT * FROM secretaria_inventario WHERE tenant_id = ?";
    $params = [$tenant_id];

    if (!empty($filtro_categoria)) {
        $sql .= " AND categoria = ?";
        $params[] = $filtro_categoria;
    }
    
    if (!empty($filtro_estado)) {
        $sql .= " AND estado_conservacao = ?";
        $params[] = $filtro_estado;
    }

    $sql .= " ORDER BY nome ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $inventario = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretaria - Inventário e Patrimônio</title>
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

        /* ==========================================
           ESTILOS PARA IMPRESSÃO (PDF / PAPEL)
           ========================================== */
        @media print {
            body { background-color: #fff !important; color: #000 !important; }
            .sidebar, .mobile-topbar, .sidebar-backdrop, .no-print, form, .btn { display: none !important; }
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 0 !important; }
            .card-custom { background-color: #fff !important; border: none !important; box-shadow: none !important; padding: 0 !important; }
            .table-dark-custom { color: #000 !important; width: 100% !important; border-collapse: collapse !important; }
            .table-dark-custom thead th { background-color: #f8f9fa !important; color: #000 !important; border-bottom: 2px solid #000 !important; }
            .table-dark-custom tbody td { border-bottom: 1px solid #ddd !important; color: #000 !important; }
            .badge { border: 1px solid #000; color: #000 !important; background: transparent !important; }
            .print-header { display: block !important; text-align: center; margin-bottom: 20px; }
        }
        .print-header { display: none; }
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
    <span class="text-white small">Inventário</span>
</div>

<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>

<?php include 'menu.php'; ?>

<main class="main-content">
    <!-- Cabeçalho exclusivo para impressão -->
    <div class="print-header">
        <h2 style="font-family: 'Cinzel', serif; font-weight: 700; margin-bottom: 5px;">Relatório de Inventário e Patrimônio</h2>
        <p style="font-size: 14px; margin: 0;">Emitido em <?= date('d/m/Y H:i') ?></p>
        <hr style="border-top: 1px solid #000; margin-top: 10px;">
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;">
                <i class="fas fa-boxes me-2 text-warning"></i> Inventário e Patrimônio
            </h2>
            <p class="text-warning mb-0">Controle de mobiliário, utensílios de ritualística, paramentos, livros e etc.</p>
        </div>
        <div class="d-flex gap-2">
            <!-- Botão de Impressão -->
            <button class="btn btn-outline-light" onclick="window.print()">
                <i class="fas fa-print me-2"></i> Imprimir Inventário
            </button>
            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalInventario" onclick="limparFormulario()" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                <i class="fas fa-plus me-2"></i> Adicionar Item
            </button>
        </div>
    </div>

    <?php if (!empty($mensagem)): ?>
        <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
            <?= htmlspecialchars($mensagem) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($erro)): ?>
        <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
            <?= htmlspecialchars($erro) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filtros de Busca -->
    <div class="card-custom mb-4 no-print">
        <form method="GET" action="inventario" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-bold text-warning">Categoria</label>
                <select name="categoria" class="form-select">
                    <option value="">Todas as Categorias</option>
                    <option value="Mobiliário" <?= $filtro_categoria == 'Mobiliário' ? 'selected' : '' ?>>Mobiliário</option>
                    <option value="Utensílios de Ritualística" <?= $filtro_categoria == 'Utensílios de Ritualística' ? 'selected' : '' ?>>Utensílios de Ritualística</option>
                    <option value="Paramentos" <?= $filtro_categoria == 'Paramentos' ? 'selected' : '' ?>>Paramentos</option>
                    <option value="Biblioteca e Livros" <?= $filtro_categoria == 'Biblioteca e Livros' ? 'selected' : '' ?>>Biblioteca e Livros</option>
                    <option value="Placas e Homenagens" <?= $filtro_categoria == 'Placas e Homenagens' ? 'selected' : '' ?>>Placas e Homenagens</option>
                    <option value="Eletrônicos" <?= $filtro_categoria == 'Eletrônicos' ? 'selected' : '' ?>>Eletrônicos</option>
                    <option value="Outros" <?= $filtro_categoria == 'Outros' ? 'selected' : '' ?>>Outros</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold text-warning">Estado de Conservação</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="Novo" <?= $filtro_estado == 'Novo' ? 'selected' : '' ?>>Novo</option>
                    <option value="Excelente" <?= $filtro_estado == 'Excelente' ? 'selected' : '' ?>>Excelente</option>
                    <option value="Bom" <?= $filtro_estado == 'Bom' ? 'selected' : '' ?>>Bom</option>
                    <option value="Regular" <?= $filtro_estado == 'Regular' ? 'selected' : '' ?>>Regular</option>
                    <option value="Necessita Reparo" <?= $filtro_estado == 'Necessita Reparo' ? 'selected' : '' ?>>Necessita Reparo</option>
                    <option value="Inservível" <?= $filtro_estado == 'Inservível' ? 'selected' : '' ?>>Inservível</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-warning w-100">
                    <i class="fas fa-filter me-1"></i> Filtrar
                </button>
                <a href="inventario.php" class="btn btn-outline-secondary w-100 text-light">Limpar</a>
            </div>
        </form>
    </div>

    <!-- Tabela de Inventário -->
    <div class="card-custom">
        <div class="table-responsive">
            <table class="table table-dark-custom">
                <thead>
                    <tr>
                        <th>Cód. Patrimônio</th>
                        <th>Nome do Item</th>
                        <th>Categoria</th>
                        <th class="text-center">Qtd</th>
                        <th>Estado</th>
                        <th class="text-center no-print">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($inventario) > 0): ?>
                        <?php foreach ($inventario as $i): ?>
                            <?php 
                                $badgeColor = 'bg-secondary';
                                if (in_array($i['estado_conservacao'], ['Novo', 'Excelente', 'Bom'])) $badgeColor = 'bg-success';
                                if ($i['estado_conservacao'] == 'Regular') $badgeColor = 'bg-warning text-dark';
                                if (in_array($i['estado_conservacao'], ['Necessita Reparo', 'Inservível'])) $badgeColor = 'bg-danger';
                            ?>
                            <tr>
                                <td class="text-warning fw-bold"><?= htmlspecialchars($i['codigo_patrimonio']) ?></td>
                                <td class="fw-bold text-white"><?= htmlspecialchars($i['nome']) ?></td>
                                <td><?= htmlspecialchars($i['categoria']) ?></td>
                                <td class="text-center fw-bold"><?= $i['quantidade'] ?></td>
                                <td><span class="badge <?= $badgeColor ?>"><?= htmlspecialchars($i['estado_conservacao']) ?></span></td>
                                <td class="text-center no-print">
                                    <button class="btn btn-sm btn-outline-warning me-1" onclick="editarItem(<?= htmlspecialchars(json_encode($i), ENT_QUOTES, 'UTF-8') ?>)" title="Editar" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="inventario.php?excluir=<?= $i['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Deseja realmente excluir este item do inventário?')" title="Excluir" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-light">Nenhum item encontrado no inventário com os filtros atuais.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal Cadastro / Edição de Item -->
<div class="modal fade" id="modalInventario" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background-color: var(--bg-card); color: var(--text-main); border: 1px solid var(--border-color);">
            <form method="POST" action="inventario">
                <input type="hidden" name="id" id="item_id">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title text-warning" id="modalTitulo" style="font-family: 'Cinzel', serif;">Cadastrar Novo Item</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label class="form-label fw-bold">Cód. Patrimônio <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="codigo_patrimonio" id="codigo_patrimonio" required placeholder="Bip ou digite...">
                                <button class="btn btn-outline-warning" type="button" onclick="gerarCodigoAutomatico()" title="Gerar Sequencial Automático se o Scanner falhar">
                                    <i class="fas fa-magic"></i> Gerar
                                </button>
                            </div>
                            <small class="text-muted">Use o scanner ou clique em Gerar.</small>
                        </div>
                        <div class="col-md-7 mb-3">
                            <label class="form-label fw-bold">Nome do Item <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nome_item" id="nome_item" required placeholder="Ex: Espada Flamejante / Livro da Lei">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Categoria</label>
                            <select class="form-select" name="categoria" id="categoria">
                                <option value="Mobiliário">Mobiliário</option>
                                <option value="Utensílios de Ritualística">Utensílios de Ritualística</option>
                                <option value="Paramentos">Paramentos</option>
                                <option value="Biblioteca e Livros">Biblioteca e Livros</option>
                                <option value="Placas e Homenagens">Placas e Homenagens</option>
                                <option value="Eletrônicos">Eletrônicos</option>
                                <option value="Outros">Outros</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Quantidade</label>
                            <input type="number" class="form-control" name="quantidade" id="quantidade" value="1" min="1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Estado de Conservação</label>
                            <select class="form-select" name="estado_conservacao" id="estado_conservacao">
                                <option value="Novo">Novo</option>
                                <option value="Excelente">Excelente</option>
                                <option value="Bom" selected>Bom</option>
                                <option value="Regular">Regular</option>
                                <option value="Necessita Reparo">Necessita Reparo</option>
                                <option value="Inservível">Inservível</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Data de Aquisição (Opcional)</label>
                        <input type="date" class="form-control" name="data_aquisicao" id="data_aquisicao" value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Observações (Localização, Detalhes, etc.)</label>
                        <textarea class="form-control" name="observacoes" id="observacoes" rows="3" placeholder="Detalhes sobre onde está guardado, quem doou, etc..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>Cancelar</button>
                    <button type="submit" class="btn btn-warning" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>Salvar Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Função para buscar via AJAX o próximo código sequencial do banco
function gerarCodigoAutomatico() {
    fetch('inventario.php?acao=proximo_codigo')
    .then(response => response.json())
    .then(data => {
        if (data.status === 'sucesso') {
            document.getElementById('codigo_patrimonio').value = data.codigo;
            // Joga o foco para o próximo campo (Nome do Item) para agilizar o fluxo
            document.getElementById('nome_item').focus();
        } else {
            alert('Erro ao gerar código automático.');
        }
    })
    .catch(err => {
        console.error('Erro:', err);
        // Fallback local caso a requisição falhe por rede
        document.getElementById('codigo_patrimonio').value = 'PAT-' + Math.floor(1000 + Math.random() * 9000);
    });
}

function limparFormulario() {
    // Pega a data de hoje no formato YYYY-MM-DD
    const hoje = new Date().toISOString().split('T')[0];

    document.getElementById('item_id').value = '';
    document.getElementById('codigo_patrimonio').value = '';
    document.getElementById('nome_item').value = '';
    document.getElementById('categoria').value = 'Mobiliário';
    document.getElementById('quantidade').value = '1';
    document.getElementById('estado_conservacao').value = 'Bom';
    document.getElementById('data_aquisicao').value = hoje;
    document.getElementById('observacoes').value = '';
    document.getElementById('modalTitulo').innerText = 'Cadastrar Novo Item';
}

function editarItem(i) {
    document.getElementById('item_id').value = i.id;
    document.getElementById('codigo_patrimonio').value = i.codigo_patrimonio || '';
    document.getElementById('nome_item').value = i.nome;
    document.getElementById('categoria').value = i.categoria;
    document.getElementById('quantidade').value = i.quantidade;
    document.getElementById('estado_conservacao').value = i.estado_conservacao;
    document.getElementById('data_aquisicao').value = i.data_aquisicao || '';
    document.getElementById('observacoes').value = i.observacoes || '';
    document.getElementById('modalTitulo').innerText = 'Editar Item do Inventário';
    
    var modal = new bootstrap.Modal(document.getElementById('modalInventario'));
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