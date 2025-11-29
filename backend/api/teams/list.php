<?php
// written by 2040042 Sarang Kim
// backend/api/teams/list.php

// 1. 헤더 설정 (JSON 응답, 한글 깨짐 방지)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// 2. 설정 파일 및 모델 불러오기
include_once '../../config/db.php';
include_once '../../models/TeamsModel.php';

try {
    // 3. DB 연결
    $database = new Database();
    $db = $database->getConnection();

    // 4. 모델 객체 생성
    $teamsModel = new TeamsModel($db);

    // 5. 데이터 가져오기 실행
    $stmt = $teamsModel->getAllTeamsWithStats();
    $num = $stmt->rowCount();

    // 6. 결과 처리
    if ($num > 0) {
        $teams_arr = array();
        $teams_arr["message"] = "전체 팀 목록 조회 성공";
        $teams_arr["data"] = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            extract($row);

            $team_item = array(
                "team_id" => (int)$team_id,       // 숫자로 변환
                "name" => $name,
                "region" => $region_name,         // DB의 region_name을 JSON의 region 키에 매핑
                "player_count" => (int)$player_count, // 계산된 선수 수
                "match_count" => (int)$match_count    // 계산된 경기 수
            );

            array_push($teams_arr["data"], $team_item);
        }

        http_response_code(200);
        echo json_encode($teams_arr, JSON_UNESCAPED_UNICODE);

    } else {
        // 데이터가 하나도 없을 때
        http_response_code(404);
        echo json_encode(
            array("message" => "팀 데이터가 없습니다.", "data" => [])
        );
    }

} catch (Exception $e) {
    // 500 에러 처리
    http_response_code(500);
    echo json_encode(
        array(
            "message" => "내부 서버 에러",
            "data" => null,
            "error" => $e->getMessage() 
        )
    );
}
?>