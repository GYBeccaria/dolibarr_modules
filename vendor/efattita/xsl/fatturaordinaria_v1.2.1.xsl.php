<?php
// Imposta l'intestazione CORS per consentire l'accesso da qualsiasi dominio.
header("Access-Control-Allow-Origin: *");

// Imposta l'intestazione Content-Type per indicare che si tratta di un file XML.
header("Content-Type: application/xml");

// Leggi il file XML e restituiscilo come output.
$xmlFile = file_get_contents("fatturaordinaria_v1.2.1.xsl");
echo $xmlFile;