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

    public function getAll($page = 1, $limit = 10, $searchTerm = '', $orderBy = 'created_at', $orderDirection = 'DESC') {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }
        
        try {
            // Đảm bảo page và limit là số nguyên dương
            $page = max(1, (int)$page);
            $limit = max(1, (int)$limit);
            $offset = ($page - 1) * $limit;
            
            // Validate orderDirection
            $orderDirection = strtoupper($orderDirection);
            if ($orderDirection !== 'ASC' && $orderDirection !== 'DESC') {
                $orderDirection = 'DESC';
            }
            
            // Validate orderBy - đảm bảo orderBy là một cột hợp lệ
            $validColumns = ['MaNguoiDung', 'HoTen', 'Email', 'SDT', 'DiaChi', 'NgaySinh', 'created_at'];
            if (!in_array($orderBy, $validColumns)) {
                $orderBy = 'created_at'; // Mặc định sắp xếp theo thời gian tạo
            }
            
            // Xây dựng câu truy vấn cơ bản
            $query = "SELECT * FROM " . $this->table_name;
            $countQuery = "SELECT COUNT(*) as total FROM " . $this->table_name;
            $params = [];
            
            // Thêm điều kiện tìm kiếm nếu có
            if (!empty($searchTerm)) {
                $query .= " WHERE (HoTen LIKE :searchTerm OR Email LIKE :searchTerm OR SDT LIKE :searchTerm OR DiaChi LIKE :searchTerm)";
                $countQuery .= " WHERE (HoTen LIKE :searchTerm OR Email LIKE :searchTerm OR SDT LIKE :searchTerm OR DiaChi LIKE :searchTerm)";
                $params[':searchTerm'] = "%$searchTerm%";
            }
            
            // Thêm sắp xếp - mặc định theo created_at giảm dần (mới nhất trước)
            $query .= " ORDER BY " . $orderBy . " " . $orderDirection;
            
            // Thêm phân trang
            $query .= " LIMIT :limit OFFSET :offset";
            
            // Chuẩn bị và thực thi câu truy vấn đếm tổng số bản ghi
            $countStmt = $this->conn->prepare($countQuery);
            if (!empty($searchTerm)) {
                $countStmt->bindParam(':searchTerm', $params[':searchTerm']);
            }
            $countStmt->execute();
            $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Chuẩn bị và thực thi câu truy vấn lấy dữ liệu
            $stmt = $this->conn->prepare($query);
            if (!empty($searchTerm)) {
                $stmt->bindParam(':searchTerm', $params[':searchTerm']);
            }
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            // Lấy dữ liệu
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Tính toán thông tin phân trang
            $totalPages = ceil($totalRecords / $limit);
            
            // Trả về kết quả
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
            error_log("Lỗi lấy danh sách người dùng: " . $e->getMessage());
            throw new Exception("Không thể lấy danh sách người dùng: " . $e->getMessage());
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

    public function update($maNguoiDung, $data) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }
        
        try {
            // Lấy dữ liệu hiện tại của người dùng
            $currentData = $this->getById($maNguoiDung);
            if (!$currentData) {
                throw new Exception("Không tìm thấy người dùng với mã: " . $maNguoiDung);
            }
            
            // Kết hợp dữ liệu hiện tại với dữ liệu mới
            $updatedData = array_merge($currentData, $data);
            
            $query = "UPDATE " . $this->table_name . " 
                      SET HoTen=:HoTen, Email=:Email, SDT=:SDT, 
                      DiaChi=:DiaChi, NgaySinh=:NgaySinh
                      WHERE MaNguoiDung=:MaNguoiDung";
            
            $stmt = $this->conn->prepare($query);
            
            // Sanitize input
            $hoTen = htmlspecialchars(strip_tags($updatedData['HoTen'] ?? ""));
            $email = htmlspecialchars(strip_tags($updatedData['Email'] ?? ""));
            $sdt = htmlspecialchars(strip_tags($updatedData['SDT'] ?? ""));
            $diaChi = htmlspecialchars(strip_tags($updatedData['DiaChi'] ?? ""));
            $ngaySinh = !empty($updatedData['NgaySinh']) ? htmlspecialchars(strip_tags($updatedData['NgaySinh'])) : null;
            
            // Bind values
            $stmt->bindParam(":HoTen", $hoTen);
            $stmt->bindParam(":Email", $email);
            $stmt->bindParam(":SDT", $sdt);
            $stmt->bindParam(":DiaChi", $diaChi);
            $stmt->bindParam(":NgaySinh", $ngaySinh);
            $stmt->bindParam(":MaNguoiDung", $maNguoiDung);
            
            if ($stmt->execute()) {
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Lỗi cập nhật thông tin người dùng: " . $e->getMessage());
            throw new Exception("Không thể cập nhật thông tin người dùng: " . $e->getMessage());
        }
    }

    public function delete($maNguoiDung) {
    
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }
        
        try {
          
            $checkUser = $this->getById($maNguoiDung);
            if (!$checkUser) {
                throw new Exception("Không tìm thấy người dùng với mã: " . $maNguoiDung);
            }
            
            $query = "DELETE FROM " . $this->table_name . " WHERE MaNguoiDung = :MaNguoiDung";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaNguoiDung", $maNguoiDung);

            if ($stmt->execute()) {
                return true;
            }

            return false;
        } catch (PDOException $e) {
            error_log("Lỗi xóa người dùng: " . $e->getMessage());
            throw new Exception("Không thể xóa người dùng: " . $e->getMessage());
        }
    }


    
    
}