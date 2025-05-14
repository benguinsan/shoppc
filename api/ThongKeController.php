<?php
require_once __DIR__ . '/../models/HoaDon.php';

class ThongKeController {
    private $hoaDonModel;

    public function __construct() {
        $this->hoaDonModel = new HoaDon();
    }

    // Thống kê theo ngày
    public function thongKeTheoNgay($date) {
        $stmt = $this->hoaDonModel->thongKeTheoNgay($date);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data);
    }

    // Thống kê theo tháng
    public function thongKeTheoThang($month) {
        $stmt = $this->hoaDonModel->thongKeTheoThang($month);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data);
    }

    // Thống kê theo năm
    public function thongKeTheoNam($year) {
        $stmt = $this->hoaDonModel->thongKeTheoNam($year);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data);
    }

    // Thống kê theo sản phẩm
    public function thongKeTheoSanPham($type, $value) {
        $stmt = $this->hoaDonModel->thongKeTheoSanPham($type, $value);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data);
    }

    public function thongKeTheoLoaiSanPham($type, $value) {
        $stmt = $this->hoaDonModel->thongKeTheoLoaiSanPham($type, $value);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data);
    }   
} 