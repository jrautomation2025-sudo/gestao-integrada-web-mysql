<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gestão Integrada</title>
    <link rel="icon" href="../configuracoes/icone.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    
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
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', sans-serif;
        }
        .login-card {
            background-color: var(--bg-card);
            border: 1px solid #334155;
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        }
        .brand-title {
            color: var(--gold);
            font-weight: bold;
            text-align: center;
            margin-bottom: 30px;
            font-size: 1.8rem;
        }
        .form-control {
            background-color: #0f172a;
            border: 1px solid #334155;
            color: #fff;
        }
        .form-control:focus {
            background-color: #0f172a;
            border-color: var(--gold);
            color: #fff;
            box-shadow: 0 0 0 0.25rem rgba(207, 163, 78, 0.25);
        }
        .btn-gold {
            background-color: var(--gold);
            color: #000;
            font-weight: bold;
            width: 100%;
            padding: 10px;
            border: none;
            transition: all 0.3s;
        }
        .btn-gold:hover {
            background-color: #b8860b;
            color: #fff;
            transform: scale(1.02);
        }
        .alert-custom {
            display: none;
            margin-top: 15px;
            font-size: 0.9rem;
        }
        input::placeholder {
            color: #a0aec0 !important;
            opacity: 0.5 !important;
        }
        .btn-voltar {
            position: absolute;
            top: 20px;
            left: 20px;
            text-decoration: none;
            color: #94a3b8;
            font-weight: 500;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            background: rgba(15, 23, 42, 0.8);
            z-index: 1000;
        }
        .btn-voltar:hover {
            color: var(--gold);
            border-color: var(--gold);
            transform: translateX(-5px);
        }
    </style>
</head>
<body>
    
    <a href="/" class="btn-voltar">
        <i class="fas fa-arrow-left me-2"></i> Voltar ao Site
    </a>

    <div class="login-card">
        <div class="brand-title">
            <i class="fas fa-coins me-2"></i>Tesouraria
        </div>

        <form id="formLogin">
            <input type="hidden" name="perfil" value="tesoureiro"/>
            <div class="mb-3">
                <label class="form-label">E-mail</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-light"><i class="fas fa-envelope"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="seu@email.com" required>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Senha</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-light"><i class="fas fa-lock"></i></span>
                    <input type="password" name="senha" class="form-control" placeholder="******" required>
                </div>
            </div>
            
            <div class="mb-4" style="display: flex; justify-content: center;">
                 <div class="cf-turnstile" data-sitekey="0x4AAAAAAFElGAIaSPQzf7Qo" data-theme="dark"></div>
            </div>

            <button type="submit" class="btn btn-gold mb-3" id="btnEntrar">
                ENTRAR <i class="fas fa-arrow-right ms-2"></i>
            </button>
            
            <div id="msgErro" class="alert alert-danger alert-custom text-center" role="alert"></div>
        </form>
    </div>

    <script>
    const N8N_TURNSTILE_WEBHOOK = 'https://n8n-prod.jrtec.com.br/webhook/cloudflare-login';
    const CHAVE_SECRETA_N8N = 'ymXsxhOMqWwbUfQwmUStiCqbf4KxN72KitFWq4CmhgH02up0uNapH3EumwjC0qMM';
    
    // CORREÇÃO: Adicionado 'async' na função do evento
    document.getElementById('formLogin').addEventListener('submit', async function(e) {
        e.preventDefault();
            
        const btn = document.querySelector('button[type="submit"]');
        const msgErro = document.getElementById('msgErro');
        const formData = new FormData(this);

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Entrando...';
        msgErro.style.display = 'none';

        // --- PASSO 1: COLETAR E VALIDAR O TOKEN DO TURNSTILE LOCALMENTE ---
        const turnstileResponse = document.querySelector('[name="cf-turnstile-response"]')?.value;

        if (!turnstileResponse) {
            msgErro.innerText = 'Por favor, complete a validação de segurança (Captcha).';
            msgErro.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = 'ENTRAR <i class="fas fa-arrow-right ms-2"></i>';
            return;
        }

        // --- PASSO 2: INVOCAR O WEBHOOK DO N8N PARA VERIFICAÇÃO DE SEGURANÇA ---
        try {
            const n8nCheck = await fetch(N8N_TURNSTILE_WEBHOOK, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Form-Token': CHAVE_SECRETA_N8N
                },
                body: JSON.stringify({ 'cf-turnstile-token': turnstileResponse })
            });

            if (!n8nCheck.ok) {
                throw new Error('Falha na validação de segurança. Acesso recusado.');
            }
            
            formData.append('captcha_validado', 'true');

        } catch (error) {
            msgErro.innerText = error.message || 'Erro ao validar o sistema de segurança. Tente novamente.';
            msgErro.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = 'ENTRAR <i class="fas fa-arrow-right ms-2"></i>';
            if (typeof turnstile !== 'undefined') turnstile.reset();
            return;
        }

        // --- PASSO 3: CONTINUAR COM O FLUXO DE LOGIN ---
        try {
            const response = await fetch('../configuracoes/auth?action=login', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.status === 'success') {
                window.location.href = './inicio';
            } 
            else if (data.status === '2fa_required') {
                btn.innerHTML = 'ENTRAR <i class="fas fa-arrow-right ms-2"></i>';
                btn.disabled = false;
                
                Swal.fire({
                    title: 'Autenticação 2FA',
                    text: 'Digite o código do seu app autenticador:',
                    input: 'text',
                    inputAttributes: {
                        autocapitalize: 'off',
                        maxlength: 6,
                        style: 'text-align: center; letter-spacing: 5px; font-size: 1.5rem;'
                    },
                    showCancelButton: true,
                    confirmButtonText: 'Validar',
                    confirmButtonColor: '#cfa34e',
                    background: '#1e293b',
                    color: '#fff',
                    showLoaderOnConfirm: true,
                    preConfirm: async (codigo) => {
                        let fd = new FormData();
                        fd.append('codigo', codigo);
                        try {
                            const res = await fetch('../configuracoes/auth?action=verify_2fa', { method: 'POST', body: fd });
                            const resp = await res.json();
                            if (resp.status !== 'success') {
                                throw new Error(resp.message);
                            }
                            return resp;
                        } catch (error) {
                            Swal.showValidationMessage(`Erro: ${error}`);
                        }
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = './inicio';
                    }
                });
            }
            else if (data.status === '2fa_whatsapp') {
                btn.innerHTML = 'ENTRAR <i class="fas fa-arrow-right ms-2"></i>';
                btn.disabled = false;
                
                Swal.fire({
                    title: 'Verificação de Segurança',
                    text: 'Digite o código de 6 dígitos que enviamos no seu WhatsApp:',
                    input: 'text',
                    inputAttributes: {
                        autocapitalize: 'off',
                        maxlength: 6,
                        style: 'text-align: center; letter-spacing: 5px; font-size: 1.5rem;'
                    },
                    showCancelButton: true,
                    confirmButtonText: 'Validar Código',
                    confirmButtonColor: '#25D366',
                    background: '#1e293b',
                    color: '#fff',
                    showLoaderOnConfirm: true,
                    preConfirm: async (codigo) => {
                        let fd = new FormData();
                        fd.append('codigo', codigo);
                        try {
                            const res = await fetch('../configuracoes/auth?action=verify_2fa_whatsapp', { method: 'POST', body: fd });
                            const resp = await res.json();
                            if (resp.status !== 'success' && resp.status !== 'need_password_change') { 
                                throw new Error(resp.message); 
                            }
                            return resp;
                        } catch (error) { 
                            Swal.showValidationMessage(`Erro: ${error}`); 
                        }
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        const respostaServidor = result.value;

                        if (respostaServidor && respostaServidor.status === 'need_password_change') {
                            Swal.fire({
                                title: 'Primeiro Acesso',
                                html: 'Defina uma <b>nova senha definitiva</b>.<br><small style="color: #94a3b8;">Mínimo 8 caracteres, com letra maiúscula, minúscula, número e caractere especial.</small>',
                                input: 'password',
                                inputAttributes: {
                                    placeholder: 'Sua nova senha forte',
                                    autocapitalize: 'off',
                                    style: 'text-align: center;'
                                },
                                showCancelButton: false,
                                confirmButtonText: 'Salvar Senha e Entrar',
                                confirmButtonColor: '#cfa34e',
                                background: '#1e293b',
                                color: '#fff',
                                showLoaderOnConfirm: true,
                                preConfirm: async (novaSenha) => {
                                    const minLength = novaSenha.length >= 8;
                                    const hasUpper = /[A-Z]/.test(novaSenha);
                                    const hasLower = /[a-z]/.test(novaSenha);
                                    const hasNumber = /[0-9]/.test(novaSenha);
                                    const hasSpecial = /[^a-zA-Z0-9]/.test(novaSenha);

                                    if (!minLength || !hasUpper || !hasLower || !hasNumber || !hasSpecial) {
                                        Swal.showValidationMessage('A senha precisa ter no mínimo 8 caracteres, 1 maiúscula, 1 minúscula, 1 número e 1 caractere especial.');
                                        return false;
                                    }

                                    let fdPass = new FormData();
                                    fdPass.append('nova_senha', novaSenha);
                                    try {
                                        const res = await fetch('../configuracoes/auth?action=update_first_password', { method: 'POST', body: fdPass });
                                        const resp = await res.json();
                                        if (resp.status !== 'success') { throw new Error(resp.message); }
                                        return resp;
                                    } catch (error) { 
                                        Swal.showValidationMessage(`Erro: ${error}`); 
                                    }
                                },
                                allowOutsideClick: false
                            }).then((resFinal) => {
                                if (resFinal.isConfirmed) {
                                    Swal.fire({
                                        title: 'Tudo pronto!',
                                        text: 'Sua senha foi alterada com sucesso.',
                                        icon: 'success',
                                        timer: 1500,
                                        showConfirmButton: false,
                                        background: '#1e293b',
                                        color: '#fff'
                                    }).then(() => {
                                        window.location.href = './inicio';
                                    });
                                }
                            });
                        } else {
                            window.location.href = './inicio';
                        }
                    }
                });
            } else {
                msgErro.innerText = data.message;
                msgErro.style.display = 'block';
                btn.innerHTML = 'ENTRAR <i class="fas fa-arrow-right ms-2"></i>';
                btn.disabled = false;
            }
        } catch (err) {
            msgErro.innerText = 'Erro de conexão ao realizar o login.';
            msgErro.style.display = 'block';
            btn.innerHTML = 'ENTRAR <i class="fas fa-arrow-right ms-2"></i>';
            btn.disabled = false;
        }
    });
    </script>
</body>
</html>
