<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../configuracoes/config.php';

// Verificação de segurança
if (!isset($_SESSION['tenant_id'])) {
    die("Acesso negado. Redirecionando para o login...");
}
$tenant_id = $_SESSION['tenant_id'];

$sessao_id = isset($_GET['sessao_id']) ? (int)$_GET['sessao_id'] : 0;

try {
    // 1. Busca todas as sessões da Loja para o Select
    $stmtSessoes = $pdo->prepare("SELECT id, data_sessao, tipo, grau FROM chancelaria_sessoes WHERE tenant_id = ? ORDER BY data_sessao DESC");
    $stmtSessoes->execute([$tenant_id]);
    $sessoes = $stmtSessoes->fetchAll(PDO::FETCH_ASSOC);

    // 2. Busca todos os membros ativos para popular o Modal de adição
    $stmtTodos = $pdo->prepare("SELECT id, nome, cim FROM chancelaria_membros WHERE tenant_id = ? AND status = 'Ativo' ORDER BY nome ASC");
    $stmtTodos->execute([$tenant_id]);
    $todosMembros = $stmtTodos->fetchAll(PDO::FETCH_ASSOC);

    // 3. Se uma sessão foi escolhida, busca os dados dela, os obreiros presentes e os visitantes
    $sessaoAtual = null;
    $presentes = [];
    $visitantes = [];
    
    if ($sessao_id > 0) {
        $stmtSessaoInfo = $pdo->prepare("SELECT * FROM chancelaria_sessoes WHERE id = ? AND tenant_id = ?");
        $stmtSessaoInfo->execute([$sessao_id, $tenant_id]);
        $sessaoAtual = $stmtSessaoInfo->fetch(PDO::FETCH_ASSOC);

        // Obreiros presentes do quadro
        $stmtPresentes = $pdo->prepare("
            SELECT m.id as membro_id, m.cim, m.nome, m.grau, p.status_presenca 
            FROM chancelaria_membros m
            INNER JOIN chancelaria_presencas p ON m.id = p.membro_id 
            WHERE m.tenant_id = ? 
            AND p.sessao_id = ? 
            AND p.status_presenca = 'P'
            ORDER BY m.nome ASC
        ");
        $stmtPresentes->execute([$tenant_id, $sessao_id]);
        $presentes = $stmtPresentes->fetchAll(PDO::FETCH_ASSOC);

        // Visitantes presentes na sessão
        $stmtVisitantes = $pdo->prepare("
            SELECT * FROM chancelaria_visitantes 
            WHERE sessao_id = ? 
            ORDER BY nome ASC
        ");
        $stmtVisitantes->execute([$sessao_id]);
        $visitantes = $stmtVisitantes->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Livro de Presença - Gestão Integrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { --bg-main: #141724; --bg-card: #222738; --text-main: #e2e8f0; --gold: #f5c041; --border-color: #333951; }
        body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 260px; padding: 30px 40px; width: calc(100% - 260px); }
        .card-custom { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; padding: 25px; }
        .form-select, .form-control { background-color: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); }
        .form-select:focus, .form-control:focus { border-color: var(--gold); box-shadow: 0 0 0 0.25rem rgba(245, 192, 65, 0.25); color: var(--text-main); }
        .table-dark-custom { color: var(--text-main); vertical-align: middle; }
        .table-dark-custom thead th { background-color: rgba(0,0,0,0.2); color: var(--gold); border-bottom: 2px solid var(--border-color); font-weight: 600; text-transform: uppercase; font-size: 0.85rem; }
        .table-dark-custom tbody td { background-color: transparent; border-bottom: 1px solid var(--border-color); padding: 15px 10px; color: #fff; }
        .btn-gold { background-color: var(--gold); color: #000; font-weight: 600; }
        .btn-gold:hover { background-color: #dca732; }

        /* Oculto na tela normal, aparece apenas na impressão */
        .print-header { display: none !important; }
        .print-signatures { display: none !important; }
        .print-section-title { display: none !important; }

        /* Estilos exclusivos para Impressão / Relatório Oficial */
        @media print {
            body { background-color: #fff !important; color: #000 !important; }
            .sidebar, .mobile-topbar, .d-flex.justify-content-between.align-items-center.mb-4, .mb-4:has(select), .btn, .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 0 !important; }
            .card-custom { background-color: #fff !important; border: none !important; padding: 0 !important; }
            
            .print-header { 
                display: block !important; 
                text-align: center; 
                margin-bottom: 20px; 
                border-bottom: 2px solid #000; 
                padding-bottom: 15px; 
            }
            .print-header h3 { font-family: 'Cinzel', serif; font-weight: bold; margin-bottom: 5px; color: #000; font-size: 1.4rem; }
            .print-header p { margin-bottom: 2px; font-size: 0.95rem; color: #333; }
            
            .print-section-title {
                display: block !important;
                font-family: 'Cinzel', serif;
                font-size: 1.1rem;
                font-weight: bold;
                margin-top: 25px;
                margin-bottom: 10px;
                color: #000;
                border-bottom: 1px solid #999;
                padding-bottom: 5px;
            }

            .table-dark-custom { color: #000 !important; }
            .table-dark-custom thead th { background-color: #eee !important; color: #000 !important; border-bottom: 2px solid #000 !important; }
            .table-dark-custom tbody td { color: #000 !important; border-bottom: 1px solid #ccc !important; }
            .badge { background-color: #ddd !important; color: #000 !important; border: 1px solid #999; }

            .print-signatures { display: flex !important; justify-content: space-between; margin-top: 60px; page-break-inside: avoid; }
            .signature-line { width: 40%; text-align: center; border-top: 1px solid #000; padding-top: 5px; font-size: 0.9rem; }
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
            <span style="font-family: 'Cinzel', serif; color: var(--gold); font-weight: bold;">CHANCELARIA</span>
        </div>
        <span class="text-white small">Painel</span>
    </div>
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>

    <?php include 'menu.php'; ?>
    <main class="main-content">
        
        <!-- Cabeçalho da Página -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;">
                    <i class="fas fa-book me-2 text-warning"></i> Livro de Presença
                </h2>
                <p class="text-warning mb-0">Registre e emita o relatório oficial dos Irmãos e Visitantes presentes</p>
            </div>
            
            <div class="d-flex gap-2">
                <?php if ($sessao_id > 0): ?>
                <button class="btn btn-warning fw-bold" data-bs-toggle="modal" data-bs-target="#modalPresenca" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                    <i class="fas fa-plus"></i> Adicionar Presença
                </button>
                <button class="btn btn-outline-light fw-bold" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Imprimir Relatório
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-custom">
            
            <!-- Seleção da Sessão -->
            <div class="col-md-6 mb-4 no-print">
                <label class="form-label text-white">Selecione a Sessão</label>
                <select class="form-select form-select-dark" onchange="window.location.href='presenca.php?sessao_id='+this.value">
                    <option value="0">Escolha uma sessão...</option>
                    <?php foreach ($sessoes as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $s['id'] == $sessao_id ? 'selected' : '' ?>>
                            <?= date('d/m/Y', strtotime($s['data_sessao'])) ?> - <?= htmlspecialchars($s['tipo']) ?> (Grau <?= $s['grau'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- CABEÇALHO OFICIAL DE IMPRESSÃO -->
            <?php if ($sessao_id > 0 && $sessaoAtual): ?>
            <div class="print-header">
                <h3>RELATÓRIO OFICIAL DE PRESENÇAS E TRONCO</h3>
                <p><strong>Sessão:</strong> <?= htmlspecialchars($sessaoAtual['titulo'] ?? $sessaoAtual['tipo']) ?> — <strong>Data:</strong> <?= date('d/m/Y', strtotime($sessaoAtual['data_sessao'])) ?></p>
                <p><strong>Tipo:</strong> <?= htmlspecialchars($sessaoAtual['tipo']) ?> | <strong>Grau:</strong> <?= $sessaoAtual['grau'] ?></p>
                <p class="mt-2"><strong>Valor Arrecadado no Tronco de Solidariedade:</strong> R$ <?= number_format($sessaoAtual['valor'] ?? 0, 2, ',', '.') ?></p>
            </div>
            <?php endif; ?>

            <?php if ($sessao_id > 0): ?>
                
                <!-- TABELA DE OBREIROS DO QUADRO -->
                <div class="print-section-title">Irmãos do Quadro Presentes</div>
                <div class="table-responsive">
                    <table class="table table-dark-custom">
                        <thead>
                            <tr>
                                <th width="10%">CIM</th>
                                <th width="40%">Nome do Obreiro</th>
                                <th width="15%">Loja</th>
                                <th width="15%">Grau</th>
                                <th width="15%" class="text-center">Status</th>
                                <th width="20%" class="text-center no-print">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($presentes) > 0): ?>
                                <?php foreach ($presentes as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['cim'] ?: '-') ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($p['nome']) ?></td>
                                    <td><span>Loja Fraternidade Carpinense N° 4028</span></td>
                                    <td><span class="badge bg-secondary">Grau <?= htmlspecialchars($p['grau']) ?></span></td>
                                    <td class="text-center">
                                        <span class="badge bg-success">Presente</span>
                                    </td>
                                    <td class="text-center no-print">
                                        <button class="btn btn-outline-danger btn-sm" onclick="removerPresenca(<?= $p['membro_id'] ?>)" title="Remover Presença" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-secondary">
                                        Nenhuma presença de obreiro registrada nesta sessão.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- TABELA DE VISITANTES (Exibida no relatório impresso e opcionalmente na tela) -->
                <div class="print-section-title">Irmãos Visitantes</div>
                <div class="table-responsive mt-4">
                    <table class="table table-dark-custom">
                        <thead>
                            <tr>
                                <th width="10%">CIM</th>
                                <th width="40%">Nome do Visitante</th>
                                <th width="15%">Loja</th>
                                <th width="15%" >Grau</th>
                                <th width="15%" class="text-center">Status</th>
                                <th width="20%" class="text-center no-print">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($visitantes) > 0): ?>
                                <?php foreach ($visitantes as $v): ?>
                                <tr>
                                    <td><?= htmlspecialchars($v['cim'] ?? '-') ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($v['nome']) ?></td>
                                    <td><?= htmlspecialchars($v['loja_origem'] ?? '-') ?></td>
                                    <td><span class="badge bg-secondary"><?= ($v['grau'] == 1) ? 'Aprendiz' : (($v['grau'] == 2) ? 'Companheiro' : (($v['grau'] == 3) ? 'Mestre' : 'Mestre Instalado')) ?></span></td>
                                    <td class="text-center">
                                    <span class="badge bg-success">Presente</span>
                                    </td>
                                    <td class="text-center no-print">
                                        <button class="btn btn-outline-primary btn-sm" onclick="enviarCertificado(<?= $sessao_id ?>, <?= $tenant_id ?>, <?= htmlspecialchars($v['cim'] ?? '-') ?>)" title="Enviar Certificado" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-3 text-secondary">
                                        Nenhum visitante registrado para esta sessão.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- ASSINATURAS OFICIAIS NA IMPRESSÃO -->
                <div class="print-signatures">
                    <div class="signature-line">
                        Venerável Mestre
                    </div>
                    <div class="signature-line">
                        Chanceler
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Adicionar Presença -->
    <div class="modal fade" id="modalPresenca" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background-color: var(--bg-card); border: 1px solid var(--border-color);">
                <form id="formAddPresenca">
                    <input type="hidden" name="tenant_id" value="<?= $tenant_id ?>">
                    <input type="hidden" name="sessao_id" value="<?= $sessao_id ?>">
                    <input type="hidden" name="status" value="P">
                    
                    <div class="modal-header border-bottom border-secondary">
                        <h5 class="modal-title text-warning"><i class="fas fa-user-plus me-2"></i>Registrar Presença</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-white">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Obreiro</label>
                            <select class="form-select" name="membro_id" required>
                                <option value="">Selecione o irmão...</option>
                                <?php foreach ($todosMembros as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nome']) ?> (CIM: <?= htmlspecialchars($m['cim']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-top border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Salvar Presença</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function toggleMobileMenu() {
        const sidebar = document.querySelector('.sidebar'); 
        const backdrop = document.getElementById('sidebarBackdrop');
        if (sidebar) sidebar.classList.toggle('show');
        if (backdrop) backdrop.classList.toggle('show');
    }

    // Função para Salvar Presença via AJAX
    document.getElementById('formAddPresenca')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch('presenca_action', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.sucesso) {
                location.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Erro', text: result.mensagem, background: '#222738', color: '#fff' });
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Erro de comunicação', background: '#222738', color: '#fff' });
        }
    });

    // Função para Remover Presença
    function removerPresenca(membro_id) {
        Swal.fire({
            title: 'Tem certeza?',
            text: "Deseja remover a presença deste obreiro?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, remover',
            cancelButtonText: 'Cancelar',
            background: '#222738',
            color: '#fff'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const formData = new FormData();
                    formData.append('acao', 'remover');
                    formData.append('sessao_id', '<?= $sessao_id ?>');
                    formData.append('membro_id', membro_id);

                    const response = await fetch('presenca_action', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const res = await response.json();
                    if (res.sucesso) {
                        location.reload();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Erro', text: res.mensagem, background: '#222738', color: '#fff' });
                    }
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Erro de comunicação', background: '#222738', color: '#fff' });
                }
            }
        });
    }
    
    function enviarCertificado(sessaoId, tenantId, cim) {
    
    Swal.fire({
        title: 'Confirmar Envio',
        text: 'Deseja enviar o certificado de presença para o visitante?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#22c55e',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Sim, enviar!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            
            Swal.fire({
                title: 'Enviando certificado...',
                html: 'Aguarde enquanto o envio é processado.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Adiciona a 'acao_interna' para o webhook de membros
            const payload = {
                acao_interna: 'certificados', 
                action: 'individual',
                sessao_id: sessaoId, // Avisa o PHP que é para enviar-membros
                tenant_id: tenantId,
                cim: cim
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
                    Swal.fire('Enviado!', 'Seu certificado foi enviodo com sucesso.', 'success')
                    .then(() => {
                        document.getElementById('nomeLoja').value = '';
                        document.getElementById('tesoureiro').value = '';
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
    </script>
</body>
</html>