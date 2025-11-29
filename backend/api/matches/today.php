<?php
// written by 2040042 Sarang Kim
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../../config/db.php';
include_once '../../models/MatchesModel.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $matchesModel = new MatchesModel($db);

    $stmt = $matchesModel->getTodayMatches();
    $num = $stmt->rowCount();

    $matches_arr = array();
    $matches_arr["message"] = ($num > 0) ? "오늘의 경기 목록 조회 성공" : "오늘의 경기 목록 조회 성공";
    $matches_arr["data"] = array();

    if ($num > 0) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            extract($row);
            $match_item = array(
                "match_id" => (int)$match_id,
                "time" => substr($time, 0, 5),
                "status" => $status,
                "home_team_name" => $home_team_name,
                "home_score" => (int)$home_score,
                "away_team_name" => $away_team_name,
                "away_score" => (int)$away_score,
                "stadium_name" => $stadium_name
            );
            array_push($matches_arr["data"], $match_item);
        }
    }
    http_response_code(200);
    echo json_encode($matches_arr, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => "에러 발생: " . $e->getMessage()));
}
?>