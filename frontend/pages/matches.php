<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
$db = getDB();

$pageTitle = "KBO 야구 경기 일정";

// 필터 파라미터
$regionFilter = $_GET['region'] ?? '';
$monthFilter = $_GET['month'] ?? date('Y-m');

// 백엔드 API를 통해 경기 목록 가져오기 (list.php 사용)
$matches = [];
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$basePath = dirname(dirname($_SERVER['PHP_SELF']));
$baseApiUrl = $protocol . '://' . $host . $basePath . '/backend/api/matches/list.php';

// 월 필터링: 해당 월의 모든 날짜에 대해 API 호출
$startDate = date('Y-m-01', strtotime($monthFilter . '-01'));
$endDate = date('Y-m-t', strtotime($monthFilter . '-01')); // 해당 월의 마지막 날

$currentDate = $startDate;
while ($currentDate <= $endDate) {
    $apiUrl = $baseApiUrl . '?date=' . urlencode($currentDate);
    if ($regionFilter) {
        $apiUrl .= '&region_id=' . urlencode($regionFilter);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $apiResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($apiResponse !== false && $httpCode == 200) {
        $apiData = json_decode($apiResponse, true);
        if (isset($apiData['data']) && is_array($apiData['data'])) {
            foreach ($apiData['data'] as $match) {
                // date 필드를 match_date로 변환하여 기존 코드와 호환
                $match['match_date'] = $match['date'] ?? $currentDate;
                $match['match_time'] = $match['time'] ?? '';
                $match['id'] = $match['match_id'] ?? '';
                $match['stadium_name'] = $match['stadium'] ?? '';
                $match['region_name'] = ''; // list.php에는 region_name이 없음
                $matches[] = $match;
            }
        }
    }
    
    $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
}

// 날짜 내림차순, 시간 오름차순 정렬
usort($matches, function($a, $b) {
    $dateCompare = strcmp($b['match_date'], $a['match_date']);
    if ($dateCompare !== 0) {
        return $dateCompare;
    }
    return strcmp($a['match_time'], $b['match_time']);
});

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
                    <div class="time"><?php echo htmlspecialchars($match['match_time'] ?? ''); ?></div>
                    <?php if (!empty($match['region_name'])): ?>
                        <span class="region-badge"><?php echo htmlspecialchars($match['region_name']); ?></span>
                    <?php endif; ?>
                    <?php 
                    // status 필드가 있으면 사용, 없으면 getMatchStatus 함수 사용
                    if (isset($match['status'])) {
                        $statusLabel = '';
                        $statusClass = '';
                        switch($match['status']) {
                            case 'finished':
                            case '완료':
                                $statusLabel = '완료';
                                $statusClass = 'status-finished';
                                break;
                            case 'scheduled':
                            case '예정':
                                $statusLabel = '예정';
                                $statusClass = 'status-scheduled';
                                break;
                            default:
                                $statusLabel = $match['status'];
                                $statusClass = 'status-scheduled';
                        }
                    } else {
                        $status = getMatchStatus($match['match_date'], $match['match_time']);
                        $statusLabel = $status['label'];
                        $statusClass = $status['class'];
                    }
                    ?>
                    <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                </div>
                <div class="match-teams-col">
                    <div class="team-row">
                        <span class="team-name"><?php echo htmlspecialchars($match['home_team'] ?? ''); ?></span>
                        <?php if (isset($match['home_score']) && $match['home_score'] !== null): ?>
                            <span class="score"><?php echo $match['home_score']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="team-row">
                        <span class="team-name"><?php echo htmlspecialchars($match['away_team'] ?? ''); ?></span>
                        <?php if (isset($match['away_score']) && $match['away_score'] !== null): ?>
                            <span class="score"><?php echo $match['away_score']; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="match-info-col">
                    <p><strong><?php echo htmlspecialchars($match['stadium_name'] ?? ''); ?></strong></p>
                    <?php if (!empty($match['region_name'])): ?>
                        <p><?php echo htmlspecialchars($match['region_name']); ?></p>
                    <?php endif; ?>
                    <?php if (isset($match['attendance']) && $match['attendance'] > 0): ?>
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


