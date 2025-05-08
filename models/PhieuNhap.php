<?php
require_once __DIR__ . '/../config/Database.php';

class PhieuNhap {
    private $conn;
    private $table_name = "phieunhap";
    private $primary_key = "MaPhieuNhap";

    public function __construct($connection = null) {
        try {
            if ($connection !== null) {
                $this->conn = $connection;
            } else {
                $db = new Database();
                $this->conn = $db->getConnection();
            }

            if ($this->conn === null) {
                throw new Exception("Không thể kết nối đến cơ sở dữ liệu");
            }
        } catch (Exception $e) {
            error_log("Lỗi khởi tạo PhieuNhap: " . $e->getMessage());
            throw $e;
        }
    }

    public function getAll($page = 1, $limit = 10, $searchTerm = '', $fromDate = '', $toDate = '', $status = '', $orderBy = 'MaPhieuNhap', $orderDirection = 'DESC') {
        try {
            $page = max(1, (int)$page);
            $limit = max(1, (int)$limit);
            $offset = ($page - 1) * $limit;

            $orderDirection = strtoupper($orderDirection) === 'ASC' ? 'ASC' : 'DESC';
            $validColumns = ['MaPhieuNhap', 'NgayNhap', 'TongTien', 'TrangThai'];
            $orderBy = in_array($orderBy, $validColumns) ? $orderBy : 'NgayNhap';

            $query = "SELECT pn.*, 
                            ncc.TenNCC as TenNhaCungCap,
                            nv.HoTen as TenNhanVien
                    FROM " . $this->table_name . " pn
                    LEFT JOIN nhacungcap ncc ON pn.MaNCC = ncc.MaNCC
                    LEFT JOIN nguoidung nv ON pn.MaNhanVien = nv.MaNguoiDung
                    WHERE 1=1";

            $countQuery = "SELECT COUNT(*) as total FROM " . $this->table_name . " pn
                    LEFT JOIN nhacungcap ncc ON pn.MaNCC = ncc.MaNCC
                    LEFT JOIN nguoidung nv ON pn.MaNhanVien = nv.MaNguoiDung
                    WHERE 1=1";

            $params = [];

            if (!empty($searchTerm)) {
                $query .= " AND (pn.MaPhieuNhap LIKE :search 
                           OR ncc.TenNCC LIKE :search 
                           OR nv.HoTen LIKE :search)";
                $countQuery .= " AND (pn.MaPhieuNhap LIKE :search 
                               OR ncc.TenNCC LIKE :search
                               OR nv.HoTen LIKE :search)";
                $params[':search'] = "%$searchTerm%";
            }

            if (!empty($fromDate)) {
                $query .= " AND DATE(pn.NgayNhap) >= :fromDate";
                $countQuery .= " AND DATE(pn.NgayNhap) >= :fromDate";
                $params[':fromDate'] = $fromDate;
            }

            if (!empty($toDate)) {
                $query .= " AND DATE(pn.NgayNhap) <= :toDate";
                $countQuery .= " AND DATE(pn.NgayNhap) <= :toDate";
                $params[':toDate'] = $toDate;
            }

            if ($status !== '') {
                $query .= " AND pn.TrangThai = :status";
                $countQuery .= " AND pn.TrangThai = :status";
                $params[':status'] = $status;
            }

            $query .= " ORDER BY pn." . $orderBy . " " . $orderDirection;
            $query .= " LIMIT :limit OFFSET :offset";

            $countStmt = $this->conn->prepare($countQuery);
            foreach($params as $key => $value) {
                if($key != ':limit' && $key != ':offset') {
                    $countStmt->bindValue($key, $value);
                }
            }
            $countStmt->execute();
            $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $this->conn->prepare($query);
            foreach($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
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
            error_log("Lỗi lấy danh sách phiếu nhập: " . $e->getMessage());
            throw new Exception("Không thể lấy danh sách phiếu nhập");
        }
    }
    
    public function getAllNhanVien() {
        try {
            // Kiểm tra cấu trúc bảng
            $checkTableQuery = "SHOW COLUMNS FROM nguoidung";
            $checkStmt = $this->conn->prepare($checkTableQuery);
            $checkStmt->execute();
            $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
            
            $query = "SELECT MaNguoiDung, HoTen, Email, SDT FROM nguoidung";
            
            // Nếu có trường MaNhomQuyen, thêm điều kiện lọc
            if (in_array('MaNhomQuyen', $columns)) {
                $query .= " WHERE MaNhomQuyen = 'NHANVIEN'";
            } 
            // Nếu có trường TrangThai, chỉ lấy người dùng đang hoạt động
            if (in_array('TrangThai', $columns)) {
                $query .= (strpos($query, 'WHERE') !== false) ? " AND TrangThai = 1" : " WHERE TrangThai = 1";
            }
            
            $query .= " ORDER BY HoTen ASC";
            
            // Log query để debug
            error_log("Query getAllNhanVien: " . $query);
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Nếu không có kết quả, lấy tất cả người dùng
            if (empty($result)) {
                $query = "SELECT MaNguoiDung, HoTen, Email, SDT FROM nguoidung ORDER BY HoTen ASC";
                error_log("Thử lại với query: " . $query);
                $stmt = $this->conn->prepare($query);
                $stmt->execute();
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return [
                'status' => 'success',
                'data' => $result
            ];
        } catch (PDOException $e) {
            error_log("Lỗi lấy danh sách nhân viên: " . $e->getMessage());
            throw new Exception("Không thể lấy danh sách nhân viên: " . $e->getMessage());
        }
    }
} 