-- aggiunge indici per la ricerca veloce delle fatture di cui aggiornare lo stato
CREATE INDEX idx_statoFattura ON llx_facture_extrafields(statoFattura);
CREATE INDEX idx_arubasdi_dataStato ON llx_facture_extrafields(arubasdi_dataStato);