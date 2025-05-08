<?php
require_once './models/BaoHanh.php';

class BaoHanhController {
    private $baoHanhModel;

    public function __construct() {
        $this->baoHanhModel = new BaoHanh();
    }

    // Lấy tất cả thông tin bảo hành
    public function getAllWarranties() {
        try {
            $result = $this->baoHanhModel->getAll();
            $num = $result->rowCount();

            if ($num > 0) {
                $warranties_arr = array();
                $warranties_arr['data'] = array();

                while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                    extract($row);

                    $warranty_item = array(
                        'MaBH' => $MaBH,
                        'MaHD' => $MaHD,
                        'MaSeri' => $MaSeri,
                        'NgayMua' => $NgayMua,
                        'HanBaoHanh' => $HanBaoHanh,
                        'MoTa' => $MoTa
                    );

                    array_push($warranties_arr['data'], $warranty_item);
                }

                http_response_code(200);
                echo json_encode($warranties_arr);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Không tìm thấy thông tin bảo hành nào']);
            }
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
        }
    }

    // Lấy thông tin bảo hành theo ID
    public function getWarrantyById($id) {
        try {
            $result = $this->baoHanhModel->getById($id);
            $num = $result->rowCount();

            if ($num > 0) {
                $row = $result->fetch(PDO::FETCH_ASSOC);
                
                $warranty = array(
                    'MaBH' => $row['MaBH'],
                    'MaHD' => $row['MaHD'],
                    'MaSeri' => $row['MaSeri'],
                    'NgayMua' => $row['NgayMua'],
                    'HanBaoHanh' => $row['HanBaoHanh'],
                    'MoTa' => $row['MoTa'],
                    'ConHieuLuc' => date('Y-m-d') <= $row['HanBaoHanh']
                );

                http_response_code(200);
                echo json_encode($warranty);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Không tìm thấy thông tin bảo hành với mã ' . $id]);
            }
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
        }
    }

    // Lấy thông tin bảo hành theo số seri
    public function getWarrantyBySerialNumber() {
        try {
            // Lấy số seri từ request
            $serialNumber = $_GET['serial_number'] ?? null;
            
            if (!$serialNumber) {
                http_response_code(400);
                echo json_encode(['error' => 'Số seri sản phẩm là bắt buộc']);
                return;
            }
            
            $result = $this->baoHanhModel->getBySerialNumber($serialNumber);
            $num = $result->rowCount();

            if ($num > 0) {
                $row = $result->fetch(PDO::FETCH_ASSOC);
                
                $warranty = array(
                    'MaBH' => $row['MaBH'],
                    'MaHD' => $row['MaHD'],
                    'MaSeri' => $row['MaSeri'],
                    'NgayMua' => $row['NgayMua'],
                    'HanBaoHanh' => $row['HanBaoHanh'],
                    'MoTa' => $row['MoTa'],
                    'ConHieuLuc' => date('Y-m-d') <= $row['HanBaoHanh']
                );

                http_response_code(200);
                echo json_encode($warranty);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Không tìm thấy thông tin bảo hành cho sản phẩm này']);
            }
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
        }
    }

    // Lấy thông tin bảo hành theo mã hóa đơn
    public function getWarrantiesByInvoiceId($invoiceId) {
        try {
            $result = $this->baoHanhModel->getByInvoiceId($invoiceId);
            $num = $result->rowCount();

            if ($num > 0) {
                $warranties_arr = array();
                $warranties_arr['data'] = array();

                while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                    extract($row);

                    $warranty_item = array(
                        'MaBH' => $MaBH,
                        'MaHD' => $MaHD,
                        'MaSeri' => $MaSeri,
                        'NgayMua' => $NgayMua,
                        'HanBaoHanh' => $HanBaoHanh,
                        'MoTa' => $MoTa,
                        'ConHieuLuc' => date('Y-m-d') <= $HanBaoHanh
                    );

                    array_push($warranties_arr['data'], $warranty_item);
                }

                http_response_code(200);
                echo json_encode($warranties_arr);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Không tìm thấy thông tin bảo hành cho hóa đơn này']);
            }
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
        }
    }

    // Tạo thông tin bảo hành mới
    public function createWarranty() {
        try {
            // Lấy dữ liệu từ request
            $data = json_decode(file_get_contents("php://input"));
            
            // Kiểm tra dữ liệu bắt buộc
            if(!isset($data->MaHD) || !isset($data->MaSeri) || !isset($data->NgayMua) || !isset($data->HanBaoHanh)) {
                http_response_code(400);
                echo json_encode(['error' => 'Thiếu thông tin bắt buộc']);
                return;
            }
            
            // Thiết lập thuộc tính cho bảo hành
            $this->baoHanhModel->MaBH = $this->baoHanhModel->generateWarrantyId();
            $this->baoHanhModel->MaHD = $data->MaHD;
            $this->baoHanhModel->MaSeri = $data->MaSeri;
            $this->baoHanhModel->NgayMua = $data->NgayMua;
            $this->baoHanhModel->HanBaoHanh = $data->HanBaoHanh;
            $this->baoHanhModel->MoTa = $data->MoTa ?? '';
            
            // Tạo bảo hành
            if($this->baoHanhModel->create()) {
                http_response_code(201);
                echo json_encode([
                    'message' => 'Tạo thông tin bảo hành thành công',
                    'MaBH' => $this->baoHanhModel->MaBH
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Không thể tạo thông tin bảo hành']);
            }
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
        }
    }

    // Cập nhật thông tin bảo hành
    public function updateWarranty($id) {
        try {
            // Kiểm tra bảo hành tồn tại
            $existingWarranty = $this->baoHanhModel->getById($id);
            if ($existingWarranty->rowCount() == 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Không tìm thấy thông tin bảo hành']);
                return;
            }
            
            // Lấy dữ liệu từ request
            $data = json_decode(file_get_contents("php://input"));
            $existingData = $existingWarranty->fetch(PDO::FETCH_ASSOC);
            
            // Gán các giá trị cập nhật
            $this->baoHanhModel->MaBH = $id;
            $this->baoHanhModel->MaHD = $data->MaHD ?? $existingData['MaHD'];
            $this->baoHanhModel->MaSeri = $data->MaSeri ?? $existingData['MaSeri'];
            $this->baoHanhModel->NgayMua = $data->NgayMua ?? $existingData['NgayMua'];
            $this->baoHanhModel->HanBaoHanh = $data->HanBaoHanh ?? $existingData['HanBaoHanh'];
            $this->baoHanhModel->MoTa = $data->MoTa ?? $existingData['MoTa'];
            
            // Cập nhật bảo hành
            if($this->baoHanhModel->update()) {
                http_response_code(200);
                echo json_encode(['message' => 'Cập nhật thông tin bảo hành thành công']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Không thể cập nhật thông tin bảo hành']);
            }
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
        }
    }

    // Xóa thông tin bảo hành
    public function deleteWarranty($id) {
        try {
            // Kiểm tra bảo hành tồn tại
            $existingWarranty = $this->baoHanhModel->getById($id);
            if ($existingWarranty->rowCount() == 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Không tìm thấy thông tin bảo hành']);
                return;
            }
            
            // Xóa bảo hành
            $this->baoHanhModel->MaBH = $id;
            if ($this->baoHanhModel->delete()) {
                http_response_code(200);
                echo json_encode(['message' => 'Xóa thông tin bảo hành thành công']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Không thể xóa thông tin bảo hành']);
            }
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
        }
    }

    // Kiểm tra trạng thái bảo hành
    public function checkWarrantyStatus() {
        try {
            // Lấy số seri từ request
            $serialNumber = $_GET['serial_number'] ?? null;
            
            if (!$serialNumber) {
                http_response_code(400);
                echo json_encode(['error' => 'Số seri sản phẩm là bắt buộc']);
                return;
            }
            
            $result = $this->baoHanhModel->getBySerialNumber($serialNumber);
            
            if ($result->rowCount() > 0) {
                $row = $result->fetch(PDO::FETCH_ASSOC);
                $isValid = date('Y-m-d') <= $row['HanBaoHanh'];
                
                http_response_code(200);
                echo json_encode([
                    'MaSeri' => $row['MaSeri'],
                    'NgayMua' => $row['NgayMua'],
                    'HanBaoHanh' => $row['HanBaoHanh'],
                    'TrangThai' => $isValid ? 'Còn bảo hành' : 'Hết hạn bảo hành',
                    'ConHieuLuc' => $isValid
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Không tìm thấy thông tin bảo hành cho sản phẩm này']);
            }
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
        }
    }
}
?>