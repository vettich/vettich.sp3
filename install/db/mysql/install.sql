CREATE TABLE `vettich_sp3_template` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `IS_ENABLE` varchar(1) NOT NULL,
  `NAME` varchar(255) NOT NULL,
  `IBLOCK_TYPE` varchar(255) NOT NULL,
  `IBLOCK_ID` varchar(255) NOT NULL,
  `IS_SECTIONS` varchar(1) NOT NULL,
  `NEED_UTM` varchar(1) NOT NULL,
  `UTM_SOURCE` varchar(255) NOT NULL,
  `UTM_MEDIUM` varchar(255) NOT NULL,
  `UTM_CAMPAIGN` varchar(255) NOT NULL,
  `UTM_TERM` varchar(255) NOT NULL,
  `UTM_CONTENT` varchar(255) NOT NULL,
  `IS_AUTO` varchar(1) NOT NULL,
  `PUBLISH_AT` varchar(255) NOT NULL,
  `UPDATE_IN_NETWORKS` varchar(1) NOT NULL,
  `DELETE_IN_NETWORKS` varchar(1) NOT NULL,
  `QUEUE_DUPLICATE` varchar(1) NOT NULL,
  `UNLOAD_ENABLE` varchar(1) NOT NULL,
  `UNLOAD_TIMEZONE` varchar(255) NOT NULL,
  `UNLOAD_SORT_FIELD` varchar(255) NOT NULL,
  `UNLOAD_SORT_ORDER` varchar(255) NOT NULL,
  `UNLOAD_KEEP_INTERVAL` varchar(1) NOT NULL,
  `LAST_PUBLISHED_AT` datetime NOT NULL,
  `USER_ID` int(11) NOT NULL,
  `UPDATED_AT` datetime NOT NULL,
  `CREATED_AT` datetime NOT NULL,
  `IBLOCK_SECTIONS` text NOT NULL,
  `DOMAIN` text NOT NULL,
  `CONDITIONS` text NOT NULL,
  `ACCOUNTS` text NOT NULL,
  `PUBLISH` text NOT NULL,
  `UNLOAD_DATETIME` text NOT NULL,
  PRIMARY KEY (`ID`)
);

CREATE TABLE `vettich_sp3_post_iblock` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `IBLOCK_ID` varchar(255) NOT NULL,
  `ELEM_ID` int(11) NOT NULL,
  `TEMPLATE_ID` int(11) NOT NULL,
  `POST_ID` varchar(255) NOT NULL,
  `TEMPLATE` text NOT NULL,
  PRIMARY KEY (`ID`)
);
