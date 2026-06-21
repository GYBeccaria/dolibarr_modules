--
-- Struttura della tabella `llx_efattita_natura_2`
--

CREATE TABLE IF NOT EXISTS `llx_efattita_natura_2` (
  `code` varchar(4) NOT NULL,
  `description` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `llx_efattita_natura_2`
--
ALTER TABLE `llx_efattita_natura_2`
ADD PRIMARY KEY (`code`);

--
-- Dump dei dati per la tabella `llx_efattita_natura_2`
--

INSERT IGNORE INTO `llx_efattita_natura_2` (`code`, `description`) VALUES
('N1', 'N1 - Operazioni escluse ex art. 15'),
('N2.1', 'N2.1 - Operazioni non soggette ad IVA ai sensi degli artt. da 7 a 7-septies'),
('N2.2', 'N2.2 - Operazioni non soggette - altri casi'),
('N3.1', 'N3.1 - Operazioni non imponibili - esportazioni'),
('N3.2', 'N3.2 - Operazioni non imponibili - cessioni intracomunitarie'),
('N3.3', 'N3.3 - Operazioni non imponibili - cessioni verso San Marino'),
('N3.4', 'N3.4 - Operazioni non imponibili - operazioni assimilate alle cessioni all’esportazione'),
('N3.5', 'N3.5 - Operazioni non imponibili - a seguito di dichiarazioni d’intento'),
('N3.6', 'N3.6 - Operazioni non imponibili - altre operazioni che non concorrono alla formazione del plafond'),
('N4', 'N4 - Operazioni esenti'),
('N5', 'N5 - Regime del margine/IVA non esposta in fattura'),
('N6.1', 'N6.1 - Inversione contabile - cessione di rottami e altri materiali di recupero'),
('N6.2', 'N6.2 - Inversione contabile - cessione di oro e argento puro'),
('N6.3', 'N6.3 - Inversione contabile - subappalto nel settore edile'),
('N6.4', 'N6.4 - Inversione contabile - cessione di fabbricati'),
('N6.5', 'N6.5 - Inversione contabile - cessione di telefoni cellulari'),
('N6.6', 'N6.6 - Inversione contabile - cessione di prodotti elettronici'),
('N6.7', 'N6.7 - Inversione contabile - prestazioni comparto edile e settori connessi'),
('N6.8', 'N6.8 - Inversione contabile - operazioni settore energetico'),
('N6.9', 'N6.9 - Inversione contabile - altri casi'),
('N7', 'N7 - IVA assolta in altro stato UE (vendite a distanza ex art. 40 commi 3 e 4 e art. 41 comma 1 lett. b), D.L. n. 331/93; prestazione di servizi di telecomunicazioni, tele-radiodiffusione ed elettronici ex art. 7-sexies lett. f), g), DPR n. 633/7');
