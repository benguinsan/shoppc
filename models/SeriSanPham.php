<?php
require_once __DIR__ . '/../config/Database.php';

class SeriSanPham {
    private $conn;
    private $table_name = "serisanpham";

    public function __construct($connection = null) {
        if ($connection !== null) {
            $this->conn = $connection;
        } else {
            $db = new Database();
            $this->conn = $db->getConnection();
        }
    }

    // Hàm random số seri không trùng
    public function generateUniqueSeri($maSP, $length = 16) {
        do {
            $seri = $this->randomSeri($length);
            $exists = $this->checkSeriExists($seri);
        } while ($exists);
        return $seri;
    }

    private function randomSeri($length = 16) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $seri = '';
        for ($i = 0; $i < $length; $i++) {
            $seri .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $seri;
    }

    public function checkSeriExists($seri) {
        $query = "SELECT COUNT(*) as count FROM $this->table_name WHERE SoSeri = :seri";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":seri", $seri);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'] > 0;
    }

    public function insertSeri($maSP, $seri, $trangThai = 1) {
        $query = "INSERT INTO $this->table_name (MaSeri, MaSP, SoSeri, TrangThai) VALUES (:MaSeri, :MaSP, :SoSeri, :TrangThai)";
        $maSeri = uniqid('SERI');
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":MaSeri", $maSeri);
        $stmt->bindParam(":MaSP", $maSP);
        $stmt->bindParam(":SoSeri", $seri);
        $stmt->bindParam(":TrangThai", $trangThai);
        $stmt->execute();
        return $maSeri;
    }

    public function countByMaSP($maSP) {
        $query = "SELECT COUNT(*) as count FROM $this->table_name WHERE MaSP = :maSP";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":maSP", $maSP);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$row['count'];
    }

    // Lấy seri còn trống (TrangThai = 0) theo MaSP, trả về 1 bản ghi đầu tiên hoặc null
    public function getSeriConTrongByMaSP($maSP) {
        $query = "SELECT * FROM $this->table_name WHERE MaSP = :maSP AND TrangThai = 0 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":maSP", $maSP);
        $stmt->execute();
        $seri = $stmt->fetch(PDO::FETCH_ASSOC);
        return $seri ? $seri : null;
    }

    // Cập nhật trạng thái seri theo MaSeri
    public function updateTrangThaiSeriByMaSeri($maSeri, $trangThai) {
        $query = "UPDATE $this->table_name SET TrangThai = :trangThai WHERE MaSeri = :maSeri";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":trangThai", $trangThai, PDO::PARAM_INT);
        $stmt->bindParam(":maSeri", $maSeri);
        return $stmt->execute();
    }
}
