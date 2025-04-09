<?php
require_once __DIR__ . '/../config/Database.php';

class NhaCungCap
{
    private $conn;
    private $table_name = "nhacungcap";
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
            error_log("Lỗi khởi tạo NhaCungCap: " . $e->getMessage());
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

            $validColumns = ['MaNCC', 'TenNCC', 'DiaChi', 'SDT', 'Email', 'TrangThai', 'created_at'];
            if (!in_array($orderBy, $validColumns)) {
                $orderBy = 'created_at';
            }

            $query = "SELECT * FROM " . $this->table_name;
            $countQuery = "SELECT COUNT(*) as total FROM " . $this->table_name;
            $params = [];

            if (!empty($searchTerm)) {
                $query .= " WHERE TenNCC LIKE :searchTerm OR DiaChi LIKE :searchTerm OR SDT LIKE :searchTerm OR Email LIKE :searchTerm";
                $countQuery .= " WHERE TenNCC LIKE :searchTerm OR DiaChi LIKE :searchTerm OR SDT LIKE :searchTerm OR Email LIKE :searchTerm";
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
            error_log("Lỗi lấy danh sách nhà cung cấp: " . $e->getMessage());
            throw new Exception("Không thể lấy danh sách nhà cung cấp: " . $e->getMessage());
        }
    }

    public function getById($id)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE MaNCC = :MaNCC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaNCC", $id);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi lấy thông tin nhà cung cấp: " . $e->getMessage());
            throw new Exception("Không thể lấy thông tin nhà cung cấp: " . $e->getMessage());
        }
    }

    public function create($data)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            // Tạo mã nhà cung cấp tự động nếu không được cung cấp
            $maNCC = isset($data['MaNCC']) && !empty($data['MaNCC'])
                ? $data['MaNCC']
                : $this->generateNCCId();

            // Kiểm tra xem mã nhà cung cấp đã tồn tại chưa
            $checkQuery = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE MaNCC = :MaNCC";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(":MaNCC", $maNCC);
            $checkStmt->execute();

            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception("Mã nhà cung cấp đã tồn tại");
            }

            $query = "INSERT INTO " . $this->table_name . " 
                      SET MaNCC=:MaNCC, TenNCC=:TenNCC, DiaChi=:DiaChi, SDT=:SDT, Email=:Email, TrangThai=:TrangThai";

            $stmt = $this->conn->prepare($query);

            $diaChi = $data['DiaChi'] ?? "";
            $sdt = $data['SDT'] ?? "";
            $email = $data['Email'] ?? "";

            // Bind các tham số
            $stmt->bindParam(":MaNCC", $maNCC);
            $stmt->bindParam(":TenNCC", $data['TenNCC']);
            $stmt->bindParam(":DiaChi", $diaChi);
            $stmt->bindParam(":SDT", $sdt);
            $stmt->bindParam(":Email", $email);
            $trangThai = isset($data['TrangThai']) ? (int)$data['TrangThai'] : 1;
            $stmt->bindParam(":TrangThai", $trangThai);

            $stmt->execute();
            $this->lastInsertedId = $maNCC;

            return true;
        } catch (PDOException $e) {
            error_log("Lỗi tạo nhà cung cấp: " . $e->getMessage());
            throw new Exception("Không thể tạo nhà cung cấp: " . $e->getMessage());
        }
    }

    public function update($id, $data)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            // Kiểm tra xem nhà cung cấp có tồn tại không
            $nhaCungCap = $this->getById($id);
            if (!$nhaCungCap) {
                throw new Exception("Không tìm thấy nhà cung cấp");
            }

            // Xây dựng câu lệnh SQL động dựa trên dữ liệu được cung cấp
            $updateFields = [];
            $params = [];

            if (isset($data['TenNCC']) && !empty($data['TenNCC'])) {
                $updateFields[] = "TenNCC = :TenNCC";
                $params[':TenNCC'] = htmlspecialchars(strip_tags($data['TenNCC']));
            }

            if (isset($data['DiaChi'])) {
                $updateFields[] = "DiaChi = :DiaChi";
                $params[':DiaChi'] = htmlspecialchars(strip_tags($data['DiaChi']));
            }

            if (isset($data['SDT'])) {
                $updateFields[] = "SDT = :SDT";
                $params[':SDT'] = htmlspecialchars(strip_tags($data['SDT']));
            }

            if (isset($data['Email'])) {
                $updateFields[] = "Email = :Email";
                $params[':Email'] = htmlspecialchars(strip_tags($data['Email']));
            }

            if (isset($data['TrangThai'])) {
                $updateFields[] = "TrangThai = :TrangThai";
                $params[':TrangThai'] = (int)$data['TrangThai'];
            }

            // Nếu không có trường nào được cập nhật
            if (empty($updateFields)) {
                return true; // Không có gì để cập nhật
            }

            $query = "UPDATE " . $this->table_name . " SET " . implode(", ", $updateFields) . " WHERE MaNCC = :MaNCC";
            $params[':MaNCC'] = $id;

            $stmt = $this->conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            error_log("Lỗi cập nhật nhà cung cấp: " . $e->getMessage());
            throw new Exception("Không thể cập nhật nhà cung cấp: " . $e->getMessage());
        }
    }

    public function delete($id)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            // Kiểm tra xem nhà cung cấp có tồn tại không
            $nhaCungCap = $this->getById($id);
            if (!$nhaCungCap) {
                throw new Exception("Không tìm thấy nhà cung cấp");
            }

            // Kiểm tra xem nhà cung cấp có đang được sử dụng không
            // (Bạn có thể thêm kiểm tra liên kết với các bảng khác ở đây)

            $query = "DELETE FROM " . $this->table_name . " WHERE MaNCC = :MaNCC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaNCC", $id);
            $stmt->execute();

            return true;
        } catch (PDOException $e) {
            error_log("Lỗi xóa nhà cung cấp: " . $e->getMessage());
            throw new Exception("Không thể xóa nhà cung cấp: " . $e->getMessage());
        }
    }

    public function getLastInsertId()
    {
        return $this->lastInsertedId;
    }

    private function generateNCCId()
    {
        try {
            // Lấy mã nhà cung cấp lớn nhất hiện tại
            $query = "SELECT MAX(CAST(SUBSTRING(MaNCC, 4) AS UNSIGNED)) as max_id FROM " . $this->table_name . " WHERE MaNCC LIKE 'NCC%'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $nextId = 1; // Mặc định bắt đầu từ 1
            if ($result && $result['max_id']) {
                $nextId = (int)$result['max_id'] + 1;
            }

            // Định dạng mã: NCC001, NCC002, ..., NCC999
            return 'NCC' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            // Nếu có lỗi, sử dụng phương pháp dự phòng
            error_log("Lỗi tạo mã nhà cung cấp tự động: " . $e->getMessage());
            return 'NCC' . date('ymd') . rand(100, 999);
        }
    }
}
