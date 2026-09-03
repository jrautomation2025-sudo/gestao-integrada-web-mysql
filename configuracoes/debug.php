<?php
// Exibe todos os erros
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

//require 'config.php';

$email = $_GET['email'] ?? 'teste@gmail.com';
$pass  = $_GET['senha'] ?? 'teste123';

if (!$email || !$pass) {
    die("<h3>Uso:</h3> Adicione ?email=SEU_EMAIL&pass=SUA_SENHA na URL.");
}

echo "<h1>🕵️ Detetive de Senhas</h1>";
echo "Testando login para: <strong>$email</strong><br><hr>";

// 1. Busca no banco
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    die("❌ ERRO: Usuário não encontrado no banco.");
}

// 2. Analisa o Hash salvo
$hash_salvo = $user['senha'];
$tamanho = strlen($hash_salvo);

echo "Senha digitada: " . htmlspecialchars($pass) . "<br>";
echo "Hash salvo no Banco: " . htmlspecialchars($hash_salvo) . "<br>";
echo "Tamanho do Hash: <strong>$tamanho caracteres</strong><br>";

// 3. Verifica Truncamento
if ($tamanho < 60) {
    echo "<h2 style='color:red'>🚨 PROBLEMA ENCONTRADO!</h2>";
    echo "O hash salvo tem apenas $tamanho caracteres, mas deveria ter 60.<br>";
    echo "Isso significa que sua coluna 'password' no MySQL é muito pequena.<br>";
    echo "<strong>Solução:</strong> Vá no phpMyAdmin e altere a coluna 'password' para <strong>VARCHAR(255)</strong>.";
} 
// 4. Teste de Verificação
else {
    if (password_verify($pass, $hash_salvo)) {
        echo "<h2 style='color:green'>✅ SUCESSO!</h2>";
        echo "A senha bateu! O problema pode estar no JavaScript ou sessão, não na senha.";
    } else {
        echo "<h2 style='color:red'>❌ A SENHA NÃO BATE.</h2>";
        echo "Gerando novo hash para comparação: " . password_hash($pass, PASSWORD_DEFAULT) . "<br>";
        echo "Se o hash acima for muito diferente do salvo, você pode ter digitado a senha errada no cadastro.";
    }
}
?>