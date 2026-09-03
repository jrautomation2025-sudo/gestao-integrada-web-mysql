<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - Gestão Financeira</title>
    <link rel="icon" href="./configuracoes/icone.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { --bg-dark: #0f172a; --bg-card: #1e293b; --gold: #cfa34e; --text-light: #e2e8f0; }
        body { background-color: var(--bg-dark); color: var(--text-light); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', sans-serif; padding: 20px; }
        .login-card { background-color: var(--bg-card); border: 1px solid #334155; border-radius: 15px; padding: 40px; width: 100%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        .brand-title { color: var(--gold); font-weight: bold; text-align: center; margin-bottom: 30px; font-size: 1.8rem; }
        .form-control, .form-select { background-color: #0f172a; border: 1px solid #334155; color: #fff; }
        .form-control:focus, .form-select:focus { background-color: #0f172a; border-color: var(--gold); color: #fff; box-shadow: 0 0 0 0.25rem rgba(207, 163, 78, 0.25); }
        .btn-gold { background-color: var(--gold); color: #000; font-weight: bold; width: 100%; padding: 10px; border: none; transition: all 0.3s; }
        .btn-gold:hover { background-color: #b8860b; color: #fff; transform: scale(1.02); }
        .btn-gold:disabled { background-color: #555; cursor: not-allowed; transform: none; color: #ccc; }
        .btn-voltar { position: absolute; top: 20px; left: 20px; text-decoration: none; color: #94a3b8; font-weight: 500; padding: 10px 15px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.1); transition: all 0.3s ease; background: rgba(15, 23, 42, 0.8); z-index: 1000; }
        .btn-voltar:hover { color: var(--gold); border-color: var(--gold); transform: translateX(-5px); }
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
    
    <a href="/" class="btn-voltar">
        <i class="fas fa-arrow-left me-2"></i> Voltar ao Site
    </a>

    <div class="login-card">
        <div class="brand-title">
            <i class="fas fa-key me-2"></i>Faça seu Cadastro
            <p style="font-size: 0.8rem; color: #64748b;">Requer autorização do Administrador</p>
        </div>

        <form id="formCadastro">
            
            <div class="mb-3">
                <label class="form-label">Nome Completo *</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-light"><i class="fas fa-user"></i></span>
                    <input type="text" name="nome" class="form-control" required placeholder="Seu nome ou da Loja">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">E-mail *</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-light"><i class="fas fa-envelope"></i></span>
                    <input type="email" name="email" id="inputEmail" class="form-control" required oninput="validarFormulario()" placeholder="seu@email.com">
                </div>
                <small id="msgEmail" class="d-block mt-1" style="font-size: 0.8rem; display:none;"></small>
            </div>

            <div class="mb-3">
                <label class="form-label">WhatsApp *</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-light"><i class="fab fa-whatsapp"></i></span>
                    <input type="tel" name="telefone" id="inputTelefone" class="form-control" placeholder="(11) 99999-9999" maxlength="15" required oninput="mascaraTelefone(event)">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Senha *</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-light"><i class="fas fa-lock"></i></span>
                    <input type="password" name="senha" id="inputSenha" class="form-control" required minlength="8" placeholder="Mínimo 8 caracteres" oninput="validarFormulario()">
                    <button class="btn btn-outline-secondary border-secondary" type="button" onclick="mostrarSenha()">
                        <i class="fas fa-eye text-light"></i>
                    </button>
                </div>
                <small id="msgSenha" class="d-block mt-2" style="font-size: 0.8rem; color: #64748b;">
                    <i class="fas fa-info-circle me-1"></i> Requisitos: Letra, Número e Símbolo.
                </small>
            </div>
            
            <?php $planoUrl = $_GET['plano'] ?? 'free'; ?>
            
            <div class="mb-4">
                <label class="form-label">Plano Selecionado</label>
                <select name="plano_selecionado" class="form-select">
                    <option value="free" <?php echo ($planoUrl == 'free') ? 'selected' : ''; ?>>Plano Gratuito 6 Meses</option>
                    <option value="pro_mensal" <?php echo ($planoUrl == 'mensal') ? 'selected' : ''; ?>>Plano Mensal</option>
                    <option value="pro_anual" <?php echo ($planoUrl == 'anual') ? 'selected' : ''; ?>>Plano Anual</option>
                </select>
            </div>

            <button type="submit" class="btn btn-gold mb-3" id="btnCadastrar" disabled>
                CRIAR CONTA <i class="fas fa-check ms-2"></i>
            </button>
            
            <div id="msgErro" class="alert alert-danger text-center" style="display:none;"></div>

            <div class="text-center mt-3">
                <span style="color: #cbd5e1; font-size: 0.9rem;">Já tem conta?</span>
                <a href="sistema" class="text-decoration-none fw-bold" style="color: var(--gold);">Acessar Sistema</a>
            </div>
            
        </form>
    </div>

    <script>
        function mostrarSenha() {
            const input = document.getElementById('inputSenha');
            input.type = input.type === 'password' ? 'text' : 'password';
        }

        function validarFormulario() {
            const btn = document.getElementById('btnCadastrar');
            const emailInput = document.getElementById('inputEmail');
            const msgEmail = document.getElementById('msgEmail');
            const email = emailInput.value.trim();
            const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            let emailValido = false;

            if (email.length > 0) {
                if (regexEmail.test(email)) {
                    msgEmail.style.display = 'none';
                    emailInput.classList.remove('border-danger');
                    emailInput.classList.add('border-success');
                    emailValido = true;
                } else {
                    msgEmail.style.display = 'block';
                    msgEmail.innerHTML = 'E-mail inválido (ex: nome@site.com)';
                    msgEmail.style.color = '#ef4444';
                    emailInput.classList.add('border-danger');
                    emailInput.classList.remove('border-success');
                    emailValido = false;
                }
            } else {
                msgEmail.style.display = 'none';
                emailInput.classList.remove('border-danger', 'border-success');
            }

            const senhaInput = document.getElementById('inputSenha');
            const msgSenha = document.getElementById('msgSenha');
            const senha = senhaInput.value;
            const regexSenha = /^(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[\W_]).{8,}$/;
            let senhaValida = false;

            if (senha.length > 0) {
                if (regexSenha.test(senha)) {
                    msgSenha.innerHTML = '<i class="fas fa-check-circle me-1"></i> Senha Segura!';
                    msgSenha.style.color = '#22c55e';
                    senhaInput.classList.remove('border-danger');
                    senhaInput.classList.add('border-success');
                    senhaValida = true;
                } else {
                    msgSenha.innerHTML = '<i class="fas fa-times-circle me-1"></i> Fraca: Use 8 chars, letra, número e símbolo.';
                    msgSenha.style.color = '#ef4444';
                    senhaInput.classList.add('border-danger');
                    senhaInput.classList.remove('border-success');
                    senhaValida = false;
                }
            } else {
                msgSenha.innerHTML = '<i class="fas fa-info-circle me-1"></i> Requisitos: Letra, Número e Símbolo.';
                msgSenha.style.color = '#64748b';
                senhaInput.classList.remove('border-danger', 'border-success');
            }

            if (emailValido && senhaValida) {
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
            } else {
                btn.disabled = true;
                btn.style.opacity = '0.6';
                btn.style.cursor = 'not-allowed';
            }
        }

        document.getElementById('formCadastro').addEventListener('submit', function(e) {
            e.preventDefault();

            const btn = document.getElementById('btnCadastrar');
            const msgErro = document.getElementById('msgErro');
            const formData = new FormData(this);
            
            // Remove a máscara do telefone
            let telefoneLimpo = formData.get('telefone').replace(/\D/g, '');
            formData.set('telefone', telefoneLimpo);

            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Criando...';
            btn.disabled = true;
            msgErro.style.display = 'none';

            fetch('configuracoes/auth?action=register', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Como o PHP cuidou do webhook, nós só mostramos a mensagem de sucesso!
                    Swal.fire({
                        title: 'Sucesso!',
                        text: 'Conta criada com sucesso. Faça login.',
                        icon: 'success',
                        background: '#1e293b',
                        color: '#fff',
                        confirmButtonColor: '#cfa34e'
                    }).then(() => {
                        window.location.href = 'index';
                    });
                } else {
                    msgErro.innerText = data.message || "Erro ao cadastrar";
                    msgErro.style.display = 'block';
                    btn.innerHTML = 'CRIAR CONTA <i class="fas fa-check ms-2"></i>';
                    validarFormulario(); 
                }
            })
            .catch(error => {
                msgErro.innerText = "Erro de conexão com o servidor.";
                msgErro.style.display = 'block';
                btn.innerHTML = 'CRIAR CONTA <i class="fas fa-check ms-2"></i>';
                btn.disabled = false;
            });
        });
        
        function mascaraTelefone(event) {
            let input = event.target;
            let valor = input.value.replace(/\D/g, ""); 
            valor = valor.substring(0, 11);

            if (valor.length === 0) {
                input.value = "";
                return;
            }

            if (valor.length <= 10) {
                valor = valor.replace(/^(\d{2})(\d{4})(\d{0,4})/, "($1) $2-$3");
            } else {
                valor = valor.replace(/^(\d{2})(\d{5})(\d{0,4})/, "($1) $2-$3");
            }
    
            input.value = valor;
        }
    </script>
</body>
</html>
