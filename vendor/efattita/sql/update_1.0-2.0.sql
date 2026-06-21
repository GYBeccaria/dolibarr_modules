-- aggiorna il campo natura, togliendolo dalla scheda
update llx_extrafields set list=2 where name='natura' and elementtype='facture';

-- cancella il vecchio campo extra ora viene messo nella impostazione modulo
delete from llx_extrafields where name='regime_fiscale' and elementtype='facture';

-- aggiorna il campo riferimento_normativo, con il dato indicizzato
UPDATE `llx_extrafields` SET `type` = 'sellist', `param` = 'a:1:{s:7:\"options\";a:1:{s:50:\"efattita_riferimento_normativo:description:rowid::\";N;}}' WHERE `llx_extrafields`.`name` = 'riferimento_normativo';
