<!-- 댓글  -->
 
<?php

if (!isset($matchId)) {
    $matchId = $_GET['match_id'] ?? 0;
}
if (!$matchId) return;

// DB 연결
if (!isset($db)) {
    require_once '../config/database.php';
    $db = getDB();
}

require_once '../helpers/api_helper.php';

$comments = [];
$apiBaseUrl = getApiBaseUrl(); 
$result = callApi($apiBaseUrl . '/comments/list.php?match_id=' . $matchId);

if ($result['success']) {
    $json = json_decode($result['response'], true);
    $comments = $json['data'] ?? [];
}

// 팀 조회 
$stmt = $db->prepare("
    SELECT DISTINCT t.id, t.name
    FROM teams t
    JOIN matches m ON (t.id = m.home_team_id OR t.id = m.away_team_id)
    WHERE m.id = ?
    ORDER BY t.name
");
$stmt->execute([$matchId]);
$matchTeams = $stmt->fetchAll();

// 선수 조회
$stmt = $db->prepare("
    SELECT p.id, p.name, p.position, t.id as team_id, t.name as team_name
    FROM players p
    JOIN teams t ON p.team_id = t.id
    JOIN matches m ON (t.id = m.home_team_id OR t.id = m.away_team_id)
    WHERE m.id = ?
    ORDER BY t.name, p.position, p.name
");
$stmt->execute([$matchId]);
$matchPlayers = $stmt->fetchAll();
?>

<div class="comments-section">
    <h4>댓글 (<?php echo count($comments); ?>)</h4>
    
    <div class="comment-form">
        <form id="commentForm" onsubmit="return handleCommentSubmit(event)">
            <input type="hidden" name="match_id" id="comment_match_id" value="<?php echo $matchId; ?>">
            
            <div class="form-group">
                <label for="supporting_team">응원 팀</label>
                <select name="supporting_team_id" id="supporting_team" onchange="filterPlayersByTeam()" style="width: 100% !important; padding: 12px 16px !important; border: 2px solid #dee2e6 !important; border-radius: 8px !important; font-size: 0.95rem !important; font-family: inherit !important; transition: all 0.3s ease !important; background: #ffffff !important; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06) !important; color: #212529 !important; appearance: none !important; -webkit-appearance: none !important; -moz-appearance: none !important; cursor: pointer !important; background-image: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23667eea' d='M6 9L1 4h10z'/%3E%3C/svg%3E\") !important; background-repeat: no-repeat !important; background-position: right 16px center !important; padding-right: 40px !important;">
                    <option value="">선택 안 함</option>
                    <?php foreach ($matchTeams as $team): ?>
                        <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="supporting_player">응원 선수</label>
                <select name="supporting_player_id" id="supporting_player" style="width: 100% !important; padding: 12px 16px !important; border: 2px solid #dee2e6 !important; border-radius: 8px !important; font-size: 0.95rem !important; font-family: inherit !important; transition: all 0.3s ease !important; background: #ffffff !important; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06) !important; color: #212529 !important; appearance: none !important; -webkit-appearance: none !important; -moz-appearance: none !important; cursor: pointer !important; background-image: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23667eea' d='M6 9L1 4h10z'/%3E%3C/svg%3E\") !important; background-repeat: no-repeat !important; background-position: right 16px center !important; padding-right: 40px !important;">
                    <option value="">선택 안 함</option>
                    <?php 
                    $lastTeamId = null;
                    foreach ($matchPlayers as $p): 
                        if ($lastTeamId !== $p['team_id']):
                            if ($lastTeamId !== null) echo '</optgroup>';
                            echo '<optgroup label="' . htmlspecialchars($p['team_name']) . '">';
                            $lastTeamId = $p['team_id'];
                        endif;
                    ?>
                        <option value="<?php echo $p['id']; ?>" data-team-id="<?php echo $p['team_id']; ?>">
                            <?php echo htmlspecialchars($p['name']); ?> 
                            <?php echo $p['position'] ? '('.$p['position'].')' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if ($lastTeamId !== null) echo '</optgroup>'; ?>
                </select>
            </div>
            
            <div class="form-group">
                <textarea name="content" id="content" rows="3" required placeholder="응원 한마디를 남겨주세요!"></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">등록</button>
        </form>
    </div>
    
    <div class="comments-list">
        <?php if (empty($comments)): ?>
            <p class="no-data">첫 번째 댓글을 남겨보세요!</p>
        <?php else: ?>
            <?php 
            $today = date('Y-m-d');
            foreach ($comments as $c): 
                $cDate = substr($c['created_at'], 0, 10);
                $canEdit = ($cDate === $today);
    
                $teamName = $c['team_name'] ?? '';
                $playerName = $c['player_name'] ?? '';
            ?>
                <div class="comment-item" id="comment-row-<?php echo $c['comment_id']; ?>">
                    <div class="comment-header">
                        <div class="info">
                            <strong>익명</strong>
                            <?php if ($teamName): ?>
                                <span class="badge team" style="display: inline-block; padding: 5px 12px; border-radius: 15px; font-size: 0.8rem; font-weight: 600; margin-left: 8px; white-space: nowrap; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; box-shadow: 0 2px 6px rgba(102, 126, 234, 0.3);"><?php echo htmlspecialchars($teamName); ?></span>
                            <?php endif; ?>
                            <?php if ($playerName): ?>
                                <span class="badge player" style="display: inline-block; padding: 5px 12px; border-radius: 15px; font-size: 0.8rem; font-weight: 600; margin-left: 8px; white-space: nowrap; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; box-shadow: 0 2px 6px rgba(245, 87, 108, 0.3);"><?php echo htmlspecialchars($playerName); ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="date"><?php echo substr($c['created_at'], 0, 16); ?></span>
                        
                        <?php if ($canEdit): ?>
                            <div class="actions" style="display: flex; gap: 8px;">
                                <button type="button" onclick="toggleEditMode(<?php echo $c['comment_id']; ?>)" style="padding: 6px 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 600; transition: all 0.3s; box-shadow: 0 2px 5px rgba(102, 126, 234, 0.3);">수정</button>
                                <button type="button" onclick="deleteComment(<?php echo $c['comment_id']; ?>)" style="padding: 6px 14px; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 600; transition: all 0.3s; box-shadow: 0 2px 5px rgba(220, 53, 69, 0.3);">삭제</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="comment-body" id="view-<?php echo $c['comment_id']; ?>">
                        <?php echo nl2br(htmlspecialchars($c['content'])); ?>
                    </div>

                    <div class="comment-edit" id="edit-<?php echo $c['comment_id']; ?>" style="display:none;">
                        <textarea id="input-<?php echo $c['comment_id']; ?>" rows="3" style="width: 100%; padding: 10px 15px; border: 2px solid #dee2e6; border-radius: 8px; font-size: 0.95rem; font-family: inherit; transition: all 0.3s; background: white; margin-bottom: 10px;"><?php echo htmlspecialchars($c['content']); ?></textarea>
                        <div class="edit-btns" style="display: flex; gap: 10px;">
                            <button onclick="updateCommentAction(<?php echo $c['comment_id']; ?>)" style="padding: 8px 20px; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.9rem; font-weight: 600; transition: all 0.3s; box-shadow: 0 2px 5px rgba(40, 167, 69, 0.3);">저장</button>
                            <button onclick="toggleEditMode(<?php echo $c['comment_id']; ?>)" style="padding: 8px 20px; background: #6c757d; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.9rem; font-weight: 600; transition: all 0.3s; box-shadow: 0 2px 5px rgba(108, 117, 125, 0.3);">취소</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.comment-actions button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.4) !important;
}
.comment-actions button[onclick*="toggleEditMode"]:hover {
    background: linear-gradient(135deg, #764ba2 0%, #667eea 100%) !important;
}
.comment-actions button[onclick*="deleteComment"]:hover {
    background: linear-gradient(135deg, #c82333 0%, #bd2130 100%) !important;
}
.edit-btns button[onclick*="updateCommentAction"]:hover {
    background: linear-gradient(135deg, #20c997 0%, #28a745 100%) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(40, 167, 69, 0.4) !important;
}
.edit-btns button[onclick*="toggleEditMode"]:hover {
    background: #5a6268 !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(108, 117, 125, 0.4) !important;
}
#supporting_team:focus,
#supporting_player:focus {
    outline: none !important;
    border-color: #667eea !important;
    box-shadow: 0 0 0 5px rgba(102, 126, 234, 0.15), 0 6px 20px rgba(102, 126, 234, 0.12) !important;
    transform: translateY(-2px) !important;
    background-color: #fafbff !important;
}
#supporting_team:hover,
#supporting_player:hover {
    border-color: #adb5bd !important;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08) !important;
}
</style>

<script>
//  API 호출 공통 함수
async function sendApiRequest(endpoint, method, data = null) {
    const apiBase = '<?php echo getApiBaseUrl() . "/comments"; ?>'; 
    
    try {
        const options = {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include'
        };
        if (data) options.body = JSON.stringify(data);

        const response = await fetch(apiBase + endpoint, options);
        const result = await response.json();

        // 성공 메시지가 있으면 알림
        if (result.message && method !== 'GET') {
            alert(result.message);
        }
        return result;
    } catch (error) {
        console.error(error);
        alert('처리 중 오류가 발생했습니다.');
        return null;
    }
}

// 2. 댓글 등록
async function handleCommentSubmit(e) {
    e.preventDefault();
    const content = document.getElementById('content').value.trim();
    if (!content) return alert('내용을 입력해주세요.');

    const data = {
        match_id: document.getElementById('comment_match_id').value,
        content: content,
        team_id: document.getElementById('supporting_team').value || null,
        player_id: document.getElementById('supporting_player').value || null
    };

    const res = await sendApiRequest('/create.php', 'POST', data);
    if (res && res.message && res.message.includes('등록')) location.reload();
}

// 3. 댓글 수정
async function updateCommentAction(id) {
    const content = document.getElementById('input-' + id).value.trim();
    if (!content) return alert('내용을 입력해주세요.');

    const res = await sendApiRequest('/update.php', 'PUT', { comment_id: id, content: content });
    if (res && res.message && res.message.includes('수정')) location.reload();
}

// 4. 댓글 삭제
async function deleteComment(id) {
    if (!confirm('정말 삭제하시겠습니까?')) return;
    
    const res = await sendApiRequest('/delete.php', 'DELETE', { comment_id: id });
    if (res && res.message && res.message.includes('삭제')) location.reload();
}

// UI: 수정 모드 토글
function toggleEditMode(id) {
    const viewDiv = document.getElementById('view-' + id);
    const editDiv = document.getElementById('edit-' + id);
    const isEditing = editDiv.style.display === 'block';
    
    viewDiv.style.display = isEditing ? 'block' : 'none';
    editDiv.style.display = isEditing ? 'none' : 'block';
}

// UI: 팀 선택 시 선수 필터링
function filterPlayersByTeam() {
    const teamId = document.getElementById('supporting_team').value;
    const playerSelect = document.getElementById('supporting_player');
    const options = playerSelect.querySelectorAll('option');
    const groups = playerSelect.querySelectorAll('optgroup');

    options.forEach(opt => {
        const pTeam = opt.getAttribute('data-team-id');
        if (!teamId || teamId === '0' || !pTeam || pTeam === teamId) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });

    groups.forEach(group => {
        if (!teamId || teamId === '0') {
            group.style.display = '';
        } else {
            const firstOpt = group.querySelector('option');
            group.style.display = (firstOpt && firstOpt.getAttribute('data-team-id') === teamId) ? '' : 'none';
        }
    });
    playerSelect.value = ''; 
}
</script>