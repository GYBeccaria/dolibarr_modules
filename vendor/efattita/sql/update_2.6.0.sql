-- Efattita update
--
-- Update per la tabella `llx_extrafields`
--

UPDATE `llx_extrafields` SET `type` = 'sellist', `size` = '4', `param` = 'a:1:{s:7:\"options\";a:1:{s:44:\"efattita_tipo_documento:description|1:code::\";N;}}' WHERE `llx_extrafields`.`name` = 'tipo_documento';
ALTER TABLE `llx_facture_extrafields` CHANGE `tipo_documento` `tipo_documento` VARCHAR(4) NULL DEFAULT NULL ;

-- viene reso visibile il campo natura
UPDATE `llx_extrafields` SET `list` = '1' WHERE `name` = 'natura' and `elementtype`='facture'; 

-- llx_efattita_natura viene sostituita da llx_efattita_natura_2
DROP TABLE IF EXISTS `llx_efattita_natura`;
UPDATE `llx_extrafields` SET `type` = 'sellist', `size` = '4', `param` = 'a:1:{s:7:\"options\";a:1:{s:38:\"efattita_natura_2:description|1:code::\";N;}}' WHERE `llx_extrafields`.`name` = 'natura' and `elementtype`='facture';


-- llx_efattita_causali viene sostituita da llx_efattita_causali_2
DROP TABLE IF EXISTS `llx_efattita_causali`;

-- modalità pagamento
UPDATE `llx_extrafields` SET `type` = 'sellist', `size` = '4', `param` = 'a:1:{s:7:\"options\";a:1:{s:48:\"efattita_modalita_pagamento:description|1:code::\";N;}}' WHERE `llx_extrafields`.`name` = 'modalita_pagamento' and `elementtype`='facture';
