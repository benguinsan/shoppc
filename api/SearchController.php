<?php
require_once __DIR__ . '/../models/Search.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class SearchController
{
    private $search;

    public function __construct()
    {
        $this->search = new Search();
    }

    public function handleSearch()
    {
        try {
            // Get pagination parameters from query string
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

            // Build filter array from query parameters
            $filter = [
                'TenSP' => $_GET['TenSP'] ?? '',
                'MaLoaiSP' => $_GET['MaLoaiSP'] ?? '',
                'RAM' => $_GET['RAM'] ?? '',
                'min_price' => $_GET['min_price'] ?? '',
                'max_price' => $_GET['max_price'] ?? ''
            ];

            // Remove empty filters
            $filter = array_filter($filter, function ($value) {
                return $value !== '';
            });

            $result = $this->search->searchProducts($filter, $page, $limit);

            echo json_encode([
                'dataSource' => $result['data'],
                'pageNo' => $page - 1,
                'pageSize' => $limit,
                'totalElements' => $result['pagination']['total'],
                'filters_applied' => $result['filters_applied']
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
