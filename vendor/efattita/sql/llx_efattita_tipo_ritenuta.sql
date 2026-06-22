--
-- Struttura della tabella `llx_efattita_tipo_ritenuta`
--

CREATE TABLE IF NOT EXISTS `llx_efattita_tipo_ritenuta` (
  `id` int(11) NOT NULL,
  `code` varchar(4) NOT NULL,
  `description` varchar(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dump dei dati per la tabella `llx_efattita_tipo_ritenuta`
--

INSERT IGNORE INTO `llx_efattita_tipo_ritenuta` (`id`, `code`, `description`) VALUES
(1, 'RT01', 'RT01 - Ritenuta persone fisiche'),
(2, 'RT02', 'RT02 - Ritenuta persone giuridiche'),
(3, 'RT03', 'RT03 - Contributo INPS'),
(4, 'RT04', 'RT04 - Contributo ENASARCO'),
(5, 'RT05', 'RT05 - Contributo ENPAM'),
(6, 'RT06', 'RT06 - Altro contributo previdenziale');

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `llx_efattita_tipo_ritenuta`
--
ALTER TABLE `llx_efattita_tipo_ritenuta`
  ADD PRIMARY KEY (`id`);
