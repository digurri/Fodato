<?php
// written by 2040042 Sarang Kim
// backend/api/comments/list.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../../config/db.php';
include_once '../../models/CommentsModel.php';

try {
    // 1. match_id 확인
    if (!isset($_GET['match_id'])) {
        http_response_code(400);
        echo json_encode(array("message" => "잘못된 요청입니다. (match_id 필요)", "data" => null));
        exit();
    }

    $match_id = $_GET['match_id'];

    // 2. DB 연결
    $database = new Database();
    $db = $database->getConnection();

    // 3. 데이터 조회
    $commentsModel = new CommentsModel($db);
    $stmt = $commentsModel->getCommentsByMatchId($match_id);
    $num = $stmt->rowCount();

    if ($num > 0) {
        $comments_arr = array();
        $comments_arr["message"] = "댓글 목록 조회 성공";
        $comments_arr["data"] = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            extract($row);

            $comment_item = array(
                "comment_id" => (int)$comment_id,
                "content" => $content,
                "created_at" => $created_at,
                "team_name" => $team_name,   // 없으면 null로 나옴 (LEFT JOIN 효과)
                "player_name" => $player_name // 없으면 null로 나옴
            );

            array_push($comments_arr["data"], $comment_item);
        }

        http_response_code(200);
        echo json_encode($comments_arr, JSON_UNESCAPED_UNICODE);

    } else {
        // 댓글이 하나도 없을 때 (빈 배열 반환)
        http_response_code(200);
        echo json_encode(
            array("message" => "등록된 댓글이 없습니다.", "data" => [])
        );
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => "내부 서버 에러", "data" => null));
}
?>