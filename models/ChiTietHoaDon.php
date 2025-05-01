<?php
require_once __DIR__ . '/../config/Database.php';

class ChiTietHoaDon {
    private $conn;
    private $table_name = "chitiethoadon";

    public function __construct($connection = null) {
        try {
            if ($connection !== null) {
                $this->conn = $connection;
            } else {
                $db = new Database();
                $this->conn = $db->getConnection();
            }

            if ($this->conn === null) {
                throw new Exception("Không thể kết nối đến cơ sở dữ liệu. Vui lòng kiểm tra cấu hình database.");
            }
        } catch (Exception $e) {
            error_log("Lỗi khởi tạo ChiTietHoaDon: " . $e->getMessage());
            throw $e;
        }
    }

    public function getByMaHD($maHD) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        
        try {
            $query = "SELECT ct.*, sp.TenSP 
                     FROM " . $this->table_name . " ct
                     LEFT JOIN sanpham sp ON ct.MaSP = sp.MaSP
                     WHERE ct.MaHD = :MaHD";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaHD", $maHD);
            $stmt->execute();

            $chiTietList = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $chiTietList;
        } catch (PDOException $e) {
            error_log("Lỗi lấy chi tiết hóa đơn: " . $e->getMessage());
            throw new Exception("Không thể lấy chi tiết hóa đơn: " . $e->getMessage());
        }
    }
    
    public function getAll() {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            $query = "SELECT ct.*, sp.TenSP, hd.NgayLap, hd.TrangThai 
                     FROM " . $this->table_name . " ct
                     LEFT JOIN sanpham sp ON ct.MaSP = sp.MaSP
                     LEFT JOIN hoadon hd ON ct.MaHD = hd.MaHD
                     ORDER BY ct.MaHD, ct.MaCTHD";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            $chiTietList = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $chiTietList;
        } catch (PDOException $e) {
            error_log("Lỗi lấy danh sách chi tiết hóa đơn: " . $e->getMessage());
            throw new Exception("Không thể lấy danh sách chi tiết hóa đơn: " . $e->getMessage());
        }
    }
    
    public function create($data) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            // Bắt đầu transaction
            $this->conn->beginTransaction();
            
            // Kiểm tra hóa đơn có tồn tại không
            $checkInvoiceQuery = "SELECT COUNT(*) as count FROM hoadon WHERE MaHD = :MaHD";
            $checkInvoiceStmt = $this->conn->prepare($checkInvoiceQuery);
            $checkInvoiceStmt->bindParam(":MaHD", $data['MaHD']);
            $checkInvoiceStmt->execute();
            $invoiceCount = $checkInvoiceStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($invoiceCount == 0) {
                throw new Exception("Hóa đơn với mã {$data['MaHD']} không tồn tại.");
            }
            
            // Kiểm tra sản phẩm có tồn tại không
            $checkProductQuery = "SELECT COUNT(*) as count FROM sanpham WHERE MaSP = :MaSP";
            $checkProductStmt = $this->conn->prepare($checkProductQuery);
            $checkProductStmt->bindParam(":MaSP", $data['MaSP']);
            $checkProductStmt->execute();
            $productCount = $checkProductStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($productCount == 0) {
                throw new Exception("Sản phẩm với mã {$data['MaSP']} không tồn tại.");
            }
            
            // Kiểm tra mã seri đã được sử dụng chưa
            $checkUsedSeriQuery = "SELECT COUNT(*) as count FROM chitiethoadon WHERE MaSeri = :MaSeri";
            $checkUsedSeriStmt = $this->conn->prepare($checkUsedSeriQuery);
            $checkUsedSeriStmt->bindParam(":MaSeri", $data['MaSeri']);
            $checkUsedSeriStmt->execute();
            $usedSeriCount = $checkUsedSeriStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($usedSeriCount > 0) {
                throw new Exception("Mã seri {$data['MaSeri']} đã được sử dụng trong một hóa đơn khác.");
            }
            
            // Tạo mã chi tiết hóa đơn mới (CTHD001, CTHD002,...)
            $getMaxIdQuery = "SELECT MAX(MaCTHD) as maxId FROM " . $this->table_name;
            $getMaxIdStmt = $this->conn->prepare($getMaxIdQuery);
            $getMaxIdStmt->execute();
            $maxId = $getMaxIdStmt->fetch(PDO::FETCH_ASSOC)['maxId'];
            
            if ($maxId) {
                // Nếu đã có mã, lấy số và tăng lên 1
                preg_match('/CTHD(\d+)/', $maxId, $matches);
                if (isset($matches[1])) {
                    $nextNumber = intval($matches[1]) + 1;
                    $newMaCTHD = 'CTHD' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
                } else {
                    // Nếu mã không theo định dạng, bắt đầu từ CTHD001
                    $newMaCTHD = 'CTHD001';
                }
            } else {
                // Nếu chưa có mã nào, bắt đầu từ CTHD001
                $newMaCTHD = 'CTHD001';
            }
            
            // Tạo chi tiết hóa đơn
            $query = "INSERT INTO " . $this->table_name . " 
                    (MaCTHD, MaHD, MaSP, MaSeri, DonGia) 
                    VALUES (:MaCTHD, :MaHD, :MaSP, :MaSeri, :DonGia)";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaCTHD", $newMaCTHD);
            $stmt->bindParam(":MaHD", $data['MaHD']);
            $stmt->bindParam(":MaSP", $data['MaSP']);
            $stmt->bindParam(":MaSeri", $data['MaSeri']);
            $stmt->bindParam(":DonGia", $data['DonGia']);
            $stmt->execute();
            
            // Cập nhật tổng tiền hóa đơn
            $updateTotalQuery = "UPDATE hoadon SET TongTien = TongTien + :DonGia WHERE MaHD = :MaHD";
            $updateTotalStmt = $this->conn->prepare($updateTotalQuery);
            $updateTotalStmt->bindParam(":DonGia", $data['DonGia']);
            $updateTotalStmt->bindParam(":MaHD", $data['MaHD']);
            $updateTotalStmt->execute();
            
            // Commit transaction
            $this->conn->commit();
            
            return [
                'id' => $newMaCTHD,
                'message' => 'Tạo chi tiết hóa đơn thành công'
            ];
            
        } catch (PDOException $e) {
            // Rollback transaction nếu có lỗi
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Lỗi tạo chi tiết hóa đơn: " . $e->getMessage());
            throw new Exception("Không thể tạo chi tiết hóa đơn: " . $e->getMessage());
        }
    }
    
    public function update($maCTHD, $data) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            // Kiểm tra chi tiết hóa đơn có tồn tại không
            $checkQuery = "SELECT ct.*, hd.TongTien 
                           FROM " . $this->table_name . " ct 
                           JOIN hoadon hd ON ct.MaHD = hd.MaHD
                           WHERE ct.MaCTHD = :MaCTHD";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(":MaCTHD", $maCTHD);
            $checkStmt->execute();
            $chiTietHienTai = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$chiTietHienTai) {
                throw new Exception("Chi tiết hóa đơn với mã {$maCTHD} không tồn tại.");
            }
            
            // Bắt đầu transaction
            $this->conn->beginTransaction();
            
            // Nếu thay đổi MaSeri, kiểm tra xem seri mới đã được sử dụng chưa
            if (isset($data['MaSeri']) && $data['MaSeri'] !== $chiTietHienTai['MaSeri']) {
                $checkSeriQuery = "SELECT COUNT(*) as count FROM " . $this->table_name . " WHERE MaSeri = :MaSeri";
                $checkSeriStmt = $this->conn->prepare($checkSeriQuery);
                $checkSeriStmt->bindParam(":MaSeri", $data['MaSeri']);
                $checkSeriStmt->execute();
                $seriCount = $checkSeriStmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($seriCount > 0) {
                    throw new Exception("Mã seri {$data['MaSeri']} đã được sử dụng trong một hóa đơn khác.");
                }
            }
            
            // Kiểm tra sản phẩm nếu có thay đổi
            if (isset($data['MaSP']) && $data['MaSP'] !== $chiTietHienTai['MaSP']) {
                $checkProductQuery = "SELECT COUNT(*) as count FROM sanpham WHERE MaSP = :MaSP";
                $checkProductStmt = $this->conn->prepare($checkProductQuery);
                $checkProductStmt->bindParam(":MaSP", $data['MaSP']);
                $checkProductStmt->execute();
                $productCount = $checkProductStmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($productCount == 0) {
                    throw new Exception("Sản phẩm với mã {$data['MaSP']} không tồn tại.");
                }
            }
            
            // Xây dựng câu lệnh UPDATE
            $setClause = [];
            $params = [':MaCTHD' => $maCTHD];
            
            // Các trường có thể cập nhật
            $allowedFields = ['MaSP', 'MaSeri', 'DonGia'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $setClause[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }
            
            // Nếu không có trường nào được cập nhật
            if (empty($setClause)) {
                throw new Exception("Không có thông tin nào được cập nhật.");
            }
            
            // Thực hiện cập nhật chi tiết hóa đơn
            $query = "UPDATE " . $this->table_name . " SET " . implode(", ", $setClause) . " WHERE MaCTHD = :MaCTHD";
            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            
            // Nếu có thay đổi đơn giá, cập nhật tổng tiền hóa đơn
            if (isset($data['DonGia']) && $data['DonGia'] != $chiTietHienTai['DonGia']) {
                $donGiaCu = $chiTietHienTai['DonGia'];
                $donGiaMoi = $data['DonGia'];
                $chenhLech = $donGiaMoi - $donGiaCu;
                
                $updateTotalQuery = "UPDATE hoadon SET TongTien = TongTien + :ChenhLech WHERE MaHD = :MaHD";
                $updateTotalStmt = $this->conn->prepare($updateTotalQuery);
                $updateTotalStmt->bindParam(":ChenhLech", $chenhLech);
                $updateTotalStmt->bindParam(":MaHD", $chiTietHienTai['MaHD']);
                $updateTotalStmt->execute();
            }
            
            // Commit transaction
            $this->conn->commit();
            
            return [
                'id' => $maCTHD,
                'message' => 'Cập nhật chi tiết hóa đơn thành công',
                'affected_rows' => $stmt->rowCount()
            ];
            
        } catch (PDOException $e) {
            // Rollback transaction nếu có lỗi
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Lỗi cập nhật chi tiết hóa đơn: " . $e->getMessage());
            throw new Exception("Không thể cập nhật chi tiết hóa đơn: " . $e->getMessage());
        }
    }
} 