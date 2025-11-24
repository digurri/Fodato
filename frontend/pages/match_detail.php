<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../helpers/match_helper.php';
require_once '../helpers/api_helper.php';

$db = getDB();
$pageTitle = "KBO 야구 경기 상세";

// ID 검증
$matchId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$matchId) {
    header('Location: matches.php');
    exit;
}


$apiBaseUrl = getApiBaseUrl();
$matchData = null;
$result = callApi($apiBaseUrl . '/matches/detail.php?match_id=' . $matchId);

if ($result['success']) {
    $json = json_decode($result['response'], true);
    $matchData = $json['data'] ?? null;
}


if (!$matchData) {
    header('Location: matches.php');
    exit;
}

// API에 없는 정보(관중, 경기장 상세, 지역 등)만 추가 조회

$supplementQuery = "
    SELECT 
        s.location, s.capacity, r.name as region_name,
        ms.attendance, ms.winning_hitter_name, ms.winning_hit_description
    FROM matches m
    LEFT JOIN stadiums s ON m.stadium_id = s.id
    LEFT JOIN regions r ON s.region_id = r.id
    LEFT JOIN match_stat ms ON m.id = ms.match_id
    WHERE m.id = :match_id
    LIMIT 1
";
$stmt = $db->prepare($supplementQuery);
$stmt->execute([':match_id' => $matchId]);
$extraData = $stmt->fetch() ?: []; 

$match = [
    'id' => $matchData['match_id'],
    'date' => $matchData['date'],
    'time' => $matchData['time'],
    'status' => $matchData['status'], 
    'home_team' => $matchData['home']['name'],
    'home_score' => $matchData['home']['score'],
    'home_logo' => $matchData['home']['logo'] ?? '',
    'away_team' => $matchData['away']['name'],
    'away_score' => $matchData['away']['score'],
    'away_logo' => $matchData['away']['logo'] ?? '',
    'stadium_name' => $matchData['stadium']['name'],
    'weather' => $matchData['stadium']['weather'] ?? '',
    'mvp' => $matchData['result']['mvp'] ?? '',
    // API에 winning_hit 없으면 DB의 winning_hitter_name과 winning_hit_description 조합 사용
    'winning_hit' => $matchData['result']['winning_hit'] ?? (
        ($extraData['winning_hitter_name'] ?? '') 
        ? ($extraData['winning_hitter_name'] . ($extraData['winning_hit_description'] ? ' (' . $extraData['winning_hit_description'] . ')' : ''))
        : null
    ),
    
    // DB에서 가져온 추가 정보
    'location' => $extraData['location'] ?? '-',
    'capacity' => $extraData['capacity'] ?? 0,
    'region_name' => $extraData['region_name'] ?? '-',
    'attendance' => $extraData['attendance'] ?? 0,
    'stadium_id' => $matchData['stadium_id'] ?? null // API에 없으면 null
];

// 팀 비교 데이터
$comparisonData = null;
$compResult = callApi($apiBaseUrl . '/matches/comparison.php?match_id=' . $matchId);
if ($compResult['success']) {
    $compJson = json_decode($compResult['response'], true);
    $comparisonData = $compJson['data'] ?? null;
}

$homeStats = $comparisonData['home'] ?? [];
$awayStats = $comparisonData['away'] ?? [];

// 사용자 토큰 (댓글용)
if (!isset($_COOKIE['user_token'])) {
    setcookie('user_token', bin2hex(random_bytes(16)), time() + (86400 * 365), '/');
}

include '../includes/header.php';
?>

<div class="match-detail">
    <div class="detail-header">
        <?php 
        // 헬퍼 함수로 상태 표시
        $statusInfo = getMatchStatus($match['date'], $match['time']);
        ?>
        <span class="status-badge <?php echo $statusInfo['class']; ?>">
            <?php echo htmlspecialchars($statusInfo['label']); ?>
        </span>
    </div>

    <div class="match-score-section">
        <div class="team-section">
            <h3><?php echo htmlspecialchars($match['home_team']); ?></h3>
            <div class="score-large">
                <?php echo ($statusInfo['status'] === 'finished') ? $match['home_score'] : '-'; ?>
            </div>
        </div>
        <div class="vs-section">VS</div>
        <div class="team-section">
            <h3><?php echo htmlspecialchars($match['away_team']); ?></h3>
            <div class="score-large">
                <?php echo ($statusInfo['status'] === 'finished') ? $match['away_score'] : '-'; ?>
            </div>
        </div>
    </div>

    <div class="match-info-grid">
        <div class="info-card">
            <h4>경기 정보</h4>
            <table>
                <tr>
                    <th>날짜</th>
                    <td><?php echo date('Y년 m월 d일', strtotime($match['date'])); ?></td>
                </tr>
                <tr>
                    <th>시간</th>
                    <td><?php echo htmlspecialchars($match['time']); ?></td>
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
                    <td><?php echo htmlspecialchars($match['location']); ?></td>
                </tr>
                <tr>
                    <th>수용 인원</th>
                    <td><?php echo $match['capacity'] > 0 ? number_format($match['capacity']) . '명' : '-'; ?></td>
                </tr>
            </table>
        </div>

        <?php if ($statusInfo['status'] === 'finished'): ?>
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
                
                <tr>
                    <th>결승타</th>
                    <td><?php echo $match['winning_hit'] ? htmlspecialchars($match['winning_hit']) : '<span style="color:#999; font-style:italic;">정보 없음</span>'; ?></td>
                </tr>
                
                <?php if ($match['mvp']): ?>
                <tr>
                    <th>MVP</th>
                    <td><?php echo htmlspecialchars($match['mvp']); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php else: ?>
        <div class="info-card">
            <h4>경기 통계</h4>
            <p style="color: #999; font-style: italic; padding: 20px; text-align: center;">경기 완료 후 통계 정보가 표시됩니다.</p>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($statusInfo['status'] === 'finished'): ?>
    <div class="team-stats-comparison">
        <h3>팀별 성적 비교</h3>
        <div class="team-stats-grid">
            <div class="team-stat-card">
                <h4><?php echo htmlspecialchars($match['home_team']); ?></h4>
                <div class="stat-items">
                    <div class="stat-item">
                        <span class="stat-label">팀 타율</span>
                        <span class="stat-value"><?php echo $homeStats['avg_batting'] ?? '-'; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">도루 성공률</span>
                        <span class="stat-value">
                            <?php echo isset($homeStats['stolen_base_success_rate']) 
                                ? number_format((float)str_replace('%', '', $homeStats['stolen_base_success_rate']), 1) . '%' 
                                : '-'; ?>
                        </span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">도루 성공</span>
                        <span class="stat-value">
                            <?php echo !empty($homeStats['total_stolen_bases']) 
                                ? number_format($homeStats['total_stolen_bases']) . '회' 
                                : '-'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="team-stat-card">
                <h4><?php echo htmlspecialchars($match['away_team']); ?></h4>
                <div class="stat-items">
                    <div class="stat-item">
                        <span class="stat-label">팀 타율</span>
                        <span class="stat-value"><?php echo $awayStats['avg_batting'] ?? '-'; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">도루 성공률</span>
                        <span class="stat-value">
                            <?php echo isset($awayStats['stolen_base_success_rate']) 
                                ? number_format((float)str_replace('%', '', $awayStats['stolen_base_success_rate']), 1) . '%' 
                                : '-'; ?>
                        </span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">도루 성공</span>
                        <span class="stat-value">
                            <?php echo !empty($awayStats['total_stolen_bases']) 
                                ? number_format($awayStats['total_stolen_bases']) . '회' 
                                : '-'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="team-stats-comparison">
        <h3>팀별 성적 비교</h3>
        <p style="color: #999; font-style: italic; padding: 20px; text-align: center;">경기 완료 후 팀별 성적 비교 정보가 표시됩니다.</p>
    </div>
    <?php endif; ?>

    <?php include 'comments.php'; ?>
</div>

<?php include '../includes/footer.php'; ?>
