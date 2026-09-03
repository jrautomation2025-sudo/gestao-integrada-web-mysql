<?php
session_start();
require '../configuracoes/config.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) { 
    header("Location: ./login.php"); 
    exit; 
}
$user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['user']['id'];
$contexto = $_SESSION['contexto_atual'] ?? 'pessoal';

// 1. Define o mês/ano atual ou pega da URL
$mes_ano_filtro = $_GET['mes_ano'] ?? date('Y-m');
$ano = date('Y', strtotime($mes_ano_filtro));
$mes = date('m', strtotime($mes_ano_filtro));

// 2. Busca os meses que TÊM transações para montar o select de forma inteligente
$sql_meses = "SELECT DISTINCT DATE_FORMAT(data_transacao, '%Y-%m') as mes_ano 
              FROM transacoes 
              WHERE usuario_id = ? AND contexto = ? 
              ORDER BY mes_ano DESC";
$stmt_meses = $pdo->prepare($sql_meses);
$stmt_meses->execute([$user_id, $contexto]);
$meses_disponiveis = $stmt_meses->fetchAll(PDO::FETCH_COLUMN);

// Garante que o mês atual sempre apareça na lista, mesmo se não houver transações ainda
if (!in_array($mes_ano_filtro, $meses_disponiveis)) {
    $meses_disponiveis[] = $mes_ano_filtro;
    rsort($meses_disponiveis); // Reordena para o mais recente ficar no topo
}

// Array para traduzir os meses
$nomes_meses = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
    '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
    '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
];

// 3. Busca as transações filtradas pelo mês selecionado
$sql = "SELECT id, data_transacao, descricao, tipo, valor 
        FROM transacoes 
        WHERE usuario_id = ? 
        AND contexto = ? 
        AND MONTH(data_transacao) = ? 
        AND YEAR(data_transacao) = ? 
        ORDER BY data_transacao ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id, $contexto, $mes, $ano]);
$transacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transações - Gestão Integrada</title>
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
        
        .form-control-dark, .form-select-dark {
            background-color: #0f172a; border: 1px solid #334155; color: #f1f5f9;
        }
        .form-control-dark:focus, .form-select-dark:focus {
            background-color: #0f172a; border-color: var(--gold); color: #f1f5f9; box-shadow: 0 0 0 0.25rem rgba(207, 163, 78, 0.25);
        }
        
        /* Modal Escuro */
        .modal-content { background-color: var(--bg-card); border: 1px solid #334155; color: var(--text-light); }
        .modal-header { border-bottom: 1px solid #334155; }
        .modal-footer { border-top: 1px solid #334155; }

        .table-dark-custom { color: #f1f5f9; }
        .table-dark-custom th { border-bottom: 2px solid #334155; background-color: transparent; color: #94a3b8; }
        .table-dark-custom td { border-bottom: 1px solid #334155; background-color: transparent; vertical-align: middle; }

        .badge-receita { background-color: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid #22c55e; }
        .badge-fixo { background-color: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid #f59e0b; }
        .badge-variavel { background-color: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid #ef4444; }
        .badge-saldo { background-color: rgba(65,105,225, 0.2); color: #0000FF; border: 1px solid #0000CD; }
        .badge-mensalidade { background-color: rgba(119,136,153, 0.2); color: #778899; border: 1px solid #708090; }
        .badge-tronco { background-color: rgba(65,105,225, 0.2); color: #0000FF; border: 1px solid #0000CD; }
        .badge-doacao { background-color: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid #ef4444; }

        .mobile-header { display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px; background-color: var(--bg-card); border-bottom: 1px solid #334155; z-index: 2000; align-items: center; padding: 0 20px; justify-content: space-between; }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); z-index: 3000; width: 280px; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 15px; padding-top: 80px; }
            .mobile-header { display: flex !important; }
        }
    </style>
</head>
<body>

    <!-- Barra Superior Mobile (Visível apenas em celulares) -->
<div class="mobile-topbar">
    <div class="d-flex align-items-center">
        <button class="btn btn-outline-warning btn-sm me-3" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <span style="font-family: 'Cinzel', serif; color: var(--gold); font-weight: bold;">TESOURARIA</span>
    </div>
    <span class="text-white small">Painel</span>
</div>

<!-- Backdrop escuro para fechar o menu ao clicar fora -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>

    <?php include 'menu.php'; ?>
    
    <div class="main-content">
    <div class="container-fluid py-4 px-4">
            
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="page-header mb-4">
            <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;"><i class="fas fa-bars me-2 text-warning"></i> Controle de Transações</h2>
            <p class="text-warning">Visualize e atualize suas transações financeiras</p>
        </div>
    
    <div class="d-flex align-items-center gap-3 flex-wrap">
        
        <!-- Seletor de Mês/Ano Elegante -->
        <form method="GET" id="formFiltroMes" class="m-0">
            <div class="input-group">
                <span class="input-group-text bg-transparent border-end-0" style="border-color: #334155; color: var(--gold);">
                    <i class="far fa-calendar-alt"></i>
                </span>
                <select name="mes_ano" class="form-select form-select-dark border-start-0 shadow-none" 
                        style="cursor: pointer; min-width: 180px;" 
                        onchange="document.getElementById('formFiltroMes').submit();">
                    <?php foreach ($meses_disponiveis as $ma): 
                        $ano_opt = substr($ma, 0, 4);
                        $mes_opt = substr($ma, 5, 2);
                        $nome_mes = $nomes_meses[$mes_opt];
                        $selected = ($ma === $mes_ano_filtro) ? 'selected' : '';
                    ?>
                        <option value="<?= $ma ?>" <?= $selected ?>><?= $nome_mes ?> de <?= $ano_opt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <!-- Campo de Busca Rápida -->
        <div class="input-group" style="width: 250px;">
            <span class="input-group-text bg-transparent border-end-0 shadow-none" style="border-color: #334155; color: #94a3b8;">
                <i class="fas fa-search"></i>
            </span>
            <input type="text" id="buscaInput" onkeyup="filtrarTabela()" class="form-control form-control-dark border-start-0 shadow-none" placeholder="Buscar na tabela...">
        </div>
        
    </div>
</div>

            <div class="card-custom">
                <div class="table-responsive">
                    <table class="table table-dark-custom table-hover w-100" id="tabelaTransacoes">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Descrição</th>
                                <th>Tipo</th>
                                <th>Valor (R$)</th>
                                <?php if ($_SESSION['is_admin'] == 1): ?>
                                <th class="text-end">Ações</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transacoes)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Nenhuma transação encontrada.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transacoes as $t): 
                                    $tipo = strtolower(trim($t['tipo']));
                                    $badgeClass = match($tipo) {
                                        'saldo'       => 'badge-saldo',
                                        'receita'     => 'badge-receita',
                                        'mensalidade' => 'badge-mensalidade',
                                        'fixo'        => 'badge-fixo',
                                        'tronco'      => 'badge-tronco',
                                        'doação'      => 'badge-doacao',
                                        default       => 'badge-variavel',
                                    };
                                    $corValor = match($tipo) {
                                        'receita'     => 'text-success',
                                        'saldo'       => 'text-primary',
                                        'mensalidade' => 'text-secondary',
                                        'fixo'        => 'text-warning',
                                        'tronco'      => 'text-primary',
                                        'doação'      => 'text-danger',
                                        default       => 'text-danger', // Cobre o 'variavel' e qualquer outro não previsto
                                    };
                                    $sinal = ($tipo == 'receita' || $tipo == 'saldo' || $tipo == 'mensalidade' || $tipo == 'tronco') ? '+' : '-';
                                ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($t['data_transacao'])) ?></td>
                                    <td><?= htmlspecialchars($t['descricao']) ?></td>
                                    <td><span class="badge rounded-pill <?= $badgeClass ?> text-capitalize"><?= htmlspecialchars($t['tipo']) ?></span></td>
                                    <td class="<?= $corValor ?> fw-bold"><?= $sinal ?> R$ <?= number_format($t['valor'], 2, ',', '.') ?></td>
                                    <td class="text-end">
                                        <!-- Usamos data-atributos para guardar os valores com segurança -->
                                        <?php if ($_SESSION['is_admin'] == 1): ?>
                                        <button type="button" class="btn btn-sm btn-outline-info me-2" 
                                            data-id="<?= $t['id'] ?>"
                                            data-descricao="<?= htmlspecialchars($t['descricao']) ?>"
                                            data-tipo="<?= htmlspecialchars($t['tipo']) ?>"
                                            data-valor="<?= $t['valor'] ?>"
                                            onclick="abrirModalEditar(this)">
                                            <i class="fas fa-edit"></i>
                                        </button>
    
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="excluirTransacao(<?= $t['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- Modal de Edição -->
    <div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-gold"><i class="fas fa-edit me-2"></i> Editar Transação</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formEditar">
                        <input type="hidden" id="edit_id" name="id">
                        
                        <div class="mb-3">
                            <label class="form-label text-light">Descrição</label>
                            <input type="text" id="edit_descricao" name="descricao" class="form-control form-control-dark" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-light">Tipo</label>
                            <select id="edit_tipo" name="tipo" class="form-select form-select-dark" required>
                                <option value="receita">Receita</option>
                                <option value="mensalidade">Mensalidade</option>
                                <option value="saldo">Saldo</option>
                                <option value="tronco">Tronco</option>
                                <option value="fixo">Despesa Fixa</option>
                                <option value="variável">Despesa Variável</option>
                                <option value="doação">Despesa Doação</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-light">Valor (R$)</label>
                            <!-- step="0.01" permite valores quebrados -->
                            <input type="number" step="0.01" id="edit_valor" name="valor" class="form-control form-control-dark" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning" onclick="salvarEdicao()">Salvar Alterações</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        //onst instanciaModalEditar = new bootstrap.Modal(document.getElementById('modalEditar'));

        // Filtro de Busca em Tempo Real
        function filtrarTabela() {
            let input = document.getElementById("buscaInput").value.toLowerCase();
            let trs = document.querySelectorAll("#tabelaTransacoes tbody tr");
            
            trs.forEach(tr => {
                // Pula a linha de "Nenhuma transação" se existir
                if(tr.cells.length === 1) return; 
                
                let textoLinha = tr.innerText.toLowerCase();
                tr.style.display = textoLinha.includes(input) ? "" : "none";
            });
        }

// Abre o Modal preenchido lendo os dados seguros do botão (data-attributes)
// Abre o Modal preenchido lendo os dados seguros do botão (data-attributes)
function abrirModalEditar(botao) {
    // 1. Captura os dados salvos no próprio botão
    const id = botao.getAttribute('data-id');
    const descricao = botao.getAttribute('data-descricao');
    const tipo = botao.getAttribute('data-tipo');
    const valor = botao.getAttribute('data-valor');

    // 2. Preenche os campos de texto
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_descricao').value = descricao;
    
    // 3. Tratamento para o campo Select (Tipo)
    let tipoFormatado = tipo ? tipo.trim().toLowerCase() : '';
    if (tipoFormatado === 'variavel') {
        tipoFormatado = 'variável';
    }
    document.getElementById('edit_tipo').value = tipoFormatado;
    
    // 4. Tratamento para o campo Number (Valor)
    document.getElementById('edit_valor').value = parseFloat(valor || 0).toFixed(2);
    
    // 5. Instancia e exibe o modal de forma segura (Padrão Bootstrap 5)
    const modalElement = document.getElementById('modalEditar');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    modal.show();
}

// Salvar Edição
function salvarEdicao() {
    const form = document.getElementById('formEditar');
    if(!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);

    fetch('editar_transacao', {
        method: 'POST',
        body: formData
    })
    .then(async res => {
        // Pega a resposta "crua" do servidor em texto antes de converter para JSON
        const textoResposta = await res.text(); 
        try {
            const data = JSON.parse(textoResposta);
            return data;
        } catch (erro) {
            // Se falhar ao converter para JSON, mostra o erro exato do PHP no console
            console.error("ERRO DO PHP:", textoResposta);
            throw new Error("O PHP retornou um erro em vez de JSON.");
        }
    })
    .then(data => {
        if(data.sucesso) {
            Swal.fire('Atualizado!', 'Transação alterada com sucesso.', 'success').then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire('Aviso', data.mensagem, 'warning');
        }
    })
    .catch(erro => {
        Swal.fire('Erro no Servidor', 'Abra o Console (F12) para ver o erro exato do PHP.', 'error');
    });
}

        // Excluir Registro
        function excluirTransacao(id) {
            Swal.fire({
                title: 'Tem certeza?',
                text: "Você não poderá reverter essa exclusão!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#3b82f6',
                confirmButtonText: 'Sim, excluir!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    let formData = new FormData();
                    formData.append('id', id);

                    fetch('excluir_transacao', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if(data.sucesso) {
                            Swal.fire('Excluído!', 'O registro foi removido.', 'success').then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire('Erro', data.mensagem, 'error');
                        }
                    })
                    .catch(() => Swal.fire('Erro', 'Falha na comunicação com o servidor.', 'error'));
                }
            });
        }

    function toggleMobileMenu() {
        const sidebar = document.querySelector('.sidebar'); // Certifique-se de que a sua tag <nav> ou <div class="sidebar"> do menu tenha essa classe
        const backdrop = document.getElementById('sidebarBackdrop');
        
        if (sidebar) {
            sidebar.classList.toggle('show');
        }
        if (backdrop) {
            backdrop.classList.toggle('show');
        }
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>