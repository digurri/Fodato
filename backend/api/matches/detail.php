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

    $stmt = $matchesModel->getMatchDetail($_GET['match_id']);
    
    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        extract($row);

        $data = array(
            "match_id" => (int)$match_id,
            "date" => $date,
            "time" => substr($time, 0, 5),
            "status" => $status,
            "home" => array(
                "team_id" => (int)$home_team_id, // id 추가됨
                "name" => $home_team_name,
                "score" => (int)$home_score,
                "logo" => $home_team_logo
            ),
            "away" => array(
                "team_id" => (int)$away_team_id,
                "name" => $away_team_name,
                "score" => (int)$away_score,
                "logo" => $away_team_logo
            ),
            "stadium" => array(
                "name" => $stadium_name,
                "weather" => $weather
            ),
            "result" => array(
                "mvp" => $mvp_player_name,
                "winning_hit" => $winning_hitter_name . " (" . $winning_hit_description . ")"
            )
        );

        http_response_code(200);
        echo json_encode(array("message" => "경기 상세 정보 조회 성공", "data" => $data), JSON_UNESCAPED_UNICODE);

    } else {
        http_response_code(404);
        echo json_encode(array("message" => "해당 경기를 찾을 수 없습니다.", "data" => null));
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => "내부 서버 에러", "data" => null));
}
?>