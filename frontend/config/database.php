<?php
require_once 'config.php';

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, 
                PDO::ATTR_EMULATE_PREPARES => false, // 진짜 Prepared Statement 사용 (보안)
            ]);
        } catch(PDOException $e) {
            // msg 출력 후 종료
            die("데이터베이스 연결 실패: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        // 연결된 인스턴스가 없으면 새로 생성
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    public function __clone() {
        throw new Exception("복제 금지");
    }
}

// getDB를 이용해 데이터베이스 연결 가져오기
function getDB() {
    return Database::getInstance()->getConnection();
}
?>
