<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
$db = getDB();

$pageTitle = "홈";

// 오늘의 경기 가져오기 (야구만)
$todayMatchesQuery = "
    SELECT * FROM today_matches_view
    WHERE sport_name = '야구'
    ORDER BY match_time
";
$todayMatches = $db->query($todayMatchesQuery)->fetchAll();

// 지역별 오늘 경기 수
$regionStatsQuery = "
    SELECT 
        region_name,
        COUNT(*) as match_count
    FROM today_matches_view
    WHERE sport_name = '야구'
    GROUP BY region_id, region_name
    ORDER BY match_count DESC
";
$regionStats = $db->query($regionStatsQuery)->fetchAll();

include '../includes/header.php';
?>

<div class="hero-section">
    <h2>오늘의 KBO 야구 경기</h2>
    <p><?php echo date('Y년 m월 d일'); ?> 경기 일정</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <h3>오늘의 경기</h3>
        <p class="stat-number"><?php echo count($todayMatches); ?></p>
        <p class="stat-label">경기</p>
    </div>
    <div class="stat-card">
        <h3>지역별</h3>
        <?php foreach ($regionStats as $stat): ?>
            <p><?php echo htmlspecialchars($stat['region_name']); ?>: <?php echo $stat['match_count']; ?>경기</p>
        <?php endforeach; ?>
    </div>
</div>

<div class="filter-section">
    <h3>필터</h3>
    <form method="GET" action="matches.php" class="filter-form">
        <select name="region">
            <option value="">전체 지역</option>
            <?php
            $regions = $db->query("SELECT * FROM regions ORDER BY name")->fetchAll();
            foreach ($regions as $region):
            ?>
                <option value="<?php echo $region['id']; ?>"><?php echo htmlspecialchars($region['name']); ?></option>
            <?php endforeach; ?>
        </select>
        
        <button type="submit">검색</button>
    </form>
</div>

<div class="matches-section">
    <h3>오늘의 경기 일정</h3>
    <?php if (empty($todayMatches)): ?>
        <p class="no-data">데이터 없음</p>
    <?php else: ?>
        <div class="matches-grid">
            <?php foreach ($todayMatches as $match): ?>
                <div class="match-card">
                    <div class="match-header">
                        <span class="region-badge"><?php echo htmlspecialchars($match['region_name']); ?></span>
                        <?php 
                        $status = getMatchStatus($match['match_date'], $match['match_time']);
                        ?>
                        <span class="status-badge <?php echo $status['class']; ?>"><?php echo $status['label']; ?></span>
                    </div>
                    <div class="match-time">
                        <?php echo date('H:i', strtotime($match['match_time'])); ?>
                    </div>
                    <div class="match-teams">
                        <div class="team home-team">
                            <strong><?php echo htmlspecialchars($match['home_team']); ?></strong>
                            <?php if ($match['home_score'] !== null): ?>
                                <span class="score"><?php echo $match['home_score']; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="vs">VS</div>
                        <div class="team away-team">
                            <strong><?php echo htmlspecialchars($match['away_team']); ?></strong>
                            <?php if ($match['away_score'] !== null): ?>
                                <span class="score"><?php echo $match['away_score']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="match-info">
                        <p><strong>경기장:</strong> <?php echo htmlspecialchars($match['stadium_name']); ?></p>
                        <?php if ($match['attendance']): ?>
                            <p><strong>관중:</strong> <?php echo number_format($match['attendance']); ?>명</p>
                        <?php endif; ?>
                    </div>
                    <a href="match_detail.php?id=<?php echo $match['id']; ?>" class="btn-detail">상세보기</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>


