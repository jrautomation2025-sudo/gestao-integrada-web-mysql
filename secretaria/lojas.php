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

// Identifica o tenant_id (dono da conta) e o usuário logado no momento
$tenant_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['user']['id'];
$user_logado_id = $_SESSION['user_id'] ?? $_SESSION['user']['id'];

// Se o usuário logado for o próprio dono da conta, ele tem permissão total
$is_dono = ($user_logado_id == $tenant_id);

$mensagem = $_SESSION['mensagem'] ?? '';
$erro = $_SESSION['erro'] ?? '';
unset($_SESSION['mensagem'], $_SESSION['erro']);

// ==========================================
// AÇÕES DE ASSOCIAÇÃO DA LOJA (Apenas Dono)
// ==========================================
if (isset($_GET['associar_loja']) && $is_dono) {
    $id_associar = (int) $_GET['associar_loja'];
    try {
        $stmt = $pdo->prepare("UPDATE usuarios SET loja_id = ? WHERE id = ?");
        $stmt->execute([$id_associar, $tenant_id]);
        $_SESSION['mensagem'] = "Loja definida como sua Principal com sucesso!";
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao associar a loja.";
    }
    header("Location: lojas");
    exit;
}

if (isset($_GET['desassociar_loja']) && $is_dono) {
    try {
        $stmt = $pdo->prepare("UPDATE usuarios SET loja_id = NULL WHERE id = ?");
        $stmt->execute([$tenant_id]);
        $_SESSION['mensagem'] = "Associação de Loja Principal removida com sucesso!";
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao remover a associação.";
    }
    header("Location: lojas");
    exit;
}

// ==========================================
// 1. Processamento de Inserção / Edição de Loja
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nome_loja'])) {
    $id = $_POST['id'] ?? null;
    $nome = trim($_POST['nome_loja'] ?? '');
    $potencia = trim($_POST['potencia'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rito = trim($_POST['rito'] ?? '');
    $localizacao = trim($_POST['localizacao'] ?? '');
    $dia_semana = trim($_POST['dia_semana'] ?? '');
    $frequencia = trim($_POST['frequencia'] ?? '');
    $horario = trim($_POST['horario'] ?? '');
    $data_fundacao = !empty($_POST['data_fundacao']) ? $_POST['data_fundacao'] : null;
    $endereco = trim($_POST['endereco'] ?? '');
    $url_logo = trim($_POST['url_logo'] ?? '');

    if (!empty($nome)) {
        try {
            if (!empty($id)) {
                // Atualizar
                $stmt = $pdo->prepare("UPDATE secretaria_lojas SET nome = ?, potencia = ?, telefone = ?, email = ?, rito = ?, localizacao = ?, dia_semana = ?, frequencia = ?, horario = ?, data_fundacao = ?, endereco = ?, url_logo = ? WHERE id = ?");
                $stmt->execute([$nome, $potencia, $telefone, $email, $rito, $localizacao, $dia_semana, $frequencia, $horario, $data_fundacao, $endereco, $url_logo, $id]);
                $_SESSION['mensagem'] = "Loja atualizada com sucesso!";
            } else {
                // Inserir
                $stmt = $pdo->prepare("INSERT INTO secretaria_lojas (nome, potencia, telefone, email, rito, localizacao, dia_semana, frequencia, horario, data_fundacao, endereco, url_logo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nome, $potencia, $telefone, $email, $rito, $localizacao, $dia_semana, $frequencia, $horario, $data_fundacao, $endereco, $url_logo]);
                $_SESSION['mensagem'] = "Loja cadastrada com sucesso!";
            }
        } catch (PDOException $e) {
            $_SESSION['erro'] = "Erro ao salvar a loja: " . $e->getMessage();
        }
    } else {
        $_SESSION['erro'] = "O nome da loja é obrigatório.";
    }

    header("Location: lojas");
    exit;
}

// ==========================================
// 2. Exclusão de Loja
// ==========================================
if (isset($_GET['excluir'])) {
    $idExcluir = $_GET['excluir'];
    try {
        $stmt = $pdo->prepare("DELETE FROM secretaria_lojas WHERE id = ?");
        $stmt->execute([$idExcluir]);
        $_SESSION['mensagem'] = "Loja excluída com sucesso!";
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao excluir a loja.";
    }
    header("Location: lojas");
    exit;
}

// ==========================================
// CONSULTAS INICIAIS DA TELA
// ==========================================

// Descobre qual é a Loja Principal associada a este Tenant
$loja_principal_id = null;
try {
    $stmt_tenant = $pdo->prepare("SELECT loja_id FROM usuarios WHERE id = ?");
    $stmt_tenant->execute([$tenant_id]);
    $loja_principal_id = $stmt_tenant->fetchColumn();
} catch (PDOException $e) {
    // Continua normal
}

// Busca todas as lojas cadastradas
$lojas = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM secretaria_lojas ORDER BY nome ASC");
    $stmt->execute();
    $lojas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $lojas = [];
}

function calcularIdade($dataFundacao) {
    if (empty($dataFundacao)) return 'N/I';
    $dataNascimento = new DateTime($dataFundacao);
    $dataAtual = new DateTime();
    $idade = $dataAtual->diff($dataNascimento);
    return $idade->y . ' anos';
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretaria - Gestão de Lojas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root { --bg-main: #141724; --bg-card: #1d2132; --text-main: #e2e8f0; --gold: #f5c041; --border-color: #333951; }
        body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 260px; padding: 30px 40px; width: calc(100% - 260px); }
        
        .card-custom { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 22px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); transition: transform 0.2s; }
        .card-custom:hover { transform: translateY(-3px); border-color: rgba(245, 192, 65, 0.4); }
        
        /* Destaque para a Loja Principal */
        .card-principal { border: 2px solid var(--gold) !important; box-shadow: 0 0 20px rgba(245, 192, 65, 0.15) !important; }
        
        .btn-gold { background-color: var(--gold); color: #141724; font-weight: 600; border: none; }
        .btn-gold:hover { background-color: #dca732; color: #141724; }
        
        .form-control, .form-select { background-color: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); }
        .form-control:focus, .form-select:focus { border-color: var(--gold); box-shadow: 0 0 0 0.25rem rgba(245, 192, 65, 0.25); color: var(--text-main); background-color: var(--bg-main); }

        .loja-logo { width: 50px; height: 50px; object-fit: contain; border-radius: 50%; background-color: #fff; padding: 3px; border: 1px solid var(--border-color); }
        
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

<div class="mobile-topbar">
    <div class="d-flex align-items-center">
        <button class="btn btn-outline-warning btn-sm me-3" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <span style="font-family: 'Cinzel', serif; color: var(--gold); font-weight: bold;">SECRETARIA</span>
    </div>
    <span class="text-white small">Gestão de Lojas</span>
</div>

<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>

<?php include 'menu.php'; ?>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;">
                <i class="fas fa-landmark text-warning me-2"></i> Cadastro de Lojas
            </h2>
            <p class="text-warning mb-0">Gerencie e defina a Loja Principal do seu sistema.</p>
        </div>
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalLoja" onclick="limparFormulario()">
            <i class="fas fa-plus me-2"></i> Nova Loja
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

    <!-- Grid de Cards de Lojas -->
    <div class="row g-4">
        <?php if (count($lojas) > 0): ?>
            <?php foreach ($lojas as $l): ?>
                
                <?php 
                    // Verifica se esta loja no loop é a loja vinculada ao Tenant
                    $is_minha_loja = ($l['id'] == $loja_principal_id); 
                ?>

                <div class="col-xl-4 col-md-6">
                    <!-- Se for a loja principal, aplica a classe 'card-principal' que dá o brilho dourado -->
                    <div class="card-custom h-100 d-flex flex-column justify-content-between <?= $is_minha_loja ? 'card-principal' : '' ?>">
                        <div>
                            <!-- Topo do Card -->
                            <div class="d-flex align-items-center mb-3">
                                <img src="<?= !empty($l['url_logo']) ? htmlspecialchars($l['url_logo']) : 'https://img.icons8.com/color/96/masonic.png' ?>" alt="Logo" class="loja-logo me-3">
                                <div>
                                    <h5 class="fw-bold text-white mb-0" style="font-size: 1rem;"><?= htmlspecialchars($l['nome']) ?></h5>
                                    <small class="text-warning text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                        <?= htmlspecialchars($l['potencia'] ?: 'S/ Potência') ?> &bull; <?= htmlspecialchars($l['rito'] ?: 'S/ Rito') ?>
                                    </small>
                                </div>
                                
                                <!-- Selo de Loja Principal -->
                                <?php if ($is_minha_loja): ?>
                                    <div class="ms-auto">
                                        <span class="badge bg-warning text-dark px-2 py-1 shadow-sm" title="Esta é a sua Loja conectada ao sistema">
                                            <i class="fas fa-star"></i> Principal
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <hr class="border-secondary my-2 opacity-50">

                            <!-- Detalhes em Grid (2 Colunas) -->
                            <div class="row g-2 text-white-50 small my-3">
                                <div class="col-6">
                                    <div class="mb-2"><i class="fas fa-map-marker-alt text-warning me-2"></i> <?= htmlspecialchars($l['localizacao'] ?: 'N/I') ?></div>
                                    <div class="mb-2"><i class="fas fa-sync-alt text-warning me-2"></i> <?= htmlspecialchars($l['frequencia'] ?: 'N/I') ?></div>
                                    <div class="mb-2"><i class="fas fa-history text-warning me-2"></i> Fundação: <?= !empty($l['data_fundacao']) ? date('d/m/Y', strtotime($l['data_fundacao'])) : 'N/I' ?></div>
                                </div>
                                <div class="col-6">
                                    <div class="mb-2"><i class="fas fa-calendar-alt text-warning me-2"></i> <?= htmlspecialchars($l['dia_semana'] ?: 'N/I') ?></div>
                                    <div class="mb-2"><i class="fas fa-clock text-warning me-2"></i> <?= htmlspecialchars($l['horario'] ?: 'N/I') ?></div>
                                    <div class="mb-2"><i class="fas fa-birthday-cake text-warning me-2"></i> Idade: <?= calcularIdade($l['data_fundacao'] ?? null) ?></div>
                                </div>
                            </div>

                            <!-- Endereço Completo -->
                            <div class="p-2 rounded bg-dark border border-secondary small text-light mb-3">
                                <i class="fas fa-map text-warning me-1"></i> <?= htmlspecialchars($l['endereco'] ?: 'Endereço não cadastrado') ?>
                            </div>
                        </div>

                        <!-- Ações do Card -->
                        <div class="d-flex justify-content-between align-items-center gap-2 pt-2 border-top border-secondary opacity-75">
                            
                            <!-- Botão de ASSOCIAÇÃO (Exibido Apenas para o DONO da conta) -->
                            <div>
                                <?php if ($is_dono): ?>
                                    <?php if ($is_minha_loja): ?>
                                        <a href="lojas.php?desassociar_loja=1" class="btn btn-sm btn-outline-secondary" title="Remover minha associação com esta loja">
                                            <i class="fas fa-unlink me-1"></i> Desassociar
                                        </a>
                                    <?php else: ?>
                                        <?php if (empty($loja_principal_id)): // Só mostra "Associar" se ele ainda não tem nenhuma loja associada ?>
                                            <a href="lojas.php?associar_loja=<?= $l['id'] ?>" class="btn btn-sm btn-outline-success" title="Definir esta como minha Loja Principal">
                                                <i class="fas fa-link me-1"></i> Associar
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Botões de Edição Padrão -->
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-warning" onclick="editarLoja(<?= htmlspecialchars(json_encode($l), ENT_QUOTES, 'UTF-8') ?>)" title="Editar Loja">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="lojas.php?excluir=<?= $l['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Deseja realmente excluir esta loja?')" title="Excluir Loja">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="card-custom text-center py-5">
                    <p class="text-muted mb-0">Nenhuma loja cadastrada no momento.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Modal Cadastro / Edição de Loja -->
<div class="modal fade" id="modalLoja" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background-color: var(--bg-card); color: var(--text-main); border: 1px solid var(--border-color);">
            <form method="POST" action="lojas">
                <input type="hidden" name="id" id="loja_id">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title text-warning" id="modalTitulo" style="font-family: 'Cinzel', serif;">Cadastrar Nova Loja</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Nome da Loja <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nome_loja" id="nome_loja" required placeholder="Ex: ARLS Philotimia nº 93">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Potência</label>
                            <input type="text" class="form-control" name="potencia" id="potencia" placeholder="Ex: GOB, GLMEPE...">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Rito</label>
                            <input type="text" class="form-control" name="rito" id="rito" placeholder="Ex: REAA">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Localização (Cidade/UF)</label>
                            <input type="text" class="form-control" name="localizacao" id="localizacao" placeholder="Ex: Recife-PE">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Dia da Semana</label>
                            <input type="text" class="form-control" name="dia_semana" id="dia_semana" placeholder="Ex: SEGUNDA-FEIRA">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Frequência</label>
                            <input type="text" class="form-control" name="frequencia" id="frequencia" placeholder="Ex: Semanal / Quinzenal">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Horário</label>
                            <input type="text" class="form-control" name="horario" id="horario" placeholder="Ex: 20:00">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Data de Fundação</label>
                            <input type="date" class="form-control" name="data_fundacao" id="data_fundacao">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Telefone</label>
                            <div class="d-flex gap-1">
                                <input type="text" class="form-control" name="telefone" id="telefone" placeholder="Telefone">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">E-mail de Contato</label>
                        <input type="email" class="form-control" name="email" id="email" placeholder="contato@loja.com">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Endereço Completo</label>
                        <textarea class="form-control" name="endereco" id="endereco" rows="2" placeholder="Ex: Rua Álvaro Amorim Imbiribeira, Recife-PE"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">URL da Logo (Imagem)</label>
                        <input type="url" class="form-control" name="url_logo" id="url_logo" placeholder="https://exemplo.com/logo.png">
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Salvar Loja</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function limparFormulario() {
    document.getElementById('loja_id').value = '';
    document.getElementById('nome_loja').value = '';
    document.getElementById('potencia').value = '';
    document.getElementById('rito').value = '';
    document.getElementById('localizacao').value = '';
    document.getElementById('dia_semana').value = '';
    document.getElementById('frequencia').value = '';
    document.getElementById('horario').value = '';
    document.getElementById('data_fundacao').value = '';
    document.getElementById('telefone').value = '';
    document.getElementById('email').value = '';
    document.getElementById('endereco').value = '';
    document.getElementById('url_logo').value = '';
    document.getElementById('modalTitulo').innerText = 'Cadastrar Nova Loja';
}

function editarLoja(l) {
    document.getElementById('loja_id').value = l.id;
    document.getElementById('nome_loja').value = l.nome;
    document.getElementById('potencia').value = l.potencia || '';
    document.getElementById('rito').value = l.rito || '';
    document.getElementById('localizacao').value = l.localizacao || '';
    document.getElementById('dia_semana').value = l.dia_semana || '';
    document.getElementById('frequencia').value = l.frequencia || '';
    document.getElementById('horario').value = l.horario || '';
    document.getElementById('data_fundacao').value = l.data_fundacao || '';
    document.getElementById('telefone').value = l.telefone || '';
    document.getElementById('email').value = l.email || '';
    document.getElementById('endereco').value = l.endereco || '';
    document.getElementById('url_logo').value = l.url_logo || '';
    document.getElementById('modalTitulo').innerText = 'Editar Informações da Loja';
    
    var modal = new bootstrap.Modal(document.getElementById('modalLoja'));
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