<?php
// written by 2040042 Sarang Kim
// backend/models/MatchesModel.php

class MatchesModel {
    private $conn;
    private $table_name = "matches";

    public function __construct($db) {
        $this->conn = $db;
    }

    // 1. [홈] 오늘의 경기 조회 (수정됨: 점수는 match_stat에서!)
    public function getTodayMatches() {
        $query = "SELECT 
                    m.id AS match_id,
                    m.time,
                    m.status,
                    IFNULL(ms.home_score, 0) AS home_score, -- m -> ms 로 수정
                    IFNULL(ms.away_score, 0) AS away_score, -- m -> ms 로 수정
                    ht.name AS home_team_name,
                    at.name AS away_team_name,
                    s.name AS stadium_name
                  FROM " . $this->table_name . " m
                  LEFT JOIN teams ht ON m.home_team_id = ht.id
                  LEFT JOIN teams at ON m.away_team_id = at.id
                  LEFT JOIN stadiums s ON m.stadium_id = s.id
                  LEFT JOIN match_stat ms ON m.id = ms.match_id -- JOIN 추가 필수!
                  WHERE m.date = CURDATE()";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // 2. [일정] 경기 목록 조회 (수정됨: 점수는 match_stat에서!)
    public function getMatchList($date, $region_id) {
        $query = "SELECT 
                    m.id AS match_id,
                    m.date,
                    m.time,
                    m.status,
                    IFNULL(ms.home_score, 0) AS home_score, -- m -> ms 로 수정
                    IFNULL(ms.away_score, 0) AS away_score, -- m -> ms 로 수정
                    ht.name AS home_team_name,
                    at.name AS away_team_name,
                    s.name AS stadium_name,
                    ms.attendance
                  FROM " . $this->table_name . " m
                  LEFT JOIN teams ht ON m.home_team_id = ht.id
                  LEFT JOIN teams at ON m.away_team_id = at.id
                  LEFT JOIN stadiums s ON m.stadium_id = s.id
                  LEFT JOIN match_stat ms ON m.id = ms.match_id
                  WHERE 1=1";

        // 동적 쿼리 빌딩
        if (!empty($date)) {
            $query .= " AND m.date = :date";
        }
        if (!empty($region_id)) {
            $query .= " AND s.region_id = :region_id";
        }
        
        $query .= " ORDER BY m.date ASC, m.time ASC";

        $stmt = $this->conn->prepare($query);

        if (!empty($date)) {
            $stmt->bindParam(":date", $date);
        }
        if (!empty($region_id)) {
            $stmt->bindParam(":region_id", $region_id);
        }

        $stmt->execute();
        return $stmt;
    }

    // 3. [상세] 경기 상세 정보 조회 (여긴 이미 잘 되어있음)
    public function getMatchDetail($match_id) {
        $query = "SELECT 
                    m.id AS match_id, m.date, m.time, m.status,
                    m.home_team_id, ht.name AS home_team_name, ht.logo AS home_team_logo,
                    m.away_team_id, at.name AS away_team_name, at.logo AS away_team_logo,
                    s.name AS stadium_name,
                    ms.home_score, ms.away_score, ms.weather, ms.mvp_player_name, 
                    ms.winning_hitter_name, ms.winning_hit_description
                  FROM " . $this->table_name . " m
                  LEFT JOIN teams ht ON m.home_team_id = ht.id
                  LEFT JOIN teams at ON m.away_team_id = at.id
                  LEFT JOIN stadiums s ON m.stadium_id = s.id
                  LEFT JOIN match_stat ms ON m.id = ms.match_id
                  WHERE m.id = :match_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":match_id", $match_id);
        $stmt->execute();
        return $stmt;
    }

    // 4. [비교] 팀별 시즌 통계 계산
    public function getTeamSeasonStats($team_id) {
        $query = "SELECT 
                    SUM(hits) as total_hits, 
                    SUM(at_bats) as total_at_bats,
                    SUM(stolen_bases) as total_sb,
                    SUM(stolen_base_tries) as total_sb_tries
                  FROM match_players
                  WHERE team_id = :team_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":team_id", $team_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // [홈] 오늘 경기 지역별 통계 (ROLLUP 적용)
    public function getTodayRegionStats() {
        $query = "SELECT 
                    IFNULL(r.name, 'Total') AS region_name, 
                    COUNT(m.id) AS match_count
                  FROM matches m
                  JOIN stadiums s ON m.stadium_id = s.id
                  JOIN regions r ON s.region_id = r.id
                  WHERE m.date = CURDATE()  -- 오직 오늘 날짜만!
                  GROUP BY r.name WITH ROLLUP";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }
}
?>