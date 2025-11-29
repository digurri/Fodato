<?php
// written by 2040042 Sarang Kim
// backend/models/CommentsModel.php

class CommentsModel {
    private $conn;
    private $table_name = "comments";

    public function __construct($db) {
        $this->conn = $db;
    }

    // 1. [조회] 특정 경기의 댓글 목록 가져오기 (작성자 이름 포함)
    public function getCommentsByMatchId($match_id) {
        // comments 테이블을 기준으로 teams, players 테이블을 LEFT JOIN
        // 이유: team_id나 player_id가 NULL일 수도 있기 때문 (선택 안 한 경우)
        $query = "SELECT 
                    c.id AS comment_id,
                    c.content,
                    c.created_at,
                    t.name AS team_name,
                    p.name AS player_name
                  FROM " . $this->table_name . " c
                  LEFT JOIN teams t ON c.team_id = t.id
                  LEFT JOIN players p ON c.player_id = p.id
                  WHERE c.match_id = :match_id
                  ORDER BY c.created_at DESC"; // 최신순 정렬

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":match_id", $match_id);
        $stmt->execute();

        return $stmt;
    }

    // written by 2303050 Eunseo Park (2. 댓글 쓰기)
    // 2. [작성] 댓글 쓰기
    public function createComment($match_id, $content, $session_id, $team_id = null, $player_id = null) {
        try {
            // 트랜잭션 시작
            $this->conn->beginTransaction();
            
            $query = "INSERT INTO " . $this->table_name . " 
                      (match_id, content, session_id, team_id, player_id) 
                      VALUES (:match_id, :content, :session_id, :team_id, :player_id)";
    
            $stmt = $this->conn->prepare($query);
    
            // 데이터 바인딩
            $stmt->bindParam(":match_id", $match_id);
            $stmt->bindParam(":content", $content);
            $stmt->bindParam(":session_id", $session_id);
            $stmt->bindParam(":team_id", $team_id);
            $stmt->bindParam(":player_id", $player_id);
    
            $result = $stmt->execute();
            
            if ($result) {
                // 성공 시 커밋
                $this->conn->commit();
                return true;
            } else {
                // 실패 시 롤백
                $this->conn->rollBack();
                return false;
            }
            
        } catch (Exception $e) {
            // 예외 발생 시 롤백
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Comment creation failed: " . $e->getMessage());
            return false;
        }
    }

    // written by 2040042 Sarang Kim
    // 3. [검증] 특정 댓글의 세션 ID 가져오기 (수정/삭제 권한 확인용)
    public function getSessionIdByCommentId($comment_id) {
        $query = "SELECT session_id FROM " . $this->table_name . " WHERE id = :comment_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":comment_id", $comment_id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['session_id'];
        }
        return null; // 댓글이 없으면 null 반환
    }

    // written by 2040042 Sarang Kim
    // 4. [수정] 댓글 내용 업데이트
    public function updateComment($comment_id, $content) {
        $query = "UPDATE " . $this->table_name . " 
                  SET content = :content 
                  WHERE id = :comment_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":content", $content);
        $stmt->bindParam(":comment_id", $comment_id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // written by 2040042 Sarang Kim
    // 5. [삭제] 댓글 지우기
    public function deleteComment($comment_id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :comment_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":comment_id", $comment_id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
}
?>