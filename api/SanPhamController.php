<?php
require_once __DIR__ . '/../models/SanPham.php';
class SanPhamController {
    public function getAll() {
        $model = new SanPham();
        $result = $model->getAll();
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'data' => $result
        ]);
    }
} 