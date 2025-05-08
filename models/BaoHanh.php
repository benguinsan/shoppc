<?php
require_once 'config/Database.php';

class BaoHanh {
    // Database connection
    private $conn;
    private $table = 'baohanh';

    // Properties
    public $MaBH;
    public $MaHD;
    public $MaSeri;
    public $NgayMua;
    public $HanBaoHanh;
    public $MoTa;

    // Constructor with DB
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Get all warranties
    public function getAll() {
        $query = "SELECT * FROM " . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Get warranty by ID
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE MaBH = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt;
    }

    // Get warranties by invoice ID
    public function getByInvoiceId($maHD) {
        $query = "SELECT * FROM " . $this->table . " WHERE MaHD = :maHD";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':maHD', $maHD);
        $stmt->execute();
        return $stmt;
    }

    // Get warranty by serial number
    public function getBySerialNumber($maSeri) {
        $query = "SELECT * FROM " . $this->table . " WHERE MaSeri = :maSeri";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':maSeri', $maSeri);
        $stmt->execute();
        return $stmt;
    }

    // Create a new warranty
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (MaBH, MaHD, MaSeri, NgayMua, HanBaoHanh, MoTa) 
                  VALUES 
                  (:MaBH, :MaHD, :MaSeri, :NgayMua, :HanBaoHanh, :MoTa)";

        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->MaBH = htmlspecialchars(strip_tags($this->MaBH));
        $this->MaHD = htmlspecialchars(strip_tags($this->MaHD));
        $this->MaSeri = htmlspecialchars(strip_tags($this->MaSeri));
        $this->NgayMua = htmlspecialchars(strip_tags($this->NgayMua));
        $this->HanBaoHanh = htmlspecialchars(strip_tags($this->HanBaoHanh));
        $this->MoTa = htmlspecialchars(strip_tags($this->MoTa));

        // Bind parameters
        $stmt->bindParam(':MaBH', $this->MaBH);
        $stmt->bindParam(':MaHD', $this->MaHD);
        $stmt->bindParam(':MaSeri', $this->MaSeri);
        $stmt->bindParam(':NgayMua', $this->NgayMua);
        $stmt->bindParam(':HanBaoHanh', $this->HanBaoHanh);
        $stmt->bindParam(':MoTa', $this->MoTa);

        // Execute query
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Update a warranty
    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET 
                  MaHD = :MaHD, 
                  MaSeri = :MaSeri, 
                  NgayMua = :NgayMua, 
                  HanBaoHanh = :HanBaoHanh, 
                  MoTa = :MoTa 
                  WHERE MaBH = :MaBH";

        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->MaBH = htmlspecialchars(strip_tags($this->MaBH));
        $this->MaHD = htmlspecialchars(strip_tags($this->MaHD));
        $this->MaSeri = htmlspecialchars(strip_tags($this->MaSeri));
        $this->NgayMua = htmlspecialchars(strip_tags($this->NgayMua));
        $this->HanBaoHanh = htmlspecialchars(strip_tags($this->HanBaoHanh));
        $this->MoTa = htmlspecialchars(strip_tags($this->MoTa));

        // Bind parameters
        $stmt->bindParam(':MaBH', $this->MaBH);
        $stmt->bindParam(':MaHD', $this->MaHD);
        $stmt->bindParam(':MaSeri', $this->MaSeri);
        $stmt->bindParam(':NgayMua', $this->NgayMua);
        $stmt->bindParam(':HanBaoHanh', $this->HanBaoHanh);
        $stmt->bindParam(':MoTa', $this->MoTa);

        // Execute query
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Delete a warranty
    public function delete() {
        $query = "DELETE FROM " . $this->table . " WHERE MaBH = :MaBH";
        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->MaBH = htmlspecialchars(strip_tags($this->MaBH));
        
        // Bind parameter
        $stmt->bindParam(':MaBH', $this->MaBH);

        // Execute query
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Check if warranty is still valid
    public function isValid() {
        $currentDate = date('Y-m-d');
        return $currentDate <= $this->HanBaoHanh;
    }

    // Generate a unique warranty ID
    public function generateWarrantyId() {
        return 'BH' . uniqid();
    }
}
?>