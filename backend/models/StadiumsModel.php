<?php
require_once __DIR__ . '/../config/db.php';

class StadiumsModel {
    private $conn;

    // 생성자 (연결 객체 받아 초기화)
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /** 지역별 경기장 조회 함수
     * @param string $query 경기장명 검색어 (부분일치)
     * @param int|null $region_id 지역 ID 필터 (0 또는 null일 시 무시)
     * @param int|null $id 특정 경기장 ID로 필터링 (null 이면 무시)
     * @return array|false 결과 배열 또는 실패 시 false 반환
     */
    public function searchStadiums($query = '', $region_id = null, $id = null) {
        $sql = "SELECT 
                    s.name, 
                    r.name AS region, 
                    s.location, 
                    s.capacity, 
                    COUNT(m.id) AS total_matches 
                FROM stadiums s 
                JOIN regions r ON s.region_id = r.id 
                LEFT JOIN matches m ON s.id = m.stadium_id
                WHERE 1=1";
    
        // id 필터 조건 추가
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
    
        $sql .= " GROUP BY s.id, s.name, r.name, s.location, s.capacity";

        // 쿼리 준비
        $stmt = $this->conn->prepare($sql);
    
        // 파라미터값 바인딩
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
    
        // 쿼리 실행
        $stmt->execute();
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }          

    /** 
     * 특정 경기장 상세 정보 조회
     * @param int $id 경기장 ID
     * @return array|false 경기장 상세 정보
     */
    public function getStadiumDetail($id) {
        $sql = "SELECT 
                    r.name AS region, 
                    s.name AS name, 
                    s.location, 
                    s.capacity,
                    (SELECT COUNT(*) FROM matches m WHERE m.stadium_id = s.id) AS total_matches,
                    ROUND((SELECT AVG(ms.attendance) 
                           FROM matches m2 
                           JOIN match_stat ms ON m2.id = ms.match_id 
                           WHERE m2.stadium_id = s.id)) AS avg_spectators,
                    (SELECT JSON_OBJECT(
                         'date', m3.date,
                         'time', m3.time,
                         'state', m3.status,
                         'teams', CONCAT(ht.name, ' vs ', at.name),
                         'spectators', ms2.attendance
                     )
                     FROM matches m3
                     JOIN teams ht ON m3.home_team_id = ht.id
                     JOIN teams at ON m3.away_team_id = at.id
                     LEFT JOIN match_stat ms2 ON m3.id = ms2.match_id
                     WHERE m3.stadium_id = s.id AND m3.date < CURDATE()
                     ORDER BY m3.date DESC, m3.time DESC
                     LIMIT 1
                    ) AS recent_match
                FROM stadiums s
                JOIN regions r ON s.region_id = r.id
                WHERE s.id = :id";
    
        // 쿼리 준비 및 id 바인딩
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        // 실행 및 결과 가져오기
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // recent_match json 문자열을 배열로 변환
        if ($result && $result['recent_match']) {
            $result['recent_match'] = json_decode($result['recent_match'], true);
        } else {
            $result['recent_match'] = null;
        }
    
        return $result;
    }
    
}
?>