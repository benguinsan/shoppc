<?php

if ($uri == '/api/baohanh/search' && $_SERVER['REQUEST_METHOD'] == 'GET') {
    $controller = new BaoHanhController();
    $controller->searchWarranties();
    exit;
} 