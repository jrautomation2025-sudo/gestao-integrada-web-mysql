<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Inválido - Gestão Financeira</title>
    <link rel="icon" href="./configuracoes/icone.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { 
            --bg-dark: #0f172a; 
            --bg-card: #1e293b; 
            --gold: #cfa34e; 
            --text-light: #e2e8f0; 
            --error: #ef4444;
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
        .error-card { 
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
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--error);
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
        .error-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #fff;
        }
        .error-text {
            color: #cbd5e1;
            font-size: 1.05rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .btn-outline-light {
            border: 1px solid #475569;
            color: #e2e8f0;
            font-weight: 500;
            padding: 12px 30px;
            border-radius: 8px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-outline-light:hover {
            background-color: #334155;
            color: #fff;
        }
    </style>
</head>
<body>

    <div class="error-card">
        <div class="brand-title">Gestão Financeira</div>
        
        <div class="icon-circle">
            <i class="fas fa-exclamation-triangle"></i>
        </div>

        <h1 class="error-title">Link Inválido ou Expirado</h1>
        
        <p class="error-text">
            O link que você tentou acessar não é mais válido. Ele pode já ter sido utilizado ou o tempo limite para validação expirou.
        </p>
    </div>

</body>
</html>