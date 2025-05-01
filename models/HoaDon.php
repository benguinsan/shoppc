<?php
require_once __DIR__ . '/../config/Database.php';

class HoaDon {
    private $conn;
    private $table_name = "hoadon";
    private $lastInsertedId;

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
            error_log("Lỗi khởi tạo HoaDon: " . $e->getMessage());
            throw $e;
        }
    }

    public function getAll($page = 1, $limit = 10, $searchTerm = '', $fromDate = '', $toDate = '', $status = '', $orderBy = 'MaHD', $orderDirection = 'ASC') {
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

            $validColumns = ['MaHD', 'NgayLap', 'TongTien', 'TrangThai'];
            if (!in_array($orderBy, $validColumns)) {
                $orderBy = 'NgayLap';
            }

            // Query chính với JOIN
            $query = "SELECT hd.*, 
                            nd.HoTen as TenNguoiDung,
                            nv.HoTen as TenNhanVien
                     FROM " . $this->table_name . " hd
                     LEFT JOIN nguoidung nd ON hd.MaNguoiDung = nd.MaNguoiDung
                     LEFT JOIN nguoidung nv ON hd.MaNhanVien = nv.MaNguoiDung
                     WHERE 1=1";

            // Query đếm tổng số record
            $countQuery = "SELECT COUNT(*) as total FROM " . $this->table_name . " hd WHERE 1=1";

            $params = array();

            // Thêm điều kiện tìm kiếm
            if (!empty($searchTerm)) {
                $query .= " AND (hd.MaHD LIKE :search 
                           OR nd.HoTen LIKE :search 
                           OR nv.HoTen LIKE :search)";
                $countQuery .= " AND (hd.MaHD LIKE :search)";
                $params[':search'] = "%$searchTerm%";
            }

            // Lọc theo khoảng thời gian
            if (!empty($fromDate)) {
                $query .= " AND DATE(hd.NgayLap) >= :fromDate";
                $countQuery .= " AND DATE(hd.NgayLap) >= :fromDate";
                $params[':fromDate'] = $fromDate;
            }

            if (!empty($toDate)) {
                $query .= " AND DATE(hd.NgayLap) <= :toDate";
                $countQuery .= " AND DATE(hd.NgayLap) <= :toDate";
                $params[':toDate'] = $toDate;
            }

            // Lọc theo trạng thái
            if ($status !== '') {
                $query .= " AND hd.TrangThai = :status";
                $countQuery .= " AND hd.TrangThai = :status";
                $params[':status'] = $status;
            }

            // Thêm sắp xếp và phân trang
            $query .= " ORDER BY hd." . $orderBy . " " . $orderDirection;
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
            error_log("Lỗi lấy danh sách hóa đơn: " . $e->getMessage());
            throw new Exception("Không thể lấy danh sách hóa đơn: " . $e->getMessage());
        }
    }

    public function getById($maHD) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            $query = "SELECT hd.*, 
                            nd.HoTen as TenNguoiDung,
                            nv.HoTen as TenNhanVien
                     FROM " . $this->table_name . " hd
                     LEFT JOIN nguoidung nd ON hd.MaNguoiDung = nd.MaNguoiDung
                     LEFT JOIN nguoidung nv ON hd.MaNhanVien = nv.MaNguoiDung
                     WHERE hd.MaHD = :MaHD";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaHD", $maHD);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi lấy thông tin hóa đơn: " . $e->getMessage());
            throw new Exception("Không thể lấy thông tin hóa đơn: " . $e->getMessage());
        }
    }

    public function getTrangThaiText($trangThai) {
        switch($trangThai) {
            case 0:
                return 'Đã hủy';
            case 1:
                return 'Chờ xác nhận';
            case 2:
                return 'Đã xác nhận';
            case 3:
                return 'Đang giao hàng';
            case 4:
                return 'Đã giao hàng';
            default:
                return 'Không xác định';
        }
    }
} 