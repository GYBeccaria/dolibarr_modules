-- ripristina i nomi tagliati delle fatture generate
update `llx_facture_extrafields` fe set fe.fattura_generata = concat(fe.fattura_generata, '7m') where fe.fattura_generata like '%.p'; 