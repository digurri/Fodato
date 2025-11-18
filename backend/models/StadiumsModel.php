<?php
require_once __DIR__ . '/../config/db.php';

class StadiumsModel {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function searchStadiums($query = '', $region_id = null, $id = null) {
        $sql = "SELECT s.*, r.name AS region_name FROM stadiums s 
                JOIN regions r ON s.region_id = r.id WHERE 1=1";
        
        if ($id !== null) {
            $sql .= " AND s.id = :id";
        } else {
            if ($query !== '') {
                $sql .= " AND s.name LIKE :query";
            }
            if ($region_id !== null && $region_id != 0) {
                $sql .= " AND s.region_id = :region_id";
            }
        }
    
        $stmt = $this->conn->prepare($sql);
    
        if ($id !== null) {
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        } else {
            if ($query !== '') {
                $search = '%' . $query . '%';
                $stmt->bindParam(':query', $search);
            }
            if ($region_id !== null && $region_id != 0) {
                $stmt->bindParam(':region_id', $region_id, PDO::PARAM_INT);
            }
        }
    
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }      

    public function getStadiumDetail($id) {
        $sql = "SELECT s.*, r.name AS region_name FROM stadiums s 
                JOIN regions r ON s.region_id = r.id
                WHERE s.id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>