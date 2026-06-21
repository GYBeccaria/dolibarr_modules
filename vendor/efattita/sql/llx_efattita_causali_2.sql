
--
-- Struttura della tabella `llx_efattita_causali_2`
--

CREATE TABLE IF NOT EXISTS `llx_efattita_causali_2` (
  `code` varchar(2) NOT NULL,
  `description` varchar(420) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dump dei dati per la tabella `llx_efattita_causali_2`
--

INSERT IGNORE INTO `llx_efattita_causali_2` (`code`, `description`) VALUES
('A', 'Prestazioni di lavoro autonomo rientranti nell’esercizio di arte o professione abituale'),
('B', 'Utilizzazione economica, da parte dell’autore o dell’inventore, di opere dell’ingegno, di brevetti industriali e di processi, formule o informazioni relativi a esperienze acquisite in campo industriale, commerciale o scientifico'),
('C', 'Utilizzazione economica, da parte dell’autore o dell’inventore, di opere dell’ingegno, di brevetti industriali e di processi, formule o informazioni relativi a esperienze acquisite in campo industriale, commerciale o scientifico'),
('D', 'Utili spettanti ai soci promotori e ai soci fondatori delle società di capitali'),
('E', 'Levata di protesti cambiari da parte dei segretari comunali'),
('G', 'Indennità corrisposte per la cessazione di attività sportiva professionale'),
('H', 'Indennità corrisposte per la cessazione dei rapporti di agenzia delle persone fisiche e delle società di persone, con esclusione delle somme maturate entro il 31.12.2003, già imputate per competenza e tassate come reddito d’impresa'),
('I', 'Indennità corrisposte per la cessazione da funzioni notarili'),
('L', 'Utilizzazione economica, da parte di soggetto diverso dall’autore o dall’inventore, di opere dell’ingegno, di brevetti industriali e di processi, formule e informazioni relative a esperienze acquisite in campo industriale, commerciale o scientifico'),
('L1', 'Redditi derivanti dall’utilizzazione economica di opere dell’ingegno, di brevetti industriali e di processi, formule e informazioni relativi a esperienze acquisite in campo industriale, commerciale o scientifico, che sono percepiti da soggetti che abbiano acquistato a titolo oneroso i diritti alla loro utilizzazione'),
('M', 'Prestazioni di lavoro autonomo non esercitate abitualmente, obblighi di fare, di non fare o permettere'),
('M1', 'Redditi derivanti dall’assunzione di obblighi di fare, di non fare o permettere'),
('M2', 'Prestazioni di lavoro autonomo non esercitate abitualmente per le quali sussiste l’obbligo di iscrizione alla Gestione Separata ENPAPI'),
('N', 'Indennità di trasferta, rimborso forfetario di spese, premi e compensi erogati: .. nell’esercizio diretto di attività sportive dilettantistiche'),
('O', 'Prestazioni di lavoro autonomo non esercitate abitualmente, obblighi di fare, di non fare o permettere, per le quali non sussiste l’obbligo di iscrizione alla gestione separata (Circ. Inps 104/2001)'),
('O1', 'Redditi derivanti dalla assunzione di obblighi di fare, di non fare o permettere, per le quali non sussiste l’obbligo di iscrizione alla Gestione Separata (Circolare INPS n. 104/2001)'),
('P', 'Compensi corrisposti a soggetti non residenti privi di stabile organizzazione per l’uso o la concessione in uso di attrezzature industriali, commerciali o scientifiche che si trovano nel territorio dello'),
('Q', 'Provvigioni corrisposte ad agente o rappresentante di commercio monomandatario'),
('R', 'Provvigioni corrisposte ad agente o rappresentante di commercio plurimandatario'),
('S', 'Provvigioni corrisposte a commissionario'),
('T', 'Provvigioni corrisposte a mediatore'),
('U', 'Provvigioni corrisposte a procacciatore di affari'),
('V', 'Provvigioni corrisposte a incaricato per le vendite a domicilio e provvigioni corrisposte a incaricato per la vendita porta a porta e per la vendita ambulante di giornali quotidiani e periodici (L. 25.02.1987, n. 67)'),
('V1', 'Redditi derivanti da attività commerciali non esercitate abitualmente (ad esempio provvigioni corrisposte per prestazioni occasionali ad agenti e rappresentanti di commercio, mediatori e simili)'),
('V2', 'Redditi derivanti dalle prestazioni non esercitate abitualmente rese dagli incaricati alla vendita diretta a domicilio'),
('W', 'Corrispettivi erogati nel 2013 per prestazioni relative a contratti d’appalto cui si sono resi applicabili le disposizioni contenute nell’art. 25-ter D.P.R. 600/1973'),
('X', 'Canoni corrisposti nel 2004 da società o enti residenti, ovvero da stabili organizzazioni di società estere di cui all’art. 26-quater, c. 1, lett. a) e b) D.P.R. 600/1973, a società o stabili organizzazioni di società, situate in altro Stato membro dell’Unione Europea in presenza dei relativi requisiti richiesti, per i quali è stato effettuato nel 2006 il rimborso della ritenuta ai sensi dell’art. 4 D. Lgs. 143/2005'),
('Y', 'Canoni corrisposti dal 1.01.2005 al 26.07.2005 da soggetti di cui al punto precedente'),
('Z', 'Titolo diverso dai precedenti'),
('ZO', 'Titolo diverso dai precedenti');

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `llx_efattita_causali_2`
--
ALTER TABLE `llx_efattita_causali_2`
  ADD PRIMARY KEY (`code`);
