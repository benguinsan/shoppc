<?php
require_once __DIR__ . '/../config/Database.php';

class LoaiSanPham
{
    private $conn;
    private $table_name = "loaisanpham";
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
            error_log("Lỗi khởi tạo LoaiSanPham: " . $e->getMessage());
            throw $e;
        }
    }

    public function getAll($page = 1, $limit = 15, $orderDirection = 'DESC')
    {
        try {
            // Validate order direction
            $orderDirection = strtoupper($orderDirection) === 'ASC' ? 'ASC' : 'DESC';

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
                     ORDER BY MaLoaiSP $orderDirection 
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
            throw new Exception("Có lỗi xảy ra khi lấy danh sách sản phẩm"  . $e->getMessage());
        }
    }


    public function getById($maLoaiSP)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE MaLoaiSP = :MaLoaiSP";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaLoaiSP", $maLoaiSP);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi lấy thông tin loại sản phẩm: " . $e->getMessage());
            throw new Exception("Không thể lấy thông tin loại sản phẩm: " . $e->getMessage());
        }
    }

    public function create($data)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            // Tạo mã loại sản phẩm tự động nếu không được cung cấp
            $maLoaiSP = isset($data['MaLoaiSP']) && !empty($data['MaLoaiSP'])
                ? $data['MaLoaiSP']
                : $this->generateLoaiSPId();

            // Kiểm tra xem mã loại sản phẩm đã tồn tại chưa
            $checkQuery = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE MaLoaiSP = :MaLoaiSP";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(":MaLoaiSP", $maLoaiSP);
            $checkStmt->execute();

            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception("Mã loại sản phẩm đã tồn tại");
            }

            $query = "INSERT INTO " . $this->table_name . " 
                      SET MaLoaiSP=:MaLoaiSP, TenLoaiSP=:TenLoaiSP, MoTa=:MoTa";

            $stmt = $this->conn->prepare($query);

            $moTa = $data['MoTa'] ?? "";

            // Bind các tham số
            $stmt->bindParam(":MaLoaiSP", $maLoaiSP);
            $stmt->bindParam(":TenLoaiSP", $data['TenLoaiSP']);
            $stmt->bindParam(":MoTa", $moTa);

            $stmt->execute();
            $this->lastInsertedId = $maLoaiSP;

            return true;
        } catch (PDOException $e) {
            error_log("Lỗi tạo loại sản phẩm: " . $e->getMessage());
            throw new Exception("Không thể tạo loại sản phẩm: " . $e->getMessage());
        }
    }

    public function update($maLoaiSP, $data)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            // Kiểm tra xem loại sản phẩm có tồn tại không
            $loaiSanPham = $this->getById($maLoaiSP);
            if (!$loaiSanPham) {
                throw new Exception("Không tìm thấy loại sản phẩm");
            }

            // Xây dựng câu lệnh SQL động dựa trên dữ liệu được cung cấp
            $updateFields = [];
            $params = [];

            if (isset($data['TenLoaiSP']) && !empty($data['TenLoaiSP'])) {
                $updateFields[] = "TenLoaiSP = :TenLoaiSP";
                $params[':TenLoaiSP'] = htmlspecialchars(strip_tags($data['TenLoaiSP']));
            }

            if (isset($data['MoTa'])) {
                $updateFields[] = "MoTa = :MoTa";
                $params[':MoTa'] = htmlspecialchars(strip_tags($data['MoTa']));
            }

            // Nếu không có trường nào được cập nhật
            if (empty($updateFields)) {
                return true; // Không có gì để cập nhật
            }

            $query = "UPDATE " . $this->table_name . " SET " . implode(", ", $updateFields) . " WHERE MaLoaiSP = :MaLoaiSP";
            $params[':MaLoaiSP'] = $maLoaiSP;

            $stmt = $this->conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            error_log("Lỗi cập nhật loại sản phẩm: " . $e->getMessage());
            throw new Exception("Không thể cập nhật loại sản phẩm: " . $e->getMessage());
        }
    }

    public function delete($maLoaiSP)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            // Kiểm tra xem loại sản phẩm có tồn tại không
            $loaiSanPham = $this->getById($maLoaiSP);
            if (!$loaiSanPham) {
                throw new Exception("Không tìm thấy loại sản phẩm" . $maLoaiSP);
            }

            // Toggle trạng thái (0 -> 1 hoặc 1 -> 0)
            $newStatus = $loaiSanPham['TrangThai'] == 1 ? 0 : 1;

            // Cập nhật trạng thái mới
            $query = "UPDATE " . $this->table_name . " 
                     SET TrangThai = :TrangThai 
                     WHERE MaLoaiSP = :MaLoaiSP";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaLoaiSP", $maLoaiSP);
            $stmt->bindParam(":TrangThai", $newStatus, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'success' => true,
                'message' => 'Đã thay đổi trạng thái loại sản phẩm',
                'new_status' => $newStatus,
                'MaLoaiSP' => $maLoaiSP
            ];
        } catch (PDOException $e) {
            error_log("Lỗi cập nhật trạng thái loại sản phẩm: " . $e->getMessage());
            throw new Exception("Không thể cập nhật trạng thái loại sản phẩm: " . $e->getMessage());
        }
    }

    public function getLastInsertId()
    {
        return $this->lastInsertedId;
    }

    private function generateLoaiSPId()
    {
        try {
            // Lấy mã loại sản phẩm lớn nhất hiện tại
            $query = "SELECT MAX(CAST(SUBSTRING(MaLoaiSP, 4) AS UNSIGNED)) as max_id FROM " . $this->table_name . " WHERE MaLoaiSP LIKE 'LSP%'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $nextId = 1; // Mặc định bắt đầu từ 1
            if ($result && $result['max_id']) {
                $nextId = (int)$result['max_id'] + 1;
            }

            // Định dạng mã: LSP001, LSP002, ..., LSP999
            return 'LSP' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            // Nếu có lỗi, sử dụng phương pháp dự phòng
            error_log("Lỗi tạo mã loại sản phẩm tự động: " . $e->getMessage());
            return 'LSP' . date('ymd') . rand(100, 999);
        }
    }
}
