<?php
require_once '../config/database.php';
$db = getDB();

$pageTitle = "KBO 팀 상세";

$teamId = $_GET['id'] ?? 0;

if (!$teamId) {
    header('Location: teams.php');
    exit;
}

// 팀 정보 가져오기
$teamQuery = "
    SELECT 
        t.*,
        r.name as region_name,
        s.name as sport_name
    FROM teams t
    JOIN regions r ON t.region_id = r.id
    JOIN sports s ON t.sport_id = s.id
    WHERE t.id = :id
";

$teamStmt = $db->prepare($teamQuery);
$teamStmt->execute([':id' => $teamId]);
$team = $teamStmt->fetch();

if (!$team) {
    header('Location: teams.php');
    exit;
}

// 팀 선수 목록 가져오기
// position_stat 컬럼 존재 여부 확인
$checkColumnQuery = "
    SELECT COUNT(*) as cnt 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'players' 
    AND COLUMN_NAME = 'position_stat'
";
$hasPositionStat = $db->query($checkColumnQuery)->fetch()['cnt'] > 0;

$playersQuery = "
    SELECT * FROM players
    WHERE team_id = :team_id
    ORDER BY position, back_number
";

$playersStmt = $db->prepare($playersQuery);
$playersStmt->execute([':team_id' => $teamId]);
$players = $playersStmt->fetchAll();

// position_stat 컬럼이 없으면 빈 값으로 채우기
if (!$hasPositionStat) {
    foreach ($players as &$player) {
        $player['position_stat'] = null;
    }
    unset($player);
}

// 팀 경기 통계
$statsQuery = "
    SELECT 
        COUNT(*) as total_matches,
        COUNT(CASE WHEN m.status = 'finished' THEN 1 END) as finished_matches,
        COUNT(CASE WHEN m.match_date = CURDATE() THEN 1 END) as today_matches
    FROM matches m
    WHERE m.home_team_id = :team_id OR m.away_team_id = :team_id2
";

$statsStmt = $db->prepare($statsQuery);
$statsStmt->execute([
    ':team_id' => $teamId,
    ':team_id2' => $teamId
]);
$stats = $statsStmt->fetch();

// 통계가 없을 경우 기본값 설정
if (!$stats) {
    $stats = [
        'total_matches' => 0,
        'finished_matches' => 0,
        'today_matches' => 0
    ];
}

include '../includes/header.php';
?>

<div class="team-detail">
    <h2><?php echo htmlspecialchars($team['name']); ?></h2>
    
    <div class="team-info-grid">
        <div class="info-card">
            <h4>기본 정보</h4>
            <table>
                <tr>
                    <th>지역</th>
                    <td><?php echo htmlspecialchars($team['region_name']); ?></td>
                </tr>
                <tr>
                    <th>총 경기</th>
                    <td><?php echo number_format($stats['total_matches']); ?>경기</td>
                </tr>
                <tr>
                    <th>완료 경기</th>
                    <td><?php echo number_format($stats['finished_matches']); ?>경기</td>
                </tr>
                <tr>
                    <th>오늘 경기</th>
                    <td><?php echo number_format($stats['today_matches']); ?>경기</td>
                </tr>
            </table>
        </div>
    </div>
    
    <div class="players-section">
        <h3>선수 명단 (<?php echo count($players); ?>명)</h3>
        <?php if (empty($players)): ?>
            <div class="no-data">
                <p>데이터 없음</p>
            </div>
        <?php elseif (!$hasPositionStat): ?>
            <div class="no-data" style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <p style="color: #856404; font-weight: 600;">⚠️ 포지션 지표 컬럼이 데이터베이스에 없습니다.</p>
                <p style="margin-top: 10px; font-size: 0.9rem; color: #856404;">
                    포지션 지표를 표시하려면 phpMyAdmin에서 다음 SQL을 실행하세요:
                </p>
                <pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin-top: 10px; font-size: 0.85rem; overflow-x: auto;">ALTER TABLE players 
ADD COLUMN position_stat DECIMAL(5,3) DEFAULT NULL 
COMMENT '포지션별 대표 능력치 (투수: 평균자책점, 타자: 타율, 수비수: 수비율)';</pre>
                <p style="margin-top: 10px; font-size: 0.9rem; color: #856404;">
                    그 다음 <code>database/add_position_stat_column.sql</code> 파일을 실행하여 기존 선수 데이터에 능력치를 추가하세요.
                </p>
            </div>
        <?php else: ?>
            <div class="players-table-container">
                <table class="players-table">
                    <thead>
                        <tr>
                            <th>등번호</th>
                            <th>선수명</th>
                            <th>포지션</th>
                            <th>포지션 지표</th>
                            <th>생년월일</th>
                            <th>체격</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($players as $player): ?>
                            <?php
                            // 포지션별 지표 라벨 및 포맷 결정
                            $statLabel = '';
                            $statValue = '';
                            $positionStat = $player['position_stat'] ?? null;
                            if ($positionStat !== null && $positionStat !== '') {
                                if ($player['position'] === '투수') {
                                    $statLabel = '평균자책점';
                                    $statValue = number_format((float)$positionStat, 2);
                                } elseif (in_array($player['position'], ['포수', '2루수', '유격수'])) {
                                    $statLabel = '수비율';
                                    $statValue = number_format((float)$positionStat, 3);
                                } else {
                                    $statLabel = '타율';
                                    $statValue = number_format((float)$positionStat, 3);
                                }
                            }
                            ?>
                            <tr>
                                <td><?php echo $player['back_number'] ? '#' . $player['back_number'] : '-'; ?></td>
                                <td><strong><?php echo htmlspecialchars($player['name']); ?></strong></td>
                                <td><span class="position-badge"><?php echo htmlspecialchars($player['position'] ?? '-'); ?></span></td>
                                <td>
                                    <?php if ($statValue): ?>
                                        <span class="position-stat">
                                            <span class="stat-label"><?php echo $statLabel; ?>:</span>
                                            <span class="stat-value"><?php echo $statValue; ?></span>
                                        </span>
                                    <?php else: ?>
                                        <span class="no-stat">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $player['birth_date'] ? date('Y-m-d', strtotime($player['birth_date'])) : '-'; ?></td>
                                <td>
                                    <?php 
                                    if ($player['height'] && $player['weight']) {
                                        echo $player['height'] . 'cm / ' . $player['weight'] . 'kg';
                                    } elseif ($player['height']) {
                                        echo $player['height'] . 'cm';
                                    } elseif ($player['weight']) {
                                        echo $player['weight'] . 'kg';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="action-buttons">
        <a href="teams.php" class="btn btn-secondary">팀 목록</a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

