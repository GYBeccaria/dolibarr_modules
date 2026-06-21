-- Efattita update

--
-- Update per la tabella `llx_facture_fourn`
-- Toglie la chiave unica che impedisce stesso numero fattura in anni diversi
-- 

ALTER TABLE `llx_facture_fourn` DROP INDEX `uk_facture_fourn_ref_supplier`;
