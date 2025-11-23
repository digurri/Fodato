<!-- 댓글 작성 페이지 -->


<?php
date_default_timezone_set('Asia/Seoul');

$matchId = $_GET['match_id'] ?? 0;
if (!$matchId) {
    return;
}

if (!isset($db)) {
    require_once '../config/database.php';
    $db = getDB();
}

require_once '../helpers/api_helper.php';

$comments = [];
$apiBaseUrl = getApiBaseUrl(3);
$result = callApi($apiBaseUrl . '/comments/list.php?match_id=' . urlencode($matchId));

if ($result['success']) {
    $commentsData = json_decode($result['response'], true);
    
    if (isset($commentsData['data']) && is_array($commentsData['data'])) {
        foreach ($commentsData['data'] as $comment) {
            $comments[] = [
                'id' => $comment['comment_id'],
                'comment_id' => $comment['comment_id'],
                'content' => $comment['content'],
                'created_at' => $comment['created_at'],
                'updated_at' => $comment['created_at'],
                'user_token' => '',
                'supporting_team_name' => $comment['team_name'] ?? null,
                'supporting_player_name' => $comment['player_name'] ?? null,
                'supporting_player_number' => null,
            ];
        }
    }
}
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

<div class="comments-section">
    <h4>댓글 (<?php echo count($comments); ?>)</h4>
    
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
                    // 선수 목록을 팀별로 묶어서 출력
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
                <label for="content">의견 입력</label>
                <textarea name="content" id="content" rows="5" required placeholder="경기에 대한 의견을 자유롭게 남겨주세요..."></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">등록하기</button>
        </form>
    </div>
    
    <div class="comments-list">
        <?php if (empty($comments)): ?>
            <p class="no-data">데이터 없음</p>
        <?php else: ?>
            <?php foreach ($comments as $comment): ?>
                <?php 
                $commentCreatedAt = $comment['created_at'] ?? '';
                $commentDate = '';
                $today = date('Y-m-d');
                
                if (!empty($commentCreatedAt)) {
                    if (strlen($commentCreatedAt) >= 10) {
                        $commentDate = substr($commentCreatedAt, 0, 10);
                    } else {
                        $timestamp = strtotime($commentCreatedAt);
                        if ($timestamp !== false) {
                            $commentDate = date('Y-m-d', $timestamp);
                        }
                    }
                }
                
                // 오늘 작성한 댓글만 수정/삭제 가능 -> 버튼 표시로 제한
                $canEdit = false;
                if (!empty($commentDate)) {
                    $canEdit = ($commentDate === $today);
                }
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
                        <?php if ($canEdit): ?>
                            <div class="comment-actions">
                                <button type="button" class="btn-edit" onclick="editComment(<?php echo $comment['id']; ?>, '<?php echo htmlspecialchars(addslashes($comment['content'])); ?>')">수정</button>
                                <button type="button" class="btn-delete" onclick="deleteComment(<?php echo $comment['id']; ?>)">삭제</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="comment-content" id="comment-content-<?php echo $comment['id']; ?>">
                        <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                    </div>
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
const apiBaseUrl = '<?php echo getApiBaseUrl(3) . "/comments"; ?>';

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
        credentials: 'include',
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.message) {
            alert(result.message);
            if (result.message.includes('등록되었습니다')) {
                location.reload();
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('댓글 등록 중 오류가 발생했습니다.');
    });
    
    return false;
}

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
        credentials: 'include',
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.message) {
            alert(result.message);
            if (result.message.includes('수정되었습니다')) {
                location.reload();
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('댓글 수정 중 오류가 발생했습니다.');
    });
    
    return false;
}

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
        credentials: 'include',
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.message) {
            alert(result.message);
            if (result.message.includes('삭제되었습니다')) {
                location.reload();
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('댓글 삭제 중 오류가 발생했습니다.');
    });
}

function editComment(commentId, content) {
    document.getElementById('comment-content-' + commentId).style.display = 'none';
    document.getElementById('edit-form-' + commentId).style.display = 'block';
    const commentItem = document.querySelector('[data-comment-id="' + commentId + '"]');
    const actions = commentItem.querySelector('.comment-actions');
    if (actions) {
        actions.style.display = 'none';
    }
}

function cancelEdit(commentId) {
    document.getElementById('edit-form-' + commentId).style.display = 'none';
    document.getElementById('comment-content-' + commentId).style.display = 'block';
    const commentItem = document.querySelector('[data-comment-id="' + commentId + '"]');
    const actions = commentItem.querySelector('.comment-actions');
    if (actions) {
        actions.style.display = 'block';
    }
}

function updatePlayerList() {
    const teamSelect = document.getElementById('supporting_team');
    const playerSelect = document.getElementById('supporting_player');
    const selectedTeamId = teamSelect.value;
    
    for (let i = 0; i < playerSelect.options.length; i++) {
        const option = playerSelect.options[i];
        const teamId = option.getAttribute('data-team-id');
        
        if (option.value === '' || selectedTeamId === '' || selectedTeamId === '0') {
            option.style.display = '';
        } else if (teamId === selectedTeamId) {
            option.style.display = '';
        } else {
            option.style.display = 'none';
        }
    }
    
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
    
    playerSelect.value = '';
}
</script>

