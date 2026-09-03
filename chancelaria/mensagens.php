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

try {
    // Busca as sessões para o caso de mensagens para Faltosos ou Visitantes
    $stmtSessoes = $pdo->prepare("SELECT id, data_sessao, tipo FROM chancelaria_sessoes WHERE tenant_id = ? ORDER BY data_sessao DESC LIMIT 20");
    $stmtSessoes->execute([$tenant_id]);
    $sessoes = $stmtSessoes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envio de Mensagens - Gestão Integrada</title>
    <link rel="icon" href="../configuracoes/icone.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { --bg-main: #141724; --bg-card: #222738; --text-main: #e2e8f0; --gold: #f5c041; --gold-hover: #dca732; --border-color: #333951; --success: #22c55e; }
        body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 260px; padding: 30px 40px; width: calc(100% - 260px); }
        .card-custom { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; padding: 25px; }
        .btn-gold { background-color: var(--gold); color: #000; font-weight: 600; padding: 10px 20px; border: none; border-radius: 6px; }
        .btn-gold:hover { background-color: var(--gold-hover); }
        
        .form-control, .form-select { background-color: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); }
        .form-control:focus, .form-select:focus { border-color: var(--gold); box-shadow: 0 0 0 0.25rem rgba(245, 192, 65, 0.25); color: var(--text-main); }
        .info-box { background-color: rgba(34, 197, 94, 0.1); border-left: 4px solid var(--success); padding: 15px; border-radius: 4px; font-size: 0.9rem; }
    </style>
</head>
<body>
    
    <!-- Barra Superior Mobile (Visível apenas em celulares) -->
<div class="mobile-topbar">
    <div class="d-flex align-items-center">
        <button class="btn btn-outline-warning btn-sm me-3" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <span style="font-family: 'Cinzel', serif; color: var(--gold); font-weight: bold;">CHANCELARIA</span>
    </div>
    <span class="text-white small">Painel</span>
</div>

<!-- Backdrop escuro para fechar o menu ao clicar fora -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>

    <?php include 'menu.php'; ?>
    <main class="main-content">
        <div class="page-header mb-4">
            <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;"><i class="fab fa-whatsapp me-2 text-warning"></i> Central de Comunicação</h2>
            <p class="text-warning">Integração com n8n para envios via WhatsApp</p>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card-custom">
                    <form id="formMensagem">
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label text-white">Destinatários</label>
                                <select class="form-select" name="publico_alvo" id="publico_alvo" onchange="toggleSessaoSelect()" required>
                                    <option value="">Selecione...</option>
                                    <optgroup label="Disparo em Massa (Individual)">
                                        <option value="ativos">Todos os Obreiros Ativos</option>
                                        <option value="faltosos">Obreiros Ausentes em uma Sessão</option>
                                        <option value="visitantes">Visitantes de uma Sessão</option>
                                    </optgroup>
                                    <optgroup label="Grupos">
                                        <option value="grupo">Grupo da Loja (WhatsApp)</option>
                                    </optgroup>
                                </select>
                            </div>
                            
                            <div class="col-md-6" id="divSessao" style="display: none;">
                                <label class="form-label text-white">Selecione a Sessão</label>
                                <select class="form-select" name="sessao_id" id="sessao_id">
                                    <option value="">Escolha...</option>
                                    <?php foreach ($sessoes as $s): ?>
                                        <option value="<?= $s['id'] ?>">
                                            <?= date('d/m/Y', strtotime($s['data_sessao'])) ?> - <?= htmlspecialchars($s['tipo']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-white d-flex justify-content-between">
                                <span>Mensagem</span>
                                <!--<small class="text-warning" id="dicaNome">Use {nome} para personalizar (Apenas Individual)</small>-->
                            </label>
                            <textarea class="form-control" name="mensagem_texto" id="mensagem_texto" rows="6" placeholder="Digite sua mensagem aqui..." required></textarea>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-warning" id="btnEnviar" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                                <i class="fas fa-paper-plane me-2"></i> Enviar Mensagem
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card-custom info-box">
                    <h5 class="text-success fw-bold mb-3"><i class="fas fa-info-circle me-2"></i> Como funciona?</h5>
                    <p class="text-white"><strong>Envio Individual:</strong> O sistema busca os telefones no banco e envia uma mensagem privada para cada Irmão.</p>
                    <p class="text-white"><strong>Envio para o Grupo:</strong> Dispara uma única mensagem diretamente no grupo da Oficina configurado no sistema.</p>
                    <hr style="border-color: var(--success);">
                    <p class="text-white mb-0" style="font-size: 0.8rem;">
                        <i class="fas fa-server text-warning"></i> Os disparos são processados em segundo plano pelo <strong>Sistema</strong> garantindo segurança e estabilidade.
                    </p>
                </div>
            </div>
        </div>
    </main>

    <script>
        function toggleSessaoSelect() {
            const publico = document.getElementById('publico_alvo').value;
            const divSessao = document.getElementById('divSessao');
            const selectSessao = document.getElementById('sessao_id');
            const dicaNome = document.getElementById('dicaNome');
            
            // Controle do campo de Sessão
            if (publico === 'faltosos' || publico === 'visitantes') {
                divSessao.style.display = 'block';
                selectSessao.setAttribute('required', 'required');
            } else {
                divSessao.style.display = 'none';
                selectSessao.removeAttribute('required');
                selectSessao.value = '';
            }

            // Controle da dica de personalização
            if (publico === 'grupo') {
                dicaNome.style.display = 'none';
            } else {
                dicaNome.style.display = 'block';
            }
        }

        // Submissão
        document.getElementById('formMensagem').addEventListener('submit', async function(e) {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(this).entries());
            const btn = document.getElementById('btnEnviar');
            const originalText = btn.innerHTML;
            
            const confirm = await Swal.fire({
                title: 'Confirmar Disparo?',
                text: "Os dados serão enviados para a fila do n8n.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#22c55e',
                cancelButtonColor: '#333951',
                confirmButtonText: 'Sim, Enviar!',
                background: '#222738',
                color: '#e2e8f0'
            });

            if (!confirm.isConfirmed) return;

            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processando...';
            btn.disabled = true;

            try {
                const response = await fetch('mensagens_action', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();

                if (result.sucesso) {
                    Swal.fire({ icon: 'success', title: 'Sucesso!', text: result.mensagem, background: '#222738', color: '#e2e8f0' });
                    document.getElementById('mensagem_texto').value = ''; 
                } else {
                    Swal.fire({ icon: 'error', title: 'Erro', text: result.mensagem, background: '#222738', color: '#e2e8f0' });
                }
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha na comunicação com o servidor.', background: '#222738', color: '#e2e8f0' });
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });
        
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