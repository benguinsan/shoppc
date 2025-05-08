<?php
require_once __DIR__ . '/../models/PhieuNhap.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class PhieuNhapController {
    private $phieuNhapModel;
    private $authMiddleware;

    public function __construct() {
        $this->phieuNhapModel = new PhieuNhap();
        $this->authMiddleware = new AuthMiddleware();
    }

    private function getTrangThaiText($trangThai) {
        switch($trangThai) {
            case 0:
                return 'Đã hủy';
            case 1:
                return 'Chờ xử lý';
            case 2:
                return 'Đã xử lý';
            default:
                return 'Không xác định';
        }
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
            $orderBy = isset($_GET['order_by']) ? $_GET['order_by'] : 'NgayNhap';
            $orderDirection = isset($_GET['order_direction']) ? $_GET['order_direction'] : 'DESC';

            // Gọi model để lấy dữ liệu
            $result = $this->phieuNhapModel->getAll(
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
                    'MaPN' => $record['MaPhieuNhap'],
                    'MaNhaCungCap' => $record['MaNCC'],
                    'TenNhaCungCap' => $record['TenNhaCungCap'],
                    'NgayNhap' => $record['NgayNhap'],
                    'TongTien' => (float)$record['TongTien'],
                    'TrangThai' => (int)$record['TrangThai'],
                    'TrangThaiText' => $this->getTrangThaiText($record['TrangThai'])
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

    /**
     * Lấy danh sách tất cả nhân viên
     */
    public function getNhanVien() {
        try {
            // Xác thực người dùng (có thể bỏ nếu không cần)
            $this->authMiddleware->authenticate();
            
            // Log thông tin thực hiện API
            error_log("Đang gọi API lấy danh sách nhân viên");
            
            // Gọi model để lấy danh sách nhân viên
            $result = $this->phieuNhapModel->getAllNhanVien();
            
            // Log kết quả
            error_log("Kết quả API nhân viên: " . json_encode($result));
            
            // Trả về response
            http_response_code(200);
            echo json_encode($result);
            
        } catch(Exception $e) {
            // Log chi tiết lỗi
            error_log("Lỗi API nhân viên: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

}