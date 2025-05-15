<?php
require_once __DIR__ . '/../config/Database.php';

class PhieuNhap {
    private $conn;
    private $table_name = "phieunhap";
    private $primary_key = "MaPhieuNhap";

    public function __construct($connection = null) {
        try {
            if ($connection !== null) {
                $this->conn = $connection;
            } else {
                $db = new Database();
                $this->conn = $db->getConnection();
            }

            if ($this->conn === null) {
                throw new Exception("Không thể kết nối đến cơ sở dữ liệu");
            }
        } catch (Exception $e) {
            error_log("Lỗi khởi tạo PhieuNhap: " . $e->getMessage());
            throw $e;
        }
    }

    public function getAll($page = 1, $limit = 10, $searchTerm = '', $fromDate = '', $toDate = '', $status = '', $orderBy = 'MaPhieuNhap', $orderDirection = 'DESC') {
        try {
            $page = max(1, (int)$page);
            $limit = max(1, (int)$limit);
            $offset = ($page - 1) * $limit;

            $orderDirection = strtoupper($orderDirection) === 'ASC' ? 'ASC' : 'DESC';
            $validColumns = ['MaPhieuNhap', 'NgayNhap', 'TongTien', 'TrangThai'];
            $orderBy = in_array($orderBy, $validColumns) ? $orderBy : 'NgayNhap';

            $query = "SELECT pn.*, 
                            ncc.TenNCC as TenNhaCungCap,
                            nv.HoTen as TenNhanVien
                    FROM " . $this->table_name . " pn
                    LEFT JOIN nhacungcap ncc ON pn.MaNCC = ncc.MaNCC
                    LEFT JOIN nguoidung nv ON pn.MaNhanVien = nv.MaNguoiDung
                    WHERE 1=1";

            $countQuery = "SELECT COUNT(*) as total FROM " . $this->table_name . " pn
                    LEFT JOIN nhacungcap ncc ON pn.MaNCC = ncc.MaNCC
                    LEFT JOIN nguoidung nv ON pn.MaNhanVien = nv.MaNguoiDung
                    WHERE 1=1";

            $params = [];

            if (!empty($searchTerm)) {
                $query .= " AND (pn.MaPhieuNhap LIKE :search 
                           OR ncc.TenNCC LIKE :search 
                           OR nv.HoTen LIKE :search)";
                $countQuery .= " AND (pn.MaPhieuNhap LIKE :search 
                               OR ncc.TenNCC LIKE :search
                               OR nv.HoTen LIKE :search)";
                $params[':search'] = "%$searchTerm%";
            }

            if (!empty($fromDate)) {
                $query .= " AND DATE(pn.NgayNhap) >= :fromDate";
                $countQuery .= " AND DATE(pn.NgayNhap) >= :fromDate";
                $params[':fromDate'] = $fromDate;
            }

            if (!empty($toDate)) {
                $query .= " AND DATE(pn.NgayNhap) <= :toDate";
                $countQuery .= " AND DATE(pn.NgayNhap) <= :toDate";
                $params[':toDate'] = $toDate;
            }

            if ($status !== '') {
                $query .= " AND pn.TrangThai = :status";
                $countQuery .= " AND pn.TrangThai = :status";
                $params[':status'] = $status;
            }

            $query .= " ORDER BY pn." . $orderBy . " " . $orderDirection;
            $query .= " LIMIT :limit OFFSET :offset";

            $countStmt = $this->conn->prepare($countQuery);
            foreach($params as $key => $value) {
                if($key != ':limit' && $key != ':offset') {
                    $countStmt->bindValue($key, $value);
                }
            }
            $countStmt->execute();
            $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $this->conn->prepare($query);
            foreach($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'pagination' => [
                    'total' => (int)$totalRecords,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'last_page' => ceil($totalRecords / $limit),
                    'from' => $offset + 1,
                    'to' => min($offset + $limit, $totalRecords)
                ]
            ];

        } catch (PDOException $e) {
            error_log("Lỗi lấy danh sách phiếu nhập: " . $e->getMessage());
            throw new Exception("Không thể lấy danh sách phiếu nhập");
        }
    }
    
    public function getNhanVien() {
        try {
            $query = "SELECT nd.*, tk.*
                      FROM nguoidung nd
                      JOIN taikhoan tk ON nd.MaNguoiDung = tk.MaNguoiDung
                      WHERE tk.MaNhomQuyen = 'NHANVIEN'
                      AND (tk.TrangThai = 1 OR tk.TrangThai IS NULL)
                      ORDER BY nd.HoTen ASC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'data' => $result
            ];
        } catch (PDOException $e) {
            error_log("Lỗi lấy danh sách nhân viên: " . $e->getMessage());
            throw new Exception("Không thể lấy danh sách nhân viên: " . $e->getMessage());
        }
    }

    public function postPhieuNhap($MaNCC, $MaNhanVien, $NgayNhap, $danhSachSanPham, $TongTien, $TrangThai = 1) {
        try {
            $this->conn->beginTransaction();

            // 1. Tạo mã phiếu nhập mới
            $MaPhieuNhap = uniqid('PN');

            // 2. Thêm vào bảng phieunhap
            $sqlPN = "INSERT INTO phieunhap (MaPhieuNhap, MaNCC, MaNhanVien, NgayNhap, TongTien, TrangThai) VALUES (?, ?, ?, ?, ?, ?)";
            $stmtPN = $this->conn->prepare($sqlPN);
            $stmtPN->execute([$MaPhieuNhap, $MaNCC, $MaNhanVien, $NgayNhap, $TongTien, $TrangThai]);

            // 3. Thêm vào bảng chitietphieunhap
            foreach ($danhSachSanPham as $sp) {
                $MaCTPN = uniqid('CTPN');
                $sqlCT = "INSERT INTO chitietphieunhap (MaCTPN, MaPhieuNhap, MaSP, SoLuong, DonGia, ThanhTien) VALUES (?, ?, ?, ?, ?, ?)";
                $stmtCT = $this->conn->prepare($sqlCT);
                $stmtCT->execute([
                    $MaCTPN,
                    $MaPhieuNhap,
                    $sp['MaSP'],
                    $sp['SoLuong'],
                    $sp['DonGia'],
                    $sp['ThanhTien']
                ]);

                // 4. Nếu có danh sách seri, thêm vào bảng sanphamsoseri
                if (isset($sp['SoSeri']) && is_array($sp['SoSeri'])) {
                    foreach ($sp['SoSeri'] as $seri) {
                        $MaSeri = uniqid('SERI');
                        $sqlSeri = "INSERT INTO sanphamsoseri (MaSeri, MaSP, SoSeri, TrangThai) VALUES (?, ?, ?, 1)";
                        $stmtSeri = $this->conn->prepare($sqlSeri);
                        $stmtSeri->execute([$MaSeri, $sp['MaSP'], $seri]);
                    }
                }
            }

            $this->conn->commit();
            return [
                'status' => 'success',
                'MaPhieuNhap' => $MaPhieuNhap
            ];
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Lỗi thêm phiếu nhập: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    public function create($data) {
        try {
            $this->conn->beginTransaction();
            $MaPhieuNhap = uniqid('PN');
            $sql = "INSERT INTO phieunhap (MaPhieuNhap, MaNCC, MaNhanVien, NgayNhap, TongTien, TrangThai) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $MaPhieuNhap,
                $data['MaNCC'],
                $data['MaNhanVien'],
                $data['NgayNhap'],
                $data['TongTien'],
                $data['TrangThai'] ?? 1
            ]);
            $this->conn->commit();
            return [
                'status' => 'success',
                'MaPhieuNhap' => $MaPhieuNhap
            ];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    // Thêm hàm cập nhật phiếu nhập
    public function update($maPhieuNhap, $data) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }
        try {
            // Kiểm tra phiếu nhập có tồn tại không
            $checkQuery = "SELECT COUNT(*) as count FROM " . $this->table_name . " WHERE MaPhieuNhap = :MaPhieuNhap";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(":MaPhieuNhap", $maPhieuNhap);
            $checkStmt->execute();
            $count = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
            if ($count == 0) {
                throw new Exception("Phiếu nhập với mã {$maPhieuNhap} không tồn tại.");
            }

            // Bắt đầu transaction
            $this->conn->beginTransaction();

            // Các trường cho phép cập nhật
            $allowedFields = ['MaNCC', 'MaNhanVien', 'NgayNhap', 'TongTien', 'TrangThai'];
            $setClause = [];
            $params = [':MaPhieuNhap' => $maPhieuNhap];
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $setClause[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }
            if (empty($setClause)) {
                throw new Exception("Không có thông tin nào được cập nhật.");
            }
            $query = "UPDATE " . $this->table_name . " SET " . implode(", ", $setClause) . " WHERE MaPhieuNhap = :MaPhieuNhap";
            $stmt = $this->conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $this->conn->commit();
            return [
                'id' => $maPhieuNhap,
                'message' => 'Cập nhật phiếu nhập thành công',
                'affected_rows' => $stmt->rowCount()
            ];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Lỗi cập nhật phiếu nhập: " . $e->getMessage());
            throw new Exception("Không thể cập nhật phiếu nhập: " . $e->getMessage());
        }
    }

    /**
     * Cập nhật tổng tiền của phiếu nhập dựa trên tổng các chi tiết phiếu nhập
     */
    public function updateTongTien($maPhieuNhap) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }
        try {
            // Kiểm tra phiếu nhập có tồn tại không
            $checkQuery = "SELECT COUNT(*) as count FROM " . $this->table_name . " WHERE MaPhieuNhap = :MaPhieuNhap";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(":MaPhieuNhap", $maPhieuNhap);
            $checkStmt->execute();
            $count = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
            if ($count == 0) {
                throw new Exception("Phiếu nhập với mã {$maPhieuNhap} không tồn tại.");
            }

            // Tính tổng tiền từ các chi tiết phiếu nhập
            $query = "SELECT SUM(ThanhTien) as TongTien FROM chitietphieunhap WHERE MaPhieuNhap = :MaPhieuNhap";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaPhieuNhap", $maPhieuNhap);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $tongTien = $result['TongTien'] ?: 0;

            // Cập nhật tổng tiền cho phiếu nhập
            $updateQuery = "UPDATE " . $this->table_name . " SET TongTien = :TongTien WHERE MaPhieuNhap = :MaPhieuNhap";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindParam(":TongTien", $tongTien);
            $updateStmt->bindParam(":MaPhieuNhap", $maPhieuNhap);
            $updateStmt->execute();

            return [
                'id' => $maPhieuNhap,
                'message' => 'Cập nhật tổng tiền phiếu nhập thành công',
                'affected_rows' => $updateStmt->rowCount(),
                'TongTien' => $tongTien
            ];
        } catch (PDOException $e) {
            error_log("Lỗi cập nhật tổng tiền phiếu nhập: " . $e->getMessage());
            throw new Exception("Không thể cập nhật tổng tiền phiếu nhập: " . $e->getMessage());
        }
    }
} 