<?php
require_once '../config/database.php';
$db = getDB();

$pageTitle = "KBO 야구";

// 야구 경기 일정만 표시
$query = "
    SELECT 
        m.*,
        s.name as stadium_name,
        r.name as region_name,
        ht.name as home_team,
        at.name as away_team,
        ms.home_score,
        ms.away_score,
        ms.attendance
    FROM matches m
    JOIN sports sp ON m.sport_id = sp.id
    JOIN stadiums s ON m.stadium_id = s.id
    JOIN regions r ON s.region_id = r.id
    JOIN teams ht ON m.home_team_id = ht.id
    JOIN teams at ON m.away_team_id = at.id
    LEFT JOIN match_stat ms ON m.id = ms.match_id
    WHERE sp.name = '야구'
    ORDER BY m.match_date DESC, m.match_time
";

$matches = $db->query($query)->fetchAll();

include '../includes/header.php';
?>

<h2>KBO 야구 경기 일정</h2>
    
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
    
    <?php
}

include '../includes/footer.php';
?>


