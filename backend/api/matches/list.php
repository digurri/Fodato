<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../../config/db.php';
include_once '../../models/MatchesModel.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $matchesModel = new MatchesModel($db);

    // 파라미터 받기
    $date = isset($_GET['date']) ? $_GET['date'] : null;
    $region_id = isset($_GET['region_id']) ? $_GET['region_id'] : null;

    // 날짜 형식 유효성 검사 (간단하게)
    if ($date && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
        http_response_code(400);
        echo json_encode(array("message" => "잘못된 요청입니다. (날짜 형식 오류)"));
        exit();
    }

    $stmt = $matchesModel->getMatchList($date, $region_id);
    $num = $stmt->rowCount();

    $matches_arr = array();
    $matches_arr["message"] = "경기 일정 조회 성공";
    $matches_arr["data"] = array();

    if ($num > 0) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            extract($row);
            $match_item = array(
                "match_id" => (int)$match_id,
                "date" => $date,
                "time" => substr($time, 0, 5),
                "status" => $status,
                "home_team" => $home_team_name, // 명세서 키 확인 (home_team)
                "home_score" => (int)$home_score,
                "away_team" => $away_team_name,
                "away_score" => (int)$away_score,
                "stadium" => $stadium_name,
                "attendance" => (int)$attendance
            );
            array_push($matches_arr["data"], $match_item);
        }
    }

    http_response_code(200);
    echo json_encode($matches_arr, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    // 에러 메시지를 그대로 화면에 출력하게 변경!
    echo json_encode(array("message" => "에러 발생: " . $e->getMessage()));
}
?>