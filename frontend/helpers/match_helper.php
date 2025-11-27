<!-- LeeSeungHyeon -->
<?php
/**
 * 현재 날짜를 기준으로 경기 상태를 계산
 * @param string $matchDate 경기 날짜 (Y-m-d 형식)
 * @param string $matchTime 경기 시간 (H:i:s or H:i)
 * @return array 
 */

function getMatchStatus($matchDate, $matchTime = '00:00:00') {
    
    // 변환 함수 사용
    $matchTimestamp = strtotime("$matchDate $matchTime");
    $now = time();

    // 예정
    if ($now<$matchTimestamp){
        return [
            'status' => 'scheduled',
            'label' => '예정',
            'class' => 'status-scheduled'
        ];
    }

    // 그 외는 완료 처리

    return [
        'status' => 'finished',
        'lable' => '완료',
        'class' => 'status-finished'
    ];

}
?>