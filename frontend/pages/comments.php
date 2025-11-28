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
                <select name="supporting_team_id" id="supporting_team" onchange="filterPlayersByTeam()">
                    <option value="">선택 안 함</option>
                    <?php foreach ($matchTeams as $team): ?>
                        <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="supporting_player">응원 선수</label>
                <select name="supporting_player_id" id="supporting_player">
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
                                <span class="badge team">
                                    <?php echo htmlspecialchars($teamName); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($playerName): ?>
                                <span class="badge player">
                                    <?php echo htmlspecialchars($playerName); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <span class="date"><?php echo substr($c['created_at'], 0, 16); ?></span>
                        
                        <?php if ($canEdit): ?>
                            <div class="actions">
                                <button type="button" onclick="toggleEditMode(<?php echo $c['comment_id']; ?>)">수정</button>
                                <button type="button" onclick="deleteComment(<?php echo $c['comment_id']; ?>)">삭제</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="comment-body" id="view-<?php echo $c['comment_id']; ?>">
                        <?php echo nl2br(htmlspecialchars($c['content'])); ?>
                    </div>

                    <div class="comment-edit" id="edit-<?php echo $c['comment_id']; ?>">
                        <textarea id="input-<?php echo $c['comment_id']; ?>" rows="3">
<?php echo htmlspecialchars($c['content']); ?>
                        </textarea>
                        <div class="edit-btns">
                            <button onclick="updateCommentAction(<?php echo $c['comment_id']; ?>)">저장</button>
                            <button onclick="toggleEditMode(<?php echo $c['comment_id']; ?>)">취소</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

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

// 댓글 등록
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

// 댓글 수정
async function updateCommentAction(id) {
    const content = document.getElementById('input-' + id).value.trim();
    if (!content) return alert('내용을 입력해주세요.');

    const res = await sendApiRequest('/update.php', 'PUT', { comment_id: id, content: content });
    if (res && res.message && res.message.includes('수정')) location.reload();
}

// 댓글 삭제
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
            group.display = '';
        } else {
            const firstOpt = group.querySelector('option');
            group.style.display = (firstOpt && firstOpt.getAttribute('data-team-id') === teamId) ? '' : 'none';
        }
    });
    playerSelect.value = ''; 
}
</script>
