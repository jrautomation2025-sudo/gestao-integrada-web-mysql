<?php
//ini_set('display_errors', 1);
//error_reporting(E_ALL);
require_once '../configuracoes/config.php';
session_start(); // Assegura que a sessão está ativa para verificar o login do adm

// ==========================================
// VALIDAÇÃO INICIAL DA PÁGINA & COOKIE DE BLOQUEIO
// ==========================================
$token = $_GET['token'] ?? '';

if (empty($token)) {
    die("<div style='background:#141724; color:#fff; padding:40px; text-align:center; font-family:Inter;'><h3>QR Code inválido ou expirado.</h3></div>");
}

$stmt = $pdo->prepare("SELECT * FROM chancelaria_sessoes WHERE token_presenca = ?");
$stmt->execute([$token]);
$sessao = $stmt->fetch();

if (!$sessao) {
    die("<div style='background:#141724; color:#fff; padding:40px; text-align:center; font-family:Inter;'><h3>Sessão não encontrada.</h3></div>");
}

$sessao_id = $sessao['id'];
$tenant_id = $sessao['tenant_id'];

// Verifica se quem está acessando é o Administrador/Gestor logado no painel
$is_admin_logado = isset($_SESSION['chanceler_logado']) || isset($_SESSION['tenant_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1);

// ==========================================
// NOVA TRAVA: DATA DA SESSÃO E HORÁRIO (19:00)
// ==========================================
$erro_bloqueio_tempo = "";

if (!$is_admin_logado) {
    // Supondo que a coluna de data na sua tabela se chame 'data' ou 'data_sessao'. 
    // Ajuste abaixo caso o nome da coluna no banco seja diferente (ex: $sessao['data_sessao'])
    $data_sessao = $sessao['data'] ?? $sessao['data_sessao'] ?? date('Y-m-d');
    $hora_sessao = $sessao['hora_sessao'] ?? date('H:i');
    
    $data_atual = date('Y-m-d');
    $hora_atual = date('H:i:s');
    $hora_limite = date('H:i:s', strtotime($hora_sessao . ' -1 hour'));

    if ($data_atual < $data_sessao) {
        $erro_bloqueio_tempo = "O check-in para esta sessão só estará disponível no dia " . date('d/m/Y', strtotime($data_sessao)) . ".";
    } elseif ($data_atual === $data_sessao && $hora_atual < $hora_limite) {
        $erro_bloqueio_tempo = "O check-in só será liberado a partir das ". date('H:i:s', strtotime($hora_limite)) . " horas do dia da sessão.";
    }
}

// Nome do cookie único para esta sessão
$cookie_nome = "checkin_realizado_" . $sessao_id;

// Se for administrador, o bloqueio por cookie é ignorado (fica sempre liberado)
$ja_registrado_cookie = $is_admin_logado ? false : isset($_COOKIE[$cookie_nome]);

// ==========================================
// AJAX VIA POST (Processamento da Busca)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_ajax'])) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
    header('Content-Type: application/json; charset=utf-8');

    // Se houver bloqueio de tempo, impede a busca via AJAX para usuários comuns
    if (!empty($erro_bloqueio_tempo)) {
        echo json_encode(['status' => 'erro', 'mensagem' => $erro_bloqueio_tempo]);
        exit;
    }

    $acao = $_POST['acao_ajax'];

    if ($acao === 'buscar_obreiro') {
        $cim = trim($_POST['cim'] ?? '');
        $stmt = $pdo->prepare("SELECT id, nome, cim, grau FROM chancelaria_membros WHERE cim = ? AND tenant_id = ?");
        $stmt->execute([$cim, $tenant_id]);
        $obreiro = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($obreiro) {
            $stmtCheck = $pdo->prepare("SELECT id FROM chancelaria_presencas WHERE sessao_id = ? AND membro_id = ?");
            $stmtCheck->execute([$sessao_id, $obreiro['id']]);
            if ($stmtCheck->fetch()) {
                echo json_encode(['status' => 'ja_registrado', 'mensagem' => 'Presença já foi registrada para este CIM nesta sessão!']);
            } else {
                echo json_encode(['status' => 'sucesso', 'dados' => $obreiro]);
            }
        } else {
            echo json_encode(['status' => 'erro', 'mensagem' => 'CIM não encontrado na base de dados.']);
        }
        exit;
    }

    if ($acao === 'buscar_visitante') {
        $termo = trim($_POST['termo'] ?? '');
        $stmt = $pdo->prepare("SELECT * FROM chancelaria_visitantes WHERE (cim = ? OR nome LIKE ?) AND tenant_id = ? LIMIT 1");
        $stmt->execute([$termo, "%$termo%", $tenant_id]);
        $visitante = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($visitante) {
            echo json_encode(['status' => 'encontrado', 'dados' => $visitante]);
        } else {
            echo json_encode(['status' => 'novo']);
        }
        exit;
    }
}

$presença = 'P';

// ==========================================
// PROCESSAMENTO DO FORMULÁRIO COMUM (POST)
// ==========================================
$msg = "";
$erro = "";
$sucesso_registro = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['acao_ajax'])) {
    if (!empty($erro_bloqueio_tempo)) {
        $erro = $erro_bloqueio_tempo;
    } elseif ($ja_registrado_cookie) {
        $erro = "Este dispositivo já registrou presença nesta sessão.";
    } else {
        $tipo = $_POST['tipo_presenca'] ?? '';

        if ($tipo === 'obreiro') {
            $obreiro_id = $_POST['obreiro_id'] ?? '';
            if (!empty($obreiro_id)) {
                $stmtCheck = $pdo->prepare("SELECT id FROM chancelaria_presencas WHERE sessao_id = ? AND membro_id = ?");
                $stmtCheck->execute([$sessao_id, $obreiro_id]);
                if ($stmtCheck->fetch()) {
                    $erro = "Presença já registrada para este obreiro!";
                } else {
                    $stmtIns = $pdo->prepare("INSERT INTO chancelaria_presencas (tenant_id, sessao_id, membro_id, status_presenca) VALUES (?, ?, ?, ?)");
                    $stmtIns->execute([$tenant_id, $sessao_id, $obreiro_id, $presença]);
                    $msg = "Presença confirmada com sucesso!";
                    $sucesso_registro = true;
                }
            }
        } elseif ($tipo === 'visitante') {
            $nome = trim($_POST['nome'] ?? '');
            $cim_visitante = trim($_POST['cim_visitante'] ?? '');
            $loja_origem = trim($_POST['loja_origem'] ?? '');
            $oriente = trim($_POST['oriente'] ?? '');
            $potencia = trim($_POST['potencia'] ?? '');
            $cargo = trim($_POST['cargo'] ?? '');
            $telefone = trim($_POST['telefone'] ?? '');
            $email = trim($_POST['email'] ?? '');

            // Insere o visitante vinculado à sessão atual
            $stmtCad = $pdo->prepare("INSERT INTO chancelaria_visitantes (tenant_id, sessao_id, nome, cim, grau, loja_origem, oriente, potencia, telefone, email, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmtCad->execute([$tenant_id, $sessao_id, $nome, $cim_visitante, $cargo, $loja_origem, $oriente, $potencia, $telefone ?: null, $email ?: null]);
            
            $msg = "Visitante cadastrado e presença registrada com sucesso!";
            $sucesso_registro = true;
        }

        // Se ocorreu com sucesso e NÃO é o admin, criamos o cookie para travar o dispositivo comum
        if ($sucesso_registro && !$is_admin_logado) {
            setcookie($cookie_nome, "true", time() + 86400, "/");
            $ja_registrado_cookie = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkin - Gestão Integrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { background-color: #141724; color: #e2e8f0; font-family: 'Inter', sans-serif; padding: 20px; }
        .card-custom { background-color: #1d2132; border: 1px solid #333951; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        .btn-gold { background-color: #f5c041; color: #141724; font-weight: 600; }
        .btn-gold:hover { background-color: #dca732; color: #141724; }
        .form-control, .form-select { background-color: #141724; border: 1px solid #333951; color: #fff; }
        .form-control:focus, .form-select:focus { background-color: #141724; border-color: #f5c041; color: #fff; box-shadow: none; }
        .nav-pills .nav-link.active { background-color: #f5c041; color: #141724; font-weight: bold; }
        .nav-pills .nav-link { color: #8b92a5; }
    </style>
</head>
<body>

<div class="container" style="max-width: 500px; margin-top: 20px;">
    <div class="text-center mb-4">
        <i class="fas fa-book-open text-warning mb-2" style="font-size: 2.5rem;"></i>
        <h3 style="font-family: 'Cinzel', serif; color: #f5c041;">Registro de Frequência</h3>
        <p class="text-white small"><?= htmlspecialchars($sessao['titulo'] ?? 'Reunião') ?></p>
        <?php if (isset($data_sessao)): ?>
            <p class="text-muted small mb-0"><i class="far fa-calendar-alt me-1"></i> Data da Sessão: <?= date('d/m/Y', strtotime($data_sessao)) ?> às 19:00</p>
        <?php endif; ?>
        <?php if ($is_admin_logado): ?>
            <div><span class="badge bg-warning text-dark mt-2">Modo Administrador (Acesso Livre)</span></div>
        <?php endif; ?>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-success text-center" role="alert"><i class="fas fa-check-circle me-2"></i> <?= $msg ?></div>
    <?php endif; ?>

    <?php if (!empty($erro)): ?>
        <div class="alert alert-danger text-center" role="alert"><i class="fas fa-exclamation-triangle me-2"></i> <?= $erro ?></div>
    <?php endif; ?>

    <div class="card-custom">
        <?php if (!empty($erro_bloqueio_tempo)): ?>
            <!-- TELA DE BLOQUEIO POR ANTECIPAÇÃO (Data ou Horário) -->
            <div class="text-center py-4">
                <i class="fas fa-clock text-warning mb-3" style="font-size: 3rem;"></i>
                <h5 class="text-warning mb-2">Check-in Indisponível</h5>
                <p class="text-white small mb-0"><?= $erro_bloqueio_tempo ?></p>
            </div>
        <?php elseif ($ja_registrado_cookie): ?>
            <!-- TELA DE BLOQUEIO SE JÁ REGISTRADO (Apenas para usuários comuns) -->
            <div class="text-center py-4">
                <i class="fas fa-check-circle text-success mb-3" style="font-size: 3rem;"></i>
                <h5 class="text-warning mb-2">Presença já confirmada</h5>
                <p class="text-white small mb-0">Sua frequência para esta sessão já foi registrada neste dispositivo. O acesso a novos registros foi bloqueado.</p>
            </div>
        <?php else: ?>
            <!-- FORMULÁRIOS NORMAIS -->
            <ul class="nav nav-pills nav-fill mb-4" id="pills-tab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pills-obreiro-tab" data-bs-toggle="pill" data-bs-target="#pills-obreiro" type="button" role="tab">Sou da Loja</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-visitante-tab" data-bs-toggle="pill" data-bs-target="#pills-visitante" type="button" role="tab">Visitante</button>
                </li>
            </ul>

            <div class="tab-content" id="pills-tabContent">
                <!-- AB: OBREIRO DA CASA -->
                <div class="tab-pane fade show active" id="pills-obreiro" role="tabpanel">
                    <div class="mb-3">
                        <label class="form-label text-white small">Digite o seu CIM</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-warning"><i class="fas fa-id-card"></i></span>
                            <input type="tel" id="inputCimObreiro" class="form-control form-control-lg" oninput="this.value = this.value.replace(/\D/g, '')" placeholder="Ex: 123456" autofocus>
                            <button class="btn btn-outline-warning" type="button" onclick="buscarObreiro()">Buscar</button>
                        </div>
                    </div>

                    <div id="resultadoObreiro" class="d-none">
                        <div class="p-3 mb-3 rounded border border-secondary bg-dark">
                            <p class="mb-1 text-white small">Nome Encontrado:</p>
                            <h5 id="nomeObreiroExibicao" class="text-warning mb-2"></h5>
                            <p class="mb-0 text-white small">CIM: <span id="cimObreiroExibicao" class="text-white"></span></p>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="tipo_presenca" value="obreiro">
                            <input type="hidden" name="obreiro_id" id="inputObreiroId">
                            <button type="submit" class="btn btn-gold w-100 py-2">Confirmar Minha Presença</button>
                        </form>
                    </div>
                </div>

                <!-- AB: VISITANTE -->
                <div class="tab-pane fade" id="pills-visitante" role="tabpanel">
                    <div class="mb-3">
                        <label class="form-label text-white small">Pesquisar por CIM ou Nome (Caso já tenha visitado antes)</label>
                        <div class="input-group">
                            <input type="tel" id="inputBuscaVisitante" class="form-control" oninput="this.value = this.value.replace(/\D/g, '')" placeholder="Digite CIM ou Nome...">
                            <button class="btn btn-outline-warning" type="button" onclick="buscarVisitante()">Verificar</button>
                        </div>
                    </div>

                    <form method="POST" id="formVisitante">
                        <input type="hidden" name="tipo_presenca" value="visitante">
                        <input type="hidden" name="visitante_id" id="inputVisitanteId" value="">
                        
                        <div class="mb-3">
                            <label class="form-label text-white small">Nome Completo</label>
                            <input type="text" name="nome" id="visNome" class="form-control" placeholder="Seu nome completo" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white small">CIM</label>
                            <input type="tel" name="cim_visitante" id="visCim" class="form-control" placeholder="CIM" oninput="this.value = this.value.replace(/\D/g, '')" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white small">Loja de Origem</label>
                            <input type="text" name="loja_origem" id="visLoja" class="form-control" placeholder="Ex: Acácia Negra nº 15" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white small">Oriente</label>
                            <input type="text" name="oriente" id="visOriente" class="form-control" placeholder="Ex: Carpina PE" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white small">Potência / Obediência</label>
                            <input type="text" name="potencia" id="visPotencia" class="form-control" placeholder="Ex: GOB, GLP..." required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white small">Grau / Cargo</label>
                            <select name="cargo" id="visCargo" class="form-select" required>
                                <option value="" disabled selected>Selecione o grau...</option>
                                <option value="1">Aprendiz</option>
                                <option value="2">Companheiro</option>
                                <option value="3">Mestre</option>
                                <option value="4">Mestre Instalado</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white small">Telefone / WhatsApp (Para receber certificado)</label>
                            <input type="tel" name="telefone" id="visTelefone" class="form-control" placeholder="Ex: (81) 99999-9999" oninput="this.value = this.value.replace(/\D/g, '')">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white small">E-mail (Para receber certificado)</label>
                            <input type="email" name="email" id="visEmail" class="form-control" placeholder="seu@email.com">
                        </div>
                        <button type="submit" id="btnSalvarVisitante" class="btn btn-gold w-100 py-2 mt-2">Cadastrar e Registrar Presença</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function buscarObreiro() {
    const cim = document.getElementById('inputCimObreiro').value.trim();
    if (!cim) return;

    const formData = new URLSearchParams();
    formData.append('acao_ajax', 'buscar_obreiro');
    formData.append('cim', cim);

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'sucesso') {
            document.getElementById('nomeObreiroExibicao').innerText = data.dados.nome;
            document.getElementById('cimObreiroExibicao').innerText = data.dados.cim;
            document.getElementById('inputObreiroId').value = data.dados.id;
            document.getElementById('resultadoObreiro').classList.remove('d-none');
        } else {
            alert(data.mensagem);
            document.getElementById('resultadoObreiro').classList.add('d-none');
            document.getElementById('inputCimObreiro').value = '';
        }
    })
    .catch(err => console.error("Erro na requisição:", err));
}

function buscarVisitante() {
    const termo = document.getElementById('inputBuscaVisitante').value.trim();
    if (!termo) return;

    const formData = new URLSearchParams();
    formData.append('acao_ajax', 'buscar_visitante');
    formData.append('termo', termo);

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'encontrado') {
            document.getElementById('inputVisitanteId').value = data.dados.id;
            document.getElementById('visNome').value = data.dados.nome;
            document.getElementById('visCim').value = data.dados.cim || '';
            document.getElementById('visLoja').value = data.dados.loja_origem || '';
            document.getElementById('visOriente').value = data.dados.oriente || '';
            document.getElementById('visPotencia').value = data.dados.potencia || '';
            document.getElementById('visCargo').value = data.dados.grau || '';
            document.getElementById('visTelefone').value = data.dados.telefone || '';
            document.getElementById('visEmail').value = data.dados.email || '';
            
            alert("Visitante encontrado na base! Dados carregados com sucesso.");
            document.getElementById('btnSalvarVisitante').innerText = "Confirmar Presença (Visitante Cadastrado)";
        } else {
            alert("Visitante não encontrado. Preencha o formulário abaixo para realizar o cadastro.");
            document.getElementById('inputVisitanteId').value = '';
            document.getElementById('inputBuscaVisitante').value = '';
            document.getElementById('formVisitante').reset();
            document.getElementById('btnSalvarVisitante').innerText = "Cadastrar e Registrar Presença";
        }
    })
    .catch(err => console.error("Erro na requisição:", err));
}
</script>
</body>
</html>
