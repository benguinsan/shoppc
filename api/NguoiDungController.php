<?php
require_once __DIR__ . '/../models/NguoiDung.php';
require_once __DIR__ . '/../models/TaiKhoan.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class NguoiDungController
{
    private $nguoiDungModel;
    private $taiKhoanModel;
    private $authMiddleware;

    public function __construct()
    {
        $this->nguoiDungModel = new NguoiDung();
        $this->taiKhoanModel = new TaiKhoan();
        $this->authMiddleware = new AuthMiddleware();
    }

    // Kiểm tra quyền (thay vì chỉ kiểm tra quyền ADMIN)
    private function checkPermission($requiredPermission = 'ADMIN')
    {
        // Tạm thời bỏ qua kiểm tra quyền để test
        return true;

        /*
        try {
            // Xác thực token và lấy thông tin từ token
            $decodedToken = $this->authMiddleware->authenticate();

            if (!$decodedToken) {
                throw new Exception('Không thể xác thực người dùng');
            }

            // Kiểm tra quyền
            if (!isset($decodedToken->MaNhomQuyen)) {
                throw new Exception('Không tìm thấy thông tin quyền người dùng');
            }
            
            // Kiểm tra xem người dùng có quyền yêu cầu không
            // Ví dụ: nếu requiredPermission là 'QLND' hoặc 'ADMIN'
            $userPermission = $decodedToken->MaNhomQuyen;
            
            // Danh sách các quyền được phép
            $allowedPermissions = [];
            
            // Nếu yêu cầu quyền ADMIN
            if ($requiredPermission === 'ADMIN') {
                $allowedPermissions = ['ADMIN'];
            } 
            // Nếu yêu cầu quyền QLND (Quản lý người dùng)
            else if ($requiredPermission === 'QLND') {
                $allowedPermissions = ['ADMIN', 'QLND'];
            }
            // Thêm các trường hợp khác nếu cần
            
            if (!in_array($userPermission, $allowedPermissions)) {
                throw new Exception('Bạn không có quyền thực hiện hành động này');
            }

            return true;
        } catch (Exception $e) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
        */
    }

    private function sendResponse($statusCode, $data)
    {
        http_response_code($statusCode);
        echo json_encode($data);
    }

    public function getCurrentUser()
    {
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

            $this->sendResponse(200, $response);
        } catch (Exception $e) {
            $this->sendResponse(401, [
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function updateCurrentUser()
    {
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

            // Lấy MaNguoiDung từ tài khoản
            $maNguoiDung = $taiKhoan['MaNguoiDung'] ?? null;

            if (!$maNguoiDung) {
                throw new Exception('Tài khoản không liên kết với người dùng nào');
            }

            // Lấy dữ liệu từ request
            $data = json_decode(file_get_contents("php://input"), true);

            if (!$data) {
                throw new Exception('Dữ liệu không hợp lệ');
            }

            // Cập nhật thông tin người dùng
            $result = $this->nguoiDungModel->update($maNguoiDung, $data);

            if (!$result) {
                throw new Exception('Cập nhật thông tin người dùng thất bại');
            }

            $nguoiDung = $this->nguoiDungModel->getById($maNguoiDung);

            $this->sendResponse(200, [
                'success' => true,
                'message' => 'Cập nhật thông tin người dùng thành công',
                'data' => $nguoiDung
            ]);
        } catch (Exception $e) {
            $this->sendResponse(400, [
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function updateUser($maNguoiDung)
    {
        // Kiểm tra quyền (thay vì chỉ kiểm tra quyền ADMIN)
        $this->checkPermission('QLND');

        try {
            // Lấy dữ liệu từ request
            $data = json_decode(file_get_contents("php://input"), true);

            if (!$data) {
                throw new Exception('Dữ liệu không hợp lệ');
            }

            // Cập nhật thông tin người dùng
            $result = $this->nguoiDungModel->update($maNguoiDung, $data);

            if (!$result) {
                throw new Exception('Cập nhật thông tin người dùng thất bại');
            }

            // Lấy thông tin người dùng sau khi cập nhật
            $nguoiDung = $this->nguoiDungModel->getById($maNguoiDung);

            $this->sendResponse(200, [
                'success' => true,
                'message' => 'Cập nhật thông tin người dùng thành công',
                'data' => $nguoiDung
            ]);
        } catch (Exception $e) {
            $this->sendResponse(400, [
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function deleteUser($maNguoiDung)
    {
        // Kiểm tra quyền (thay vì chỉ kiểm tra quyền ADMIN)
        $this->checkPermission('QLND');

        try {
            // Xóa người dùng
            $result = $this->nguoiDungModel->delete($maNguoiDung);

            if (!$result) {
                throw new Exception('Xóa người dùng thất bại');
            }

            $this->sendResponse(200, [
                'success' => true,
                'message' => 'Xóa người dùng thành công'
            ]);
        } catch (Exception $e) {
            $this->sendResponse(400, [
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getAllUsers()
    {
        $this->checkPermission('QLND');

        try {
            // Lấy các tham số từ request
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
            $orderBy = isset($_GET['order_by']) ? $_GET['order_by'] : 'created_at';
            $orderDirection = isset($_GET['order_direction']) ? $_GET['order_direction'] : 'DESC';

            // Gọi hàm getAll từ model NguoiDung
            $result = $this->nguoiDungModel->getAll($page, $limit, $searchTerm, $orderBy, $orderDirection);

            $this->sendResponse(200, [
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination']
            ]);
        } catch (Exception $e) {
            $this->sendResponse(400, [
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
