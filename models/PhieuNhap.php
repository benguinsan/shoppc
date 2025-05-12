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
} 