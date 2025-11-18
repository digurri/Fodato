<?php
require_once __DIR__ . '/../config/db.php';

class StatisticsModel {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    /**
     * 전체 통계 데이터 조회 함수
     * 여러 종류의 통계 데이터를 한 번에 반환
     * @return array 각 통계 유형별 결과 배열 포함
     */
    public function getAllAggregatedData() {
        $data = [];
    
        // 1) 경기장별 통계 - 경기장별 순위, 총 경기 수, 최대/평균/총 관중
        $sql_stadiums = "
            SELECT
                RANK() OVER (ORDER BY total_matches DESC) AS stadium_ranking,
                stadium_name,
                stadium_region,
                total_matches,
                max_spectators,
                avg_spectators,
                total_spectators
                FROM (
                    SELECT
                        s.name AS stadium_name,
                        r.name AS stadium_region,
                        COUNT(m.id) AS total_matches,
                        IFNULL(MAX(ms.attendance), 0) AS max_spectators,
                        IFNULL(ROUND(AVG(ms.attendance)), 0) AS avg_spectators,
                        IFNULL(SUM(ms.attendance), 0) AS total_spectators
                    FROM stadiums s
                    JOIN regions r ON s.region_id = r.id
                    LEFT JOIN matches m ON m.stadium_id = s.id
                    LEFT JOIN match_stat ms ON m.id = ms.match_id
                    GROUP BY s.id, s.name, r.name
                    ORDER BY total_matches DESC
                ) AS stats
                ORDER BY total_matches DESC;";
    
        $data['stadiums'] = $this->conn->query($sql_stadiums)->fetchAll(PDO::FETCH_ASSOC);

        // 2) 시즌별 통계 - 시즌별 경기수, 경기장 수, 팀 수, 관중 통계
        $sql_leagues = "
            SELECT
                l.name AS season,
                (SELECT COUNT(*) FROM matches m WHERE m.league_id = l.id) AS league_matches,
                (SELECT COUNT(DISTINCT m.stadium_id) FROM matches m WHERE m.league_id = l.id) AS league_stadiums,
                (SELECT COUNT(DISTINCT t.id) FROM teams t WHERE t.league_id = l.id) AS league_teams,
                IFNULL((SELECT SUM(ms.attendance) FROM matches m JOIN match_stat ms ON m.id=ms.match_id WHERE m.league_id=l.id), 0) AS league_total_spectators,
                IFNULL(ROUND((SELECT AVG(ms.attendance) FROM matches m JOIN match_stat ms ON m.id=ms.match_id WHERE m.league_id=l.id)), 0) AS league_avg_spectators,
                IFNULL((SELECT MAX(ms.attendance) FROM matches m JOIN match_stat ms ON m.id=ms.match_id WHERE m.league_id=l.id), 0) AS league_max_spectators
            FROM leagues l
            ORDER BY l.year DESC";
    
        $data['leagues'] = $this->conn->query($sql_leagues)->fetchAll(PDO::FETCH_ASSOC);
    
        // 3) 지역별 통계 - 지역별 경기장 수, 경기 수, 총 관중, 평균 관중
        $sql_regions = "
            SELECT
                r.name AS region_name,
                (SELECT COUNT(*) FROM stadiums s WHERE s.region_id=r.id) AS region_stadium_count,
                (SELECT COUNT(*) FROM matches m JOIN stadiums s ON m.stadium_id=s.id WHERE s.region_id=r.id) AS region_matches,
                IFNULL((SELECT SUM(ms.attendance) FROM matches m JOIN stadiums s ON m.stadium_id=s.id JOIN match_stat ms ON m.id=ms.match_id WHERE s.region_id=r.id), 0) AS region_total_spectators,
                IFNULL(ROUND((SELECT AVG(ms.attendance) FROM matches m JOIN stadiums s ON m.stadium_id=s.id JOIN match_stat ms ON m.id=ms.match_id WHERE s.region_id=r.id)), 0) AS region_avg_spectators
            FROM regions r";
    
        $data['regions'] = $this->conn->query($sql_regions)->fetchAll(PDO::FETCH_ASSOC);
    
        // 4) 최근 7일간 날짜별 통계 - 경기수, 총/평균 관중 및 랭킹 (윈도우 함수 활용)
        $sql_dates = "
            SELECT
                m.date,
                COUNT(*) AS daily_matches,
                IFNULL(SUM(ms.attendance), 0) AS daily_total_spectators,
                IFNULL(ROUND(AVG(ms.attendance)), 0) AS daily_avg_spectators,
                RANK() OVER (ORDER BY COUNT(*) DESC) AS daily_matches_ranking,
                RANK() OVER (ORDER BY IFNULL(SUM(ms.attendance), 0) DESC) AS daily_spectators_ranking
            FROM matches m
            LEFT JOIN match_stat ms ON m.id = ms.match_id
            WHERE m.date BETWEEN CURDATE() - INTERVAL 6 DAY AND CURDATE()
            GROUP BY m.date
            ORDER BY daily_matches DESC
            LIMIT 7;";
    
        $data['dates'] = $this->conn->query($sql_dates)->fetchAll(PDO::FETCH_ASSOC);
    
        // 5) 최고 관중 수 경기 TOP10 - 경기 일자, 경기장, 팀명, 관중수 및 랭킹 (윈도우 함수 활용)
        $sql_matches = "
            SELECT
                ROW_NUMBER() OVER (ORDER BY m.date ASC) AS match_ranking,
                m.date AS match_date,
                s.name AS match_stadium,
                CONCAT(ht.name, ' vs ', at.name) AS match_teams,
                IFNULL(ms.attendance, 0) AS match_spectators
            FROM matches m
            JOIN stadiums s ON m.stadium_id=s.id
            JOIN teams ht ON m.home_team_id=ht.id
            JOIN teams at ON m.away_team_id=at.id
            LEFT JOIN match_stat ms ON m.id=ms.match_id
            ORDER BY m.date ASC
            LIMIT 10";
    
        $data['matches'] = $this->conn->query($sql_matches)->fetchAll(PDO::FETCH_ASSOC);
    
        // 6) 팀별 타율 통계 - 팀명, 지역, 타자수, 타율 및 랭킹 (윈도우 함수 활용)
        $sql_teams_ba = "
            SELECT
                ROW_NUMBER() OVER (ORDER BY team_ba DESC) AS ba_ranking,
                team_name,
                team_region,
                team_hitter,
                team_ba
                FROM (
                    SELECT
                        t.name AS team_name,
                        r.name AS team_region,
                        COUNT(DISTINCT mp.player_id) AS team_hitter,
                        ROUND(SUM(mp.hits) / NULLIF(SUM(mp.at_bats), 0), 3) AS team_ba
                    FROM teams t
                    JOIN regions r ON t.region_id = r.id
                    JOIN match_players mp ON mp.team_id = t.id
                    GROUP BY t.id, t.name, r.name
                ) AS calculated_ba
                ORDER BY ba_ranking ASC";
    
        $data['teams_ba'] = $this->conn->query($sql_teams_ba)->fetchAll(PDO::FETCH_ASSOC);
    
        // 7) 팀별 도루 성공률 - 시도, 성공 및 성공률, 랭킹
        $sql_teams_steal = "
            SELECT
                ROW_NUMBER() OVER (ORDER BY steal_rate DESC) AS steal_ranking,
                team_name,
                team_region,
                steal_try,
                steal_success,
                CONCAT(ROUND(
                    CASE WHEN steal_try = 0 THEN 0
                    ELSE (steal_success / steal_try) * 100 END, 1), '%') AS steal_rate
            FROM (
                SELECT
                    t.name AS team_name,
                    r.name AS team_region,
                    SUM(mp.stolen_base_tries) AS steal_try,
                    SUM(mp.stolen_bases) AS steal_success
                FROM teams t
                JOIN regions r ON t.region_id = r.id
                JOIN match_players mp ON mp.team_id = t.id
                GROUP BY t.id, t.name, r.name
            ) AS subquery
            ORDER BY steal_ranking ASC;";
    
        $data['teams_steal'] = $this->conn->query($sql_teams_steal)->fetchAll(PDO::FETCH_ASSOC);
    
        // 8) 포지션별 퍼포먼스 요약 - 평가지표, 선수 수, 평균/최고/최저 성적
        $sql_positions = "
            SELECT 
            mp.position AS position,
            CASE
                WHEN mp.position = '투수' THEN '평균자책점'
                WHEN mp.position = '지명타자' THEN '타율'
                ELSE '수비율'
            END AS indicator,
            COUNT(DISTINCT mp.player_id) AS players,
            ROUND(AVG(
                CASE 
                    WHEN mp.position = '투수' AND mp.innings_pitched > 0 THEN mp.earned_runs / mp.innings_pitched
                    WHEN mp.position IN ('포수', '1루수', '2루수', '3루수', '내야수', '외야수', '유격수', '좌익수', '중견수', '우익수') THEN
                        COALESCE((mp.putouts + mp.assists) / NULLIF((mp.putouts + mp.assists + mp.errors), 0), 0)
                    WHEN mp.position = '지명타자' AND mp.at_bats > 0 THEN mp.hits / mp.at_bats
                    ELSE NULL
                END
            ), 3) AS avg_perform,
            CASE
                WHEN mp.position = '투수' THEN MIN(mp.earned_runs / NULLIF(mp.innings_pitched, 0))
                ELSE MAX(COALESCE(
                    CASE 
                        WHEN mp.position IN ('포수', '1루수', '2루수', '3루수', '내야수', '외야수', '유격수', '좌익수', '중견수', '우익수') THEN
                            (mp.putouts + mp.assists) / NULLIF((mp.putouts + mp.assists + mp.errors), 0)
                        WHEN mp.position = '지명타자' THEN mp.hits / NULLIF(mp.at_bats, 0)
                        ELSE NULL
                    END, 0))
            END AS best_perform,
            CASE
                WHEN mp.position = '투수' THEN MAX(mp.earned_runs / NULLIF(mp.innings_pitched, 0))
                ELSE MIN(COALESCE(
                    CASE 
                        WHEN mp.position IN ('포수', '1루수', '2루수', '3루수', '내야수', '외야수', '유격수', '좌익수', '중견수', '우익수') THEN
                            (mp.putouts + mp.assists) / NULLIF((mp.putouts + mp.assists + mp.errors), 0)
                        WHEN mp.position = '지명타자' THEN mp.hits / NULLIF(mp.at_bats, 0)
                        ELSE NULL
                    END, 0))
                END AS worst_perform
            FROM match_players mp
            GROUP BY mp.position
            ORDER BY mp.position;";
    
        $data['positions'] = $this->conn->query($sql_positions)->fetchAll(PDO::FETCH_ASSOC);
    
        return $data;
    }    
}
?>