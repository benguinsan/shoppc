<?php
require_once __DIR__ . '/../models/NguoiDung.php';
require_once __DIR__ . '/../models/TaiKhoan.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class NguoiDungController {
    private $nguoiDungModel;
    private $taiKhoanModel;
    private $authMiddleware;

    public function __construct()
    {
        $this->nguoiDungModel = new NguoiDung();
        $this->taiKhoanModel = new TaiKhoan();
        $this->authMiddleware = new AuthMiddleware();
    }

    public function getCurrentUser() {
        try {
            // Xác thực token và lấy thông tin từ token
            $decodedToken = $this->authMiddleware->authenticate();
            
            if (!$decodedToken) {
                throw new Exception('Không thể xác thực người dùng');
            }
            
            // Lấy MaTK từ token đã giải mã
            $maTK = $decodedToken->MaTK;
            
            // Lấy thông tin tài khoản
            $taiKhoan = $this->taiKhoanModel->getById($maTK);
            
            if (!$taiKhoan) {
                throw new Exception('Không tìm thấy thông tin tài khoản');
            }
            
            // Loại bỏ thông tin nhạy cảm
            unset($taiKhoan['MatKhau']);
            
            // Lấy MaNguoiDung từ tài khoản
            $maNguoiDung = $taiKhoan['MaNguoiDung'] ?? null;
            
            // Chuẩn bị dữ liệu phản hồi
            $response = [
                'success' => true,
                'data' => [
                    'taiKhoan' => $taiKhoan
                ]
            ];
            
            // Thêm thông tin người dùng nếu có MaNguoiDung
            if ($maNguoiDung) {
                $nguoiDung = $this->nguoiDungModel->getById($maNguoiDung);
                if ($nguoiDung) {
                    $response['data']['nguoiDung'] = $nguoiDung;
                }
            }
            
            http_response_code(200);
            echo json_encode($response);
            
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}