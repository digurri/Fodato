<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
$db = getDB();

$pageTitle = "KBO 야구 경기 일정";

// 필터 파라미터
$regionFilter = $_GET['region'] ?? '';
$monthFilter = $_GET['month'] ?? date('Y-m');

// 쿼리 구성 (야구만)
$query = "
    SELECT 
        m.id,
        m.match_date,
        m.match_time,
        s.name as stadium_name,
        s.region_id,
        r.name as region_name,
        ht.name as home_team,
        at.name as away_team,
        ms.home_score,
        ms.away_score,
        ms.attendance,
        m.status
    FROM matches m
    JOIN sports sp ON m.sport_id = sp.id
    JOIN stadiums s ON m.stadium_id = s.id
    JOIN regions r ON s.region_id = r.id
    JOIN teams ht ON m.home_team_id = ht.id
    JOIN teams at ON m.away_team_id = at.id
    LEFT JOIN match_stat ms ON m.id = ms.match_id
    WHERE sp.name = '야구'
";

$params = [];

if ($regionFilter) {
    $query .= " AND s.region_id = :region";
    $params[':region'] = $regionFilter;
}

if ($monthFilter) {
    // 월 단위 필터링 (YYYY-MM 형식)
    $query .= " AND DATE_FORMAT(m.match_date, '%Y-%m') = :month";
    $params[':month'] = $monthFilter;
}

$query .= " ORDER BY m.match_date DESC, m.match_time";

$stmt = $db->prepare($query);
$stmt->execute($params);
$matches = $stmt->fetchAll();

// 지역 목록
$regions = $db->query("SELECT * FROM regions ORDER BY name")->fetchAll();

include '../includes/header.php';
?>

<h2>KBO 야구 경기 일정</h2>

<div class="filter-section">
    <form method="GET" action="matches.php" class="filter-form">
        <label>
            월:
            <input type="month" name="month" value="<?php echo htmlspecialchars($monthFilter); ?>">
        </label>
        
        <label>
            지역:
            <select name="region">
                <option value="">전체</option>
                <?php foreach ($regions as $region): ?>
                    <option value="<?php echo $region['id']; ?>" 
                        <?php echo $regionFilter == $region['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($region['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        
        <button type="submit">검색</button>
        <a href="matches.php" class="btn-reset">초기화</a>
    </form>
</div>

<div class="matches-section">
    <?php if (empty($matches)): ?>
        <p class="no-data">데이터 없음</p>
    <?php else: ?>
        <div class="matches-list">
            <?php 
            $currentDate = '';
            foreach ($matches as $match): 
                $matchDate = $match['match_date'];
                if ($currentDate !== $matchDate):
                    $currentDate = $matchDate;
                    $dateStr = date('Y년 m월 d일 (D)', strtotime($matchDate));
            ?>
                <h3 class="date-divider"><?php echo $dateStr; ?></h3>
            <?php endif; ?>
            
            <div class="match-item">
                <div class="match-time-col">
                    <div class="time"><?php echo date('H:i', strtotime($match['match_time'])); ?></div>
                    <span class="region-badge"><?php echo htmlspecialchars($match['region_name']); ?></span>
                    <?php 
                    $status = getMatchStatus($match['match_date'], $match['match_time']);
                    ?>
                    <span class="status-badge <?php echo $status['class']; ?>"><?php echo $status['label']; ?></span>
                </div>
                <div class="match-teams-col">
                    <div class="team-row">
                        <span class="team-name"><?php echo htmlspecialchars($match['home_team']); ?></span>
                        <?php if ($match['home_score'] !== null): ?>
                            <span class="score"><?php echo $match['home_score']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="team-row">
                        <span class="team-name"><?php echo htmlspecialchars($match['away_team']); ?></span>
                        <?php if ($match['away_score'] !== null): ?>
                            <span class="score"><?php echo $match['away_score']; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="match-info-col">
                    <p><strong><?php echo htmlspecialchars($match['stadium_name']); ?></strong></p>
                    <p><?php echo htmlspecialchars($match['region_name']); ?></p>
                    <?php if ($match['attendance']): ?>
                        <p class="attendance">관중: <?php echo number_format($match['attendance']); ?>명</p>
                    <?php endif; ?>
                </div>
                <div class="match-action-col">
                    <a href="match_detail.php?id=<?php echo $match['id']; ?>" class="btn-detail">상세보기</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>


