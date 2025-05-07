<?php
// Đường dẫn tương đối đến file authentication.php
require_once './api/taikhoan/authentication.php';
require_once './api/NguoiDungController.php';
require_once './api/NhomQuyenController.php';
require_once './api/ChucNangController.php';
require_once './api/NhaCungCapController.php';
require_once './api/LoaiSanPhamController.php';
require_once './api/taikhoan/TaiKhoanController.php';
require_once './api/SanPhamController.php';
require_once './api/HoaDonController.php';
require_once './api/ChiTietHoaDonController.php';
require_once './api/VNPayController.php';

// Thiết lập header JSON
header("Content-Type: application/json");

// Xử lý CORS (cho phép truy cập từ các domain khác)
header('Access-Control-Allow-Origin: http://localhost:5173'); // Origin của frontend
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Xử lý preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


// Khởi tạo controller
$authController = new AuthController();
$nguoiDungController = new NguoiDungController();
$nhomQuyenController = new NhomQuyenController();
$chucNangController = new ChucNangController();
$nhaCungCapController = new NhaCungCapController();
$hoaDonController = new HoaDonController();
$chiTietHoaDonController = new ChiTietHoaDonController();
$loaiSanPhamController = new LoaiSanPhamController();
$taiKhoanController = new TaiKhoanController();
$sanphamController = new SanPhamController();

$vnpayController = new VNPayController();

error_log($_SERVER['REQUEST_URI']);

// Lấy URI và phương thức request
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$requestMethod = $_SERVER['REQUEST_METHOD'];

error_log($requestUri);

// Xác định base path của API
$basePath = '/shoppc/api'; // Sửa lại base path cho đúng
$apiPath = str_replace($basePath, '', $requestUri);

error_log("API Path: " . $apiPath);


switch ($apiPath) {
    case '/auth/register':
        if ($requestMethod === 'POST') {
            $authController->register();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case '/auth/login':
        if ($requestMethod === 'POST') {
            $authController->login();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
    
    case '/auth/logout':
        if ($requestMethod === 'POST') {
            $authController->logout();
        } 
        break;
        
    case '/user/profile':
        if ($requestMethod === 'GET') {
            $nguoiDungController->getCurrentUser();
        } else if ($requestMethod === 'PUT' || $requestMethod === 'PATCH') {
            $nguoiDungController->updateCurrentUser();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    // nguoi dung tu doi mat khau
    case '/user/password':
        if ($requestMethod === 'PUT' || $requestMethod === 'PATCH') {
            $taiKhoanController->updatePassword();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    // nguoi dung tu vo hieu/kich hoat tai khoan co the khong can thiet
    case '/user/deactivate':
        if ($requestMethod === 'PUT' || $requestMethod === 'PATCH') {
            $taiKhoanController->deactivateOwnAccount();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case '/accounts':
        if ($requestMethod === 'GET') {
            $taiKhoanController->getAllAccounts();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case (preg_match('#^/accounts/([^/]+)$#', $apiPath, $matches) ? true : false):
        $maTaiKhoan = $matches[1];

        if ($requestMethod === 'GET') {
            $taiKhoanController->getAccountById($maTaiKhoan);
        } else if ($requestMethod === 'PUT' || $requestMethod === 'PATCH') {
            $taiKhoanController->updateAccount($maTaiKhoan);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case (preg_match('#^/accounts/([^/]+)/role$#', $apiPath, $matches) ? true : false):
        $maTaiKhoan = $matches[1];

        if ($requestMethod === 'PUT' || $requestMethod === 'PATCH') {
            $taiKhoanController->updateAccountRole($maTaiKhoan);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case '/users':
        if ($requestMethod === 'GET') {
            $nguoiDungController->getAllUsers();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    // Url path động thì sử dụng biểu thử preg_match
    case (preg_match('#^/users/([^/]+)$#', $apiPath, $matches) ? $apiPath : ''):
        $maNguoiDung = $matches[1];

        if ($requestMethod === 'PUT' || $requestMethod === 'PATCH') {
            $nguoiDungController->updateUser($maNguoiDung);
        } else if ($requestMethod === 'DELETE') {
            $nguoiDungController->deleteUser($maNguoiDung);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    // Nhom quyen route
    case '/nhomquyen':
        if ($requestMethod === 'GET') {
            $nhomQuyenController->getAll();
        } else if ($requestMethod === 'POST') {
            $nhomQuyenController->create();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case (preg_match('#^/nhomquyen/([^/]+)$#', $apiPath, $matches) ? true : false):
        $maNhomQuyen = $matches[1];

        if ($requestMethod === 'GET') {
            $nhomQuyenController->getOne($maNhomQuyen);
        } else if ($requestMethod === 'PUT' || $requestMethod === 'PATCH') {
            $nhomQuyenController->update($maNhomQuyen);
        } else if ($requestMethod === 'DELETE') {
            $nhomQuyenController->delete($maNhomQuyen);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case (preg_match('#^/nhomquyen/([^/]+)/chucnang$#', $apiPath, $matches) ? true : false):
        $maNhomQuyen = $matches[1];

        if ($requestMethod === 'GET') {
            $nhomQuyenController->getFunctions($maNhomQuyen);
        } else if ($requestMethod === 'PUT' || $requestMethod === 'PATCH') {
            $nhomQuyenController->updateFunctions($maNhomQuyen);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    // chuc nang route
    case '/chucnang':
        if ($requestMethod === 'GET') {
            $chucNangController->getAll();
        } else if ($requestMethod === 'POST') {
            $chucNangController->create();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case (preg_match('#^/chucnang/([^/]+)$#', $apiPath, $matches) ? true : false):
        $maChucNang = $matches[1];

        if ($requestMethod === 'GET') {
            $chucNangController->getOne($maChucNang);
        } else if ($requestMethod === 'PUT' || $requestMethod === 'PATCH') {
            $chucNangController->update($maChucNang);
        } else if ($requestMethod === 'DELETE') {
            $chucNangController->delete($maChucNang);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    // nha cung cap route
    case '/nhacungcap':
        if ($requestMethod === 'GET') {
            $nhaCungCapController->getAll();
        } else if ($requestMethod === 'POST') {
            $nhaCungCapController->create();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case (preg_match('#^/nhacungcap/([^/]+)$#', $apiPath, $matches) ? true : false):
        $maNhaCungCap = $matches[1];

        if ($requestMethod === 'GET') {
            $nhaCungCapController->getOne($maNhaCungCap);
        } else if ($requestMethod === 'PUT' || $requestMethod === 'PATCH') {
            $nhaCungCapController->update($maNhaCungCap);
        } else if ($requestMethod === 'DELETE') {
            $nhaCungCapController->delete($maNhaCungCap);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    // Hóa đơn route
    case '/hoadon':
        if ($requestMethod === 'GET') {
            $hoaDonController->getAll();
        } else if ($requestMethod === 'POST') {
            $hoaDonController->create();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case '/hoadon/search':
        if ($requestMethod === 'GET') {
            $hoaDonController->search();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case (preg_match('#^/hoadon/([^/]+)$#', $apiPath, $matches) ? true : false):
        $maHD = $matches[1];

        if ($requestMethod === 'PUT' || $requestMethod === 'PATCH') {
            $hoaDonController->update($maHD);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
        
    // Chi tiết hóa đơn route
    case '/chitiethoadon':
        if ($requestMethod === 'GET') {
            $chiTietHoaDonController->getAll();
        } else if ($requestMethod === 'POST') {
            $chiTietHoaDonController->create();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
        
    case (preg_match('#^/chitiethoadon/([^/]+)$#', $apiPath, $matches) ? true : false):
        $maCTHD = $matches[1];

        if ($requestMethod === 'PUT' || $requestMethod === 'PATCH') {
            $chiTietHoaDonController->update($maCTHD);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
        
    case (preg_match('#^/hoadon/([^/]+)/chitiet$#', $apiPath, $matches) ? true : false):
        $maHD = $matches[1];

        if ($requestMethod === 'GET') {
            $chiTietHoaDonController->getByMaHD($maHD);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    // Xóa mềm nhà cung cấp
    case (preg_match('#^/nhacungcap/([^/]+)/soft-delete$#', $apiPath, $matches) && $requestMethod === 'PUT'):
        $nhaCungCapController->softDelete($matches[1]);
        break;

    // Khôi phục nhà cung cấp
    case (preg_match('#^/nhacungcap/([^/]+)/restore$#', $apiPath, $matches) && $requestMethod === 'PUT'):
        $nhaCungCapController->restore($matches[1]);
        break;

    // loai san pham route
    case '/loaisanpham':
        if ($requestMethod === 'GET') {
            $loaiSanPhamController->getAll();
        } else if ($requestMethod === 'POST') {
            $loaiSanPhamController->create();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case (preg_match('#^/loaisanpham/([^/]+)$#', $apiPath, $matches) ? true : false):
        $maLoaiSanPham = $matches[1];

        if ($requestMethod === 'PUT' || $requestMethod === 'PATCH') {
            $loaiSanPhamController->update($maLoaiSanPham);
        } else if ($requestMethod === 'DELETE') {
            $loaiSanPhamController->delete($maLoaiSanPham);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    // San pham route
    case '/sanpham/filter':
        if ($requestMethod === 'GET') {
            $sanphamController->getFilterProduct();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case '/sanpham':
        if ($requestMethod === 'GET') {
            $sanphamController->getAllByPage();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case '/sanpham/create':
        if ($requestMethod === 'POST') {
            $sanphamController->createSanPham();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case '/sanpham/update':
        if ($requestMethod === 'PUT') {
            $sanphamController->capnhatSanPham();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case '/sanpham/status':
        if ($requestMethod === 'PUT' || $requestMethod === 'PATCH') {
            $sanphamController->changeStatus();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case (preg_match('#^/sanpham/([^/]+)$#', $apiPath, $matches) ? true : false):
        $maSP = $matches[1];

        if ($requestMethod === 'GET') {
            $sanphamController->getSanPhamByMaSP($maSP);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    // VNPay routes
    case '/payment/vnpay/create':
        if ($requestMethod === 'POST') {
            $vnpayController->createPayment();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case '/payment/vnpay/return':
        if ($requestMethod === 'GET') {
            $vnpayController->paymentReturn();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found', 'path' => $apiPath]);
        break;
}
