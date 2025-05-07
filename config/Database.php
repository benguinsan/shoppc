<?php
class Database
{
  private $host = 'localhost';
  private $db = 'shop_pc';
  private $port = 3306; // Thêm port nếu cần
  private $user = 'root';
  private $password = '';
  public $conn;

  public function __construct()
  {
    $this->conn = null;
    try {
      // Thêm driver mysql vào chuỗi kết nối
      $this->conn = new PDO("mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db, $this->user, $this->password);

      // Thiết lập để PDO ném ra ngoại lệ khi có lỗi
      $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

      // Thiết lập charset để hỗ trợ tiếng Việt
      $this->conn->exec("set names utf8");
    } catch (PDOException $error) {
      // Ghi log lỗi thay vì hiển thị trực tiếp
      error_log("Database Connection Error: " . $error->getMessage());
      throw new Exception("Không thể kết nối đến cơ sở dữ liệu: " . $error->getMessage());
    }
  }

  public function getConnection()
  {
    return $this->conn;
  }
}
