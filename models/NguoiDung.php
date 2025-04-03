<?php
require_once __DIR__ . '/../config/Database.php';

class NguoiDung
{
    private $conn;
    private $table_name = "nguoidung";
    private $lastInsertedId; // Thêm biến để lưu ID vừa tạo
    
    // Constructor nhận kết nối từ bên ngoài hoặc tự tạo kết nối mới
    public function __construct($connection = null) {
        try {
            // Nếu đã có kết nối được truyền vào, sử dụng nó
            if ($connection !== null) {
                $this->conn = $connection;
            } else {
                // Nếu không, tạo kết nối mới
                $db = new Database();
                $this->conn = $db->getConnection();
            }

            if ($this->conn === null) {
                throw new Exception("Không thể kết nối đến cơ sở dữ liệu. Vui lòng kiểm tra cấu hình database.");
            }
        } catch (Exception $e) {
            error_log("Lỗi khởi tạo NguoiDung: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function create($data) {
        // Kiểm tra kết nối trước khi thực hiện truy vấn
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }
        
        try {
            // Tạo MaNguoiDung
            $maNguoiDung = $this->generateNguoiDungId();
            
            $query = "INSERT INTO " . $this->table_name . " 
                      SET MaNguoiDung=:MaNguoiDung, HoTen=:HoTen, Email=:Email, 
                      SDT=:SDT, DiaChi=:DiaChi, NgaySinh=:NgaySinh";
            
            $stmt = $this->conn->prepare($query);
        
            // Sanitize input và gán giá trị mặc định nếu không có
            $data['HoTen'] = htmlspecialchars(strip_tags($data['HoTen'] ?? ""));
            $data['Email'] = htmlspecialchars(strip_tags($data['Email'] ?? ""));
            $data['DiaChi'] = htmlspecialchars(strip_tags($data['DiaChi'] ?? ""));
            $data['NgaySinh'] = !empty($data['NgaySinh']) ? htmlspecialchars(strip_tags($data['NgaySinh'])) : null;
            $data['SDT'] = htmlspecialchars(strip_tags($data['SDT'] ?? ""));
        
            // Bind values
            $stmt->bindParam(":MaNguoiDung", $maNguoiDung);
            $stmt->bindParam(":HoTen", $data['HoTen']);
            $stmt->bindParam(":Email", $data['Email']);
            $stmt->bindParam(":SDT", $data['SDT']);
            $stmt->bindParam(":DiaChi", $data['DiaChi']);
            $stmt->bindParam(":NgaySinh", $data['NgaySinh']);
        
            if ($stmt->execute()) {
                // Lưu ID vừa tạo
                $this->lastInsertedId = $maNguoiDung;
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Lỗi tạo người dùng: " . $e->getMessage());
            throw new Exception("Không thể tạo người dùng: " . $e->getMessage());
        }
    }

    public function getLastInsertId() {
        if (isset($this->lastInsertedId)) {
            return $this->lastInsertedId;
        }
        return null;
    }

    private function generateNguoiDungId() {
        return 'ND' . uniqid();
    }

    // Lấy thông tin người dùng theo MaNguoiDung
    public function getById($maNguoiDung) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }
        
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE MaNguoiDung = :MaNguoiDung";
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(":MaNguoiDung", $maNguoiDung);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi lấy thông tin người dùng: " . $e->getMessage());
            throw new Exception("Không thể lấy thông tin người dùng: " . $e->getMessage());
        }
    }
    


    
    
}