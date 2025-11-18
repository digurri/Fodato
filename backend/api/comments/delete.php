<?php
// backend/api/comments/delete.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE");

include_once '../../config/db.php';
include_once '../../models/CommentsModel.php';

session_start(); // 세션 시작

try {
    $database = new Database();
    $db = $database->getConnection();
    $commentsModel = new CommentsModel($db);

    $data = json_decode(file_get_contents("php://input"));

    if (!empty($data->comment_id)) {
        
        // 1. 세션 검증 준비
        $current_session_id = session_id();
        $db_session_id = $commentsModel->getSessionIdByCommentId($data->comment_id);

        // 2. [검증] 비교
        if ($db_session_id === $current_session_id) {
            // 일치하면 삭제 진행
            if ($commentsModel->deleteComment($data->comment_id)) {
                http_response_code(200);
                echo json_encode(array("message" => "댓글이 삭제되었습니다."));
            } else {
                throw new Exception("삭제 실패");
            }
        } else {
            // 불일치: 권한 없음
            http_response_code(401);
            echo json_encode(array("message" => "삭제 권한이 없습니다. (세션 불일치)"));
        }

    } else {
        http_response_code(400);
        echo json_encode(array("message" => "comment_id가 없습니다."));
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => "내부 서버 에러"));
}
?>