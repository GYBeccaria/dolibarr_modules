ALTER TABLE `llx_efattita_riferimento_normativo` CHANGE `code` `code` VARCHAR(4) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL; 
ALTER TABLE `llx_efattita_riferimento_normativo` CHANGE `description` `description` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL; 
INSERT INTO `llx_efattita_riferimento_normativo` (`rowid`, `code`, `description`) VALUES (39, 'N2.2', 'art. 1, commi da 54 a 89, della Legge n. 190/2014 e successive modificazioni'), (40, 'N2.2', 'art. 41, comma 2-bis, DL 331/1993');
INSERT INTO `llx_efattita_riferimento_normativo` (`rowid`, `code`, `description`) VALUES (41, 'N2.2', 'Non costituisce cessione intracomunitaria ai sensi dell\'art.41, comma 2-bis, DL 331/1993'); 
