--
-- Struttura della tabella `llx_efattita_riferimento_normativo`
--

CREATE TABLE IF NOT EXISTS `llx_efattita_riferimento_normativo` (
  `rowid` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(2) DEFAULT NULL,
  `description` varchar(48) DEFAULT NULL,
  PRIMARY KEY (`rowid`),
  KEY `fk_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8;

--
-- Dump dei dati per la tabella `llx_efattita_riferimento_normativo`
--

INSERT IGNORE INTO `llx_efattita_riferimento_normativo` (`rowid`, `code`, `description`) VALUES
(1, 'N1', 'art. 15 DPR 633/72'),
(2, 'N2', 'art. 7 ter DPR 633/72'),
(3, 'N2', 'art. 7 quater c.1 lettera a) DPR 633/72'),
(4, 'N2', 'art. 7 quater c.1 lett. a) e c) DPR 633/72'),
(5, 'N2', 'art. 7 - 7 septies DPR 633/72'),
(6, 'N2', 'art. 2 c.2 DPR 633/1972'),
(7, 'N2', 'art. 74 DPR 633/72'),
(8, 'N2', 'art. 68 lett. a DPR 633/72'),
(9, 'N2', 'art. 68 lett. b,c,d,e,f, DPR 633/72'),
(10, 'N3', 'art. 41 c.1 DL 331/93'),
(11, 'N3', 'art. 8 c.1 lett. a) e b) DPR 633/72'),
(12, 'N3', 'art. 8 c.1 lett. c) DPR 633/72'),
(13, 'N3', 'art. 8 c.1 DPR 633/72'),
(14, 'N3', 'art. 9 DPR 633/72'),
(15, 'N3', 'ex art. 8 bis DPR 633/72'),
(16, 'N3', 'art. 41 e 42 DL331/93'),
(17, 'N3', 'art. 71 DPR 633/72'),
(18, 'N3', 'art. 72 DPR 633/72'),
(19, 'N4', 'art. 42 DL 331/1993'),
(20, 'N4', 'art. 10 DPR 633/72'),
(21, 'N5', 'art. 36 e s. DL 41/95'),
(22, 'N5', 'art. 74 ter DPR 633/1972'),
(23, 'N6', 'art. 17 DPR 633/72'),
(24, 'N6', 'art. 17 c.6 lett. a-ter) DPR 633/1972'),
(25, 'N6', 'art. 17 c.6 lett. c) DPR 633/1972'),
(26, 'N6', 'art. 17 c.6 lett. a) DPR 633/1972'),
(27, 'N6', 'art. 17 c.6 lett. a-bis) DPR 633/1972'),
(28, 'N6', 'art. 17 c.6 lett. b) DPR 633/1972'),
(29, 'N6', 'art. 74, c.7 DPR 633/72'),
(30, 'N7', 'art. 40 c. 3 e 4 e 41 c. 1 lettera b) DL 331/93'),
(31, 'N7', 'art. 7 sexies lettere f) e g) DPR 633/72'),
(32, 'N7', 'art. 7 quinques DPR 633/72'),
(33, 'N7', 'art. 7 quater lett. a) DPR 633/72'),
(34, 'N7', 'art. 7 quater lett. c) DPR 633/72'),
(35, 'N7', 'art. 74 sexies DPR 633/72'),
(36, NULL, 'art. 17-ter DPR 633/72 (scissione dei pagamenti)'),
(37, 'N4', 'art. 19 c.1 DPR 633/72');
