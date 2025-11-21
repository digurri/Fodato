<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
$db = getDB();

$pageTitle = "KBO 야구 경기 상세";

$matchId = $_GET['id'] ?? 0;

if (!$matchId) {
    header('Location: matches.php');
    exit;
}

// 백엔드 API를 통해 경기 상세 정보 가져오기 (detail.php 사용)
$matchData = null;
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
// frontend/pages/에서 backend/로 가려면 3단계 위로 올라가야 함
$basePath = dirname(dirname(dirname($_SERVER['PHP_SELF'])));
$apiUrl = $protocol . '://' . $host . $basePath . '/backend/api/matches/detail.php?match_id=' . urlencode($matchId);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$apiResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($apiResponse !== false && $httpCode == 200) {
    $apiData = json_decode($apiResponse, true);
    if (isset($apiData['data']) && $apiData['data'] !== null) {
        $matchData = $apiData['data'];
    }
}

if (!$matchData) {
    header('Location: matches.php');
    exit;
}

// API 응답을 기존 코드와 호환되도록 변환
$match = [
    'id' => $matchData['match_id'],
    'match_id' => $matchData['match_id'],
    'match_date' => $matchData['date'],
    'match_time' => $matchData['time'],
    'status' => $matchData['status'],
    'home_team_id' => $matchData['home']['team_id'],
    'home_team' => $matchData['home']['name'],
    'home_score' => $matchData['home']['score'],
    'home_team_logo' => $matchData['home']['logo'] ?? null,
    'away_team_id' => $matchData['away']['team_id'],
    'away_team' => $matchData['away']['name'],
    'away_score' => $matchData['away']['score'],
    'away_team_logo' => $matchData['away']['logo'] ?? null,
    'stadium_name' => $matchData['stadium']['name'],
    'weather' => $matchData['stadium']['weather'] ?? null,
    'mvp' => $matchData['result']['mvp'] ?? null,
    'winning_hit' => $matchData['result']['winning_hit'] ?? null,
];

// API에 없는 추가 정보는 DB에서 가져오기 (경기장 상세 정보, 관중 수 등)
$stadiumInfoQuery = "
    SELECT 
        s.id as stadium_id,
        s.location,
        s.capacity,
        r.name as region_name
    FROM stadiums s
    JOIN regions r ON s.region_id = r.id
    WHERE s.name = :stadium_name
    LIMIT 1
";
$stadiumStmt = $db->prepare($stadiumInfoQuery);
$stadiumStmt->execute([':stadium_name' => $match['stadium_name']]);
$stadiumInfo = $stadiumStmt->fetch();

if ($stadiumInfo) {
    $match['stadium_id'] = $stadiumInfo['stadium_id'];
    $match['region_name'] = $stadiumInfo['region_name'] ?? '';
    $match['capacity'] = $stadiumInfo['capacity'] ?? 0;
    $match['location'] = $stadiumInfo['location'] ?? '';
} else {
    // 경기장 정보를 찾을 수 없는 경우 기본값 설정
    $match['stadium_id'] = null;
    $match['region_name'] = '';
    $match['capacity'] = 0;
    $match['location'] = '';
}

// 관중 수, 비고 등 추가 정보 가져오기
$matchStatQuery = "
    SELECT attendance
    FROM match_stat
    WHERE match_id = :match_id
    LIMIT 1
";
$matchStatStmt = $db->prepare($matchStatQuery);
$matchStatStmt->execute([':match_id' => $matchId]);
$matchStat = $matchStatStmt->fetch();

if ($matchStat) {
    $match['attendance'] = $matchStat['attendance'] ?? null;
    $match['notes'] = null; // notes 컬럼이 없으므로 null로 설정
} else {
    $match['attendance'] = null;
    $match['notes'] = null;
}

// game_winning_hit 컬럼 존재 여부 확인
$columnExists = false;
try {
    $checkQuery = "SHOW COLUMNS FROM match_stat LIKE 'game_winning_hit'";
    $checkStmt = $db->query($checkQuery);
    $columnExists = $checkStmt->fetch() !== false;
} catch (PDOException $e) {
    // 테이블이 없거나 오류 발생 시 무시
}

if ($columnExists) {
    $gameWinningHitQuery = "
        SELECT game_winning_hit
        FROM match_stat
        WHERE match_id = :match_id
        LIMIT 1
    ";
    $gameWinningHitStmt = $db->prepare($gameWinningHitQuery);
    $gameWinningHitStmt->execute([':match_id' => $matchId]);
    $gameWinningHit = $gameWinningHitStmt->fetch();
    if ($gameWinningHit) {
        $match['game_winning_hit'] = $gameWinningHit['game_winning_hit'];
    }
}

// 사용자 토큰 생성 또는 가져오기 (쿠키 사용) - 댓글 기능에서 사용하지 않지만 다른 곳에서 사용할 수 있음
if (!isset($_COOKIE['user_token'])) {
    $userToken = bin2hex(random_bytes(16));
    setcookie('user_token', $userToken, time() + (86400 * 365), '/'); // 1년간 유지
} else {
    $userToken = $_COOKIE['user_token'];
}

// 백엔드 API를 통해 팀 통계 비교 가져오기 (comparison.php 사용)
$teamComparison = null;
$comparisonApiUrl = $protocol . '://' . $host . $basePath . '/backend/api/matches/comparison.php?match_id=' . urlencode($matchId);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $comparisonApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$comparisonResponse = curl_exec($ch);
$comparisonHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($comparisonResponse !== false && $comparisonHttpCode == 200) {
    $comparisonData = json_decode($comparisonResponse, true);
    if (isset($comparisonData['data']) && $comparisonData['data'] !== null) {
        $teamComparison = $comparisonData['data'];
    }
}

// API 응답을 기존 코드와 호환되도록 변환
$homeTeamStats = null;
$awayTeamStats = null;

if ($teamComparison) {
    // 홈팀 통계 (백엔드 API 구조에 맞게: home, avg_batting, total_stolen_bases)
    $homeTeamStats = [
        'team_batting_avg' => $teamComparison['home']['avg_batting'] ?? null,
        'steal_success_rate' => isset($teamComparison['home']['stolen_base_success_rate']) 
            ? str_replace('%', '', $teamComparison['home']['stolen_base_success_rate']) 
            : null,
        'total_stolen_bases' => $teamComparison['home']['total_stolen_bases'] ?? 0,
        'total_steal_attempts' => null, // API에 없음
    ];
    
    // 원정팀 통계 (백엔드 API 구조에 맞게: away, avg_batting, total_stolen_bases)
    $awayTeamStats = [
        'team_batting_avg' => $teamComparison['away']['avg_batting'] ?? null,
        'steal_success_rate' => isset($teamComparison['away']['stolen_base_success_rate']) 
            ? str_replace('%', '', $teamComparison['away']['stolen_base_success_rate']) 
            : null,
        'total_stolen_bases' => $teamComparison['away']['total_stolen_bases'] ?? 0,
        'total_steal_attempts' => null, // API에 없음
    ];
}

include '../includes/header.php';
?>

<div class="match-detail">
    <div class="detail-header">
        <?php 
        // API의 status 필드 사용
        $statusLabel = '';
        $statusClass = '';
        if (isset($match['status'])) {
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
            // fallback: getMatchStatus 함수 사용
        $status = getMatchStatus($match['match_date'], $match['match_time']);
            $statusLabel = $status['label'];
            $statusClass = $status['class'];
        }
        ?>
        <span class="status-badge <?php echo $statusClass; ?>">
            <?php echo htmlspecialchars($statusLabel); ?>
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
                    <td><?php echo htmlspecialchars($match['match_time']); ?></td>
                </tr>
                <tr>
                    <th>경기장</th>
                    <td><?php echo htmlspecialchars($match['stadium_name']); ?></td>
                </tr>
                <tr>
                    <th>지역</th>
                    <td><?php echo !empty($match['region_name']) ? htmlspecialchars($match['region_name']) : '-'; ?></td>
                </tr>
                <tr>
                    <th>주소</th>
                    <td><?php echo !empty($match['location']) ? htmlspecialchars($match['location']) : '-'; ?></td>
                </tr>
                <tr>
                    <th>수용 인원</th>
                    <td><?php echo isset($match['capacity']) && $match['capacity'] > 0 ? number_format($match['capacity']) . '명' : '-'; ?></td>
                </tr>
            </table>
        </div>

        <div class="info-card">
            <h4>경기 통계</h4>
            <table>
                <?php if (isset($match['attendance']) && $match['attendance']): ?>
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
                <?php if (isset($match['notes']) && $match['notes']): ?>
                <tr>
                    <th>비고</th>
                    <td><?php echo nl2br(htmlspecialchars($match['notes'])); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>결승타</th>
                    <td><?php 
                        if (isset($match['winning_hit']) && $match['winning_hit']) {
                            echo htmlspecialchars($match['winning_hit']);
                        } elseif ($columnExists && isset($match['game_winning_hit']) && $match['game_winning_hit']) {
                            echo htmlspecialchars($match['game_winning_hit']);
                        } else {
                            echo '<span style="color: #999; font-style: italic;">정보 없음</span>';
                        }
                    ?></td>
                </tr>
                <?php if (isset($match['mvp']) && $match['mvp']): ?>
                <tr>
                    <th>MVP</th>
                    <td><?php echo htmlspecialchars($match['mvp']); ?></td>
                </tr>
                <?php endif; ?>
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
                            if ($homeTeamStats && isset($homeTeamStats['team_batting_avg']) && $homeTeamStats['team_batting_avg'] !== null) {
                                // avg_batting은 이미 number_format이 적용된 문자열
                                echo htmlspecialchars($homeTeamStats['team_batting_avg']);
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
                            if ($homeTeamStats && isset($homeTeamStats['steal_success_rate']) && $homeTeamStats['steal_success_rate'] !== null) {
                                $rate = (float)$homeTeamStats['steal_success_rate'];
                                if ($rate > 0) {
                                    echo number_format($rate, 1) . '%';
                                } else {
                                    echo '-';
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">도루 성공</span>
                        <span class="stat-value">
                            <?php 
                            if ($homeTeamStats && isset($homeTeamStats['total_stolen_bases']) && $homeTeamStats['total_stolen_bases'] > 0) {
                                echo number_format($homeTeamStats['total_stolen_bases']) . '회';
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
                            if ($awayTeamStats && isset($awayTeamStats['team_batting_avg']) && $awayTeamStats['team_batting_avg'] !== null) {
                                // avg_batting은 이미 number_format이 적용된 문자열
                                echo htmlspecialchars($awayTeamStats['team_batting_avg']);
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
                            if ($awayTeamStats && isset($awayTeamStats['steal_success_rate']) && $awayTeamStats['steal_success_rate'] !== null) {
                                $rate = (float)$awayTeamStats['steal_success_rate'];
                                if ($rate > 0) {
                                    echo number_format($rate, 1) . '%';
                                } else {
                                    echo '-';
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">도루 성공</span>
                        <span class="stat-value">
                            <?php 
                            if ($awayTeamStats && isset($awayTeamStats['total_stolen_bases']) && $awayTeamStats['total_stolen_bases'] > 0) {
                                echo number_format($awayTeamStats['total_stolen_bases']) . '회';
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
    <?php 
    // 댓글 기능을 별도 파일로 분리
    $_GET['match_id'] = $matchId; // comments.php에서 사용할 수 있도록 설정
    include 'comments.php';
    ?>

    <div class="action-buttons">
        <?php if (!empty($match['stadium_id'])): ?>
        <a href="stadiums.php?id=<?php echo $match['stadium_id']; ?>" class="btn">경기장 정보</a>
        <?php endif; ?>
        <a href="matches.php" class="btn btn-secondary">목록으로</a>
    </div>
</div>


<?php include '../includes/footer.php'; ?>



