-- Seed: MLB teams (30), 2 entries, pool weeks from 2026-03-30, sample games for week 1
SET NAMES utf8mb4;

INSERT INTO teams (mlb_id, city, name, abbreviation, league, division) VALUES
('108','Los Angeles','Angels','LAA','AL','West'),
('109','Arizona','Diamondbacks','AZ','NL','West'),
('110','Baltimore','Orioles','BAL','AL','East'),
('111','Boston','Red Sox','BOS','AL','East'),
('112','Chicago','Cubs','CHC','NL','Central'),
('113','Cincinnati','Reds','CIN','NL','Central'),
('114','Cleveland','Guardians','CLE','AL','Central'),
('115','Colorado','Rockies','COL','NL','West'),
('116','Detroit','Tigers','DET','AL','Central'),
('117','Houston','Astros','HOU','AL','West'),
('118','Kansas City','Royals','KC','AL','Central'),
('119','Los Angeles','Dodgers','LAD','NL','West'),
('120','Washington','Nationals','WSH','NL','East'),
('121','New York','Mets','NYM','NL','East'),
('133','Oakland','Athletics','OAK','AL','West'),
('134','Pittsburgh','Pirates','PIT','NL','Central'),
('135','San Diego','Padres','SD','NL','West'),
('136','Seattle','Mariners','SEA','AL','West'),
('137','San Francisco','Giants','SF','NL','West'),
('138','St. Louis','Cardinals','STL','NL','Central'),
('139','Tampa Bay','Rays','TB','AL','East'),
('140','Texas','Rangers','TEX','AL','West'),
('141','Toronto','Blue Jays','TOR','AL','East'),
('142','Minnesota','Twins','MIN','AL','Central'),
('143','Philadelphia','Phillies','PHI','NL','East'),
('144','Atlanta','Braves','ATL','NL','East'),
('145','Chicago','White Sox','CWS','AL','Central'),
('146','Miami','Marlins','MIA','NL','East'),
('147','New York','Yankees','NYY','AL','East'),
('158','Milwaukee','Brewers','MIL','NL','Central');

INSERT INTO entries (label) VALUES ('Entry 1'), ('Entry 2');

-- Pool restarts at Week 6: Monday 2026-04-27 (weeks 1–5 omitted). Mon–Sun through Oct 11.
INSERT INTO pool_weeks (week_number, week_start_local, week_end_local, week_label, deadline_datetime, status) VALUES
(6, '2026-04-27', '2026-05-03', 'Week 6 (Apr 27 – May 3)', '2026-04-27 19:05:00', 'active'),
(7, '2026-05-04', '2026-05-10', 'Week 7 (May 4 – May 10)', '2026-05-04 19:05:00', 'upcoming'),
(8, '2026-05-11', '2026-05-17', 'Week 8 (May 11 – May 17)', '2026-05-11 19:05:00', 'upcoming'),
(9, '2026-05-18', '2026-05-24', 'Week 9 (May 18 – May 24)', '2026-05-18 19:05:00', 'upcoming'),
(10, '2026-05-25', '2026-05-31', 'Week 10 (May 25 – May 31)', '2026-05-25 19:05:00', 'upcoming'),
(11, '2026-06-01', '2026-06-07', 'Week 11 (Jun 1 – Jun 7)', '2026-06-01 19:05:00', 'upcoming'),
(12, '2026-06-08', '2026-06-14', 'Week 12 (Jun 8 – Jun 14)', '2026-06-08 19:05:00', 'upcoming'),
(13, '2026-06-15', '2026-06-21', 'Week 13 (Jun 15 – Jun 21)', '2026-06-15 19:05:00', 'upcoming'),
(14, '2026-06-22', '2026-06-28', 'Week 14 (Jun 22 – Jun 28)', '2026-06-22 19:05:00', 'upcoming'),
(15, '2026-06-29', '2026-07-05', 'Week 15 (Jun 29 – Jul 5)', '2026-06-29 19:05:00', 'upcoming'),
(16, '2026-07-06', '2026-07-12', 'Week 16 (Jul 6 – Jul 12)', '2026-07-06 19:05:00', 'upcoming'),
(17, '2026-07-13', '2026-07-19', 'Week 17 (Jul 13 – Jul 19)', '2026-07-13 19:05:00', 'upcoming'),
(18, '2026-07-20', '2026-07-26', 'Week 18 (Jul 20 – Jul 26)', '2026-07-20 19:05:00', 'upcoming'),
(19, '2026-07-27', '2026-08-02', 'Week 19 (Jul 27 – Aug 2)', '2026-07-27 19:05:00', 'upcoming'),
(20, '2026-08-03', '2026-08-09', 'Week 20 (Aug 3 – Aug 9)', '2026-08-03 19:05:00', 'upcoming'),
(21, '2026-08-10', '2026-08-16', 'Week 21 (Aug 10 – Aug 16)', '2026-08-10 19:05:00', 'upcoming'),
(22, '2026-08-17', '2026-08-23', 'Week 22 (Aug 17 – Aug 23)', '2026-08-17 19:05:00', 'upcoming'),
(23, '2026-08-24', '2026-08-30', 'Week 23 (Aug 24 – Aug 30)', '2026-08-24 19:05:00', 'upcoming'),
(24, '2026-08-31', '2026-09-06', 'Week 24 (Aug 31 – Sep 6)', '2026-08-31 19:05:00', 'upcoming'),
(25, '2026-09-07', '2026-09-13', 'Week 25 (Sep 7 – Sep 13)', '2026-09-07 19:05:00', 'upcoming'),
(26, '2026-09-14', '2026-09-20', 'Week 26 (Sep 14 – Sep 20)', '2026-09-14 19:05:00', 'upcoming'),
(27, '2026-09-21', '2026-09-27', 'Week 27 (Sep 21 – Sep 27)', '2026-09-21 19:05:00', 'upcoming'),
(28, '2026-09-28', '2026-10-04', 'Week 28 (Sep 28 – Oct 4)', '2026-09-28 19:05:00', 'upcoming'),
(29, '2026-10-05', '2026-10-11', 'Week 29 (Oct 5 – Oct 11)', '2026-10-05 19:05:00', 'upcoming');

-- Games: use Admin → Import schedule or Daily sync (statsapi.mlb.com) — no mock rows here.
