<?php
// Identifica a página atual de forma limpa para marcar o menu ativo
$paginaAtual = basename($_SERVER['PHP_SELF'], ".php");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospitalaria</title>
    <link rel="icon" href="../configuracoes/icone.svg" type="image/svg+xml">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Fonte Cinzel (Títulos) e Inter (Textos) -->
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- Chart.js para o gráfico -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            background-color: #141724;
            color: #e2e8f0;
            font-family: 'Inter', sans-serif;
            margin: 0;
            overflow-x: hidden;
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */
        .sidebar {
            width: 260px;
            background-color: #1d2132;
            border-right: 1px solid #333951;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1100;
            transition: left 0.3s ease;
        }
        
        .sidebar-brand {
            padding: 25px 20px;
            font-family: 'Cinzel', serif;
            color: #f5c041;
            font-size: 1.4rem;
            text-align: center;
            border-bottom: 1px solid #333951;
        }

        .sidebar-section {
            padding: 20px 20px 5px;
            font-size: 0.75rem;
            color: #8b92a5;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-nav li a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #e2e8f0;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 0.95rem;
        }

        .sidebar-nav li a i {
            width: 25px;
            color: #8b92a5;
            transition: color 0.2s;
        }

        .sidebar-nav li a:hover, .sidebar-nav li a.active {
            background-color: rgba(245, 192, 65, 0.05);
            color: #f5c041;
        }

        .sidebar-nav li a:hover i, .sidebar-nav li a.active i {
            color: #f5c041;
        }

        /* BARRA SUPERIOR MOBILE (Oculta no Desktop) */
        .mobile-topbar {
            display: none;
            background-color: #1d2132;
            border-bottom: 1px solid #333951;
            padding: 12px 20px;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1050;
            justify-content: space-between;
            align-items: center;
        }

        /* BACKDROP PARA MENU MOBILE */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.5);
            z-index: 1090;
        }

        /* AJUSTES RESPONSIVOS */
        @media (max-width: 991.98px) {
            .mobile-topbar {
                display: flex !important;
            }
            .sidebar {
                left: -260px;
            }
            .sidebar.show {
                left: 0;
            }
            .sidebar-backdrop.show {
                display: block !important;
            }
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 20px 15px !important;
                margin-top: 60px !important;
            }
        }
        
        /* Para navegadores modernos (Chrome, Firefox, Edge, Safari) */
        input::placeholder {
        color: #a0aec0 !important; /* Um cinza claro bem visível */
        opacity: 0.5 !important;     /* Remove a transparência padrão do navegador */
        }

        /* Para garantir compatibilidade com versões antigas do Firefox */
        input:-moz-placeholder {
        color: #a0aec0 !important;
        opacity: 1 !important;
        }

        /* Para garantir compatibilidade com versões antigas do Internet Explorer/Edge */
        input::-ms-input-placeholder {
        color: #a0aec0 !important;
        }
    </style>
</head>
<body>

    <!-- BARRA SUPERIOR MOBILE -->
    <div class="mobile-topbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-outline-warning btn-sm me-3" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </button>
            <span style="font-family: 'Cinzel', serif; color: #f5c041; font-weight: bold;">HOSPITALARIA</span>
        </div>
        <span class="text-white small">Painel</span>
    </div>

    <!-- BACKDROP MOBILE -->
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileMenu()"></div>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-heartbeat d-block mb-2 text-center" style="font-size: 1.8rem;"></i>
            Gestão de<br>Hospitalaria
        </div>

        <div class="sidebar-section">Menu Principal</div>
        <ul class="sidebar-nav">
            <li><a href="dashboard" class="<?= ($paginaAtual == 'dashboard') ? 'active' : '' ?>"><i class="fas fa-chart-pie"></i> Dashboard</a></li>
        </ul>

        <div class="sidebar-section">Ações</div>
        <ul class="sidebar-nav">
            <li><a href="acompanhamentos" class="<?= ($paginaAtual == 'acompanhamentos') ? 'active' : '' ?>"><i class="fas fa-user-injured me-2"></i> Acompanhamentos</a></li>
            <li><a href="beneficencia" class="<?= ($paginaAtual == 'beneficencia') ? 'active' : '' ?>"><i class="fas fa-hand-holding-usd me-2"></i> Beneficência</a></li>
        </ul>

        <div class="sidebar-section">Informações</div>
        <ul class="sidebar-nav">
            <li><a href="relatorios" class="<?= ($paginaAtual == 'relatorios') ? 'active' : '' ?>"><i class="fas fa-book me-2"></i> Relatórios</a></li>
            <li>
                <a href="#" onclick="confirmarLogout(event)" class="nav-link">
                   <i class="fas fa-sign-out-alt me-2 text-danger"></i> Sair do Sistema
                </a>
            </li>
        </ul>
    </aside>

<script>
    function toggleMobileMenu() {
        const sidebar = document.querySelector('.sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (sidebar) sidebar.classList.toggle('show');
        if (backdrop) backdrop.classList.toggle('show');
    }

    function confirmarLogout(e) {
        e.preventDefault(); 

        Swal.fire({
            title: 'Sair do Sistema?',
            text: "Você terá que fazer login novamente.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, sair!',
            cancelButtonText: 'Cancelar',
            background: '#1e293b',
            color: '#fff'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '../configuracoes/logout';
            }
        })
    }
</script>    
</body>
</html>