# 데이터베이스 스키마 문서

KBO 야구 일정 관리 시스템의 데이터베이스 구조를 설명합니다.

## 데이터베이스 정보

- **데이터베이스명**: `sports_schedule`
- **문자셋**: `utf8mb4`
- **정렬 방식**: `utf8mb4_unicode_ci`
- **시스템 범위**: KBO 야구 리그 전용
- **지역 수**: 9개

---

## 테이블 구조

### 1. sports (종목) ⚠️ 제거 고려 대상

스포츠 종목 정보를 저장하는 테이블입니다.

**⚠️ 중요**:

- 현재 시스템은 **야구 종목만** 사용합니다.
- **야구만 사용한다면 이 테이블과 관련 `sport_id` 컬럼들을 제거할 수 있습니다.**
- 현재는 모든 쿼리에서 `WHERE sp.name = '야구'` 조건으로 필터링하고 있습니다.

| 컬럼명 | 타입    | 제약조건                    | 설명         |
| ------ | ------- | --------------------------- | ------------ |
| id     | INT     | PRIMARY KEY, AUTO_INCREMENT | 종목 고유 ID |
| name   | VARCHAR | NOT NULL                    | 종목명       |

**현재 사용 데이터:**

- 야구 (KBO 리그)

**실제 사용 컬럼:**

- `id`: JOIN 조건에 사용 (`matches.sport_id`, `teams.sport_id`, `stadiums.sport_id`)
- `name`: `WHERE sp.name = '야구'` 조건에 사용

**제거 시 고려사항:**

- `matches`, `teams`, `stadiums` 테이블의 `sport_id` 컬럼 제거 필요
- 모든 쿼리에서 `JOIN sports` 및 `WHERE sp.name = '야구'` 조건 제거 필요
- 코드 수정 범위가 큼 (모든 페이지 파일)

---

### 2. regions (지역)

지역 정보를 저장하는 테이블입니다.

**⚠️ 중요**: KBO 리그는 **9개 지역**을 사용합니다.

| 컬럼명 | 타입    | 제약조건                    | 설명         |
| ------ | ------- | --------------------------- | ------------ |
| id     | INT     | PRIMARY KEY, AUTO_INCREMENT | 지역 고유 ID |
| name   | VARCHAR | NOT NULL                    | 지역명       |

**KBO 9개 지역 예시:**

- 서울
- 인천
- 수원
- 부산
- 대구
- 광주
- 대전
- 고양
- 창원

(실제 지역명은 데이터베이스에 저장된 값에 따라 다를 수 있습니다)

---

### 3. stadiums (경기장)

KBO 야구 경기장 정보를 저장하는 테이블입니다.

| 컬럼명     | 타입      | 제약조건                    | 설명                               |
| ---------- | --------- | --------------------------- | ---------------------------------- |
| id         | INT       | PRIMARY KEY, AUTO_INCREMENT | 경기장 고유 ID                     |
| name       | VARCHAR   | NOT NULL                    | 경기장명                           |
| location   | VARCHAR   | NULL                        | 위치 정보                          |
| address    | VARCHAR   | NULL                        | 주소                               |
| capacity   | INT       | NULL                        | 수용 인원                          |
| region_id  | INT       | FOREIGN KEY → regions(id)   | 소속 지역 ID (9개 지역 중 하나)    |
| sport_id   | INT       | FOREIGN KEY → sports(id)    | 종목 ID (야구만 사용) ⚠️ 제거 고려 |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP   | 생성일시                           |

**관계:**

- `region_id` → `regions.id` (9개 지역 중 하나)
- `sport_id` → `sports.id` (야구만 사용) ⚠️ 제거 시 이 관계도 제거

**실제 사용 컬럼:**

- `id`, `name`, `location`, `address`, `capacity`, `region_id`, `sport_id`
- `created_at`: 코드에서 직접 사용하지 않지만 GROUP BY에 포함될 수 있음

**참고**: 모든 경기장은 야구 종목에 속하며, 9개 지역 중 하나에 소속됩니다.

---

### 4. teams (팀)

KBO 야구 팀 정보를 저장하는 테이블입니다.

| 컬럼명     | 타입      | 제약조건                    | 설명                               |
| ---------- | --------- | --------------------------- | ---------------------------------- |
| id         | INT       | PRIMARY KEY, AUTO_INCREMENT | 팀 고유 ID                         |
| name       | VARCHAR   | NOT NULL                    | 팀명                               |
| sport_id   | INT       | FOREIGN KEY → sports(id)    | 종목 ID (야구만 사용) ⚠️ 제거 고려 |
| region_id  | INT       | FOREIGN KEY → regions(id)   | 소속 지역 ID (9개 지역 중 하나)    |
| logo_url   | VARCHAR   | NULL                        | 로고 이미지 URL (선택)             |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP   | 생성일시                           |

**관계:**

- `sport_id` → `sports.id` (야구만 사용) ⚠️ 제거 시 이 관계도 제거
- `region_id` → `regions.id` (9개 지역 중 하나)

**실제 사용 컬럼:**

- `id`, `name`, `sport_id`, `region_id`, `logo_url`, `created_at`
- `logo_url`: GROUP BY 절에 포함되어 있음 (실제 사용 여부는 확인 필요)
- `created_at`: GROUP BY 절에 포함되어 있음

**참고**: 모든 팀은 야구 종목에 속하며, 9개 지역 중 하나에 소속됩니다.

---

### 5. matches (경기)

KBO 야구 경기 일정 정보를 저장하는 테이블입니다.

| 컬럼명       | 타입      | 제약조건                    | 설명                                    |
| ------------ | --------- | --------------------------- | --------------------------------------- |
| id           | INT       | PRIMARY KEY, AUTO_INCREMENT | 경기 고유 ID                            |
| match_date   | DATE      | NOT NULL                    | 경기 날짜                               |
| match_time   | TIME      | NOT NULL                    | 경기 시간                               |
| sport_id     | INT       | FOREIGN KEY → sports(id)    | 종목 ID (야구만 사용) ⚠️ 제거 고려      |
| stadium_id   | INT       | FOREIGN KEY → stadiums(id)  | 경기장 ID                               |
| home_team_id | INT       | FOREIGN KEY → teams(id)     | 홈팀 ID                                 |
| away_team_id | INT       | FOREIGN KEY → teams(id)     | 원정팀 ID                               |
| status       | VARCHAR   | NULL                        | 경기 상태 (예: 'scheduled', 'finished') |
| created_at   | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP   | 생성일시                                |

**관계:**

- `sport_id` → `sports.id` (야구만 사용) ⚠️ 제거 시 이 관계도 제거
- `stadium_id` → `stadiums.id`
- `home_team_id` → `teams.id`
- `away_team_id` → `teams.id`

**실제 사용 컬럼:**

- `id`, `match_date`, `match_time`, `sport_id`, `stadium_id`, `home_team_id`, `away_team_id`, `status`
- `created_at`: `SELECT m.*`로 조회되지만 직접 사용하지 않음

**참고**: 모든 경기는 야구 종목이며, 홈팀과 원정팀은 서로 다른 팀이어야 합니다.

---

### 6. match_stat (경기 통계)

경기 결과 및 통계 정보를 저장하는 테이블입니다.

| 컬럼명           | 타입      | 제약조건                                              | 설명               |
| ---------------- | --------- | ----------------------------------------------------- | ------------------ |
| match_id         | INT       | PRIMARY KEY, FOREIGN KEY → matches(id)                | 경기 ID            |
| home_score       | INT       | NULL                                                  | 홈팀 점수          |
| away_score       | INT       | NULL                                                  | 원정팀 점수        |
| attendance       | INT       | NULL                                                  | 관중 수            |
| weather          | VARCHAR   | NULL                                                  | 날씨 정보          |
| notes            | TEXT      | NULL                                                  | 비고               |
| game_winning_hit | VARCHAR   | NULL                                                  | 결승타 정보 (선택) |
| created_at       | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP                             | 생성일시           |
| updated_at       | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | 수정일시           |

**관계:**

- `match_id` → `matches.id` (1:1 관계)

**실제 사용 컬럼:**

- `match_id`, `home_score`, `away_score`, `attendance`, `weather`, `notes`
- `game_winning_hit`: 선택적 컬럼 (코드에서 존재 여부 확인 후 사용)

**참고:** `game_winning_hit` 컬럼은 선택적이며, 데이터베이스에 존재하지 않을 수 있습니다. 코드에서 `SHOW COLUMNS`로 존재 여부를 확인한 후 사용합니다.

---

### 7. players (선수)

선수 정보를 저장하는 테이블입니다.

| 컬럼명         | 타입         | 제약조건                                              | 설명                                                                                                       |
| -------------- | ------------ | ----------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| id             | INT          | PRIMARY KEY, AUTO_INCREMENT                           | 선수 고유 ID                                                                                               |
| name           | VARCHAR      | NOT NULL                                              | 선수명                                                                                                     |
| team_id        | INT          | FOREIGN KEY → teams(id)                               | 소속 팀 ID                                                                                                 |
| position       | VARCHAR      | NULL                                                  | 포지션 (예: '투수', '포수', '1루수', '2루수', '3루수', '유격수', '좌익수', '중견수', '우익수', '지명타자') |
| back_number    | INT          | NULL                                                  | 등번호                                                                                                     |
| birth_date     | DATE         | NULL                                                  | 생년월일                                                                                                   |
| height         | INT          | NULL                                                  | 키 (cm)                                                                                                    |
| weight         | INT          | NULL                                                  | 몸무게 (kg)                                                                                                |
| position_stat  | DECIMAL(5,3) | NULL                                                  | 포지션별 능력치 (투수: 평균자책점, 타자: 타율, 수비수: 수비율) (선택)                                      |
| steal_attempts | INT          | DEFAULT 0                                             | 도루 시도 횟수                                                                                             |
| steal_success  | INT          | DEFAULT 0                                             | 도루 성공 횟수                                                                                             |
| created_at     | TIMESTAMP    | DEFAULT CURRENT_TIMESTAMP                             | 생성일시                                                                                                   |
| updated_at     | TIMESTAMP    | DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | 수정일시                                                                                                   |

**관계:**

- `team_id` → `teams.id`

**실제 사용 컬럼:**

- `id`, `name`, `team_id`, `position`, `back_number`, `birth_date`, `height`, `weight`
- `position_stat`: 선택적 컬럼 (코드에서 `INFORMATION_SCHEMA.COLUMNS`로 존재 여부 확인)
- `steal_attempts`, `steal_success`: 도루 통계에 사용
- `created_at`, `updated_at`: `SELECT * FROM players`로 조회되지만 직접 사용하지 않음

**참고:**

- `position_stat` 컬럼은 선택적이며, 데이터베이스에 존재하지 않을 수 있습니다. 코드에서 `INFORMATION_SCHEMA.COLUMNS`로 존재 여부를 확인한 후 사용합니다.
- 포지션별 지표 의미:
  - 투수: 평균자책점 (낮을수록 좋음)
  - 타자 (1루수, 3루수, 좌익수, 중견수, 우익수, 지명타자): 타율 (높을수록 좋음)
  - 수비수 (포수, 2루수, 유격수): 수비율 (높을수록 좋음)

---

### 8. comments (댓글)

경기에 대한 댓글을 저장하는 테이블입니다.

| 컬럼명               | 타입      | 제약조건                                              | 설명                    |
| -------------------- | --------- | ----------------------------------------------------- | ----------------------- |
| id                   | INT       | PRIMARY KEY, AUTO_INCREMENT                           | 댓글 고유 ID            |
| match_id             | INT       | FOREIGN KEY → matches(id)                             | 경기 ID                 |
| content              | TEXT      | NOT NULL                                              | 댓글 내용               |
| user_token           | VARCHAR   | NOT NULL                                              | 사용자 토큰 (쿠키 기반) |
| supporting_team_id   | INT       | FOREIGN KEY → teams(id), NULL                         | 응원하는 팀 ID (선택)   |
| supporting_player_id | INT       | FOREIGN KEY → players(id), NULL                       | 응원하는 선수 ID (선택) |
| created_at           | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP                             | 생성일시                |
| updated_at           | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | 수정일시                |

**관계:**

- `match_id` → `matches.id`
- `supporting_team_id` → `teams.id` (선택)
- `supporting_player_id` → `players.id` (선택)

**실제 사용 컬럼:**

- `id`, `match_id`, `content`, `user_token`, `supporting_team_id`, `supporting_player_id`, `created_at`, `updated_at`
- `supporting_team_name`, `supporting_player_name`, `supporting_player_number`: JOIN으로 가져오는 별칭

**참고:** 사용자 인증은 쿠키 기반의 `user_token`을 사용합니다. `comment_action.php` 파일이 삭제되어 댓글 작성 기능은 현재 비활성화 상태입니다.

---

## 뷰 (Views)

### today_matches_view

오늘의 경기를 조회하기 위한 뷰입니다.

**사용 컬럼:**

- 경기 정보 (id, match_date, match_time)
- 종목명 (sport_name) ⚠️ 제거 고려
- 경기장 정보 (stadium_name)
- 지역명 (region_name)
- 홈팀/원정팀 (home_team, away_team)
- 경기 통계 (home_score, away_score, attendance)

**용도:**

- 오늘 날짜의 경기 목록을 빠르게 조회
- 홈페이지에서 오늘의 경기 표시
- 야구 종목만 필터링되어 표시됨 (현재는 `WHERE sport_name = '야구'` 조건 사용)

---

## 시즌 관리

**⚠️ 중요**: 현재 시스템에는 **별도의 시즌 테이블이 없습니다.**

시즌별 통계는 `YEAR(match_date)` 함수를 사용하여 경기 날짜에서 연도를 추출하여 처리합니다.

**현재 구현:**

```sql
-- 시즌별 통계 예시 (statistics.php에서 사용)
SELECT
    YEAR(m.match_date) as season,
    COUNT(m.id) as total_matches,
    ...
FROM matches m
GROUP BY YEAR(m.match_date)
```

**장점:**

- 별도 테이블 관리 불필요
- 경기 날짜만으로 시즌 구분 가능
- 데이터 중복 없음

**단점:**

- 시즌별 메타데이터(시즌명, 시작일, 종료일 등) 저장 불가
- 시즌별 추가 정보가 필요하면 별도 테이블 필요

---

## ER 다이어그램

```
┌──────────┐       ┌──────────┐       ┌──────────┐
│  sports  │       │ regions  │       │ stadiums │
│ (종목)    │       │ (지역)    │       │ (경기장)  │
├──────────┤       ├──────────┤       ├──────────┤
│ id (PK)  │       │ id (PK)  │       │ id (PK)  │
│ name     │       │ name     │       │ name     │
└──────────┘       └──────────┘       │ location │
                                      │ capacity │
┌──────────┐       ┌──────────┐       │region_id │───┐
│  teams   │       │ matches  │       │sport_id  │───┤
│ (팀)     │       │ (경기)    │       └──────────┘   │
├──────────┤       ├──────────┤                      │
│ id (PK)  │       │ id (PK)  │                      │
│ name     │       │date/time │                      │
│sport_id  │───┐   │sport_id  │───┐                  │
│region_id │───┤   │stadium_id│───┼──────────────────┘
└──────────┘   │   │home_team │───┤
               │   │away_team │───┘
┌──────────┐   │   └──────────┘
│ players  │   │
│ (선수)    │   │   ┌──────────┐
├──────────┤   │   │match_stat │
│ id (PK)  │   │   │ (경기통계) │
│ name     │   │   ├──────────┤
│team_id   │───┘   │match_id  │───┐
│position  │       │home_score│   │
│position_ │       │away_score│   │
│  stat    │       │attendance│   │
└──────────┘       └──────────┘   │
                                   │
┌──────────┐       ┌──────────┐   │
│ comments │       │          │   │
│ (댓글)    │       │          │   │
├──────────┤       │          │   │
│ id (PK)  │       │          │   │
│match_id  │───────┼──────────┼───┘
│content   │       │          │
│user_token│       │          │
│supporting│       │          │
│team_id   │───┐   │          │
│supporting│   │   │          │
│player_id │───┼───┘          │
└──────────┘   │              │
               │              │
               └──────────────┘
```

---

## 주요 관계

1. **sports** ↔ **stadiums**: 1:N (야구 종목에 여러 경기장)
2. **regions** ↔ **stadiums**: 1:N (9개 지역 중 한 지역에 여러 경기장)
3. **sports** ↔ **teams**: 1:N (야구 종목에 여러 팀)
4. **regions** ↔ **teams**: 1:N (9개 지역 중 한 지역에 여러 팀)
5. **teams** ↔ **players**: 1:N (한 팀에 여러 선수)
6. **sports** ↔ **matches**: 1:N (야구 종목에 여러 경기)
7. **stadiums** ↔ **matches**: 1:N (한 경기장에 여러 경기)
8. **teams** ↔ **matches**: 1:N (한 팀이 여러 경기에 참여)
9. **matches** ↔ **match_stat**: 1:1 (한 경기당 하나의 통계)
10. **matches** ↔ **comments**: 1:N (한 경기에 여러 댓글)
11. **teams** ↔ **comments**: 1:N (한 팀에 대한 여러 댓글)
12. **players** ↔ **comments**: 1:N (한 선수에 대한 여러 댓글)

**시스템 특징:**

- 모든 데이터는 **야구 종목**에 한정됨
- 지역은 **9개**로 제한됨
- 모든 쿼리에서 `WHERE sp.name = '야구'` 조건이 적용됨 (현재)
- 시즌은 별도 테이블 없이 `YEAR(match_date)`로 처리

**제거 가능한 요소:**

- `sports` 테이블: 야구만 사용하므로 제거 가능
- `matches.sport_id`, `teams.sport_id`, `stadiums.sport_id`: 제거 가능
- 모든 쿼리의 `JOIN sports` 및 `WHERE sp.name = '야구'` 조건: 제거 가능

---

## 인덱스 권장사항

성능 향상을 위해 다음 컬럼에 인덱스를 생성하는 것을 권장합니다:

```sql
-- matches 테이블
CREATE INDEX idx_match_date ON matches(match_date);
CREATE INDEX idx_match_sport ON matches(sport_id);
CREATE INDEX idx_match_stadium ON matches(stadium_id);

-- players 테이블
CREATE INDEX idx_player_team ON players(team_id);
CREATE INDEX idx_player_position ON players(position);

-- comments 테이블
CREATE INDEX idx_comment_match ON comments(match_id);
CREATE INDEX idx_comment_created ON comments(created_at);
```

---

## 데이터 타입 참고사항

- **날짜/시간**: `DATE`, `TIME`, `TIMESTAMP` 사용
- **문자열**: `VARCHAR` (길이 제한 필요시), `TEXT` (긴 텍스트)
- **숫자**: `INT` (정수), `DECIMAL(5,3)` (소수점 포함, 예: 타율 0.350)
- **문자셋**: 모든 텍스트 컬럼은 `utf8mb4` 사용 (한글 지원)

---

## 주의사항

1. **시스템 범위**:

   - 이 시스템은 **KBO 야구 리그 전용**입니다
   - 모든 쿼리에서 `WHERE sp.name = '야구'` 조건이 자동으로 적용됩니다
   - 다른 종목 데이터는 표시되지 않습니다

2. **지역 제한**:

   - 지역은 **9개**로 제한됩니다
   - KBO 리그의 실제 지역 구성에 맞춰 데이터가 저장되어야 합니다

3. **선택적 컬럼**: 일부 컬럼은 데이터베이스에 존재하지 않을 수 있습니다:

   - `match_stat.game_winning_hit`
   - `players.position_stat`

4. **외래키 제약조건**: 실제 데이터베이스에 외래키 제약조건이 설정되어 있지 않을 수 있습니다. 애플리케이션 레벨에서 관계를 관리합니다.

5. **데이터 무결성**:
   - 경기 날짜와 시간은 유효한 값이어야 합니다
   - 홈팀과 원정팀은 서로 다른 팀이어야 합니다
   - 선수의 등번호는 팀 내에서 고유해야 합니다 (선택적 제약)
   - 모든 경기, 팀, 경기장은 야구 종목에 속해야 합니다

---
