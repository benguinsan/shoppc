<?php
require_once __DIR__ . '/../../models/Taikhoan.php';
require_once __DIR__ . '/../../Middleware/AuthMiddleware.php';

class TaiKhoanController
{
    private $taikhoanModel;
    private $authMiddleware;

    public function __construct()
    {
        $this->taikhoanModel = new Taikhoan();
        $this->authMiddleware = new AuthMiddleware();
    }

    // Kiểm tra quyền
    private function checkPermission($requiredPermission = 'ADMIN')
    {
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
            $userPermission = $decodedToken->MaNhomQuyen;

            // Danh sách các quyền được phép
            $allowedPermissions = [];

            // Nếu yêu cầu quyền ADMIN
            if ($requiredPermission === 'ADMIN') {
                $allowedPermissions = ['ADMIN'];
            }
            // Nếu yêu cầu quyền QLTK (Quản lý tài khoản)
            else if ($requiredPermission === 'QLTK') {
                $allowedPermissions = ['ADMIN', 'QLTK'];
            }
            // Thêm các trường hợp khác nếu cần

            if (!in_array($userPermission, $allowedPermissions)) {
                throw new Exception('Bạn không có quyền thực hiện hành động này');
            }

            return $decodedToken;
        } catch (Exception $e) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    private function sendResponse($statusCode, $data)
    {
        http_response_code($statusCode);
        echo json_encode($data);
    }

    // Lấy danh sách tất cả tài khoản (chỉ admin)
    public function getAllAccounts()
    {
        // Kiểm tra quyền ADMIN hoặc QLTK
        $this->checkPermission('QLTK');

        try {
            // Lấy các tham số từ request
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
            $orderBy = isset($_GET['order_by']) ? $_GET['order_by'] : 'MaTK';
            $orderDirection = isset($_GET['order_direction']) ? $_GET['order_direction'] : 'ASC';

            // Gọi phương thức getAllTaiKhoan với các tham số phân trang
            $result = $this->taikhoanModel->getAllTaiKhoan(
                $page,
                $limit,
                $searchTerm,
                $orderBy,
                $orderDirection
            );

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

    // Lấy thông tin tài khoản theo ID
    public function getAccountById($maTaiKhoan)
    {
        try {
            // Xác thực token và lấy thông tin từ token
            $decodedToken = $this->authMiddleware->authenticate();

            if (!$decodedToken) {
                throw new Exception('Không thể xác thực người dùng');
            }

            // Kiểm tra quyền: chỉ admin/QLTK hoặc chính chủ tài khoản mới có quyền xem
            if (
                $decodedToken->MaTK !== $maTaiKhoan &&
                !in_array($decodedToken->MaNhomQuyen, ['ADMIN', 'QLTK'])
            ) {
                throw new Exception('Bạn không có quyền xem thông tin tài khoản này');
            }

            $account = $this->taikhoanModel->getById($maTaiKhoan);

            if (!$account) {
                throw new Exception("Không tìm thấy tài khoản");
            }

            // Loại bỏ mật khẩu trước khi trả về
            unset($account['MatKhau']);

            $this->sendResponse(200, [
                'success' => true,
                'data' => $account
            ]);
        } catch (Exception $e) {
            $this->sendResponse(400, [
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    // Cập nhật thông tin tài khoản (chỉ admin)
    public function updateAccount($maTaiKhoan)
    {
        // Kiểm tra quyền ADMIN hoặc QLTK
        $this->checkPermission('QLTK');

        try {
            // Lấy dữ liệu từ request
            $data = json_decode(file_get_contents("php://input"), true);

            if (!$data) {
                throw new Exception('Dữ liệu không hợp lệ');
            }

            // Cập nhật thông tin tài khoản
            $accountData = [];
            if (isset($data['MaNhomQuyen'])) $accountData['MaNhomQuyen'] = $data['MaNhomQuyen'];
            if (isset($data['TrangThai'])) $accountData['TrangThai'] = (int)$data['TrangThai'];
            if (isset($data['MatKhau']) && !empty($data['MatKhau'])) $accountData['MatKhau'] = $data['MatKhau'];

            if (!empty($accountData)) {
                $this->taikhoanModel->update($maTaiKhoan, $accountData);
            }

            // Lấy thông tin tài khoản sau khi cập nhật
            $account = $this->taikhoanModel->getById($maTaiKhoan);

            if (!$account) {
                throw new Exception("Không tìm thấy tài khoản");
            }

            // Loại bỏ mật khẩu trước khi trả về
            unset($account['MatKhau']);

            $this->sendResponse(200, [
                'success' => true,
                'message' => 'Cập nhật tài khoản thành công',
                'data' => $account
            ]);
        } catch (Exception $e) {
            $this->sendResponse(400, [
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    // Cập nhật mật khẩu tài khoản (người dùng chỉ có thể cập nhật mật khẩu của chính mình)
    public function updatePassword()
    {
        try {
            // Xác thực token và lấy thông tin từ token
            $decodedToken = $this->authMiddleware->authenticate();
            if (!$decodedToken) {
                throw new Exception('Không thể xác thực người dùng');
            }

            // Lấy MaTK từ token (chỉ cho phép cập nhật mật khẩu của chính mình)
            $maTaiKhoan = $decodedToken->MaTK;

            // Kiểm tra xem người dùng có quyền admin hoặc quản lý tài khoản không
            $isAdmin = in_array($decodedToken->MaNhomQuyen, ['ADMIN', 'QLTK']);

            // Lấy dữ liệu từ request
            $data = json_decode(file_get_contents("php://input"), true);

            if (!$data || !isset($data['MatKhau']) || empty($data['MatKhau'])) {
                throw new Exception('Mật khẩu mới là bắt buộc');
            }

            // Nếu không phải admin, yêu cầu mật khẩu cũ
            if (!$isAdmin) {
                if (!isset($data['MatKhauCu']) || empty($data['MatKhauCu'])) {
                    throw new Exception('Mật khẩu cũ là bắt buộc');
                }

                // Cập nhật mật khẩu với xác thực mật khẩu cũ
                $this->taikhoanModel->updatePassword($maTaiKhoan, $data['MatKhau'], $data['MatKhauCu'], false);
            } else {
                // Admin không cần mật khẩu cũ
                $this->taikhoanModel->updatePassword($maTaiKhoan, $data['MatKhau'], null, true);
            }

            $this->sendResponse(200, [
                'success' => true,
                'message' => 'Cập nhật mật khẩu thành công'
            ]);
        } catch (Exception $e) {
            $this->sendResponse(400, [
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    // Vô hiệu hóa tài khoản của chính mình (người dùng có thể vô hiệu hóa tài khoản của chính mình)
    public function deactivateOwnAccount()
    {
        try {
            // Xác thực token và lấy thông tin từ token
            $decodedToken = $this->authMiddleware->authenticate();
            if (!$decodedToken) {
                throw new Exception('Không thể xác thực người dùng');
            }

            // Lấy MaTK từ token (chỉ cho phép vô hiệu hóa tài khoản của chính mình)
            $maTaiKhoan = $decodedToken->MaTK;

            // Vô hiệu hóa tài khoản (TrangThai = 0)
            $this->taikhoanModel->updateStatus($maTaiKhoan, 0);

            $this->sendResponse(200, [
                'success' => true,
                'message' => 'Tài khoản đã được vô hiệu hóa'
            ]);
        } catch (Exception $e) {
            $this->sendResponse(400, [
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    // Cập nhật nhóm quyền tài khoản (chỉ admin và nhóm quyền có thẩm quyền)
    public function updateAccountRole($maTaiKhoan)
    {
        // Kiểm tra quyền ADMIN hoặc QLTK
        $this->checkPermission('QLTK');

        try {
            // Lấy dữ liệu từ request
            $data = json_decode(file_get_contents("php://input"), true);

            if (!$data || !isset($data['MaNhomQuyen']) || empty($data['MaNhomQuyen'])) {
                throw new Exception('Mã nhóm quyền là bắt buộc');
            }

            // Cập nhật nhóm quyền tài khoản
            $this->taikhoanModel->updateRole($maTaiKhoan, $data['MaNhomQuyen']);

            $this->sendResponse(200, [
                'success' => true,
                'message' => 'Cập nhật nhóm quyền tài khoản thành công'
            ]);
        } catch (Exception $e) {
            $this->sendResponse(400, [
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
