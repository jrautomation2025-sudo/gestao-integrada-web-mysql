<?php
session_start();
require '../configuracoes/config.php';

// Segurança
if (!isset($_SESSION['tenant_id']) && !isset($_SESSION['user'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['user']['id'];

// Contexto (Pessoal ou Empresa)
$contexto = $_SESSION['contexto_atual'] ?? 'pessoal';

?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investimentos - Gestão Integrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { --bg-dark: #0f172a; --bg-card: #1e293b; --gold: #cfa34e; --text-light: #e2e8f0; }
   
        body { background-color: var(--bg-dark); color: var(--text-light); font-family: 'Segoe UI', sans-serif; }
        
        /*@media (min-width: 768px) { body { padding-left: 250px; } }*/

        .btn-gold { background: var(--gold); border: none; color: #000; font-weight: bold; }
        .btn-gold:hover { background: #b8860b; color: #fff; }

        .card-custom { background: var(--bg-card); border: 1px solid #334155; border-radius: 12px; height: 100%; padding: 20px; }
        .text-gold { color: var(--gold) !important; }
        
        .table-custom th { background: #334155; color: var(--gold); border: none; }
        .table-custom td { border-bottom: 1px solid #334155; color: #cbd5e1; vertical-align: middle; }
        .table-custom tr:hover td { background: rgba(255,255,255,0.02); }
        
        
    /* HEADER MOBILE */
    .mobile-header {
        display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px;
        background-color: var(--bg-surface); border-bottom: 1px solid var(--border-color);
        z-index: 2000; align-items: center; padding: 0 20px; justify-content: space-between;
        box-shadow: 0 2px 10px rgba(0,0,0,0.3);
    }

    /* --- MOBILE & TABLET (Até 992px) --- */
    @media (max-width: 992px) {
        .sidebar { transform: translateX(-100%); z-index: 3000; width: 280px; box-shadow: 5px 0 15px rgba(0,0,0,0.5); }
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
    
    <main class="main-content">

    <div class="container-fluid py-4 px-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;"><i class="fas fa-money-bill-trend-up text-warning mb-2"></i> Carteira de Investimentos</h2>
                <p class="text-warning">Gerencie seus ativos e acompanhe a rentabilidade</p>
            </div>
            <?php if ($_SESSION['is_admin'] == 1): ?>
            <button class="btn btn-warning shadow" data-bs-toggle="modal" data-bs-target="#modalInvest">
                + Novo Aporte
            </button>
            <?php endif; ?>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card-custom border-gold" style="border-color: rgba(207,163,78,0.3);">
                    <small class="text-muted text-uppercase">Total Investido</small>
                    <h3 id="valInvestido" class="fw-bold mt-2 mb-0">R$ 0,00</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-custom" style="background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%); border: 1px solid var(--gold);">
                    <small class="text-gold text-uppercase fw-bold">Saldo Atual</small>
                    <h3 id="valAtual" class="fw-bold text-white mt-2 mb-0">R$ 0,00</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-custom">
                    <small class="text-muted text-uppercase">Lucro / Prejuízo</small>
                    <h3 id="valLucro" class="fw-bold mt-2 mb-0 text-success">R$ 0,00</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-custom">
                    <small class="text-muted text-uppercase">Rentabilidade</small>
                    <h3 id="valRent" class="fw-bold mt-2 mb-0 text-info">0%</h3>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card-custom">
                    <h6 class="fw-bold text-white mb-4">Alocação por Categoria</h6>
                    <div style="height: 250px; position: relative;">
                        <canvas id="chartInvest"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card-custom p-0 overflow-hidden">
                    <div class="p-3 border-bottom border-secondary">
                        <h6 class="fw-bold m-0 text-white">Meus Ativos</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>ATIVO</th>
                                    <th>CATEGORIA</th>
                                    <th>INVESTIDO</th>
                                    <th>ATUAL</th>
                                    <th>DATA</th>
                                    <?php if ($_SESSION['is_admin'] == 1): ?>
                                    <th>ACÃO</th>
                                    <?php endif; ?>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="listaInvestimentos">
                                </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalInvest" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-secondary">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title text-gold">Novo Aporte</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formInvest">
                        <input type="hidden" name="contexto" value="<?php echo $contexto; ?>">
                        
                        <div class="mb-3">
                            <label class="text-light small">Categoria</label>
                            <select name="categoria" class="form-select bg-dark text-light border-secondary" required>
                                <?php if($contexto === 'empresa'): ?>
                                <option value="Caixa Operacional">Caixa Operacional / Capital de Giro</option>
                                <option value="CDB Empresarial">CDB Empresarial / Renda Fixa</option>
                                <option value="Fundo de Reserva">Fundo de Reserva PJ</option>
                                <option value="Equipamentos">Investimento em Equipamentos</option>
                                <option value="Outros">Outros</option>
                                <?php else: ?>
                                <option value="acao">Ações</option>
                                <option value="fii">Fundos Imobiliários</option>
                                <option value="renda_fixa">Renda Fixa (CDB/Tesouro)</option>
                                <option value="cripto">Criptomoedas</option>
                                <option value="exterior">Investimento no Exterior</option>
                                <option value="reserva">Caixa / Reserva de Emergência</option>
                                <option value="outros">Outros</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="text-light small">Nome do Ativo</label>
                            <input type="text" name="ativo" class="form-control bg-dark text-light border-secondary" placeholder="Ex: PETR4, Bitcoin..." required>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="text-light small">Valor Investido (R$)</label>
                                <input type="number" step="0.01" name="valor_investido" class="form-control bg-dark text-light border-secondary" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="text-light small">Valor Atual (R$)</label>
                                <input type="number" step="0.01" name="valor_atual" class="form-control bg-dark text-light border-secondary" required>
                                <small class="text-muted" style="font-size: 0.7rem;">Se for hoje, repita o valor.</small>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="text-light small">Data do Aporte</label>
                            <input type="date" name="data" id="dataAtual" class="form-control bg-dark text-light border-secondary" required>
                        </div>
                        <button type="submit" class="btn btn-warning w-100">Salvar Aporte</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </div>
    
    <!-- Modal de Edição de Investimento -->
    <div class="modal fade" id="modalEditInvest" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-secondary">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title text-gold"><i class="fas fa-edit me-2"></i> Editar Ativo</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formEditInvest">
                        <input type="hidden" name="id" id="edit_id">
                        <input type="hidden" name="contexto" value="<?php echo $contexto; ?>">
                        
                        <div class="mb-3">
                            <label class="text-light small">Categoria</label>
                            <select name="categoria" id="edit_categoria" class="form-select bg-dark text-light border-secondary" required>
                                <?php if($contexto === 'empresa'): ?>
                                    <option value="Caixa Operacional">Caixa Operacional / Capital de Giro</option>
                                    <option value="CDB Empresarial">CDB Empresarial / Renda Fixa</option>
                                    <option value="Fundo de Reserva">Fundo de Reserva PJ</option>
                                    <option value="Equipamentos">Investimento em Equipamentos</option>
                                    <option value="Outros">Outros</option>
                                <?php else: ?>
                                    <option value="acao">Ações</option>
                                    <option value="fii">Fundos Imobiliários</option>
                                    <option value="renda_fixa">Renda Fixa (CDB/Tesouro)</option>
                                    <option value="cripto">Criptomoedas</option>
                                    <option value="exterior">Investimento no Exterior</option>
                                    <option value="reserva">Caixa / Reserva de Emergência</option>
                                    <option value="outros">Outros</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="text-light small">Nome do Ativo</label>
                            <input type="text" name="ativo" id="edit_ativo" class="form-control bg-dark text-light border-secondary" required>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="text-light small">Valor Investido (R$)</label>
                                <input type="number" step="0.01" name="valor_investido" id="edit_valor_investido" class="form-control bg-dark text-light border-secondary" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="text-light small">Valor Atual (R$)</label>
                                <input type="number" step="0.01" name="valor_atual" id="edit_valor_atual" class="form-control bg-dark text-light border-secondary" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="text-light small">Data do Aporte</label>
                            <input type="date" name="data" id="edit_data" class="form-control bg-dark text-light border-secondary" required>
                        </div>
                        <button type="submit" class="btn btn-warning w-100">Salvar Alterações</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('dataAtual').valueAsDate = new Date();
        const fmtBRL = (v) => parseFloat(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

        function carregarInvestimentos() {
            // 1. Resumo
            fetch('../configuracoes/api?action=inv_summary')
                .then(res => res.json())
                .then(data => {
                    document.getElementById('valInvestido').innerText = fmtBRL(data.investido);
                    document.getElementById('valAtual').innerText = fmtBRL(data.atual);
                    
                    let elLucro = document.getElementById('valLucro');
                    elLucro.innerText = fmtBRL(data.lucro);
                    elLucro.className = data.lucro >= 0 ? 'fw-bold mt-2 mb-0 text-success' : 'fw-bold mt-2 mb-0 text-danger';

                    let elRent = document.getElementById('valRent');
                    elRent.innerText = data.rentabilidade + '%';
                    elRent.className = data.rentabilidade >= 0 ? 'fw-bold mt-2 mb-0 text-info' : 'fw-bold mt-2 mb-0 text-danger';
                });

            // 2. Lista
            fetch('../configuracoes/api?action=inv_list')
                .then(res => res.json())
                .then(data => {
                    const tbody = document.getElementById('listaInvestimentos');
                    tbody.innerHTML = '';
                    if(data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Nenhum ativo neste modo.</td></tr>';
                        return;
                    }
                    data.forEach(item => {
                        let lucro = item.valor_atual - item.valor_investido;
                        let cor = lucro >= 0 ? 'text-success' : 'text-danger';
                        
                        tbody.innerHTML += `
                            <tr>
                                <td class="fw-bold text-white">${item.ativo}</td>
                                <td><span class="badge bg-secondary">${item.categoria}</span></td>
                                <td>${fmtBRL(item.valor_investido)}</td>
                                <td class="fw-bold text-gold">${fmtBRL(item.valor_atual)}</td>
                                <td class="text-muted small">${item.data_aporte}</td>
                                <td class="text-end text-nowrap">
                                    <!-- Botão Editar Seguro -->
                                    <?php if ($_SESSION['is_admin'] == 1): ?>
                                    <button type="button" class="btn btn-sm btn-link text-info p-0 me-2" 
                                        data-id="${item.id}"
                                        data-categoria="${item.categoria}"
                                        data-ativo="${item.ativo.replace(/"/g, '&quot;')}"
                                        data-investido="${item.valor_investido}"
                                        data-atual="${item.valor_atual}"
                                        data-data="${item.data_aporte}"
                                        onclick="abrirModalEditarInvest(this)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <!-- Botão Excluir -->
                                    <button type="button" onclick="excluirInvest(${item.id})" class="btn btn-sm btn-link text-danger p-0">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        `;
                    });
                });

            // 3. Gráfico
            carregarGrafico();
        }

        let chartInstance = null;
        function carregarGrafico() {
            fetch('../configuracoes/api?action=inv_chart')
                .then(res => res.json())
                .then(data => {
                    const ctx = document.getElementById('chartInvest').getContext('2d');
                    if(chartInstance) chartInstance.destroy();

                    if(data.data.length === 0) return; // Não gera gráfico vazio

                    chartInstance = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                data: data.data,
                                backgroundColor: ['#cfa34e', '#22c55e', '#38bdf8', '#f59e0b', '#ef4444', '#94a3b8'],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'right', labels: { color: '#e2e8f0', boxWidth: 10 } }
                            }
                        }
                    });
                });
        }

        function excluirInvest(id) {
            if(confirm('Excluir este ativo?')) {
                const fd = new FormData(); fd.append('id', id);
                fetch('../configuracoes/api?action=inv_delete', { method: 'POST', body: fd })
                .then(() => carregarInvestimentos());
            }
        }
        
        // 1. Função para abrir e preencher o modal de edição
        function abrirModalEditarInvest(botao) {
            document.getElementById('edit_id').value = botao.getAttribute('data-id');
            document.getElementById('edit_categoria').value = botao.getAttribute('data-categoria');
            document.getElementById('edit_ativo').value = botao.getAttribute('data-ativo');
            
            // Converte os valores para aparecerem corretamente no campo number
            document.getElementById('edit_valor_investido').value = parseFloat(botao.getAttribute('data-investido') || 0).toFixed(2);
            document.getElementById('edit_valor_atual').value = parseFloat(botao.getAttribute('data-atual') || 0).toFixed(2);
            
            // Tratamento caso a data venha do banco como DD/MM/YYYY (ajusta para o input date YYYY-MM-DD)
            let dataAporte = botao.getAttribute('data-data');
            if(dataAporte.includes('/')) {
                let partes = dataAporte.split('/');
                dataAporte = `${partes[2]}-${partes[1]}-${partes[0]}`;
            }
            document.getElementById('edit_data').value = dataAporte;

            // Abre o modal
            const modalElement = document.getElementById('modalEditInvest');
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            modal.show();
        }

        // 2. Evento de Submit para salvar a edição
        document.getElementById('formEditInvest').addEventListener('submit', function(e){
            e.preventDefault();
            const fd = new FormData(this);
            
            // Dispara para uma nova ação da API: inv_edit
            fetch('../configuracoes/api?action=inv_edit', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    // Fecha o modal e atualiza a tela
                    bootstrap.Modal.getInstance(document.getElementById('modalEditInvest')).hide();
                    Swal.fire('Atualizado!', 'Ativo modificado com sucesso.', 'success');
                    carregarInvestimentos();
                } else {
                    Swal.fire('Erro', data.message || 'Falha ao atualizar.', 'error');
                }
            })
            .catch(() => Swal.fire('Erro', 'Falha na comunicação com o servidor.', 'error'));
        });

        // Form Submit
        document.getElementById('formInvest').addEventListener('submit', function(e){
            e.preventDefault();
            const fd = new FormData(this);
            fetch('../configuracoes/api?action=inv_add', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('modalInvest')).hide();
                    this.reset();
                    carregarInvestimentos();
                    // Opcional: Avisar sucesso
                } else {
                    alert('Erro ao salvar');
                }
            });
        });

        // Iniciar
        carregarInvestimentos();
        
    
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
</body>
</html>