<?php
// written by 2040042 Sarang Kim
// backend/api/matches/analytics.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../../config/db.php';
include_once '../../models/MatchesModel.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $matchesModel = new MatchesModel($db);

    // ROLLUP 함수 실행 (오늘 날짜 기준)
    $stmt = $matchesModel->getTodayRegionStats();
    $num = $stmt->rowCount();

    if ($num > 0) {
        $stats_arr = array();
        $stats_arr["message"] = "오늘의 지역별 경기 통계 (ROLLUP)";
        $stats_arr["data"] = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            extract($row);
            $item = array(
                "region_name" => $region_name,
                "match_count" => (int)$match_count
            );
            array_push($stats_arr["data"], $item);
        }

        http_response_code(200);
        echo json_encode($stats_arr, JSON_UNESCAPED_UNICODE);

    } else {
        http_response_code(200);
        echo json_encode(array(
            "message" => "오늘 예정된 경기가 없습니다.",
            "data" => []
        ));
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => "내부 서버 에러"));
}
?>