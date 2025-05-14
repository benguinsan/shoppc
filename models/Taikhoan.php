<?php
require_once __DIR__ . '/../config/Database.php';
require_once 'NguoiDung.php';

class Taikhoan
{
    private $conn;
    private $table_name = "taikhoan";

    public function __construct()
    {
        try {
            $db = new Database();
            $this->conn = $db->getConnection();

            if ($this->conn === null) {
                throw new Exception("Không thể kết nối đến cơ sở dữ liệu. Vui lòng kiểm tra cấu hình database.");
            }
        } catch (Exception $e) {
            error_log("Lỗi khởi tạo Taikhoan: " . $e->getMessage());
            throw $e;
        }
    }

    public function getAllTaiKhoan($page = 1, $limit = 10, $searchTerm = '', $orderBy = 'MaTK', $orderDirection = 'ASC')
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
                $orderDirection = 'ASC';
            }

            $validColumns = ['MaTK', 'TenTK', 'MaNguoiDung', 'MaNhomQuyen', 'TrangThai', 'created_at'];

            if (!in_array($orderBy, $validColumns)) {
                $orderBy = 'created_at';
            }

            // Xây dựng câu truy vấn cơ bản
            $query = "SELECT MaTK, TenTK, MaNguoiDung, MaNhomQuyen, TrangThai
                     FROM " . $this->table_name;

            $countQuery = "SELECT COUNT(*) as total FROM " . $this->table_name;

            $params = [];

            // Thêm điều kiện tìm kiếm nếu có
            if (!empty($searchTerm)) {
                $query .= " WHERE TenTK LIKE :searchTerm 
                          OR MaTK LIKE :searchTerm 
                          OR MaNhomQuyen LIKE :searchTerm";

                $countQuery .= " WHERE TenTK LIKE :searchTerm 
                               OR MaTK LIKE :searchTerm 
                               OR MaNhomQuyen LIKE :searchTerm";

                $params[':searchTerm'] = "%$searchTerm%";
            }

            // Thêm ORDER BY
            $query .= " ORDER BY " . $orderBy . " " . $orderDirection;
            $query .= " LIMIT :limit OFFSET :offset";

            // Đếm tổng số bản ghi
            $countStmt = $this->conn->prepare($countQuery);
            if (!empty($searchTerm)) {
                $countStmt->bindParam(':searchTerm', $params[':searchTerm']);
            }
            $countStmt->execute();
            $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Thực hiện truy vấn chính
            $stmt = $this->conn->prepare($query);
            if (!empty($searchTerm)) {
                $stmt->bindParam(':searchTerm', $params[':searchTerm']);
            }
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Tính toán thông tin phân trang
            $totalPages = ceil($totalRecords / $limit);

            return [
                'data' => $accounts,
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
            error_log("Lỗi lấy danh sách tài khoản: " . $e->getMessage());
            throw new Exception("Không thể lấy danh sách tài khoản: " . $e->getMessage());
        }
    }

    public function usernameExists($username)
    {
        $query = "SELECT MaTK FROM " . $this->table_name . " WHERE TenTK = :username LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $username = htmlspecialchars(strip_tags($username));
        $stmt->bindParam(":username", $username);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function findByUsername($username)
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE TenTK = :username LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $username = htmlspecialchars(strip_tags($username));
        $stmt->bindParam(":username", $username);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data)
    {
        if ($this->conn === null) {
            throw new Exception("Database connection is not initialized");
        }

        // Bắt đầu transaction
        $this->conn->beginTransaction();

        try {
            // Kiểm tra username đã tồn tại chưa
            if ($this->usernameExists($data['TenTK'])) {
                throw new Exception("Tên tài khoản đã tồn tại");
            }

            $nguoiDung = new NguoiDung($this->conn);

            $userData = [
                'HoTen' => $data['HoTen'] ?? "",
                'Email' => $data['Email'] ?? "",
                'SDT' => $data['SDT'] ?? "",
                'DiaChi' => $data['DiaChi'] ?? "",
                'NgaySinh' => $data['NgaySinh'] ?? null,
            ];

            if (!$nguoiDung->create($userData)) {
                throw new Exception("Không thể tạo người dùng");
            }

            // Lấy ID người dùng vừa tạo
            $maNguoiDung = $nguoiDung->getLastInsertId();

            if (!$maNguoiDung) {
                throw new Exception("Không thể lấy ID người dùng");
            }

            // Kiểm tra xem người dùng có tồn tại trong database không
            $checkQuery = "SELECT MaNguoiDung FROM nguoidung WHERE MaNguoiDung = :maNguoiDung";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(":maNguoiDung", $maNguoiDung);
            $checkStmt->execute();

            if ($checkStmt->rowCount() === 0) {
                throw new Exception("Người dùng không tồn tại trong database");
            }

            // Hash mật khẩu
            $hashedPassword = password_hash($data['MatKhau'], PASSWORD_BCRYPT);

            // Tạo MaTK
            $maTK = $this->generateAccountId();

            // Ma Nhom quyen khi register
            $maNhomQuyen = 'KHACHHANG';
            $trangThai = 1;

            // Sanitize input
            $tenTK = htmlspecialchars(strip_tags($data['TenTK']));

            // Sử dụng prepared statement với PDO
            $query = "INSERT INTO " . $this->table_name . " 
                     (MaTK, TenTK, MatKhau, MaNguoiDung, MaNhomQuyen, TrangThai) 
                     VALUES (:MaTK, :TenTK, :MatKhau, :MaNguoiDung, :MaNhomQuyen, :TrangThai)";

            $stmt = $this->conn->prepare($query);

            // Bind parameters với PDO
            $stmt->bindParam(":MaTK", $maTK);
            $stmt->bindParam(":TenTK", $tenTK);
            $stmt->bindParam(":MatKhau", $hashedPassword);
            $stmt->bindParam(":MaNguoiDung", $maNguoiDung);
            $stmt->bindParam(":MaNhomQuyen", $maNhomQuyen);
            $stmt->bindParam(":TrangThai", $trangThai);

            $result = $stmt->execute();

            if (!$result) {
                throw new Exception("Không thể tạo tài khoản: " . implode(", ", $stmt->errorInfo()));
            }

            // Commit transaction nếu mọi thứ thành công
            $this->conn->commit();

            return true;
        } catch (Exception $e) {
            // Rollback transaction nếu có lỗi
            $this->conn->rollBack();
            error_log("Lỗi tạo tài khoản: " . $e->getMessage());
            throw $e;
        }
    }

    private function generateAccountId()
    {
        return 'TK' . uniqid();
    }

    // Lấy thông tin tài khoản theo MaTaiKhoan
    public function getById($maTaiKhoan)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE MaTK = :MaTK";
            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(":MaTK", $maTaiKhoan);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi lấy thông tin tài khoản: " . $e->getMessage());
            throw new Exception("Không thể lấy thông tin tài khoản: " . $e->getMessage());
        }
    }

    public function updatePassword($maTaiKhoan, $matKhauMoi, $matKhauCu = null, $isAdmin = false)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            // Kiểm tra tài khoản tồn tại
            $taiKhoan = $this->getById($maTaiKhoan);
            if (!$taiKhoan) {
                throw new Exception("Không tìm thấy tài khoản với mã: " . $maTaiKhoan);
            }

            // Nếu không phải admin và có yêu cầu xác minh mật khẩu cũ
            if (!$isAdmin && $matKhauCu !== null) {
                // Kiểm tra mật khẩu cũ có đúng không
                if (!password_verify($matKhauCu, $taiKhoan['MatKhau'])) {
                    throw new Exception("Mật khẩu cũ không chính xác");
                }
            }

            // Hash mật khẩu mới
            $hashedPassword = password_hash($matKhauMoi, PASSWORD_BCRYPT);

            // Cập nhật mật khẩu
            $query = "UPDATE " . $this->table_name . " SET MatKhau = :MatKhau WHERE MaTK = :MaTK";
            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(":MatKhau", $hashedPassword);
            $stmt->bindParam(":MaTK", $maTaiKhoan);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Lỗi cập nhật mật khẩu: " . $e->getMessage());
            throw new Exception("Không thể cập nhật mật khẩu: " . $e->getMessage());
        }
    }

    public function updateStatus($maTaiKhoan, $trangThai)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            // Kiểm tra tài khoản tồn tại
            $taiKhoan = $this->getById($maTaiKhoan);
            if (!$taiKhoan) {
                throw new Exception("Không tìm thấy tài khoản với mã: " . $maTaiKhoan);
            }

            // Cập nhật trạng thái
            $query = "UPDATE " . $this->table_name . " SET TrangThai = :TrangThai WHERE MaTK = :MaTK";
            $stmt = $this->conn->prepare($query);

            $trangThai = (int)$trangThai === 1 ? 1 : 0;
            $stmt->bindParam(":TrangThai", $trangThai, PDO::PARAM_INT);
            $stmt->bindParam(":MaTK", $maTaiKhoan);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Lỗi cập nhật trạng thái tài khoản: " . $e->getMessage());
            throw new Exception("Không thể cập nhật trạng thái tài khoản: " . $e->getMessage());
        }
    }

    public function updateRole($maTaiKhoan, $maNhomQuyen)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            // Kiểm tra tài khoản tồn tại
            $taiKhoan = $this->getById($maTaiKhoan);
            if (!$taiKhoan) {
                throw new Exception("Không tìm thấy tài khoản với mã: " . $maTaiKhoan);
            }

            // Cập nhật nhóm quyền
            $query = "UPDATE " . $this->table_name . " SET MaNhomQuyen = :MaNhomQuyen WHERE MaTK = :MaTK";
            $stmt = $this->conn->prepare($query);

            $maNhomQuyen = htmlspecialchars(strip_tags($maNhomQuyen));
            $stmt->bindParam(":MaNhomQuyen", $maNhomQuyen);
            $stmt->bindParam(":MaTK", $maTaiKhoan);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Lỗi cập nhật nhóm quyền tài khoản: " . $e->getMessage());
            throw new Exception("Không thể cập nhật nhóm quyền tài khoản: " . $e->getMessage());
        }
    }

    public function updateProfile($maTaiKhoan, $data)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            // Kiểm tra tài khoản tồn tại
            $account = $this->getById($maTaiKhoan);
            if (!$account) {
                throw new Exception("Tài khoản không tồn tại");
            }

            // Người dùng thông thường chỉ có thể cập nhật mật khẩu
            if (!isset($data['MatKhau']) || empty($data['MatKhau'])) {
                return true; // Không có gì để cập nhật
            }

            // Hash mật khẩu
            $hashedPassword = password_hash($data['MatKhau'], PASSWORD_BCRYPT);

            // Cập nhật mật khẩu
            $query = "UPDATE " . $this->table_name . " SET MatKhau = :MatKhau WHERE MaTK = :MaTK";
            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(":MatKhau", $hashedPassword);
            $stmt->bindParam(":MaTK", $maTaiKhoan);

            if (!$stmt->execute()) {
                throw new Exception("Cập nhật mật khẩu thất bại");
            }

            return true;
        } catch (PDOException $e) {
            error_log("Lỗi cập nhật mật khẩu: " . $e->getMessage());
            throw new Exception("Không thể cập nhật mật khẩu: " . $e->getMessage());
        }
    }

    public function update($maTaiKhoan, $data)
    {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            // Kiểm tra tài khoản tồn tại
            $account = $this->getById($maTaiKhoan);
            if (!$account) {
                throw new Exception("Tài khoản không tồn tại");
            }

            // Bắt đầu xây dựng câu truy vấn
            $query = "UPDATE " . $this->table_name . " SET ";
            $updateFields = [];
            $params = [];

            // Cập nhật MaNhomQuyen nếu có
            if (isset($data['MaNhomQuyen'])) {
                $updateFields[] = "MaNhomQuyen = :MaNhomQuyen";
                $params[':MaNhomQuyen'] = htmlspecialchars(strip_tags($data['MaNhomQuyen']));
            }

            // Cập nhật TrangThai nếu có
            if (isset($data['TrangThai'])) {
                // Chỉ cho phép giá trị 0 hoặc 1
                if ($data['TrangThai'] !== 0 && $data['TrangThai'] !== 1) {
                    throw new Exception("Trạng thái không hợp lệ. Chỉ chấp nhận giá trị 0 (vô hiệu hóa) hoặc 1 (kích hoạt)");
                }
                $updateFields[] = "TrangThai = :TrangThai";
                $params[':TrangThai'] = $data['TrangThai'];
            }

            // Cập nhật mật khẩu nếu có
            if (isset($data['MatKhau']) && !empty($data['MatKhau'])) {
                $hashedPassword = password_hash($data['MatKhau'], PASSWORD_BCRYPT);
                $updateFields[] = "MatKhau = :MatKhau";
                $params[':MatKhau'] = $hashedPassword;
            }

            // Nếu không có trường nào cần cập nhật
            if (empty($updateFields)) {
                return true; // Không có gì để cập nhật
            }

            // Hoàn thiện câu truy vấn
            $query .= implode(", ", $updateFields) . " WHERE MaTK = :MaTK";
            $params[':MaTK'] = $maTaiKhoan;

            // Thực hiện truy vấn
            $stmt = $this->conn->prepare($query);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            if (!$stmt->execute()) {
                throw new Exception("Cập nhật tài khoản thất bại");
            }

            return true;
        } catch (PDOException $e) {
            error_log("Lỗi cập nhật tài khoản: " . $e->getMessage());
            throw new Exception("Không thể cập nhật tài khoản: " . $e->getMessage());
        }
    }
}
