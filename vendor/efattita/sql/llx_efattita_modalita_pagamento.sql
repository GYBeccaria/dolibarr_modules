--
-- Struttura della tabella `llx_efattita_modalita_pagamento`
--

CREATE TABLE IF NOT EXISTS `llx_efattita_modalita_pagamento` (
  `code` varchar(4) NOT NULL,
  `description` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dump dei dati per la tabella `llx_efattita_modalita_pagamento`
--

INSERT IGNORE INTO `llx_efattita_modalita_pagamento` (`code`, `description`) VALUES
('MP01', 'MP01 - Contanti'),
('MP02', 'MP02 - Assegno'),
('MP03', 'MP03 - Assegno circolare'),
('MP04', 'MP04 - Contanti presso Tesoreria'),
('MP05', 'MP05 - Bonifico'),
('MP06', 'MP06 - Vaglia cambiario'),
('MP07', 'MP07 - Bollettino bancario'),
('MP08', 'MP08 - Carta di pagamento'),
('MP09', 'MP09 - RID'),
('MP10', 'MP10 - RID utenze'),
('MP11', 'MP11 - RID veloce'),
('MP12', 'MP12 - RIBA'),
('MP13', 'MP13 - MAV'),
('MP14', 'MP14 - Quietanza erario'),
('MP15', 'MP15 - Giroconto su conti di contabilità speciale'),
('MP16', 'MP16 - Domiciliazione bancaria'),
('MP17', 'MP17 - Domiciliazione postale'),
('MP18', 'MP18 - Bollettino di c/c postale'),
('MP19', 'MP19 - SEPA Direct Debit'),
('MP20', 'MP20 - SEPA Direct Debit CORE'),
('MP21', 'MP21 - SEPA Direct Debit B2B'),
('MP22', 'MP22 - Trattenuta su somme già riscosse'),
('MP23', 'MP23 - PagoPA');

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `llx_efattita_modalita_pagamento`
--
ALTER TABLE `llx_efattita_modalita_pagamento`
  ADD PRIMARY KEY (`code`);
