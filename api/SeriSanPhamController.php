<?php
require_once __DIR__ . '/../models/SeriSanPham.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class SeriSanPhamController {
    private $seriSanPhamModel;
    private $authMiddleware;

    public function __construct() {
        $this->seriSanPhamModel = new SeriSanPham();
        $this->authMiddleware = new AuthMiddleware();
    }

    // Lấy tất cả seri theo mã sản phẩm
    public function getAll() {
        try {
            $this->authMiddleware->authenticate();
            $maSP = isset($_GET['MaSP']) ? $_GET['MaSP'] : null;
            if (!$maSP) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Thiếu MaSP']);
                return;
            }
            $db = $this->authMiddleware->getConnection();
            $stmt = $db->prepare('SELECT * FROM serisanpham WHERE MaSP = ?');
            $stmt->execute([$maSP]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            http_response_code(200);
            echo json_encode(['status' => 'success', 'data' => $data]);
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // Tạo mới seri cho sản phẩm
    public function createSeri() {
        try {
            $this->authMiddleware->authenticate();
            $input = json_decode(file_get_contents('php://input'), true);
            $maSP = $input['MaSP'] ?? null;
            $trangThai = $input['TrangThai'] ?? 2;
            if (!$maSP) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Thiếu MaSP']);
                return;
            }
            $result = $this->seriSanPhamModel->createSeri($maSP, $trangThai);
            http_response_code(201);
            echo json_encode(['status' => 'success', 'data' => $result]);
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // Kiểm tra số seri đã tồn tại chưa
    public function checkSeriExists() {
        try {
            $this->authMiddleware->authenticate();
            $seri = isset($_GET['SoSeri']) ? $_GET['SoSeri'] : null;
            if (!$seri) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Thiếu SoSeri']);
                return;
            }
            $exists = $this->seriSanPhamModel->checkSeriExists($seri);
            http_response_code(200);
            echo json_encode(['status' => 'success', 'exists' => $exists]);
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // Đếm số seri theo mã sản phẩm
    public function countByMaSP() {
        try {
            $this->authMiddleware->authenticate();
            $maSP = isset($_GET['MaSP']) ? $_GET['MaSP'] : null;
            if (!$maSP) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Thiếu MaSP']);
                return;
            }
            $count = $this->seriSanPhamModel->countByMaSP($maSP);
            http_response_code(200);
            echo json_encode(['status' => 'success', 'count' => $count]);
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
} 