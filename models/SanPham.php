<?php

require_once __DIR__ . '/../config/Database.php';

class SanPham
{

    private $conn;
    private $table_name = "sanpham";
    private $lastInsertedId;

    public function __construct($connection = null)
    {
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
            error_log("Lỗi khởi tạo SanPham: " . $e->getMessage());
            throw $e;
        }
    }

    public function getBanner()
    {
        try {
            $query = "SELECT * FROM " . $this->table_name . " 
                 WHERE TrangThai = TRUE 
                 ORDER BY MaSP DESC 
                 LIMIT 10";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi truy vấn banner: " . $e->getMessage());
            throw new Exception("Có lỗi xảy ra khi lấy banner: " . $e->getMessage());
        }
    }

    public function getAllByPage($page = 1, $limit = 15)
    {
        try {
            // Calculate offset
            $offset = ($page - 1) * $limit;

            // Get total records for pagination
            $total_query = "SELECT COUNT(*) as total FROM " . $this->table_name;
            $total_stmt = $this->conn->prepare($total_query);
            $total_stmt->execute();
            $total_row = $total_stmt->fetch(PDO::FETCH_ASSOC);
            $total_records = $total_row['total'];

            // Get products with pagination
            $query = "SELECT * FROM " . $this->table_name . " 
                     ORDER BY MaSP DESC 
                     LIMIT :limit OFFSET :offset";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'data' => $records,
                'pagination' => [
                    'total' => $total_records,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'last_page' => ceil($total_records / $limit),
                    'from' => $offset + 1,
                    'to' => min($offset + $limit, $total_records)
                ]
            ];
        } catch (PDOException $e) {
            error_log("Lỗi truy vấn sản phẩm: " . $e->getMessage());
            throw new Exception("Có lỗi xảy ra khi lấy danh sách sản phẩm: " . $e->getMessage());
        }
    }

    public function getSanPhamByMaSP($maSP)
    {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE MaSP = :MaSP";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':MaSP', $maSP, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi truy vấn sản phẩm theo MaSP: " . $e->getMessage());
            throw new Exception("Có lỗi xảy ra khi lấy sản phẩm theo MaSP: " . $e->getMessage());
        }
    }

    public function getFilter($filter, $page = 1, $limit = 15)
    {
        try {
            $conditions = [];
            $params = [];

            // Base query for counting total records
            $countQuery = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE TrangThai = TRUE";

            // Base query for fetching records
            $query = "SELECT * FROM " . $this->table_name . " WHERE TrangThai = TRUE";

            // Add TenSP condition if present
            if (isset($filter['TenSP']) && $filter['TenSP'] !== '') {
                $conditions[] = "TenSP LIKE :TenSP";
                $params[':TenSP'] = '%' . $filter['TenSP'] . '%';
            }

            // Add MaLoaiSP condition if present
            if (isset($filter['MaLoaiSP']) && $filter['MaLoaiSP'] !== '') {
                if (strpos($filter['MaLoaiSP'], ',') !== false) {
                    // Split MaLoaiSP values by comma
                    $loaiSpValues = explode(',', $filter['MaLoaiSP']);
                    $loaiSpConditions = [];
                    foreach ($loaiSpValues as $index => $value) {
                        $paramName = ':MaLoaiSP' . $index;
                        $loaiSpConditions[] = "MaLoaiSP = $paramName";
                        $params[$paramName] = trim($value);
                    }
                    $conditions[] = '(' . implode(' OR ', $loaiSpConditions) . ')';
                } else {
                    // Single category
                    $conditions[] = "MaLoaiSP = :MaLoaiSP";
                    $params[':MaLoaiSP'] = $filter['MaLoaiSP'];
                }
            }

            // Add RAM condition if present
            if (isset($filter['RAM']) && $filter['RAM'] !== '') {
                // Split RAM values by comma
                $ramValues = explode(',', $filter['RAM']);
                $ramConditions = [];
                foreach ($ramValues as $index => $value) {
                    $paramName = ':RAM' . $index;
                    $ramConditions[] = "RAM LIKE $paramName";
                    $params[$paramName] = '%' . trim($value) . '%';
                }
                $conditions[] = '(' . implode(' OR ', $ramConditions) . ')';
            }

            // Add min_price condition if present
            if (isset($filter['min_price']) && $filter['min_price'] !== '') {
                $conditions[] = "Gia >= :min_price";
                $params[':min_price'] = $filter['min_price'];
            }

            // Add max_price condition if present
            if (isset($filter['max_price']) && $filter['max_price'] !== '') {
                $conditions[] = "Gia <= :max_price";
                $params[':max_price'] = $filter['max_price'];
            }

            // Append conditions to queries if any exist
            if (!empty($conditions)) {
                $conditionStr = " AND " . implode(" AND ", $conditions);
                $countQuery .= $conditionStr;
                $query .= $conditionStr;
            }

            // Add pagination
            $offset = ($page - 1) * $limit;
            $query .= " ORDER BY MaSP DESC LIMIT :limit OFFSET :offset";

            // Get total records first
            $countStmt = $this->conn->prepare($countQuery);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Then get paginated records
            $stmt = $this->conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $records,
                'pagination' => [
                    'total' => (int)$totalRecords,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'last_page' => ceil($totalRecords / $limit),
                    'from' => $offset + 1,
                    'to' => min($offset + $limit, $totalRecords)
                ],
                'filters_applied' => array_keys($params)
            ];
        } catch (PDOException $e) {
            error_log("Lỗi lọc sản phẩm: " . $e->getMessage());
            throw new Exception("Có lỗi xảy ra khi lọc sản phẩm: " . $e->getMessage());
        }
    }

    public function getByID($MaSP)
    {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $MaSP, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi truy vấn sản phẩm theo ID: " . $e->getMessage());
            throw new Exception("Có lỗi xảy ra khi lấy sản phẩm theo ID");
        }
    }

    public function createSanpham($data)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }
        try {
            //Tạo mã sp mới nhất
            $maSP = $this->generateMaSP();

            $query = "INSERT INTO " . $this->table_name . " 
            SET MaSP=:MaSP, MaLoaiSP=:MaLoaiSP, TenSP=:TenSP, MoTa=:MoTa, 
            CPU=:CPU, RAM=:RAM, GPU=:GPU, Storage=:Storage, tg_baohanh=:tg_baohanh, ManHinh=:ManHinh, 
            Gia=:Gia, ImgUrl=:ImgUrl, TrangThai=:TrangThai";

            $stmt = $this->conn->prepare($query);

            $moTa = $data['MoTa'] ?? "";

            // Fixed parameter names to match query
            $stmt->bindParam(':MaSP', $maSP); // Changed from :maSP to :MaSP
            $stmt->bindParam(':MaLoaiSP', $data['MaLoaiSP']);
            $stmt->bindParam(':TenSP', $data['TenSP']);
            $stmt->bindParam(':MoTa', $moTa);
            $stmt->bindParam(':CPU', $data['CPU']);
            $stmt->bindParam(':RAM', $data['RAM']); // Changed from :Ram to :RAM
            $stmt->bindParam(':GPU', $data['GPU']);
            $stmt->bindParam(':Storage', $data['Storage']);
            $stmt->bindParam(':ManHinh', $data['ManHinh']);
            $stmt->bindParam(':Gia', $data['Gia']);
            $stmt->bindParam(':tg_baohanh', $data['tg_baohanh']);
            $stmt->bindParam(':ImgUrl', $data['ImgUrl']);
            $stmt->bindParam(':TrangThai', $data['TrangThai']);

            $stmt->execute();
            $this->lastInsertedId = $this->conn->lastInsertId();
            return true;
        } catch (PDOException $e) {
            error_log("Lỗi tạo sản phẩm: " . $e->getMessage());
            throw new Exception("Không thể tạo sản phẩm: " . $e->getMessage());
        }
    }

    public function editSanpham($data)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }
        try {
            // Check if product exists
            $checkQuery = "SELECT MaSP FROM " . $this->table_name . " WHERE MaSP = :MaSP";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(':MaSP', $data['MaSP']);
            $checkStmt->execute();

            if (!$checkStmt->fetch()) {
                throw new Exception("Không tìm thấy sản phẩm với mã: " . $data['MaSP']);
            }

            $query = "UPDATE " . $this->table_name . " SET 
            MaLoaiSP=:MaLoaiSP, 
            TenSP=:TenSP, 
            MoTa=:MoTa, 
            CPU=:CPU, 
            RAM=:RAM, 
            GPU=:GPU, 
            Storage=:Storage, 
            ManHinh=:ManHinh, 
            Gia=:Gia, 
            tg_baohanh=:tg_baohanh,
            ImgUrl=:ImgUrl, 
            TrangThai=:TrangThai 
            WHERE MaSP=:MaSP";

            $stmt = $this->conn->prepare($query);

            $moTa = $data['MoTa'] ?? "";

            // Bind parameters
            $stmt->bindParam(':MaSP', $data['MaSP']);
            $stmt->bindParam(':MaLoaiSP', $data['MaLoaiSP']);
            $stmt->bindParam(':TenSP', $data['TenSP']);
            $stmt->bindParam(':MoTa', $moTa);
            $stmt->bindParam(':CPU', $data['CPU']);
            $stmt->bindParam(':RAM', $data['RAM']);
            $stmt->bindParam(':GPU', $data['GPU']);
            $stmt->bindParam(':Storage', $data['Storage']);
            $stmt->bindParam(':ManHinh', $data['ManHinh']);
            $stmt->bindParam(':Gia', $data['Gia']);
            $stmt->bindParam('tg_baohanh', $data['tg_baohanh']);
            $stmt->bindParam(':ImgUrl', $data['ImgUrl']);
            $stmt->bindParam(':TrangThai', $data['TrangThai']);

            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Cập nhật sản phẩm thành công',
                    'data' => $data
                ];
            }

            throw new Exception("Không thể cập nhật sản phẩm");
        } catch (PDOException $e) {
            error_log("Lỗi cập nhật sản phẩm: " . $e->getMessage());
            throw new Exception("Không thể cập nhật sản phẩm: " . $e->getMessage());
        }
    }

    public function changeStatus($MaSP)
    {
        try {
            // First get current status
            $queryStatus = "SELECT TrangThai FROM " . $this->table_name . " WHERE MaSP = :MaSP";
            $stmtStatus = $this->conn->prepare($queryStatus);
            $stmtStatus->bindParam(':MaSP', $MaSP, PDO::PARAM_STR);
            $stmtStatus->execute();
            $currentStatus = $stmtStatus->fetch(PDO::FETCH_ASSOC);

            if (!$currentStatus) {
                throw new Exception("Không tìm thấy sản phẩm với mã: " . $MaSP);
            }

            // Toggle status (if 1 then 0, if 0 then 1)
            $newStatus = $currentStatus['TrangThai'] == 1 ? 0 : 1;

            // Update with new status
            $query = "UPDATE " . $this->table_name . " SET TrangThai = :TrangThai WHERE MaSP = :MaSP";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':MaSP', $MaSP, PDO::PARAM_STR);
            $stmt->bindParam(':TrangThai', $newStatus, PDO::PARAM_INT);

            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Đã thay đổi trạng thái sản phẩm',
                    'new_status' => $newStatus
                ];
            }

            return false;
        } catch (PDOException $e) {
            error_log("Lỗi thay đổi trạng thái sản phẩm: " . $e->getMessage());
            throw new Exception("Không thể thay đổi trạng thái sản phẩm: " . $e->getMessage());
        }
    }

    public function getLastInsertId()
    {
        return $this->lastInsertedId;
    }

    private function generateMaSP()
    {
        try {
            // Lấy mã sản phẩm lớn nhất hiện tại
            $query = "SELECT MAX(CAST(SUBSTRING(MaSP, 3) AS UNSIGNED)) as max_id FROM " . $this->table_name . " WHERE MaSP LIKE 'SP%'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $nextId = 1; // Mặc định bắt đầu từ 1
            if ($result && $result['max_id']) {
                $nextId = (int)$result['max_id'] + 1;
            }
            // Định dạng mã: SP001, SP002, ..., SP999
            return 'SP' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            // Nếu có lỗi, sử dụng phương pháp dự phòng
            error_log("Lỗi tạo mã sản phẩm tự động: " . $e->getMessage());
            return 'SP' . date('ymd') . rand(100, 999);
        }
    }
}
