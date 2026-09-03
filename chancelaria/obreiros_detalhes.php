<?php
session_start();
require_once '../configuracoes/config.php';

if (!isset($_SESSION['tenant_id'])) {
    header('Location: login.php');
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$mensagem = "";

// Busca todos os membros da loja para popular o select
$stmtMembros = $pdo->prepare("SELECT id, nome FROM chancelaria_membros WHERE tenant_id = ? ORDER BY nome ASC");
$stmtMembros->execute([$tenant_id]);
$membros = $stmtMembros->fetchAll(PDO::FETCH_ASSOC);

$membro_selecionado = $_GET['membro_id'] ?? ($membros[0]['id'] ?? null);
$detalhes = [];

// Se um membro foi escolhido, busca os dados detalhados dele (se existirem)
if ($membro_selecionado) {
    $stmtDet = $pdo->prepare("SELECT * FROM chancelaria_membros_detalhes WHERE membro_id = ?");
    $stmtDet->execute([$membro_selecionado]);
    $detalhes = $stmtDet->fetch(PDO::FETCH_ASSOC) ?: [];
}

// Processa o salvamento do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $membro_id = $_POST['membro_id'];
    $rg = trim($_POST['rg']);
    $cpf = trim($_POST['cpf']);
    $cep = trim($_POST['cep']);
    $endereco = trim($_POST['endereco']);
    $numero = trim($_POST['numero']);
    $bairro = trim($_POST['bairro']);
    $cidade = trim($_POST['cidade']);
    $estado = trim($_POST['estado']);
    $telefone_extra = trim($_POST['telefone_extra']);

    // Verifica se já existe registro de detalhes para este membro
    $stmtCheck = $pdo->prepare("SELECT id FROM chancelaria_membros_detalhes WHERE membro_id = ?");
    $stmtCheck->execute([$membro_id]);
    
    if ($stmtCheck->rowCount() > 0) {
        // Atualiza
        $stmtUp = $pdo->prepare("
            UPDATE chancelaria_membros_detalhes 
            SET rg = ?, cpf = ?, cep = ?, endereco = ?, numero = ?, bairro = ?, cidade = ?, estado = ?, telefone_extra = ?
            WHERE membro_id = ?
        ");
        $stmtUp->execute([$rg, $cpf, $cep, $endereco, $numero, $bairro, $cidade, $estado, $telefone_extra, $membro_id]);
    } else {
        // Insere
        $stmtIns = $pdo->prepare("
            INSERT INTO chancelaria_membros_detalhes (membro_id, rg, cpf, cep, endereco, numero, bairro, cidade, estado, telefone_extra)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtIns->execute([$membro_id, $rg, $cpf, $cep, $endereco, $numero, $bairro, $cidade, $estado, $telefone_extra]);
    }
    
    $mensagem = "Informações salvas com sucesso!";
    // Recarrega os dados atualizados
    $stmtDet->execute([$membro_id]);
    $detalhes = $stmtDet->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Obreiro - Gestão Integrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        input::placeholder { color: #a0aec0 !important; opacity: 1 !important; }
    </style>
</head>
<body>
    
    <?php include 'menu.php'?>
    
    <div class="container py-4">
        
        <div>
                <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;"><i class="fas fa-address-book me-2 text-warning"></i> Informações dos Obreiros</h2>
                <p class="text-warning">Gerencie as informações pessoais dos membros</p>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-success"><?= $mensagem ?></div>
        <?php endif; ?>

        <!-- Seletor de Obreiro -->
        <form method="GET" class="mb-4">
            <label class="form-label">Selecione o Obreiro:</label>
            <select name="membro_id" class="form-control bg-secondary text-light" onchange="this.form.submit()">
                <?php foreach ($membros as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= ($m['id'] == $membro_selecionado) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <!-- Formulário de Detalhes -->
        <form method="POST" class="row g-3">
            <input type="hidden" class="form-control text-light" name="membro_id" value="<?= $membro_selecionado ?>">

            <div class="col-md-6">
                <label class="form-label">RG</label>
                <input type="tel" name="rg" class="form-control bg-secondary text-light" oninput="this.value = this.value.replace(/\D/g, '')" placeholder="00.000.000-0" value="<?= htmlspecialchars($detalhes['rg'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">CPF</label>
                <input type="text" name="cpf" class="form-control bg-secondary text-light" placeholder="000.000.000-00" value="<?= htmlspecialchars($detalhes['cpf'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">CEP</label>
                <input type="tel" id="cep" name="cep" class="form-control bg-secondary text-light" maxlength="9" oninput="this.value = this.value.replace(/\D/g, '')" placeholder="00000-000" value="<?= htmlspecialchars($detalhes['cep'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Endereço (Rua, Av, etc.)</label>
                <input type="text" id="endereco" name="endereco" class="form-control bg-secondary text-light" placeholder="Nome da rua" value="<?= htmlspecialchars($detalhes['endereco'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Número</label>
                <input type="tel" name="numero" class="form-control bg-secondary text-light" oninput="this.value = this.value.replace(/\D/g, '')" placeholder="123" value="<?= htmlspecialchars($detalhes['numero'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Bairro</label>
                <input type="text" id="bairro" name="bairro" class="form-control bg-secondary text-light" placeholder="Bairro" value="<?= htmlspecialchars($detalhes['bairro'] ?? '') ?>">
            </div>
            <div class="col-md-5">
                <label class="form-label">Cidade</label>
                <input type="text" id="cidade" name="cidade" class="form-control bg-secondary text-light" placeholder="Cidade" value="<?= htmlspecialchars($detalhes['cidade'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Estado (UF)</label>
                <input type="text" id="estado" name="estado" class="form-control bg-secondary text-light" maxlength="2" placeholder="UF" value="<?= htmlspecialchars($detalhes['estado'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Telefone Alternativo / WhatsApp</label>
                <input type="tel" name="telefone_extra" class="form-control bg-secondary text-light" oninput="this.value = this.value.replace(/\D/g, '')" placeholder="(00) 00000-0000" value="<?= htmlspecialchars($detalhes['telefone_extra'] ?? '') ?>">
            </div>

            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-warning w-100" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>Salvar Informações</button>
            </div>
        </form>
    </div>
    
<script>
document.getElementById('cep').addEventListener('blur', function() {
    let cep = this.value.replace(/\D/g, '');

    if (cep.length === 8) {
        // Mostra um feedback visual leve de carregamento (opcional)
        document.getElementById('endereco').value = 'Buscando...';
        document.getElementById('bairro').value = 'Buscando...';
        document.getElementById('cidade').value = 'Buscando...';
        document.getElementById('estado').value = '...';

        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(response => response.json())
            .then(data => {
                if (!data.erro) {
                    document.getElementById('endereco').value = data.logradouro || '';
                    document.getElementById('bairro').value = data.bairro || '';
                    document.getElementById('cidade').value = data.localidade || '';
                    document.getElementById('estado').value = data.uf || '';
                    // Foca automaticamente no campo de número após preencher
                    document.querySelector('input[name="numero"]').focus();
                } else {
                    alert('CEP não encontrado.');
                    limparCamposCEP();
                }
            })
            .catch(error => {
                console.error('Erro ao buscar o CEP:', error);
                limparCamposCEP();
            });
    } else if (cep.length > 0) {
        alert('Formato de CEP inválido.');
        limparCamposCEP();
    }
});

function limparCamposCEP() {
    document.getElementById('endereco').value = '';
    document.getElementById('bairro').value = '';
    document.getElementById('cidade').value = '';
    document.getElementById('estado').value = '';
}
</script>
</body>
</html>