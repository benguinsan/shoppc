<?php
require_once __DIR__ . '/../config/Database.php';
class SanPham {
    private $conn;
    private $table_name = "sanpham";
    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }
    public function getAll() {
        $query = "SELECT MaSP, TenSP FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
