<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suporte Técnico - JR Tech Automation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --bg-dark: #0f172a; --bg-card: #1e293b; --gold: #cfa34e; --text-light: #f1f5f9; }
        body { background-color: var(--bg-dark); color: var(--text-light); font-family: 'Segoe UI', sans-serif; min-height: 100vh; display: flex; flex-direction: column; justify-content: center; }

        .card-custom { background: var(--bg-card); border: 1px solid #334155; border-radius: 12px; padding: 40px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        .text-gold { color: var(--gold) !important; }
        .btn-gold { background: var(--gold); border: none; color: #000; font-weight: bold; padding: 12px 30px; font-size: 1.1rem; transition: transform 0.2s; }
        .btn-gold:hover { background: #b8860b; color: #fff; transform: translateY(-2px); }
        .top-bar { position: absolute; top: 20px; left: 20px; }
        .btn-voltar { position: absolute; top: 20px; left: 20px; text-decoration: none; color: #94a3b8; font-weight: 500; padding: 10px 15px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.1); transition: all 0.3s ease; background: rgba(15, 23, 42, 0.8); z-index: 1000; }
        .btn-voltar:hover { color: var(--gold); border-color: var(--gold); transform: translateX(-5px); }
    </style>
</head>
<body>


<a href="sistema" class="btn-voltar">
    <i class="fas fa-arrow-left me-2"></i> Voltar ao Site
</a>

<div class="container py-5">
    <div class="row justify-content-center align-items-center">
        <div class="col-lg-7 col-md-9">
            
            <div class="text-center mb-4">
                <h2 style="font-family: 'Cinzel', serif; font-weight: 700; color: white; font-size: 2rem;">
                    <i class="fas fa-life-ring me-2 text-warning"></i> Central de Suporte
                </h2>
                <p class="text-warning">JR Tec - Atendimento Externo</p>
                <p class="text-warning mb-0">Precisa de ajuda, encontrou algum erro ou quer sugerir melhorias? Estamos aqui para ajudar.</p>
            </div>

            <div class="card-custom text-center">
                <div class="mb-4">
                    <i class="fas fa-headset text-gold" style="font-size: 4rem;"></i>
                </div>
                <h3 class="fw-bold mb-3">Portal de Atendimento Jira Cloud</h3>
                <p class="text-muted mb-4 px-md-3">
                    Precisa de ajuda com o acesso, encontrou algum erro no sistema ou quer abrir um chamado técnico? Acesse nossa central oficial de atendimento.
                </p>
                
                <a href="https://jrtec.atlassian.net/servicedesk/customer/portal/1" target="_blank" class="btn btn-gold rounded-pill shadow">
                    <i class="fas fa-external-link-alt me-2"></i> Acessar Portal de Chamados
                </a>

                <div class="mt-5 pt-4 border-top border-secondary text-start text-muted small">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <i class="fas fa-clock text-gold me-2"></i> <strong>Atendimento:</strong><br>Segunda a Sexta, das 08h às 18h.
                        </div>
                        <div class="col-md-6">
                            <i class="fas fa-envelope text-gold me-2"></i> <strong>Notificações:</strong><br>Acompanhe o chamado por e-mail.
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
