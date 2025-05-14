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
            // Lấy tham số query từ request
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
            $fromDate = isset($_GET['from_date']) ? $_GET['from_date'] : '';
            $toDate = isset($_GET['to_date']) ? $_GET['to_date'] : '';
            $status = isset($_GET['status']) ? $_GET['status'] : '';
            $orderBy = isset($_GET['order_by']) ? $_GET['order_by'] : 'NgayGuiBaoHanh';
            $orderDirection = isset($_GET['order_direction']) ? $_GET['order_direction'] : 'DESC';
            
            $result = $this->baoHanhModel->getAll(
                $page, 
                $limit, 
                $searchTerm, 
                $fromDate, 
                $toDate, 
                $status, 
                $orderBy, 
                $orderDirection
            );
            
            http_response_code(200);
            echo json_encode($result);
            
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
                    'NgayGuiBaoHanh' => $row['NgayGuiBaoHanh'],
                    'NgayTraBaoHanh' => $row['NgayTraBaoHanh'],
                    'MoTa' => $row['MoTa'],
                    'TrangThai' => (int)$row['TrangThai'],
                    'TrangThaiText' => $this->baoHanhModel->getTrangThaiText($row['TrangThai'])
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
                    'NgayGuiBaoHanh' => $row['NgayGuiBaoHanh'],
                    'NgayTraBaoHanh' => $row['NgayTraBaoHanh'],
                    'MoTa' => $row['MoTa'],
                    'TrangThai' => (int)$row['TrangThai'],
                    'TrangThaiText' => $this->baoHanhModel->getTrangThaiText($row['TrangThai'])
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
                        'NgayGuiBaoHanh' => $NgayGuiBaoHanh,
                        'NgayTraBaoHanh' => $NgayTraBaoHanh,
                        'MoTa' => $MoTa,
                        'TrangThai' => (int)$TrangThai,
                        'TrangThaiText' => $this->baoHanhModel->getTrangThaiText($TrangThai)
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

    // Kiểm tra sản phẩm còn trong thời gian bảo hành không
    private function checkWarrantyEligibility($maSeri, $maHD = null) {
        try {
            // Kết nối database
            $db = new Database();
            $conn = $db->getConnection();
            
            // Nếu không có mã hóa đơn, tìm mã hóa đơn từ mã seri
            if (!$maHD) {
                $findInvoiceQuery = "SELECT hd.MaHD, hd.NgayLap 
                                    FROM hoadon hd 
                                    JOIN chitiethoadon cthd ON hd.MaHD = cthd.MaHD 
                                    WHERE cthd.MaSeri = :MaSeri
                                    ORDER BY hd.NgayLap DESC LIMIT 1";
                $findInvoiceStmt = $conn->prepare($findInvoiceQuery);
                $findInvoiceStmt->bindParam(':MaSeri', $maSeri);
                $findInvoiceStmt->execute();
                
                if ($findInvoiceStmt->rowCount() == 0) {
                    return [
                        'eligible' => false,
                        'message' => 'Không tìm thấy hóa đơn cho sản phẩm này'
                    ];
                }
                
                $invoiceData = $findInvoiceStmt->fetch(PDO::FETCH_ASSOC);
                $maHD = $invoiceData['MaHD'];
                $ngayMua = $invoiceData['NgayLap'];
            } else {
                // Lấy ngày mua từ hóa đơn
                $invoiceQuery = "SELECT NgayLap FROM hoadon WHERE MaHD = :MaHD";
                $invoiceStmt = $conn->prepare($invoiceQuery);
                $invoiceStmt->bindParam(':MaHD', $maHD);
                $invoiceStmt->execute();
                
                if ($invoiceStmt->rowCount() == 0) {
                    return [
                        'eligible' => false,
                        'message' => 'Không tìm thấy hóa đơn với mã ' . $maHD
                    ];
                }
                
                $ngayMua = $invoiceStmt->fetch(PDO::FETCH_ASSOC)['NgayLap'];
            }
            
            // Lấy mã sản phẩm từ số seri
            $seriQuery = "SELECT MaSP FROM serisanpham WHERE MaSeri = :MaSeri";
            $seriStmt = $conn->prepare($seriQuery);
            $seriStmt->bindParam(':MaSeri', $maSeri);
            $seriStmt->execute();
            
            if ($seriStmt->rowCount() == 0) {
                return [
                    'eligible' => false,
                    'message' => 'Không tìm thấy thông tin sản phẩm với số seri ' . $maSeri
                ];
            }
            
            $maSP = $seriStmt->fetch(PDO::FETCH_ASSOC)['MaSP'];
            
            // Lấy thời gian bảo hành từ sản phẩm
            $productQuery = "SELECT tg_baohanh FROM sanpham WHERE MaSP = :MaSP";
            $productStmt = $conn->prepare($productQuery);
            $productStmt->bindParam(':MaSP', $maSP);
            $productStmt->execute();
            
            if ($productStmt->rowCount() == 0) {
                return [
                    'eligible' => false,
                    'message' => 'Không tìm thấy thông tin sản phẩm với mã ' . $maSP
                ];
            }
            
            $tgBaoHanh = $productStmt->fetch(PDO::FETCH_ASSOC)['tg_baohanh'];
            
            // Kiểm tra sản phẩm đã được bảo hành trước đó chưa
            $existingWarrantyQuery = "SELECT COUNT(*) as count FROM baohanh WHERE MaSeri = :MaSeri";
            $existingWarrantyStmt = $conn->prepare($existingWarrantyQuery);
            $existingWarrantyStmt->bindParam(':MaSeri', $maSeri);
            $existingWarrantyStmt->execute();
            $existingWarrantyCount = $existingWarrantyStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Tính ngày hết hạn bảo hành
            $ngayMuaObj = new DateTime($ngayMua);
            $ngayHetHan = clone $ngayMuaObj;
            $ngayHetHan->add(new DateInterval('P' . $tgBaoHanh . 'M')); // Thêm số tháng bảo hành
            
            $ngayHienTai = new DateTime();
            
            // Kiểm tra còn trong thời gian bảo hành không
            if ($ngayHienTai > $ngayHetHan) {
                return [
                    'eligible' => false,
                    'message' => 'Sản phẩm đã hết thời gian bảo hành',
                    'ngayMua' => $ngayMua,
                    'tgBaoHanh' => $tgBaoHanh,
                    'ngayHetHan' => $ngayHetHan->format('Y-m-d')
                ];
            }
            
            return [
                'eligible' => true,
                'message' => 'Sản phẩm còn trong thời gian bảo hành',
                'ngayMua' => $ngayMua,
                'tgBaoHanh' => $tgBaoHanh,
                'ngayHetHan' => $ngayHetHan->format('Y-m-d'),
                'daGuiBaoHanh' => $existingWarrantyCount > 0
            ];
            
        } catch (Exception $e) {
            return [
                'eligible' => false,
                'message' => 'Lỗi kiểm tra bảo hành: ' . $e->getMessage()
            ];
        }
    }
    
    // Kiểm tra khả năng bảo hành của sản phẩm
    public function checkEligibility() {
        try {
            // Lấy thông tin từ request
            $maSeri = $_GET['serial_number'] ?? null;
            $maHD = $_GET['invoice_id'] ?? null;
            
            if (!$maSeri) {
                http_response_code(400);
                echo json_encode(['error' => 'Số seri sản phẩm là bắt buộc']);
                return;
            }
            
            $result = $this->checkWarrantyEligibility($maSeri, $maHD);
            
            if ($result['eligible']) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => [
                        'MaSeri' => $maSeri,
                        'NgayMua' => $result['ngayMua'],
                        'ThoiGianBaoHanh' => $result['tgBaoHanh'] . ' tháng',
                        'NgayHetHan' => $result['ngayHetHan'],
                        'DaGuiBaoHanh' => $result['daGuiBaoHanh']
                    ]
                ]);
            } else {
                http_response_code(200); // Vẫn trả về 200 nhưng với success = false
                echo json_encode([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => isset($result['ngayMua']) ? [
                        'MaSeri' => $maSeri,
                        'NgayMua' => $result['ngayMua'] ?? null,
                        'ThoiGianBaoHanh' => $result['tgBaoHanh'] ?? null,
                        'NgayHetHan' => $result['ngayHetHan'] ?? null
                    ] : null
                ]);
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
            if(!isset($data->MaHD) || !isset($data->MaSeri) || !isset($data->NgayGuiBaoHanh)) {
                http_response_code(400);
                echo json_encode(['error' => 'Thiếu thông tin bắt buộc: MaHD, MaSeri, NgayGuiBaoHanh']);
                return;
            }
            
            // Kiểm tra sản phẩm còn trong thời gian bảo hành không
            $eligibility = $this->checkWarrantyEligibility($data->MaSeri, $data->MaHD);
            
            if (!$eligibility['eligible']) {
                http_response_code(200); // Đổi từ 400 thành 200
                echo json_encode([
                    'success' => false,
                    'message' => $eligibility['message'],
                    'data' => isset($eligibility['ngayMua']) ? [
                        'MaSeri' => $data->MaSeri,
                        'NgayMua' => $eligibility['ngayMua'],
                        'ThoiGianBaoHanh' => $eligibility['tgBaoHanh'] . ' tháng',
                        'NgayHetHan' => $eligibility['ngayHetHan']
                    ] : null
                ]);
                return;
            }
            
            // Thiết lập thuộc tính cho bảo hành
            $this->baoHanhModel->MaHD = $data->MaHD;
            $this->baoHanhModel->MaSeri = $data->MaSeri;
            $this->baoHanhModel->NgayGuiBaoHanh = $data->NgayGuiBaoHanh;
            $this->baoHanhModel->NgayTraBaoHanh = $data->NgayTraBaoHanh ?? null;
            $this->baoHanhModel->MoTa = $data->MoTa ?? '';
            $this->baoHanhModel->TrangThai = $data->TrangThai ?? 1; // Mặc định là Chờ xử lý
            
            // Thêm MaTK nếu có
            if(isset($data->MaTK)) {
                $this->baoHanhModel->MaTK = $data->MaTK;
            }
            
            // Tạo bảo hành
            if($this->baoHanhModel->create()) {
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'Tạo thông tin bảo hành thành công',
                    'data' => [
                        'MaBH' => $this->baoHanhModel->MaBH
                    ]
                ]);
            } else {
                http_response_code(200);
                echo json_encode([
                    'success' => false,
                    'message' => 'Không thể tạo thông tin bảo hành'
                ]);
            }
        } catch(Exception $e) {
            http_response_code(200);
            echo json_encode([
                'success' => false,
                'message' => 'Lỗi server: ' . $e->getMessage()
            ]);
        }
    }

    // Cập nhật thông tin bảo hành
    public function updateWarranty($id) {
        try {
            // Kiểm tra bảo hành tồn tại hay không
            $result = $this->baoHanhModel->getById($id);
            if($result->rowCount() == 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Không tìm thấy thông tin bảo hành']);
                return;
            }
            
            // Lấy dữ liệu từ request
            $data = json_decode(file_get_contents("php://input"));
            
            // Thiết lập các thuộc tính
            $this->baoHanhModel->MaBH = $id;
            
            if(isset($data->MaHD)) {
                $this->baoHanhModel->MaHD = $data->MaHD;
            }
            
            if(isset($data->MaSeri)) {
                $this->baoHanhModel->MaSeri = $data->MaSeri;
            }
            
            if(isset($data->NgayGuiBaoHanh)) {
                $this->baoHanhModel->NgayGuiBaoHanh = $data->NgayGuiBaoHanh;
            }
            
            if(isset($data->NgayTraBaoHanh)) {
                $this->baoHanhModel->NgayTraBaoHanh = $data->NgayTraBaoHanh;
            }
            
            if(isset($data->MoTa)) {
                $this->baoHanhModel->MoTa = $data->MoTa;
            }
            
            if(isset($data->TrangThai)) {
                $this->baoHanhModel->TrangThai = $data->TrangThai;
            }
            
            // Thêm MaTK nếu có
            if(isset($data->MaTK)) {
                $this->baoHanhModel->MaTK = $data->MaTK;
            }
            
            // Cập nhật bảo hành
            if($this->baoHanhModel->update()) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Cập nhật thông tin bảo hành thành công'
                ]);
            } else {
                http_response_code(200);
                echo json_encode([
                    'success' => false,
                    'message' => 'Không thể cập nhật thông tin bảo hành'
                ]);
            }
        } catch(Exception $e) {
            http_response_code(200);
            echo json_encode([
                'success' => false,
                'message' => 'Lỗi server: ' . $e->getMessage()
            ]);
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

    // Lấy bảo hành theo trạng thái
    public function getWarrantiesByStatus() {
        try {
            // Lấy trạng thái từ request
            $status = isset($_GET['status']) ? (int)$_GET['status'] : null;
            
            if ($status === null) {
                http_response_code(400);
                echo json_encode(['error' => 'Trạng thái bảo hành là bắt buộc']);
                return;
            }
            
            $result = $this->baoHanhModel->getByStatus($status);
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
                        'NgayGuiBaoHanh' => $NgayGuiBaoHanh,
                        'NgayTraBaoHanh' => $NgayTraBaoHanh,
                        'MoTa' => $MoTa,
                        'TrangThai' => (int)$TrangThai,
                        'TrangThaiText' => $this->baoHanhModel->getTrangThaiText($TrangThai)
                    );

                    array_push($warranties_arr['data'], $warranty_item);
                }

                http_response_code(200);
                echo json_encode($warranties_arr);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Không tìm thấy thông tin bảo hành nào có trạng thái ' . $status]);
            }
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
        }
    }
}
?>