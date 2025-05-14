<?php
require_once __DIR__ . '/../models/NhomQuyen.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class NhomQuyenController
{
    private $nhomQuyenModel;
    private $authMiddleware;

    public function __construct()
    {
        $this->nhomQuyenModel = new NhomQuyen();
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
            // Nếu yêu cầu quyền QLNQ (Quản lý nhóm quyền)
            else if ($requiredPermission === 'QLNQ') {
                $allowedPermissions = ['ADMIN', 'QLNQ'];
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
            $pageNo = isset($_GET['page']) ? (int)$_GET['page'] : 0;
            $pageSize = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

            $page = $pageNo + 1;

            $searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
            $orderBy = isset($_GET['orderBy']) ? $_GET['orderBy'] : 'created_at';
            $orderDirection = isset($_GET['orderDirection']) ? $_GET['orderDirection'] : 'DESC';

            $result = $this->nhomQuyenModel->getAll($page, $pageSize, $searchTerm, $orderBy, $orderDirection);
            $this->sendResponse(200, [
                'dataSource' => $result['data'],
                'pageNo' => $pageNo,
                'pageSize' => $pageSize,
                'totalElements' => $result['pagination']['total']
            ]);
        } catch (Exception $e) {
            $this->sendResponse(500, ['error' => $e->getMessage()]);
        }
    }

    public function getOne($maNhomQuyen)
    {
        // Kiểm tra quyền
        $this->checkPermission();

        try {
            $nhomQuyen = $this->nhomQuyenModel->getById($maNhomQuyen);

            if ($nhomQuyen) {
                $this->sendResponse(200, $nhomQuyen);
            } else {
                $this->sendResponse(404, ['error' => 'Không tìm thấy nhóm quyền']);
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

            if (!isset($data['TenNhomQuyen']) || empty($data['TenNhomQuyen'])) {
                $this->sendResponse(400, ['error' => 'Tên nhóm quyền không được để trống']);
                return;
            }

            if ($this->nhomQuyenModel->create($data)) {
                $id = $this->nhomQuyenModel->getLastInsertId();
                $nhomQuyen = $this->nhomQuyenModel->getById($id);
                $this->sendResponse(201, [
                    'message' => 'Tạo nhóm quyền thành công',
                    'data' => $nhomQuyen
                ]);
            } else {
                $this->sendResponse(500, ['error' => 'Không thể tạo nhóm quyền']);
            }
        } catch (Exception $e) {
            $this->sendResponse(500, ['error' => $e->getMessage()]);
        }
    }

    public function update($id)
    {
        // Kiểm tra quyền
        $this->checkPermission();

        try {
            $data = json_decode(file_get_contents("php://input"), true);

            if (empty($data)) {
                $this->sendResponse(400, ['error' => 'Không có dữ liệu để cập nhật']);
                return;
            }

            if ($this->nhomQuyenModel->update($id, $data)) {
                $nhomQuyen = $this->nhomQuyenModel->getById($id);
                $this->sendResponse(200, [
                    'message' => 'Cập nhật nhóm quyền thành công',
                    'data' => $nhomQuyen
                ]);
            } else {
                $this->sendResponse(500, ['error' => 'Không thể cập nhật nhóm quyền']);
            }
        } catch (Exception $e) {
            $this->sendResponse(500, ['error' => $e->getMessage()]);
        }
    }

    public function delete($id)
    {
        // Kiểm tra quyền
        $this->checkPermission();

        try {
            if ($this->nhomQuyenModel->delete($id)) {
                $this->sendResponse(200, ['message' => 'Xóa nhóm quyền thành công']);
            } else {
                $this->sendResponse(500, ['error' => 'Không thể xóa nhóm quyền']);
            }
        } catch (Exception $e) {
            $this->sendResponse(500, ['error' => $e->getMessage()]);
        }
    }

    public function getFunctions($id)
    {
        // Kiểm tra quyền
        $this->checkPermission();

        try {
            $functions = $this->nhomQuyenModel->getFunctionsByRoleId($id);
            $this->sendResponse(200, $functions);
        } catch (Exception $e) {
            $this->sendResponse(500, ['error' => $e->getMessage()]);
        }
    }

    public function updateFunctions($id)
    {
        // Kiểm tra quyền
        $this->checkPermission();

        try {
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['ChucNang']) || !is_array($data['ChucNang'])) {
                $this->sendResponse(400, ['error' => 'Dữ liệu chức năng không hợp lệ']);
                return;
            }

            if ($this->nhomQuyenModel->updateRoleFunctions($id, $data['ChucNang'])) {
                $this->sendResponse(200, ['message' => 'Cập nhật chức năng cho nhóm quyền thành công']);
            } else {
                $this->sendResponse(500, ['error' => 'Không thể cập nhật chức năng cho nhóm quyền']);
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
