<?php
require_once __DIR__ . '/../config/Database.php';

class ChiTietPhieuNhap {
    private $conn;
    private $table_name = "chitietphieunhap";

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
            error_log("Lỗi khởi tạo ChiTietPhieuNhap: " . $e->getMessage());
            throw $e;
        }
    }

    public function getByMaPhieuNhap($maPhieuNhap) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            $query = "SELECT ctpn.*, sp.TenSP, pn.NgayNhap 
                     FROM " . $this->table_name . " ctpn
                     LEFT JOIN sanpham sp ON ctpn.MaSP = sp.MaSP
                     LEFT JOIN phieunhap pn ON ctpn.MaPhieuNhap = pn.MaPhieuNhap
                     WHERE ctpn.MaPhieuNhap = :MaPhieuNhap
                     ORDER BY ctpn.MaCTPN";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaPhieuNhap", $maPhieuNhap);
            $stmt->execute();

            $chiTietList = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $chiTietList;
        } catch (PDOException $e) {
            error_log("Lỗi lấy chi tiết phiếu nhập: " . $e->getMessage());
            throw new Exception("Không thể lấy chi tiết phiếu nhập: " . $e->getMessage());
        }
    }

    public function getAll() {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            $query = "SELECT ctpn.*, sp.TenSP, pn.NgayNhap 
                     FROM " . $this->table_name . " ctpn
                     LEFT JOIN sanpham sp ON ctpn.MaSP = sp.MaSP
                     LEFT JOIN phieunhap pn ON ctpn.MaPhieuNhap = pn.MaPhieuNhap
                     ORDER BY ctpn.MaPhieuNhap, ctpn.MaCTPN";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            $chiTietList = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $chiTietList;
        } catch (PDOException $e) {
            error_log("Lỗi lấy danh sách chi tiết phiếu nhập: " . $e->getMessage());
            throw new Exception("Không thể lấy danh sách chi tiết phiếu nhập: " . $e->getMessage());
        }
    }

    public function create($data) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }
        try {
            // Không bắt đầu transaction ở đây, transaction sẽ do PhieuNhap::create quản lý
            
            // Kiểm tra phiếu nhập có tồn tại không
            $checkPhieuNhapQuery = "SELECT COUNT(*) as count FROM phieunhap WHERE MaPhieuNhap = :MaPhieuNhap";
            $checkPhieuNhapStmt = $this->conn->prepare($checkPhieuNhapQuery);
            $checkPhieuNhapStmt->bindParam(":MaPhieuNhap", $data['MaPhieuNhap']);
            $checkPhieuNhapStmt->execute();
            $phieuNhapCount = $checkPhieuNhapStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($phieuNhapCount == 0) {
                throw new Exception("Phiếu nhập với mã {$data['MaPhieuNhap']} không tồn tại.");
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
            
            // Tạo mã chi tiết phiếu nhập mới (CTPN001, CTPN002,...)
            $getMaxIdQuery = "SELECT MAX(MaCTPN) as maxId FROM " . $this->table_name;
            $getMaxIdStmt = $this->conn->prepare($getMaxIdQuery);
            $getMaxIdStmt->execute();
            $maxId = $getMaxIdStmt->fetch(PDO::FETCH_ASSOC)['maxId'];
            
            if ($maxId) {
                // Nếu đã có mã, lấy số và tăng lên 1
                preg_match('/CTPN(\d+)/', $maxId, $matches);
                if (isset($matches[1])) {
                    $nextNumber = intval($matches[1]) + 1;
                    $newMaCTPN = 'CTPN' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
                } else {
                    // Nếu mã không theo định dạng, bắt đầu từ CTPN001
                    $newMaCTPN = 'CTPN001';
                }
            } else {
                // Nếu chưa có mã nào, bắt đầu từ CTPN001
                $newMaCTPN = 'CTPN001';
            }
            
            // Tính thành tiền
            $thanhTien = $data['SoLuong'] * $data['DonGia'];
            
            // Tạo chi tiết phiếu nhập
            $query = "INSERT INTO " . $this->table_name . " 
                    (MaCTPN, MaPhieuNhap, MaSP, SoLuong, DonGia, ThanhTien) 
                    VALUES (:MaCTPN, :MaPhieuNhap, :MaSP, :SoLuong, :DonGia, :ThanhTien)";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaCTPN", $newMaCTPN);
            $stmt->bindParam(":MaPhieuNhap", $data['MaPhieuNhap']);
            $stmt->bindParam(":MaSP", $data['MaSP']);
            $stmt->bindParam(":SoLuong", $data['SoLuong']);
            $stmt->bindParam(":DonGia", $data['DonGia']);
            $stmt->bindParam(":ThanhTien", $thanhTien);
            $stmt->execute();
            
            // Cập nhật tổng tiền phiếu nhập
            $updateTotalQuery = "UPDATE phieunhap SET TongTien = TongTien + :ThanhTien WHERE MaPhieuNhap = :MaPhieuNhap";
            $updateTotalStmt = $this->conn->prepare($updateTotalQuery);
            $updateTotalStmt->bindParam(":ThanhTien", $thanhTien);
            $updateTotalStmt->bindParam(":MaPhieuNhap", $data['MaPhieuNhap']);
            $updateTotalStmt->execute();
            
            return [
                'id' => $newMaCTPN,
                'message' => 'Tạo chi tiết phiếu nhập thành công'
            ];
            
        } catch (PDOException $e) {
            // Rollback transaction nếu có lỗi
            // if ($this->conn->inTransaction()) {
            //     $this->conn->rollBack();
            // }
            error_log("Lỗi tạo chi tiết phiếu nhập: " . $e->getMessage());
            throw new Exception("Không thể tạo chi tiết phiếu nhập: " . $e->getMessage());
        }
    }

    public function update($maCTPN, $data) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }
        try {
            $fields = [];
            $params = [":MaCTPN" => $maCTPN];
            if (isset($data['MaSP'])) {
                $fields[] = "MaSP = :MaSP";
                $params[":MaSP"] = $data['MaSP'];
            }
            if (isset($data['SoLuong'])) {
                $fields[] = "SoLuong = :SoLuong";
                $params[":SoLuong"] = $data['SoLuong'];
            }
            if (isset($data['DonGia'])) {
                $fields[] = "DonGia = :DonGia";
                $params[":DonGia"] = $data['DonGia'];
            }
            // Nếu có SoLuong hoặc DonGia thì cập nhật lại ThanhTien
            if (isset($data['SoLuong']) || isset($data['DonGia'])) {
                $soLuong = isset($data['SoLuong']) ? $data['SoLuong'] : null;
                $donGia = isset($data['DonGia']) ? $data['DonGia'] : null;
                // Lấy giá trị hiện tại nếu chưa truyền vào
                $query = "SELECT SoLuong, DonGia FROM " . $this->table_name . " WHERE MaCTPN = :MaCTPN";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(":MaCTPN", $maCTPN);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($soLuong === null) $soLuong = $row['SoLuong'];
                if ($donGia === null) $donGia = $row['DonGia'];
                $fields[] = "ThanhTien = :ThanhTien";
                $params[":ThanhTien"] = $soLuong * $donGia;
            }
            if (empty($fields)) {
                throw new Exception("Không có trường nào để cập nhật.");
            }
            $sql = "UPDATE " . $this->table_name . " SET " . implode(", ", $fields) . " WHERE MaCTPN = :MaCTPN";
            $stmt = $this->conn->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            return [
                'id' => $maCTPN,
                'message' => 'Cập nhật chi tiết phiếu nhập thành công',
                'affected_rows' => $stmt->rowCount()
            ];
        } catch (PDOException $e) {
            error_log("Lỗi cập nhật chi tiết phiếu nhập: " . $e->getMessage());
            throw new Exception("Không thể cập nhật chi tiết phiếu nhập: " . $e->getMessage());
        }
    }

    public function createChiTietPhieuNhap($data) {
        try {
            $MaCTPN = uniqid('CTPN');
            $sql = "INSERT INTO chitietphieunhap (MaCTPN, MaPhieuNhap, MaSP, SoLuong, DonGia, ThanhTien) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $MaCTPN,
                $data['MaPhieuNhap'],
                $data['MaSP'],
                $data['SoLuong'],
                $data['DonGia'],
                $data['ThanhTien']
            ]);
            return [
                'status' => 'success',
                'MaCTPN' => $MaCTPN
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}