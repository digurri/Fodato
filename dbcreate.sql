-- --------------------------------------------------------
-- KBO 야구 일정 웹사이트 (dbcreate.sql)
-- --------------------------------------------------------

-- 1. 데이터베이스 선택
USE team05_db;

-- --------------------------------------------------------
-- 테이블 삭제 (외래 키 제약조건을 피하기 위해 생성의 역순으로 삭제)
-- --------------------------------------------------------
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS match_players;
DROP TABLE IF EXISTS match_stat;
DROP TABLE IF EXISTS matches;
DROP TABLE IF EXISTS players;
DROP TABLE IF EXISTS teams;
DROP TABLE IF EXISTS stadiums;
DROP TABLE IF EXISTS regions;
DROP TABLE IF EXISTS leagues;

-- --------------------------------------------------------
-- 1. 코어 정보 테이블 (리그, 지역)
-- --------------------------------------------------------

-- leagues 테이블 (시즌 정보)
CREATE TABLE leagues (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL COMMENT '시즌명 (예: KBO 2025)',
  year INT NOT NULL
);

-- regions 테이블 (지역 정보)
CREATE TABLE regions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL COMMENT '지역명 (예: 서울, 부산 등)'
);

-- stadiums 테이블 (경기장 정보)
CREATE TABLE stadiums (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(200) NOT NULL,
  location VARCHAR(200) NOT NULL COMMENT '경기장 주소',
  capacity INT NOT NULL COMMENT '최대 수용 인원',
  region_id INT NOT NULL,
  
  FOREIGN KEY (region_id) REFERENCES regions(id)
);

-- --------------------------------------------------------
-- 2. 팀 및 선수 정보 테이블
-- --------------------------------------------------------

-- teams 테이블 (팀 정보)
CREATE TABLE teams (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(200) NOT NULL,
  logo VARCHAR(500) COMMENT '팀 로고 이미지 경로',
  league_id INT NOT NULL,
  region_id INT NOT NULL COMMENT '연고지',
  
  FOREIGN KEY (league_id) REFERENCES leagues(id),
  FOREIGN KEY (region_id) REFERENCES regions(id)
);

-- players 테이블 (선수 정보)
CREATE TABLE players (
  id INT PRIMARY KEY AUTO_INCREMENT,
  team_id INT NOT NULL,
  uniform_number INT NOT NULL COMMENT '등번호',
  name VARCHAR(100) NOT NULL,
  position VARCHAR(50) NOT NULL COMMENT '주 포지션 (예: 투수, 포수)',
  birth_date DATE NOT NULL,
  height_cm INT,
  weight_kg INT,
  
  FOREIGN KEY (team_id) REFERENCES teams(id)
  -- 참고: 팀이 해체되어도 선수가 남을 수 있으므로 ON DELETE는 기본(RESTRICT)으로 둡니다.
);

-- --------------------------------------------------------
-- 3. 경기 및 결과 정보 테이블
-- --------------------------------------------------------

-- matches 테이블 (경기 일정)
CREATE TABLE matches (
  id INT PRIMARY KEY AUTO_INCREMENT,
  date DATE NOT NULL,
  time TIME NOT NULL,
  stadium_id INT NOT NULL,
  league_id INT NOT NULL,
  home_team_id INT NOT NULL,
  away_team_id INT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'scheduled' COMMENT '경기 상태 (scheduled, live, finished)',
  
  FOREIGN KEY (stadium_id) REFERENCES stadiums(id),
  FOREIGN KEY (league_id) REFERENCES leagues(id),
  FOREIGN KEY (home_team_id) REFERENCES teams(id),
  FOREIGN KEY (away_team_id) REFERENCES teams(id)
);

-- match_stat 테이블 (경기 통계)
CREATE TABLE match_stat (
  id INT PRIMARY KEY AUTO_INCREMENT,
  match_id INT NOT NULL UNIQUE,
  home_score INT NOT NULL DEFAULT 0,
  away_score INT NOT NULL DEFAULT 0,
  attendance INT NOT NULL DEFAULT 0 COMMENT '경기 통계에서 관중 수로 사용',
  weather VARCHAR(50) COMMENT '경기 당일 날씨 (예: 맑음, 흐림, 비)',
  highlights TEXT,
  mvp_player_name VARCHAR(100) COMMENT '경기 MVP 선수명',
  winning_hitter_name VARCHAR(100) COMMENT '결승타를 친 선수 이름',
  winning_hit_description VARCHAR(500) COMMENT '결승타 상황 상세 설명',
  
  FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
);

-- match_players 테이블 (경기별 선수 기록)
CREATE TABLE match_players (
  id INT PRIMARY KEY AUTO_INCREMENT,
  match_id INT NOT NULL,
  team_id INT NOT NULL,
  player_id INT NOT NULL,
  position VARCHAR(50) NOT NULL COMMENT '경기 중 포지션',
  batting_order INT COMMENT '타순 (1~9번)',
  is_starting BOOLEAN DEFAULT TRUE COMMENT '선발 여부',
  
  -- 통계용 필드
  innings_pitched DECIMAL(4,2) DEFAULT 0 COMMENT '투수용: 투구 이닝 수',
  earned_runs INT DEFAULT 0 COMMENT '투수용: 자책점',
  at_bats INT DEFAULT 0 COMMENT '타자용: 타수',
  hits INT DEFAULT 0 COMMENT '타자용: 안타',
  putouts INT DEFAULT 0 COMMENT '수비수용: 풋아웃',
  assists INT DEFAULT 0 COMMENT '수비수용: 어시스트 (보살)',
  errors INT DEFAULT 0 COMMENT '수비수용: 실책',
  
  FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  FOREIGN KEY (team_id) REFERENCES teams(id),
  FOREIGN KEY (player_id) REFERENCES players(id)
);

-- --------------------------------------------------------
-- 4. 사용자 인터랙션 정보 테이블 (댓글)
-- --------------------------------------------------------

-- comments 테이블 (익명 댓글)
CREATE TABLE comments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  match_id INT NOT NULL,
  content TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  session_id VARCHAR(100) NOT NULL COMMENT '세션 ID로 구현 (수정/삭제 권한용)',
  team_id INT NULL COMMENT '응원 팀 ID (선택 사항)',
  player_id INT NULL COMMENT '응원 선수 ID (선택 사항)',
  
  FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL
);