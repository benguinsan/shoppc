<?php
require_once __DIR__ . '/../utils/JwtHandler.php';
require_once __DIR__ . '/../models/TaiKhoan.php';
require_once __DIR__ . '/../models/NguoiDung.php';
require_once __DIR__ . '/../config/Database.php';

class AuthMiddleware {
    private $jwtHandler;
    private $db;
    private $decodedToken;

    public function __construct() {
        $this->jwtHandler = new JwtHandler();
        
        // Khởi tạo kết nối database
        $database = new Database();
        $this->db = $database->getConnection();
        
        $this->decodedToken = null;
    }

    // Phương thức để lấy kết nối database
    public function getConnection() {
        return $this->db;
    }

    public function authenticate(): ?object {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            http_response_code(401);
            echo json_encode(['error' => 'Token not provided']);
            exit;
        }

        $this->decodedToken = $this->jwtHandler->validateToken($matches[1]);
        if (!$this->decodedToken) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid token']);
            exit;
        }

        return $this->decodedToken;
    }

    public function getCurrentUser(): ?array {
        if (!$this->decodedToken) {
            return null;
        }
        
        $taikhoanModel = new Taikhoan();
        $maTK = $this->decodedToken->MaTK;
        
        return $taikhoanModel->getById($maTK);
    }
    
    public function getCurrentUserDetails(): ?array {
        if (!$this->decodedToken) {
            return null;
        }
        
        $taikhoanModel = new Taikhoan();
        $nguoiDungModel = new NguoiDung();
        $maTK = $this->decodedToken->MaTK;
        
        $taikhoan = $taikhoanModel->getById($maTK);
        if (!$taikhoan) {
            return null;
        }
        
        // Lấy MaNguoiDung từ tài khoản
        $maNguoiDung = $taikhoan['MaNguoiDung'] ?? null;
        
        // Nếu có MaNguoiDung, lấy thông tin người dùng
        $nguoiDung = null;
        if ($maNguoiDung) {
            $nguoiDung = $nguoiDungModel->getById($maNguoiDung);
        }
        
        // Kết hợp thông tin tài khoản và người dùng
        return array_merge($taikhoan, $nguoiDung ?? []);
    }
}