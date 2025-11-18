<?php
require_once '../config/database.php';
$db = getDB();

$pageTitle = "KBO 팀 목록";

$teamFilter = $_GET['team'] ?? '';

// 팀 목록 가져오기 (야구만)
$query = "
    SELECT 
        t.*,
        r.name as region_name,
        COUNT(DISTINCT p.id) as player_count,
        COUNT(DISTINCT m.id) as match_count
    FROM teams t
    JOIN regions r ON t.region_id = r.id
    JOIN sports sp ON t.sport_id = sp.id
    LEFT JOIN players p ON t.id = p.team_id
    LEFT JOIN matches m ON (t.id = m.home_team_id OR t.id = m.away_team_id)
    WHERE sp.name = '야구'
";

$params = [];

if ($teamFilter) {
    $query .= " AND t.id = :team";
    $params[':team'] = $teamFilter;
}

$query .= " GROUP BY t.id, t.name, t.sport_id, t.region_id, t.logo_url, t.created_at, r.name ORDER BY t.name";

$stmt = $db->prepare($query);
$stmt->execute($params);
$teams = $stmt->fetchAll();

// 선택된 팀의 선수 목록 가져오기
$selectedTeamPlayers = [];
if ($teamFilter) {
    $playersQuery = "
        SELECT * FROM players
        WHERE team_id = :team_id
        ORDER BY position, back_number
    ";
    $playersStmt = $db->prepare($playersQuery);
    $playersStmt->execute([':team_id' => $teamFilter]);
    $selectedTeamPlayers = $playersStmt->fetchAll();
}

include '../includes/header.php';
?>

<h2>KBO 팀 목록</h2>

<div class="filter-section">
    <form method="GET" action="teams.php" class="filter-form">
        <label>
            팀 선택:
            <select name="team" onchange="this.form.submit()">
                <option value="">전체 팀</option>
                <?php foreach ($teams as $team): ?>
                    <option value="<?php echo $team['id']; ?>"
                        <?php echo $teamFilter == $team['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($team['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if ($teamFilter): ?>
            <a href="teams.php" class="btn-reset">초기화</a>
        <?php endif; ?>
    </form>
</div>

<div class="teams-section">
    <?php if (empty($teams)): ?>
        <p class="no-data">데이터 없음</p>
    <?php else: ?>
        <div class="stadiums-grid">
            <?php foreach ($teams as $team): ?>
                <div class="stadium-card">
                    <h3><?php echo htmlspecialchars($team['name']); ?></h3>
                    <div class="stadium-badges">
                        <span class="region-badge"><?php echo htmlspecialchars($team['region_name']); ?></span>
                    </div>
                    <div class="stadium-info">
                        <p><strong>선수 수:</strong> <?php echo number_format($team['player_count']); ?>명</p>
                        <p><strong>경기 수:</strong> <?php echo number_format($team['match_count']); ?>경기</p>
                    </div>
                    <a href="team_detail.php?id=<?php echo $team['id']; ?>" class="btn-detail">팀 상세보기</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($teamFilter && !empty($selectedTeamPlayers)): ?>
<div class="players-section">
    <h3><?php 
        $selectedTeam = array_filter($teams, function($t) use ($teamFilter) { return $t['id'] == $teamFilter; });
        $selectedTeam = reset($selectedTeam);
        echo htmlspecialchars($selectedTeam['name']); 
    ?> 선수 명단 (<?php echo count($selectedTeamPlayers); ?>명)</h3>
    <div class="players-grid">
        <?php foreach ($selectedTeamPlayers as $player): ?>
            <div class="player-card">
                <div class="player-header">
                    <span class="back-number"><?php echo $player['back_number'] ? '#' . $player['back_number'] : '-'; ?></span>
                    <span class="position-badge"><?php echo htmlspecialchars($player['position'] ?? '-'); ?></span>
                </div>
                <h4><?php echo htmlspecialchars($player['name']); ?></h4>
                <div class="player-info">
                    <?php if ($player['birth_date']): ?>
                        <p>생년월일: <?php echo date('Y-m-d', strtotime($player['birth_date'])); ?></p>
                    <?php endif; ?>
                    <?php if ($player['height'] && $player['weight']): ?>
                        <p>신체: <?php echo $player['height']; ?>cm / <?php echo $player['weight']; ?>kg</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

