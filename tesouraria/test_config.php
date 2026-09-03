<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../configuracoes/config.php';

// 1. COLOQUE AQUI O USUÁRIO E A SENHA QUE VOCÊ SABE QUE ESTÃO CERTOS
$usuario_digitado = 'fraternidade4028@gmail.com'; // ou seu email de teste
$senha_digitada = 'Loja@4028';  // a senha real

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. MUDE "usuarios" PARA O NOME DA SUA TABELA E "login" PARA O NOME DA SUA COLUNA
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = :email");
    $stmt->bindParam(':email', $usuario_digitado);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verifica se achou o usuário
    if ($user) {
        echo "✅ Usuário encontrado no banco!<br><br>";
        
        // 3. MUDE 'senha' PARA O NOME DA COLUNA DE SENHA NA SUA TABELA
        echo "Senha guardada no banco: <strong>" . $user['senha'] . "</strong><br>";
        echo "Senha que você digitou: <strong>" . $senha_digitada . "</strong><br><br>";

        // Tenta descobrir como a senha está validando
        if ($senha_digitada === $user['senha']) {
            echo "🔓 SUCESSO: A senha confere exatamente (Texto Puro). O problema está nas Sessões!";
        } elseif (password_verify($senha_digitada, $user['senha'])) {
            echo "🔓 SUCESSO: A senha confere (Criptografada com password_hash). Seu script precisa usar password_verify()!";
        } elseif (md5($senha_digitada) === $user['senha']) {
            echo "🔓 SUCESSO: A senha confere (Criptografada com MD5). Seu script precisa usar md5()!";
        } else {
            echo "❌ ERRO: O usuário foi achado, mas a senha digitada NÃO BATE com a do banco de jeito nenhum.";
        }
    } else {
        echo "❌ ERRO: O banco não achou nenhum usuário com o login '$usuario_digitado'. Verifique se o nome da tabela/coluna está correto no SELECT.";
    }

} catch(PDOException $e) {
    echo "❌ Erro no banco de dados: " . $e->getMessage();
}
?>