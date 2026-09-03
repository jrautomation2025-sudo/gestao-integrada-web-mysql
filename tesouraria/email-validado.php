<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-mail Validado - Gestão Financeira</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-family: 'Segoe UI', sans-serif; 
            padding: 20px; 
        }
        .success-card { 
            background-color: var(--bg-card); 
            border: 1px solid #334155; 
            border-radius: 15px; 
            padding: 50px 40px; 
            width: 100%; 
            max-width: 500px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.5); 
            text-align: center;
        }
        .icon-circle {
            width: 80px;
            height: 80px;
            background-color: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 25px auto;
        }
        .brand-title { 
            color: var(--gold); 
            font-weight: bold; 
            font-size: 1.2rem; 
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .success-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #fff;
        }
        .success-text {
            color: #cbd5e1;
            font-size: 1.05rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .btn-gold { 
            background-color: var(--gold); 
            color: #000; 
            font-weight: bold; 
            padding: 12px 30px; 
            border-radius: 8px;
            border: none; 
            transition: all 0.3s; 
            text-decoration: none;
            display: inline-block;
        }
        .btn-gold:hover { 
            background-color: #b8860b; 
            color: #fff; 
            transform: scale(1.05); 
        }
    </style>
</head>
<body>

    <div class="success-card">
        <div class="brand-title">Gestão Financeira</div>
        
        <div class="icon-circle">
            <i class="fas fa-check"></i>
        </div>

        <h1 class="success-title">E-mail Confirmado!</h1>
        
        <p class="success-text">
            Obrigado por validar seu endereço de e-mail. Seu cadastro em nossa base foi concluído com sucesso e você já passará a receber nossos comunicados.
        </p>
        
        <div class="mt-4">
            <small style="color: #64748b;">Você já pode fechar esta janela.</small>
        </div>
    </div>

</body>
</html>