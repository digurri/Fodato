<!-- 경기 통계 페이지 -->

<?php
require_once '../config/database.php';
require_once '../helpers/api_helper.php';
$db = getDB();

$pageTitle = "KBO 야구 통계 분석";

$statisticsData = null;
$apiBaseUrl = getApiBaseUrl(3);
$result = callApi($apiBaseUrl . '/statistics/index.php');

if ($result['success']) {
    $apiData = json_decode($result['response'], true);
    if (isset($apiData['result']) && $apiData['result'] !== null) {
        $statisticsData = $apiData['result'];
    }
}

$stadiumStats = $statisticsData['stadiums'] ?? [];
$seasonStats = $statisticsData['leagues'] ?? [];
$regionStats = $statisticsData['regions'] ?? [];
$dailyStats = $statisticsData['dates'] ?? [];
$topAttendance = $statisticsData['matches'] ?? [];
$teamBattingAvg = $statisticsData['teams_ba'] ?? [];
$teamSteal = $statisticsData['teams_steal'] ?? [];
$positionPerformance = $statisticsData['positions'] ?? [];

include '../includes/header.php';
?>

<h2>KBO 야구 통계 분석</h2>

<div class="statistics-container">
    <!-- 경기장별 통계 -->
    <section class="stat-section">
        <h3>경기장별 통계 (경기 수 순위)</h3>
        <div class="table-responsive">
            <table class="stat-table">
                        <thead>
                    <tr>
                        <th>순위</th>
                        <th>경기장</th>
                        <th>지역</th>
                        <th>총 경기</th>
                        <th>최대 관중</th>
                        <th>평균 관중</th>
                        <th>총 관중</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stadiumStats)): ?>
                        <tr>
                            <td colspan="7" class="no-data">데이터 없음</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($stadiumStats as $stat): ?>
                            <tr>
                                <td><?php echo $stat['stadium_ranking'] ?? ''; ?></td>
                                <td><strong><?php echo htmlspecialchars($stat['stadium_name'] ?? ''); ?></strong></td>
                                <td><?php echo htmlspecialchars($stat['stadium_region'] ?? ''); ?></td>
                                <td><?php echo number_format($stat['total_matches'] ?? 0); ?></td>
                                <td><?php echo isset($stat['max_spectators']) && $stat['max_spectators'] > 0 ? number_format($stat['max_spectators']) : '-'; ?></td>
                                <td><?php echo isset($stat['avg_spectators']) && $stat['avg_spectators'] > 0 ? number_format($stat['avg_spectators'], 0) : '-'; ?></td>
                                <td><?php echo isset($stat['total_spectators']) && $stat['total_spectators'] > 0 ? number_format($stat['total_spectators']) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- 시즌별 통계 -->
    <section class="stat-section">
        <h3>시즌별 통계</h3>
        <div class="table-responsive">
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>시즌</th>
                        <th>총 경기</th>
                        <th>경기장 수</th>
                        <th>참가 팀 수</th>
                        <th>총 관중</th>
                        <th>평균 관중</th>
                        <th>최대 관중</th>
                        <th>최소 관중</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($seasonStats)): ?>
                        <tr>
                            <td colspan="8" class="no-data">데이터 없음</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($seasonStats as $stat): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($stat['season'] ?? ''); ?></strong></td>
                                <td><?php echo number_format($stat['league_matches'] ?? 0); ?></td>
                                <td><?php echo number_format($stat['league_stadiums'] ?? 0); ?></td>
                                <td><?php echo isset($stat['league_teams']) && $stat['league_teams'] > 0 ? number_format($stat['league_teams']) : '-'; ?></td>
                                <td><?php echo isset($stat['league_total_spectators']) && $stat['league_total_spectators'] > 0 ? number_format($stat['league_total_spectators']) : '-'; ?></td>
                                <td><?php echo isset($stat['league_avg_spectators']) && $stat['league_avg_spectators'] > 0 ? number_format($stat['league_avg_spectators'], 0) : '-'; ?></td>
                                <td><?php echo isset($stat['league_max_spectators']) && $stat['league_max_spectators'] > 0 ? number_format($stat['league_max_spectators']) : '-'; ?></td>
                                <td>-</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- 지역별 통계 -->
    <section class="stat-section">
        <h3>지역별 통계</h3>
        <div class="table-responsive">
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>지역</th>
                        <th>경기장 수</th>
                        <th>총 경기</th>
                        <th>총 관중</th>
                        <th>평균 관중</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($regionStats)): ?>
                        <tr>
                            <td colspan="5" class="no-data">데이터 없음</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($regionStats as $stat): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($stat['region_name'] ?? ''); ?></strong></td>
                                <td><?php echo number_format($stat['region_stadium_count'] ?? 0); ?></td>
                                <td><?php echo number_format($stat['region_matches'] ?? 0); ?></td>
                                <td><?php echo isset($stat['region_total_spectators']) && $stat['region_total_spectators'] > 0 ? number_format($stat['region_total_spectators']) : '-'; ?></td>
                                <td><?php echo isset($stat['region_avg_spectators']) && $stat['region_avg_spectators'] > 0 ? number_format($stat['region_avg_spectators'], 0) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- 날짜별 통계 (WINDOWING) -->
    <section class="stat-section">
        <h3>최근 날짜별 경기 통계</h3>
        <div class="table-responsive">
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>날짜</th>
                        <th>경기 수</th>
                        <th>총 관중</th>
                        <th>평균 관중</th>
                        <th>경기 수 순위</th>
                        <th>관중 수 순위</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dailyStats)): ?>
                        <tr>
                            <td colspan="6" class="no-data">데이터 없음</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dailyStats as $stat): ?>
                            <tr>
                                <td><?php echo isset($stat['date']) ? date('Y-m-d (D)', strtotime($stat['date'])) : '-'; ?></td>
                                <td><?php echo number_format($stat['daily_matches'] ?? 0); ?></td>
                                <td><?php echo isset($stat['daily_total_spectators']) && $stat['daily_total_spectators'] > 0 ? number_format($stat['daily_total_spectators']) : '-'; ?></td>
                                <td><?php echo isset($stat['daily_avg_spectators']) && $stat['daily_avg_spectators'] > 0 ? number_format($stat['daily_avg_spectators'], 0) : '-'; ?></td>
                                <td><span class="rank-badge"><?php echo $stat['daily_matches_ranking'] ?? '-'; ?>위</span></td>
                                <td><span class="rank-badge"><?php echo $stat['daily_spectators_ranking'] ?? '-'; ?>위</span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- 최고 관중 수 경기 TOP 10 -->
    <section class="stat-section">
        <h3>최고 관중 수 경기 TOP 10</h3>
        <div class="table-responsive">
            <table class="stat-table">
                        <thead>
                    <tr>
                        <th>순위</th>
                        <th>날짜</th>
                        <th>경기장</th>
                        <th>경기</th>
                        <th>관중 수</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topAttendance)): ?>
                        <tr>
                            <td colspan="5" class="no-data">데이터 없음</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topAttendance as $match): ?>
                            <tr>
                                <td><span class="rank-badge rank-<?php echo $match['match_ranking'] ?? ''; ?>">
                                    <?php echo $match['match_ranking'] ?? ''; ?>위
                                </span></td>
                                <td><?php echo isset($match['match_date']) ? date('Y-m-d', strtotime($match['match_date'])) : '-'; ?></td>
                                <td><?php echo htmlspecialchars($match['match_stadium'] ?? ''); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($match['match_teams'] ?? ''); ?>
                                </td>
                                <td><strong><?php echo isset($match['match_spectators']) && $match['match_spectators'] > 0 ? number_format($match['match_spectators']) : '-'; ?>명</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- 팀별 타율 통계 -->
    <section class="stat-section">
        <h3>팀별 타율 통계</h3>
        <div class="table-responsive">
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>순위</th>
                        <th>팀명</th>
                        <th>지역</th>
                        <th>타자 수</th>
                        <th>팀 타율</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($teamBattingAvg)): ?>
                        <tr>
                            <td colspan="5" class="no-data">데이터 없음</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($teamBattingAvg as $team): ?>
                            <tr>
                                <td><span class="rank-badge rank-<?php echo $team['ba_ranking'] ?? ''; ?>"><?php echo $team['ba_ranking'] ?? ''; ?>위</span></td>
                                <td><strong><?php echo htmlspecialchars($team['team_name'] ?? ''); ?></strong></td>
                                <td><?php echo htmlspecialchars($team['team_region'] ?? ''); ?></td>
                                <td><?php echo number_format($team['team_hitter'] ?? 0); ?>명</td>
                                <td><strong class="stat-highlight"><?php echo isset($team['team_ba']) && $team['team_ba'] ? number_format((float)$team['team_ba'], 3) : '-'; ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- 팀별 도루 성공률 통계 -->
    <section class="stat-section">
        <h3>팀별 도루 성공률</h3>
        <div class="table-responsive">
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>순위</th>
                        <th>팀명</th>
                        <th>지역</th>
                        <th>시도</th>
                        <th>성공</th>
                        <th>성공률</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($teamSteal)): ?>
                        <tr>
                            <td colspan="6" class="no-data">데이터 없음</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($teamSteal as $team): ?>
                            <tr>
                                <td><span class="rank-badge rank-<?php echo $team['steal_ranking'] ?? ''; ?>"><?php echo $team['steal_ranking'] ?? ''; ?>위</span></td>
                                <td><strong><?php echo htmlspecialchars($team['team_name'] ?? ''); ?></strong></td>
                                <td><?php echo htmlspecialchars($team['team_region'] ?? ''); ?></td>
                                <td><?php echo number_format($team['steal_try'] ?? 0); ?>회</td>
                                <td><?php echo number_format($team['steal_success'] ?? 0); ?>회</td>
                                <td><strong class="stat-highlight"><?php echo isset($team['steal_rate']) ? htmlspecialchars($team['steal_rate']) : '-'; ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- 포지션별 퍼포먼스 요약 -->
    <section class="stat-section">
        <h3>포지션별 퍼포먼스 요약</h3>
        <div class="table-responsive">
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>포지션</th>
                        <th>지표 유형</th>
                        <th>선수 수</th>
                        <th>평균</th>
                        <th>최고</th>
                        <th>최저</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($positionPerformance)): ?>
                        <tr>
                            <td colspan="6" class="no-data">데이터 없음</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($positionPerformance as $perf): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($perf['position'] ?? ''); ?></strong></td>
                                <td><span class="stat-type-badge"><?php echo htmlspecialchars($perf['indicator'] ?? ''); ?></span></td>
                                <td><?php echo number_format($perf['players'] ?? 0); ?>명</td>
                                <td><strong><?php 
                                    $position = $perf['position'] ?? '';
                                    if ($position === '투수') {
                                        echo isset($perf['avg_perform']) ? number_format((float)$perf['avg_perform'], 2) : '-';
                                    } else {
                                        echo isset($perf['avg_perform']) ? number_format((float)$perf['avg_perform'], 3) : '-';
                                    }
                                ?></strong></td>
                                <td class="stat-max"><?php 
                                    if ($position === '투수') {
                                        echo isset($perf['best_perform']) ? number_format((float)$perf['best_perform'], 2) : '-';
                                    } else {
                                        echo isset($perf['best_perform']) ? number_format((float)$perf['best_perform'], 3) : '-';
                                    }
                                ?></td>
                                <td class="stat-min"><?php 
                                    if ($position === '투수') {
                                        echo isset($perf['worst_perform']) ? number_format((float)$perf['worst_perform'], 2) : '-';
                                    } else {
                                        echo isset($perf['worst_perform']) ? number_format((float)$perf['worst_perform'], 3) : '-';
                                    }
                                ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>


