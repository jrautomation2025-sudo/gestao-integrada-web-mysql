<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../configuracoes/config.php';

if (!isset($_SESSION['tenant_id'])) {
    die("Acesso negado. Redirecionando para le login...");
}
$tenant_id = $_SESSION['tenant_id'];

$mesAtual = (int)date('n');
$anoAtual = (int)date('Y');
$meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];

try {
    // Busca os obreiros ativos
    $stmt = $pdo->prepare("SELECT id, nome, data_nascimento, data_iniciacao, telefone, email 
                           FROM chancelaria_membros 
                           WHERE tenant_id = ? AND status = 'Ativo' 
                           ORDER BY nome ASC");
    $stmt->execute([$tenant_id]);
    $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $natalicios = array_fill_keys(array_keys($meses), []);
    $maconicos = array_fill_keys(array_keys($meses), []);

    foreach ($membros as $m) {
        if (!empty($m['data_nascimento'])) {
            $mesNasc = (int)date('n', strtotime($m['data_nascimento']));
            $natalicios[$mesNasc][] = $m;
        }
        if (!empty($m['data_iniciacao'])) {
            $mesInic = (int)date('n', strtotime($m['data_iniciacao']));
            $maconicos[$mesInic][] = $m;
        }
    }

    $ordenarPorDia = function($campo) {
        return function($a, $b) use ($campo) {
            return (int)date('d', strtotime($a[$campo])) - (int)date('d', strtotime($b[$campo]));
        };
    };

    foreach ($meses as $num => $nome) {
        usort($natalicios[$num], $ordenarPorDia('data_nascimento'));
        usort($maconicos[$num], $ordenarPorDia('data_iniciacao'));
    }

    // Total de alertas para o sininho (aniversariantes do mês atual)
    $totalAniversariantesMes = count($natalicios[$mesAtual]) + count($maconicos[$mesAtual]);

} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}

function linkWhatsapp($telefone) {
    $numero = preg_replace('/[^0-9]/', '', $telefone);
    if(strlen($numero) >= 10 && substr($numero, 0, 2) !== '55') {
        $numero = '55' . $numero;
    }
    return "https://wa.me/" . $numero;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aniversariantes - Gestão Integrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { --bg-main: #141724; --bg-card: #222738; --text-main: #e2e8f0; --gold: #f5c041; --border-color: #333951; }
        body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 260px; padding: 30px 40px; width: calc(100% - 260px); }
        
        /* Estilo do Sininho de Notificação */
        .notification-bell {
            position: relative;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--gold);
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 1.2rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        .notification-bell:hover {
            background: rgba(245, 192, 65, 0.1);
            color: #fff;
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ef4444;
            color: white;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 50%;
            border: 2px solid var(--bg-main);
        }

        /* Estilos das Abas */
        .nav-tabs { border-bottom: 2px solid var(--border-color); }
        .nav-tabs .nav-link { color: #94a3b8; border: none; font-weight: 600; text-transform: uppercase; padding: 12px 25px; }
        .nav-tabs .nav-link:hover { color: var(--gold); }
        .nav-tabs .nav-link.active { background: transparent; color: var(--gold); border-bottom: 3px solid var(--gold); }
        
        /* Estilos do Accordion Dark */
        .accordion-item { background-color: var(--bg-card); border: 1px solid var(--border-color); margin-bottom: 10px; border-radius: 8px !important; overflow: hidden; }
        .accordion-button { background-color: var(--bg-card); color: var(--text-main); font-weight: 600; font-size: 1.1rem; box-shadow: none !important; }
        .accordion-button:not(.collapsed) { background-color: rgba(245, 192, 65, 0.1); color: var(--gold); }
        .accordion-button::after { filter: invert(1); }
        .accordion-button:not(.collapsed)::after { filter: invert(0.8) sepia(1) saturate(5) hue-rotate(350deg); }
        .accordion-body { background-color: var(--bg-main); border-top: 1px solid var(--border-color); padding: 0; }
        
        .table-dark-custom { color: var(--text-main); margin-bottom: 0; }
        .table-dark-custom td { border-bottom: 1px solid var(--border-color); padding: 15px 20px; vertical-align: middle; background: transparent; }
        .table-dark-custom tr:last-child td { border-bottom: none; }
        
        .mes-destaque .accordion-button { border-left: 4px solid var(--gold); }
        .badge-dia { font-size: 1.2rem; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--gold); padding: 8px 12px; border-radius: 6px; font-weight: 700; display: inline-block; min-width: 50px; text-align: center; }
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
        <!-- Cabeçalho com o Sininho de Alerta -->
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;"><i class="fas fas fa-birthday-cake me-2 text-warning"></i> Aniversariantes</h2>
                <p class="text-warning mb-0">Acompanhe as idades e os aniversários de iniciação dos obreiros.</p>
            </div>
            
            <!-- Sininho -->
            <div class="d-flex align-items-center">
                <a href="#accordionNatalicios" class="notification-bell" title="<?= $totalAniversariantesMes ?> aniversariantes neste mês!">
                    <i class="fas fa-bell"></i>
                    <?php if ($totalAniversariantesMes > 0): ?>
                        <span class="notification-badge"><?= $totalAniversariantesMes ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <ul class="nav nav-tabs mb-4" id="aniversariosTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="natalicios-tab" data-bs-toggle="tab" data-bs-target="#natalicios" type="button" role="tab">
                    <i class="fas fa-gift me-2"></i> Natalícios
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="maconicos-tab" data-bs-toggle="tab" data-bs-target="#maconicos" type="button" role="tab">
                    <i class="fas fa-compass me-2"></i> Maçônicos
                </button>
            </li>
        </ul>

        <div class="tab-content" id="aniversariosTabContent">
            
            <!-- ABA: NATALÍCIOS -->
            <div class="tab-pane fade show active" id="natalicios" role="tabpanel">
                <div class="accordion" id="accordionNatalicios">
                    <?php foreach ($meses as $numMes => $nomeMes): ?>
                        <?php 
                        $temAniversariante = count($natalicios[$numMes]) > 0;
                        $isMesAtual = ($numMes === $mesAtual);
                        $collapseId = "collapseNat" . $numMes;
                        ?>
                        <div class="accordion-item <?= $isMesAtual ? 'mes-destaque' : '' ?>">
                            <h2 class="accordion-header">
                                <button class="accordion-button <?= $isMesAtual ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>">
                                    <?= $nomeMes ?>
                                    <?php if($isMesAtual): ?>
                                        <span class="badge bg-warning text-dark ms-3 fs-6">Mês Atual</span>
                                    <?php endif; ?>
                                    <span class="ms-auto me-3 badge bg-secondary rounded-pill"><?= count($natalicios[$numMes]) ?></span>
                                </button>
                            </h2>
                            <div id="<?= $collapseId ?>" class="accordion-collapse collapse <?= $isMesAtual ? 'show' : '' ?>" data-bs-parent="#accordionNatalicios">
                                <div class="accordion-body">
                                    <?php if ($temAniversariante): ?>
                                        <table class="table table-dark-custom table-hover">
                                            <tbody>
                                                <?php foreach ($natalicios[$numMes] as $ob): ?>
                                                    <?php 
                                                        $anoNascimento = (int)date('Y', strtotime($ob['data_nascimento']));
                                                        $idade = $anoAtual - $anoNascimento;
                                                    ?>
                                                    <tr>
                                                        <td width="10%">
                                                            <div class="badge-dia"><?= date('d', strtotime($ob['data_nascimento'])) ?></div>
                                                        </td>
                                                        <td width="50%">
                                                            <div class="fw-bold fs-5"><?= htmlspecialchars($ob['nome']) ?></div>
                                                            <small class="text-warning fw-bold"><?= $idade ?> anos de idade</small>
                                                            <small class="text-muted ms-2">(Nasceu em <?= date('Y', strtotime($ob['data_nascimento'])) ?>)</small>
                                                        </td>
                                                        <td width="40%" class="text-end">
                                                            <?php if(!empty($ob['telefone'])): ?>
                                                                <button class="btn btn-sm btn-outline-success" 
                                                                    onclick="enviarFelicitacoes(<?= htmlspecialchars(json_encode($ob['nome']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($ob['telefone']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($ob['email']), ENT_QUOTES, 'UTF-8') ?>, <?= $tenant_id ?>)" <?= ($_SESSION['is_admin'] == 0) ? 'disabled' : '' ?>>
                                                                    <i class="fab fa-whatsapp me-1"></i> Felicitar
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="text-muted"><i class="fas fa-phone-slash"></i> Sem contato</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <div class="p-4 text-center text-white">Nenhum irmão faz aniversário neste mês.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ABA: MAÇÔNICOS -->
            <div class="tab-pane fade" id="maconicos" role="tabpanel">
                <div class="accordion" id="accordionMaconicos">
                    <?php foreach ($meses as $numMes => $nomeMes): ?>
                        <?php 
                        $temAniversariante = count($maconicos[$numMes]) > 0;
                        $isMesAtual = ($numMes === $mesAtual);
                        $collapseId = "collapseMac" . $numMes;
                        ?>
                        <div class="accordion-item <?= $isMesAtual ? 'mes-destaque' : '' ?>">
                            <h2 class="accordion-header">
                                <button class="accordion-button <?= $isMesAtual ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>">
                                    <?= $nomeMes ?>
                                    <?php if($isMesAtual): ?>
                                        <span class="badge bg-warning text-dark ms-3 fs-6">Mês Atual</span>
                                    <?php endif; ?>
                                    <span class="ms-auto me-3 badge bg-secondary rounded-pill"><?= count($maconicos[$numMes]) ?></span>
                                </button>
                            </h2>
                            <div id="<?= $collapseId ?>" class="accordion-collapse collapse <?= $isMesAtual ? 'show' : '' ?>" data-bs-parent="#accordionMaconicos">
                                <div class="accordion-body">
                                    <?php if ($temAniversariante): ?>
                                        <table class="table table-dark-custom table-hover">
                                            <tbody>
                                                <?php foreach ($maconicos[$numMes] as $ob): ?>
                                                    <?php 
                                                        $anoIniciacao = (int)date('Y', strtotime($ob['data_iniciacao']));
                                                        $anosMaconaria = $anoAtual - $anoIniciacao;
                                                    ?>
                                                    <tr>
                                                        <td width="10%">
                                                            <div class="badge-dia"><?= date('d', strtotime($ob['data_iniciacao'])) ?></div>
                                                        </td>
                                                        <td width="50%">
                                                            <div class="fw-bold fs-5"><?= htmlspecialchars($ob['nome']) ?></div>
                                                            <small class="text-warning fw-bold"><?= $anosMaconaria ?> anos de Maçonaria</small> 
                                                            <small class="text-muted ms-2">(Iniciado em <?= date('Y', strtotime($ob['data_iniciacao'])) ?>)</small>
                                                        </td>
                                                        <td width="40%" class="text-end">
                                                            <?php if(!empty($ob['telefone'])): ?>
                                                                <a href="<?= linkWhatsapp($ob['telefone']) ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                                                    <i class="fab fa-whatsapp me-1"></i> Felicitar
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="text-muted"><i class="fas fa-phone-slash"></i> Sem contato</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <div class="p-4 text-center text-white">Nenhum irmão completa ano maçônico neste mês.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
<script>
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

    function enviarFelicitacoes(nome, telefone, email, tenant_id) {
    
    Swal.fire({
        title: 'Confirmar Envio',
        text: 'Deseja enviar mensagem de felicidades para o membro?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#22c55e',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Sim, enviar!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            
            Swal.fire({
                title: 'Enviando mensagem...',
                html: 'Aguarde enquanto o envio é processado.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Adiciona a 'acao_interna' para o webhook de membros
            const payload = {
                acao_interna: 'felicitacoes', 
                action: 'aniversario',
                nome: nome, // Avisa o PHP que é para enviar-membros
                telefone: telefone,
                email: email,
                tenant_id: tenant_id
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
                    Swal.fire('Enviado!', 'Sua mensagem foi enviada com sucesso.', 'success')
                    .then(() => {
                        console.log('sucesso');
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