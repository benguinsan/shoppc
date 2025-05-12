<?php
require_once 'config/Database.php';

class BaoHanh {
    // Database connection
    private $conn;
    private $table = 'baohanh';

    // Properties
    public $MaBH;
    public $MaHD;
    public $MaSeri;
    public $NgayMua;
    public $HanBaoHanh;
    public $MoTa;

    // Constructor with DB
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Get all warranties
    public function getAll() {
        $query = "SELECT * FROM " . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
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

    // Create a new warranty
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (MaBH, MaHD, MaSeri, NgayMua, HanBaoHanh, MoTa) 
                  VALUES 
                  (:MaBH, :MaHD, :MaSeri, :NgayMua, :HanBaoHanh, :MoTa)";

        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->MaBH = htmlspecialchars(strip_tags($this->MaBH));
        $this->MaHD = htmlspecialchars(strip_tags($this->MaHD));
        $this->MaSeri = htmlspecialchars(strip_tags($this->MaSeri));
        $this->NgayMua = htmlspecialchars(strip_tags($this->NgayMua));
        $this->HanBaoHanh = htmlspecialchars(strip_tags($this->HanBaoHanh));
        $this->MoTa = htmlspecialchars(strip_tags($this->MoTa));

        // Bind parameters
        $stmt->bindParam(':MaBH', $this->MaBH);
        $stmt->bindParam(':MaHD', $this->MaHD);
        $stmt->bindParam(':MaSeri', $this->MaSeri);
        $stmt->bindParam(':NgayMua', $this->NgayMua);
        $stmt->bindParam(':HanBaoHanh', $this->HanBaoHanh);
        $stmt->bindParam(':MoTa', $this->MoTa);

        // Execute query
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Update a warranty
    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET 
                  MaHD = :MaHD, 
                  MaSeri = :MaSeri, 
                  NgayMua = :NgayMua, 
                  HanBaoHanh = :HanBaoHanh, 
                  MoTa = :MoTa 
                  WHERE MaBH = :MaBH";

        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->MaBH = htmlspecialchars(strip_tags($this->MaBH));
        $this->MaHD = htmlspecialchars(strip_tags($this->MaHD));
        $this->MaSeri = htmlspecialchars(strip_tags($this->MaSeri));
        $this->NgayMua = htmlspecialchars(strip_tags($this->NgayMua));
        $this->HanBaoHanh = htmlspecialchars(strip_tags($this->HanBaoHanh));
        $this->MoTa = htmlspecialchars(strip_tags($this->MoTa));

        // Bind parameters
        $stmt->bindParam(':MaBH', $this->MaBH);
        $stmt->bindParam(':MaHD', $this->MaHD);
        $stmt->bindParam(':MaSeri', $this->MaSeri);
        $stmt->bindParam(':NgayMua', $this->NgayMua);
        $stmt->bindParam(':HanBaoHanh', $this->HanBaoHanh);
        $stmt->bindParam(':MoTa', $this->MoTa);

        // Execute query
        if($stmt->execute()) {
            return true;
        }
        return false;
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

    // Check if warranty is still valid
    public function isValid() {
        $currentDate = date('Y-m-d');
        return $currentDate <= $this->HanBaoHanh;
    }

    // Generate a unique warranty ID
    public function generateWarrantyId() {
        return 'BH' . uniqid();
    }

    public function search($page = 1, $limit = 10, $searchTerm = '', $fromDate = '', $toDate = '', $orderBy = 'MaBH', $orderDirection = 'ASC') {
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

            $validColumns = ['MaBH', 'NgayMua', 'HanBaoHanh'];
            if (!in_array($orderBy, $validColumns)) {
                $orderBy = 'NgayMua';
            }

            // Query chính với JOIN
            $query = "SELECT bh.*, 
                            hd.MaHD,
                            sp.TenSP,
                            sp.MaSeri
                     FROM " . $this->table . " bh
                     LEFT JOIN hoadon hd ON bh.MaHD = hd.MaHD
                     LEFT JOIN sanpham sp ON bh.MaSeri = sp.MaSeri
                     WHERE 1=1";

            // Query đếm tổng số record
            $countQuery = "SELECT COUNT(*) as total FROM " . $this->table . " bh 
                           LEFT JOIN hoadon hd ON bh.MaHD = hd.MaHD
                           LEFT JOIN sanpham sp ON bh.MaSeri = sp.MaSeri
                           WHERE 1=1";

            $params = array();

            // Thêm điều kiện tìm kiếm
            if (!empty($searchTerm)) {
                $query .= " AND (bh.MaBH LIKE :search 
                           OR bh.MaHD LIKE :search 
                           OR bh.MaSeri LIKE :search
                           OR sp.TenSP LIKE :search)";
                           
                $countQuery .= " AND (bh.MaBH LIKE :search 
                               OR bh.MaHD LIKE :search
                               OR bh.MaSeri LIKE :search
                               OR sp.TenSP LIKE :search)";
                               
                $params[':search'] = "%$searchTerm%";
            }

            // Lọc theo khoảng thời gian mua
            if (!empty($fromDate)) {
                $query .= " AND DATE(bh.NgayMua) >= :fromDate";
                $countQuery .= " AND DATE(bh.NgayMua) >= :fromDate";
                $params[':fromDate'] = $fromDate;
            }

            if (!empty($toDate)) {
                $query .= " AND DATE(bh.NgayMua) <= :toDate";
                $countQuery .= " AND DATE(bh.NgayMua) <= :toDate";
                $params[':toDate'] = $toDate;
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
            error_log("Lỗi tìm kiếm bảo hành: " . $e->getMessage());
            throw new Exception("Không thể tìm kiếm bảo hành: " . $e->getMessage());
        }
    }
   
}
?>