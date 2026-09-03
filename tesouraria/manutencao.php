<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo em Desenvolvimento</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Fonte Clássica -->
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&display=swap" rel="stylesheet">
    
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
            align-items: center;
            justify-content: center;
            padding: 20px;
            text-align: center;
        }

        .font-classic {
            font-family: 'Cinzel', serif;
        }

        .construction-card {
            background-color: var(--bg-card);
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 50px 30px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 15px 35px rgba(0,0,0,0.5);
            position: relative;
            overflow: hidden;
        }

        /* Efeito visual no topo do card */
        .construction-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--bg-card), var(--gold), var(--bg-card));
        }

        .icon-wrapper {
            font-size: 4rem;
            color: var(--gold);
            margin-bottom: 25px;
            display: inline-block;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        .title {
            color: var(--gold);
            font-size: 2rem;
            margin-bottom: 15px;
        }

        .description {
            color: #94a3b8;
            font-size: 1.1rem;
            margin-bottom: 35px;
            line-height: 1.6;
        }

        .btn-gold {
            background-color: var(--gold);
            color: #000;
            font-weight: 600;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-block;
            font-family: 'Cinzel', serif;
        }

        .btn-gold:hover {
            background-color: #b58b3d;
            color: #000;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(207, 163, 78, 0.3);
        }
    </style>
</head>
<body>

    <div class="construction-card">
        <div class="icon-wrapper">
            <i class="fas fa-hammer"></i> <!-- Ícone que remete a trabalho/construção -->
        </div>
        
        <h1 class="title font-classic">Em Desenvolvimento</h1>
        
        <p class="description">
            Nossa oficina ainda está trabalhando na lapidação deste módulo. <br>
            Em breve, novas ferramentas estarão disponíveis para auxiliar nos trabalhos da Loja.
        </p>

        <a href="/" class="btn-gold">
            <i class="fas fa-arrow-left me-2"></i> Voltar ao Portal
        </a>
    </div>

</body>
</html>