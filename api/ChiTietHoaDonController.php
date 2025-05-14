<?php
require_once __DIR__ . '/../models/ChiTietHoaDon.php';
require_once __DIR__ . '/../models/HoaDon.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class ChiTietHoaDonController {
    private $chiTietHoaDonModel;
    private $hoaDonModel;
    private $authMiddleware;

    public function __construct() {
        $this->chiTietHoaDonModel = new ChiTietHoaDon();
        $this->hoaDonModel = new HoaDon();
        $this->authMiddleware = new AuthMiddleware();
    }

    public function getAll() {
        try {
            // Xác thực người dùng
            $this->authMiddleware->authenticate();
            
            // Gọi model để lấy dữ liệu
            $chiTietList = $this->chiTietHoaDonModel->getAll();

            // Format dữ liệu trả về
            $formattedRecords = array_map(function($record) {
                $formattedRecord = [
                    'MaCTHD' => $record['MaCTHD'],
                    'MaHD' => $record['MaHD'],
                    'MaSP' => $record['MaSP'],
                    'TenSP' => $record['TenSP'] ?? 'Không xác định',
                    'MaSeri' => $record['MaSeri'],
                    'DonGia' => (float)$record['DonGia'],
                    'NgayLap' => $record['NgayLap'],
                    'TrangThai' => (int)$record['TrangThai']
                ];
                
                if (isset($record['TrangThai'])) {
                    $formattedRecord['TrangThaiText'] = $this->hoaDonModel->getTrangThaiText($record['TrangThai']);
                }
                
                return $formattedRecord;
            }, $chiTietList);

            // Trả về response
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

    public function getByMaHD($maHD) {
        try {
            // Xác thực người dùng
            $this->authMiddleware->authenticate();
            
            // Kiểm tra hóa đơn có tồn tại không
            $hoaDon = $this->hoaDonModel->getById($maHD);
            
            if (!$hoaDon) {
                throw new Exception("Hóa đơn với mã {$maHD} không tồn tại.");
            }
            
            // Gọi model để lấy dữ liệu
            $chiTietList = $this->chiTietHoaDonModel->getByMaHD($maHD);

            // Format dữ liệu trả về
            $formattedRecords = array_map(function($record) {
                return [
                    'MaCTHD' => $record['MaCTHD'],
                    'MaHD' => $record['MaHD'],
                    'MaSP' => $record['MaSP'],
                    'TenSP' => $record['TenSP'] ?? 'Không xác định',
                    'MaSeri' => $record['MaSeri'],
                    'DonGia' => (float)$record['DonGia']
                ];
            }, $chiTietList);

            // Trả về response
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
            // Xác thực người dùng
            $userData = $this->authMiddleware->authenticate();
            
            // Lấy dữ liệu từ body request
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                throw new Exception("Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.");
            }
            
            // Validate dữ liệu (bỏ qua MaSeri)
            $this->validateCreateDataNoSeri($data);
            
            // Lấy seri còn trống từ bảng serisanpham
            require_once __DIR__ . '/../models/SeriSanPham.php';
            $seriModel = new SeriSanPham();
            $seriConTrong = $seriModel->getSeriConTrongByMaSP($data['MaSP']);
            if (!$seriConTrong) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Sản phẩm này đã hết hàng (không còn seri trống)!'
                ]);
                return;
            }
            $data['MaSeri'] = $seriConTrong['MaSeri'];
            
            // Gọi model để tạo chi tiết hóa đơn
            $result = $this->chiTietHoaDonModel->create($data);
            
            // Cập nhật trạng thái seri thành đã bán (1)
            if (method_exists($seriModel, 'updateTrangThaiSeriByMaSeri')) {
                $seriModel->updateTrangThaiSeriByMaSeri($seriConTrong['MaSeri'], 1);
            } else {
                // Nếu chưa có hàm, cập nhật trực tiếp
                $updateSeriQuery = "UPDATE serisanpham SET TrangThai = 1 WHERE MaSeri = :maSeri";
                $db = new \Database();
                $conn = $db->getConnection();
                $conn->prepare($updateSeriQuery)->execute([':maSeri' => $seriConTrong['MaSeri']]);
            }
            
            // Trả về response
            http_response_code(201);
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'MaCTHD' => $result['id'],
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
    
    public function update($maCTHD) {
        try {
            // Xác thực người dùng
            $userData = $this->authMiddleware->authenticate();
            
            // Lấy dữ liệu từ body request
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                throw new Exception("Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.");
            }
            
            // Validate dữ liệu
            $this->validateUpdateData($data);
            
            // Gọi model để cập nhật chi tiết hóa đơn
            $result = $this->chiTietHoaDonModel->update($maCTHD, $data);
            
            // Trả về response
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'MaCTHD' => $result['id'],
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
        // Kiểm tra có ít nhất một trường được cập nhật
        $allowedFields = ['MaSP', 'MaSeri', 'DonGia'];
        $hasUpdate = false;
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $hasUpdate = true;
                
                // Kiểm tra DonGia nếu được cung cấp
                if ($field === 'DonGia' && (!is_numeric($data['DonGia']) || (float)$data['DonGia'] <= 0)) {
                    throw new Exception("Đơn giá phải là số dương.");
                }
            }
        }
        
        if (!$hasUpdate) {
            throw new Exception("Không có thông tin nào được cập nhật.");
        }
    }
    
    private function validateCreateDataNoSeri($data) {
        $requiredFields = ['MaHD', 'MaSP', 'DonGia'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Trường {$field} là bắt buộc.");
            }
        }
        if (!is_numeric($data['DonGia']) || (float)$data['DonGia'] <= 0) {
            throw new Exception("Đơn giá phải là số dương.");
        }
    }
} 