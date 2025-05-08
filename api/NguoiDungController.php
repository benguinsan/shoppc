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

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Cập nhật thông tin người dùng thành công',
                'data' => $nguoiDung
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function updateUser($maNguoiDung)
    {
        try {
            // Xác thực token và kiểm tra quyền admin
            $decodedToken = $this->authMiddleware->authenticate();

            if (!$decodedToken) {
                throw new Exception('Không thể xác thực người dùng');
            }

            // Kiểm tra quyền admin (giả sử có trường Quyen trong token)
            // if (!isset($decodedToken->MaNhomQuyen) || $decodedToken->MaNhomQuyen !== 'ADMIN') {
            //     throw new Exception('Bạn không có quyền thực hiện hành động này');
            // }

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

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Cập nhật thông tin người dùng thành công',
                'data' => $nguoiDung
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function deleteUser($maNguoiDung)
    {
        try {
            // Xác thực token và kiểm tra quyền admin
            $decodedToken = $this->authMiddleware->authenticate();

            if (!$decodedToken) {
                throw new Exception('Không thể xác thực người dùng');
            }

            // Kiểm tra quyền admin (giả sử có trường Quyen trong token)
            if (!isset($decodedToken->MaNhomQuyen) || $decodedToken->MaNhomQuyen !== 'ADMIN') {
                throw new Exception('Bạn không có quyền thực hiện hành động này');
            }

            // Xóa người dùng
            $result = $this->nguoiDungModel->delete($maNguoiDung);

            if (!$result) {
                throw new Exception('Xóa người dùng thất bại');
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Xóa người dùng thành công'
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    // ... existing code ...

    public function getAllUsers()
    {
        try {
            // Xác thực token và kiểm tra quyền admin
            $decodedToken = $this->authMiddleware->authenticate();

            if (!$decodedToken) {
                throw new Exception('Không thể xác thực người dùng');
            }

            // Kiểm tra quyền admin
            // if (!isset($decodedToken->MaNhomQuyen) || $decodedToken->MaNhomQuyen !== 'ADMIN') {
            //     throw new Exception('Bạn không có quyền thực hiện hành động này');
            // }

            // Lấy các tham số từ request
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
            $orderBy = isset($_GET['order_by']) ? $_GET['order_by'] : 'created_at';
            $orderDirection = isset($_GET['order_direction']) ? $_GET['order_direction'] : 'DESC';

            // Gọi hàm getAll từ model NguoiDung
            $result = $this->nguoiDungModel->getAll($page, $limit, $searchTerm, $orderBy, $orderDirection);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination']
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getNhanVien() {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            $query = "SELECT nd.MaNguoiDung, nd.HoTen FROM nguoidung nd JOIN taikhoan tk ON nd.MaNguoiDung = tk.MaNguoiDung WHERE tk.MaNhomQuyen = 'NHANVIEN' AND tk.TrangThai = 1";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            http_response_code(200);
            echo json_encode(['status' => 'success', 'data' => $result]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

}
