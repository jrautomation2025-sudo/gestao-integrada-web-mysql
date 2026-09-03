<?php
// jwt.php - MOTOR DE GERAÇÃO E VALIDAÇÃO DE TOKENS
require_once 'config.php';

// Defina uma chave secreta FORTE no seu config.php ou aqui
$jwt_secret = getenv('JWT_SECRET');
if (!defined('JWT_SECRET')) define('JWT_SECRET', $jwt_secret);

class JWT {
    public static function encode($payload) {
        // Cabeçalho
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));

        // Payload
        $payloadJson = json_encode($payload);
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payloadJson));

        // Assinatura
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public static function decode($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;

        list($header, $payload, $signature) = $parts;

        // Recria a assinatura para verificar se é válida
        $validSignature = hash_hmac('sha256', $header . "." . $payload, JWT_SECRET, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($validSignature));

        if ($base64UrlSignature !== $signature) return false;

        // Decodifica o payload
        $payloadData = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);
        
        // Verifica expiração
        if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
            return 'expired';
        }

        return $payloadData;
    }
}
?>
