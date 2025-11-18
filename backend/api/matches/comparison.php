<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../../config/db.php';
include_once '../../models/MatchesModel.php';

try {
    if (!isset($_GET['match_id'])) {
        http_response_code(404);
        echo json_encode(array("message" => "해당 경기를 찾을 수 없습니다.", "data" => null));
        exit();
    }

    $database = new Database();
    $db = $database->getConnection();
    $matchesModel = new MatchesModel($db);

    // 1. 경기 정보에서 홈/원정 팀 ID 가져오기
    $stmt = $matchesModel->getMatchDetail($_GET['match_id']);
    
    if ($stmt->rowCount() > 0) {
        $match = $stmt->fetch(PDO::FETCH_ASSOC);
        $home_id = $match['home_team_id'];
        $away_id = $match['away_team_id'];
        $home_name = $match['home_team_name'];
        $away_name = $match['away_team_name'];

        // 2. 홈팀 통계 계산
        $home_stats = $matchesModel->getTeamSeasonStats($home_id);
        $home_avg = ($home_stats['total_at_bats'] > 0) ? $home_stats['total_hits'] / $home_stats['total_at_bats'] : 0;
        $home_sb_rate = ($home_stats['total_sb_tries'] > 0) ? ($home_stats['total_sb'] / $home_stats['total_sb_tries']) * 100 : 0;

        // 3. 원정팀 통계 계산
        $away_stats = $matchesModel->getTeamSeasonStats($away_id);
        $away_avg = ($away_stats['total_at_bats'] > 0) ? $away_stats['total_hits'] / $away_stats['total_at_bats'] : 0;
        $away_sb_rate = ($away_stats['total_sb_tries'] > 0) ? ($away_stats['total_sb'] / $away_stats['total_sb_tries']) * 100 : 0;

        // 4. 데이터 조립
        $data = array(
            "home" => array(
                "team_id" => (int)$home_id,
                "name" => $home_name,
                "avg_batting" => number_format($home_avg, 3),
                "total_stolen_bases" => (int)$home_stats['total_sb'],
                "stolen_base_success_rate" => number_format($home_sb_rate, 1) . "%"
            ),
            "away" => array(
                "team_id" => (int)$away_id,
                "name" => $away_name,
                "avg_batting" => number_format($away_avg, 3),
                "total_stolen_bases" => (int)$away_stats['total_sb'],
                "stolen_base_success_rate" => number_format($away_sb_rate, 1) . "%"
            )
        );

        http_response_code(200);
        echo json_encode(array("message" => "전적 비교 조회 성공", "data" => $data), JSON_UNESCAPED_UNICODE);

    } else {
        http_response_code(404);
        echo json_encode(array("message" => "해당 경기를 찾을 수 없습니다.", "data" => null));
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => "내부 서버 에러", "data" => null));
}
?>