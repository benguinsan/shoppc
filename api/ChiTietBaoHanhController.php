<?php
require_once __DIR__ . '/../models/ChiTietBaoHanh.php';
require_once __DIR__ . '/../models/BaoHanh.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class ChiTietBaoHanhController {
    private $chiTietBaoHanhModel;
    private $baoHanhModel;
    private $authMiddleware;

    public function __construct() {
        // Cấu hình múi giờ cho PHP
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        
        $this->chiTietBaoHanhModel = new ChiTietBaoHanh();
        $this->baoHanhModel = new BaoHanh();
        $this->authMiddleware = new AuthMiddleware();
    }

    private function formatDate($dateString) {
        if (empty($dateString)) {
            return null;
        }
        // Chuyển đổi ngày tháng về định dạng chuẩn
        $date = new DateTime($dateString);
        return $date->format('Y-m-d H:i:s');
    }

    public function getAll() {
        try {
            $this->authMiddleware->authenticate();
            $chiTietList = $this->chiTietBaoHanhModel->getAll();
            $formattedRecords = array_map(function($record) {
                return [
                    'MaCTBH' => $record['MaCTBH'],
                    'MaBH' => $record['MaBH'],
                    'MaSeri' => $record['MaSeri'] ?? null,
                    'NgayBaoHanh' => $record['NgayBaoHanh'],
                    'NgayHoanThanh' => $record['NgayHoanThanh'],
                    'TinhTrang' => $record['TinhTrang'],
                    'ChiTiet' => $record['ChiTiet']
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

    public function getByMaBH($maBH) {
        try {
            $this->authMiddleware->authenticate();
            $baoHanh = $this->baoHanhModel->getById($maBH);
            if (!$baoHanh) {
                throw new Exception("Bảo hành với mã {$maBH} không tồn tại.");
            }
            $chiTietList = $this->chiTietBaoHanhModel->getByMaBH($maBH);
            $formattedRecords = array_map(function($record) {
                return [
                    'MaCTBH' => $record['MaCTBH'],
                    'MaBH' => $record['MaBH'],
                    'NgayBaoHanh' => $record['NgayBaoHanh'],
                    'NgayHoanThanh' => $record['NgayHoanThanh'],
                    'TinhTrang' => $record['TinhTrang'],
                    'ChiTiet' => $record['ChiTiet']
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

    public function create() {
        try {
            $userData = $this->authMiddleware->authenticate();
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception("Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.");
            }

            // Xử lý ngày tháng
            if (isset($data['NgayBaoHanh'])) {
                $data['NgayBaoHanh'] = $this->formatDate($data['NgayBaoHanh']);
            }
            if (isset($data['NgayHoanThanh'])) {
                $data['NgayHoanThanh'] = $this->formatDate($data['NgayHoanThanh']);
            }

            $this->validateCreateData($data);
            $result = $this->chiTietBaoHanhModel->create($data);
            http_response_code(201);
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'MaCTBH' => $result['id'],
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

    public function update($maCTBH) {
        try {
            $userData = $this->authMiddleware->authenticate();
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception("Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.");
            }

            // Xử lý ngày tháng
            if (isset($data['NgayBaoHanh'])) {
                $data['NgayBaoHanh'] = $this->formatDate($data['NgayBaoHanh']);
            }
            if (isset($data['NgayHoanThanh'])) {
                $data['NgayHoanThanh'] = $this->formatDate($data['NgayHoanThanh']);
            }

            $this->validateUpdateData($data);
            $result = $this->chiTietBaoHanhModel->update($maCTBH, $data);
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'MaCTBH' => $result['id'],
                    'message' => $result['message'],
                    'affected_rows' => $result['affected_rows']
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

    private function validateUpdateData($data) {
        $allowedFields = ['NgayBaoHanh', 'NgayHoanThanh', 'TinhTrang', 'ChiTiet'];
        $hasUpdate = false;
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $hasUpdate = true;
            }
        }
        if (!$hasUpdate) {
            throw new Exception("Không có thông tin nào được cập nhật.");
        }
    }

    private function validateCreateData($data) {
        $requiredFields = ['MaBH', 'NgayBaoHanh', 'TinhTrang', 'ChiTiet'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Trường {$field} là bắt buộc.");
            }
        }
        // NgayHoanThanh có thể null hoặc rỗng (nếu chưa hoàn thành)
    }
} 