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
            display: none; /* Escondido por padrão */
            margin-top: 15px;
            font-size: 0.9rem;
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

    <a href="/" class="btn-voltar">
        <i class="fas fa-arrow-left me-2"></i> Voltar ao Site
    </a>

    <style>
        /* Estilo do Botão Voltar */
        .btn-voltar {
            position: absolute;
            top: 20px;
            left: 20px;
            text-decoration: none;
            color: #94a3b8; /* Cinza suave */
            font-weight: 500;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            background: rgba(15, 23, 42, 0.8); /* Fundo escuro transparente */
            z-index: 1000; /* Garante que fique por cima de tudo */
        }
        .btn-voltar:hover {
            color: var(--gold); /* Dourado ao passar o mouse */
            border-color: var(--gold);
            transform: translateX(-5px); /* Efeito de mover para a esquerda */
        }
    </style>

    <div class="login-card">
        <div class="brand-title">
            <i class="fas fa-book-open me-2"></i>Chancelaria
        </div>

        <form id="formLogin">
            <input type="hidden" name="perfil" value="chanceler"/>
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

            <button type="submit" class="btn btn-gold mb-3" id="btnEntrar">
                ENTRAR <i class="fas fa-arrow-right ms-2"></i>
            </button>
            
            <div id="msgErro" class="alert alert-danger alert-custom text-center" role="alert"></div>

        </form>
    </div>

    <script>
    document.getElementById('formLogin').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.querySelector('button[type="submit"]');
    const msgErro = document.getElementById('msgErro');
    const formData = new FormData(this);

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Entrando...';
    msgErro.style.display = 'none';

    fetch('../configuracoes/auth?action=login', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            window.location.href = './dashboard';
        } 
        else if (data.status === '2fa_required') {
            // --- AQUI A MÁGICA: PEDE O CÓDIGO ---
            btn.innerHTML = 'ENTRAR';
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
                preConfirm: (codigo) => {
                    // Envia para validar o código
                    let fd = new FormData();
                    fd.append('codigo', codigo);
                    return fetch('../configuracoes/auth?action=verify_2fa', { method: 'POST', body: fd })
                        .then(response => response.json())
                        .then(resp => {
                            if (resp.status !== 'success') {
                                throw new Error(resp.message);
                            }
                            return resp;
                        })
                        .catch(error => {
                            Swal.showValidationMessage(`Erro: ${error}`);
                        });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = './dashboard'; // Sucesso no 2FA
                }
            });
        }
        // --- CÓDIGO NOVO: WHATSAPP 2FA ---
        else if (data.status === '2fa_whatsapp') {
            btn.innerHTML = 'ENTRAR';
            btn.disabled = false;
            
            // --- MODAL 1: CÓDIGO DO WHATSAPP ---
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
                preConfirm: (codigo) => {
                    let fd = new FormData();
                    fd.append('codigo', codigo);
                    return fetch('../configuracoes/auth?action=verify_2fa_whatsapp', { method: 'POST', body: fd })
                        .then(response => response.json())
                        .then(resp => {
                            if (resp.status !== 'success' && resp.status !== 'need_password_change') { 
                                throw new Error(resp.message); 
                            }
                            return resp; // Retorna o objeto de resposta para sabermos se precisa do 2º modal
                        })
                        .catch(error => { Swal.showValidationMessage(`Erro: ${error}`); });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    const respostaServidor = result.value;

                    // Se o servidor pediu a troca de senha (first_access == 1)
                    if (respostaServidor && respostaServidor.status === 'need_password_change') {
                        
                        // --- MODAL 2: NOVA SENHA DEFINITIVA ---
                        // --- MODAL 2: NOVA SENHA DEFINITIVA ---
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
                            preConfirm: (novaSenha) => {
                                // Validações no Front-End
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
                                return fetch('../configuracoes/auth?action=update_first_password', { method: 'POST', body: fdPass })
                                    .then(response => response.json())
                                    .then(resp => {
                                        if (resp.status !== 'success') { throw new Error(resp.message); }
                                        return resp;
                                    })
                                    .catch(error => { Swal.showValidationMessage(`Erro: ${error}`); });
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
                                    window.location.href = './dashboard';
                                });
                            }
                        });

                    } else {
                        // Login normal caso por algum motivo o first_access fosse 0
                        window.location.href = './dashboard';
                    }
                }
            });

        } else {
            msgErro.innerText = data.message;
            msgErro.style.display = 'block';
            btn.innerHTML = 'ENTRAR';
            btn.disabled = false;
        }
      });
    });
    </script>

</body>
</html>