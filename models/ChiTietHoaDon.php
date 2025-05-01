<?php
require_once __DIR__ . '/../config/Database.php';

class ChiTietHoaDon {
    private $conn;
    private $table_name = "chitiethoadon";

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
            error_log("Lỗi khởi tạo ChiTietHoaDon: " . $e->getMessage());
            throw $e;
        }
    }

    public function getByMaHD($maHD) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            $query = "SELECT ct.*, sp.TenSP 
                     FROM " . $this->table_name . " ct
                     LEFT JOIN sanpham sp ON ct.MaSP = sp.MaSP
                     WHERE ct.MaHD = :MaHD";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaHD", $maHD);
            $stmt->execute();

            $chiTietList = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $chiTietList;
        } catch (PDOException $e) {
            error_log("Lỗi lấy chi tiết hóa đơn: " . $e->getMessage());
            throw new Exception("Không thể lấy chi tiết hóa đơn: " . $e->getMessage());
        }
    }
    
    public function getAll() {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            $query = "SELECT ct.*, sp.TenSP, hd.NgayLap, hd.TrangThai 
                     FROM " . $this->table_name . " ct
                     LEFT JOIN sanpham sp ON ct.MaSP = sp.MaSP
                     LEFT JOIN hoadon hd ON ct.MaHD = hd.MaHD
                     ORDER BY ct.MaHD, ct.MaCTHD";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            $chiTietList = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $chiTietList;
        } catch (PDOException $e) {
            error_log("Lỗi lấy danh sách chi tiết hóa đơn: " . $e->getMessage());
            throw new Exception("Không thể lấy danh sách chi tiết hóa đơn: " . $e->getMessage());
        }
    }
} 