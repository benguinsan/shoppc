<?php
require 'vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtHandler {
    private $secretKey;
    private $algorithm;

    public function __construct() {
        $this->secretKey = 'your_very_strong_secret_key_123!@#';
        $this->algorithm = 'HS256';
    }

    // Tạo token
    public function generateToken(array $payload): string {
        $defaultClaims = [
            'iss' => 'shop_pc',
            'iat' => time(),
            'exp' => time() + 3600 // 1 giờ
        ];
        $finalPayload = array_merge($defaultClaims, $payload);
        
        return JWT::encode($finalPayload, $this->secretKey, $this->algorithm);
    }

    // Xác thực token
    public function validateToken(string $token): ?object {
        try {
            JWT::$leeway = 60; // Sai lệch thời gian cho phép (giây)
            return JWT::decode($token, new Key($this->secretKey, $this->algorithm));
        } catch (Exception $e) {
            return null;
        }
    }

 

}