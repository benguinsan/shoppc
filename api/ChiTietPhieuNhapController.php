<?php
require_once __DIR__ . '/../models/ChiTietPhieuNhap.php';
require_once __DIR__ . '/../models/PhieuNhap.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class ChiTietPhieuNhapController {
    private $chiTietPhieuNhapModel;
    private $phieuNhapModel;
    private $authMiddleware;

    public function __construct() {
        $this->chiTietPhieuNhapModel = new ChiTietPhieuNhap();
        $this->phieuNhapModel = new PhieuNhap();
        $this->authMiddleware = new AuthMiddleware();
    }

    // Lấy tất cả chi tiết phiếu nhập
    public function getAll() {
        try {
            $this->authMiddleware->authenticate();
            $chiTietList = $this->chiTietPhieuNhapModel->getAll();
            $formattedRecords = array_map(function($record) {
                return [
                    'MaCTPN' => $record['MaCTPN'],
                    'MaPhieuNhap' => $record['MaPhieuNhap'],
                    'MaSP' => $record['MaSP'],
                    'TenSP' => $record['TenSP'] ?? 'Không xác định',
                    'SoLuong' => (int)$record['SoLuong'],
                    'DonGia' => (float)$record['DonGia'],
                    'ThanhTien' => (float)$record['ThanhTien'],
                    'NgayNhap' => $record['NgayNhap'] ?? null
                ];
            }, $chiTietList);
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'data' => $formattedRecords
            ]);
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    // Lấy chi tiết phiếu nhập theo mã phiếu nhập
    public function getByMaPhieuNhap($maPhieuNhap) {
        try {
            $data = $this->chiTietPhieuNhapModel->getByMaPhieuNhap($maPhieuNhap);
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'data' => $data
            ]);
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    // Tạo mới chi tiết phiếu nhập
    public function create() {
        try {
            $this->authMiddleware->authenticate();
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception("Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.");
            }
            $this->validateCreateData($data);
            $result = $this->chiTietPhieuNhapModel->create($data);
            http_response_code(201);
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'MaCTPN' => $result['id'],
                    'message' => $result['message']
                ]
            ]);
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    private function validateCreateData($data) {
        $requiredFields = ['MaPhieuNhap', 'MaSP', 'SoLuong', 'DonGia'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Trường {$field} là bắt buộc.");
            }
        }
        if (!is_numeric($data['DonGia']) || (float)$data['DonGia'] <= 0) {
            throw new Exception("Đơn giá phải là số dương.");
        }
        if (!is_numeric($data['SoLuong']) || (int)$data['SoLuong'] <= 0) {
            throw new Exception("Số lượng phải là số dương.");
        }
    }

    /**
     * Tạo mới chi tiết phiếu nhập (dùng hàm createChiTietPhieuNhap ở model)
     */
    public function createChiTietPhieuNhap() {
        try {
            $this->authMiddleware->authenticate();
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception("Dữ liệu không hợp lệ.");
            }
            $required = ['MaPhieuNhap', 'MaSP', 'SoLuong', 'DonGia', 'ThanhTien'];
            foreach ($required as $field) {
                if (empty($data[$field])) throw new Exception("Thiếu trường $field");
            }
            $result = $this->chiTietPhieuNhapModel->createChiTietPhieuNhap($data);
            http_response_code($result['status'] === 'success' ? 201 : 400);
            echo json_encode($result);
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
} 