<?php
// Đường dẫn tương đối đến file authentication.php
require_once './api/taikhoan/authentication.php';
require_once './api/NguoiDungController.php';

// Thiết lập header JSON
header("Content-Type: application/json");

// Xử lý CORS (cho phép truy cập từ các domain khác)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Xử lý preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


// Khởi tạo controller
$authController = new AuthController();
$nguoiDungController = new NguoiDungController();

error_log($_SERVER['REQUEST_URI']);

// Lấy URI và phương thức request
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$requestMethod = $_SERVER['REQUEST_METHOD'];

error_log($requestUri);

// Xác định base path của API
$basePath = '/shop_pc/api'; // Sửa lại base path cho đúng
$apiPath = str_replace($basePath, '', $requestUri);

error_log("API Path: " . $apiPath);

// Router đơn giản
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

    case '/user/profile':
            if ($requestMethod === 'GET') {
                $nguoiDungController->getCurrentUser();
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