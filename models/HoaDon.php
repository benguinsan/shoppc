<?php
require_once 'config/Database.php';

class HoaDon {
    // Database connection
    private $conn;
    private $table = 'hoadon';

    // Properties
    public $MaHD;
    public $MaNguoiDung;
    public $MaNhanVien;
    public $NgayLap;
    public $TongTien;
    public $TrangThai;

    // Constructor with DB
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Get all invoices
    public function getAll() {
        $query = "SELECT * FROM " . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Get invoice by ID
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE MaHD = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt;
    }

    // Get invoices by user ID
    public function getByUserId($maNguoiDung) {
        $query = "SELECT * FROM " . $this->table . " WHERE MaNguoiDung = :maNguoiDung";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':maNguoiDung', $maNguoiDung);
        $stmt->execute();
        return $stmt;
    }

    // Get invoices by staff ID
    public function getByStaffId($maNhanVien) {
        $query = "SELECT * FROM " . $this->table . " WHERE MaNhanVien = :maNhanVien";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':maNhanVien', $maNhanVien);
        $stmt->execute();
        return $stmt;
    }

    // Get invoices by date range
    public function getByDateRange($startDate, $endDate) {
        $query = "SELECT * FROM " . $this->table . " WHERE NgayLap BETWEEN :startDate AND :endDate";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':startDate', $startDate);
        $stmt->bindParam(':endDate', $endDate);
        $stmt->execute();
        return $stmt;
    }

    // Create a new invoice
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (MaHD, MaNguoiDung, MaNhanVien, NgayLap, TongTien, TrangThai) 
                  VALUES 
                  (:MaHD, :MaNguoiDung, :MaNhanVien, :NgayLap, :TongTien, :TrangThai)";

        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->MaHD = htmlspecialchars(strip_tags($this->MaHD));
        $this->MaNguoiDung = htmlspecialchars(strip_tags($this->MaNguoiDung));
        $this->MaNhanVien = htmlspecialchars(strip_tags($this->MaNhanVien));
        $this->NgayLap = htmlspecialchars(strip_tags($this->NgayLap));
        $this->TongTien = htmlspecialchars(strip_tags($this->TongTien));
        $this->TrangThai = htmlspecialchars(strip_tags($this->TrangThai));

        // Bind parameters
        $stmt->bindParam(':MaHD', $this->MaHD);
        $stmt->bindParam(':MaNguoiDung', $this->MaNguoiDung);
        $stmt->bindParam(':MaNhanVien', $this->MaNhanVien);
        $stmt->bindParam(':NgayLap', $this->NgayLap);
        $stmt->bindParam(':TongTien', $this->TongTien);
        $stmt->bindParam(':TrangThai', $this->TrangThai);

        // Execute query
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Update an invoice
    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET 
                  MaNguoiDung = :MaNguoiDung, 
                  MaNhanVien = :MaNhanVien, 
                  NgayLap = :NgayLap, 
                  TongTien = :TongTien, 
                  TrangThai = :TrangThai 
                  WHERE MaHD = :MaHD";

        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->MaHD = htmlspecialchars(strip_tags($this->MaHD));
        $this->MaNguoiDung = htmlspecialchars(strip_tags($this->MaNguoiDung));
        $this->MaNhanVien = htmlspecialchars(strip_tags($this->MaNhanVien));
        $this->NgayLap = htmlspecialchars(strip_tags($this->NgayLap));
        $this->TongTien = htmlspecialchars(strip_tags($this->TongTien));
        $this->TrangThai = htmlspecialchars(strip_tags($this->TrangThai));

        // Bind parameters
        $stmt->bindParam(':MaHD', $this->MaHD);
        $stmt->bindParam(':MaNguoiDung', $this->MaNguoiDung);
        $stmt->bindParam(':MaNhanVien', $this->MaNhanVien);
        $stmt->bindParam(':NgayLap', $this->NgayLap);
        $stmt->bindParam(':TongTien', $this->TongTien);
        $stmt->bindParam(':TrangThai', $this->TrangThai);

        // Execute query
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Update invoice status
    public function updateStatus($status) {
        $query = "UPDATE " . $this->table . " SET TrangThai = :trangThai WHERE MaHD = :MaHD";
        
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(':trangThai', $status);
        $stmt->bindParam(':MaHD', $this->MaHD);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Delete an invoice
    public function delete() {
        $query = "DELETE FROM " . $this->table . " WHERE MaHD = :MaHD";
        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->MaHD = htmlspecialchars(strip_tags($this->MaHD));
        
        // Bind parameter
        $stmt->bindParam(':MaHD', $this->MaHD);

        // Execute query
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Get total sales by date range
    public function getTotalSalesByDateRange($startDate, $endDate) {
        $query = "SELECT SUM(TongTien) as TotalSales FROM " . $this->table . " 
                  WHERE NgayLap BETWEEN :startDate AND :endDate
                  AND TrangThai = 'Đã thanh toán'";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':startDate', $startDate);
        $stmt->bindParam(':endDate', $endDate);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['TotalSales'] ?? 0;
    }

    // Generate a unique invoice ID
    public function generateInvoiceId() {
        return 'HD' . uniqid();
    }
}
?>