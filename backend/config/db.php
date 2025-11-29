<?php
// written by 2303050 Eunseo Park
class Database {
    private $host = "localhost";
    private $db_name = "team05";
    private $username = "team05";
    private $password = "team05";
    public $conn;

    public function getConnection(){
        $this->conn = null;
        try{
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8mb4");
        }catch(PDOException $exception){
            echo "Connection error: " . $exception->getMessage();
            exit;
        }
        return $this->conn;
    }
}
?>