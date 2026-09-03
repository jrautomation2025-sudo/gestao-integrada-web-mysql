<?php
// webhook_helper.php

function dispararWebhookN8n($url, $dadosArray, $secret) {
    // 1. Gera o JWT
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    
    $payload = json_encode([
        'iss' => 'painel_saas',
        'iat' => time(),
        'exp' => time() + 300 // Expira em 5 minutos
    ]);

    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    $token = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

    // 2. Dispara o cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dadosArray)); 
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token 
    ]);

    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Retorna um array com o resultado detalhado
    return [
        'sucesso' => ($httpCode >= 200 && $httpCode < 300),
        'http_code' => $httpCode,
        'resposta'  => $resposta
    ];
}