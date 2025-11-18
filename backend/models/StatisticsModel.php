<?php
require_once __DIR__ . '/../config/db.php';

class StatisticsModel {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getAllStatistics() {
        $sql = "SELECT ms.*, m.date, m.time, ht.name AS home_team, at.name AS away_team, s.name AS stadium_name
                FROM match_stat ms
                JOIN matches m ON ms.match_id = m.id
                JOIN teams ht ON m.home_team_id = ht.id
                JOIN teams at ON m.away_team_id = at.id
                JOIN stadiums s ON m.stadium_id = s.id
                ORDER BY m.date DESC, m.time DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStatisticsByMatchId($match_id) {
        $sql = "SELECT ms.*, m.date, m.time, ht.name AS home_team, at.name AS away_team, s.name AS stadium_name
                FROM match_stat ms
                JOIN matches m ON ms.match_id = m.id
                JOIN teams ht ON m.home_team_id = ht.id
                JOIN teams at ON m.away_team_id = at.id
                JOIN stadiums s ON m.stadium_id = s.id
                WHERE ms.match_id = :match_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':match_id', $match_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>