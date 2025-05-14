<?php
require_once __DIR__ . '/../../models/Taikhoan.php';
require_once __DIR__ . '/../../utils/JwtHandler.php';

class AuthController
{
    private $taikhoanModel;
    private $jwtHandler;

    public function __construct()
    {
        $this->taikhoanModel = new Taikhoan();
        $this->jwtHandler = new JwtHandler();
    }

    // API Register using TenTK and MatKhau
    // API Register yêu cầu HoTen, TenTK và MatKhau
    public function register()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // Validate input - yêu cầu HoTen, TenTK và MatKhau
        if (empty($data['HoTen']) || empty($data['TenTK']) || empty($data['MatKhau'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Họ tên, tên tài khoản và mật khẩu là bắt buộc']);
            return;
        }

        try {
            // Kiểm tra username đã tồn tại chưa
            if ($this->taikhoanModel->usernameExists($data['TenTK'])) {
                http_response_code(409); // Conflict
                echo json_encode(['error' => 'Tên tài khoản đã tồn tại']);
                return;
            }

            // Prepare account data
            $accountData = [
                'TenTK' => $data['TenTK'],
                'MatKhau' => $data['MatKhau'], // Will be hashed in model
                'HoTen' => $data['HoTen'], // Sử dụng HoTen được cung cấp
                'Email' => $data['Email'] ?? '',
                'SDT' => $data['SDT'] ?? '',
                'DiaChi' => $data['DiaChi'] ?? '',
                'NgaySinh' => $data['NgaySinh'] ?? null
            ];

            // Register account (model will handle user creation first)
            $result = $this->taikhoanModel->create($accountData);

            if ($result) {
                // Get the newly created account
                $newAccount = $this->taikhoanModel->findByUsername($accountData['TenTK']);

                if (!$newAccount) {
                    throw new Exception('Không thể lấy thông tin tài khoản sau khi tạo');
                }

                http_response_code(201);
                echo json_encode([
                    'message' => 'Đăng ký tài khoản thành công',
                    'MaTK' => $newAccount['MaTK'],
                    'TenTK' => $newAccount['TenTK'],
                    'MaNhomQuyen' => $newAccount['MaNhomQuyen'],
                    'HoTen' => $accountData['HoTen'] // Thêm HoTen vào response
                ]);
            } else {
                throw new Exception('Không thể tạo tài khoản');
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // API Login using TenTK and MatKhau
    public function login()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // Validate input
        if (empty($data['TenTK']) || empty($data['MatKhau'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Tên tài khoản và mật khẩu là bắt buộc']);
            return;
        }

        try {
            // Find account by username
            $account = $this->taikhoanModel->findByUsername($data['TenTK']);

            if (!$account) {
                throw new Exception('Tên tài khoản hoặc mật khẩu không đúng');
            }

            // Verify password is correct
            if (!password_verify($data['MatKhau'], $account['MatKhau'])) {
                throw new Exception('Tên tài khoản hoặc mật khẩu không đúng');
            }

            // Check if account is active
            if ($account['TrangThai'] != 1) {
                throw new Exception('Tài khoản đã bị khóa');
            }

            // Generate token
            $token = $this->jwtHandler->generateToken([
                'MaTK' => $account['MaTK'],
                'TenTK' => $account['TenTK'],
                'MaNhomQuyen' => $account['MaNhomQuyen']
            ]);

            http_response_code(200);
            echo json_encode([
                'message' => 'Đăng nhập thành công',
                'token' => $token,
            ]);
        } catch (Exception $e) {
            $statusCode = $e->getMessage() === 'Tài khoản đã bị khóa' ? 403 : 401;
            http_response_code($statusCode);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getCurrentUser()
    {
        try {
            $authMiddleware = new AuthMiddleware();
            $decodedToken = $authMiddleware->authenticate();

            if (!$decodedToken) {
                throw new Exception('Không thể xác thực người dùng');
            }

            // Lấy thông tin tài khoản từ MaTK trong token
            $account = $this->taikhoanModel->getById($decodedToken->MaTK);

            if (!$account) {
                throw new Exception('Không tìm thấy thông tin tài khoản');
            }

            // Loại bỏ thông tin nhạy cảm trước khi trả về
            unset($account['MatKhau']);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $account
            ]);
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function logout()
    {
        try {
            // Kiểm tra xem có token trong header không
            $headers = getallheaders();
            $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

            if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                // Nếu không có token, vẫn trả về thành công vì người dùng đã đăng xuất
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Đăng xuất thành công'
                ]);
                return;
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
