<?php
// backend/api/teams/list.php

// 1. 헤더 설정 (JSON 응답, 한글 깨짐 방지)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// 2. 설정 파일 및 모델 불러오기
include_once '../../config/db.php';
include_once '../../models/TeamsModel.php';

try {
    // 3. DB 연결 (팀원분의 Class 방식 사용)
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

        // PDO 방식: fetch(PDO::FETCH_ASSOC)
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // extract를 하면 $row['name']을 $name 변수로 바로 쓸 수 있음
            extract($row);

            // 명세서에 맞게 아이템 구성
            $team_item = array(
                "team_id" => (int)$team_id,       // 숫자로 변환
                "name" => $name,
                "region" => $region_name,         // DB의 region_name을 JSON의 region 키에 매핑
                "player_count" => (int)$player_count, // 계산된 선수 수
                "match_count" => (int)$match_count    // 계산된 경기 수
            );

            array_push($teams_arr["data"], $team_item);
        }

        // 200 OK 응답
        http_response_code(200);
        echo json_encode($teams_arr, JSON_UNESCAPED_UNICODE);

    } else {
        // 데이터가 하나도 없을 때 (예외 상황)
        http_response_code(404); // 200으로 보내고 빈 배열을 줄 수도 있지만, 명세서가 없으니 일단 404 처리
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
            "error" => $e->getMessage() // 디버깅용 (실제 배포시엔 뺌)
        )
    );
}
?>