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

// 1. Processamento de Inserção / Edição de Balaustre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['titulo_balaustre'])) {
    $id = $_POST['id'] ?? null;
    $titulo = trim($_POST['titulo_balaustre'] ?? '');
    $data_balaustre = $_POST['data_balaustre'] ?? '';
    $grau = $_POST['grau'] ?? '1';
    $conteudo = trim($_POST['conteudo'] ?? '');

    if (!empty($titulo) && !empty($data_balaustre)) {
        try {
            if (!empty($id)) {
                // Atualizar
                $stmt = $pdo->prepare("UPDATE secretaria_balaustres SET titulo = ?, data_balaustre = ?, grau = ?, conteudo = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$titulo, $data_balaustre, $grau, $conteudo, $id, $tenant_id]);
                $_SESSION['mensagem'] = "Balaustre atualizado com sucesso!";
            } else {
                // Inserir
                $stmt = $pdo->prepare("INSERT INTO secretaria_balaustres (tenant_id, titulo, data_balaustre, grau, conteudo) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$tenant_id, $titulo, $data_balaustre, $grau, $conteudo]);
                $_SESSION['mensagem'] = "Balaustre cadastrado com sucesso!";
            }
        } catch (PDOException $e) {
            $_SESSION['erro'] = "Erro ao salvar o balaustre: " . $e->getMessage();
        }
    } else {
        $_SESSION['erro'] = "Preencha os campos obrigatórios.";
    }

    header("Location: balaustres.php");
    exit;
}

// 2. Exclusão de Balaustre
if (isset($_GET['excluir'])) {
    $idExcluir = $_GET['excluir'];
    try {
        $stmt = $pdo->prepare("DELETE FROM secretaria_balaustres WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$idExcluir, $tenant_id]);
        $_SESSION['mensagem'] = "Balaustre excluído com sucesso!";
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao excluir o balaustre.";
    }
    header("Location: balaustres.php");
    exit;
}

// Filtro por Mês e Ano
$filtro_mes = isset($_GET['mes']) && $_GET['mes'] !== '' ? $_GET['mes'] : date('m');
$filtro_ano = isset($_GET['ano']) && $_GET['ano'] !== '' ? $_GET['ano'] : date('Y');

// Busca os balaustres cadastrados
$balaustres = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM secretaria_balaustres 
        WHERE tenant_id = ? 
        AND MONTH(data_balaustre) = ? 
        AND YEAR(data_balaustre) = ? 
        ORDER BY data_balaustre DESC
    ");
    $stmt->execute([$tenant_id, $filtro_mes, $filtro_ano]);
    $balaustres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Caso a tabela ainda não exista, evitamos crash
    $balaustres = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretaria - Gestão de Balaustres</title>
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
    <span class="text-white small">Balaustres</span>
</div>

<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>

<?php include 'menu.php'; ?>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;">
                <i class="fas fa-file-alt me-2 text-warning" ></i> Gestão de Balaustres e Atas
            </h2>
            <p class="text-warning mb-0">Cadastre, consulte e gerencie os registros das sessões da Oficina.</p>
        </div>
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalBalaustre" onclick="limparFormulario()" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
            <i class="fas fa-plus me-2"></i> Novo Balaustre
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
        <form method="GET" action="balaustres" class="row g-3 align-items-end">
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
                <a href="balaustres.php" class="btn btn-outline-secondary w-100 text-light">Limpar</a>
            </div>
        </form>
    </div>

    <!-- Tabela de Balaustres -->
    <div class="card-custom">
        <div class="table-responsive">
            <table class="table table-dark-custom">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Título da Ata / Balaustre</th>
                        <th>Grau</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($balaustres) > 0): ?>
                        <?php foreach ($balaustres as $b): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($b['data_balaustre'])) ?></td>
                                <td class="fw-bold text-white"><?= htmlspecialchars($b['titulo']) ?></td>
                                <td><?= ($b['grau'] == 1) ? 'Aprendiz' : (($b['grau'] == 2) ? 'Companheiro' : (($b['grau'] == 3) ? 'Mestre' : (($b['grau'] == 4) ? 'Administrativa' : (($b['grau'] == 5) ? 'Especial' : 'Magna')))) ?></td>
                                <td class="text-center">
                                    <!-- CORREÇÃO NO BOTÃO DENTRO DO FOREACH -->
                                    <button class="btn btn-sm btn-outline-light me-1" onclick="printBalaustre(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($lodge), ENT_QUOTES, 'UTF-8') ?>)" title="Imprimir Balaustre">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning me-1" onclick="editarBalaustre(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8') ?>)" title="Editar" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="balaustres.php?excluir=<?= $b['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Deseja realmente excluir este balaustre?')" title="Excluir" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-light">Nenhum balaustre encontrado para este período.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal Cadastro / Edição de Balaustre -->
<div class="modal fade" id="modalBalaustre" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background-color: var(--bg-card); color: var(--text-main); border: 1px solid var(--border-color);">
            <form method="POST" action="balaustres">
                <input type="hidden" name="id" id="balaustre_id">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title text-warning" id="modalTitulo" style="font-family: 'Cinzel', serif;">Novo Balaustre</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Título da Ata / Balaustre</label>
                        <input type="text" class="form-control" name="titulo_balaustre" id="titulo_balaustre" required placeholder="Ex: Ata da Sessão Ordinária nº 45">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Data da Sessão</label>
                            <input type="date" class="form-control" name="data_balaustre" id="data_balaustre" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Grau</label>
                            <select class="form-select" name="grau" id="grau">
                                <option value="1">1º Grau (Aprendiz)</option>
                                <option value="2">2º Grau (Companheiro)</option>
                                <option value="3">3º Grau (Mestre)</option>
                                <option value="4">Administrativa</option>
                                <option value="5">Especial</option>
                                <option value="6">Magna</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Texto / Conteúdo do Balaustre</label>
                        <textarea class="form-control" name="conteudo" id="conteudo" rows="15" placeholder="Digite ou cole o conteúdo da ata..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"<?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>Cancelar</button>
                    <button type="submit" class="btn btn-warning"<?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>Salvar Balaustre</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function limparFormulario() {
    document.getElementById('balaustre_id').value = '';
    document.getElementById('titulo_balaustre').value = '';
    document.getElementById('data_balaustre').value = '<?= date('Y-m-d') ?>';
    document.getElementById('grau').value = '1';
    document.getElementById('conteudo').value = '';
    document.getElementById('modalTitulo').innerText = 'Novo Balaustre';
}

function editarBalaustre(b) {
    document.getElementById('balaustre_id').value = b.id;
    document.getElementById('titulo_balaustre').value = b.titulo;
    document.getElementById('data_balaustre').value = b.data_balaustre;
    document.getElementById('grau').value = b.grau;
    document.getElementById('conteudo').value = b.conteudo || '';
    document.getElementById('modalTitulo').innerText = 'Editar Balaustre';
    
    var modal = new bootstrap.Modal(document.getElementById('modalBalaustre'));
    modal.show();
}

function printBalaustre(b, lodge) {
    const win = window.open('', '_blank', 'width=800,height=600');
    const dataBr = b.data_balaustre.split('-').reverse().join('/');
    
    // Verifica se tem logo, senão ignora
    const logoHtml = lodge.url_logo ? `<img src="${lodge.url_logo}" style="max-width: 120px; margin-bottom: 10px;">` : '';

    win.document.write(`
        <html>
        <head>
            <title>Balaustre - ${b.titulo}</title>
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
                <div class="document-title">BALAUSTRE / ATA DE SESSÃO</div>
            </div>
            
            <div class="meta">
                Data: ${dataBr} | Grau: ${b.grau == 1 ? 'Aprendiz' : (b.grau == 2 ? 'Companheiro' : (b.grau == 3 ? 'Mestre' : (b.grau == 4 ? 'Administrativa' : (b.grau == 5 ? 'Especial' : 'Magna'))))}
            </div>
            
            <div class="conteudo"><strong>Título:</strong> ${b.titulo}<br><br>${b.conteudo}</div>
            
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

function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar'); 
    const backdrop = document.getElementById('sidebarBackdrop');
    if (sidebar) sidebar.classList.toggle('show');
    if (backdrop) backdrop.classList.toggle('show');
}
</script>
</body>
</html>