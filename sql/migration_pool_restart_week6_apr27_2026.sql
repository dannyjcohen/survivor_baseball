-- Restart pool at Week 6 starting Monday 2026-04-27: drop weeks before that date, renumber 5→6 … 28→29, refresh labels.
-- Picks and games tied to deleted pool_week rows are removed (ON DELETE CASCADE).
-- Run once against an existing DB that still has the original week 1–28 seed.

DELETE FROM pool_weeks WHERE week_start_local < '2026-04-27';

UPDATE pool_weeks SET week_number = week_number + 1;

UPDATE pool_weeks SET week_label = 'Week 6 (Apr 27 – May 3)' WHERE week_start_local = '2026-04-27';
UPDATE pool_weeks SET week_label = 'Week 7 (May 4 – May 10)' WHERE week_start_local = '2026-05-04';
UPDATE pool_weeks SET week_label = 'Week 8 (May 11 – May 17)' WHERE week_start_local = '2026-05-11';
UPDATE pool_weeks SET week_label = 'Week 9 (May 18 – May 24)' WHERE week_start_local = '2026-05-18';
UPDATE pool_weeks SET week_label = 'Week 10 (May 25 – May 31)' WHERE week_start_local = '2026-05-25';
UPDATE pool_weeks SET week_label = 'Week 11 (Jun 1 – Jun 7)' WHERE week_start_local = '2026-06-01';
UPDATE pool_weeks SET week_label = 'Week 12 (Jun 8 – Jun 14)' WHERE week_start_local = '2026-06-08';
UPDATE pool_weeks SET week_label = 'Week 13 (Jun 15 – Jun 21)' WHERE week_start_local = '2026-06-15';
UPDATE pool_weeks SET week_label = 'Week 14 (Jun 22 – Jun 28)' WHERE week_start_local = '2026-06-22';
UPDATE pool_weeks SET week_label = 'Week 15 (Jun 29 – Jul 5)' WHERE week_start_local = '2026-06-29';
UPDATE pool_weeks SET week_label = 'Week 16 (Jul 6 – Jul 12)' WHERE week_start_local = '2026-07-06';
UPDATE pool_weeks SET week_label = 'Week 17 (Jul 13 – Jul 19)' WHERE week_start_local = '2026-07-13';
UPDATE pool_weeks SET week_label = 'Week 18 (Jul 20 – Jul 26)' WHERE week_start_local = '2026-07-20';
UPDATE pool_weeks SET week_label = 'Week 19 (Jul 27 – Aug 2)' WHERE week_start_local = '2026-07-27';
UPDATE pool_weeks SET week_label = 'Week 20 (Aug 3 – Aug 9)' WHERE week_start_local = '2026-08-03';
UPDATE pool_weeks SET week_label = 'Week 21 (Aug 10 – Aug 16)' WHERE week_start_local = '2026-08-10';
UPDATE pool_weeks SET week_label = 'Week 22 (Aug 17 – Aug 23)' WHERE week_start_local = '2026-08-17';
UPDATE pool_weeks SET week_label = 'Week 23 (Aug 24 – Aug 30)' WHERE week_start_local = '2026-08-24';
UPDATE pool_weeks SET week_label = 'Week 24 (Aug 31 – Sep 6)' WHERE week_start_local = '2026-08-31';
UPDATE pool_weeks SET week_label = 'Week 25 (Sep 7 – Sep 13)' WHERE week_start_local = '2026-09-07';
UPDATE pool_weeks SET week_label = 'Week 26 (Sep 14 – Sep 20)' WHERE week_start_local = '2026-09-14';
UPDATE pool_weeks SET week_label = 'Week 27 (Sep 21 – Sep 27)' WHERE week_start_local = '2026-09-21';
UPDATE pool_weeks SET week_label = 'Week 28 (Sep 28 – Oct 4)' WHERE week_start_local = '2026-09-28';
UPDATE pool_weeks SET week_label = 'Week 29 (Oct 5 – Oct 11)' WHERE week_start_local = '2026-10-05';
