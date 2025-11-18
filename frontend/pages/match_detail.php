<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
$db = getDB();

$pageTitle = "KBO 야구 경기 상세";

$matchId = $_GET['id'] ?? 0;

// game_winning_hit 컬럼 존재 여부 확인
$columnExists = false;
try {
    $checkQuery = "SHOW COLUMNS FROM match_stat LIKE 'game_winning_hit'";
    $checkStmt = $db->query($checkQuery);
    $columnExists = $checkStmt->fetch() !== false;
} catch (PDOException $e) {
    // 테이블이 없거나 오류 발생 시 무시
}

// 경기 상세 정보
$gameWinningHitField = $columnExists ? ", ms.game_winning_hit" : "";
$query = "
    SELECT 
        m.*,
        sp.name as sport_name,
        s.name as stadium_name,
        s.location,
        s.capacity,
        s.address,
        r.name as region_name,
        ht.name as home_team,
        at.name as away_team,
        ms.home_score,
        ms.away_score,
        ms.attendance,
        ms.weather,
        ms.notes
        $gameWinningHitField
    FROM matches m
    JOIN sports sp ON m.sport_id = sp.id
    JOIN stadiums s ON m.stadium_id = s.id
    JOIN regions r ON s.region_id = r.id
    JOIN teams ht ON m.home_team_id = ht.id
    JOIN teams at ON m.away_team_id = at.id
    LEFT JOIN match_stat ms ON m.id = ms.match_id
    WHERE m.id = :id
";

$stmt = $db->prepare($query);
$stmt->execute([':id' => $matchId]);
$match = $stmt->fetch();

if (!$match) {
    header('Location: matches.php');
    exit;
}

// 댓글 목록 가져오기 (응원 팀 및 선수 정보 포함)
$commentsQuery = "
    SELECT 
        c.*,
        st.name as supporting_team_name,
        sp.name as supporting_player_name,
        sp.back_number as supporting_player_number
    FROM comments c
    LEFT JOIN teams st ON c.supporting_team_id = st.id
    LEFT JOIN players sp ON c.supporting_player_id = sp.id
    WHERE c.match_id = :match_id
    ORDER BY c.created_at DESC
";
$commentsStmt = $db->prepare($commentsQuery);
$commentsStmt->execute([':match_id' => $matchId]);
$comments = $commentsStmt->fetchAll();

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
    SELECT p.id, p.name, p.back_number, p.position, t.id as team_id, t.name as team_name
    FROM players p
    JOIN teams t ON p.team_id = t.id
    JOIN matches m ON (t.id = m.home_team_id OR t.id = m.away_team_id)
    WHERE m.id = :match_id
    ORDER BY t.name, p.position, p.back_number
";
$matchPlayersStmt = $db->prepare($matchPlayersQuery);
$matchPlayersStmt->execute([':match_id' => $matchId]);
$matchPlayers = $matchPlayersStmt->fetchAll();

// 사용자 토큰 생성 또는 가져오기 (쿠키 사용)
if (!isset($_COOKIE['user_token'])) {
    $userToken = bin2hex(random_bytes(16));
    setcookie('user_token', $userToken, time() + (86400 * 365), '/'); // 1년간 유지
} else {
    $userToken = $_COOKIE['user_token'];
}

// 홈팀 통계 가져오기
$homeTeamStatsQuery = "
    SELECT 
        AVG(CASE 
            WHEN p.position IN ('1루수', '3루수', '좌익수', '중견수', '우익수', '지명타자') 
            THEN p.position_stat 
            ELSE NULL 
        END) as team_batting_avg,
        SUM(p.steal_attempts) as total_steal_attempts,
        SUM(p.steal_success) as total_steal_success,
        CASE 
            WHEN SUM(p.steal_attempts) > 0 
            THEN (SUM(p.steal_success) / SUM(p.steal_attempts)) * 100
            ELSE 0 
        END as steal_success_rate
    FROM teams t
    LEFT JOIN players p ON t.id = p.team_id
    WHERE t.id = :team_id
";

$homeTeamStatsStmt = $db->prepare($homeTeamStatsQuery);
$homeTeamStatsStmt->execute([':team_id' => $match['home_team_id']]);
$homeTeamStats = $homeTeamStatsStmt->fetch();

// 원정팀 통계 가져오기
$awayTeamStatsQuery = "
    SELECT 
        AVG(CASE 
            WHEN p.position IN ('1루수', '3루수', '좌익수', '중견수', '우익수', '지명타자') 
            THEN p.position_stat 
            ELSE NULL 
        END) as team_batting_avg,
        SUM(p.steal_attempts) as total_steal_attempts,
        SUM(p.steal_success) as total_steal_success,
        CASE 
            WHEN SUM(p.steal_attempts) > 0 
            THEN (SUM(p.steal_success) / SUM(p.steal_attempts)) * 100
            ELSE 0 
        END as steal_success_rate
    FROM teams t
    LEFT JOIN players p ON t.id = p.team_id
    WHERE t.id = :team_id
";

$awayTeamStatsStmt = $db->prepare($awayTeamStatsQuery);
$awayTeamStatsStmt->execute([':team_id' => $match['away_team_id']]);
$awayTeamStats = $awayTeamStatsStmt->fetch();

include '../includes/header.php';
?>

<div class="match-detail">
    <div class="detail-header">
        <?php 
        $status = getMatchStatus($match['match_date'], $match['match_time']);
        ?>
        <span class="status-badge <?php echo $status['class']; ?>">
            <?php echo $status['label']; ?>
        </span>
    </div>

    <div class="match-score-section">
        <div class="team-section">
            <h3><?php echo htmlspecialchars($match['home_team']); ?></h3>
            <div class="score-large">
                <?php echo $match['home_score'] !== null ? $match['home_score'] : '-'; ?>
            </div>
        </div>
        <div class="vs-section">VS</div>
        <div class="team-section">
            <h3><?php echo htmlspecialchars($match['away_team']); ?></h3>
            <div class="score-large">
                <?php echo $match['away_score'] !== null ? $match['away_score'] : '-'; ?>
            </div>
        </div>
    </div>

    <div class="match-info-grid">
        <div class="info-card">
            <h4>경기 정보</h4>
            <table>
                <tr>
                    <th>날짜</th>
                    <td><?php echo date('Y년 m월 d일', strtotime($match['match_date'])); ?></td>
                </tr>
                <tr>
                    <th>시간</th>
                    <td><?php echo date('H:i', strtotime($match['match_time'])); ?></td>
                </tr>
                <tr>
                    <th>경기장</th>
                    <td><?php echo htmlspecialchars($match['stadium_name']); ?></td>
                </tr>
                <tr>
                    <th>지역</th>
                    <td><?php echo htmlspecialchars($match['region_name']); ?></td>
                </tr>
                <tr>
                    <th>주소</th>
                    <td><?php echo htmlspecialchars($match['address']); ?></td>
                </tr>
                <tr>
                    <th>수용 인원</th>
                    <td><?php echo number_format($match['capacity']); ?>명</td>
                </tr>
            </table>
        </div>

        <div class="info-card">
            <h4>경기 통계</h4>
            <table>
                <?php if ($match['attendance']): ?>
                <tr>
                    <th>관중 수</th>
                    <td><?php echo number_format($match['attendance']); ?>명</td>
                </tr>
                <?php endif; ?>
                <?php if ($match['weather']): ?>
                <tr>
                    <th>날씨</th>
                    <td><?php echo htmlspecialchars($match['weather']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($match['notes']): ?>
                <tr>
                    <th>비고</th>
                    <td><?php echo nl2br(htmlspecialchars($match['notes'])); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>결승타</th>
                    <td><?php 
                        if ($columnExists && isset($match['game_winning_hit']) && $match['game_winning_hit']) {
                            echo htmlspecialchars($match['game_winning_hit']);
                        } else {
                            echo '<span style="color: #999; font-style: italic;">정보 없음</span>';
                        }
                    ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- 팀별 성적 비교 -->
    <div class="team-stats-comparison">
        <h3>팀별 성적 비교</h3>
        <div class="team-stats-grid">
            <!-- 홈팀 통계 -->
            <div class="team-stat-card">
                <h4><?php echo htmlspecialchars($match['home_team']); ?></h4>
                <div class="stat-items">
                    <div class="stat-item">
                        <span class="stat-label">팀 타율</span>
                        <span class="stat-value">
                            <?php 
                            if ($homeTeamStats && $homeTeamStats['team_batting_avg'] !== null) {
                                echo number_format((float)$homeTeamStats['team_batting_avg'], 3);
                            } else {
                                echo '-';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">도루 성공률</span>
                        <span class="stat-value">
                            <?php 
                            if ($homeTeamStats && $homeTeamStats['steal_success_rate'] > 0) {
                                echo number_format((float)$homeTeamStats['steal_success_rate'], 1) . '%';
                            } else {
                                echo '-';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">도루 시도</span>
                        <span class="stat-value">
                            <?php 
                            if ($homeTeamStats && $homeTeamStats['total_steal_attempts'] > 0) {
                                echo number_format($homeTeamStats['total_steal_attempts']) . '회';
                            } else {
                                echo '-';
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- 원정팀 통계 -->
            <div class="team-stat-card">
                <h4><?php echo htmlspecialchars($match['away_team']); ?></h4>
                <div class="stat-items">
                    <div class="stat-item">
                        <span class="stat-label">팀 타율</span>
                        <span class="stat-value">
                            <?php 
                            if ($awayTeamStats && $awayTeamStats['team_batting_avg'] !== null) {
                                echo number_format((float)$awayTeamStats['team_batting_avg'], 3);
                            } else {
                                echo '-';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">도루 성공률</span>
                        <span class="stat-value">
                            <?php 
                            if ($awayTeamStats && $awayTeamStats['steal_success_rate'] > 0) {
                                echo number_format((float)$awayTeamStats['steal_success_rate'], 1) . '%';
                            } else {
                                echo '-';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">도루 시도</span>
                        <span class="stat-value">
                            <?php 
                            if ($awayTeamStats && $awayTeamStats['total_steal_attempts'] > 0) {
                                echo number_format($awayTeamStats['total_steal_attempts']) . '회';
                            } else {
                                echo '-';
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 댓글 섹션 -->
    <div class="comments-section">
        <h4>댓글 (<?php echo count($comments); ?>)</h4>
        
        <!-- 댓글 작성 폼 -->
        <div class="comment-form">
            <form method="POST" action="comment_action.php" id="commentForm">
                <input type="hidden" name="match_id" value="<?php echo $matchId; ?>">
                <input type="hidden" name="user_token" value="<?php echo htmlspecialchars($userToken); ?>">
                
                <div class="form-group">
                    <label for="supporting_team">💬 응원 팀 선택</label>
                    <select name="supporting_team_id" id="supporting_team" onchange="updatePlayerList()">
                        <option value="">선택 안 함</option>
                        <?php foreach ($matchTeams as $team): ?>
                            <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['name']); ?></option>
                        <?php endforeach; ?>
                        <option value="0">기타</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="supporting_player">💬 응원 선수 선택</label>
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
                                if ($player['back_number']) {
                                    echo ' #' . $player['back_number'];
                                }
                                if ($player['position']) {
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
                    $isMyComment = (md5($userToken) === $comment['user_token']);
                    // 오늘 쓴 댓글인지 확인 (날짜만 비교)
                    $commentDate = date('Y-m-d', strtotime($comment['created_at']));
                    $today = date('Y-m-d');
                    $canEdit = $isMyComment && ($commentDate === $today);
                    ?>
                    <div class="comment-item <?php echo $isMyComment ? 'my-comment' : 'other-comment'; ?>" data-comment-id="<?php echo $comment['id']; ?>">
                        <div class="comment-header">
                            <div class="comment-author-info">
                                <strong class="comment-nickname">익명</strong>
                                <?php if ($isMyComment): ?>
                                    <span class="my-comment-badge">내 댓글</span>
                                <?php endif; ?>
                                <?php if ($comment['supporting_team_name']): ?>
                                    <span class="supporting-badge team-badge">응원: <?php echo htmlspecialchars($comment['supporting_team_name']); ?></span>
                                <?php endif; ?>
                                <?php if ($comment['supporting_player_name']): ?>
                                    <span class="supporting-badge player-badge">
                                        선수: <?php echo htmlspecialchars($comment['supporting_player_name']); ?>
                                        <?php if ($comment['supporting_player_number']): ?>
                                            #<?php echo $comment['supporting_player_number']; ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span class="comment-date">
                                <?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?>
                                <?php if ($comment['updated_at'] != $comment['created_at']): ?>
                                    <span class="edited-badge">(수정됨)</span>
                                <?php endif; ?>
                            </span>
                            <?php if ($canEdit): ?>
                                <div class="comment-actions">
                                    <button type="button" class="btn-edit" onclick="editComment(<?php echo $comment['id']; ?>, '<?php echo htmlspecialchars(addslashes($comment['content'])); ?>')">수정</button>
                                    <form method="POST" action="comment_action.php" class="delete-comment-form" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                        <input type="hidden" name="user_token" value="<?php echo htmlspecialchars($userToken); ?>">
                                        <button type="submit" class="btn-delete" onclick="return confirm('댓글을 삭제하시겠습니까?');">삭제</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="comment-content" id="comment-content-<?php echo $comment['id']; ?>">
                            <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                        </div>
                        <!-- 수정 폼 (기본적으로 숨김) -->
                        <div class="comment-edit-form" id="edit-form-<?php echo $comment['id']; ?>" style="display: none;">
                            <form method="POST" action="comment_action.php" onsubmit="return validateEditForm(<?php echo $comment['id']; ?>)">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                <input type="hidden" name="user_token" value="<?php echo htmlspecialchars($userToken); ?>">
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

    <div class="action-buttons">
        <a href="stadiums.php?id=<?php echo $match['stadium_id']; ?>" class="btn">경기장 정보</a>
        <a href="matches.php" class="btn btn-secondary">목록으로</a>
    </div>
</div>

<script>
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

function validateEditForm(commentId) {
    const content = document.getElementById('edit-content-' + commentId).value.trim();
    
    if (!content) {
        alert('댓글 내용을 입력해주세요.');
        return false;
    }
    
    return true;
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

// 수정 폼에서도 동일한 기능
function updateEditPlayerList(commentId) {
    const teamSelect = document.getElementById('edit-supporting-team-' + commentId);
    const playerSelect = document.getElementById('edit-supporting-player-' + commentId);
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
}
</script>

<?php include '../includes/footer.php'; ?>


