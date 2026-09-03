<?php
// 1. Incluir a conexão e iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../configuracoes/config.php'; // Ajuste o caminho se necessário

$tenant_id = $_SESSION['tenant_id']; // Em produção, virá da $_SESSION['tenant_id']

try {
    // ---------------------------------------------------------
    // CONSULTAS PARA OS CARDS SUPERIORES
    // ---------------------------------------------------------
    
    // 1. Total de Obreiros
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM chancelaria_membros WHERE tenant_id = ? AND status = 'Ativo'");
    $stmt->execute([$tenant_id]);
    $total_obreiros = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM chancelaria_membros WHERE tenant_id = ? AND status = 'Ativo' AND presenca = 'isento'");
    $stmt->execute([$tenant_id]);
    $total_obreiros_isentos = $stmt->fetchColumn();
    
    $total_obreiros_percentual = $total_obreiros - $total_obreiros_isentos;

    // 2. Sessões Realizadas no Ano
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM chancelaria_sessoes WHERE tenant_id = ? AND YEAR(data_sessao) = YEAR(CURDATE()) AND data_sessao <= CURDATE() AND status != 'Cancelada'");
    $stmt->execute([$tenant_id]);
    $sessoes_ano = $stmt->fetchColumn();

    // 3. Aniversariantes do Mês
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM chancelaria_membros WHERE tenant_id = ? AND MONTH(data_nascimento) = MONTH(CURDATE())");
    $stmt->execute([$tenant_id]);
    $aniversariantes_mes = $stmt->fetchColumn();

    // 4. Média de Frequência Geral (Considerando o total de obreiros ativos da loja)
    $stmt = $pdo->prepare("
        SELECT AVG(freq_sessao) as media_geral FROM (
            SELECT 
                cs.id,
                (SUM(CASE WHEN cp.status_presenca = 'P' THEN 1 ELSE 0 END) / ?) * 100 as freq_sessao
            FROM chancelaria_sessoes cs
            LEFT JOIN chancelaria_presencas cp ON cs.id = cp.sessao_id
            WHERE cs.tenant_id = ? AND YEAR(cs.data_sessao) = YEAR(CURDATE()) AND cs.status != 'Cancelada'
            GROUP BY cs.id
        ) t
    ");
    $stmt->execute([$total_obreiros_percentual, $tenant_id]);
    $media_freq_calc = $stmt->fetchColumn();
    $media_frequencia = $media_freq_calc ? number_format($media_freq_calc, 1, ',', '.') . '%' : '0,0%';

    // ---------------------------------------------------------
    // CONSULTAS DE DESTAQUE E LISTAS
    // ---------------------------------------------------------

    // 5. Próxima Sessão Agendada
    $stmt = $pdo->prepare("SELECT tipo, data_sessao, grau, titulo FROM chancelaria_sessoes WHERE tenant_id = ? AND data_sessao >= CURDATE() ORDER BY data_sessao ASC LIMIT 1");
    $stmt->execute([$tenant_id]);
    $proxima_sessao = $stmt->fetch(PDO::FETCH_ASSOC);

    // 6. Últimas 4 Sessões Realizadas
    $stmt = $pdo->prepare("SELECT id, tipo, data_sessao, grau, status FROM chancelaria_sessoes WHERE tenant_id = ? AND data_sessao < CURDATE() ORDER BY data_sessao DESC LIMIT 4");
    $stmt->execute([$tenant_id]);
    $ultimas_sessoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$tenant_id]);
    $meuPerfil = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 7. Evolução da Frequência por Mês (Baseada no Total de Obreiros Ativos)
    $stmt = $pdo->prepare("
        SELECT 
            mes_num,
            AVG(freq_sessao) as media_mes
        FROM (
            SELECT 
                MONTH(cs.data_sessao) as mes_num,
                (SUM(CASE WHEN cp.status_presenca = 'P' THEN 1 ELSE 0 END) / ?) * 100 as freq_sessao
            FROM chancelaria_sessoes cs
            LEFT JOIN chancelaria_presencas cp ON cs.id = cp.sessao_id
            WHERE cs.tenant_id = ? AND YEAR(cs.data_sessao) = YEAR(CURDATE()) AND cs.status != 'Cancelada'
            GROUP BY cs.id
        ) sessoes_mes
        GROUP BY mes_num
        ORDER BY mes_num ASC
    ");
    $stmt->execute([$total_obreiros, $tenant_id]);
    $dados_evolucao = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mapeia os resultados por número do mês
    $frequenciaPorMes = [];
    foreach ($dados_evolucao as $row) {
        $frequenciaPorMes[(int)$row['mes_num']] = round((float)$row['media_mes'], 1);
    }

    // Monta o array final com os 12 meses do ano
    $mediaValores = [];
    for ($m = 1; $m <= 12; $m++) {
        $mediaValores[] = $frequenciaPorMes[$m] ?? 0;
    }
    
} catch (PDOException $e) {
    die("Erro ao carregar dados do Dashboard: " . $e->getMessage());
}

// Array auxiliar para os nomes dos meses em português
$meses = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gestão Integrada</title>
    <link rel="icon" href="../configuracoes/icone.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --bg-main: #141724;
            --bg-sidebar: #1d2132;
            --bg-card: #222738;
            --text-main: #e2e8f0;
            --text-muted: #8b92a5;
            --gold: #f5c041;
            --gold-hover: #dca732;
            --border-color: #333951;
            --success: #22c55e;
            --danger: #ef4444;
        }

        body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Inter', sans-serif; margin: 0; overflow-x: hidden; display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background-color: var(--bg-sidebar); border-right: 1px solid var(--border-color); display: flex; flex-direction: column; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-brand { padding: 25px 20px; font-family: 'Cinzel', serif; color: var(--gold); font-size: 1.4rem; text-align: center; border-bottom: 1px solid var(--border-color); }
        .sidebar-section { padding: 20px 20px 5px; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        .sidebar-nav { list-style: none; padding: 0; margin: 0; }
        .sidebar-nav li a { display: flex; align-items: center; padding: 12px 20px; color: var(--text-main); text-decoration: none; transition: all 0.2s; font-size: 0.95rem; }
        .sidebar-nav li a i { width: 25px; color: var(--text-muted); transition: color 0.2s; }
        .sidebar-nav li a:hover, .sidebar-nav li a.active { background-color: rgba(245, 192, 65, 0.05); color: var(--gold); }
        .sidebar-nav li a:hover i, .sidebar-nav li a.active i { color: var(--gold); }
        
        .main-content { flex: 1; margin-left: 260px; padding: 30px 40px; width: calc(100% - 260px); }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .greeting h2 { font-weight: 600; margin-bottom: 5px; font-size: 1.8rem; }
        .badge-pro { background-color: rgba(245, 192, 65, 0.2); color: var(--gold); padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; border: 1px solid rgba(245, 192, 65, 0.4); }
        .dash-card { background-color: var(--bg-card); border-radius: 8px; padding: 20px; border: 1px solid var(--border-color); height: 100%; }
        .dash-card-title { color: var(--text-muted); font-size: 0.85rem; margin-bottom: 10px; font-weight: 500; }
        .dash-card-value { font-size: 1.5rem; font-weight: 500; }
        .dash-card-highlight { border-color: var(--gold); text-align: center; padding: 30px; }
        .dash-card-highlight .dash-card-title { color: var(--gold); font-size: 0.9rem; letter-spacing: 1px; text-transform: uppercase; }
        .dash-card-highlight .dash-card-value { font-size: 2.5rem; font-weight: 600; }
        .section-title { font-size: 1rem; color: var(--gold); margin-bottom: 20px; font-weight: 600; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
        .list-item { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid var(--border-color); }
        .list-item:last-child { border-bottom: none; }
        .list-title { font-weight: 500; margin-bottom: 3px; }
        .list-subtitle { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; }
        .list-value { font-weight: 600; }
        .text-green { color: var(--success); }
        .text-red { color: var(--danger); }
    </style>
</head>
<body>
    
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
        
        <div class="page-header">
            <div class="greeting">
                <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 1.8rem;"> Olá, Chanceler!</h2>
                <span class="badge-pro"><i class="fas fa-gem me-1"></i> Loja Ativa <?php echo htmlspecialchars($meuPerfil['nome']); ?> </span>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="dash-card">
                    <div class="dash-card-title">Total de Obreiros Ativos</div>
                    <div class="dash-card-value"><?= $total_obreiros ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dash-card">
                    <div class="dash-card-title">Sessões Realizadas (Ano)</div>
                    <div class="dash-card-value"><?= $sessoes_ano ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dash-card">
                    <div class="dash-card-title">Média de Frequência</div>
                    <div class="dash-card-value text-green"><?= $media_frequencia ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dash-card">
                    <div class="dash-card-title">Aniversariantes do Mês</div>
                    <div class="dash-card-value"><?= $aniversariantes_mes ?></div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="dash-card dash-card-highlight">
                    <div class="dash-card-title">Próxima Sessão Agendada</div>
                    <?php if ($proxima_sessao): 
                        $data_formatada = date('d/m/Y', strtotime($proxima_sessao['data_sessao']));
                    ?>
                        <div class="dash-card-value"><?= htmlspecialchars($proxima_sessao['tipo']) ?></div>
                        <div class="text-white mt-2">
                            <?= $data_formatada ?> • Grau <?= $proxima_sessao['grau'] ?>
                            <?php if(!empty($proxima_sessao['titulo'])): ?>
                                <br><small><i class="fas fa-info-circle"></i> <?= htmlspecialchars($proxima_sessao['titulo']) ?></small>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="dash-card-value" style="color: var(--text-muted);">Nenhuma sessão futura agendada</div>
                        <div class="text-muted mt-2">Utilize o botão "+ Nova Sessão" acima.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-7">
                <div class="dash-card">
                    <div class="section-title mb-0 border-0"><i class="fas fa-chart-bar me-2"></i> Evolução da Frequência</div>
                    <hr style="border-color: var(--border-color);">
                    <!-- Canvas para renderizar o Chart.js -->
                    <div style="height: 270px; position: relative;">
                        <canvas id="graficoEvolucaoFreq"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                <div class="dash-card">
                    <div class="section-title"><i class="fas fa-history me-2"></i> Últimas Sessões</div>
                    
                    <?php if (count($ultimas_sessoes) > 0): ?>
                        <?php foreach ($ultimas_sessoes as $sessao): ?>
                            <div class="list-item">
                                <div>
                                    <div class="list-title"><?= htmlspecialchars($sessao['tipo']) ?></div>
                                    <div class="list-subtitle">Grau <?= $sessao['grau'] ?> • <?= date('d/m/Y', strtotime($sessao['data_sessao'])) ?></div>
                                </div>
                                <?php 
                                    $statusAtual = $sessao['status'] ?? 'Realizada';
                                    $corClasse = 'text-success';
                                    $iconeClasse = 'fas fa-check';

                                    if ($statusAtual === 'Agendada') {
                                        $corClasse = 'text-warning';
                                        $iconeClasse = 'fas fa-clock';
                                    } elseif ($statusAtual === 'Cancelada') {
                                        $corClasse = 'text-danger';
                                        $iconeClasse = 'fas fa-ban';
                                    }
                                ?>
                                <div class="list-value <?= $corClasse ?>" style="font-size: 0.8rem;">
                                    <i class="<?= $iconeClasse ?> me-1"></i> <?= htmlspecialchars($statusAtual) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-white py-4">Nenhuma sessão anterior encontrada.</div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
        
        <!-- Modal Nova Sessão -->
        <div class="modal fade" id="modalNovaSessao" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="background-color: var(--bg-card); border: 1px solid var(--border-color);">
                    <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                        <h5 class="modal-title" style="font-family: 'Cinzel', serif; color: var(--gold);">
                            <i class="fas fa-gavel me-2"></i> Agendar Nova Sessão
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="formNovaSessao">
                        <div class="modal-body">
                            <style>
                                .modal-body .form-control, .modal-body .form-select {
                                    background-color: var(--bg-main);
                                    border: 1px solid var(--border-color);
                                    color: var(--text-main);
                                }
                                .modal-body .form-control:focus, .modal-body .form-select:focus {
                                    border-color: var(--gold);
                                    box-shadow: 0 0 0 0.25rem rgba(245, 192, 65, 0.25);
                                }
                                ::-webkit-calendar-picker-indicator { filter: invert(1); }
                            </style>
                            <div class="mb-3">
                                <label class="form-label text-muted">Data da Sessão</label>
                                <input type="date" class="form-control" name="data_sessao" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted">Tipo de Sessão</label>
                                <select class="form-select" name="tipo" required>
                                    <option value="Ordinária">Ordinária</option>
                                    <option value="Magna de Iniciação">Magna de Iniciação</option>
                                    <option value="Magna de Elevação">Magna de Elevação</option>
                                    <option value="Magna de Exaltação">Magna de Exaltação</option>
                                    <option value="Administrativa">Administrativa</option>
                                    <option value="Pompas Fúnebres">Pompas Fúnebres</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted">Grau de Trabalho</label>
                                <select class="form-select" name="grau_trabalho" required>
                                    <option value="1">1º Grau - Aprendiz</option>
                                    <option value="2">2º Grau - Companheiro</option>
                                    <option value="3">3º Grau - Mestre</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted">Assunto / Pauta (Opcional)</label>
                                <input type="text" class="form-control" name="assunto" placeholder="Ex: Apresentação de Peça de Arquitetura">
                            </div>
                        </div>
                        <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: #333951; border: none;">Cancelar</button>
                            <button type="submit" class="btn btn-warning">Salvar Sessão</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
    
<script>
document.getElementById('formNovaSessao').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());
    data.acao = 'nova_sessao';

    const btnSubmit = this.querySelector('button[type="submit"]');
    const textoOriginal = btnSubmit.innerHTML;
    btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
    btnSubmit.disabled = true;

    try {
        const response = await fetch('sessao_action', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();

        if (result.sucesso) {
            var modal = bootstrap.Modal.getInstance(document.getElementById('modalNovaSessao'));
            modal.hide();
            Swal.fire({
                icon: 'success',
                title: 'Sessão Agendada!',
                text: 'A nova sessão foi registrada com sucesso.',
                background: '#222738', color: '#e2e8f0', confirmButtonColor: '#f5c041'
            }).then(() => {
                document.getElementById('formNovaSessao').reset();
            });
        } else {
            Swal.fire({
                icon: 'error', title: 'Erro', text: result.mensagem,
                background: '#222738', color: '#e2e8f0', confirmButtonColor: '#f5c041'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error', title: 'Erro de Conexão', text: 'Não foi possível salvar a sessão.',
            background: '#222738', color: '#e2e8f0', confirmButtonColor: '#f5c041'
        });
    } finally {
        btnSubmit.innerHTML = textoOriginal;
        btnSubmit.disabled = false;
    }
});

function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (sidebar) { sidebar.classList.toggle('show'); }
    if (backdrop) { backdrop.classList.toggle('show'); }
}

// Inicialização segura do Gráfico de Evolução da Frequência via Chart.js
document.addEventListener("DOMContentLoaded", function() {
    const dadosMedia = <?= json_encode($mediaValores) ?>;

    const ctx = document.getElementById('graficoEvolucaoFreq').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
            datasets: [{
                label: 'Média de Frequência (%)',
                data: dadosMedia,
                backgroundColor: 'rgba(245, 192, 65, 0.1)',
                borderColor: '#f5c041',
                borderWidth: 2,
                pointBackgroundColor: '#f5c041',
                pointBorderColor: '#222738',
                pointRadius: 4,
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ' Média: ' + context.parsed.y + '%';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    grid: { color: 'rgba(51, 57, 81, 0.5)' },
                    ticks: {
                        color: '#8b92a5',
                        callback: function(value) { return value + '%'; }
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#8b92a5' }
                }
            }
        }
    });
});
</script>

<?php if (isset($_SESSION['show_2fa_alert']) && $_SESSION['show_2fa_alert'] === true): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            title: '🔒 Aumente sua Segurança!',
            html: "Notamos que você ainda não ativou a <strong>Autenticação de Dois Fatores (2FA)</strong>.<br>Isso protege sua conta mesmo que sua senha seja roubada.",
            icon: 'info',
            background: '#1e293b',
            color: '#fff',
            showCancelButton: true,
            confirmButtonText: '🛡️ Ativar Agora',
            confirmButtonColor: '#cfa34e',
            cancelButtonText: 'Agora não',
            cancelButtonColor: '#64748b',
            showDenyButton: true,
            denyButtonText: 'Não lembrar mais',
            denyButtonColor: '#ef4444',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '../configuracoes/seguranca.php';
            } else if (result.isDenied) {
                fetch('../configuracoes/auth.php?action=ignore_2fa');
                Swal.fire({
                    title: 'Entendido!', 
                    text: 'Não vamos mais te incomodar com isso.', 
                    icon: 'success',
                    background: '#1e293b', 
                    color: '#fff',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });
    });
</script>
<?php 
    unset($_SESSION['show_2fa_alert']); 
endif; 
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>