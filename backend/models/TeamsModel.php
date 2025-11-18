<?php
// backend/models/TeamsModel.php

class TeamsModel {
    private $conn;
    private $table_name = "teams"; // 테이블 이름

    // 생성자: DB 연결 객체(PDO)를 받음
    public function __construct($db) {
        $this->conn = $db;
    }

    // 모든 팀 목록 가져오기 (+ 지역명, 선수수, 경기수 포함)
    public function getAllTeamsWithStats() {
        // 쿼리 설명:
        // 1. teams(t) 테이블을 기준
        // 2. regions(r) 테이블을 JOIN해서 'region_name' 가져옴
        // 3. (SELECT COUNT...) 로 해당 팀의 player 수 계산
        // 4. (SELECT COUNT...) 로 해당 팀이 홈팀이거나 원정팀인 경기 수 계산
        
        $query = "SELECT 
                    t.id AS team_id, 
                    t.name, 
                    r.name AS region_name,
                    (SELECT COUNT(*) FROM players p WHERE p.team_id = t.id) AS player_count,
                    (SELECT COUNT(*) FROM matches m WHERE m.home_team_id = t.id OR m.away_team_id = t.id) AS match_count
                  FROM " . $this->table_name . " t
                  LEFT JOIN regions r ON t.region_id = r.id
                  ORDER BY t.id ASC";

        // PDO 방식의 쿼리 준비 및 실행
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    public function getTeamDetail($team_id) {
        // total: 홈 또는 원정인 모든 경기 수
        // completed: 홈 또는 원정이며 status가 finished인 경기 수
        // today: 홈 또는 원정이며 date가 오늘인 경기 수
        $query = "SELECT 
                    t.id AS team_id, 
                    t.name, 
                    r.name AS region_name,
                    (SELECT COUNT(*) FROM matches m 
                     WHERE m.home_team_id = t.id OR m.away_team_id = t.id) AS total_matches,
                    (SELECT COUNT(*) FROM matches m 
                     WHERE (m.home_team_id = t.id OR m.away_team_id = t.id) 
                     AND m.status = 'finished') AS completed_matches,
                    (SELECT COUNT(*) FROM matches m 
                     WHERE (m.home_team_id = t.id OR m.away_team_id = t.id) 
                     AND m.date = CURDATE()) AS today_matches
                  FROM " . $this->table_name . " t
                  LEFT JOIN regions r ON t.region_id = r.id
                  WHERE t.id = :team_id
                  LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":team_id", $team_id);
        $stmt->execute();

        return $stmt;
    }

    public function getTeamPlayers($team_id) {
        // 선수 기본 정보 + 경기 기록 합계(이닝, 자책점, 타수, 안타)
        $query = "SELECT 
                    p.id AS player_id,
                    p.uniform_number,
                    p.name,
                    p.position,
                    p.birth_date,
                    p.height_cm,
                    p.weight_kg,
                    IFNULL(SUM(mp.innings_pitched), 0) AS total_ip,
                    IFNULL(SUM(mp.earned_runs), 0) AS total_er,
                    IFNULL(SUM(mp.at_bats), 0) AS total_ab,
                    IFNULL(SUM(mp.hits), 0) AS total_h
                  FROM players p
                  LEFT JOIN match_players mp ON p.id = mp.player_id
                  WHERE p.team_id = :team_id
                  GROUP BY p.id
                  ORDER BY p.uniform_number ASC"; // 등번호 순 정렬

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":team_id", $team_id);
        $stmt->execute();

        return $stmt;
    }
}
?>