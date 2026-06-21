-- Efattita update

--
-- Update per la tabella `llx_extrafields`
--
UPDATE `llx_extrafields` SET `size` = '100' WHERE `llx_extrafields`.`name` ='riferimento_normativo' AND `llx_extrafields`.`elementtype`='facture'; 

--
-- Update per la tabella `llx_facture_extrafields`
--
ALTER TABLE `llx_facture_extrafields` CHANGE `riferimento_normativo` `riferimento_normativo` VARCHAR(100) NULL DEFAULT NULL; 
