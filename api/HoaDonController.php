<?php
require_once __DIR__ . '/../models/HoaDon.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class HoaDonController {
    private $hoaDonModel;
    private $authMiddleware;

    public function __construct() {
        $this->hoaDonModel = new HoaDon();
        $this->authMiddleware = new AuthMiddleware();
    }

    public function getAll() {
        try {
            // Xác thực người dùng
            $this->authMiddleware->authenticate();
            
            // Lấy tham số từ query string
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            $fromDate = isset($_GET['from_date']) ? $_GET['from_date'] : '';
            $toDate = isset($_GET['to_date']) ? $_GET['to_date'] : '';
            $status = isset($_GET['status']) ? $_GET['status'] : '';
            $orderBy = isset($_GET['order_by']) ? $_GET['order_by'] : 'NgayLap';
            $orderDirection = isset($_GET['order_direction']) ? $_GET['order_direction'] : 'DESC';

            // Gọi model để lấy dữ liệu
            $result = $this->hoaDonModel->getAll(
                $page,
                $limit,
                $search,
                $fromDate,
                $toDate,
                $status,
                $orderBy,
                $orderDirection
            );

            // Format dữ liệu trả về
            $formattedRecords = array_map(function($record) {
                return [
                    'MaHD' => $record['MaHD'],
                    'MaNguoiDung' => $record['MaNguoiDung'],
                    'TenNguoiDung' => $record['TenNguoiDung'],
                    'MaNhanVien' => $record['MaNhanVien'],
                    'TenNhanVien' => $record['TenNhanVien'],
                    'NgayLap' => $record['NgayLap'],
                    'TongTien' => (float)$record['TongTien'],
                    'TrangThai' => (int)$record['TrangThai'],
                    'TrangThaiText' => $this->hoaDonModel->getTrangThaiText($record['TrangThai'])
                ];
            }, $result['data']);

            // Trả về response
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'data' => $formattedRecords,
                'pagination' => $result['pagination']
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
            // Xác thực người dùng
            $userData = $this->authMiddleware->authenticate();
            
            // Lấy dữ liệu từ body request
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                throw new Exception("Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.");
            }
            
            // Validate dữ liệu
            $this->validateCreateData($data);
            
            // Nếu không có MaNguoiDung, lấy từ token
            if (!isset($data['MaNguoiDung'])) {
                $data['MaNguoiDung'] = $userData['MaNguoiDung'];
            }
            
            // Nếu không có TongTien, mặc định là 0
            if (!isset($data['TongTien'])) {
                $data['TongTien'] = 0;
            }
            
            // Gọi model để tạo hóa đơn
            $result = $this->hoaDonModel->create($data);
            
            // Trả về response
            http_response_code(201);
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'MaHD' => $result['id'],
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
        // Kiểm tra MaNguoiDung
        if (!isset($data['MaNguoiDung'])) {
            throw new Exception("Mã người dùng không được để trống.");
        }
    }
    
    public function update($maHD) {
        try {
            // Xác thực người dùng
            $userData = $this->authMiddleware->authenticate();
            
            // Lấy dữ liệu từ body request
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                throw new Exception("Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.");
            }
            
            // Gọi model để cập nhật hóa đơn
            $result = $this->hoaDonModel->update($maHD, $data);
            
            // Trả về response
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'MaHD' => $result['id'],
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
} 