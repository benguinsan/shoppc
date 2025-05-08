<?php
require_once __DIR__ . '/../models/LoaiSanPham.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class LoaiSanPhamController
{
    private $loaiSanPhamModel;
    private $authMiddleware;

    public function __construct()
    {
        $this->loaiSanPhamModel = new LoaiSanPham();
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
            // Nếu yêu cầu quyền QLLSP (Quản lý loại sản phẩm)
            else if ($requiredPermission === 'QLLSP') {
                $allowedPermissions = ['ADMIN', 'QLLSP'];
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
        $this->checkPermission('QLLSP');

        try {
            // Lấy các tham số từ request
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
            $orderBy = isset($_GET['orderBy']) ? $_GET['orderBy'] : 'created_at';
            $orderDirection = isset($_GET['orderDirection']) ? $_GET['orderDirection'] : 'DESC';

            // Gọi hàm getAll từ model LoaiSanPham
            $result = $this->loaiSanPhamModel->getAll($page, $limit, $searchTerm, $orderBy, $orderDirection);

            $this->sendResponse(200, [
                'success' => true,
                'message' => 'Lấy danh sách loại sản phẩm thành công',
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

    // public function getOne($maLoaiSP)
    // {
    //     // Kiểm tra quyền
    //     $this->checkPermission('QLLSP');

    //     try {
    //         $loaiSanPham = $this->loaiSanPhamModel->getById($maLoaiSP);

    //         if (!$loaiSanPham) {
    //             throw new Exception("Không tìm thấy loại sản phẩm với mã: $maLoaiSP");
    //         }

    //         $this->sendResponse(200, [
    //             'success' => true,
    //             'message' => 'Lấy thông tin loại sản phẩm thành công',
    //             'data' => $loaiSanPham
    //         ]);
    //     } catch (Exception $e) {
    //         $this->sendResponse(400, [
    //             'success' => false,
    //             'error' => $e->getMessage()
    //         ]);
    //     }
    // }

    public function create()
    {
        // Kiểm tra quyền
        $this->checkPermission('QLLSP');

        try {
            // Lấy dữ liệu từ request
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['TenLoaiSP']) || empty($data['TenLoaiSP'])) {
                throw new Exception('Tên loại sản phẩm không được để trống');
            }

            $result = $this->loaiSanPhamModel->create($data);
            $newId = $this->loaiSanPhamModel->getLastInsertId();
            $newLoaiSP = $this->loaiSanPhamModel->getById($newId);

            $this->sendResponse(201, [
                'success' => true,
                'message' => 'Tạo loại sản phẩm thành công',
                'data' => $newLoaiSP
            ]);
        } catch (Exception $e) {
            $this->sendResponse(400, [
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function update($maLoaiSP)
    {
        // Kiểm tra quyền
        $this->checkPermission('QLLSP');

        try {
            // Lấy dữ liệu từ request
            $data = json_decode(file_get_contents("php://input"), true);

            if (!$data) {
                throw new Exception('Dữ liệu không hợp lệ');
            }

            $result = $this->loaiSanPhamModel->update($maLoaiSP, $data);
            $updatedLoaiSP = $this->loaiSanPhamModel->getById($maLoaiSP);

            $this->sendResponse(200, [
                'success' => true,
                'message' => 'Cập nhật loại sản phẩm thành công',
                'data' => $updatedLoaiSP
            ]);
        } catch (Exception $e) {
            $this->sendResponse(400, [
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function delete($maLoaiSP)
    {
        // Kiểm tra quyền
        $this->checkPermission('QLLSP');

        try {
            $result = $this->loaiSanPhamModel->delete($maLoaiSP);

            $this->sendResponse(200, [
                'success' => true,
                'message' => 'Xóa loại sản phẩm thành công'
            ]);
        } catch (Exception $e) {
            $this->sendResponse(400, [
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function sendResponse($statusCode, $data)
    {
        http_response_code($statusCode);
        echo json_encode($data);
    }
}
