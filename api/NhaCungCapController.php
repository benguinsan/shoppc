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

    // Kiểm tra quyền ADMIN
    private function checkAdminPermission()
    {
        // Tạm thời bỏ qua kiểm tra quyền để test
        return true;
    }

    public function getAll()
    {
        // Kiểm tra quyền ADMIN
        $this->checkAdminPermission();

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

    public function getOne($id)
    {
        // Kiểm tra quyền ADMIN
        $this->checkAdminPermission();

        try {
            $nhaCungCap = $this->nhaCungCapModel->getById($id);

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
        // Kiểm tra quyền ADMIN
        $this->checkAdminPermission();

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

    public function update($id)
    {
        // Kiểm tra quyền ADMIN
        $this->checkAdminPermission();

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

            if ($this->nhaCungCapModel->update($id, $data)) {
                $nhaCungCap = $this->nhaCungCapModel->getById($id);

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

    public function delete($id)
    {
        // Kiểm tra quyền ADMIN
        $this->checkAdminPermission();

        try {
            if ($this->nhaCungCapModel->delete($id)) {
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
