<?php
session_start();
require '../configuracoes/config.php';

// Segurança
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) { 
    header("Location: ./login.php"); 
    exit; 
}

$mes_filtro = $_GET['mes_filtro'] ?? date('m'); 
$mensagem = $_POST['mensagem'] ?? '';
$alerta = $alerta ?? '';

$mesesPT = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março',
    '04' => 'Abril', '05' => 'Maio', '06' => 'Junho',
    '07' => 'Julho', '08' => 'Agosto', '09' => 'Setembro',
    '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
];

$user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['user']['id'];
$contexto = $_SESSION['contexto_atual'] ?? 'pessoal';

// Busca os dados APENAS do usuário logado
$stmt = $pdo->prepare("SELECT * FROM configuracoes_pix WHERE usuario_id = :usuario_id");
$stmt->execute([':usuario_id' => $user_id]);
$config = $stmt->fetch(PDO::FETCH_ASSOC);

// Busca os dados APENAS do usuário logado
$stmt = $pdo->prepare("SELECT id, nome FROM clientes WHERE usuario_id = :usuario_id order by nome asc");
$stmt->execute([':usuario_id' => $user_id]);
$membros = $stmt->fetchAll(PDO::FETCH_ASSOC);

$membro_selecionado = $_GET['id'] ?? null;

?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disparo de Mensagens - Gestão Integrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { --bg-dark: #0f172a; --bg-card: #1e293b; --gold: #cfa34e; --text-light: #f1f5f9; }
        body { background-color: var(--bg-dark); color: var(--text-light); font-family: 'Segoe UI', sans-serif; }

        .card-custom { background: var(--bg-card); border: 1px solid #334155; border-radius: 12px; padding: 20px; height: 100%; }
        .text-gold { color: var(--gold) !important; }
        .btn-gold { background: var(--gold); border: none; color: #000; font-weight: bold; }
        .btn-gold:hover { background: #b8860b; color: #fff; }
        
        .form-control-dark {
            background-color: #0f172a;
            border: 1px solid #334155;
            color: #f1f5f9;
        }
        .form-control-dark:focus {
            background-color: #0f172a;
            border-color: var(--gold);
            color: #f1f5f9;
            box-shadow: 0 0 0 0.25rem rgba(207, 163, 78, 0.25);
        }

        /* HEADER MOBILE */
        .mobile-header {
            display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px;
            background-color: var(--bg-card); border-bottom: 1px solid #334155;
            z-index: 2000; align-items: center; padding: 0 20px; justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

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

    <!-- Inclui o menu lateral existente -->
    <?php include 'menu.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid py-4 px-4">
            
            <div class="page-header mb-4">
                <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;"><i class="fab fa-whatsapp me-2 text-warning"></i> Central de Mensagens</h2>
                <p class="text-warning">Envie suas mensagens via whatsApp</p>
            </div>
            
            <!-- Seletor de Obreiro -->
        <form method="GET" class="mb-4">
           
        </form>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card-custom">
                        <h5 class="text-primary mb-3 fw-bold"><i class="fas fa-paper-plane me-2"></i> Envio de Boletos Individuais</h5>
                        
                        <div class="mb-4">
                            <label for="mensagemTexto" class="form-label text-light">Digite a mensagem escolha o obreiro, mês de referência e a quantidade de parcelas que deseja enviar:</label>
                            <!--<textarea id="mensagemTexto" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?> class="form-control form-control-dark" rows="6" placeholder="Escreva sua mensagem aqui..."></textarea><br/>-->
                                <select id="membro_id" name="membro_id" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?> class="form-control text-light">
                                    <?php foreach ($membros as $m): ?>
                                        <option value="<?= $m['id'] ?>" <?= ($m['id'] == $membro_selecionado) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($m['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select><br/>
                                <select id="mes" name="mes" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?> class="form-control text-light">
                                    <option value="todos" <?php echo ('todos' == $mes_filtro) ? 'selected' : ''; ?>>Todos os Meses</option>
                                    <!-- 3. Usando o FOREACH para percorrer o array -->
                                    <?php foreach ($mesesPT as $numero_mes => $nome_mes): ?>
                                    <option value="<?php echo $numero_mes; ?>" <?php echo ($numero_mes == $mes_filtro) ? 'selected' : ''; ?>>
                                        <?php echo $nome_mes; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select><br/>
                            <input type="number" id="parcela" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?> class="form-control form-control-dark" rows="6" placeholder="Informe o numero de parcelas..."></input>
                            <small class="text-muted mt-2 d-block"><i class="fas fa-info-circle me-1"></i> Os dados serão enviado exatamente como digitado acima.</small>
                        </div>

                        <div class="d-flex flex-column flex-md-row gap-3">
                            <!-- Botão Webhook Grupo -->
                            <button type="button" class="btn btn-primary flex-grow-1 py-2 fw-bold" onclick="dispararWebhookIndividual('individual')" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?> >
                                <i class="fab fa-whatsapp me-2"></i> Enviar Boleto de Cobrança Individual
                            </button>
                            
                        </div>
                    </div>
                </div>
            </div>
            
            <br/><br/>
            
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card-custom">
                        <h5 class="text-success mb-3 fw-bold"><i class="fas fa-paper-plane me-2"></i> Envio de Boletos em Grupo</h5>
                        
                        <div class="mb-4">
                            <label for="mensagemTexto" class="form-label text-light">Digite a mensagem que deseja enviar:</label>
                            <textarea id="mensagemTexto" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?> class="form-control form-control-dark" rows="6" placeholder="Escreva sua mensagem aqui..."></textarea>
                            <small class="text-muted mt-2 d-block"><i class="fas fa-info-circle me-1"></i> A mensagem será enviada exatamente como digitada acima.</small>
                        </div>

                        <div class="d-flex flex-column flex-md-row gap-3">
                            <!-- Botão Webhook Grupo -->
                            <button type="button" class="btn btn-success flex-grow-1 py-2 fw-bold" onclick="dispararWebhook('grupo')" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?> >
                                <i class="fab fa-whatsapp me-2"></i> Enviar Situação e Cobrança para todos os Membros
                            </button>
                            
                        </div>
                    </div>
                </div>
            </div>
            
            <br/><br/>
            
             <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card-custom">
                        <h5 class="text-gold mb-3 fw-bold"><i class="fas fa-paper-plane me-2"></i> Envio de Comprovantes </h5>
                        
                        <div class="mb-4">
                            <label for="mensagemTexto" class="form-label text-light">Digite o nome da loja e do tesoureiro para enviar:</label>
                            <input type="text" id="nomeLoja" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?> class="form-control form-control-dark" rows="6" placeholder="Informe o nome da loja..."></input><br/>
                            <input type="text" id="tesoureiro" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?> class="form-control form-control-dark" rows="6" placeholder="Informe o nome do tesoureiro..."></input>
                            <small class="text-muted mt-2 d-block"><i class="fas fa-info-circle me-1"></i> Os dados serão enviado exatamente como digitado acima.</small>
                        </div>

                        <div class="d-flex flex-column flex-md-row gap-3">
                            
                            <!-- Botão Webhook Membros -->
                            <button type="button" class="btn btn-warning flex-grow-1 py-2 fw-bold" onclick="dispararWebhookMembros()" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?> >
                                <i class="fas fa-users me-2"></i> Enviar Comprovante de Pagamento para todos os Membros
                            </button> 
                        
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    
<script>
// Passa o ID do usuário logado para o Javascript
const usuarioLogadoId = <?php echo json_encode($user_id); ?>;

function dispararWebhook(tipoDestino) {
    const mensagem = document.getElementById('mensagemTexto').value.trim();
    const chavePix = <?php echo json_encode($config['chave_pix'] ?? ''); ?>;

    if (!chavePix) {
        Swal.fire('Atenção', 'Por favor, acesse o menu Configuração PIX e cadastre suas informações para continuar.', 'warning');
        return;
    }

    if (!mensagem) {
        Swal.fire('Atenção', 'Por favor, digite uma mensagem antes de enviar.', 'warning');
        return;
    }

    let tituloConfirmacao = tipoDestino === 'grupo' 
        ? 'Deseja enviar a mensagem para o Grupo do WhatsApp?' 
        : 'Deseja enviar os comprovantes de pagamentos para os membros?';

    Swal.fire({
        title: 'Confirmar Envio',
        text: tituloConfirmacao,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#22c55e',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Sim, enviar!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            
            Swal.fire({
                title: 'Enviando Mensagem...',
                html: 'Aguarde enquanto o envio é processado.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Criamos o JSON incluindo a 'acao_interna' para o PHP saber o que fazer
            const payload = {
                acao_interna: 'grupo', // Avisa o PHP que é para enviar-grupo
                mensagem: mensagem,
                usuario_id: typeof usuarioLogadoId !== 'undefined' ? usuarioLogadoId : null,
                tipo: tipoDestino
            };

            // Agora o fetch aponta para o seu PRÓPRIO servidor
            fetch('../configuracoes/acionar_webhook_mensagens', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(response => response.json()) // Transforma a resposta em JSON
            .then(data => {
                if (data.sucesso) {
                    Swal.fire('Enviado!', 'Sua mensagem foi enfileirada para envio com sucesso.', 'success')
                    .then(() => {
                        document.getElementById('mensagemTexto').value = '';
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

function dispararWebhookIndividual(tipoDestino) {
    //const mensagem = document.getElementById('mensagemTexto').value.trim();
    const membro = document.getElementById('membro_id').value.trim();
    const mes = document.getElementById('mes').value.trim();
    const parcela = document.getElementById('parcela').value.trim();
    const chavePix = <?php echo json_encode($config['chave_pix'] ?? ''); ?>;

    if (!chavePix) {
        Swal.fire('Atenção', 'Por favor, acesse o menu Configuração PIX e cadastre suas informações para continuar.', 'warning');
        return;
    }

    /*if (!mensagem) {
        Swal.fire('Atenção', 'Por favor, digite uma mensagem antes de enviar.', 'warning');
        return;
    }
    */

    let tituloConfirmacao = tipoDestino === 'individual' 
        ? 'Deseja enviar a mensagem para o Grupo do WhatsApp?' 
        : 'Deseja enviar os comprovantes de pagamentos para os membros?';

    Swal.fire({
        title: 'Confirmar Envio',
        text: tituloConfirmacao,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#22c55e',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Sim, enviar!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            
            Swal.fire({
                title: 'Enviando Mensagem...',
                html: 'Aguarde enquanto o envio é processado.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Criamos o JSON incluindo a 'acao_interna' para o PHP saber o que fazer
            const payload = {
                acao_interna: 'individual', // Avisa o PHP que é para enviar-grupo
                //mensagem: mensagem,
                membro: membro,
                mes: mes,
                parcela: parcela,
                usuario_id: typeof usuarioLogadoId !== 'undefined' ? usuarioLogadoId : null,
                tipo: tipoDestino
            };
 
            // Agora o fetch aponta para o seu PRÓPRIO servidor
            fetch('../configuracoes/acionar_webhook_mensagens', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(response => response.json()) // Transforma a resposta em JSON
            .then(data => {
                if (data.sucesso) {
                    Swal.fire('Enviado!', 'Sua mensagem foi enfileirada para envio com sucesso.', 'success')
                    .then(() => {
                        document.getElementById('mensagemTexto').value = '';
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

function dispararWebhookMembros() {
    const nomeLoja = document.getElementById('nomeLoja').value.trim();
    const tesoureiro = document.getElementById('tesoureiro').value.trim();
    
    Swal.fire({
        title: 'Confirmar Envio',
        text: 'Deseja enviar os comprovantes de pagamentos para os membros?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#22c55e',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Sim, enviar!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            
            Swal.fire({
                title: 'Enviando Comprovantes...',
                html: 'Aguarde enquanto o envio é processado.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Adiciona a 'acao_interna' para o webhook de membros
            const payload = {
                acao_interna: 'membros', // Avisa o PHP que é para enviar-membros
                loja: nomeLoja,
                usuario_id: typeof usuarioLogadoId !== 'undefined' ? usuarioLogadoId : null,
                tesoureiro: tesoureiro
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
                    Swal.fire('Enviado!', 'Seus comprovantes foram enfileirados para envio com sucesso.', 'success')
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
