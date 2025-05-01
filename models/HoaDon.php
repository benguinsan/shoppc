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

    public function create($data) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            // Kiểm tra người dùng có tồn tại không
            $checkUserQuery = "SELECT COUNT(*) as count FROM nguoidung WHERE MaNguoiDung = :MaNguoiDung";
            $checkUserStmt = $this->conn->prepare($checkUserQuery);
            $checkUserStmt->bindParam(":MaNguoiDung", $data['MaNguoiDung']);
            $checkUserStmt->execute();
            $userCount = $checkUserStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($userCount == 0) {
                throw new Exception("Người dùng với mã {$data['MaNguoiDung']} không tồn tại.");
            }
            
            // Kiểm tra nhân viên có tồn tại không (nếu có)
            if (isset($data['MaNhanVien']) && $data['MaNhanVien'] !== null) {
                $checkStaffQuery = "SELECT COUNT(*) as count FROM nguoidung WHERE MaNguoiDung = :MaNhanVien";
                $checkStaffStmt = $this->conn->prepare($checkStaffQuery);
                $checkStaffStmt->bindParam(":MaNhanVien", $data['MaNhanVien']);
                $checkStaffStmt->execute();
                $staffCount = $checkStaffStmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($staffCount == 0) {
                    throw new Exception("Nhân viên với mã {$data['MaNhanVien']} không tồn tại.");
                }
            }

            // Kiểm tra cấu trúc của bảng hóa đơn
            $tableStructureQuery = "DESCRIBE " . $this->table_name;
            $tableStructureStmt = $this->conn->prepare($tableStructureQuery);
            $tableStructureStmt->execute();
            $tableStructure = $tableStructureStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Xác định tên và đặc điểm của khóa chính
            $primaryKey = null;
            $isAutoIncrement = false;
            
            foreach ($tableStructure as $column) {
                if ($column['Key'] == 'PRI') {
                    $primaryKey = $column['Field'];
                    $isAutoIncrement = (strpos(strtolower($column['Extra']), 'auto_increment') !== false);
                    break;
                }
            }
            
            if (!$primaryKey) {
                throw new Exception("Không thể xác định khóa chính cho bảng hóa đơn.");
            }

            // Bắt đầu transaction
            $this->conn->beginTransaction();

            // Tạo câu lệnh INSERT phù hợp
            $columns = [];
            $placeholders = [];
            $params = [];
            
            // Thêm khóa chính nếu cần (không phải auto increment)
            if (!$isAutoIncrement && !isset($data[$primaryKey])) {
                // Tạo mã hóa đơn mới nếu khóa chính là MaHD
                if ($primaryKey === 'MaHD') {
                    // Lấy mã HD lớn nhất hiện tại
                    $maxIdQuery = "SELECT MAX(MaHD) as maxId FROM " . $this->table_name;
                    $maxIdStmt = $this->conn->prepare($maxIdQuery);
                    $maxIdStmt->execute();
                    $maxId = $maxIdStmt->fetch(PDO::FETCH_ASSOC)['maxId'];
                    
                    // Tạo mã mới
                    if ($maxId) {
                        // Nếu định dạng mã là HD001, HD002, ...
                        if (preg_match('/^HD(\d+)$/', $maxId, $matches)) {
                            $newId = 'HD' . str_pad((intval($matches[1]) + 1), 3, '0', STR_PAD_LEFT);
                        } else {
                            // Nếu mã là số nguyên
                            $newId = intval($maxId) + 1;
                        }
                    } else {
                        // Nếu chưa có mã nào
                        $newId = 'HD001';
                    }
                    
                    $data[$primaryKey] = $newId;
                } else {
                    throw new Exception("Khóa chính không phải auto_increment và không được cung cấp.");
                }
            }
            
            // Thêm các column vào câu lệnh INSERT
            foreach ($data as $key => $value) {
                // Bỏ qua các khóa không phải field trong DB
                if ($key === 'ChiTietHoaDon') continue;
                
                $columns[] = $key;
                $placeholders[] = ":$key";
                $params[":$key"] = $value;
            }
            
            // Đảm bảo tất cả các trường cần thiết được điền
            if (!in_array('MaNguoiDung', $columns)) {
                throw new Exception("Thiếu thông tin MaNguoiDung.");
            }
            
            if (!in_array('NgayLap', $columns)) {
                $columns[] = 'NgayLap';
                $placeholders[] = ':NgayLap';
                $params[':NgayLap'] = date('Y-m-d H:i:s');
            }
            
            if (!in_array('TongTien', $columns)) {
                $columns[] = 'TongTien';
                $placeholders[] = ':TongTien';
                $params[':TongTien'] = 0;
            }
            
            if (!in_array('TrangThai', $columns)) {
                $columns[] = 'TrangThai';
                $placeholders[] = ':TrangThai';
                $params[':TrangThai'] = 1;
            }
            
            // Tạo câu lệnh INSERT
            $query = "INSERT INTO " . $this->table_name . " (" . implode(", ", $columns) . ") 
                     VALUES (" . implode(", ", $placeholders) . ")";
            
            $stmt = $this->conn->prepare($query);
            
            // Bind các tham số
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            
            // Lấy id của hóa đơn vừa tạo
            if ($isAutoIncrement) {
                $maHD = $this->conn->lastInsertId();
            } else {
                $maHD = $data[$primaryKey];
            }

            // Commit transaction
            $this->conn->commit();
            
            return [
                'id' => $maHD,
                'message' => 'Tạo hóa đơn thành công'
            ];
            
        } catch (PDOException $e) {
            // Rollback transaction nếu có lỗi
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Lỗi tạo hóa đơn: " . $e->getMessage());
            throw new Exception("Không thể tạo hóa đơn: " . $e->getMessage());
        }
    }
} 