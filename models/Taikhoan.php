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

    public function getAllTaiKhoan()
    {
        $query = "SELECT * FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
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

    

    
}
