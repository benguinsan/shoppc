<?php
require_once 'config/Database.php';

class BaoHanh {
    // Database connection
    private $conn;
    private $table = 'baohanh';
    private $table_name_taikhoan = "taikhoan";

    // Properties
    public $MaBH;
    public $MaHD;
    public $MaSeri;
    public $NgayGuiBaoHanh;
    public $NgayTraBaoHanh;
    public $MoTa;
    public $TrangThai;
    public $MaNhanVien;
    public $MaTK;

    // Constructor with DB
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getAll($page = 1, $limit = 10, $searchTerm = '', $fromDate = '', $toDate = '', $status = '', $orderBy = 'MaBH', $orderDirection = 'DESC') {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            $page = max(1, (int)$page);
            $limit = max(1, (int)$limit);
            $offset = ($page - 1) * $limit;

            $orderDirection = strtoupper($orderDirection);
            if ($orderDirection !== 'ASC' && $orderDirection !== 'DESC') {
                $orderDirection = 'DESC';
            }

            $validColumns = ['MaBH', 'MaHD', 'MaSeri', 'NgayGuiBaoHanh', 'NgayTraBaoHanh', 'TrangThai'];
            if (!in_array($orderBy, $validColumns)) {
                $orderBy = 'NgayGuiBaoHanh';
            }

            // Query chính với JOIN
            $query = "SELECT bh.*, 
                            nd.HoTen as TenNhanVien
                     FROM " . $this->table . " bh
                     LEFT JOIN nguoidung nd ON bh.MaNhanVien = nd.MaNguoiDung
                     WHERE 1=1";

            // Query đếm tổng số record
            $countQuery = "SELECT COUNT(*) as total FROM " . $this->table . " bh 
                           LEFT JOIN nguoidung nd ON bh.MaNhanVien = nd.MaNguoiDung
                           WHERE 1=1";

            $params = array();

            // Thêm điều kiện tìm kiếm
            if (!empty($searchTerm)) {
                $query .= " AND (bh.MaBH LIKE :search 
                           OR bh.MaHD LIKE :search 
                           OR bh.MaSeri LIKE :search
                           OR bh.MoTa LIKE :search
                           OR nd.HoTen LIKE :search)";
                           
                $countQuery .= " AND (bh.MaBH LIKE :search 
                               OR bh.MaHD LIKE :search
                               OR bh.MaSeri LIKE :search
                               OR bh.MoTa LIKE :search
                               OR nd.HoTen LIKE :search)";
                               
                $params[':search'] = "%$searchTerm%";
            }

            // Lọc theo khoảng thời gian gửi bảo hành
            if (!empty($fromDate)) {
                $query .= " AND DATE(bh.NgayGuiBaoHanh) >= :fromDate";
                $countQuery .= " AND DATE(bh.NgayGuiBaoHanh) >= :fromDate";
                $params[':fromDate'] = $fromDate;
            }

            if (!empty($toDate)) {
                $query .= " AND DATE(bh.NgayGuiBaoHanh) <= :toDate";
                $countQuery .= " AND DATE(bh.NgayGuiBaoHanh) <= :toDate";
                $params[':toDate'] = $toDate;
            }

            // Lọc theo trạng thái
            if ($status !== '') {
                $query .= " AND bh.TrangThai = :status";
                $countQuery .= " AND bh.TrangThai = :status";
                $params[':status'] = $status;
            }

            // Thêm sắp xếp và phân trang
            $query .= " ORDER BY bh." . $orderBy . " " . $orderDirection;
            $query .= " LIMIT :limit OFFSET :offset";

            // Thực thi query đếm tổng số record
            $countStmt = $this->conn->prepare($countQuery);
            foreach($params as $key => $value) {
                if($key != ':limit' && $key != ':offset') {
                    $countStmt->bindValue($key, $value);
                }
            }
            $countStmt->execute();
            $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Thực thi query chính
            $stmt = $this->conn->prepare($query);
            foreach($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Thêm trạng thái dạng text cho mỗi bản ghi
            foreach ($records as &$record) {
                $record['TrangThaiText'] = $this->getTrangThaiText($record['TrangThai']);
            }

            return [
                'data' => $records,
                'pagination' => [
                    'total' => (int)$totalRecords,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'last_page' => ceil($totalRecords / $limit),
                    'from' => $offset + 1,
                    'to' => min($offset + $limit, $totalRecords)
                ]
            ];

        } catch (PDOException $e) {
            error_log("Lỗi lấy danh sách bảo hành: " . $e->getMessage());
            throw new Exception("Không thể lấy danh sách bảo hành: " . $e->getMessage());
        }
    }

    // Get warranty by ID
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE MaBH = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt;
    }

    // Get warranties by invoice ID
    public function getByInvoiceId($maHD) {
        $query = "SELECT * FROM " . $this->table . " WHERE MaHD = :maHD";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':maHD', $maHD);
        $stmt->execute();
        return $stmt;
    }

    // Get warranty by serial number
    public function getBySerialNumber($maSeri) {
        $query = "SELECT * FROM " . $this->table . " WHERE MaSeri = :maSeri";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':maSeri', $maSeri);
        $stmt->execute();
        return $stmt;
    }

    // Get MaNguoiDung from MaTK
    public function getMaNhanVienFromMaTK($maTK) {
        try {
            // Nếu MaTK đã là định dạng MaNguoiDung (ví dụ: NDxxxx)
            if (preg_match('/^(ND[a-f0-9]+)$/', $maTK)) {
                return $maTK; // MaTK đã là MaNguoiDung, dùng trực tiếp
            }
            
            // Nếu không, thử lấy MaNguoiDung từ bảng taikhoan
            $query = "SELECT MaNguoiDung FROM " . $this->table_name_taikhoan . " WHERE MaTK = :MaTK";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaTK", $maTK);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                return $result['MaNguoiDung'];
            } else {
                return null;
            }
        } catch (PDOException $e) {
            error_log("Lỗi lấy MaNhanVien từ MaTK: " . $e->getMessage());
            throw new Exception("Không thể lấy thông tin nhân viên từ tài khoản: " . $e->getMessage());
        }
    }

    // Create a new warranty
    public function create() {
        // Generate a unique warranty ID if not provided
        if(empty($this->MaBH)) {
            $this->MaBH = $this->generateWarrantyId();
        }

        // Process MaTK to get MaNhanVien if provided
        if(isset($this->MaTK) && !empty($this->MaTK)) {
            try {
                $this->MaNhanVien = $this->getMaNhanVienFromMaTK($this->MaTK);
                if ($this->MaNhanVien === null) {
                    throw new Exception("Không tìm thấy nhân viên với mã tài khoản {$this->MaTK}.");
                }
            } catch (Exception $e) {
                throw new Exception("Lỗi khi xử lý MaTK: " . $e->getMessage());
            }
        }

        try {
            $query = "INSERT INTO " . $this->table . " 
                      (MaBH, MaHD, MaSeri, NgayGuiBaoHanh, NgayTraBaoHanh, MoTa, TrangThai, MaNhanVien) 
                      VALUES 
                      (:MaBH, :MaHD, :MaSeri, :NgayGuiBaoHanh, :NgayTraBaoHanh, :MoTa, :TrangThai, :MaNhanVien)";

            $stmt = $this->conn->prepare($query);

            // Clean data
            $this->MaBH = htmlspecialchars(strip_tags($this->MaBH));
            $this->MaHD = htmlspecialchars(strip_tags($this->MaHD));
            $this->MaSeri = htmlspecialchars(strip_tags($this->MaSeri));
            $this->NgayGuiBaoHanh = htmlspecialchars(strip_tags($this->NgayGuiBaoHanh));
            $this->NgayTraBaoHanh = !empty($this->NgayTraBaoHanh) ? htmlspecialchars(strip_tags($this->NgayTraBaoHanh)) : null;
            $this->MoTa = htmlspecialchars(strip_tags($this->MoTa));
            $this->TrangThai = htmlspecialchars(strip_tags($this->TrangThai));
            $this->MaNhanVien = isset($this->MaNhanVien) ? htmlspecialchars(strip_tags($this->MaNhanVien)) : null;

            // Bind parameters
            $stmt->bindParam(':MaBH', $this->MaBH);
            $stmt->bindParam(':MaHD', $this->MaHD);
            $stmt->bindParam(':MaSeri', $this->MaSeri);
            $stmt->bindParam(':NgayGuiBaoHanh', $this->NgayGuiBaoHanh);
            $stmt->bindParam(':NgayTraBaoHanh', $this->NgayTraBaoHanh);
            $stmt->bindParam(':MoTa', $this->MoTa);
            $stmt->bindParam(':TrangThai', $this->TrangThai);
            $stmt->bindParam(':MaNhanVien', $this->MaNhanVien);

            // Execute query
            if($stmt->execute()) {
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Lỗi khi tạo bảo hành: " . $e->getMessage());
            throw new Exception("Không thể tạo bảo hành: " . $e->getMessage());
        }
    }

    // Update a warranty
    public function update() {
        // Process MaTK to get MaNhanVien if provided
        if(isset($this->MaTK) && !empty($this->MaTK)) {
            try {
                $this->MaNhanVien = $this->getMaNhanVienFromMaTK($this->MaTK);
                if ($this->MaNhanVien === null) {
                    throw new Exception("Không tìm thấy nhân viên với mã tài khoản {$this->MaTK}.");
                }
            } catch (Exception $e) {
                throw new Exception("Lỗi khi xử lý MaTK: " . $e->getMessage());
            }
        }

        try {
            $query = "UPDATE " . $this->table . " 
                      SET 
                      MaHD = :MaHD, 
                      MaSeri = :MaSeri, 
                      NgayGuiBaoHanh = :NgayGuiBaoHanh, 
                      NgayTraBaoHanh = :NgayTraBaoHanh, 
                      MoTa = :MoTa,
                      TrangThai = :TrangThai,
                      MaNhanVien = :MaNhanVien
                      WHERE MaBH = :MaBH";

            $stmt = $this->conn->prepare($query);

            // Clean data
            $this->MaBH = htmlspecialchars(strip_tags($this->MaBH));
            $this->MaHD = htmlspecialchars(strip_tags($this->MaHD));
            $this->MaSeri = htmlspecialchars(strip_tags($this->MaSeri));
            $this->NgayGuiBaoHanh = htmlspecialchars(strip_tags($this->NgayGuiBaoHanh));
            $this->NgayTraBaoHanh = !empty($this->NgayTraBaoHanh) ? htmlspecialchars(strip_tags($this->NgayTraBaoHanh)) : null;
            $this->MoTa = htmlspecialchars(strip_tags($this->MoTa));
            $this->TrangThai = htmlspecialchars(strip_tags($this->TrangThai));
            $this->MaNhanVien = isset($this->MaNhanVien) ? htmlspecialchars(strip_tags($this->MaNhanVien)) : null;

            // Bind parameters
            $stmt->bindParam(':MaBH', $this->MaBH);
            $stmt->bindParam(':MaHD', $this->MaHD);
            $stmt->bindParam(':MaSeri', $this->MaSeri);
            $stmt->bindParam(':NgayGuiBaoHanh', $this->NgayGuiBaoHanh);
            $stmt->bindParam(':NgayTraBaoHanh', $this->NgayTraBaoHanh);
            $stmt->bindParam(':MoTa', $this->MoTa);
            $stmt->bindParam(':TrangThai', $this->TrangThai);
            $stmt->bindParam(':MaNhanVien', $this->MaNhanVien);

            // Execute query
            if($stmt->execute()) {
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Lỗi khi cập nhật bảo hành: " . $e->getMessage());
            throw new Exception("Không thể cập nhật bảo hành: " . $e->getMessage());
        }
    }

    // Delete a warranty
    public function delete() {
        $query = "DELETE FROM " . $this->table . " WHERE MaBH = :MaBH";
        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->MaBH = htmlspecialchars(strip_tags($this->MaBH));
        
        // Bind parameter
        $stmt->bindParam(':MaBH', $this->MaBH);

        // Execute query
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Get warranties by status
    public function getByStatus($trangThai) {
        $query = "SELECT * FROM " . $this->table . " WHERE TrangThai = :trangThai";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':trangThai', $trangThai);
        $stmt->execute();
        return $stmt;
    }

    // Get text representation of status
    public function getTrangThaiText($trangThai) {
        switch($trangThai) {
            case 0:
                return 'Đã hủy';
            case 1:
                return 'Chờ xử lý';
            case 2:
                return 'Đang xử lý';
            case 3:
                return 'Đã hoàn thành';
            default:
                return 'Không xác định';
        }
    }

    // Generate a unique warranty ID
    public function generateWarrantyId() {
        // Get current max ID
        $query = "SELECT MAX(MaBH) as max_id FROM " . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row['max_id']) {
            // Extract number part if MaBH is in format "BHxxx"
            if (preg_match('/^BH(\d+)$/', $row['max_id'], $matches)) {
                $nextId = intval($matches[1]) + 1;
                return 'BH' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
            }
        }
        
        // Default if no existing IDs or different format
        return 'BH001';
    }
}
?>