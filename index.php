<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Maçônico - Gestão Integrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Segoe+UI:wght@400;600&display=swap" rel="stylesheet">

    <style>
        :root { --bg-dark: #0f172a; --bg-card: #1e293b; --gold: #cfa34e; --text-light: #f1f5f9; }
        body { background-color: var(--bg-dark); color: var(--text-light); font-family: 'Segoe UI', sans-serif; overflow-x: hidden; }
        
        .font-cinzel { font-family: 'Cinzel', serif; }
        .text-gold { color: var(--gold) !important; }
        .bg-card-custom { background: var(--bg-card); border: 1px solid #334155; border-radius: 12px; }
        
        .btn-gold { background: var(--gold); border: none; color: #000; font-weight: bold; transition: all 0.3s ease; }
        .btn-gold:hover { background: #b8860b; color: #fff; box-shadow: 0 0 15px rgba(207,163,78,0.4); transform: translateY(-2px); }

        .hero-section { padding: 100px 0 60px 0; background: radial-gradient(circle at center, #1e293b 0%, #0f172a 70%); }
        
        .card-pricing { background: var(--bg-card); border: 1px solid #334155; border-radius: 16px; transition: transform 0.3s, border-color 0.3s; position: relative; overflow: hidden; }
        .card-pricing:hover { transform: translateY(-8px); border-color: var(--gold); }
        .card-pricing.featured { border: 2px solid var(--gold); box-shadow: 0 10px 30px rgba(207,163,78,0.15); }
        .badge-featured { position: absolute; top: 15px; right: -30px; background: var(--gold); color: #000; font-size: 0.75rem; font-weight: bold; padding: 5px 35px; transform: rotate(45deg); text-transform: uppercase; }

        .feature-icon { width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; background: rgba(207,163,78,0.1); border-radius: 12px; color: var(--gold); font-size: 1.5rem; margin-bottom: 20px; }
        
        .navbar-custom { background-color: rgba(15, 23, 42, 0.95); backdrop-filter: blur(10px); border-bottom: 1px solid #334155; }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top navbar-custom px-4">
        <div class="container">
            <a class="navbar-brand font-cinzel fw-bold text-gold fs-4" href="#">
                <i class="fas fa-building me-2"></i> GESTÃO DE LOJAS MAÇÔNICAS
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center gap-3">
                    <li class="nav-item"><a class="nav-link" href="#recursos">Recursos</a></li>
                    <li class="nav-item"><a class="nav-link" href="#planos">Planos</a></li>
                    <li class="nav-item"><a class="nav-link" href="sistema">Acessar Sistema</a></li>
                    <li class="nav-item"><a href="cadastro" class="btn btn-gold btn-sm px-4">Criar Conta Grátis</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero-section text-center">
        <div class="container">
            <span class="badge bg-secondary text-warning mb-3 px-3 py-2 rounded-pill fw-semibold">
                <i class="fas fa-star me-1 text-gold"></i> Oferta de Lançamento: 6 Meses Completamente Livres
            </span>
            <h1 class="font-cinzel fw-bold display-4 mb-4 text-white">
                Controle da Loja Totalmente <span class="text-gold">Simplificado</span>
            </h1>
            <p class="lead text-muted mx-auto mb-5" style="max-width: 700px;">
                Automatize o controle de tesouraria, gerencie a presença dos irmãos, emita relatórios, expediente, oficios e atas com facilidade e envie documentos automatizados de forma profissional.
            </p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="cadastro" class="btn btn-gold btn-lg px-5 py-3 fw-bold">
                    <i class="fas fa-rocket me-2"></i> Testar 6 Meses Grátis
                </a>
                <a href="#recursos" class="btn btn-outline-light btn-lg px-4 py-3">
                    Conhecer Recursos
                </a>
            </div>
        </div>
    </header>

    <!-- Recursos Section -->
    <section id="recursos" class="py-5 bg-card-custom mx-3 mx-lg-5 my-5 px-4">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h6 class="text-gold text-uppercase fw-bold">Tecnologia e Eficiência</h6>
                <h2 class="font-cinzel fw-bold">Tudo o que sua administração precisa em um só lugar</h2>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card-pricing p-4 h-100 bg-dark">
                        <div class="feature-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                        <h4 class="font-cinzel h5 mb-3">Tesouraria Integrada</h4>
                        <p class="text-muted small mb-0">Espelhamento automático de lançamentos nas transações principais, controle de recebidos e pendências mensais e anuais.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card-pricing p-4 h-100 bg-dark">
                        <div class="feature-icon"><i class="fas fa-paper-plane"></i></div>
                        <h4 class="font-cinzel h5 mb-3">Recibos via Webhook</h4>
                        <p class="text-muted small mb-0">Integração nativa para disparo de recibos digitais e notificações automatizadas diretamente para os responsáveis.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card-pricing p-4 h-100 bg-dark">
                        <div class="feature-icon"><i class="fas fa-print"></i></div>
                        <h4 class="font-cinzel h5 mb-3">Relatórios Prontos para Impressão</h4>
                        <p class="text-muted small mb-0">Geração de relatórios detalhados com design limpo, otimizados para impressão rápida e auditoria contábil.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Planos Section -->
    <section id="planos" class="py-5">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h6 class="text-gold text-uppercase fw-bold">Investimento Transparente</h6>
                <h2 class="font-cinzel fw-bold">Escolha o plano ideal para sua Loja ou Oriente</h2>
                <p class="text-muted">Comece a testar hoje mesmo sem compromisso e sem cartão de crédito.</p>
            </div>

            <div class="row g-4 align-items-stretch justify-content-center">
                
                <!-- Plano Gratuito (Teste 6 Meses) -->
                <div class="col-lg-4 col-md-6">
                    <div class="card-pricing p-4 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="text-center mb-4">
                                <span class="badge bg-success text-white mb-2">Popular / Teste</span>
                                <h3 class="font-cinzel fw-bold h4">Acesso de Teste</h3>
                                <p class="text-muted small">Para conhecer todas as ferramentas</p>
                                <div class="display-5 fw-bold text-white my-3">Grátis</div>
                                <p class="text-gold small mb-0">Liberado por 6 meses</p>
                            </div>
                            <ul class="list-unstyled text-muted small mb-4">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Acesso completo ao sistema</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Cadastro de Lojas e Responsáveis</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Controle de Aluguéis e Baixas</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Relatórios em tempo real</li>
                            </ul>
                        </div>
                        <a href="cadastro.php?plano=free" class="btn btn-outline-warning w-100 py-2 fw-bold">Começar 6 Meses Grátis</a>
                    </div>
                </div>

                <!-- Plano Mensal -->
                <div class="col-lg-4 col-md-6">
                    <div class="card-pricing p-4 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="text-center mb-4">
                                <span class="badge bg-secondary text-white mb-2">Flexível</span>
                                <h3 class="font-cinzel fw-bold h4">Plano Mensal</h3>
                                <p class="text-muted small">Ideal para uso contínuo sem fidelidade</p>
                                <div class="display-5 fw-bold text-gold my-3">R$ 49,<span class="fs-4">90</span></div>
                                <p class="text-muted small mb-0">Cobrado mensalmente</p>
                            </div>
                            <ul class="list-unstyled text-muted small mb-4">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Todos os recursos liberados</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Suporte técnico prioritário</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Atualizações automáticas</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Sem limite de registros mensais</li>
                            </ul>
                        </div>
                        <a href="cadastro.php?plano=mensal" class="btn btn-outline-light w-100 py-2 fw-bold">Assinar Mensal</a>
                    </div>
                </div>

                <!-- Plano Anual (Destaque) -->
                <div class="col-lg-4 col-md-6">
                    <div class="card-pricing featured p-4 h-100 d-flex flex-column justify-content-between">
                        <div class="badge-featured">Melhor Valor</div>
                        <div>
                            <div class="text-center mb-4">
                                <span class="badge bg-warning text-dark mb-2 fw-bold">Economia Máxima</span>
                                <h3 class="font-cinzel fw-bold h4">Plano Anual</h3>
                                <p class="text-muted small">Para uma gestão tranquila o ano todo</p>
                                <div class="display-5 fw-bold text-gold my-3">R$ 499,<span class="fs-4">90</span></div>
                                <p class="text-muted small mb-0">Equivale a aprox. R$ 41,65/mês</p>
                            </div>
                            <ul class="list-unstyled text-muted small mb-4">
                                <li class="mb-2"><i class="fas fa-check text-gold me-2"></i> <strong>2 meses de economia</strong> comparado ao mensal</li>
                                <li class="mb-2"><i class="fas fa-check text-gold me-2"></i> Todos os recursos e atualizações</li>
                                <li class="mb-2"><i class="fas fa-check text-gold me-2"></i> Suporte prioritário via WhatsApp</li>
                                <li class="mb-2"><i class="fas fa-check text-gold me-2"></i> Backup automático garantido</li>
                            </ul>
                        </div>
                        <a href="cadastro.php?plano=anual" class="btn btn-gold w-100 py-2 fw-bold">Assinar Anual com Desconto</a>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-card-custom border-top border-secondary py-4 text-center text-muted small">
        <div class="container">
            <p class="mb-1 font-cinzel text-gold fw-bold">PORTAL DE GESTÃO INTEGRADO</p>
            <p class="mb-0">&copy; <?= date('Y') ?> - Todos os direitos reservados.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
