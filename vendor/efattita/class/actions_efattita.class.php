<?php

dol_include_once('/efattita/lib/efattita.lib.php');
dol_include_once('/efattita/core/modules/modeFattITA.class.php');

class ActionsEfattita
{

	private $db;

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Overriding the doActions function : replacing the parent function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	function doActions($parameters, $object, $action, $hookmanager)
	{
        global $conf;
		global $invoice_predefined; // serve successivamente in showOptionals

		if (in_array('invoicecard', explode(':', $parameters['context'])))
		{
			// sezionale
			if (isset($object->array_options['options_sezionale'])) {
				if (isset($conf->global->FACTURE_MERCURE_MASK_INVOICE)) {
					$conf->global->FACTURE_MERCURE_MASK_INVOICE = str_replace('{S}',$object->array_options['options_sezionale'],$conf->global->FACTURE_MERCURE_MASK_INVOICE);
				}
				if (isset($conf->global->FACTURE_MERCURE_MASK_CREDIT)) {
					$conf->global->FACTURE_MERCURE_MASK_CREDIT = str_replace('{S}',$object->array_options['options_sezionale'],$conf->global->FACTURE_MERCURE_MASK_CREDIT);
				}
				if (isset($conf->global->FACTURE_MERCURE_MASK_DEPOSIT)) {
					$conf->global->FACTURE_MERCURE_MASK_DEPOSIT = str_replace('{S}',$object->array_options['options_sezionale'],$conf->global->FACTURE_MERCURE_MASK_DEPOSIT);
				}
				// not legal in Italy
				if (isset($conf->global->FACTURE_MERCURE_MASK_REPLACEMENT)) {
					$conf->global->FACTURE_MERCURE_MASK_REPLACEMENT = str_replace('{S}',$object->array_options['options_sezionale'],$conf->global->FACTURE_MERCURE_MASK_REPLACEMENT);
				}
			}

			// il bollo non va nel totale se è a carico del fornitore (update_price )
			if($action == 'update_extras'){
				if($_REQUEST['attribute'] == 'bollo'){
					$object->update_price(1);
				}
			}

			// valori di default
            if($action == 'create'){
                $extrafields = new ExtraFields($this->db);
                $extrafields->fetch_name_optionals_label($object->table_element);
				if(!empty($extrafields->attribute_default)){
					foreach ($extrafields->attribute_default as $key => $val)
					$object->array_options["options_".$key]=$val;
				}

				// facture fields
				if (!empty($conf->global->EFATTITA_DEFAULT_LAST_FIELDS) && $socid = GETPOST('socid','int')) {
					$sql = strtr('select :fields from llx_facture
					where fk_soc = :socid 
					order by rowid desc limit 1',[
						'llx_'		=> MAIN_DB_PREFIX,
						':fields'	=> $conf->global->EFATTITA_DEFAULT_LAST_FIELDS,
						':socid'	=> $socid
					]);
					$resql = $this->db->query($sql);
					if ($row = $this->db->fetch_object($resql)) {
						foreach ($row as $field => $value) {
							if ($field == 'fk_cond_reglement') {
								$field = 'cond_reglement_id';
							}
							if ($field == 'fk_mode_reglement') {
								$field = 'mode_reglement_id';
							}
							$_POST[$field] = $value;
							// $object->$field = $value;
						}
					}
				}
            }
		}
		return 0;
	}


	function formObjectOptions($parameters, $object, $action, $hookmanager){
		global $conf, $soc;
		global $invoice_predefined;

		if ($parameters['currentcontext'] == 'invoicecard')
		{
			if ($invoice_predefined && GETPOST('fac_rec','int')) {
				// importa i campi extra da modello fattura
				$object->fetch_optionals();
				$object->array_options = array_merge($object->array_options, $invoice_predefined->array_options);
			}else{
				// campi di default
				$socid = GETPOST('socid','int');
				if ($socid) {
	
					// $conf->global->THIRDPARTY_PROPAGATE_EXTRAFIELDS_TO_INVOICE = 1;
	
					
					// facture fields
					if (!empty($conf->global->EFATTITA_DEFAULT_LAST_FIELDS)) {
						$sql = strtr('select :fields from llx_facture
						where fk_soc = :socid 
						order by rowid desc limit 1',[
							'llx_'		=> MAIN_DB_PREFIX,
							':fields'	=> $conf->global->EFATTITA_DEFAULT_LAST_FIELDS,
							':socid'	=> $socid
						]);
						$resql = $this->db->query($sql);
						if ($row = $this->db->fetch_object($resql)) {
							foreach ($row as $field => $value) {
								if ($field == 'fk_cond_reglement') {
									$field = 'cond_reglement_id';
								}
								if ($field == 'fk_mode_reglement') {
									$field = 'mode_reglement_id';
								}
								$_POST[$field] = $value;
							}
						}
					}
				}
			}
		}
		return 0;
	}



	function showOptionals($parameters, $object, $action, $hookmanager)
	{
        global $conf, $soc, $cond_reglement_id;

		if ($parameters['currentcontext'] == 'invoicecard')
		{
			$socid = GETPOST('socid','int');
            if($action == 'create'){
				if ($socid) {

					// facture extrafields
					if (!empty($conf->global->EFATTITA_DEFAULT_LAST_EXTRAFIELDS)) {
						$sql = strtr('select :fields from llx_facture f
						left join llx_facture_extrafields fe
							on fe.fk_object = f.rowid
						where f.fk_soc = :socid 
						order by f.rowid desc limit 1',[
							'llx_'		=> MAIN_DB_PREFIX,
							':fields'	=> $conf->global->EFATTITA_DEFAULT_LAST_EXTRAFIELDS,
							':socid'	=> $socid
						]);
						$resql = $this->db->query($sql);
						if ($row = $this->db->fetch_object($resql)) {
							foreach ($row as $field => $value) {
								$_POST['options_' . $field] = $value;
							}
						}
					}
					
				}
            }
		}
		return 0;
	}

	

	// gestione bollo nel PDF
	function beforePDFCreation($parameters, $object, $action, $hookmanager){
		global $conf, $outputlangs;

		if (in_array('invoicecard', explode(':', $parameters['context']))) {
			// se non a carico del cliente
			if($object->array_options['options_bollo'] < 2){
				$object->revenuestamp = 0;
			}
	
			$outputlangs = $parameters['outputlangs'] ? $parameters['outputlangs'] : $outputlangs;
			// in caso di regime forfettario
			if ($conf->global->RegimeFiscale == 'RF19') {
				// toglie la colonna dell'iva
				$conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_COLUMN = 1;
				$conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT = 0;
				// toglie l'indicazione IVA nel totale
				$outputlangs->tab_translate['TotalTTC'] = $outputlangs->tab_translate['Total'];
				// aggiunge la nota
				$object->note_public .= "\nOperazione senza applicazione dell'IVA, effettuata ai sensi dell’articolo 1, commi da 54 a 89, l. n. 190 del 2014 così come modificato dalla l. n. 208 del 2015 e dalla l. n. 145 del 2018";
			}
		}

	}
	function addMoreActionsButtons($parameters, $object, $action, $hookmanager){
		global $conf, $langs, $user, $mysoc;

		$facture_html_path = dol_buildpath('efattita/facture_html.php',1);
		
		if ($parameters['currentcontext'] == 'invoicecard'){
		
		// anteprima della fattura
			if (class_exists('XSLTProcessor')){
			echo "
			<script>
				$('document').ready(function(){
					$('.documentdownload').each(function(){
						if($(this).attr('href').match(/\.xml/)){

							// non funziona, preview non gestisce i file xml
							var link = $(this).attr('href') + '&attachment=0';
							var newlink = link.replace('/document.php', '$facture_html_path');
							var html = '<a class=\"pictopreview documentpreview\" href=\"' + newlink + '\" mime=\"text/html\" target=\"_blank\"><i class=\"fa fa-search-plus\" style=\"color: gray\"></i></a>';

							// funziona solo firefox
							// var link = $(this).attr('href');

							// var link = $(this).attr('href');
							// var newlink = link.replace('/document.php', '$facture_html_path');
							// var html = '<a class=\"pictopreview\" href=\"' + newlink + '\" mime=\"text/css\" target=\"_blank\"><i class=\"fa fa-search-plus\" style=\"color: gray\"></i></a>';

							$(this).closest('span').addClass('widthcentpercentminusx');
							$(this).closest('td').append(html);
						}
					});
				});
			</script> ";
			}
		}
		if ($parameters['currentcontext'] == 'thirdpartycard' || $parameters['currentcontext'] == 'thirdpartycomm'){
		// Rimanenza valore intento
			if($object->array_options['options_protocollo_intento'] && $object->array_options['options_valore_intento']){
				$sql = strtr("select sum(f.total_ht) as total
					from llx_societe s
					left join llx_societe_extrafields se
						on se.fk_object = s.rowid
					left join llx_facture f
						on f.fk_soc = s.rowid
					left join llx_facture_extrafields fe
						on fe.fk_object = f.rowid
					where s.rowid = :id
					and fe.protocollo_intento = ':protocollo_intento'",[
						'llx_' => MAIN_DB_PREFIX,
						':protocollo_intento' => $object->array_options['options_protocollo_intento'],
						':id' => $object->id
					]);
				$resql = $this->db->query($sql);
				$res = $resql->fetch_object();
				$rimanenza = $object->array_options['options_valore_intento'] - $res->total;
				echo '<script>
				$(document).ready(function(){
					$( "[id*=\'valore_intento\']" ).append(" ").append("( Rimanenza: " + ' . $rimanenza . ' + ")")
				});
				</script>';
			}

		}

	}
	function beforeBodyClose($parameters, $object, $action, $hookmanager) {
		global $conf, $object, $id, $ref;

		if ($parameters['currentcontext'] == 'fileslib' && isset($object->element) && $object->element == 'facture') {

			$doc_id = GETPOST('doc_id', 'int');
			
		// azioni
			switch (GETPOST('action', 'alpha')) {
				case 'attach':
					$sql = strtr('insert into llx_ecm_files_extrafields (fk_object, efattita_attach) values (:doc_id, 1) on duplicate key update efattita_attach = 1',[
						'llx_' => MAIN_DB_PREFIX,
						':doc_id' => $doc_id
					]);
					$resql = $this->db->query($sql);
					break;
				case 'detach':
					$sql = strtr('update llx_ecm_files_extrafields set efattita_attach  = 0 where fk_object = :doc_id',[
						'llx_' => MAIN_DB_PREFIX,
						':doc_id' => $doc_id
					]);
					$this->db->query($sql);
					break;
				default:
					# code...
					break;
			}

			
		// aggiunge i documenti linkati
		if ($_SERVER['DOCUMENT_URI'] == '/compta/facture/document.php') {
			if ($object->fetchObjectLinked()) {
				foreach ($object->linkedObjects as $type) {
					foreach ($type as $linked_object) {
						$upload_dir = $conf->{$linked_object->element}->multidir_output[$linked_object->entity].'/'.dol_sanitizeFileName($linked_object->ref);
						$file_list = dol_dir_list($upload_dir, "files", 0, '', '(\.meta|_preview.*\.png)$', $sortfield, (strtolower($sortorder) == 'desc' ?SORT_DESC:SORT_ASC), 1, 1);
						$relativedir = preg_replace('/^'.preg_quote(DOL_DATA_ROOT, '/').'/', '', $upload_dir);
						$relativedir = preg_replace('/^[\\/]/', '', $relativedir);
						for ($i=0; $i < count($file_list); $i++) { 
							$sql = strtr("select * from llx_ecm_files where filename = ':filename'",[
								'llx_'		=> MAIN_DB_PREFIX,
								':filename'	=> $file_list[$i]['relativename']
							]);
							$resql = $this->db->query($sql);
							$row = $this->db->fetch_object($resql);
							$attach_list[] = array_merge($file_list[$i], (array)$row);
						}
				
				// 		completeFileArrayWithDatabaseInfo($file_list, $relativedir);
				// 		unset($file_list[0]['path']);
				// 		unset($file_list[0]['fullname']);
				// 		$this->results = array_merge($this->results, $file_list);
	
					}
				}
			}
			echo '<script>
			// aggiunge documenti linkati
			';
			for ($i=0; $i < count($attach_list); $i++) {
				$html = strtr('<tr class="oddeven" id="row-__rowid__">\
					<td class="minwith200 tdoverflowmax500">\
						<a class="paddingright valignmiddle" href="/document.php?modulepart=__src_object_type__&entity=1&file=__level1name__/__relativename__">\
							<i class="fa fa-file-pdf-o paddingright inline-block valignmiddle paddingright" title="__relativename__ (__size__ Byte)"></i>__relativename__\
						</a>\
						<a class="pictopreview documentpreview" href="/document.php?modulepart=__src_object_type__&attachment=0&file=__level1name__/__relativename__&&amp;entity=1" mime="application/octet-stream" target="_blank">\
						<span class="fa fa-search-plus pictofixedwidth" style="color: gray"></span></a>\
					</td>\
					<td class="right nowraponall">__size__ b</td>\
					<td class="center nowraponall">__datetime__</td>\
					<td class="center">&nbsp;</td><td class="center"></td>\
					<td class="valignmiddle right actionbuttons nowraponall">\
					</td>\
				</tr>',[
					'__rowid__'				=> $attach_list[$i]['rowid'],
					'__relativename__'		=> $attach_list[$i]['relativename'],
					'__size__'				=> $attach_list[$i]['size'],
					'__datetime__'			=> dol_print_date($attach_list[$i]['date'], "%d/%m/%Y %H:%M"),
					'__src_object_type__'	=> $attach_list[$i]['src_object_type'],
					'__level1name__'		=> $attach_list[$i]['level1name'],
				]);
				echo "$('#tablelines tbody').append('$html');";
			}
			echo '</script>';
		};



		// funziona solo per gli allegati della fattura
		// negli altri casi essendo diverso l'oggetto (commande ad esempio)
		// sia src_object_type che src_object_id sono diersi
		// $sql = strtr('SELECT
		// 	fe.fk_object
		// FROM
		// 	llx_ecm_files f
		// LEFT JOIN llx_ecm_files_extrafields fe ON
		// 	f.rowid = fe.fk_object
		// WHERE
		// 	f.src_object_type = \':element\'
		// AND
		// 	f.src_object_id = :fac_id
		// AND fe.efattita_attach = 1',[
		// 	'llx_'		=> MAIN_DB_PREFIX,
		// 	':fac_id'	=> $id,
		// 	':filepath' => 'facture/' . $this->db->escape($object->ref),
		// 	':element'	=> $object->element
		// ]);

		// il contro è che un file allegato ad una fattura si allega
		// automaticamente ad ogni altra fattura legata allo stesso oggetto
		$sql = strtr('SELECT
			fe.fk_object
		FROM
			llx_ecm_files_extrafields fe
		WHERE
			fe.efattita_attach = 1',[
			'llx_'		=> MAIN_DB_PREFIX,
		]);

		$resql = $this->db->query($sql);


		echo "\n	<script>
		// aggiunge colonna per allegare in fattura elettronica
		var id = {$object->id};
		var attached_files = [];
		var checked;";

		if ($resql->num_rows) {
			while ($row = $this->db->fetch_object($resql)) {
				echo "\n	attached_files[{$row->fk_object}] = 1;";
			}
		}
					
		echo "		
			$('#tablelines tr:first').append('<td class=\"right\">In fattura elettronica</td>');
			$('#tablelines tr:not(:first)').each(function(){
				var doc_id = $(this).attr('id').replace('row-', '');
				checked = attached_files[doc_id] ? 'checked' : '';
				action = attached_files[doc_id] ? 'detach' : 'attach';
				$(this).append(\"<td class='right'><input class='efattita_attach' data-id='\" + doc_id + \"' type='checkbox' \" + checked + \"/></td>\");

			});
			$('.efattita_attach').change(function(){
				var nuovoUrl = '" . $_SERVER['PHP_SELF'] . "?facid=' + id + '&action=' + (this.checked?'attach':'detach') + '&doc_id=' + $(this).data('id');
				// alert(this.checked);
				window.location.href = nuovoUrl;
			});
			</script>
			";
		}

		return 0;
	}

	/**
	* Overloading the addMoreMassActions function : replacing the parent's function with the one below
	*
	* @param   array           $parameters     Hook metadatas (context, etc...)
	* @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	* @param   string          $action         Current action (if set). Generally create or edit or null
	* @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	* @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	*/
	public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;
		$error = 0; // Error counter
		
		if (in_array($parameters['currentcontext'], array('invoicelist')))		// do something only for the context 'somecontext1' or 'somecontext2'
		{
			$this->resprints = '<option data-html=\'<span class="efattita_send  em080 pictofixedwidth" style=""></span>' . $langs->trans("SendToSdi") . '\' value="efattita_send">'.$langs->trans("SendToSdi").'</option>';
			$this->resprints .= '<option data-html=\'<span class="efattita_send  em080 pictofixedwidth" style=""></span>' . $langs->trans("GenerateXml") . '\' value="efattita_generate_xml">'.$langs->trans("GenerateXml").'</option>';
		}
		
		if (! $error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

	/**
	* Overloading the doActions function : replacing the parent's function with the one below
	*
	* @param   array           $parameters     Hook metadatas (context, etc...)
	* @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	* @param   string          $action         Current action (if set). Generally create or edit or null
	* @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	* @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	*/
	public function doMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$massaction = GETPOST('massaction', 'alpha');

		$langs->loadLangs(array( "efattita@efattita"));
		
		$error = 0; // Error counter
		
		if (in_array($parameters['currentcontext'], array('invoicelist')))
		{

			if(empty($parameters['toselect'])){
				return;
			}

			$facture = new Facture($this->db);

			switch ($massaction) {

				case 'efattita_send':

					foreach ($parameters['toselect'] as $toselectid) {
						$facture->fetch($toselectid);
						try {
							ArubaSDI::sendInvoice($facture);
						} catch (\Throwable $th) {
							setEventMessages($facture->ref . ': ' . $th->getMessage(), null, 'warnings');
						}
					}
					break;

				case 'efattita_generate_xml':

					$this->db->begin();
					$nbok = 0;

					foreach ($parameters['toselect'] as $toselectid) {
						$result = $facture->fetch($toselectid);
						if ($result > 0) {
							$outputlangs = $langs;
							$newlang = '';
				
							if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang) && GETPOST('lang_id', 'aZ09')) {
								$newlang = GETPOST('lang_id', 'aZ09');
							}
							if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang) && isset($facture->thirdparty->default_lang)) {
								$newlang = $facture->thirdparty->default_lang; // for proposal, order, invoice, ...
							}
							if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang) && isset($facture->default_lang)) {
								$newlang = $facture->default_lang; // for thirdparty
							}
							if ($conf->global->MAIN_MULTILANGS && empty($newlang) && empty($facture->thirdparty)) { //load lang from thirdparty
								$facture->fetch_thirdparty();
								$newlang = $facture->thirdparty->default_lang; // for proposal, order, invoice, ...
							}
							if (!empty($newlang)) {
								$outputlangs = new Translate("", $conf);
								$outputlangs->setDefaultLang($newlang);
							}
				
							// To be sure vars is defined
							if (empty($hidedetails)) {
								$hidedetails = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0);
							}
							if (empty($hidedesc)) {
								$hidedesc = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0);
							}
							if (empty($hideref)) {
								$hideref = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0);
							}
							if (empty($moreparams)) {
								$moreparams = null;
							}
				
							$result = $facture->generateDocument('efattita', $outputlangs, $hidedetails, $hidedesc, $hideref, $moreparams);
				
							if ($result <= 0) {
								setEventMessages($facture->error, $facture->errors, 'errors');
								$error++;
								break;
							} else {
								$nbok++;
							}
						} else {
							setEventMessages($facture->error, $facture->errors, 'errors');
							$error++;
						break;
						}
					}
				
					if (!$error) {
						if ($nbok > 1) {
							setEventMessages($langs->trans("RecordsGenerated", $nbok), null, 'mesgs');
						} else {
							setEventMessages($langs->trans("RecordGenerated", $nbok), null, 'mesgs');
						}
						$this->db->commit();
					} else {
						$this->db->rollback();
					}
				break;
			}
		}
		
		return 0;
	}

	// Facture box alert
	function addHtmlHeader($parameters, &$object, &$action, &$hookmanager){
		global $conf;

		if (isset($conf->arubasdi->enabled) && $conf->arubasdi->enabled && $parameters['currentcontext'] == 'index') {
			$sql = strtr('select count(rowid) as unsent from llx_facture_extrafields where statoFattura = 0',[
				'llx_' => MAIN_DB_PREFIX
			]);
			$resql = $this->db->query($sql);
			$result = $this->db->fetch_object($resql);
	
	
	
			if (in_array('index', explode(':', $parameters['context']))){
				$this->resprints = '<script>
				$(function(){
					var html = $(\'<div class="info-box-lines"> \
										<div class="info-box-line"> \
											<a href="/compta/facture/list.php?mainmenu=billing&amp;leftmenu=customers_bills&amp;search_options_statoFattura=0" class="info-box-text info-box-text-a"> \
											<span class="marginrightonly" title="Fatture attive non inviate">Non inviate</span> \
												<span class="classfortooltip badge badge-warning"> \
												<i class="fa fa-exclamation-triangle"></i> ' . $result->unsent . '</span> \
											</a> \
										</div> \
									</div>\');
					$(\'.bg-infobox-facture\').next().append(html);
				})
			</script>';
			}
		}
		return 0;
	}



	// check update
	function insertExtraHeader($parameters, &$object, &$action, &$hookmanager){
		global $conf, $langs;
		if (in_array('adminmodules', explode(':', $parameters['context'])))
		{
			if($conf->global->EFATTITA_CHECK_UPDATES)
			{
			/*
			  Check updates
			*/
				$module = new modeFattITA($this->db);
				if(!$module->url_last_version){
					return;
				}
				echo "
				<script>
				$('document').ready(function(){
					$.get('{$module->url_last_version}',function(last_version){
						var res = cmp('{$module->version}',last_version);
						var \$msgbox = $('#{$module->name}_modules_updates');
						if(res == -1){
							\$.jnotify('" . $langs->trans('UpdateAvalilable') . ': ' .  $module->name . ': ' . $module->version . " -> '+ last_version +' " . " . \
							" . $langs->trans('DownloadAt') . ": <a target=\"_blank\" href=\"https://www.linx.ws/mio-account/downloads/\">https://www.linx.ws/mio-account/downloads/\</a>',
							);
	 						}
					});
				});
				function cmp (a, b) {
					var pa = a.split('.');
					var pb = b.split('.');
					for (var i = 0; i < 3; i++) {
						var na = Number(pa[i]);
						var nb = Number(pb[i]);
						if (na > nb) return 1;
						if (nb > na) return -1;
						if (!isNaN(na) && isNaN(nb)) return 1;
						if (isNaN(na) && !isNaN(nb)) return -1;
					}
					return 0;
				};
				</script>";
			}
		}
		return 0;
	}
	function commonGenerateDocument($parameters, &$object, &$action, &$hookmanager) {
		global $langs, $xml_generated;
		if (!$xml_generated && in_array('invoicecard', explode(':', $parameters['context']))) {
			$xml_generated = true;
			$result = $object->generateDocument('efattita', $langs);
		}
		return 0;
	}

}