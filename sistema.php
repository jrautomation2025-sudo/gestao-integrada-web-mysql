<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Maçônico - Gestão Integrada</title>
    <link rel="icon" href="./configuracoes/icone.svg" type="image/svg+xml">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Fonte Clássica para Títulos -->
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root { 
            --bg-dark: #0f172a; 
            --bg-card: #1e293b; 
            --gold: #cfa34e; 
            --text-light: #e2e8f0; 
        }
        
        body { 
            background-color: var(--bg-dark); 
            color: var(--text-light); 
            font-family: 'Segoe UI', sans-serif; 
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Tipografia Maçônica */
        .font-classic {
            font-family: 'Cinzel', serif;
        }

        /* Barra de Navegação */
        .navbar-custom {
            background-color: rgba(15, 23, 42, 0.95);
            border-bottom: 1px solid rgba(207, 163, 78, 0.3);
            padding: 15px 0;
            backdrop-filter: blur(10px);
        }
        .navbar-brand {
            color: var(--gold) !important;
            font-size: 1.5rem;
            font-weight: 700;
        }
        .nav-link {
            color: var(--text-light) !important;
            font-weight: 500;
            transition: color 0.3s;
        }
        .nav-link:hover, .nav-link:focus {
            color: var(--gold) !important;
        }

        /* Customização do Menu Suspenso (Dropdown) */
        .dropdown-menu {
            background-color: var(--bg-card);
            border: 1px solid #334155;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            border-radius: 8px;
            margin-top: 10px;
        }
        .dropdown-item {
            color: var(--text-light);
            padding: 10px 20px;
            transition: all 0.2s;
        }
        .dropdown-item i {
            width: 25px;
            color: var(--gold);
        }
        .dropdown-item:hover {
            background-color: rgba(207, 163, 78, 0.1);
            color: var(--gold);
        }

        /* Área Principal (Hero) */
        .hero-section {
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            background: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%);
        }
        .hero-content {
            text-align: center;
            max-width: 800px;
        }
        .hero-subtitle {
            color: #94a3b8;
            font-size: 1.1rem;
            margin-bottom: 40px;
        }

        /* Cards de Atalho Rápido */
        .module-card {
            background-color: var(--bg-card);
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 30px 20px;
            text-align: center;
            text-decoration: none;
            color: var(--text-light);
            transition: all 0.3s ease;
            display: block;
            height: 100%;
        }
        .module-card:hover {
            transform: translateY(-10px);
            border-color: var(--gold);
            box-shadow: 0 10px 20px rgba(207, 163, 78, 0.15);
            color: white;
        }
        .module-icon {
            font-size: 2.5rem;
            color: var(--gold);
            margin-bottom: 15px;
        }
        .module-title {
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        footer {
            text-align: center;
            padding: 20px;
            border-top: 1px solid #334155;
            color: #64748b;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <!-- BARRA DE NAVEGAÇÃO -->
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container">
            <a class="navbar-brand font-classic" href="#">
                <i class="fas fa-compass me-2"></i>Portal Maçônico
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="fas fa-bars text-light"></i>
            </button>
            
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="/"><i class="fas fa-home me-1"></i> Início</a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="cadastro"><i class="fas fa-user-plus me-1"></i> Criar Conta</a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="/"><i class="fa-solid fa-money-bill-1-wave me-1"></i> Planos e Preços</a>
                    </li>
                    
                    <!-- MENU SUSPENSO (DROPDOWN) -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="modulosDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-th-large me-1"></i> Módulos
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="modulosDropdown">
                            <li>
                                <a class="dropdown-item" href="/chancelaria/login">
                                    <i class="fas fa-book-open"></i> Chancelaria
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="/tesouraria/login">
                                    <i class="fas fa-coins"></i> Tesouraria
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="/secretaria/login">
                                    <i class="fas fa-feather-alt"></i> Secretaria
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="/hospitalaria/login">
                                    <i class="fas fa-hand-holding-heart"></i> Hospitalaria
                                </a>
                            </li>
                            <li><hr class="dropdown-divider" style="border-color: #334155;"></li>
                            <li>
                                <a class="dropdown-item" href="/administracao/login">
                                    <i class="fas fa-user-tie"></i> Administrador
                                </a>
                                <a class="dropdown-item" href="suporte">
                                    <i class="fas fa-life-ring"></i> Suporte
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ÁREA PRINCIPAL -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content mx-auto">
                <h1 class="font-classic mb-3" style="color: var(--gold); font-size: 3rem;">Gestão Maçônica Integrada</h1>
                <p class="hero-subtitle">
                    Selecione o módulo abaixo para acessar sua área de trabalho. Acesso restrito a membros autorizados.
                </p>
                
                <!-- GRID DE ATALHOS RÁPIDOS -->
                <div class="row g-4 justify-content-center mt-2">
                    
                    <div class="col-md-4 col-sm-6">
                        <a href="/chancelaria/login" class="module-card">
                            <i class="fas fa-book-open module-icon"></i>
                            <div class="module-title font-classic">Chancelaria</div>
                            <small class="text-secondary">Gestão de obreiros, frequência e emissão de pranchas.</small>
                        </a>
                    </div>

                    <div class="col-md-4 col-sm-6">
                        <a href="/tesouraria/login" class="module-card">
                            <i class="fas fa-coins module-icon"></i>
                            <div class="module-title font-classic">Tesouraria</div>
                            <small class="text-secondary">Controle de mensalidades, troncos e fluxo de caixa.</small>
                        </a>
                    </div>

                    <div class="col-md-4 col-sm-6">
                        <a href="/secretaria/login" class="module-card">
                            <i class="fas fa-feather-alt module-icon"></i>
                            <div class="module-title font-classic">Secretaria</div>
                            <small class="text-secondary">Atas, correspondências, editais e documentos gerais.</small>
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <!-- RODAPÉ -->
    <footer class="font-classic">
        &copy; 2026 Portal Maçônico. Todos os direitos reservados.
    </footer>

    <!-- Bootstrap JS (Necessário para o Menu Suspenso funcionar) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
