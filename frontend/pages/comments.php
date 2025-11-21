<?php
// frontend/pages/comments.php
// 댓글 기능을 별도 파일로 분리

// 타임존 설정 (한국 시간)
date_default_timezone_set('Asia/Seoul');

// match_id가 없으면 리다이렉트
$matchId = $_GET['match_id'] ?? 0;
if (!$matchId) {
    return; // match_detail.php에서 include할 때는 그냥 리턴
}

// DB 연결이 없으면 연결 (match_detail.php에서 이미 연결되어 있을 수 있음)
if (!isset($db)) {
    require_once '../config/database.php';
    $db = getDB();
}

// API 기본 URL 설정
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$basePath = dirname(dirname(dirname($_SERVER['PHP_SELF'])));

// 백엔드 API를 통해 댓글 목록 가져오기 (list.php 사용)
$comments = [];
$commentsApiUrl = $protocol . '://' . $host . $basePath . '/backend/api/comments/list.php?match_id=' . urlencode($matchId);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $commentsApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$commentsResponse = curl_exec($ch);
$commentsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// 디버깅: API 응답 확인
if ($curlError) {
    error_log("CURL Error: " . $curlError);
}

if ($commentsResponse !== false && $commentsHttpCode == 200) {
    $commentsData = json_decode($commentsResponse, true);
    
    // 디버깅: JSON 파싱 확인
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Parse Error: " . json_last_error_msg());
        error_log("Raw Response: " . substr($commentsResponse, 0, 500));
    }
    
    // 디버깅: 응답 구조 확인
    error_log("API Response: " . print_r($commentsData, true));
    
    if (isset($commentsData['data']) && is_array($commentsData['data'])) {
        error_log("Comments found: " . count($commentsData['data']));
        // API 응답을 기존 코드와 호환되도록 변환
        foreach ($commentsData['data'] as $comment) {
            $comments[] = [
                'id' => $comment['comment_id'],
                'comment_id' => $comment['comment_id'],
                'content' => $comment['content'],
                'created_at' => $comment['created_at'],
                'updated_at' => $comment['created_at'], // API에 updated_at이 없으므로 created_at 사용
                'user_token' => '', // API는 세션 기반이므로 user_token 없음
                'supporting_team_name' => $comment['team_name'] ?? null,
                'supporting_player_name' => $comment['player_name'] ?? null,
                'supporting_player_number' => null, // API에 없음
            ];
        }
    } else {
        error_log("No 'data' key in response or not an array");
    }
} else {
    error_log("API call failed: HTTP $commentsHttpCode");
    error_log("Response: " . substr($commentsResponse, 0, 500));
}

error_log("Final comments count: " . count($comments));

// 경기에 참여하는 두 팀의 선수 목록 가져오기 (응원 선수 선택용)
$matchTeamsQuery = "
    SELECT DISTINCT t.id, t.name
    FROM teams t
    JOIN matches m ON (t.id = m.home_team_id OR t.id = m.away_team_id)
    WHERE m.id = :match_id
    ORDER BY t.name
";
$matchTeamsStmt = $db->prepare($matchTeamsQuery);
$matchTeamsStmt->execute([':match_id' => $matchId]);
$matchTeams = $matchTeamsStmt->fetchAll();

// 두 팀의 선수 목록 가져오기
$matchPlayersQuery = "
    SELECT p.id, p.name, p.position, t.id as team_id, t.name as team_name
    FROM players p
    JOIN teams t ON p.team_id = t.id
    JOIN matches m ON (t.id = m.home_team_id OR t.id = m.away_team_id)
    WHERE m.id = :match_id
    ORDER BY t.name, p.position, p.name
";
$matchPlayersStmt = $db->prepare($matchPlayersQuery);
$matchPlayersStmt->execute([':match_id' => $matchId]);
$matchPlayers = $matchPlayersStmt->fetchAll();
?>

<!-- 댓글 섹션 -->
<div class="comments-section">
    <h4>댓글 (<?php echo count($comments); ?>)</h4>
    
    <!-- 댓글 작성 폼 -->
    <div class="comment-form">
        <form id="commentForm" onsubmit="return submitComment(event)">
            <input type="hidden" name="match_id" id="comment_match_id" value="<?php echo $matchId; ?>">
            
            <div class="form-group">
                <label for="supporting_team">응원 팀 선택</label>
                <select name="supporting_team_id" id="supporting_team" onchange="updatePlayerList()">
                    <option value="">선택 안 함</option>
                    <?php foreach ($matchTeams as $team): ?>
                        <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['name']); ?></option>
                    <?php endforeach; ?>
                    <option value="0">기타</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="supporting_player">응원 선수 선택</label>
                <select name="supporting_player_id" id="supporting_player">
                    <option value="">선택 안 함</option>
                    <?php 
                    $currentTeamId = null;
                    foreach ($matchPlayers as $player): 
                        if ($currentTeamId !== $player['team_id']):
                            if ($currentTeamId !== null):
                                echo '</optgroup>';
                            endif;
                            echo '<optgroup label="' . htmlspecialchars($player['team_name']) . '">';
                            $currentTeamId = $player['team_id'];
                        endif;
                    ?>
                        <option value="<?php echo $player['id']; ?>" data-team-id="<?php echo $player['team_id']; ?>">
                            <?php 
                            echo htmlspecialchars($player['name']);
                            if (isset($player['position']) && $player['position']) {
                                echo ' (' . htmlspecialchars($player['position']) . ')';
                            }
                            ?>
                        </option>
                    <?php 
                    endforeach;
                    if ($currentTeamId !== null):
                        echo '</optgroup>';
                    endif;
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="content">✏️ 의견 입력</label>
                <textarea name="content" id="content" rows="5" required placeholder="경기에 대한 의견을 자유롭게 남겨주세요..."></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">✅ 등록하기</button>
        </form>
    </div>
    
    <!-- 댓글 목록 -->
    <div class="comments-list">
        <?php if (empty($comments)): ?>
            <p class="no-data">데이터 없음</p>
        <?php else: ?>
            <?php foreach ($comments as $comment): ?>
                <?php 
                // 오늘 쓴 댓글인지 확인 (날짜만 비교)
                $commentCreatedAt = $comment['created_at'] ?? '';
                $commentDate = '';
                $today = date('Y-m-d');
                
                // 날짜 부분만 추출 (YYYY-MM-DD 형식)
                if (!empty($commentCreatedAt)) {
                    // MySQL DATETIME 형식: "2025-11-21 12:34:56" -> 처음 10자리만 추출
                    if (strlen($commentCreatedAt) >= 10) {
                        $commentDate = substr($commentCreatedAt, 0, 10);
                    } else {
                        // strtotime 시도 (fallback)
                        $timestamp = strtotime($commentCreatedAt);
                        if ($timestamp !== false) {
                            $commentDate = date('Y-m-d', $timestamp);
                        }
                    }
                }
                
                // 날짜 비교: 오늘 작성한 댓글만 수정/삭제 가능
                // 타임존 차이를 고려하여 댓글 작성일과 오늘 날짜를 비교
                $canEdit = false;
                if (!empty($commentDate)) {
                    // 정확한 날짜 비교
                    $canEdit = ($commentDate === $today);
                    
                    // 타임존 차이 보정: 댓글 날짜가 오늘 또는 어제인 경우 (서버 시간 차이 고려)
                    // 하지만 요구사항은 "오늘만"이므로 정확한 비교만 사용
                    // $canEdit = ($commentDate === $today || $commentDate === date('Y-m-d', strtotime('+1 day')));
                }
                
                // 디버깅: 실제 값 확인 (임시로 활성화)
                echo "<!-- Debug: created_at='$commentCreatedAt', commentDate='$commentDate', today='$today', canEdit=" . ($canEdit ? 'true' : 'false') . " -->";
                ?>
                <div class="comment-item" data-comment-id="<?php echo $comment['id']; ?>" data-comment-date="<?php echo htmlspecialchars($commentDate); ?>" data-today="<?php echo htmlspecialchars($today); ?>">
                    <div class="comment-header">
                        <div class="comment-author-info">
                            <strong class="comment-nickname">익명</strong>
                            <?php if ($comment['supporting_team_name']): ?>
                                <span class="supporting-badge team-badge">응원: <?php echo htmlspecialchars($comment['supporting_team_name']); ?></span>
                            <?php endif; ?>
                            <?php if ($comment['supporting_player_name']): ?>
                                <span class="supporting-badge player-badge">
                                    선수: <?php echo htmlspecialchars($comment['supporting_player_name']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="comment-date">
                            <?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?>
                        </span>
                        <?php 
                        // 디버깅: canEdit 값과 날짜 정보 확인
                        if (!$canEdit) {
                            // 버튼이 표시되지 않는 이유를 HTML 주석으로 표시
                            echo "<!-- Debug: 버튼 미표시 - commentDate: '$commentDate', today: '$today', created_at: '$commentCreatedAt' -->";
                        }
                        if ($canEdit): ?>
                            <div class="comment-actions">
                                <button type="button" class="btn-edit" onclick="editComment(<?php echo $comment['id']; ?>, '<?php echo htmlspecialchars(addslashes($comment['content'])); ?>')">수정</button>
                                <button type="button" class="btn-delete" onclick="deleteComment(<?php echo $comment['id']; ?>)">삭제</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="comment-content" id="comment-content-<?php echo $comment['id']; ?>">
                        <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                    </div>
                    <!-- 수정 폼 (기본적으로 숨김) -->
                    <div class="comment-edit-form" id="edit-form-<?php echo $comment['id']; ?>" style="display: none;">
                        <form onsubmit="return updateComment(event, <?php echo $comment['id']; ?>)">
                            <div class="form-group">
                                <label for="edit-content-<?php echo $comment['id']; ?>">댓글 내용</label>
                                <textarea name="content" id="edit-content-<?php echo $comment['id']; ?>" rows="4" required><?php echo htmlspecialchars($comment['content']); ?></textarea>
                            </div>
                            <div class="edit-form-actions">
                                <button type="submit" class="btn-save">저장</button>
                                <button type="button" class="btn-cancel" onclick="cancelEdit(<?php echo $comment['id']; ?>)">취소</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// API 기본 URL
const apiBaseUrl = '<?php echo $protocol . "://" . $host . $basePath . "/backend/api/comments"; ?>';

// 댓글 작성
function submitComment(event) {
    event.preventDefault();
    
    const form = document.getElementById('commentForm');
    const matchId = document.getElementById('comment_match_id').value;
    const content = document.getElementById('content').value.trim();
    const teamId = document.getElementById('supporting_team').value || null;
    const playerId = document.getElementById('supporting_player').value || null;
    
    if (!content) {
        alert('댓글 내용을 입력해주세요.');
        return false;
    }
    
    const data = {
        match_id: parseInt(matchId),
        content: content,
        team_id: teamId ? parseInt(teamId) : null,
        player_id: playerId ? parseInt(playerId) : null
    };
    
    fetch(apiBaseUrl + '/create.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'include', // 세션 쿠키 전달
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.message) {
            alert(result.message);
            if (result.message.includes('등록되었습니다')) {
                location.reload(); // 페이지 새로고침하여 댓글 목록 갱신
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('댓글 등록 중 오류가 발생했습니다.');
    });
    
    return false;
}

// 댓글 수정
function updateComment(event, commentId) {
    event.preventDefault();
    
    const content = document.getElementById('edit-content-' + commentId).value.trim();
    
    if (!content) {
        alert('댓글 내용을 입력해주세요.');
        return false;
    }
    
    const data = {
        comment_id: parseInt(commentId),
        content: content
    };
    
    fetch(apiBaseUrl + '/update.php', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'include', // 세션 쿠키 전달
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.message) {
            alert(result.message);
            if (result.message.includes('수정되었습니다')) {
                location.reload(); // 페이지 새로고침하여 댓글 목록 갱신
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('댓글 수정 중 오류가 발생했습니다.');
    });
    
    return false;
}

// 댓글 삭제
function deleteComment(commentId) {
    if (!confirm('댓글을 삭제하시겠습니까?')) {
        return;
    }
    
    const data = {
        comment_id: parseInt(commentId)
    };
    
    fetch(apiBaseUrl + '/delete.php', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'include', // 세션 쿠키 전달
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.message) {
            alert(result.message);
            if (result.message.includes('삭제되었습니다')) {
                location.reload(); // 페이지 새로고침하여 댓글 목록 갱신
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('댓글 삭제 중 오류가 발생했습니다.');
    });
}

function editComment(commentId, content) {
    // 댓글 내용 숨기기
    document.getElementById('comment-content-' + commentId).style.display = 'none';
    // 수정 폼 보이기
    document.getElementById('edit-form-' + commentId).style.display = 'block';
    // 수정 버튼 숨기기
    const commentItem = document.querySelector('[data-comment-id="' + commentId + '"]');
    const actions = commentItem.querySelector('.comment-actions');
    if (actions) {
        actions.style.display = 'none';
    }
}

function cancelEdit(commentId) {
    // 수정 폼 숨기기
    document.getElementById('edit-form-' + commentId).style.display = 'none';
    // 댓글 내용 보이기
    document.getElementById('comment-content-' + commentId).style.display = 'block';
    // 수정 버튼 보이기
    const commentItem = document.querySelector('[data-comment-id="' + commentId + '"]');
    const actions = commentItem.querySelector('.comment-actions');
    if (actions) {
        actions.style.display = 'block';
    }
}

// 응원 팀 선택 시 해당 팀의 선수만 표시
function updatePlayerList() {
    const teamSelect = document.getElementById('supporting_team');
    const playerSelect = document.getElementById('supporting_player');
    const selectedTeamId = teamSelect.value;
    
    // 모든 선수 옵션 표시/숨김 처리
    for (let i = 0; i < playerSelect.options.length; i++) {
        const option = playerSelect.options[i];
        const teamId = option.getAttribute('data-team-id');
        
        if (option.value === '' || selectedTeamId === '' || selectedTeamId === '0') {
            // 선택 안 함 또는 기타 선택 시 모든 선수 표시
            option.style.display = '';
        } else if (teamId === selectedTeamId) {
            // 선택한 팀의 선수만 표시
            option.style.display = '';
        } else {
            // 다른 팀의 선수는 숨김
            option.style.display = 'none';
        }
    }
    
    // optgroup 표시/숨김 처리
    const optgroups = playerSelect.querySelectorAll('optgroup');
    optgroups.forEach(optgroup => {
        if (selectedTeamId === '' || selectedTeamId === '0') {
            optgroup.style.display = '';
        } else {
            const firstOption = optgroup.querySelector('option');
            if (firstOption && firstOption.getAttribute('data-team-id') === selectedTeamId) {
                optgroup.style.display = '';
            } else {
                optgroup.style.display = 'none';
            }
        }
    });
    
    // 선택 초기화
    playerSelect.value = '';
}
</script>

