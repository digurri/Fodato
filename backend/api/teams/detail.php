<?php
// written by 2040042 Sarang Kim
// backend/api/teams/detail.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../../config/db.php';
include_once '../../models/TeamsModel.php';

try {
    // 1. 파라미터 확인 (team_id 필수)
    if (!isset($_GET['team_id'])) {
        http_response_code(400);
        echo json_encode(array("message" => "잘못된 요청입니다.", "data" => null));
        exit();
    }

    $team_id = $_GET['team_id'];

    // 2. DB 연결
    $database = new Database();
    $db = $database->getConnection();

    // 3. 데이터 조회
    $teamsModel = new TeamsModel($db);
    $stmt = $teamsModel->getTeamDetail($team_id);
    $num = $stmt->rowCount();

    if ($num > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        extract($row);

        // 4. 명세서 포맷에 맞게 데이터 구성
        $team_detail = array(
            "team_id" => (int)$team_id,
            "name" => $name,
            "region" => $region_name,
            "total_matches" => (int)$total_matches,
            "completed_matches" => (int)$completed_matches,
            "today_matches" => (int)$today_matches
        );

        // 5. 200 OK 응답
        http_response_code(200);
        echo json_encode(
            array(
                "message" => "팀 기본 정보 조회 성공",
                "data" => $team_detail
            ), 
            JSON_UNESCAPED_UNICODE
        );

    } else {
        // 6. 404 Not Found (해당 ID의 팀이 없을 때)
        http_response_code(404);
        echo json_encode(
            array("message" => "해당 팀을 찾을 수 없습니다.", "data" => null)
        ); 
    }

} catch (Exception $e) {
    // 7. 500 Internal Server Error
    http_response_code(500);
    echo json_encode(
        array("message" => "내부 서버 에러", "data" => null)
    );
}
?>