<!-- written by 2171090 SeungHyeon Lee -->
<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../helpers/match_helper.php'; 
require_once '../helpers/api_helper.php';   

$db = getDB();
$pageTitle = "KBO 야구 경기장 정보";

// GET 파라미터 확인
$stadiumId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($stadiumId) {

    $stadiumData = null;
    $apiBaseUrl = getApiBaseUrl(); 
    $result = callApi($apiBaseUrl . '/stadiums/detail.php?id=' . $stadiumId);

    if ($result['success']) {
        $json = json_decode($result['response'], true);
        $stadiumData = $json['stadium'] ?? null;
    }

    if (!$stadiumData) {
        header('Location: stadiums.php');
        exit;
    }

    $recentMatch = $stadiumData['recent_match'] ?? null;
    $teams = explode(' vs ', $recentMatch['teams'] ?? ' vs ');
    
    // 경기 ID가 없으면 DB에서 조회
    $matchId = null;
    if ($recentMatch && empty($recentMatch['id']) && !empty($recentMatch['date']) && !empty($recentMatch['time'])) {
        $homeTeamName = trim($teams[0] ?? '');
        $awayTeamName = trim($teams[1] ?? '');
        
        if ($homeTeamName && $awayTeamName) {
            $matchQuery = $db->prepare("
                SELECT m.id 
                FROM matches m
                JOIN teams ht ON m.home_team_id = ht.id
                JOIN teams at ON m.away_team_id = at.id
                WHERE m.stadium_id = :stadium_id 
                AND m.date = :match_date 
                AND m.time LIKE :match_time
                AND ht.name = :home_team
                AND at.name = :away_team
                LIMIT 1
            ");
            $matchTime = $recentMatch['time'] . '%';
            $matchQuery->execute([
                ':stadium_id' => $stadiumId,
                ':match_date' => $recentMatch['date'],
                ':match_time' => $matchTime,
                ':home_team' => $homeTeamName,
                ':away_team' => $awayTeamName
            ]);
            $matchResult = $matchQuery->fetch();
            if ($matchResult) {
                $matchId = $matchResult['id'];
            }
        }
    } else if ($recentMatch && !empty($recentMatch['id'])) {
        $matchId = $recentMatch['id'];
    }

    include '../includes/header.php';
    ?>
    
    <div class="stadium-detail" style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); margin-bottom: 30px;">
        <h2 style="color: #667eea; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 3px solid #667eea;"><?php echo htmlspecialchars($stadiumData['name']); ?></h2>
        
        <div class="stadium-info-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="info-card">
                <h4>기본 정보</h4>
                <table>
                    <tr><th>지역</th><td><?php echo htmlspecialchars($stadiumData['region'] ?? '-'); ?></td></tr>
                    <tr><th>위치</th><td><?php echo htmlspecialchars($stadiumData['location'] ?? '-'); ?></td></tr>
                    <tr><th>주소</th><td><?php echo htmlspecialchars($stadiumData['address'] ?? '-'); ?></td></tr>
                    <tr><th>수용 인원</th><td><?php echo number_format($stadiumData['capacity'] ?? 0); ?>명</td></tr>
                </table>
            </div>
            <div class="info-card">
                <h4>통계 정보</h4>
                <table>
                    <tr><th>총 경기 수</th><td><?php echo number_format($stadiumData['total_matches'] ?? 0); ?>경기</td></tr>
                    <tr><th>평균 관중 수</th><td><?php echo number_format($stadiumData['avg_spectators'] ?? 0); ?>명</td></tr>
                </table>
            </div>
        </div>

        <div class="stadium-matches" style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); margin-bottom: 30px;">
            <h4 style="color: #667eea; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #667eea; font-size: 1.3rem;">최근 경기</h4>
            <?php if ($recentMatch): 
                $status = getMatchStatus($recentMatch['date'], $recentMatch['time']); ?>
                <div class="matches-list">
                    <div class="match-item">
                        <div class="match-date" style="display: flex; flex-direction: column; gap: 8px; min-width: 0;">
                            <div style="font-size: 0.9rem;"><?php echo date('Y-m-d H:i', strtotime($recentMatch['date'].' '.$recentMatch['time'])); ?></div>
                            <span class="status-badge <?php echo $status['class']; ?>" style="font-size: 0.75rem; padding: 3px 8px; display: inline-block; width: fit-content;">
                                <?php echo htmlspecialchars($status['label']); ?>
                            </span>
                        </div>
                        <div class="match-teams" style="font-weight: 500; font-size: 1rem; color: #333; min-width: 0;">
                            <?php echo htmlspecialchars($teams[0] ?? ''); ?> vs <?php echo htmlspecialchars($teams[1] ?? ''); ?>
                        </div>
                        <div class="attendance" style="color: #28a745; font-weight: 500; font-size: 0.9rem; min-width: 0;">
                            관중: <?php echo number_format($recentMatch['spectators'] ?? 0); ?>명
                        </div>
                        <div class="match-action-col" style="display: flex; justify-content: flex-end; align-items: center; min-width: 120px;">
                            <?php if ($matchId): ?>
                                <a href="match_detail.php?id=<?php echo $matchId; ?>" class="btn-detail" style="white-space: nowrap; text-align: center; display: inline-block;">상세보기</a>
                            <?php else: ?>
                                <span class="btn-detail" style="opacity: 0.5; cursor: not-allowed; pointer-events: none; white-space: nowrap; text-align: center; display: inline-block;">상세보기</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-data"><p>데이터 없음</p></div>
            <?php endif; ?>
        </div>
        
        <div class="action-buttons">
            <a href="stadiums.php" class="btn btn-secondary">목록으로</a>
        </div>
    </div>
    <?php

} else {

    $regionFilter = filter_input(INPUT_GET, 'region', FILTER_VALIDATE_INT);
    $stadiums = [];

    $apiBaseUrl = getApiBaseUrl(); // [수정] (3) 제거
    $url = $apiBaseUrl . '/stadiums/search.php';
    if ($regionFilter) {
        $url .= '?region_id=' . $regionFilter;
    }

    $result = callApi($url);
    if ($result['success']) {
        $json = json_decode($result['response'], true);
        $stadiums = $json['stadiums'] ?? [];
    }

 
    $stadiumIdMap = [];
    $stmt = $db->query("SELECT name, id FROM stadiums");
    while ($row = $stmt->fetch()) {
        $stadiumIdMap[trim($row['name'])] = $row['id'];
    }

    $regions = $db->query("SELECT * FROM regions ORDER BY name")->fetchAll();

    include '../includes/header.php';
    ?>
    
    <h2>KBO 야구 경기장 정보</h2>

    <div class="filter-section">
        <form method="GET" action="stadiums.php" class="filter-form">
            <label>
                지역:
                <select name="region">
                    <option value="">전체</option>
                    <?php foreach ($regions as $region): ?>
                        <option value="<?php echo $region['id']; ?>" <?php echo $regionFilter == $region['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($region['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">검색</button>
            <a href="stadiums.php" class="btn-reset">초기화</a>
        </form>
    </div>
    
    <div class="stadiums-grid">
        <?php if (empty($stadiums)): ?>
            <div class="no-data" style="grid-column: 1 / -1;">
                <p>데이터 없음</p>
            </div>
        <?php else: ?>
            <?php foreach ($stadiums as $stadium): 
                $stadiumName = trim($stadium['name'] ?? '');
                $linkId = $stadium['id'] ?? $stadiumIdMap[$stadiumName] ?? null;
            ?>
                <div class="stadium-card">
                    <h3><?php echo htmlspecialchars($stadiumName); ?></h3>
                    <div class="stadium-badges">
                        <span class="region-badge"><?php echo htmlspecialchars($stadium['region'] ?? ''); ?></span>
                    </div>
                    <div class="stadium-info">
                        <p><strong>위치:</strong> <?php echo htmlspecialchars($stadium['location'] ?? ''); ?></p>
                        <p><strong>수용 인원:</strong> <?php echo number_format($stadium['capacity'] ?? 0); ?>명</p>
                        <p><strong>총 경기:</strong> <?php echo number_format($stadium['total_matches'] ?? 0); ?>경기</p>
                    </div>
                    
                    <?php if ($linkId): ?>
                        <a href="stadiums.php?id=<?php echo $linkId; ?>" class="btn-detail">상세보기</a>
                    <?php else: ?>
                        <span class="btn-detail disabled">상세보기</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php
}

include '../includes/footer.php';
?>