<?php
function dispararWebhookN8n($url, $dadosArray, $api_key) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dadosArray)); 
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-gestao-api-key: ' . $api_key // O mesmo valor definido no n8n
    ]);

    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'sucesso' => ($httpCode >= 200 && $httpCode < 300),
        'http_code' => $httpCode,
        'resposta'  => $resposta
    ];
}
?>