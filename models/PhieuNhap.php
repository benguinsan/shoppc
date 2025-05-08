<?php
require_once __DIR__ . '/../config/Database.php';

class PhieuNhap {
    private $conn;
    private $table_name = "phieunhap";
    private $lastInsertedId;

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
            error_log("Lỗi khởi tạo PhieuNhap: " . $e->getMessage());
            throw $e;
        }
    }

    public function getAll($page = 1, $limit = 10, $searchTerm = '', $fromDate = '', $toDate = '', $status = '', $orderBy = 'MaPhieuNhap', $orderDirection = 'ASC') {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            $page = max(1, (int)$page);
            $limit = max(1, (int)$limit);
            $offset = ($page - 1) * $limit;

            $orderDirection = strtoupper($orderDirection);
            if ($orderDirection !== 'ASC' && $orderDirection !== 'DESC') {
                $orderDirection = 'DESC';
            }

            $validColumns = ['MaPhieuNhap', 'NgayNhap', 'TongTien', 'TrangThai'];
            if (!in_array($orderBy, $validColumns)) {
                $orderBy = 'NgayNhap';
            }

            // Query chính với JOIN
            $query = "SELECT pn.*, 
                            ncc.TenNCC as TenNhaCungCap,
                            nv.HoTen as TenNhanVien
                     FROM " . $this->table_name . " pn
                     LEFT JOIN nhacungcap ncc ON pn.MaNCC = ncc.MaNCC
                     LEFT JOIN nguoidung nv ON pn.MaNhanVien = nv.MaNguoiDung
                     WHERE 1=1";

            // Query đếm tổng số record
            $countQuery = "SELECT COUNT(*) as total FROM " . $this->table_name . " pn 
                           LEFT JOIN nhacungcap ncc ON pn.MaNCC = ncc.MaNCC
                           LEFT JOIN nguoidung nv ON pn.MaNhanVien = nv.MaNguoiDung
                           WHERE 1=1";

            $params = array();

            // Thêm điều kiện tìm kiếm
            if (!empty($searchTerm)) {
                $query .= " AND (pn.MaPhieuNhap LIKE :search 
                           OR ncc.TenNCC LIKE :search 
                           OR nv.HoTen LIKE :search)";
                           
                $countQuery .= " AND (pn.MaPhieuNhap LIKE :search 
                               OR ncc.TenNCC LIKE :search
                               OR nv.HoTen LIKE :search)";
                               
                $params[':search'] = "%$searchTerm%";
            }

            // Lọc theo khoảng thời gian
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

            // Lọc theo trạng thái
            if ($status !== '') {
                $query .= " AND pn.TrangThai = :status";
                $countQuery .= " AND pn.TrangThai = :status";
                $params[':status'] = $status;
            }

            // Thêm sắp xếp và phân trang
            $query .= " ORDER BY pn." . $orderBy . " " . $orderDirection;
            $query .= " LIMIT :limit OFFSET :offset";

            // Thực thi query đếm tổng số record
            $countStmt = $this->conn->prepare($countQuery);
            foreach($params as $key => $value) {
                if($key != ':limit' && $key != ':offset') {
                    $countStmt->bindValue($key, $value);
                }
            }
            $countStmt->execute();
            $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Thực thi query chính
            $stmt = $this->conn->prepare($query);
            foreach($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'data' => $records,
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
            throw new Exception("Không thể lấy danh sách phiếu nhập: " . $e->getMessage());
        }
    }

    public function getById($maPhieuNhap) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }

        try {
            $query = "SELECT pn.*, 
                            ncc.TenNCC as TenNhaCungCap,
                            nv.HoTen as TenNhanVien
                     FROM " . $this->table_name . " pn
                     LEFT JOIN nhacungcap ncc ON pn.MaNCC = ncc.MaNCC
                     LEFT JOIN nguoidung nv ON pn.MaNhanVien = nv.MaNguoiDung
                     WHERE pn.MaPhieuNhap = :MaPhieuNhap";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaPhieuNhap", $maPhieuNhap);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi lấy thông tin phiếu nhập: " . $e->getMessage());
            throw new Exception("Không thể lấy thông tin phiếu nhập: " . $e->getMessage());
        }
    }

    public function getTrangThaiText($trangThai) {
        switch($trangThai) {
            case 0:
                return 'Đã hủy';
            case 1:
                return 'Chờ xử lý';
            case 2:
                return 'Đã xử lý';
            default:
                return 'Không xác định';
        }
    }

    public function create($data) {
        if ($this->conn === null) {
            throw new Exception("Kết nối database không khả dụng");
        }
        require_once __DIR__ . '/ChiTietPhieuNhap.php';
        require_once __DIR__ . '/SeriSanPham.php';
        try {
            error_log('[PHIEU_NHAP_CREATE_INPUT] ' . json_encode($data));
            $this->conn->beginTransaction();
            // Tạo mã phiếu nhập mới
            $maPhieuNhap = isset($data['MaPhieuNhap']) && $data['MaPhieuNhap'] ? $data['MaPhieuNhap'] : $this->generateMaPhieuNhap();
            $maNCC = $data['MaNCC'];
            $maNhanVien = isset($data['MaNhanVien']) ? $data['MaNhanVien'] : null;
            $ngayNhap = isset($data['NgayNhap']) ? $data['NgayNhap'] : date('Y-m-d H:i:s');
            $tongTien = 0;
            $trangThai = isset($data['TrangThai']) ? $data['TrangThai'] : 1;

            // Insert phiếu nhập
            $query = "INSERT INTO phieunhap (MaPhieuNhap, MaNCC, MaNhanVien, NgayNhap, TongTien, TrangThai) VALUES (:MaPhieuNhap, :MaNCC, :MaNhanVien, :NgayNhap, :TongTien, :TrangThai)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":MaPhieuNhap", $maPhieuNhap);
            $stmt->bindParam(":MaNCC", $maNCC);
            $stmt->bindParam(":MaNhanVien", $maNhanVien);
            $stmt->bindParam(":NgayNhap", $ngayNhap);
            $stmt->bindParam(":TongTien", $tongTien);
            $stmt->bindParam(":TrangThai", $trangThai);
            $stmt->execute();
            error_log('[PHIEU_NHAP_CREATE] Inserted phieunhap: ' . $maPhieuNhap);

            // Thêm chi tiết phiếu nhập
            $chiTietModel = new ChiTietPhieuNhap($this->conn);
            $seriModel = new SeriSanPham($this->conn);
            if (!isset($data['ChiTiet']) || !is_array($data['ChiTiet'])) {
                throw new Exception("Dữ liệu chi tiết phiếu nhập không hợp lệ");
            }
            foreach ($data['ChiTiet'] as $item) {
                $maSP = $item['MaSP'];
                $soLuong = $item['SoLuong'];
                $donGia = $item['DonGia'];
                $thanhTien = $soLuong * $donGia;
                $tongTien += $thanhTien;
                // Thêm chi tiết phiếu nhập
                $chiTietModel->create([
                    'MaPhieuNhap' => $maPhieuNhap,
                    'MaSP' => $maSP,
                    'SoLuong' => $soLuong,
                    'DonGia' => $donGia
                ]);
                error_log('[PHIEU_NHAP_CREATE] Inserted chitietphieunhap: ' . $maPhieuNhap . ' - ' . $maSP);
                // Sinh số seri cho từng sản phẩm đúng số lượng
                for ($i = 0; $i < $soLuong; $i++) {
                    $seri = $seriModel->generateUniqueSeri($maSP);
                    $seriModel->insertSeri($maSP, $seri, 1);
                    error_log('[PHIEU_NHAP_CREATE] Inserted seri: ' . $maSP . ' - ' . $seri);
                }
            }
            // Cập nhật tổng tiền phiếu nhập
            $updateQuery = "UPDATE phieunhap SET TongTien = :TongTien WHERE MaPhieuNhap = :MaPhieuNhap";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindParam(":TongTien", $tongTien);
            $updateStmt->bindParam(":MaPhieuNhap", $maPhieuNhap);
            $updateStmt->execute();
            error_log('[PHIEU_NHAP_CREATE] Updated TongTien: ' . $maPhieuNhap . ' - ' . $tongTien);
            $this->conn->commit();
            return [
                'id' => $maPhieuNhap,
                'message' => 'Tạo phiếu nhập thành công'
            ];
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("[PHIEU_NHAP_CREATE_ERROR] " . $e->getMessage());
            throw new Exception("Tạo phiếu nhập thất bại: " . $e->getMessage());
        }
    }

    private function generateMaPhieuNhap() {
        $query = "SELECT MAX(MaPhieuNhap) as maxMa FROM phieunhap";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $maxMa = $stmt->fetch(PDO::FETCH_ASSOC)['maxMa'];
        if ($maxMa) {
            preg_match('/PN(\\d+)/', $maxMa, $matches);
            $nextNumber = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
            return 'PN' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
        } else {
            return 'PN00001';
        }
    }

    public function update($maPhieuNhap, $data) {
        // Tương tự như phương thức `update` trong file `HoaDon.php`
        // Thực hiện kiểm tra và cập nhật thông tin phiếu nhập
    }
}