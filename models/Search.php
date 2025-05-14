<?php

require_once __DIR__ . '/../config/Database.php';

class Search
{
    private $conn;
    private $table_name = "sanpham";

    public function __construct()
    {
        try {
            $db = new Database();
            $this->conn = $db->getConnection();

            if ($this->conn === null) {
                throw new Exception("Không thể kết nối đến cơ sở dữ liệu.");
            }
        } catch (Exception $e) {
            error_log("Lỗi khởi tạo Search: " . $e->getMessage());
            throw $e;
        }
    }

    public function searchProducts($filter, $page = 1, $limit = 15)
    {
        try {
            $conditions = [];
            $params = [];

            // Base queries
            $countQuery = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE TrangThai = TRUE";
            $query = "SELECT * FROM " . $this->table_name . " WHERE TrangThai = TRUE";

            // Search by product name if provided
            if (isset($filter['TenSP']) && !empty($filter['TenSP'])) {
                $conditions[] = "TenSP LIKE :TenSP";
                $params[':TenSP'] = '%' . $filter['TenSP'] . '%';
            }

            // Filter by category if provided
            if (isset($filter['MaLoaiSP']) && !empty($filter['MaLoaiSP'])) {
                $conditions[] = "MaLoaiSP = :MaLoaiSP";
                $params[':MaLoaiSP'] = $filter['MaLoaiSP'];
            }

            // Filter by RAM if provided
            if (isset($filter['RAM']) && !empty($filter['RAM'])) {
                $ramValues = explode(',', $filter['RAM']);
                $ramConditions = [];
                foreach ($ramValues as $index => $value) {
                    $paramName = ':RAM' . $index;
                    $ramConditions[] = "RAM LIKE $paramName";
                    $params[$paramName] = '%' . trim($value) . '%';
                }
                $conditions[] = '(' . implode(' OR ', $ramConditions) . ')';
            }

            // Filter by price range if provided
            if (isset($filter['min_price']) && !empty($filter['min_price'])) {
                $conditions[] = "Gia >= :min_price";
                $params[':min_price'] = $filter['min_price'];
            }

            if (isset($filter['max_price']) && !empty($filter['max_price'])) {
                $conditions[] = "Gia <= :max_price";
                $params[':max_price'] = $filter['max_price'];
            }

            // Add conditions to queries if any exist
            if (!empty($conditions)) {
                $conditionStr = " AND " . implode(" AND ", $conditions);
                $countQuery .= $conditionStr;
                $query .= $conditionStr;
            }

            // Add pagination
            $offset = ($page - 1) * $limit;
            $query .= " ORDER BY MaSP DESC LIMIT :limit OFFSET :offset";

            // Get total records
            $countStmt = $this->conn->prepare($countQuery);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Get paginated records
            $stmt = $this->conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $records,
                'pagination' => [
                    'total' => (int)$totalRecords,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'last_page' => ceil($totalRecords / $limit),
                    'from' => $offset + 1,
                    'to' => min($offset + $limit, $totalRecords)
                ],
                'filters_applied' => array_keys($params)
            ];
        } catch (PDOException $e) {
            error_log("Lỗi tìm kiếm sản phẩm: " . $e->getMessage());
            throw new Exception("Có lỗi xảy ra khi tìm kiếm sản phẩm: " . $e->getMessage());
        }
    }
}
