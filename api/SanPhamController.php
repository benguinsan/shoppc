<?php

require_once __DIR__ . '/../models/SanPham.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class SanPhamController
{
    private $sanPhamModel;
    private $authMiddleware;

    public function __construct()
    {
        $this->sanPhamModel = new SanPham();
        $this->authMiddleware = new AuthMiddleware();
    }

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

    public function getBannerProduct()
    {
        try {
            $result = $this->sanPhamModel->getBanner();

            echo json_encode([
                'success' => true,
                'data' => $result,
                'message' => 'Lấy banner sản phẩm thành công'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function getFilterProduct()
    {
        try {
            // Get pagination parameters from query string
            $pageNo = isset($_GET['page']) ? (int)$_GET['page'] : 0;
            $pageSize = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;

            // Get filter parameters from query string
            $filter = [
                'MaLoaiSP' => $_GET['MaLoaiSP'] ?? '',
                'RAM' => $_GET['RAM'] ?? '',
                'min_price' => $_GET['min_price'] ?? '',
                'max_price' => $_GET['max_price'] ?? ''
            ];

            // Remove empty filters
            $filter = array_filter($filter, function ($value) {
                return $value !== '';
            });

            // Adjust page number (API uses 0-based, model uses 1-based)
            $page = $pageNo + 1;

            $result = $this->sanPhamModel->getFilter($filter, $page, $pageSize);

            echo json_encode([
                'dataSource' => $result['data'],
                'pageNo' => $pageNo,
                'pageSize' => $pageSize,
                'totalElements' => $result['pagination']['total'],
                'filters_applied' => $result['filters_applied']
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }


    public function getAllByPage()
    {
        $pageNo = isset($_GET['page']) ? (int)$_GET['page'] : 0;
        $pageSize = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;

        // Tăng pageNo lên 1 vì hàm getAll đang tính từ page = 1
        $page = $pageNo + 1;

        try {
            $result = $this->sanPhamModel->getAllByPage($page, $pageSize);

            echo json_encode([
                'dataSource' => $result['data'],
                'pageNo' => $pageNo,
                'pageSize' => $pageSize,
                'totalElements' => $result['pagination']['total']
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'error' => true,
                'message' => $e->getMessage()
            ]);
        }
    }
    public function createSanPham()
    {
        $this->checkPermission('THEMSP');
        //Lấy dữ liệu từ json 
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data)) {
            echo json_encode([
                'success' => false,
                'message' => 'Không có dữ liệu để tạo sản phẩm'
            ]);
            return;
        }

        try {
            $result = $this->sanPhamModel->createSanpham($data);
            echo json_encode([
                'success' => true,
                'message' => 'Tạo sản phẩm thành công',
                'data' => $result
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function capnhatSanPham()
    {
        $this->checkPermission('SUASP');
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data) || !isset($data['MaSP'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Thiếu mã sản phẩm'
            ]);
            return;
        }
        try {
            $result = $this->sanPhamModel->editSanpham($data); // Pass MaSP instead of entire data array
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'data' => $result
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    public function changeStatus()
    {
        $this->checkPermission('XOASP');

        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data) || !isset($data['MaSP'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Thiếu mã sản phẩm'
            ]);
            return;
        }

        try {
            $result = $this->sanPhamModel->changeStatus($data['MaSP']); // Pass MaSP instead of entire data array
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'data' => $result
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
