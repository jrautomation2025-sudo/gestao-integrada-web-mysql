<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
session_start();
require '../configuracoes/config.php'; // Ajuste se seu arquivo de conexão for diferente

// Segurança: Somente o usuario_id = 5 pode acessar essa tela
$user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['user']['id'];
//if ($user_id != 5) {
//    echo "<script>alert('Acesso restrito. Área exclusiva.'); window.location.href='index.php';</script>";
//    exit;
//}

// Lida com requisições AJAX (Salvar Meses e Configuração de Clientes)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Atualizar Mês Específico (Status e Recibo)
    if ($_POST['action'] === 'update_mensalidade') {
        $cliente_id = intval($_POST['cliente_id']);
        $ano = intval($_POST['ano']);
        $mes = intval($_POST['mes']);
        $status = $_POST['status'];
        $recibo = intval($_POST['recibo']);

        $stmt = $pdo->prepare("SELECT id FROM mensalidades WHERE cliente_id = ? AND ano = ? AND mes = ?");
        $stmt->execute([$cliente_id, $ano, $mes]);
        $exist = $stmt->fetchColumn();

        if ($exist) {
            $pdo->prepare("UPDATE mensalidades SET status = ?, recibo_enviado = ? WHERE id = ?")
                ->execute([$status, $recibo, $exist]);
        } else {
            $pdo->prepare("INSERT INTO mensalidades (cliente_id, ano, mes, status, recibo_enviado) VALUES (?, ?, ?, ?, ?)")
                ->execute([$cliente_id, $ano, $mes, $status, $recibo]);
        }

        // NOVO: Se o status alterado for 'OK', cria o registro automático na tabela de transações
        if ($status === 'OK') {
            // Busca o nome do membro e o valor da mensalidade dele
            $stmtCli = $pdo->prepare("SELECT nome, valor_mensalidade FROM clientes WHERE id = ?");
            $stmtCli->execute([$cliente_id]);
            $clienteInfo = $stmtCli->fetch(PDO::FETCH_ASSOC);

            if ($clienteInfo) {
                $nomeMembro = $clienteInfo['nome'];
                $valorMensalidade = $clienteInfo['valor_mensalidade'] ?? 0.00;
                $descricaoTransacao = "Pagamento de mesalidade do irmão " . $nomeMembro;
                $dataAtual = date('Y-m-d');
                $tipoTransacao = 'mensalidade'; // ou Crédito, dependendo da estrutura da sua tabela de transações

                // Insere na tabela transacoes (ajuste o nome das colunas caso sua tabela seja diferente, ex: usuario_id ou tenant_id)
                $stmtTrans = $pdo->prepare("INSERT INTO transacoes (usuario_id, descricao, valor, data_transacao, tipo) VALUES (?, ?, ?, ?, ?)");
                // Caso sua tabela use tenant_id em vez de usuario_id, substitua o primeiro parâmetro conforme sua base
                try {
                    $stmtTrans->execute([$user_id, $descricaoTransacao, $valorMensalidade, $dataAtual, $tipoTransacao]);
                } catch (Exception $ex) {
                    // Fallback caso a tabela transacoes tenha colunas com nomes ligeiramente diferentes
                    $stmtTransAlt = $pdo->prepare("INSERT INTO transacoes (tenant_id, descricao, valor, data_transacao) VALUES (?, ?, ?, ?)");
                    $stmtTransAlt->execute([$user_id, $descricaoTransacao, $valorMensalidade, $dataAtual]);
                }
            }
        }

        echo json_encode(['success' => true]);
        exit;
    }
    
    // Atualizar Cadastro Base (Ativo/Inativo, Recolhe, Valor, Duplo Filiado)
    if ($_POST['action'] === 'update_cliente') {
        $cliente_id = intval($_POST['cliente_id']);
        $situacao = $_POST['situacao'];
        $recolhe = $_POST['recolhe'];
        $duplo_filiado = $_POST['duplo_filiado']; 
        
        // Conversão robusta de moeda BR para DB (ex: 1.250,50 -> 1250.50)
        $valorStr = str_replace('.', '', $_POST['valor']);
        $valor = floatval(str_replace(',', '.', $valorStr)); 
        
        // UPDATE atualizado
        $pdo->prepare("UPDATE clientes SET situacao = ?, recolhe = ?, valor_mensalidade = ?, duplo_filiado = ? WHERE id = ? AND usuario_id = ?")
            ->execute([$situacao, $recolhe, $valor, $duplo_filiado, $cliente_id, $user_id]);
        
        echo json_encode(['success' => true]);
        exit;
    }
}

// Preparativos da Tela
$ano_filtro = $_GET['ano'] ?? date('Y');
$mesesPT = [1=>'Jan', 2=>'Fev', 3=>'Mar', 4=>'Abr', 5=>'Mai', 6=>'Jun', 7=>'Jul', 8=>'Ago', 9=>'Set', 10=>'Out', 11=>'Nov', 12=>'Dez'];
$mesesFull = [1=>'Janeiro', 2=>'Fevereiro', 3=>'Março', 4=>'Abril', 5=>'Maio', 6=>'Junho', 7=>'Julho', 8=>'Agosto', 9=>'Setembro', 10=>'Outubro', 11=>'Novembro', 12=>'Dezembro'];

// Busca todos os membros e cruza com as mensalidades do Ano
$sql = "SELECT c.id, c.nome, c.telefone, c.situacao, c.recolhe, c.valor_mensalidade, c.duplo_filiado,
               m.mes, m.status, m.recibo_enviado
        FROM clientes c
        LEFT JOIN mensalidades m ON c.id = m.cliente_id AND m.ano = ?
        WHERE c.usuario_id = ?
        ORDER BY c.nome ASC, m.mes ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$ano_filtro, $user_id]);
$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca o nome da loja
$sql = "SELECT nome
        FROM usuarios
        WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$loja = $stmt->fetch(PDO::FETCH_ASSOC);

$nomeloja = $loja['nome'];

// Busca o nome do tesoureiro
$sql = "SELECT nome
        FROM chancelaria_membros 
        WHERE tenant_id = ?
        AND cargo = 'tesoureiro'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$membro = $stmt->fetch(PDO::FETCH_ASSOC);

$nometesoureiro = $membro['nome'];

// Estruturando array no formato da planilha (1 linha por cliente com 12 colunas)
$clientes = [];
foreach ($resultados as $row) {
    $cid = $row['id'];
    if (!isset($clientes[$cid])) {
        $clientes[$cid] = [
            'id' => $row['id'],
            'nome' => $row['nome'],
            'telefone' => $row['telefone'],
            'situacao' => $row['situacao'] ?? 'Ativo',
            'recolhe' => $row['recolhe'] ?? 'Sim',
            'valor' => $row['valor_mensalidade'] ?? 160.00,
            'duplo_filiado' => $row['duplo_filiado'] ?? 'Não', 
            'meses' => array_fill(1, 12, ['status' => 'Pendente', 'recibo' => 0])
        ];
    }
    if ($row['mes']) {
        $clientes[$cid]['meses'][$row['mes']] = [
            'status' => $row['status'],
            'recibo' => $row['recibo_enviado']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensalidades - Gestão Integrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { --bg-dark: #0f172a; --bg-card: #1e293b; --gold: #cfa34e; --text-light: #f1f5f9; }
        body { background-color: var(--bg-dark); color: var(--text-light); font-family: 'Segoe UI', sans-serif; }

        .card-custom { background: var(--bg-card); border: 1px solid #334155; border-radius: 12px; padding: 20px; }
        .text-gold { color: var(--gold) !important; }
        .btn-gold { background: var(--gold); border: none; color: #000; font-weight: bold; }
        .btn-gold:hover { background: #b8860b; color: #fff; }

        /* HEADER MOBILE */
        .mobile-header {
            display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px;
            background-color: var(--bg-card); border-bottom: 1px solid #334155;
            z-index: 2000; align-items: center; padding: 0 20px; justify-content: space-between;
        }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); z-index: 3000; width: 280px; transition: 0.3s; position: fixed; height: 100vh;}
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 15px; padding-top: 80px; }
            .mobile-header { display: flex !important; }
        }

        /* Tabela Excel Like */
        .table-responsive { overflow-x: auto; }
        .sticky-col { position: sticky; left: 0; background-color: var(--bg-card) !important; z-index: 1; border-right: 2px solid #334155; }
        .cell-month { cursor: pointer; transition: background 0.2s; min-width: 70px; }
        .cell-month:hover { background-color: #334155 !important; }
        .icon-recibo { font-size: 0.8rem; margin-top: 5px; }

        @media print {
            @page { size: landscape; margin: 10mm; } 
            body { background-color: #fff !important; color: #000 !important; padding: 0 !important; margin: 0 !important; font-size: 10pt; }
            .sidebar, .mobile-header, form, .no-print, .btn-link, .fa-cog { display: none !important; }
            .main-content { margin: 0 !important; width: 100% !important; padding: 0 !important; }
            .card-custom { border: none !important; padding: 0 !important; background: transparent !important; }
            .table { border-collapse: collapse !important; width: 100% !important; }
            .table th, .table td { border: 1px solid #999 !important; background: transparent !important; color: #000 !important; padding: 4px !important; }
            .table-secondary th { background-color: #e9ecef !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .text-light, .text-white { color: #000 !important; }
            h3 { font-size: 16pt !important; margin-bottom: 5px !important; color: #000 !important; }
            .badge { border: 1px solid #000 !important; color: #000 !important; background: transparent !important; font-weight: bold; }
            .text-success { color: #198754 !important; font-weight: bold !important; -webkit-print-color-adjust: exact; }
            .text-danger { color: #dc3545 !important; font-weight: bold !important; -webkit-print-color-adjust: exact; }
            .text-warning { color: #d97706 !important; font-weight: bold !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="mobile-header">
    <div class="d-flex align-items-center">
        <button class="btn btn-outline-warning btn-sm me-3" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <span style="font-family: 'Cinzel', serif; color: var(--gold); font-weight: bold;">TESOURARIA</span>
    </div>
    <span class="text-white small">Painel</span>
</div>

<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>

    <div class="no-print">
        <?php include 'menu.php'; ?>
    </div>

    <div class="main-content">
        <div class="container-fluid py-4 px-md-4">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div class="page-header mb-4">
                    <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;"><i class="fas fa-table me-2 text-warning"></i> Controle de Mensalidades - <?php echo $ano_filtro; ?></h2>
                    <p class="text-warning">Controle aqui os Pagamentos dos membros</p>
                </div>
                
                <div class="d-flex gap-2">
                    <form method="GET" class="m-0">
                        <select name="ano" id="ano_filtro" class="form-select form-select-sm bg-dark text-light border-secondary" onchange="this.form.submit()">
                            <?php for($i = date('Y') + 1; $i >= 2024; $i--): ?>
                                <option value="<?= $i ?>" <?= $i == $ano_filtro ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </form>
                    
                    <button type="button" onclick="window.print()" class="btn btn-sm btn-warning no-print" title="Imprimir Relatório">
                        <i class="fas fa-print me-1"></i> Imprimir
                    </button>
                </div>
            </div>

            <div class="card-custom p-0 overflow-hidden">
                <div class="table-responsive m-0">
                    <table class="table table-dark table-hover table-bordered text-center align-middle mb-0" style="font-size: 0.85rem; white-space: nowrap;">
                        <thead class="table-secondary text-dark">
                            <tr>
                                <th class="sticky-col text-start px-3">Membro</th>
                                <th>Situação</th>
                                <th>Recolhe</th>
                                <th>Valor</th>
                                <?php foreach($mesesPT as $m): ?>
                                    <th><?= $m ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($clientes as $c): ?>
                            <tr>
                                <td class="sticky-col text-start px-3 fw-bold text-light">
                                    <?= htmlspecialchars($c['nome']) ?>
                                    <button class="btn btn-sm btn-link text-gold p-0 ms-2 no-print" onclick="openClienteModal(<?= $c['id'] ?>, '<?= addslashes($c['nome']) ?>', '<?= $c['situacao'] ?>', '<?= $c['recolhe'] ?>', '<?= number_format($c['valor'], 2, ',', '') ?>', '<?= $c['duplo_filiado'] ?>')" title="Editar Membro"><i class="fas fa-cog"></i></button>
                                </td>
                                <td>
                                    <span class="badge <?= $c['situacao'] == 'Ativo' ? 'bg-primary' : 'bg-secondary' ?>"><?= $c['situacao'] ?></span>
                                </td>
                                <td><?= $c['recolhe'] ?></td>
                                <td>R$ <?= number_format($c['valor'], 2, ',', '.') ?></td>
                                
                                <?php if($c['recolhe'] == 'Não'): ?>
                                    <?php for($i = 1; $i <= 12; $i++): ?>
                                        <td class="text-muted bg-dark" style="opacity: 0.5; font-size: 0.75rem;">N/A</td>
                                    <?php endfor; ?>
                                <?php else: ?>
                                    <?php for($i = 1; $i <= 12; $i++): 
                                        $m = $c['meses'][$i]; 
                                    ?>
                                        <td class="cell-month" onclick="openMensalidadeModal(<?= $c['id'] ?>, '<?= addslashes($c['nome']) ?>', <?= $i ?>, '<?= $mesesFull[$i] ?>', '<?= $m['status'] ?>', <?= $m['recibo'] ?>, '<?= $c['telefone'] ?>')">
                                            
                                            <?php if ($m['status'] == 'OK'): ?>
                                                <span class="text-success fw-bold">OK</span>
                                            <?php elseif ($m['status'] == 'NOK'): ?>
                                                <span class="text-danger fw-bold">NOK</span>
                                            <?php elseif ($m['status'] == 'N/A'): ?>
                                                <span class="text-warning fw-bold" style="font-size: 0.75rem;">N/A</span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                            
                                            <?php if ($m['status'] != 'Pendente'): ?>
                                                <br>
                                                <?php if ($m['recibo'] == 1): ?>
                                                    <i class="fas fa-check-circle text-info icon-recibo" title="Recibo Enviado"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-envelope text-secondary icon-recibo" title="Recibo Não Enviado"></i>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                        </td>
                                    <?php endfor; ?>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- MODAL 1: EDITAR MENSALIDADE E RECIBO -->
    <div class="modal fade no-print" id="modalMensalidade" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content bg-card border-secondary text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title fs-6 fw-bold text-gold" id="modalMensalidadeLabel">Detalhes</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formMensalidade">
                    <div class="modal-body">
                        <input type="hidden" id="m_cliente_id" name="cliente_id">
                        <input type="hidden" id="m_mes" name="mes">
                        
                        <div class="mb-3">
                            <label class="form-label text-muted small">Status Financeiro</label>
                            <select id="m_status" name="status" class="form-select form-select-sm bg-dark text-light border-secondary">
                                <option value="Pendente">Pendente (-)</option>
                                <option value="OK">OK (Pago)</option>
                                <option value="NOK">NOK (Em Débito)</option>
                                <option value="N/A">N/A (Não se Aplica)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small">Controle de Recibo</label>
                            <select id="m_recibo" name="recibo" class="form-select form-select-sm bg-dark text-light border-secondary">
                                <option value="0">Não Enviado</option>
                                <option value="1">Enviado (OK)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary p-2 d-flex justify-content-between">
                        <button type="button" id="btnWhats" class="btn btn-sm btn-primary" title="Enviar Recibo"><i class="fas fa-paper-plane"></i></button>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-sm btn-warning">Salvar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 2: CONFIGURAR CLIENTE -->
    <div class="modal fade no-print" id="modalCliente" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content bg-card border-secondary text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title fs-6 fw-bold text-gold">Configurar Membro</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formCliente">
                    <div class="modal-body">
                        <input type="hidden" id="c_cliente_id" name="cliente_id">
                        <div class="mb-2">
                            <label class="form-label text-muted small mb-1">Membro</label>
                            <input type="text" id="c_nome" class="form-control form-control-sm bg-dark text-light border-secondary" disabled>
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-muted small mb-1">Situação</label>
                            <select id="c_situacao" name="situacao" class="form-select form-select-sm bg-dark text-light border-secondary">
                                <option value="Ativo">Ativo</option>
                                <option value="Inativo">Inativo</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-muted small mb-1">Recolhe Mensalidade?</label>
                            <select id="c_recolhe" name="recolhe" class="form-select form-select-sm bg-dark text-light border-secondary">
                                <option value="Sim">Sim</option>
                                <option value="Não">Não</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-muted small mb-1">Duplo Filiado?</label>
                            <select id="c_duplo" name="duplo_filiado" class="form-select form-select-sm bg-dark text-light border-secondary">
                                <option value="Não">Não</option>
                                <option value="Sim">Sim</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-muted small mb-1">Valor Padrão (R$)</label>
                            <input type="text" id="c_valor" name="valor" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="160,00">
                        </div>
                    </div>
                    <div class="modal-footer border-secondary p-2">
                        <button type="button" class="btn btn-sm btn-outline-light" data-bs-dismiss="modal">Fechar</button>
                        <button type="submit" class="btn btn-sm btn-warning">Atualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const usuarioLogadoId = <?php echo json_encode($user_id); ?>;
        const nomeLoja = <?php echo json_encode($nomeloja); ?>;
        const nometesoureiro = <?php echo json_encode($nometesoureiro); ?>;
        
        function openMensalidadeModal(clienteId, clienteNome, mesNum, mesNome, status, recibo, telefone) {
            document.getElementById('m_cliente_id').value = clienteId;
            document.getElementById('m_mes').value = mesNum;
            document.getElementById('m_status').value = status;
            document.getElementById('m_recibo').value = recibo;
            
            document.getElementById('modalMensalidadeLabel').innerText = `${clienteNome} - ${mesNome}`;
            
            let btnWhats = document.getElementById('btnWhats');
            if (telefone && telefone.trim() !== '') {
                btnWhats.style.display = 'inline-block';
                btnWhats.onclick = function() { sendWhatsapp(clienteId, telefone, clienteNome, mesNome, document.getElementById('ano_filtro').value); };
            } else {
                btnWhats.style.display = 'none';
            }
            
            new bootstrap.Modal(document.getElementById('modalMensalidade')).show();
        }

        function openClienteModal(clienteId, nome, situacao, recolhe, valor, duploFiliado) {
            document.getElementById('c_cliente_id').value = clienteId;
            document.getElementById('c_nome').value = nome;
            document.getElementById('c_situacao').value = situacao;
            document.getElementById('c_recolhe').value = recolhe;
            document.getElementById('c_valor').value = valor;
            document.getElementById('c_duplo').value = duploFiliado; 
            
            new bootstrap.Modal(document.getElementById('modalCliente')).show();
        }
 
        /*
        function sendWhatsapp(telefone, nome, mesNome, ano) {
            let tel = telefone.replace(/\D/g, ''); 
            if (tel.length < 10) { Swal.fire('Erro', 'Telefone inválido.', 'error'); return; }
            if (!tel.startsWith('55')) tel = '55' + tel;
            
            let msg = `Olá ${nome}, tudo bem? Aqui é da Fraternidade. Segue em anexo o recibo referente à mensalidade de ${mesNome}/${ano}. Muito obrigado!`;
            let url = `https://api.whatsapp.com/send?phone=${tel}&text=${encodeURIComponent(msg)}`;
            window.open(url, '_blank');
        }
        */
        
        function sendWhatsapp(clienteId, telefone, nome, mesNome, ano ) {
            let tel = telefone.replace(/\D/g, ''); 
            if (tel.length < 10) { Swal.fire('Erro', 'Telefone inválido.', 'error'); return; }
            if (!tel.startsWith('55')) tel = '55' + tel;
    
            Swal.fire({
                title: 'Confirmar Envio',
                text: 'Deseja enviar o comprovante de pagamento para o membro?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#22c55e',
                cancelButtonColor: '#ef4444',
                confirmButtonText: 'Sim, enviar!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
            
                Swal.fire({
                    title: 'Enviando Comprovante...',
                    html: 'Aguarde enquanto o envio é processado.',
                    allowOutsideClick: false,
                    didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Adiciona a 'acao_interna' para o webhook de membros
            const payload = {
                acao_interna: 'recibo-individual', // Avisa o PHP que é para recibo-individual
                cliente_id: clienteId,
                telefone: tel,
                nome: nome,
                mesNome: mesNome,
                ano: ano,
                nomeLoja: nomeLoja,
                tesoureiro: nometesoureiro,
                usuario_id: typeof usuarioLogadoId !== 'undefined' ? usuarioLogadoId : null
            };

            // Aponta para o PHP local
            fetch('../configuracoes/acionar_webhook_mensagens', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(response => response.json()) // Trata o JSON do PHP
            .then(data => {
                if (data.sucesso) {
                    Swal.fire('Enviado!', 'Seu comprovante foi enfileirado para envio.', 'success')
                    .then(() => {
                        location.reload();
                    });
                } else {
                    throw new Error(data.erro || 'Erro desconhecido');
                }
            })
            .catch(error => {
                console.error('Erro no Webhook:', error);
                Swal.fire('Erro', 'Ocorreu um erro ao tentar disparar o webhook.', 'error');
            });
            }
          });
        }


        document.getElementById('formMensalidade').onsubmit = function(e) {
            e.preventDefault();
            let formData = new FormData(this);
            formData.append('action', 'update_mensalidade');
            formData.append('ano', document.getElementById('ano_filtro').value);
            
            fetch('', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => { if(data.success) location.reload(); });
        };

        document.getElementById('formCliente').onsubmit = function(e) {
            e.preventDefault();
            let formData = new FormData(this);
            formData.append('action', 'update_cliente');
            
            fetch('', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => { if(data.success) location.reload(); });
        };
        
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
</body>
</html>