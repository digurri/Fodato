<?php
// backend/api/teams/players.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../../config/db.php';
include_once '../../models/TeamsModel.php';

try {
    // 1. 파라미터 확인
    if (!isset($_GET['team_id'])) {
        http_response_code(400);
        echo json_encode(array("message" => "잘못된 요청입니다. (team_id 필요)", "data" => null));
        exit();
    }

    $team_id = $_GET['team_id'];

    // 2. DB 연결
    $database = new Database();
    $db = $database->getConnection();

    // 3. 데이터 조회
    $teamsModel = new TeamsModel($db);
    $stmt = $teamsModel->getTeamPlayers($team_id);
    $num = $stmt->rowCount();

    if ($num > 0) {
        $players_arr = array();
        $players_arr["message"] = "팀 선수 명단 조회 성공";
        $players_arr["data"] = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            extract($row);

            // --- [핵심] 포지션별 스탯 계산 로직 ---
            $stat_label = "";
            $stat_value = "";

            if ($position === '투수') {
                // 투수: 평균자책점 (ERA) 계산
                $stat_label = "평균자책점";
                if ($total_ip > 0) {
                    $era = ($total_er * 9) / $total_ip;
                    $stat_value = number_format($era, 2); // 소수점 2자리
                } else {
                    $stat_value = "0.00";
                }
            } else {
                // 타자(포수, 내야수, 외야수): 타율 (AVG) 계산
                $stat_label = "타율";
                if ($total_ab > 0) {
                    $avg = $total_h / $total_ab;
                    $stat_value = number_format($avg, 3); // 소수점 3자리
                } else {
                    $stat_value = "0.000";
                }
            }

            // 데이터 조립
            $player_item = array(
                "player_id" => (int)$player_id,
                "uniform_number" => (int)$uniform_number,
                "name" => $name,
                "position" => $position,
                "birth_date" => $birth_date,
                "physical_info" => "{$height_cm}cm / {$weight_kg}kg", // 문자열 합치기
                "stat" => array(
                    "label" => $stat_label,
                    "value" => $stat_value
                )
            );

            array_push($players_arr["data"], $player_item);
        }

        // 4. 결과 반환
        http_response_code(200);
        echo json_encode($players_arr, JSON_UNESCAPED_UNICODE);

    } else {
        // 선수가 없을 경우 (빈 배열 반환)
        http_response_code(404);
        echo json_encode(
            array("message" => "해당 팀에 등록된 선수가 없습니다.", "data" => [])
        );
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(
        array("message" => "내부 서버 에러", "data" => null)
    );
}
?>