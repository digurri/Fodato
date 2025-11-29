<!-- written by 2171090 SeungHyeon Lee -->
<!-- 경기 통계 페이지 -->

<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../helpers/api_helper.php';

$db = getDB();
$pageTitle = "KBO 야구 통계 분석";

// API 호출 (기본 통계 데이터)
$apiData = [];
$result = callApi(getApiBaseUrl() . '/statistics/index.php');

if ($result['success']) {
    $json = json_decode($result['response'], true);
    $apiData = $json['result'] ?? [];
}

// 뷰 사용하기 편하게 변수 분리
$stadiumStats = $apiData['stadiums'] ?? [];
$seasonStats  = $apiData['leagues'] ?? [];
$regionStats  = $apiData['regions'] ?? [];
$dailyStats   = $apiData['dates'] ?? [];
$topMatches   = $apiData['matches'] ?? [];

// 복잡한 통계는 DB 쿼리로 직접 계산하여 성능 최적화
// 팀별 타율 랭킹 (경기 완료된 데이터만)
$sql = "
    SELECT 
        RANK() OVER (ORDER BY team_ba DESC) as ba_ranking,
        t.name as team_name,
        r.name as team_region,
        COUNT(DISTINCT mp.player_id) as team_hitter,
        ROUND(SUM(mp.hits) / NULLIF(SUM(mp.at_bats), 0), 3) as team_ba
    FROM teams t
    JOIN regions r ON t.region_id = r.id
    JOIN match_players mp ON mp.team_id = t.id
    JOIN matches m ON mp.match_id = m.id
    WHERE m.status = 'finished'
    GROUP BY t.id, t.name, r.name
    ORDER BY ba_ranking ASC
";
$teamBattingAvg = $db->query($sql)->fetchAll();

// 팀별 도루 성공률 (경기 완료된 데이터만)
$sql = "
    SELECT 
        RANK() OVER (ORDER BY (SUM(mp.stolen_bases) / NULLIF(SUM(mp.stolen_base_tries), 0)) DESC) as steal_ranking,
        t.name as team_name,
        r.name as team_region,
        SUM(mp.stolen_base_tries) as steal_try,
        SUM(mp.stolen_bases) as steal_success,
        ROUND((SUM(mp.stolen_bases) / NULLIF(SUM(mp.stolen_base_tries), 0)) * 100, 1) as steal_rate
    FROM teams t
    JOIN regions r ON t.region_id = r.id
    JOIN match_players mp ON mp.team_id = t.id
    JOIN matches m ON mp.match_id = m.id
    WHERE m.status = 'finished'
    GROUP BY t.id, t.name, r.name
    ORDER BY steal_ranking ASC
";
$teamSteal = $db->query($sql)->fetchAll();

// 포지션별 퍼포먼스 요약 (경기 완료된 데이터만)
$sql = "
    SELECT 
        mp.position,
        CASE 
            WHEN mp.position = '투수' THEN '평균자책점'
            WHEN mp.position = '지명타자' THEN '타율'
            ELSE '수비율' 
        END as indicator,
        COUNT(DISTINCT mp.player_id) as players,
        -- 평균 퍼포먼스 계산 (복잡한 로직은 SQL 내부 처리)
        ROUND(AVG(
            CASE 
                WHEN mp.position = '투수' AND mp.innings_pitched > 0 THEN mp.earned_runs / mp.innings_pitched
                WHEN mp.position = '지명타자' AND mp.at_bats > 0 THEN mp.hits / mp.at_bats
                WHEN mp.position NOT IN ('투수', '지명타자') THEN 
                    COALESCE((mp.putouts + mp.assists) / NULLIF((mp.putouts + mp.assists + mp.errors), 0), 0)
                ELSE NULL 
            END
        ), 3) as avg_perform
    FROM match_players mp
    JOIN matches m ON mp.match_id = m.id
    WHERE m.status = 'finished'
    GROUP BY mp.position
    ORDER BY mp.position
";
$positionPerformance = $db->query($sql)->fetchAll();

include '../includes/header.php';
?>

<h2>KBO 야구 통계 분석</h2>

<div class="statistics-container">
    
    <section class="stat-section">
        <h3>경기장별 통계 (경기 수 순위)</h3>
        <div class="table-responsive">
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>순위</th><th>경기장</th><th>지역</th>
                        <th>총 경기</th><th>최대 관중</th><th>평균 관중</th><th>총 관중</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stadiumStats)): ?>
                        <tr><td colspan="7" class="no-data">데이터 없음</td></tr>
                    <?php else: ?>
                        <?php foreach ($stadiumStats as $stat): ?>
                            <tr>
                                <td><?php echo $stat['stadium_ranking']; ?></td>
                                <td><strong><?php echo htmlspecialchars($stat['stadium_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($stat['stadium_region']); ?></td>
                                <td><?php echo number_format($stat['total_matches']); ?></td>
                                <td><?php echo number_format($stat['max_spectators']); ?></td>
                                <td><?php echo number_format($stat['avg_spectators']); ?></td>
                                <td><?php echo number_format($stat['total_spectators']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="stat-section">
        <h3>시즌별 통계</h3>
        <div class="table-responsive">
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>시즌</th><th>총 경기</th><th>경기장 수</th><th>참가 팀</th>
                        <th>총 관중</th><th>평균 관중</th><th>최대 관중</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($seasonStats)): ?>
                        <tr><td colspan="7" class="no-data">데이터 없음</td></tr>
                    <?php else: ?>
                        <?php foreach ($seasonStats as $stat): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($stat['season']); ?></strong></td>
                                <td><?php echo number_format($stat['league_matches']); ?></td>
                                <td><?php echo number_format($stat['league_stadiums']); ?></td>
                                <td><?php echo number_format($stat['league_teams']); ?></td>
                                <td><?php echo number_format($stat['league_total_spectators']); ?></td>
                                <td><?php echo number_format($stat['league_avg_spectators']); ?></td>
                                <td><?php echo number_format($stat['league_max_spectators']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="stat-section">
        <h3>지역별 통계</h3>
        <div class="table-responsive">
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>지역</th><th>경기장 수</th><th>총 경기</th><th>총 관중</th><th>평균 관중</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($regionStats)): ?>
                        <tr><td colspan="5" class="no-data">데이터 없음</td></tr>
                    <?php else: ?>
                        <?php foreach ($regionStats as $stat): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($stat['region_name']); ?></strong></td>
                                <td><?php echo number_format($stat['region_stadium_count']); ?></td>
                                <td><?php echo number_format($stat['region_matches']); ?></td>
                                <td><?php echo number_format($stat['region_total_spectators']); ?></td>
                                <td><?php echo number_format($stat['region_avg_spectators']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="stat-section">
        <h3>최근 날짜별 경기 통계</h3>
        <div class="table-responsive">
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>날짜</th><th>경기 수</th><th>총 관중</th><th>평균 관중</th><th>순위(경기/관중)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dailyStats)): ?>
                        <tr><td colspan="5" class="no-data">데이터 없음</td></tr>
                    <?php else: ?>
                        <?php foreach ($dailyStats as $stat): 
                            $dateStr = isset($stat['date']) ? date('Y-m-d (D)', strtotime($stat['date'])) : '-';
                        ?>
                            <tr>
                                <td><?php echo $dateStr; ?></td>
                                <td><?php echo number_format($stat['daily_matches']); ?></td>
                                <td><?php echo number_format($stat['daily_total_spectators']); ?></td>
                                <td><?php echo number_format($stat['daily_avg_spectators']); ?></td>
                                <td>
                                    <span class="rank-badge"><?php echo $stat['daily_matches_ranking']; ?>위</span> / 
                                    <span class="rank-badge"><?php echo $stat['daily_spectators_ranking']; ?>위</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="stat-section">
        <h3>최고 관중 수 경기 TOP 10</h3>
        <div class="table-responsive">
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>순위</th><th>날짜</th><th>경기장</th><th>경기</th><th>관중 수</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topMatches)): ?>
                        <tr><td colspan="5" class="no-data">데이터 없음</td></tr>
                    <?php else: ?>
                        <?php foreach ($topMatches as $match): ?>
                            <tr>
                                <td><span class="rank-badge"><?php echo $match['match_ranking']; ?>위</span></td>
                                <td><?php echo date('Y-m-d', strtotime($match['match_date'])); ?></td>
                                <td><?php echo htmlspecialchars($match['match_stadium']); ?></td>
                                <td><?php echo htmlspecialchars($match['match_teams']); ?></td>
                                <td><strong><?php echo number_format($match['match_spectators']); ?>명</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="stat-section">
        <h3>팀별 타율 통계</h3>
        <div class="table-responsive">
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>순위</th><th>팀명</th><th>지역</th><th>타자 수</th><th>팀 타율</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teamBattingAvg as $team): ?>
                        <tr>
                            <td><span class="rank-badge"><?php echo $team['ba_ranking']; ?>위</span></td>
                            <td><strong><?php echo htmlspecialchars($team['team_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($team['team_region']); ?></td>
                            <td><?php echo number_format($team['team_hitter']); ?>명</td>
                            <td><strong class="stat-highlight"><?php echo number_format((float)$team['team_ba'], 3); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="stat-section">
        <h3>팀별 도루 성공률</h3>
        <div class="table-responsive">
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>순위</th><th>팀명</th><th>지역</th><th>시도/성공</th><th>성공률</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teamSteal as $team): ?>
                        <tr>
                            <td><span class="rank-badge"><?php echo $team['steal_ranking']; ?>위</span></td>
                            <td><strong><?php echo htmlspecialchars($team['team_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($team['team_region']); ?></td>
                            <td><?php echo $team['steal_try'] . ' / ' . $team['steal_success']; ?></td>
                            <td><strong class="stat-highlight"><?php echo $team['steal_rate']; ?>%</strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="stat-section">
        <h3>포지션별 퍼포먼스</h3>
        <div class="table-responsive">
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>포지션</th><th>지표</th><th>선수 수</th><th>평균 기록</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($positionPerformance as $perf): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($perf['position']); ?></strong></td>
                            <td><span class="stat-type-badge"><?php echo htmlspecialchars($perf['indicator']); ?></span></td>
                            <td><?php echo number_format($perf['players']); ?>명</td>
                            <td><strong><?php echo number_format((float)$perf['avg_perform'], 3); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>