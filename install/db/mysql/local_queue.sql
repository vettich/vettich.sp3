CREATE TABLE IF NOT EXISTS `vettich_sp3_local_queue` (
  `ID`              int(11)      NOT NULL AUTO_INCREMENT,
  `IBLOCK_ID`       int(11)      NOT NULL DEFAULT 0,
  `ELEM_ID`         int(11)      NOT NULL,
  `OPERATION`       varchar(20)  NOT NULL COMMENT 'create | update | delete',
  `PAYLOAD`         text         NOT NULL COMMENT 'JSON: {"was_active":"N"}',
  `STATUS`          varchar(20)  NOT NULL DEFAULT 'pending',
  `ATTEMPTS`        int(11)      NOT NULL DEFAULT 0,
  `NEXT_ATTEMPT_AT` datetime     NOT NULL,
  `CREATED_AT`      datetime     NOT NULL,
  `LOCKED_UNTIL`    datetime     NULL DEFAULT NULL COMMENT 'lease для STATUS=processing',
  PRIMARY KEY (`ID`),
  KEY `idx_lq_pending` (`STATUS`, `NEXT_ATTEMPT_AT`),
  KEY `idx_lq_processing` (`STATUS`, `LOCKED_UNTIL`),
  UNIQUE KEY `idx_lq_dedup` (`ELEM_ID`, `IBLOCK_ID`, `OPERATION`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
