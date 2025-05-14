<?php
require_once __DIR__ . '/../config/Database.php';

class ChiTietBaoHanh {
    private $conn;
    private $table_name = "chitietbaohanh";

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
            error_log("Lỗi khởi tạo ChiTietBaoHanh: " . $e->getMessage());
            throw $e;
        }
    }

    public function getByMaBH($maBH) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE MaBH = :MaBH ORDER BY NgayBaoHanh DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaBH", $maBH);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi lấy chi tiết bảo hành: " . $e->getMessage());
            throw new Exception("Không thể lấy chi tiết bảo hành: " . $e->getMessage());
        }
    }

    public function getAll() {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }
        try {
            $query = "SELECT ctbh.*, bh.MaSeri FROM " . $this->table_name . " ctbh LEFT JOIN baohanh bh ON ctbh.MaBH = bh.MaBH ORDER BY ctbh.MaBH, ctbh.MaCTBH";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi lấy danh sách chi tiết bảo hành: " . $e->getMessage());
            throw new Exception("Không thể lấy danh sách chi tiết bảo hành: " . $e->getMessage());
        }
    }

    public function create($data) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }
        try {
            $this->conn->beginTransaction();
            // Kiểm tra bảo hành có tồn tại không
            $checkBHQuery = "SELECT COUNT(*) as count FROM baohanh WHERE MaBH = :MaBH";
            $checkBHStmt = $this->conn->prepare($checkBHQuery);
            $checkBHStmt->bindParam(":MaBH", $data['MaBH']);
            $checkBHStmt->execute();
            $bhCount = $checkBHStmt->fetch(PDO::FETCH_ASSOC)['count'];
            if ($bhCount == 0) {
                throw new Exception("Bảo hành với mã {$data['MaBH']} không tồn tại.");
            }
            // Tạo mã chi tiết bảo hành mới (CTBH001, ...)
            $getMaxIdQuery = "SELECT MAX(MaCTBH) as maxId FROM " . $this->table_name;
            $getMaxIdStmt = $this->conn->prepare($getMaxIdQuery);
            $getMaxIdStmt->execute();
            $maxId = $getMaxIdStmt->fetch(PDO::FETCH_ASSOC)['maxId'];
            if ($maxId) {
                preg_match('/CTBH(\\d+)/', $maxId, $matches);
                if (isset($matches[1])) {
                    $nextNumber = intval($matches[1]) + 1;
                    $newMaCTBH = 'CTBH' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
                } else {
                    $newMaCTBH = 'CTBH001';
                }
            } else {
                $newMaCTBH = 'CTBH001';
            }
            // Thêm chi tiết bảo hành
            $query = "INSERT INTO " . $this->table_name . " (MaCTBH, MaBH, NgayBaoHanh, NgayHoanThanh, TinhTrang, ChiTiet) VALUES (:MaCTBH, :MaBH, :NgayBaoHanh, :NgayHoanThanh, :TinhTrang, :ChiTiet)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaCTBH", $newMaCTBH);
            $stmt->bindParam(":MaBH", $data['MaBH']);
            $stmt->bindParam(":NgayBaoHanh", $data['NgayBaoHanh']);
            $stmt->bindParam(":NgayHoanThanh", $data['NgayHoanThanh']);
            $stmt->bindParam(":TinhTrang", $data['TinhTrang']);
            $stmt->bindParam(":ChiTiet", $data['ChiTiet']);
            $stmt->execute();
            $this->conn->commit();
            return [
                'id' => $newMaCTBH,
                'message' => 'Tạo chi tiết bảo hành thành công'
            ];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Lỗi tạo chi tiết bảo hành: " . $e->getMessage());
            throw new Exception("Không thể tạo chi tiết bảo hành: " . $e->getMessage());
        }
    }

    public function update($maCTBH, $data) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }
        try {
            // Kiểm tra chi tiết bảo hành có tồn tại không
            $checkQuery = "SELECT * FROM " . $this->table_name . " WHERE MaCTBH = :MaCTBH";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(":MaCTBH", $maCTBH);
            $checkStmt->execute();
            $ctbhHienTai = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if (!$ctbhHienTai) {
                throw new Exception("Chi tiết bảo hành với mã {$maCTBH} không tồn tại.");
            }
            // Xây dựng câu lệnh UPDATE
            $setClause = [];
            $params = [':MaCTBH' => $maCTBH];
            $allowedFields = ['NgayBaoHanh', 'NgayHoanThanh', 'TinhTrang', 'ChiTiet'];
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $setClause[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }
            if (empty($setClause)) {
                throw new Exception("Không có thông tin nào được cập nhật.");
            }
            $query = "UPDATE " . $this->table_name . " SET " . implode(", ", $setClause) . " WHERE MaCTBH = :MaCTBH";
            $stmt = $this->conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            return [
                'id' => $maCTBH,
                'message' => 'Cập nhật chi tiết bảo hành thành công',
                'affected_rows' => $stmt->rowCount()
            ];
        } catch (PDOException $e) {
            error_log("Lỗi cập nhật chi tiết bảo hành: " . $e->getMessage());
            throw new Exception("Không thể cập nhật chi tiết bảo hành: " . $e->getMessage());
        }
    }

    public function delete($maCTBH) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }
        try {
            $query = "DELETE FROM " . $this->table_name . " WHERE MaCTBH = :MaCTBH";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaCTBH", $maCTBH);
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Lỗi xóa chi tiết bảo hành: " . $e->getMessage());
            throw new Exception("Không thể xóa chi tiết bảo hành: " . $e->getMessage());
        }
    }
} 