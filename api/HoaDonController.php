<?php
require_once './models/HoaDon.php';

class HoaDonController {
    private $hoaDonModel;

    public function __construct() {
        $this->hoaDonModel = new HoaDon();
    }

    // Lấy tất cả hóa đơn
    public function getAllInvoices() {
        try {
            $result = $this->hoaDonModel->getAll();
            $num = $result->rowCount();

            if ($num > 0) {
                $invoices_arr = array();
                $invoices_arr['data'] = array();

                while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                    extract($row);

                    $invoice_item = array(
                        'MaHD' => $MaHD,
                        'MaNguoiDung' => $MaNguoiDung,
                        'MaNhanVien' => $MaNhanVien,
                        'NgayLap' => $NgayLap,
                        'TongTien' => $TongTien,
                        'TrangThai' => $TrangThai
                    );

                    array_push($invoices_arr['data'], $invoice_item);
                }

                http_response_code(200);
                echo json_encode($invoices_arr);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Không tìm thấy hóa đơn nào']);
            }
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
        }
    }

    // Lấy hóa đơn theo ID
    public function getInvoiceById($id) {
        try {
            $result = $this->hoaDonModel->getById($id);
            $num = $result->rowCount();

            if ($num > 0) {
                $row = $result->fetch(PDO::FETCH_ASSOC);
                
                $invoice = array(
                    'MaHD' => $row['MaHD'],
                    'MaNguoiDung' => $row['MaNguoiDung'],
                    'MaNhanVien' => $row['MaNhanVien'],
                    'NgayLap' => $row['NgayLap'],
                    'TongTien' => $row['TongTien'],
                    'TrangThai' => $row['TrangThai']
                );

                http_response_code(200);
                echo json_encode($invoice);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Không tìm thấy hóa đơn với mã ' . $id]);
            }
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
        }
    }

    // Lấy hóa đơn theo người dùng
    public function getInvoicesByUserId($userId) {
        try {
            $result = $this->hoaDonModel->getByUserId($userId);
            $num = $result->rowCount();

            if ($num > 0) {
                $invoices_arr = array();
                $invoices_arr['data'] = array();

                while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                    extract($row);

                    $invoice_item = array(
                        'MaHD' => $MaHD,
                        'NgayLap' => $NgayLap,
                        'TongTien' => $TongTien,
                        'TrangThai' => $TrangThai
                    );

                    array_push($invoices_arr['data'], $invoice_item);
                }

                http_response_code(200);
                echo json_encode($invoices_arr);
            } else {
                http_response_code(200);
                echo json_encode(['data' => [], 'message' => 'Không có hóa đơn nào']);
            }
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
        }
    }

    // Tạo hóa đơn mới
    public function createInvoice() {
        try {
            // Lấy dữ liệu từ request
            $data = json_decode(file_get_contents("php://input"));
            
            // Kiểm tra dữ liệu bắt buộc
            if(!isset($data->MaNguoiDung) || !isset($data->TongTien)) {
                http_response_code(400);
                echo json_encode(['error' => 'Thiếu thông tin bắt buộc']);
                return;
            }
            
            // Thiết lập thuộc tính cho hóa đơn
            $this->hoaDonModel->MaHD = $this->hoaDonModel->generateInvoiceId();
            $this->hoaDonModel->MaNguoiDung = $data->MaNguoiDung;
            $this->hoaDonModel->MaNhanVien = $data->MaNhanVien ?? null;
            $this->hoaDonModel->NgayLap = date('Y-m-d H:i:s');
            $this->hoaDonModel->TongTien = $data->TongTien;
            $this->hoaDonModel->TrangThai = $data->TrangThai ?? 'Chờ xử lý';
            
            // Tạo hóa đơn
            if($this->hoaDonModel->create()) {
                http_response_code(201);
                echo json_encode([
                    'message' => 'Tạo hóa đơn thành công',
                    'MaHD' => $this->hoaDonModel->MaHD
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Không thể tạo hóa đơn']);
            }
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
        }
    }

    // Cập nhật hóa đơn
    public function updateInvoice($id) {
        try {
            // Kiểm tra hóa đơn tồn tại
            $existingInvoice = $this->hoaDonModel->getById($id);
            if ($existingInvoice->rowCount() == 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Không tìm thấy hóa đơn']);
                return;
            }
            
            // Lấy dữ liệu từ request
            $data = json_decode(file_get_contents("php://input"));
            $row = $existingInvoice->fetch(PDO::FETCH_ASSOC);
            
            // Gán các giá trị cập nhật
            $this->hoaDonModel->MaHD = $id;
            $this->hoaDonModel->MaNguoiDung = $data->MaNguoiDung ?? $row['MaNguoiDung'];
            $this->hoaDonModel->MaNhanVien = $data->MaNhanVien ?? $row['MaNhanVien'];
            $this->hoaDonModel->NgayLap = $data->NgayLap ?? $row['NgayLap'];
            $this->hoaDonModel->TongTien = $data->TongTien ?? $row['TongTien'];
            $this->hoaDonModel->TrangThai = $data->TrangThai ?? $row['TrangThai'];
            
            // Cập nhật hóa đơn
            if($this->hoaDonModel->update()) {
                http_response_code(200);
                echo json_encode(['message' => 'Cập nhật hóa đơn thành công']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Không thể cập nhật hóa đơn']);
            }
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
        }
    }

    // Cập nhật trạng thái hóa đơn
    public function updateInvoiceStatus($id) {
        try {
            // Kiểm tra hóa đơn tồn tại
            $existingInvoice = $this->hoaDonModel->getById($id);
            if ($existingInvoice->rowCount() == 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Không tìm thấy hóa đơn']);
                return;
            }
            
            // Lấy dữ liệu từ request
            $data = json_decode(file_get_contents("php://input"));
            
            if (!isset($data->TrangThai)) {
                http_response_code(400);
                echo json_encode(['error' => 'Trạng thái là bắt buộc']);
                return;
            }
            
            // Cập nhật trạng thái
            $this->hoaDonModel->MaHD = $id;
            if ($this->hoaDonModel->updateStatus($data->TrangThai)) {
                http_response_code(200);
                echo json_encode(['message' => 'Cập nhật trạng thái hóa đơn thành công']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Không thể cập nhật trạng thái hóa đơn']);
            }
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
        }
    }

    // Xóa hóa đơn
    public function deleteInvoice($id) {
        try {
            // Kiểm tra hóa đơn tồn tại
            $existingInvoice = $this->hoaDonModel->getById($id);
            if ($existingInvoice->rowCount() == 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Không tìm thấy hóa đơn']);
                return;
            }
            
            // Xóa hóa đơn
            $this->hoaDonModel->MaHD = $id;
            if ($this->hoaDonModel->delete()) {
                http_response_code(200);
                echo json_encode(['message' => 'Xóa hóa đơn thành công']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Không thể xóa hóa đơn']);
            }
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
        }
    }

    // Lấy thống kê doanh thu
    public function getSalesStatistics() {
        try {
            // Lấy tham số ngày từ query string
            $startDate = $_GET['start_date'] ?? date('Y-m-01'); // Mặc định là ngày đầu tháng hiện tại
            $endDate = $_GET['end_date'] ?? date('Y-m-d');      // Mặc định là ngày hiện tại
            
            // Lấy dữ liệu thống kê
            $totalSales = $this->hoaDonModel->getTotalSalesByDateRange($startDate, $endDate);
            
            http_response_code(200);
            echo json_encode([
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_sales' => $totalSales
            ]);
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
        }
    }
}
?>