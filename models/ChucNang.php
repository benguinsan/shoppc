<?php
require_once __DIR__ . '/../config/Database.php';

class ChucNang
{
    private $conn;
    private $table_name = "chucnang";
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
            error_log("Lỗi khởi tạo ChucNang: " . $e->getMessage());
            throw $e;
        }
    }

    public function getAll($page = 1, $limit = 10, $searchTerm = '', $orderBy = 'created_at', $orderDirection = 'DESC') {
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
            
            $validColumns = ['MaChucNang', 'TenChucNang', 'MoTa', 'created_at'];
            if (!in_array($orderBy, $validColumns)) {
                $orderBy = 'created_at';
            }
            
            $query = "SELECT * FROM " . $this->table_name;
            $countQuery = "SELECT COUNT(*) as total FROM " . $this->table_name;
            $params = [];
            
            if (!empty($searchTerm)) {
                $query .= " WHERE (TenChucNang LIKE :searchTerm OR MoTa LIKE :searchTerm)";
                $countQuery .= " WHERE (TenChucNang LIKE :searchTerm OR MoTa LIKE :searchTerm)";
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
            error_log("Lỗi lấy danh sách chức năng: " . $e->getMessage());
            throw new Exception("Không thể lấy danh sách chức năng: " . $e->getMessage());
        }
    }
    
    public function create($data) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }
        
        try {
            $maChucNang = $this->generateChucNangId();
            
            $query = "INSERT INTO " . $this->table_name . " 
                      SET MaChucNang=:MaChucNang, TenChucNang=:TenChucNang, MoTa=:MoTa";
            
            $stmt = $this->conn->prepare($query);
        
            $data['TenChucNang'] = htmlspecialchars(strip_tags($data['TenChucNang'] ?? ""));
            $data['MoTa'] = htmlspecialchars(strip_tags($data['MoTa'] ?? ""));
        
            $stmt->bindParam(":MaChucNang", $maChucNang);
            $stmt->bindParam(":TenChucNang", $data['TenChucNang']);
            $stmt->bindParam(":MoTa", $data['MoTa']);
        
            if ($stmt->execute()) {
                $this->lastInsertedId = $maChucNang;
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Lỗi tạo chức năng: " . $e->getMessage());
            throw new Exception("Không thể tạo chức năng: " . $e->getMessage());
        }
    }

    public function getLastInsertId() {
        if (isset($this->lastInsertedId)) {
            return $this->lastInsertedId;
        }
        return null;
    }

    private function generateChucNangId() {
        return 'CN' . uniqid();
    }

    public function getById($maChucNang) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }
        
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE MaChucNang = :MaChucNang";
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(":MaChucNang", $maChucNang);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi lấy thông tin chức năng: " . $e->getMessage());
            throw new Exception("Không thể lấy thông tin chức năng: " . $e->getMessage());
        }
    }

    public function update($maChucNang, $data) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }
        
        try {
            $currentData = $this->getById($maChucNang);
            if (!$currentData) {
                throw new Exception("Không tìm thấy chức năng với mã: " . $maChucNang);
            }
            
            $updatedData = array_merge($currentData, $data);
            
            $query = "UPDATE " . $this->table_name . " 
                      SET TenChucNang=:TenChucNang, MoTa=:MoTa
                      WHERE MaChucNang=:MaChucNang";
            
            $stmt = $this->conn->prepare($query);
            
            $tenChucNang = htmlspecialchars(strip_tags($updatedData['TenChucNang'] ?? ""));
            $moTa = htmlspecialchars(strip_tags($updatedData['MoTa'] ?? ""));
            
            $stmt->bindParam(":TenChucNang", $tenChucNang);
            $stmt->bindParam(":MoTa", $moTa);
            $stmt->bindParam(":MaChucNang", $maChucNang);
            
            if ($stmt->execute()) {
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Lỗi cập nhật thông tin chức năng: " . $e->getMessage());
            throw new Exception("Không thể cập nhật thông tin chức năng: " . $e->getMessage());
        }
    }

    public function delete($maChucNang) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }
        
        try {
            // Kiểm tra xem chức năng có tồn tại không
            $checkFunction = $this->getById($maChucNang);
            if (!$checkFunction) {
                throw new Exception("Không tìm thấy chức năng với mã: " . $maChucNang);
            }
            
            // Kiểm tra xem chức năng có đang được sử dụng trong bảng chi tiết nhóm quyền không
            $query = "SELECT COUNT(*) as count FROM chitietnhomquyen WHERE MaChucNang = :MaChucNang";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaChucNang", $maChucNang);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                throw new Exception("Không thể xóa chức năng vì đang được sử dụng trong nhóm quyền");
            }
            
            // Xóa chức năng
            $query = "DELETE FROM " . $this->table_name . " WHERE MaChucNang = :MaChucNang";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaChucNang", $maChucNang);

            if ($stmt->execute()) {
                return true;
            }

            return false;
        } catch (PDOException $e) {
            error_log("Lỗi xóa chức năng: " . $e->getMessage());
            throw new Exception("Không thể xóa chức năng: " . $e->getMessage());
        }
    }
}