<?php
session_start();
require '../configuracoes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    exit;
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

$acao_interna = $input['acao_interna'] ?? '';
if ($acao_interna !== 'prestacao_contas') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Ação inválida.']);
    exit;
}

$mes_filtro = $input['mes'] ?? date('m');
$ano_filtro = $input['ano'] ?? date('Y');

$mesesPT = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março',
    '04' => 'Abril', '05' => 'Maio', '06' => 'Junho',
    '07' => 'Julho', '08' => 'Agosto', '09' => 'Setembro',
    '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
];

$user_id = $_SESSION['user_id'] ?? $_SESSION['user']['id'];
$contexto = $_SESSION['contexto_atual'] ?? 'pessoal';

$sqlUsuario = "SELECT nome FROM usuarios WHERE id = ?";
$stmtUsuario = $pdo->prepare($sqlUsuario);
$stmtUsuario->execute([$user_id]);
$nomeUsuarioLogado = $stmtUsuario->fetchColumn() ?: 'Administrador'; 

try {
    if ($mes_filtro === 'todos') {
        $filtroMesSql = "";
        $paramsSQL = [$user_id, $contexto, $ano_filtro];
        $dataLimiteSaldoAnterior = $ano_filtro . '-01-01'; 
    } else {
        $filtroMesSql = " AND MONTH(data_transacao) = ?";
        $paramsSQL = [$user_id, $contexto, $ano_filtro, $mes_filtro];
        $dataLimiteSaldoAnterior = $ano_filtro . '-' . str_pad($mes_filtro, 2, '0', STR_PAD_LEFT) . '-01';
    }

    $sqlAnterior = "SELECT SUM(CASE WHEN tipo = 'saldo' THEN valor ELSE -valor END) as saldo_anterior 
                    FROM transacoes 
                    WHERE usuario_id = ? AND contexto = ? AND data_transacao < ?";
    $stmtAnterior = $pdo->prepare($sqlAnterior);
    $stmtAnterior->execute([$user_id, $contexto, $dataLimiteSaldoAnterior]);
    $saldoAnterior = $stmtAnterior->fetchColumn() ?: 0;

    $sqlDetalhes = "SELECT data_transacao, descricao, valor, tipo 
                    FROM transacoes 
                    WHERE usuario_id = ? AND contexto = ? AND YEAR(data_transacao) = ?" . $filtroMesSql . " 
                    ORDER BY data_transacao ASC";
    $stmtDetalhes = $pdo->prepare($sqlDetalhes);
    $stmtDetalhes->execute($paramsSQL);
    $lancamentos_detalhados = $stmtDetalhes->fetchAll(PDO::FETCH_ASSOC);
    
    $sqlInvestimentos = "SELECT data_aporte, ativo as descricao, valor_atual as valor, categoria as tipo  
                    FROM investimentos 
                    WHERE usuario_id = ? AND contexto = ?
                    ORDER BY data_aporte ASC";
    $stmtInvestimentos = $pdo->prepare($sqlInvestimentos);
    $stmtInvestimentos->execute([$user_id, $contexto]);
    $lancamentos_investimentos = $stmtInvestimentos->fetchAll(PDO::FETCH_ASSOC);

    $listaInvestimentos = [];
    $listaReceitas = [];
    $listaMensalidades = [];
    $listaDespesasFixas = [];
    $listaDespesasVariaveis = [];
    $listaSaldo = [];
    $listaTronco = [];
    $listaDoacao = [];
    $subtotalInvestimentos = 0;
    $subtotalReceitas = 0;
    $subtotalMensalidades = 0;
    $subtotalDespesasFixas = 0;
    $subtotalDespesasVariaveis = 0;
    $subtotalSaldo = 0;
    $subtotalTronco = 0;
    $subtotalDoacao = 0;

    foreach ($lancamentos_detalhados as $l) {
        $tipo = strtolower(trim($l['tipo']));
        if ($tipo === 'receita' || $tipo === 'saldo') {
            $listaReceitas[] = $l;
            $subtotalReceitas += $l['valor'];
        } elseif ($tipo === 'saldo') {
            $listaSaldo[] = $l;
            $subtotalSaldo += $l['valor'];
        } elseif ($tipo === 'mensalidade') {
            $listaMensalidades[] = $l;
            $subtotalMensalidades += $l['valor'];
        } elseif ($tipo === 'fixo') {
            $listaDespesasFixas[] = $l;
            $subtotalDespesasFixas += $l['valor'];
        } elseif ($tipo === 'tronco') {
            $listaTronco[] = $l;
            $subtotalTronco += $l['valor']; 
        } elseif ($tipo === 'doação') {
            $listaDoacao[] = $l;
            $subtotalDoacao += $l['valor'];
        } else {
            $listaDespesasVariaveis[] = $l;
            $subtotalDespesasVariaveis += $l['valor'];
        }
    }
    
    foreach ($lancamentos_investimentos as $li) {
        $listaInvestimentos[] = $li;
        $subtotalInvestimentos += $li['valor'];
    }
    
    $totalAnterior = ($saldoAnterior === 0) ? $subtotalSaldo : $saldoAnterior;
    
    $totalPeriodo = $subtotalReceitas + $subtotalMensalidades;
    $totalReceita = $subtotalReceitas + $subtotalMensalidades;
    $totalDespesas = $subtotalDespesasFixas + $subtotalDespesasVariaveis;
    $totalAtual = $totalPeriodo - $totalDespesas;
    $totalInvestido = $subtotalInvestimentos;
    $totalTronco = $subtotalTronco;
    $totalDoacao = $subtotalDoacao;
    $totalDoacoes = $subtotalTronco - $subtotalDoacao;
    $totalfinal = $totalAtual + $totalDoacoes;
    $totalAcumulado = $totalfinal + $totalInvestido;

    $periodoTexto = ($mes_filtro === 'todos') ? "Todos os Meses de $ano_filtro" : "$mes_filtro/$ano_filtro";
    $nomeMesAtual = isset($mesesPT[$mes_filtro]) ? $mesesPT[$mes_filtro] : $mes_filtro;
    
    $corpoEmail = "
    <div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;'>
        <h2 style='color: #cfa34e; text-align: center; border-bottom: 2px solid #cfa34e; padding-bottom: 10px;'>
            Relatório Financeiro ($periodoTexto)
        </h2>
        <p style='font-size: 14px;'>Prezados irmãos, segue abaixo o resumo e o detalhamento financeiro referentes ao período de " . $nomeMesAtual .".</p>
        
        <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>
            <!--<p style='margin: 5px 0;'><strong>Saldo Anterior:</strong> <span style='color: " . ($totalAnterior >= 0 ? '#0000FF' : '#dc3545') . ";'>+ R$ " . number_format($totalAnterior, 2, ',', '.') . "</span></p><br/> -->
            <p style='margin: 5px 0;'><strong>Receitas Período:</strong> <span style='color: " . ($totalReceita >= 0 ? '#3CB371' : '#dc3545') . ";'>+ R$ " . number_format($totalReceita, 2, ',', '.') . "</span></p>
            <p style='margin: 5px 0;'><strong>Despesas Período:</strong> <span style='color: " . ($totalDespesas >= 0 ? '#FF0000' : '#dc3545') . ";'>- R$ " . number_format($totalDespesas, 2, ',', '.') . "</span></p>
            <p style='margin: 5px 0;'><strong>Saldo Atual Período:</strong> <span style='color: " . ($totalAtual >= 0 ? '#0000FF' : '#dc3545') . ";'>+ R$ " . number_format($totalAtual, 2, ',', '.') . "</span></p><br/>
            <p style='margin: 5px 0;'><strong>Receitas Tronco:</strong> <span style='color: " . ($totalTronco >= 0 ? '#3CB371' : '#dc3545') . ";'>+ R$ " . number_format($totalTronco, 2, ',', '.') . "</span></p>
            <p style='margin: 5px 0;'><strong>Doações Período:</strong> <span style='color: " . ($totalDoacao >= 0 ? '#FF0000' : '#dc3545') . ";'>- R$ " . number_format($totalDoacao, 2, ',', '.') . "</span></p>
            <p style='margin: 5px 0;'><strong>Saldo Tronco Período:</strong> <span style='color: " . ($totalDoacoes >= 0 ? '#0000FF' : '#dc3545') . ";'>+ R$ " . number_format($totalDoacoes, 2, ',', '.') . "</span></p><br/>
            <p style='margin: 5px 0;'><strong>Saldo Próximo Período:</strong> <span style='color: " . ($totalfinal >= 0 ? '#3CB371' : '#dc3545') . ";'>+ R$ " . number_format($totalfinal, 2, ',', '.') . "</span></p>
            <p style='margin: 5px 0;'><strong>Saldo Investimento:</strong> <span style='color: " . ($totalInvestido >= 0 ? '#3CB371' : '#dc3545') . ";'>+ R$ " . number_format($totalInvestido, 2, ',', '.') . "</span></p>
            <p style='margin: 5px 0;'><strong>Saldo Acumulado:</strong> <span style='color: " . ($totalAcumulado >= 0 ? '#0000FF' : '#dc3545') . ";'>+ R$ " . number_format($totalAcumulado, 2, ',', '.') . "</span></p>
        </div>
        
        <h4 style='color: #191970; margin-bottom: 5px;'>↑ Investimentos</h4>
        <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px;'>
            <thead>
                <tr style='background-color: #6495ED;'>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: left; width: 20%;'>Referência</th>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: left; width: 55%;'>Descrição</th>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: right; width: 25%;'>Valor</th>
                </tr>
            </thead>
            <tbody>";
            if (!empty($listaInvestimentos)) {
                foreach ($listaInvestimentos as $li) {
                    $corpoEmail .= "<tr><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($periodoTexto). "</td><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($li['descricao']) . "</td><td style='border: 1px solid #ddd; padding: 8px; text-align: right; color: #191970; font-weight: bold;'>+ R$ " . number_format($li['valor'], 2, ',', '.') . "</td></tr>";
                }
            } else {
                $corpoEmail .= "<tr><td colspan='3' style='border: 1px solid #ddd; padding: 8px; text-align: center; color: #777;'>Nenhum investimento.</td></tr>";
            }
            $corpoEmail .= "<tr style='background-color: #f2f2f2;'><td style='border: 1px solid #ddd; padding: 8px; text-align: right; font-weight: bold;'>SUBTOTAL:</td><td style='border: 1px solid #ddd; padding: 8px;'></td><td style='border: 1px solid #ddd; padding: 8px; text-align: right; color: #191970; font-weight: bold;'>R$ " . number_format($subtotalInvestimentos, 2, ',', '.') . "</td></tr></tbody>
        </table>

        <h4 style='color: #198754; margin-bottom: 5px;'>↑ Receitas</h4>
        <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px;'>
            <thead>
                <tr style='background-color: #e2f0d9;'>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: left; width: 20%;'>Referência</th>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: left; width: 55%;'>Descrição</th>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: right; width: 25%;'>Valor</th>
                </tr>
            </thead>
            <tbody>";
            if (!empty($listaReceitas)) {
                foreach ($listaReceitas as $l) {
                    $corpoEmail .= "<tr><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($periodoTexto). "</td><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($l['descricao']) . "</td><td style='border: 1px solid #ddd; padding: 8px; text-align: right; color: #198754; font-weight: bold;'>+ R$ " . number_format($l['valor'], 2, ',', '.') . "</td></tr>";
                }
            } else {
                $corpoEmail .= "<tr><td colspan='3' style='border: 1px solid #ddd; padding: 8px; text-align: center; color: #777;'>Nenhuma receita.</td></tr>";
            }
            $corpoEmail .= "<tr style='background-color: #f2f2f2;'><td style='border: 1px solid #ddd; padding: 8px; text-align: right; font-weight: bold;'>SUBTOTAL:</td><td style='border: 1px solid #ddd; padding: 8px;'></td><td style='border: 1px solid #ddd; padding: 8px; text-align: right; color: #198754; font-weight: bold;'>R$ " . number_format($subtotalReceitas, 2, ',', '.') . "</td></tr></tbody>
        </table>

        <h4 style='color: #4B0082; margin-bottom: 5px;'>↑ Mensalidades</h4>
        <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px;'>
            <thead>
                <tr style='background-color: #DA70D6;'>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: left; width: 20%;'>Referência</th>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: left; width: 55%;'>Descrição</th>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: right; width: 25%;'>Valor</th>
                </tr>
            </thead>
            <tbody>";
            if (!empty($listaMensalidades)) {
                foreach ($listaMensalidades as $l) {
                    $corpoEmail .= "<tr><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($periodoTexto). "</td><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($l['descricao']) . "</td><td style='border: 1px solid #ddd; padding: 8px; text-align: right; color: #4B0082; font-weight: bold;'>+ R$ " . number_format($l['valor'], 2, ',', '.') . "</td></tr>";
                }
            } else {
                $corpoEmail .= "<tr><td colspan='3' style='border: 1px solid #ddd; padding: 8px; text-align: center; color: #777;'>Nenhuma mensalidade.</td></tr>";
            }
            $corpoEmail .= "<tr style='background-color: #f2f2f2;'><td style='border: 1px solid #ddd; padding: 8px; text-align: right; font-weight: bold;'>SUBTOTAL:</td><td style='border: 1px solid #ddd; padding: 8px;'></td><td style='border: 1px solid #ddd; padding: 8px; text-align: right; color: #4B0082; font-weight: bold;'>R$ " . number_format($subtotalMensalidades, 2, ',', '.') . "</td></tr></tbody>
        </table>
        
        <h4 style='color: #A0522D; margin-bottom: 5px;'>↑ Tronco Beneficência</h4>
        <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px;'>
            <thead>
                <tr style='background-color: #D2B48C;'>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: left; width: 20%;'>Referência</th>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: left; width: 55%;'>Descrição</th>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: right; width: 25%;'>Valor</th>
                </tr>
            </thead>
            <tbody>";
            if (!empty($listaTronco)) {
                foreach ($listaTronco as $li) {
                    $corpoEmail .= "<tr><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($periodoTexto). "</td><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($li['descricao']) . "</td><td style='border: 1px solid #ddd; padding: 8px; text-align: right; color: #A0522D; font-weight: bold;'>+ R$ " . number_format($li['valor'], 2, ',', '.') . "</td></tr>";
                }
            } else {
                $corpoEmail .= "<tr><td colspan='3' style='border: 1px solid #ddd; padding: 8px; text-align: center; color: #777;'>Nenhum tronco.</td></tr>";
            }
            $corpoEmail .= "<tr style='background-color: #f2f2f2;'><td style='border: 1px solid #ddd; padding: 8px; text-align: right; font-weight: bold;'>SUBTOTAL:</td><td style='border: 1px solid #ddd; padding: 8px;'></td><td style='border: 1px solid #ddd; padding: 8px; text-align: right; color: #A0522D; font-weight: bold;'>R$ " . number_format($subtotalTronco, 2, ',', '.') . "</td></tr></tbody>
        </table>

        <h4 style='color: #DAA520; margin-bottom: 5px;'>📌 Despesas Fixas</h4>
        <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px;'>
            <thead>
                <tr style='background-color: #fff2cc;'>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: left; width: 20%;'>Referência</th>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: left; width: 55%;'>Descrição</th>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: right; width: 25%;'>Valor</th>
                </tr>
            </thead>
            <tbody>";
            if (!empty($listaDespesasFixas)) {
                foreach ($listaDespesasFixas as $l) {
                    $corpoEmail .= "<tr><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($periodoTexto). "</td><td style='border: 1px solid #ddd; padding: 8px;'>".htmlspecialchars($l['descricao'])."</td><td style='border: 1px solid #ddd; padding: 8px; text-align: right; color: #DAA520; font-weight: bold;'>- R$ ".number_format($l['valor'], 2, ',', '.')."</td></tr>";
                }
            } else {
                $corpoEmail .= "<tr><td colspan='3' style='border: 1px solid #ddd; padding: 8px; text-align: center; color: #777;'>Nenhuma despesa fixa.</td></tr>";
            }
            $corpoEmail .= "<tr style='background-color: #f2f2f2;'><td style='border: 1px solid #ddd; padding: 8px; text-align: right; font-weight: bold;'>SUBTOTAL:</td><td style='border: 1px solid #ddd; padding: 8px;'></td><td style='border: 1px solid #ddd; padding: 8px; text-align: right; color: #DAA520; font-weight: bold;'>R$ " . number_format($subtotalDespesasFixas, 2, ',', '.') . "</td></tr></tbody>
        </table>

        <h4 style='color: #FF0000; margin-bottom: 5px;'>🔀 Despesas Variáveis</h4>
        <table style='width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 13px;'>
            <thead>
                <tr style='background-color: #FF6347;'>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: left; width: 20%;'>Referência</th>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: left; width: 55%;'>Descrição</th>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: right; width: 25%;'>Valor</th>
                </tr>
            </thead>
            <tbody>";
            if (!empty($listaDespesasVariaveis)) {
                foreach ($listaDespesasVariaveis as $l) {
                    $corpoEmail .= "<tr><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($periodoTexto). "</td><td style='border: 1px solid #ddd; padding: 8px;'>".htmlspecialchars($l['descricao'])."</td><td style='border: 1px solid #ddd; padding: 8px; text-align: right; color: #FF0000; font-weight: bold;'>- R$ ".number_format($l['valor'], 2, ',', '.')."</td></tr>";
                }
            } else {
                $corpoEmail .= "<tr><td colspan='3' style='border: 1px solid #ddd; padding: 8px; text-align: center; color: #777;'>Nenhuma despesa variável.</td></tr>";
            }
            $corpoEmail .= "<tr style='background-color: #f2f2f2;'><td style='border: 1px solid #ddd; padding: 8px; text-align: right; font-weight: bold;'>SUBTOTAL:</td><td style='border: 1px solid #ddd; padding: 8px;'></td><td style='border: 1px solid #ddd; padding: 8px; text-align: right; color: #FF0000; font-weight: bold;'>R$ " . number_format($subtotalDespesasVariaveis, 2, ',', '.') . "</td></tr></tbody>
        </table>
        
        <h4 style='color: #FFA500; margin-bottom: 5px;'>🔀 Despesas Doações</h4>
        <table style='width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 13px;'>
            <thead>
                <tr style='background-color: #FFE4B5;'>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: left; width: 20%;'>Referência</th>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: left; width: 55%;'>Descrição</th>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: right; width: 25%;'>Valor</th>
                </tr>
            </thead>
            <tbody>";
            if (!empty($listaDoacao)) {
                foreach ($listaDoacao as $l) {
                    $corpoEmail .= "<tr><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($periodoTexto). "</td><td style='border: 1px solid #ddd; padding: 8px;'>".htmlspecialchars($l['descricao'])."</td><td style='border: 1px solid #ddd; padding: 8px; text-align: right; color: #FFA500; font-weight: bold;'>- R$ ".number_format($l['valor'], 2, ',', '.')."</td></tr>";
                }
            } else {
                $corpoEmail .= "<tr><td colspan='3' style='border: 1px solid #ddd; padding: 8px; text-align: center; color: #777;'>Nenhuma despesa de doação.</td></tr>";
            }
            $corpoEmail .= "<tr style='background-color: #f2f2f2;'><td style='border: 1px solid #ddd; padding: 8px; text-align: right; font-weight: bold;'>SUBTOTAL:</td><td style='border: 1px solid #ddd; padding: 8px;'></td><td style='border: 1px solid #ddd; padding: 8px; text-align: right; color: #FFA500; font-weight: bold;'>R$ " . number_format($subtotalDoacao, 2, ',', '.') . "</td></tr></tbody>
        </table>
        
        <p style='font-size: 11px; color: #999; text-align: center; margin-top: 30px;'>
            Relatório gerado automaticamente por: <strong>" . htmlspecialchars($nomeUsuarioLogado) . "</strong> no sistema Gestão Maçônica Integrada.
        </p>
    </div>";

    $sqlMembros = "SELECT nome, email FROM clientes WHERE email IS NOT NULL AND email = 'everaljun@gmail.com' AND situacao = 'Ativo' AND usuario_id = ?";
    $stmtMembros = $pdo->prepare($sqlMembros);
    $stmtMembros->execute([$user_id]); 
    $membros = $stmtMembros->fetchAll(PDO::FETCH_ASSOC);

    if (count($membros) === 0) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum membro ativo com e-mail cadastrado foi encontrado.']);
        exit;
    } 

    $assunto = $nomeUsuarioLogado . " - Relatório Financeiro - " . $periodoTexto;

    $payloadN8N = [
        'assunto' => $assunto,
        'corpo_html' => $corpoEmail,
        'membros' => $membros
    ];

    $urlWebhookN8N = 'https://n8n-prod.jrtec.com.br/webhook/envia-relatorio';

    $ch = curl_init($urlWebhookN8N);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payloadN8N));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $responseN8N = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Relatório enviado para a fila de processamento com sucesso!'
        ]);
    } else {
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Erro ao comunicar com o motor de disparos do n8n.'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false, 
        'mensagem' => 'Erro interno ao processar o relatório: ' . $e->getMessage()
    ]);
}
?>
