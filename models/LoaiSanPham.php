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

    public function getAll($page = 1, $limit = 10, $searchTerm = '', $orderBy = 'created_at', $orderDirection = 'DESC')
    {
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

            $validColumns = ['MaLoaiSP', 'TenLoaiSP', 'MoTa', 'TrangThai', 'created_at'];
            if (!in_array($orderBy, $validColumns)) {
                $orderBy = 'created_at';
            }

            $query = "SELECT * FROM " . $this->table_name;
            $countQuery = "SELECT COUNT(*) as total FROM " . $this->table_name;
            $params = [];

            if (!empty($searchTerm)) {
                $query .= " WHERE TenLoaiSP LIKE :searchTerm OR MoTa LIKE :searchTerm";
                $countQuery .= " WHERE TenLoaiSP LIKE :searchTerm OR MoTa LIKE :searchTerm";
                $params[':searchTerm'] = "%$searchTerm%";
            }

            $query .= " ORDER BY " . $orderBy . " " . $orderDirection;
            $query .= " LIMIT :limit OFFSET :offset";

            $countStmt = $this->conn->prepare($countQuery);
            if (!empty($searchTerm)) {
                $countStmt->bindParam(':searchTerm', $params[':searchTerm']);
            }
            $countStmt->execute();
            $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $this->conn->prepare($query);
            if (!empty($searchTerm)) {
                $stmt->bindParam(':searchTerm', $params[':searchTerm']);
            }
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $totalPages = ceil($totalRecords / $limit);

            return [
                'data' => $records,
                'pagination' => [
                    'total' => $totalRecords,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'last_page' => $totalPages,
                    'from' => $offset + 1,
                    'to' => min($offset + $limit, $totalRecords)
                ]
            ];
        } catch (PDOException $e) {
            error_log("Lỗi lấy danh sách loại sản phẩm: " . $e->getMessage());
            throw new Exception("Không thể lấy danh sách loại sản phẩm: " . $e->getMessage());
        }
    }

    public function getById($id)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE MaLoaiSP = :MaLoaiSP";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaLoaiSP", $id);
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
                      SET MaLoaiSP=:MaLoaiSP, TenLoaiSP=:TenLoaiSP, MoTa=:MoTa, TrangThai=:TrangThai";

            $stmt = $this->conn->prepare($query);

            $moTa = $data['MoTa'] ?? "";

            // Bind các tham số
            $stmt->bindParam(":MaLoaiSP", $maLoaiSP);
            $stmt->bindParam(":TenLoaiSP", $data['TenLoaiSP']);
            $stmt->bindParam(":MoTa", $moTa);
            $trangThai = isset($data['TrangThai']) ? (int)$data['TrangThai'] : 1;
            $stmt->bindParam(":TrangThai", $trangThai);

            $stmt->execute();
            $this->lastInsertedId = $maLoaiSP;

            return true;
        } catch (PDOException $e) {
            error_log("Lỗi tạo loại sản phẩm: " . $e->getMessage());
            throw new Exception("Không thể tạo loại sản phẩm: " . $e->getMessage());
        }
    }

    public function update($id, $data)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            // Kiểm tra xem loại sản phẩm có tồn tại không
            $loaiSanPham = $this->getById($id);
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

            if (isset($data['TrangThai'])) {
                $updateFields[] = "TrangThai = :TrangThai";
                $params[':TrangThai'] = (int)$data['TrangThai'];
            }

            // Nếu không có trường nào được cập nhật
            if (empty($updateFields)) {
                return true; // Không có gì để cập nhật
            }

            $query = "UPDATE " . $this->table_name . " SET " . implode(", ", $updateFields) . " WHERE MaLoaiSP = :MaLoaiSP";
            $params[':MaLoaiSP'] = $id;

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

    public function delete($id)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            // Kiểm tra xem loại sản phẩm có tồn tại không
            $loaiSanPham = $this->getById($id);
            if (!$loaiSanPham) {
                throw new Exception("Không tìm thấy loại sản phẩm");
            }

            // Kiểm tra xem loại sản phẩm có đang được sử dụng không
            // (Bạn có thể thêm kiểm tra liên kết với bảng sản phẩm ở đây)

            $query = "DELETE FROM " . $this->table_name . " WHERE MaLoaiSP = :MaLoaiSP";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaLoaiSP", $id);
            $stmt->execute();

            return true;
        } catch (PDOException $e) {
            error_log("Lỗi xóa loại sản phẩm: " . $e->getMessage());
            throw new Exception("Không thể xóa loại sản phẩm: " . $e->getMessage());
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
