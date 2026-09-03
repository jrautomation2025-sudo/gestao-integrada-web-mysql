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

// Cria o formatador para Português do Brasil
// O padrão "dd 'de' MMMM 'de' yyyy" gera "14 de agosto de 2026"
$formatador = new IntlDateFormatter(
    'pt_BR', 
    IntlDateFormatter::NONE, 
    IntlDateFormatter::NONE, 
    null, 
    null, 
    "dd 'de' MMMM 'de' yyyy"
);

$data_hoje = $formatador->format(time());

// Busca dados do perfil do usuário logado (opcional)
try {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$tenant_id]);
    $meuPerfil = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $meuPerfil = [];
}

// ==========================================
// BUSCA AS LOJAS CADASTRADAS PARA O SELECT
// ==========================================
$lojas = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM secretaria_lojas ORDER BY nome ASC");
    $stmt->execute();
    $lojas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $lojas = [];
}

// ==========================================
// BUSCA O VENERÁVEL E SECRETÁRIO DA CHANCELARIA
// ==========================================
$nomeVeneravel = 'Irmão Venerável'; // Valor padrão
$nomeSecretario = 'Irmão Secretário'; // Valor padrão

try {
    $stmt = $pdo->prepare("SELECT nome, cargo FROM chancelaria_membros WHERE tenant_id = ? AND cargo IN ('veneravel','secretario')");
    $stmt->execute([$tenant_id]);
    $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($membros) {
        foreach ($membros as $membro) {
            // Verifica o cargo e atribui à variável correta
            if (strtolower($membro['cargo']) === 'veneravel') {
                $nomeVeneravel = $membro['nome'];
            } elseif (strtolower($membro['cargo']) === 'secretario') {
                $nomeSecretario = $membro['nome'];
            }
        }
    }
} catch (PDOException $e) {
    // Caso dê erro, as variáveis continuam com os valores padrão definidos acima
}

$mensagem = $_SESSION['mensagem'] ?? '';
$erro = $_SESSION['erro'] ?? '';
unset($_SESSION['mensagem'], $_SESSION['erro']);

// Processamento do Envio para o n8n
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disparar_webhook'])) {
    
    // Captura os dados preenchidos no formulário
    $dados_envio = [
        'tenant_id'          => $tenant_id,
        'loja_id'            => trim($_POST['loja_id'] ?? ''),
        'loja_nome'          => trim($_POST['loja_nome'] ?? ''),
        'loja_numero'        => trim($_POST['loja_numero'] ?? ''),
        'potencia_nome'      => trim($_POST['potencia_nome'] ?? ''),
        'data_fundacao'      => trim($_POST['data_fundacao'] ?? ''),
        'tipo_sessao'        => trim($_POST['tipo_sessao'] ?? ''),
        'subtitulo_sessao'   => trim($_POST['subtitulo_sessao'] ?? ''),
        'destaque_sessao'    => trim($_POST['destaque_sessao'] ?? ''),
        'texto_jornada'      => trim($_POST['texto_jornada'] ?? ''),
        'data_sessao'        => trim($_POST['data_sessao'] ?? ''),
        'horario_sessao'     => trim($_POST['horario_sessao'] ?? ''),
        'local_endereco'     => trim($_POST['local_endereco'] ?? ''),
        'url_imagem_nuvens'  => trim($_POST['url_imagem_nuvens'] ?? ''),
        'url_brasao_loja'    => trim($_POST['url_brasao_loja'] ?? ''),
        'url_logo_esquadro'  => trim($_POST['url_logo_esquadro'] ?? ''),
        'nome_veneravel'     => trim($_POST['nome_veneravel'] ?? ''),
        'nome_secretario'    => trim($_POST['nome_secretario'] ?? '')
    ];

    // URL do Webhook do seu n8n
    $webhook_url = "https://n8n-prod.jrtec.com.br/webhook/disparar-convite-maconico";

    // Dispara via cURL para o n8n
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados_envio));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-gestao-api-key: ' . getenv('API_TOKEN')
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        $_SESSION['mensagem'] = "Convite enviado com sucesso para o fluxo do n8n!";
    } else {
        $_SESSION['erro'] = "Erro ao comunicar com o n8n. Código HTTP: " . $http_code;
    }

    header("Location: convites");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretaria - Disparar Convite via n8n</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root { --bg-main: #141724; --bg-card: #1d2132; --text-main: #e2e8f0; --gold: #f5c041; --border-color: #333951; }
        body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 260px; padding: 30px 40px; width: calc(100% - 260px); }
        .card-custom { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        .btn-gold { background-color: var(--gold); color: #141724; font-weight: 600; border: none; }
        .btn-gold:hover { background-color: #dca732; color: #141724; }
        .form-control, .form-select { background-color: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); }
        .form-control:focus, .form-select:focus { border-color: var(--gold); box-shadow: 0 0 0 0.25rem rgba(245, 192, 65, 0.25); color: var(--text-main); background-color: var(--bg-main); }
        
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
    <span class="text-white small">Disparar Convite</span>
</div>

<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>

<?php include 'menu.php'; ?>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;">
                <i class="fa-solid fa-users-gear text-warning"></i> Disparar Convite Sessões Especiais
            </h2>
            <p class="text-warning mb-0">Preencha os dados da Sessão para gerar e disparar o convite automatizado.</p>
        </div>
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

    <div class="card-custom">
        <form method="POST" action="convites">
            
            <h5 class="text-warning mb-3"><i class="fas fa-landmark me-2"></i> 1. Informações da Loja</h5>
            
            <!-- SELETOR DE LOJA -->
            <div class="row mb-3">
                <div class="col-12">
                    <label class="form-label fw-bold text-info">Preencher automaticamente da base (Opcional):</label>
                    <select class="form-select border-info" id="select_loja" onchange="preencherDadosLoja()">
                        <option value="">-- Selecione uma Loja para preencher os dados abaixo --</option>
                        <?php foreach ($lojas as $l): ?>
                            <!-- Os atributos data-* guardam as informações que o Javascript vai ler -->
                            <option value="<?= $l['id'] ?>"
                                data-id="<?= htmlspecialchars($l['id']) ?>"
                                data-nome="<?= htmlspecialchars($l['nome']) ?>"
                                data-potencia="<?= htmlspecialchars($l['potencia']) ?>"
                                data-fundacao="<?= !empty($l['data_fundacao']) ? date('d/m/Y', strtotime($l['data_fundacao'])) : '' ?>"
                                data-horario="<?= htmlspecialchars($l['horario']) ?>"
                                data-endereco="<?= htmlspecialchars($l['endereco']) ?>">
                                 <!-- data-logo="<?= htmlspecialchars($l['url_logo']) ?>">-->
                                <?= htmlspecialchars($l['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <input type="hidden" id="loja_id" name="loja_id"/>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Nome da Loja</label>
                    <input type="text" class="form-control" name="loja_nome" id="loja_nome" required>
                    <p style="font-size: 0.8rem; color: #64748b;">Preenchimento automático</p>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label fw-bold">Nº da Loja</label>
                    <input type="text" class="form-control" name="loja_numero" id="loja_numero" required>
                    <p style="font-size: 0.8rem; color: #64748b;">Preenchimento automático</p>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Potência / Obediência</label>
                    <input type="text" class="form-control" name="potencia_nome" id="potencia_nome"required>
                    <p style="font-size: 0.8rem; color: #64748b;">Preenchimento automático</p>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Data de Fundação</label>
                    <input type="text" class="form-control" name="data_fundacao" id="data_fundacao">
                    <p style="font-size: 0.8rem; color: #64748b;">Preenchimento automático</p>
                </div>
            </div>

            <hr class="border-secondary my-4">

            <h5 class="text-warning mb-3"><i class="fas fa-scroll me-2"></i> 2. Detalhes da Sessão</h5>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Tipo de Sessão</label>
                    <input type="text" class="form-control" name="tipo_sessao" value="SESSÃO SOLENE" required placeholder="Ex: SESSÃO MAGNA DE INICIAÇÃO">
                    <p style="font-size: 0.8rem; color: #64748b;">Informe aqui o tipo da sessão</p>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Subtítulo</label>
                    <input type="text" class="form-control" name="subtitulo_sessao" value="EM COMEMORAÇÃO AOS">
                    <p style="font-size: 0.8rem; color: #64748b;">Informe aqui o título da sessãoe</p>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Destaque Principal</label>
                    <input type="text" class="form-control" name="destaque_sessao" value="62 ANOS DE FUNDAÇÃO" required>
                    <p style="font-size: 0.8rem; color: #64748b;">Informe aqui o destaque da sessão</p>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Texto / Mensagem da Jornada</label>
                <textarea class="form-control" name="texto_jornada" rows="2">Uma jornada de fé, trabalho, virtude e união que se renova a cada geração, guiada aos princípios eternos da Maçonaria.</textarea>
                <p style="font-size: 0.8rem; color: #64748b;">Informe uma nova mensagem ou deixe a padrão</p>
            </div>

            <hr class="border-secondary my-4">

            <h5 class="text-warning mb-3"><i class="fas fa-calendar-alt me-2"></i> 3. Data, Horário e Local</h5>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Data da Sessão</label>
                    <input type="text" class="form-control" name="data_sessao" value="<?= ucfirst($data_hoje) ?>" required>
                    <p style="font-size: 0.8rem; color: #64748b;">Informe a data da sessão</p>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Horário</label>
                    <input type="text" class="form-control" name="horario_sessao" id="horario_sessao" value="19:30" required>
                    <p style="font-size: 0.8rem; color: #64748b;">Informe o horário da sessão</p>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Endereço do Templo</label>
                    <input type="text" class="form-control" name="local_endereco" id="local_endereco" required>
                    <p style="font-size: 0.8rem; color: #64748b;">Informe o local da sessão</p>
                </div>
            </div>

            <hr class="border-secondary my-4">

            <h5 class="text-warning mb-3"><i class="fas fa-images me-2"></i> 4. URLs de Imagens (Assets)</h5>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">URL da Imagem de Nuvens (Fundo)</label>
                    <input type="url" class="form-control" name="url_imagem_nuvens" value="https://ucpnldlufzmgurczsbwz.supabase.co/storage/v1/object/public/receipt-members/images/2026-08-11_17-49.png">
                    <p style="font-size: 0.8rem; color: #64748b;">Altere aqui a imagem de fundo do convite</p>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">URL do Brasão da Loja</label>
                    <input type="url" class="form-control" name="url_brasao_loja" id="url_brasao_loja" value="https://ucpnldlufzmgurczsbwz.supabase.co/storage/v1/object/public/receipt-members/images/fraternidade.png">
                    <p style="font-size: 0.8rem; color: #64748b;">Altere aqui o brasão da loja do convite</p>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">URL da Logo Pequena (Esquadro)</label>
                    <input type="url" class="form-control" name="url_logo_esquadro" value="https://ucpnldlufzmgurczsbwz.supabase.co/storage/v1/object/public/receipt-members/images/gemini-svg.svg">
                    <p style="font-size: 0.8rem; color: #64748b;">Altere aqui a logo do convite</p>
                </div>
            </div>

            <hr class="border-secondary my-4">

            <h5 class="text-warning mb-3"><i class="fas fa-signature me-2"></i> 5. Assinaturas</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Nome do Venerável Mestre</label>
                    <input type="text" class="form-control" name="nome_veneravel" value="<?= htmlspecialchars($nomeVeneravel) ?>">
                    <p style="font-size: 0.8rem; color: #64748b;">Preenchimento automático</p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Nome do Secretário</label>
                    <input type="text" class="form-control" name="nome_secretario" value="<?= htmlspecialchars($nomeSecretario) ?>">
                    <p style="font-size: 0.8rem; color: #64748b;">Preenchimento automático</p>
                </div>
            </div>

            <div class="text-end mt-4">
                <button type="submit" name="disparar_webhook" class="btn btn-warning btn-lg px-5">
                    <i class="fas fa-paper-plane me-2"></i> Enviar Convite
                </button>
            </div>

        </form>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function preencherDadosLoja() {
    const select = document.getElementById('select_loja');
    const option = select.options[select.selectedIndex];

    if (option.value === "") {
        // Se voltar para a opção vazia, pode limpar os campos se desejar
        return;
    }

    // Pega o nome completo da opção
    let nomeCompleto = option.getAttribute('data-nome') || '';
    let nomeLoja = nomeCompleto;
    let numeroLoja = '';

    // Lógica para tentar extrair o número automaticamente (Ex: "ARLS Philotimia Nº 93")
    const regex = /(.+?)(?:N[º°\.]\s*)(\d+)/i;
    const match = nomeCompleto.match(regex);
    
    if (match) {
        nomeLoja = match[1].trim();   // Pega a parte antes do "Nº"
        numeroLoja = match[2].trim(); // Pega a parte depois do "Nº"
    }

    // Preenche os campos do formulário
    document.getElementById('loja_id').value = option.getAttribute('data-id') || '';
    document.getElementById('loja_nome').value = nomeLoja;
    document.getElementById('loja_numero').value = numeroLoja;
    document.getElementById('potencia_nome').value = option.getAttribute('data-potencia') || '';
    document.getElementById('data_fundacao').value = option.getAttribute('data-fundacao') || '';
    document.getElementById('horario_sessao').value = option.getAttribute('data-horario') || '';
    document.getElementById('local_endereco').value = option.getAttribute('data-endereco') || '';
    //document.getElementById('url_brasao_loja').value = option.getAttribute('data-logo') || '';
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