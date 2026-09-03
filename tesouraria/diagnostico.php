<?php
// Ativa exibição de todos os erros na tela
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🕵️ Relatório de Diagnóstico</h1>";

// 1. Verifica versão do PHP
echo "<h3>1. Versão do PHP</h3>";
echo "Versão atual: " . phpversion() . " (Recomendado: 7.4 ou superior)<br>";

// 2. Verifica se o arquivo db.php existe
echo "<h3>2. Arquivo de Configuração</h3>";
if (file_exists('config.php')) {
    echo "✅ Arquivo 'config.php' encontrado.<br>";
    include 'config.php';
} else {
    die("❌ ERRO CRÍTICO: O arquivo 'config.php' não foi encontrado na pasta " . getcwd());
}

// 3. Teste de Conexão com o Banco
echo "<h3>3. Teste de Conexão MySQL</h3>";
echo "Host configurado: <strong>$host</strong><br>";
echo "Usuário configurado: <strong>$user</strong><br>";
echo "Banco configurado: <strong>$db</strong><br>";

try {
    $dsn_test = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $pdo_test = new PDO($dsn_test, $user, $pass);
    echo "<span style='color:green; font-weight:bold;'>✅ SUCESSO! Conexão com o banco estabelecida.</span>";
} catch (PDOException $e) {
    echo "<span style='color:red; font-weight:bold;'>❌ FALHA NA CONEXÃO:</span><br>";
    echo "Erro detalhado: " . $e->getMessage() . "<br><br>";
    echo "<strong>Dicas para Hostinger:</strong><br>";
    echo "1. Verifique se o usuário tem o prefixo (ex: u123456_admin).<br>";
    echo "2. Verifique se a senha está correta (tente resetar no painel).<br>";
    echo "3. Confirme se o usuário foi adicionado ao banco no painel MySQL.<br>";
}

// 4. Teste de Permissões de Sessão
echo "<h3>4. Sessões PHP</h3>";
if (session_start()) {
    echo "✅ Sessões funcionando.<br>";
} else {
    echo "❌ Erro ao iniciar sessão (verifique permissões da pasta /tmp).<br>";
}

echo "<hr><br>Fim do diagnóstico.";
?>