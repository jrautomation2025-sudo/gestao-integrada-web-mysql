<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - Gestão Integrada</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Fonte Clássica -->
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 (Para alertas bonitos) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { 
            --bg-dark: #0f172a; 
            --bg-card: #1e293b; 
            --gold: #cfa34e; 
            --text-light: #e2e8f0; 
            --input-bg: #334155;
        }
        
        body { 
            background-color: var(--bg-dark); 
            color: var(--text-light); 
            font-family: 'Segoe UI', sans-serif; 
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .font-classic { font-family: 'Cinzel', serif; }

        .auth-card {
            background-color: var(--bg-card);
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 40px;
            width: 100%;
            max-width: 800px; /* Mais largo para acomodar duas colunas */
            box-shadow: 0 15px 35px rgba(0,0,0,0.5);
        }

        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .auth-header i {
            font-size: 2.5rem;
            color: var(--gold);
            margin-bottom: 15px;
        }

        .form-control, .form-select {
            background-color: var(--input-bg);
            border: 1px solid #475569;
            color: var(--text-light);
            padding: 12px;
        }

        .form-control:focus, .form-select:focus {
            background-color: var(--input-bg);
            border-color: var(--gold);
            color: white;
            box-shadow: 0 0 0 0.25rem rgba(207, 163, 78, 0.25);
        }

        /* Corrige cor do ícone de calendário no input type date para modo escuro */
        ::-webkit-calendar-picker-indicator {
            filter: invert(1);
            opacity: 0.6;
        }

        .form-label {
            color: #94a3b8;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .btn-gold {
            background-color: var(--gold);
            color: #000;
            font-weight: 600;
            padding: 12px;
            border: none;
            transition: all 0.3s;
        }

        .btn-gold:hover {
            background-color: #b58b3d;
            transform: translateY(-2px);
        }

        .section-title {
            color: var(--gold);
            font-size: 1.1rem;
            border-bottom: 1px solid #334155;
            padding-bottom: 5px;
            margin-bottom: 20px;
            margin-top: 10px;
        }
    </style>
</head>
<body>

    <div class="auth-card">
        <div class="auth-header">
            <i class="fas fa-book-open"></i>
            <h2 class="font-classic text-uppercase" style="color: var(--gold);">Chancelaria</h2>
            <p class="text-secondary">Cadastro de Obreiro</p>
        </div>

        <form id="formCadastroMembro">
            
            <h5 class="section-title font-classic"><i class="fas fa-user me-2"></i>Dados Pessoais</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nome Completo</label>
                    <input type="text" class="form-control" name="nome" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">E-mail</label>
                    <input type="email" class="form-control" name="email" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">WhatsApp (com DDD)</label>
                    <input type="text" class="form-control" name="telefone" placeholder="Ex: 11999999999" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Data de Nascimento</label>
                    <input type="date" class="form-control" name="data_nascimento" required>
                </div>
            </div>

            <h5 class="section-title font-classic mt-4"><i class="fas fa-landmark me-2"></i>Dados Maçônicos</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">CIM (Cadastro)</label>
                    <input type="text" class="form-control" name="cim" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Grau</label>
                    <select class="form-select" name="grau" required>
                        <option value="Aprendiz">Aprendiz</option>
                        <option value="Companheiro">Companheiro</option>
                        <option value="Mestre">Mestre</option>
                        <option value="Mestre Instalado">Mestre Instalado</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Data de Iniciação</label>
                    <input type="date" class="form-control" name="data_iniciacao">
                </div>
            </div>

            <h5 class="section-title font-classic mt-4"><i class="fas fa-lock me-2"></i>Segurança do Acesso</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Senha</label>
                    <input type="password" class="form-control" name="senha" id="senha" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Confirmar Senha</label>
                    <input type="password" class="form-control" id="confirmar_senha" required>
                </div>
            </div>

            <div class="mt-5">
                <button type="submit" class="btn btn-gold w-100">Registrar Obreiro</button>
            </div>
            
            <div class="text-center mt-3">
                <a href="login.html" class="text-secondary text-decoration-none hover-gold">
                    <i class="fas fa-arrow-left me-1"></i> Voltar para o Login
                </a>
            </div>
        </form>
    </div>

<script>
document.getElementById('formCadastroMembro').addEventListener('submit', async function(e) {
    e.preventDefault(); // Evita recarregar a página
    
    const senha = document.getElementById('senha').value;
    const confirmarSenha = document.getElementById('confirmar_senha').value;

    if(senha !== confirmarSenha) {
        Swal.fire({
            icon: 'error',
            title: 'Ops!',
            text: 'As senhas não conferem.',
            background: '#1e293b', color: '#e2e8f0', confirmButtonColor: '#cfa34e'
        });
        return;
    }

    // Coleta todos os dados do formulário
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());
    
    // Adiciona a ação que o PHP espera
    data.acao = 'registrar';

    try {
        // Mostra loading no botão
        const btnSubmit = this.querySelector('button[type="submit"]');
        const textoOriginal = btnSubmit.innerHTML;
        btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
        btnSubmit.disabled = true;

        // Envia para o PHP
        const response = await fetch('auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        // Restaura o botão
        btnSubmit.innerHTML = textoOriginal;
        btnSubmit.disabled = false;

        if (result.sucesso) {
            Swal.fire({
                icon: 'success',
                title: 'TFA!',
                text: result.mensagem,
                background: '#1e293b', color: '#e2e8f0', confirmButtonColor: '#cfa34e'
            }).then(() => {
                // Limpa o formulário ou redireciona
                document.getElementById('formCadastroMembro').reset();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: result.mensagem,
                background: '#1e293b', color: '#e2e8f0', confirmButtonColor: '#cfa34e'
            });
        }

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Erro de Conexão',
            text: 'Não foi possível se comunicar com o servidor.',
            background: '#1e293b', color: '#e2e8f0', confirmButtonColor: '#cfa34e'
        });
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