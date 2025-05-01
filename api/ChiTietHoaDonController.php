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
} 