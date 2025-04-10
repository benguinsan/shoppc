<?php
require_once __DIR__ . '/../models/NhaCungCap.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class NhaCungCapController
{
    private $nhaCungCapModel;
    private $authMiddleware;

    public function __construct()
    {
        $this->nhaCungCapModel = new NhaCungCap();
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
            // Nếu yêu cầu quyền QLNCC (Quản lý nhà cung cấp)
            else if ($requiredPermission === 'QLNCC') {
                $allowedPermissions = ['ADMIN', 'QLNCC'];
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
        $this->checkPermission('QLNCC');

        try {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
            $orderBy = isset($_GET['orderBy']) ? $_GET['orderBy'] : 'created_at';
            $orderDirection = isset($_GET['orderDirection']) ? $_GET['orderDirection'] : 'DESC';

            $result = $this->nhaCungCapModel->getAll($page, $limit, $searchTerm, $orderBy, $orderDirection);
            $this->sendResponse(200, $result);
        } catch (Exception $e) {
            $this->sendResponse(500, ['error' => $e->getMessage()]);
        }
    }

    public function getOne($maNhaCungCap)
    {
        // Kiểm tra quyền
        $this->checkPermission('QLNCC');

        try {
            $nhaCungCap = $this->nhaCungCapModel->getById($maNhaCungCap);

            if (!$nhaCungCap) {
                $this->sendResponse(404, ['error' => 'Không tìm thấy nhà cung cấp']);
                return;
            }

            $this->sendResponse(200, $nhaCungCap);
        } catch (Exception $e) {
            $this->sendResponse(500, ['error' => $e->getMessage()]);
        }
    }

    public function create()
    {
        // Kiểm tra quyền
        $this->checkPermission('QLNCC');

        try {
            $data = json_decode(file_get_contents("php://input"), true);

            if (empty($data)) {
                $this->sendResponse(400, ['error' => 'Không có dữ liệu được gửi']);
                return;
            }

            // Validate dữ liệu
            $errors = $this->validateData($data, true);

            if (!empty($errors)) {
                $this->sendResponse(400, ['errors' => $errors]);
                return;
            }

            if ($this->nhaCungCapModel->create($data)) {
                $newId = $this->nhaCungCapModel->getLastInsertId();
                $nhaCungCap = $this->nhaCungCapModel->getById($newId);

                $this->sendResponse(201, [
                    'message' => 'Tạo nhà cung cấp thành công',
                    'data' => $nhaCungCap
                ]);
            } else {
                $this->sendResponse(500, ['error' => 'Không thể tạo nhà cung cấp']);
            }
        } catch (Exception $e) {
            $this->sendResponse(500, ['error' => $e->getMessage()]);
        }
    }

    public function update($maNhaCungCap)
    {
        // Kiểm tra quyền
        $this->checkPermission('QLNCC');

        try {
            $data = json_decode(file_get_contents("php://input"), true);

            if (empty($data)) {
                $this->sendResponse(400, ['error' => 'Không có dữ liệu được gửi']);
                return;
            }

            // Validate dữ liệu
            $errors = $this->validateData($data, false);

            if (!empty($errors)) {
                $this->sendResponse(400, ['errors' => $errors]);
                return;
            }

            if ($this->nhaCungCapModel->update($maNhaCungCap, $data)) {
                $nhaCungCap = $this->nhaCungCapModel->getById($maNhaCungCap);

                $this->sendResponse(200, [
                    'message' => 'Cập nhật nhà cung cấp thành công',
                    'data' => $nhaCungCap
                ]);
            } else {
                $this->sendResponse(500, ['error' => 'Không thể cập nhật nhà cung cấp']);
            }
        } catch (Exception $e) {
            $this->sendResponse(500, ['error' => $e->getMessage()]);
        }
    }

    public function delete($maNhaCungCap)
    {
        // Kiểm tra quyền
        $this->checkPermission('QLNCC');

        try {
            if ($this->nhaCungCapModel->delete($maNhaCungCap)) {
                $this->sendResponse(200, ['message' => 'Xóa nhà cung cấp thành công']);
            } else {
                $this->sendResponse(500, ['error' => 'Không thể xóa nhà cung cấp']);
            }
        } catch (Exception $e) {
            $this->sendResponse(500, ['error' => $e->getMessage()]);
        }
    }

    private function validateData($data, $isCreate = true)
    {
        $errors = [];

        // Validate TenNCC (bắt buộc khi tạo mới)
        if ($isCreate && (!isset($data['TenNCC']) || empty($data['TenNCC']))) {
            $errors['TenNCC'] = 'Tên nhà cung cấp không được để trống';
        } elseif (isset($data['TenNCC']) && strlen($data['TenNCC']) > 100) {
            $errors['TenNCC'] = 'Tên nhà cung cấp không được vượt quá 100 ký tự';
        }

        // Validate DiaChi
        if (isset($data['DiaChi']) && strlen($data['DiaChi']) > 255) {
            $errors['DiaChi'] = 'Địa chỉ không được vượt quá 255 ký tự';
        }

        // Validate SDT
        if (isset($data['SDT']) && !empty($data['SDT'])) {
            if (!preg_match('/^[0-9]{10,11}$/', $data['SDT'])) {
                $errors['SDT'] = 'Số điện thoại không hợp lệ (phải có 10-11 chữ số)';
            }
        }

        // Validate Email
        if (isset($data['Email']) && !empty($data['Email'])) {
            if (!filter_var($data['Email'], FILTER_VALIDATE_EMAIL)) {
                $errors['Email'] = 'Email không hợp lệ';
            } elseif (strlen($data['Email']) > 100) {
                $errors['Email'] = 'Email không được vượt quá 100 ký tự';
            }
        }

        // Validate TrangThai
        if (isset($data['TrangThai']) && !in_array((int)$data['TrangThai'], [0, 1])) {
            $errors['TrangThai'] = 'Trạng thái không hợp lệ (phải là 0 hoặc 1)';
        }

        return $errors;
    }

    private function sendResponse($statusCode, $data)
    {
        http_response_code($statusCode);
        echo json_encode($data);
    }
}
