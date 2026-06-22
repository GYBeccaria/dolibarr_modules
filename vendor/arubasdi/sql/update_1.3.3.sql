-- il nome file fattura generata di alcuni provider è più lungo 
ALTER TABLE `llx_facture_extrafields` CHANGE `fattura_generata` `fattura_generata` VARCHAR(40) NULL DEFAULT NULL; 
