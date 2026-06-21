<?php
/* Copyright (C) 2018 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    efattita/lib/efattita.lib.php
 * \ingroup efattita
 * \brief   Library files with common functions for eFattITA
 */

/**
 * Prepare admin pages header
 *
 * @return array
 */
function efattitaAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("efattita@efattita");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/efattita/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;
	$head[$h][0] = dol_buildpath("/efattita/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'efattita');

	return $head;
}
function fetchCountryId($code){
	global $db;
	if((float) DOL_VERSION >= 3.7)
		$sql='select rowid from '.MAIN_DB_PREFIX."c_country where code='$code'";
	else
		$sql='select rowid from '.MAIN_DB_PREFIX."c_pays where code='$code'";
	$res=$db->query($sql);
	$obj=$db->fetch_object($res);
	return $obj->rowid;
}
function fetchDepartmentId($code){
	global $db;
	if($code){
		$sql='select rowid from '.MAIN_DB_PREFIX."c_departements where code_departement='$code'";
		$res=$db->query($sql);
		$obj=$db->fetch_object($res);
		return $obj->rowid;
	}
}
function fetchStateCode($id){
	global $db;
	if($id){
		$sql='select code_departement from '. MAIN_DB_PREFIX ."c_departements where rowid=$id";
		$res=$db->query($sql);
		$obj=$db->fetch_object($res);
		return $obj->code_departement;
	}
}
function getNatura($facture){
	global $db;
	if(!empty($facture->array_options['options_riferimento_normativo'])){
		$sql = 'SELECT code FROM '. MAIN_DB_PREFIX .'efattita_riferimento_normativo where rowid = '.$facture->array_options['options_riferimento_normativo'];
		$res=$db->query($sql);
		$obj=$db->fetch_object($res);
		return $obj->code;
	}
}
function getRiferimentoNormativo($facture){
	global $db;
	if(!empty($facture->array_options['options_riferimento_normativo'])){
		$sql = 'SELECT description FROM '.MAIN_DB_PREFIX.'efattita_riferimento_normativo where rowid = '.$facture->array_options['options_riferimento_normativo'];
		$res=$db->query($sql);
		$obj=$db->fetch_object($res);
		return $obj->description;
	}
}
# 2 decimali di default
function nformat($n,$decimals=2){
    $n = round(floatval($n),$decimals);
    $n = number_format($n, $decimals,'.','');
    return $n;
}

function getNextAlphaNumeric($code) {
   $base_ten = base_convert($code,36,10);
   $result = base_convert($base_ten+1,10,36);
   // $result = str_pad($result, 3, '0', STR_PAD_LEFT);
   $result = strtoupper($result);
   return $result;
}

function getTaxLabel($vatrate, $localtax1 = 0, $localtax2 = 0, $localtax2_type = 0){
	global $db;
	// diamo per scontato che l'id paese per l'italia è 3 che non è mai cambiato
	$sql  = "SELECT t.taux, t.code";
	$sql .= " FROM ".MAIN_DB_PREFIX."c_tva as t";
	$sql .= " WHERE t.fk_pays = 3";
	$sql .= " AND t.taux = $vatrate";
	$sql .= " AND t.localtax1 = $localtax1";
	$sql .= " AND t.localtax2 = $localtax2";
	if($localtax2 && $localtax_type)
		$sql.= " AND t.localtax2_type = $localtax2_type";
	$res=$db->query($sql);
	if($res->num_rows > 0){
		$obj = $db->fetch_object($res);
		return $obj->taux.'('.$obj->code.')';
	}else{
		$code = 'V'.$vatrate.'R'.$localtax1.'C'.$localtax2;
		$description = $vatrate.'% iva';
		if($localtax1){
			$description .= " - $localtax1% ra";
		}
		if($localtax2){
			$description .= " + $localtax2% cap type $localtax2_type";
		}
		$sql="INSERT INTO " . MAIN_DB_PREFIX . "c_tva ( `fk_pays`, `code`, `taux`, `localtax1`, `localtax1_type`, `localtax2`, `localtax2_type`, `recuperableonly`, `note`, `active`, `accountancy_code_sell`, `accountancy_code_buy`) ";
		$sql.="VALUES ( 3, '$code', $vatrate, $localtax1, '1', '$localtax2', $localtax2_type, 0, '$description', 1, '160100', '260100')";
		$db->query($sql);
		return $vatrate.'('.$code.')';
	}
}
// funzione che inserisce MIME nel file p7m
function der2smime($file,$filename) {
	$to=<<<TXT
MIME-Version: 1.0
Content-Disposition: attachment; filename=”smime.p7m”
Content-Type: application/x-pkcs7-mime; smime-type=signed-data; name=”smime.p7m”
Content-Transfer-Encoding: base64
\n
TXT;
	$from=file_get_contents($file);
	$to.=chunk_split(base64_encode($from));
	// memorizza file senza .p7m
	return file_put_contents($filename,$to);
}

/**
 * stripP7MData
 *
 * removes the PKCS#7 header and the signature info footer from a digitally-signed .xml.p7m file using CAdES format.
 *
 * @param ($string, string) the CAdES .xml.p7m file content (in string format).
 * @return (string) an arguably-valid XML string with the .p7m header and footer stripped away.
 */
function stripP7MData($string) {
    
    // skip everything before the XML content
    $string = substr($string, strpos($string, '<?xml '));

    // skip everything after the XML content
    preg_match_all('/<\/.+?>/', $string, $matches, PREG_OFFSET_CAPTURE);
    $lastMatch = end($matches[0]);

    return substr($string, 0, $lastMatch[1]+strlen($lastMatch[0]));
}

/**
 * Replaces special characters in a string with their "non-special" counterpart.
 * modified from https://stackoverflow.com/questions/6284118/convert-national-chars-into-their-latin-equivalents-in-php
 * Useful for friendly URLs.
 *
 * @access public
 * @param string
 * @param int
 * @return string
 */
function efattita_transliterate($string) {

	// decode remove html entities
	$string=html_entity_decode($string);

    $table = array(
        'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Ă'=>'A', 'Ā'=>'A', 'Ą'=>'A', 'Æ'=>'A', 'Ǽ'=>'A',
        'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'ă'=>'a', 'ā'=>'a', 'ą'=>'a', 'æ'=>'a', 'ǽ'=>'a',

        'Þ'=>'B', 'þ'=>'b', 'ß'=>'Ss',

        'Ç'=>'C', 'Č'=>'C', 'Ć'=>'C', 'Ĉ'=>'C', 'Ċ'=>'C',
        'ç'=>'c', 'č'=>'c', 'ć'=>'c', 'ĉ'=>'c', 'ċ'=>'c',

        'Đ'=>'Dj', 'Ď'=>'D',
        'đ'=>'dj', 'ď'=>'d',

        'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ĕ'=>'E', 'Ē'=>'E', 'Ę'=>'E', 'Ė'=>'E',
        'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ĕ'=>'e', 'ē'=>'e', 'ę'=>'e', 'ė'=>'e',

        'Ĝ'=>'G', 'Ğ'=>'G', 'Ġ'=>'G', 'Ģ'=>'G',
        'ĝ'=>'g', 'ğ'=>'g', 'ġ'=>'g', 'ģ'=>'g',

        'Ĥ'=>'H', 'Ħ'=>'H',
        'ĥ'=>'h', 'ħ'=>'h',

        'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'İ'=>'I', 'Ĩ'=>'I', 'Ī'=>'I', 'Ĭ'=>'I', 'Į'=>'I',
        'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'į'=>'i', 'ĩ'=>'i', 'ī'=>'i', 'ĭ'=>'i', 'ı'=>'i',

        'Ĵ'=>'J',
        'ĵ'=>'j',

        'Ķ'=>'K',
        'ķ'=>'k', 'ĸ'=>'k',

        'Ĺ'=>'L', 'Ļ'=>'L', 'Ľ'=>'L', 'Ŀ'=>'L', 'Ł'=>'L',
        'ĺ'=>'l', 'ļ'=>'l', 'ľ'=>'l', 'ŀ'=>'l', 'ł'=>'l',

        'Ñ'=>'N', 'Ń'=>'N', 'Ň'=>'N', 'Ņ'=>'N', 'Ŋ'=>'N',
        'ñ'=>'n', 'ń'=>'n', 'ň'=>'n', 'ņ'=>'n', 'ŋ'=>'n', 'ŉ'=>'n',

        'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ō'=>'O', 'Ŏ'=>'O', 'Ő'=>'O', 'Œ'=>'O',
        'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ō'=>'o', 'ŏ'=>'o', 'ő'=>'o', 'œ'=>'o', 'ð'=>'o',

        'Ŕ'=>'R', 'Ř'=>'R',
        'ŕ'=>'r', 'ř'=>'r', 'ŗ'=>'r',

        'Š'=>'S', 'Ŝ'=>'S', 'Ś'=>'S', 'Ş'=>'S',
        'š'=>'s', 'ŝ'=>'s', 'ś'=>'s', 'ş'=>'s',

        'Ŧ'=>'T', 'Ţ'=>'T', 'Ť'=>'T',
        'ŧ'=>'t', 'ţ'=>'t', 'ť'=>'t',

        'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ũ'=>'U', 'Ū'=>'U', 'Ŭ'=>'U', 'Ů'=>'U', 'Ű'=>'U', 'Ų'=>'U',
        'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ü'=>'u', 'ũ'=>'u', 'ū'=>'u', 'ŭ'=>'u', 'ů'=>'u', 'ű'=>'u', 'ų'=>'u',

        'Ŵ'=>'W', 'Ẁ'=>'W', 'Ẃ'=>'W', 'Ẅ'=>'W',
        'ŵ'=>'w', 'ẁ'=>'w', 'ẃ'=>'w', 'ẅ'=>'w',

        'Ý'=>'Y', 'Ÿ'=>'Y', 'Ŷ'=>'Y',
        'ý'=>'y', 'ÿ'=>'y', 'ŷ'=>'y',

        'Ž'=>'Z', 'Ź'=>'Z', 'Ż'=>'Z',
        'ž'=>'z', 'ź'=>'z', 'ż'=>'z',

        '“'=>'"', '”'=>'"', '‘'=>"'", '’'=>"'", '•'=>'-', '…'=>'...', '—'=>'-', '–'=>'-',
		'¿'=>'?', '¡'=>'!', '°'=>' degrees ',
		'²'=>'2','³'=>'3',
    	'¼'=>' 1/4 ', '½'=>' 1/2 ', '¾'=>' 3/4 ',
	    '⅓'=>' 1/3 ', '⅔'=>' 2/3 ', '⅛'=>' 1/8 ', '⅜'=>' 3/8 ', '⅝'=>' 5/8 ', '⅞'=>' 7/8 ',
        '÷'=>' divided by ', '×'=>' times ', '±'=>' plus-minus ',
		'√'=>' square root ', '∞'=>' infinity ',
        '≈'=>' almost equal to ', '≠'=>' not equal to ', '≡'=>' identical to ', '≤'=>' less than or equal to ', '≥'=>' greater than or equal to ',
        '←'=>' left ', '→'=>' right ', '↑'=>' up ', '↓'=>' down ', '↔'=>' left and right ', '↕'=>' up and down ',
        '℅'=>' care of ', '℮' => ' estimated ',
        'Ω'=>' ohm ',
        '♀'=>' female ', '♂'=>' male ',
        '©'=>' Copyright ', '®'=>' Registered ',
		'™' =>' Trademark ', '€'=>' EUR ',
		// special chars
		'&'=>' and ', '"'=>'', '>'=>'', '<'=>'', "''"=>'', 
    );

    $string = strtr($string, $table);
	$string = preg_replace('/[^\x20-\xFF]/u', '', $string);
    return $string;
}
function efattita_get_barcode($id_product){
	global $db;
	$sql="select p.barcode, b.code, b.libelle from ".MAIN_DB_PREFIX."product p, ".MAIN_DB_PREFIX."c_barcode_type b
	where b.rowid = p.fk_barcode_type
	and p.barcode IS NOT NULL
	and p.barcode != ''
	and p.rowid = $id_product";

    $result = $db->query($sql);
    if ($result) {
		// $row = $db->fetch_object($result);
        return $db->fetch_object($result);
	}
}

/**
* @param string tva
* @param string country (countrycode)
* @param string withCountryCode if 1 
* @return string tva (with or without countrycode)
**/
function getPartitaIva($tva, $countryCode, $withCountryCode=0){
	if($withCountryCode){
		$partitaIva = substr($tva, 0, 2) == $countryCode ? $tva: $countryCode.$tva;
	}else{
		$partitaIva = substr($tva, 0, 2) == $countryCode ? substr($tva, 2): $tva;
	}
	return $partitaIva;
	
}

function utf8_filter(string $value)	{
    return preg_replace('/[^[:print:]\n]/u', '', mb_convert_encoding($value, 'UTF-8', 'UTF-8'));
}

/**
 * Removes invalid characters from a UTF-8 XML string
 *
 * @access public
 * @param string a XML string potentially containing invalid characters
 * @return string
 */
function sanitizeXML($string)
{
    if (!empty($string)) 
    {
		// https://alvinalexander.com/php/how-to-remove-non-printable-characters-in-string-regex/
		// rimuove caratteri non stampabili con uno spazio in mezzo
		$string = preg_replace('/[[:^print:]] [[:^print:]]/', '', $string);
		$string = preg_replace('/[[:^print:]]/', '', $string);
		
		return $string;
	}
}

// controlla se la fattura è attiva o passiva
function is_fattura_attiva($xmlobject){
	global $mysoc;
	$search_fields = array();
	foreach(array('tva_intra','idprof4','idprof6') as $field){
		if ($mysoc->$field) {
			$search_fields[] = getPartitaIva($mysoc->$field, $mysoc->country_code);
		}
	}
	if ( in_array($xmlobject->FatturaElettronicaHeader->CedentePrestatore->DatiAnagrafici->IdFiscaleIVA->IdCodice, $search_fields)
	 || in_array($xmlobject->FatturaElettronicaHeader->CedentePrestatore->DatiAnagrafici->IdFiscaleIVA->CodiceFiscale, $search_fields) ) {
		return true;
	}else{
		return false;
	}
}

if (!function_exists('llx_query')) 
{
	function llx_query($sql)
	{
		global $db;
		if (MAIN_DB_PREFIX != 'llx_') {
			$sql = preg_replace('/llx_/i', MAIN_DB_PREFIX, $sql);
		}
		return $db->query($sql);
	}
} 
