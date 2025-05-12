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



    public function countByMaSP($maSP) {
        $query = "SELECT COUNT(*) as count FROM $this->table_name WHERE MaSP = :maSP";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":maSP", $maSP);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$row['count'];
    }

    // Hàm tạo mới seri với MaSP, MaSeri và SoSeri đều random
    public function createSeri($maSP, $trangThai = 1) {
        $maSeri = $this->randomSeri(16); // Random MaSeri
        // Đảm bảo MaSeri không trùng
        while ($this->checkSeriExists($maSeri)) {
            $maSeri = $this->randomSeri(16);
        }
        $soSeri = $this->randomSeri(16); // Random SoSeri
        // Đảm bảo SoSeri không trùng
        while ($this->checkSeriExists($soSeri)) {
            $soSeri = $this->randomSeri(16);
        }
        $query = "INSERT INTO $this->table_name (MaSeri, MaSP, SoSeri, TrangThai) VALUES (:MaSeri, :MaSP, :SoSeri, :TrangThai)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":MaSeri", $maSeri);
        $stmt->bindParam(":MaSP", $maSP);
        $stmt->bindParam(":SoSeri", $soSeri);
        $stmt->bindParam(":TrangThai", $trangThai);
        $stmt->execute();
        return [
            'MaSeri' => $maSeri,
            'SoSeri' => $soSeri
        ];
    }
}
