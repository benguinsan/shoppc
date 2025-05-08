<?php
require_once __DIR__ . '/../models/ChucNang.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class ChucNangController
{
    private $chucNangModel;
    private $authMiddleware;

    public function __construct()
    {
        $this->chucNangModel = new ChucNang();
        $this->authMiddleware = new AuthMiddleware();
    }

    // Kiểm tra quyền
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
            $userPermission = $decodedToken->MaNhomQuyen;
            
            // Danh sách các quyền được phép
            $allowedPermissions = [];
            
            // Nếu yêu cầu quyền ADMIN
            if ($requiredPermission === 'ADMIN') {
                $allowedPermissions = ['ADMIN'];
            } 
            // Nếu yêu cầu quyền QLCN (Quản lý chức năng)
            else if ($requiredPermission === 'QLCN') {
                $allowedPermissions = ['ADMIN', 'QLCN'];
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

    public function getAll()
    {
        // Kiểm tra quyền
        $this->checkPermission();

        try {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
            $orderBy = isset($_GET['orderBy']) ? $_GET['orderBy'] : 'created_at';
            $orderDirection = isset($_GET['orderDirection']) ? $_GET['orderDirection'] : 'DESC';

            $result = $this->chucNangModel->getAll($page, $limit, $searchTerm, $orderBy, $orderDirection);
            $this->sendResponse(200, $result);
        } catch (Exception $e) {
            $this->sendResponse(500, ['error' => $e->getMessage()]);
        }
    }

    public function getOne($maChucNang)
    {
        // Kiểm tra quyền
        $this->checkPermission();

        try {
            $chucNang = $this->chucNangModel->getById($maChucNang);

            if ($chucNang) {
                $this->sendResponse(200, $chucNang);
            } else {
                $this->sendResponse(404, ['error' => 'Không tìm thấy chức năng']);
            }
        } catch (Exception $e) {
            $this->sendResponse(500, ['error' => $e->getMessage()]);
        }
    }

    public function create()
    {
        // Kiểm tra quyền
        $this->checkPermission();

        try {
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['TenChucNang']) || empty($data['TenChucNang'])) {
                $this->sendResponse(400, ['error' => 'Tên chức năng không được để trống']);
                return;
            }

            if ($this->chucNangModel->create($data)) {
                $maChucNang = $this->chucNangModel->getLastInsertId();
                $chucNang = $this->chucNangModel->getById($maChucNang);
                $this->sendResponse(201, [
                    'message' => 'Tạo chức năng thành công',
                    'data' => $chucNang
                ]);
            } else {
                $this->sendResponse(500, ['error' => 'Không thể tạo chức năng']);
            }
        } catch (Exception $e) {
            $this->sendResponse(500, ['error' => $e->getMessage()]);
        }
    }

    public function update($maChucNang)
    {
        // Kiểm tra quyền
        $this->checkPermission();

        try {
            $data = json_decode(file_get_contents("php://input"), true);

            if (empty($data)) {
                $this->sendResponse(400, ['error' => 'Không có dữ liệu để cập nhật']);
                return;
            }

            if ($this->chucNangModel->update($maChucNang, $data)) {
                $chucNang = $this->chucNangModel->getById($maChucNang);
                $this->sendResponse(200, [
                    'message' => 'Cập nhật chức năng thành công',
                    'data' => $chucNang
                ]);
            } else {
                $this->sendResponse(500, ['error' => 'Không thể cập nhật chức năng']);
            }
        } catch (Exception $e) {
            $this->sendResponse(500, ['error' => $e->getMessage()]);
        }
    }

    public function delete($maChucNang)
    {
        // Kiểm tra quyền
        $this->checkPermission();

        try {
            if ($this->chucNangModel->delete($maChucNang)) {
                $this->sendResponse(200, ['message' => 'Xóa chức năng thành công']);
            } else {
                $this->sendResponse(500, ['error' => 'Không thể xóa chức năng']);
            }
        } catch (Exception $e) {
            $this->sendResponse(500, ['error' => $e->getMessage()]);
        }
    }

    private function sendResponse($statusCode, $data)
    {
        http_response_code($statusCode);
        echo json_encode($data);
    }
}
