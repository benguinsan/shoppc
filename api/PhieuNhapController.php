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
                    'TrangThaiText' => $this->phieuNhapModel->getTrangThaiText($record['TrangThai'])
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
            error_log('[PHIEU_NHAP_CONTROLLER_INPUT] ' . json_encode($data));

            if (!$data) {
                throw new Exception("Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.");
            }

            // Validate dữ liệu
            $this->validateCreateData($data);

            // Gọi model để tạo phiếu nhập
            $result = $this->phieuNhapModel->create($data);

            // Trả về response
            http_response_code(201);
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'MaPN' => $result['id'],
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

    public function update($maPN) {
        try {
            // Xác thực người dùng
            $userData = $this->authMiddleware->authenticate();

            // Lấy dữ liệu từ body request
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                throw new Exception("Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.");
            }

            // Gọi model để cập nhật phiếu nhập
            $result = $this->phieuNhapModel->update($maPN, $data);

            // Trả về response
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'MaPN' => $result['id'],
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

    public function getDetail($maPhieuNhap) {
        try {
            $this->authMiddleware->authenticate();
            require_once __DIR__ . '/../models/ChiTietPhieuNhap.php';
            require_once __DIR__ . '/../models/SeriSanPham.php';
            $phieuNhap = $this->phieuNhapModel->getById($maPhieuNhap);
            if (!$phieuNhap) {
                throw new Exception("Không tìm thấy phiếu nhập");
            }
            $chiTietModel = new ChiTietPhieuNhap();
            $seriModel = new SeriSanPham();
            $chiTietList = $chiTietModel->getByMaPhieuNhap($maPhieuNhap);
            // Lấy danh sách seri cho từng sản phẩm trong chi tiết
            foreach ($chiTietList as &$ct) {
                $ct['DanhSachSeri'] = $this->getSeriByMaSP($ct['MaSP']);
            }
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'phieuNhap' => $phieuNhap,
                    'chiTiet' => $chiTietList
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

    private function getSeriByMaSP($maSP) {
        $db = new \Database();
        $conn = $db->getConnection();
        $query = "SELECT SoSeri FROM serisanpham WHERE MaSP = :MaSP";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":MaSP", $maSP);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $result;
    }

    private function validateCreateData($data) {
        // Kiểm tra MaNCC
        if (!isset($data['MaNCC'])) {
            throw new Exception("Mã nhà cung cấp không được để trống.");
        }
    }
}