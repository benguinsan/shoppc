<?php
class VNPayController {
    private $vnp_TmnCode = "T6XL9LZ8";
    private $vnp_HashSecret = "48F6SYYVPTNZ7K6AGBSSBO69EKOVJCJ9";
    private $vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
    private $vnp_ReturnUrl = "https://414e-2001-ee0-4fb0-7320-f0cd-d9d-a3a5-f0d1.ngrok-free.app/payment/vnpay_return";

    public function createPayment() {
        // Nhận dữ liệu từ frontend
        $body = json_decode(file_get_contents('php://input'), true);

        // Validate các trường bắt buộc
        if (
            empty($body['amount']) ||
            empty($body['orderId']) ||
            empty($body['orderInfo']) ||
            empty($body['returnUrl'])
        ) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            return;
        }

        $inputData = array(
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $this->vnp_TmnCode,
            "vnp_Amount" => $body['amount'] * 100, // Nhân 100 theo chuẩn VNPAY
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $_SERVER['REMOTE_ADDR'] == '::1' ? '127.0.0.1' : $_SERVER['REMOTE_ADDR'],
            "vnp_Locale" => "vn",
            "vnp_OrderInfo" => $body['orderInfo'],
            "vnp_OrderType" => "billpayment",
            "vnp_ReturnUrl" => $body['returnUrl'],
            "vnp_TxnRef" => $body['orderId'],
            "vnp_SecureHashType" => "SHA512"
        );

        // Sắp xếp và tạo hash
        ksort($inputData);
        $hashDataArr = [];
        foreach ($inputData as $key => $value) {
            if ($key != 'vnp_SecureHash' && $key != 'vnp_SecureHashType') {
                $hashDataArr[] = urlencode($key) . "=" . urlencode($value);
            }
        }
        $hashdata = implode('&', $hashDataArr);
        $vnpSecureHash = hash_hmac('sha512', $hashdata, $this->vnp_HashSecret);

        $query = http_build_query($inputData);
        $vnp_Url = $this->vnp_Url . "?" . $query . "&vnp_SecureHash=" . $vnpSecureHash;

        // // Ghi log debug
        // file_put_contents('vnpay_debug.txt', print_r([
        //     'inputData' => $inputData,
        //     'hashdata' => $hashdata,
        //     'vnpSecureHash' => $vnpSecureHash,
        //     'url' => $vnp_Url
        // ], true));

        echo json_encode(['paymentUrl' => $vnp_Url]);
    }

    public function paymentReturn() {
        $vnp_ResponseCode = $_GET['vnp_ResponseCode'] ?? '';
        $vnp_TxnRef = $_GET['vnp_TxnRef'] ?? '';
        $vnp_Amount = ($_GET['vnp_Amount'] ?? 0) / 100;

        if ($vnp_ResponseCode === '00') {
            echo json_encode([
                'status' => 'success',
                'message' => 'Thanh toán thành công',
                'orderId' => $vnp_TxnRef,
                'amount' => $vnp_Amount
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Thanh toán thất bại',
                'orderId' => $vnp_TxnRef
            ]);
        }
    }
}
