-- importa lo stato di tutte le fatture già inviate con canale ESI
UPDATE `llx_facture_extrafields` SET `statoFattura` = 4 WHERE `statoFattura` IS NULL AND `esito`= 1;
UPDATE `llx_facture_extrafields` SET `statoFattura` = 3 WHERE `statoFattura` IS NULL AND `esito`= 2;
UPDATE `llx_facture_extrafields` SET `statoFattura` = 8 WHERE `statoFattura` IS NULL AND `esito`= 3;
UPDATE `llx_facture_extrafields` SET `statoFattura` = 5 WHERE `statoFattura` IS NULL AND `esito`= 4;
UPDATE `llx_facture_extrafields` SET `statoFattura` = 7 WHERE `statoFattura` IS NULL AND `esito`= 5;
