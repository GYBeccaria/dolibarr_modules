--
-- Struttura della tabella `llx_efattita_tipo_cassa`
--

CREATE TABLE IF NOT EXISTS `llx_efattita_tipo_cassa` (
  `id` int(11) NOT NULL,
  `code` varchar(4) DEFAULT NULL,
  `description` varchar(84) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dump dei dati per la tabella `llx_efattita_tipo_cassa`
--

INSERT IGNORE INTO `llx_efattita_tipo_cassa` (`id`, `code`, `description`) VALUES
(1, 'TC01', 'Cassa nazionale previdenza e assistenza avvocati e procuratori legali'),
(2, 'TC02', 'Cassa previdenza dottori commercialisti'),
(3, 'TC03', 'Cassa previdenza e assistenza geometri'),
(4, 'TC04', 'Cassa nazionale previdenza e assistenza ingegneri e architetti liberi professionisti'),
(5, 'TC05', 'Cassa nazionale del notariato'),
(6, 'TC06', 'Cassa nazionale previdenza e assistenza ragionieri e periti commerciali'),
(7, 'TC07', 'Ente nazionale assistenza agenti e rappresentanti di commercio (ENASARCO)'),
(8, 'TC08', 'Ente nazionale previdenza e assistenza consulenti del lavoro (ENPACL)'),
(9, 'TC09', 'Ente nazionale previdenza e assistenza medici (ENPAM)'),
(10, 'TC10', 'Ente nazionale previdenza e assistenza farmacisti (ENPAF)'),
(11, 'TC11', 'Ente nazionale previdenza e assistenza veterinari (ENPAV)'),
(12, 'TC12', 'Ente nazionale previdenza e assistenza impiegati dell\'agricoltura (ENPAIA)'),
(13, 'TC13', 'Fondo previdenza impiegati imprese di spedizione e agenzie marittime'),
(14, 'TC14', 'Istituto nazionale previdenza giornalisti italiani (INPGI)'),
(15, 'TC15', 'Opera nazionale assistenza orfani sanitari italiani (ONAOSI)'),
(16, 'TC16', 'Cassa autonoma assistenza integrativa giornalisti italiani (CASAGIT)'),
(17, 'TC17', 'Ente previdenza periti industriali e periti industriali laureati (EPPI)'),
(18, 'TC18', 'Ente previdenza e assistenza pluricategoriale (EPAP)'),
(19, 'TC19', 'Ente nazionale previdenza e assistenza biologi (ENPAB)'),
(20, 'TC20', 'Ente nazionale previdenza e assistenza professione infermieristica (ENPAPI)'),
(21, 'TC21', 'Ente nazionale previdenza e assistenza psicologi (ENPAP)'),
(22, 'TC22', 'INPS');

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `llx_efattita_tipo_cassa`
--
ALTER TABLE `llx_efattita_tipo_cassa`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `llx_efattita_tipo_cassa`
--
ALTER TABLE `llx_efattita_tipo_cassa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;
