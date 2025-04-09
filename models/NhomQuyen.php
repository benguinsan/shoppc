<?php
require_once __DIR__ . '/../config/Database.php';

class NhomQuyen
{
    private $conn;
    private $table_name = "nhomquyen";
    private $detail_table = "chitietnhomquyen";
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
            error_log("Lỗi khởi tạo NhomQuyen: " . $e->getMessage());
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

            $validColumns = ['MaNhomQuyen', 'TenNhomQuyen', 'created_at'];
            if (!in_array($orderBy, $validColumns)) {
                $orderBy = 'created_at';
            }

            $query = "SELECT * FROM " . $this->table_name;
            $countQuery = "SELECT COUNT(*) as total FROM " . $this->table_name;
            $params = [];

            if (!empty($searchTerm)) {
                $query .= " WHERE TenNhomQuyen LIKE :searchTerm";
                $countQuery .= " WHERE TenNhomQuyen LIKE :searchTerm";
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

            // Lấy thông tin chức năng cho mỗi nhóm quyền
            foreach ($records as &$record) {
                $record['ChucNang'] = $this->getFunctionsByRoleId($record['MaNhomQuyen']);
            }

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
            error_log("Lỗi lấy danh sách nhóm quyền: " . $e->getMessage());
            throw new Exception("Không thể lấy danh sách nhóm quyền: " . $e->getMessage());
        }
    }

    public function create($data)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            $this->conn->beginTransaction();

            // Sử dụng mã nhóm quyền được cung cấp hoặc tạo mã mới nếu không có
            $maNhomQuyen = isset($data['MaNhomQuyen']) && !empty($data['MaNhomQuyen'])
                ? $data['MaNhomQuyen']
                : $this->generateNhomQuyenId();

            // Kiểm tra xem mã nhóm quyền đã tồn tại chưa
            $checkQuery = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE MaNhomQuyen = :MaNhomQuyen";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(":MaNhomQuyen", $maNhomQuyen);
            $checkStmt->execute();

            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception("Mã nhóm quyền đã tồn tại");
            }

            $query = "INSERT INTO " . $this->table_name . " 
                      SET MaNhomQuyen=:MaNhomQuyen, TenNhomQuyen=:TenNhomQuyen";

            $stmt = $this->conn->prepare($query);

            $data['TenNhomQuyen'] = htmlspecialchars(strip_tags($data['TenNhomQuyen'] ?? ""));

            $stmt->bindParam(":MaNhomQuyen", $maNhomQuyen);
            $stmt->bindParam(":TenNhomQuyen", $data['TenNhomQuyen']);

            $stmt->execute();
            $this->lastInsertedId = $maNhomQuyen;

            // Thêm các chức năng vào nhóm quyền nếu có
            if (isset($data['ChucNang']) && is_array($data['ChucNang']) && !empty($data['ChucNang'])) {
                $this->addFunctionsToRole($maNhomQuyen, $data['ChucNang']);
            }

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Lỗi tạo nhóm quyền: " . $e->getMessage());
            throw new Exception("Không thể tạo nhóm quyền: " . $e->getMessage());
        }
    }

    public function getLastInsertId()
    {
        if (isset($this->lastInsertedId)) {
            return $this->lastInsertedId;
        }
        return null;
    }

    public function generateNhomQuyenId()
    {
        return 'NQ' . uniqid();
    }

    public function getById($maNhomQuyen)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE MaNhomQuyen = :MaNhomQuyen";
            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(":MaNhomQuyen", $maNhomQuyen);
            $stmt->execute();

            $nhomQuyen = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($nhomQuyen) {
                // Lấy danh sách chức năng của nhóm quyền
                $nhomQuyen['ChucNang'] = $this->getFunctionsByRoleId($maNhomQuyen);
            }

            return $nhomQuyen;
        } catch (PDOException $e) {
            error_log("Lỗi lấy thông tin nhóm quyền: " . $e->getMessage());
            throw new Exception("Không thể lấy thông tin nhóm quyền: " . $e->getMessage());
        }
    }

    public function update($maNhomQuyen, $data)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            $this->conn->beginTransaction();

            // Kiểm tra xem nhóm quyền có tồn tại không
            $nhomQuyen = $this->getById($maNhomQuyen);
            if (!$nhomQuyen) {
                throw new Exception("Không tìm thấy nhóm quyền");
            }

            // Cập nhật thông tin cơ bản
            if (isset($data['TenNhomQuyen']) && !empty($data['TenNhomQuyen'])) {
                $query = "UPDATE " . $this->table_name . " 
                          SET TenNhomQuyen = :TenNhomQuyen
                          WHERE MaNhomQuyen = :MaNhomQuyen";

                $stmt = $this->conn->prepare($query);

                $tenNhomQuyen = htmlspecialchars(strip_tags($data['TenNhomQuyen']));

                $stmt->bindParam(":TenNhomQuyen", $tenNhomQuyen);
                $stmt->bindParam(":MaNhomQuyen", $maNhomQuyen);

                $stmt->execute();
            }

            // Cập nhật danh sách chức năng nếu có
            if (isset($data['ChucNang']) && is_array($data['ChucNang'])) {
                // Xóa tất cả các chức năng hiện tại
                $this->removeAllFunctionsFromRole($maNhomQuyen);

                // Thêm các chức năng mới
                if (!empty($data['ChucNang'])) {
                    $this->addFunctionsToRole($maNhomQuyen, $data['ChucNang']);
                }
            }

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Lỗi cập nhật nhóm quyền: " . $e->getMessage());
            throw new Exception("Không thể cập nhật nhóm quyền: " . $e->getMessage());
        }
    }

    public function delete($maNhomQuyen)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            $this->conn->beginTransaction();

            // Kiểm tra xem nhóm quyền có tồn tại không
            $checkRole = $this->getById($maNhomQuyen);
            if (!$checkRole) {
                throw new Exception("Không tìm thấy nhóm quyền với mã: " . $maNhomQuyen);
            }

            // Kiểm tra xem nhóm quyền có đang được sử dụng bởi tài khoản nào không
            $query = "SELECT COUNT(*) as count FROM taikhoan WHERE MaNhomQuyen = :MaNhomQuyen";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaNhomQuyen", $maNhomQuyen);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                throw new Exception("Không thể xóa nhóm quyền vì đang được sử dụng bởi tài khoản");
            }

            // Xóa tất cả chi tiết nhóm quyền
            $this->removeAllFunctionsFromRole($maNhomQuyen);

            // Xóa nhóm quyền
            $query = "DELETE FROM " . $this->table_name . " WHERE MaNhomQuyen = :MaNhomQuyen";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaNhomQuyen", $maNhomQuyen);
            $stmt->execute();

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Lỗi xóa nhóm quyền: " . $e->getMessage());
            throw new Exception("Không thể xóa nhóm quyền: " . $e->getMessage());
        }
    }

    // Lấy danh sách chức năng của một nhóm quyền
    public function getFunctionsByRoleId($maNhomQuyen)
    {
        try {
            $query = "SELECT c.* FROM chucnang c
                      INNER JOIN " . $this->detail_table . " ct ON c.MaChucNang = ct.MaChucNang
                      WHERE ct.MaNhomQuyen = :MaNhomQuyen";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaNhomQuyen", $maNhomQuyen);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi lấy danh sách chức năng của nhóm quyền: " . $e->getMessage());
            throw new Exception("Không thể lấy danh sách chức năng của nhóm quyền: " . $e->getMessage());
        }
    }

    // Thêm nhiều chức năng vào nhóm quyền
    public function addFunctionsToRole($maNhomQuyen, $maChucNangs)
    {
        try {
            $query = "INSERT INTO " . $this->detail_table . " (MaNhomQuyen, MaChucNang) VALUES (:MaNhomQuyen, :MaChucNang)";
            $stmt = $this->conn->prepare($query);

            foreach ($maChucNangs as $maChucNang) {
                $stmt->bindParam(":MaNhomQuyen", $maNhomQuyen);
                $stmt->bindParam(":MaChucNang", $maChucNang);
                $stmt->execute();
            }

            return true;
        } catch (PDOException $e) {
            error_log("Lỗi thêm chức năng vào nhóm quyền: " . $e->getMessage());
            throw new Exception("Không thể thêm chức năng vào nhóm quyền: " . $e->getMessage());
        }
    }

    // Xóa một chức năng khỏi nhóm quyền
    public function removeFunctionFromRole($maNhomQuyen, $maChucNang)
    {
        try {
            $query = "DELETE FROM " . $this->detail_table . " WHERE MaNhomQuyen = :MaNhomQuyen AND MaChucNang = :MaChucNang";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaNhomQuyen", $maNhomQuyen);
            $stmt->bindParam(":MaChucNang", $maChucNang);
            $stmt->execute();

            return true;
        } catch (PDOException $e) {
            error_log("Lỗi xóa chức năng khỏi nhóm quyền: " . $e->getMessage());
            throw new Exception("Không thể xóa chức năng khỏi nhóm quyền: " . $e->getMessage());
        }
    }

    // Xóa tất cả chức năng khỏi nhóm quyền
    public function removeAllFunctionsFromRole($maNhomQuyen)
    {
        try {
            $query = "DELETE FROM " . $this->detail_table . " WHERE MaNhomQuyen = :MaNhomQuyen";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaNhomQuyen", $maNhomQuyen);
            $stmt->execute();

            return true;
        } catch (PDOException $e) {
            error_log("Lỗi xóa tất cả chức năng khỏi nhóm quyền: " . $e->getMessage());
            throw new Exception("Không thể xóa tất cả chức năng khỏi nhóm quyền: " . $e->getMessage());
        }
    }

    // Cập nhật danh sách chức năng của nhóm quyền
    public function updateRoleFunctions($maNhomQuyen, $maChucNangs)
    {
        try {
            $this->conn->beginTransaction();

            // Xóa tất cả các chức năng hiện tại
            $this->removeAllFunctionsFromRole($maNhomQuyen);

            // Thêm các chức năng mới
            if (!empty($maChucNangs)) {
                $this->addFunctionsToRole($maNhomQuyen, $maChucNangs);
            }

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Lỗi cập nhật danh sách chức năng của nhóm quyền: " . $e->getMessage());
            throw new Exception("Không thể cập nhật danh sách chức năng của nhóm quyền: " . $e->getMessage());
        }
    }
}
