<?php
require_once 'config/Database.php';

class HoaDon {
    // Database connection
    private $conn;
    private $table = 'hoadon';
    private $table_chitiet = 'chitiethoadon';

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

    // Thống kê theo ngày (chuẩn, không nhân tổng tiền khi join)
    public function thongKeTheoNgay($date) {
        $query = "SELECT 
                    (SELECT SUM(TongTien) FROM hoadon WHERE DATE(NgayLap) = :date) as DoanhThu,
                    (SELECT COUNT(*) FROM hoadon WHERE DATE(NgayLap) = :date) as SoDonHang,
                    (SELECT COUNT(*) FROM chitiethoadon WHERE MaHD IN (SELECT MaHD FROM hoadon WHERE DATE(NgayLap) = :date)) as SoLuongBan";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date', $date);
        $stmt->execute();
        return $stmt;
    }

    // Thống kê theo tháng (chuẩn, không nhân tổng tiền khi join)
    public function thongKeTheoThang($month) {
        $query = "SELECT 
                    (SELECT SUM(TongTien) FROM hoadon WHERE DATE_FORMAT(NgayLap, '%Y-%m') = :month) as DoanhThu,
                    (SELECT COUNT(*) FROM hoadon WHERE DATE_FORMAT(NgayLap, '%Y-%m') = :month) as SoDonHang,
                    (SELECT COUNT(*) FROM chitiethoadon WHERE MaHD IN (SELECT MaHD FROM hoadon WHERE DATE_FORMAT(NgayLap, '%Y-%m') = :month)) as SoLuongBan";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':month', $month);
        $stmt->execute();
        return $stmt;
    }

    // Thống kê theo năm (chuẩn, không nhân tổng tiền khi join)
    public function thongKeTheoNam($year) {
        $query = "SELECT 
                    (SELECT SUM(TongTien) FROM hoadon WHERE YEAR(NgayLap) = :year) as DoanhThu,
                    (SELECT COUNT(*) FROM hoadon WHERE YEAR(NgayLap) = :year) as SoDonHang,
                    (SELECT COUNT(*) FROM chitiethoadon WHERE MaHD IN (SELECT MaHD FROM hoadon WHERE YEAR(NgayLap) = :year)) as SoLuongBan";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':year', $year);
        $stmt->execute();
        return $stmt;
    }

    // Thống kê theo sản phẩm (có tên sản phẩm)
    public function thongKeTheoSanPham($type, $value) {
        $where = '';
        if ($type === 'day') $where = "WHERE DATE(h.NgayLap) = :value";
        if ($type === 'month') $where = "WHERE DATE_FORMAT(h.NgayLap, '%Y-%m') = :value";
        if ($type === 'year') $where = "WHERE YEAR(h.NgayLap) = :value";
        $query = "SELECT ct.MaSP, s.TenSP, COUNT(*) as SoLuongBan, SUM(ct.DonGia) as DoanhThu
                  FROM hoadon h
                  JOIN chitiethoadon ct ON h.MaHD = ct.MaHD
                  JOIN sanpham s ON ct.MaSP = s.MaSP
                  $where
                  GROUP BY ct.MaSP, s.TenSP
                  ORDER BY DoanhThu DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':value', $value);
        $stmt->execute();
        return $stmt;
    }

    // Thống kê theo loại sản phẩm (có tên loại sản phẩm)
    public function thongKeTheoLoaiSanPham($type, $value) {
        $where = '';
        if ($type === 'day') $where = "WHERE DATE(h.NgayLap) = :value";
        if ($type === 'month') $where = "WHERE DATE_FORMAT(h.NgayLap, '%Y-%m') = :value";
        if ($type === 'year') $where = "WHERE YEAR(h.NgayLap) = :value";
        $query = "SELECT l.MaLoaiSP, l.TenLoaiSP, SUM(ct.DonGia) as DoanhThu, COUNT(*) as SoLuongBan
                  FROM hoadon h
                  JOIN chitiethoadon ct ON h.MaHD = ct.MaHD
                  JOIN sanpham s ON ct.MaSP = s.MaSP
                  JOIN loaisanpham l ON s.MaLoaiSP = l.MaLoaiSP
                  $where
                  GROUP BY l.MaLoaiSP, l.TenLoaiSP
                  ORDER BY DoanhThu DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':value', $value);
        $stmt->execute();
        return $stmt;
    }

    public function getConnection() {
        return $this->conn;
    }
}
?>