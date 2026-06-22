<?php
/* Copyright (C) 2023 SuperAdmin
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    dolibarrassistant/class/actions_dolibarrassistant.class.php
 * \ingroup dolibarrassistant
 * \brief   Example hook overload.
 *
 * Put detailed description here.
 */

/**
 * Class ActionsDolibarrAssistant
 */
class ActionsDolibarrAssistant
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var array Errors
	 */
	public $errors = array();


	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var int		Priority of hook (50 is used if value is not defined)
	 */
	public $priority;


	/**
	 * Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}


	/**
	 * Execute action
	 *
	 * @param	array			$parameters		Array of parameters
	 * @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action      	'add', 'update', 'view'
	 * @return	int         					<0 if KO,
	 *                           				=0 if OK but we want to process standard actions too,
	 *                            				>0 if OK and we want to replace standard actions.
	 */
	public function getNomUrl($parameters, &$object, &$action)
	{
		global $db, $langs, $conf, $user;
		$this->resprints = '';
		return 0;
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
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) { // do something only for the context 'somecontext1' or 'somecontext2'
			// Do what you want here...
			// You can for example call global vars like $fieldstosearchall to overwrite them, or update database depending on $action and $_POST values.
		}

		if (!$error) {
			$this->results = array('myreturn' => 999);
			$this->resprints = 'A text to show';
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}


	/**
	 * Overloading the doMassActions function : replacing the parent's function with the one below
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

		$error = 0; // Error counter

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) { // do something only for the context 'somecontext1' or 'somecontext2'
			foreach ($parameters['toselect'] as $objectid) {
				// Do action on each object id
			}
		}

		if (!$error) {
			$this->results = array('myreturn' => 999);
			$this->resprints = 'A text to show';
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
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
		$disabled = 1;

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) { // do something only for the context 'somecontext1' or 'somecontext2'
			$this->resprints = '<option value="0"' . ($disabled ? ' disabled="disabled"' : '') . '>' . $langs->trans("DolibarrAssistantMassAction") . '</option>';
		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}



	/**
	 * Execute action
	 *
	 * @param	array	$parameters     Array of parameters
	 * @param   Object	$object		   	Object output on PDF
	 * @param   string	$action     	'add', 'update', 'view'
	 * @return  int 		        	<0 if KO,
	 *                          		=0 if OK but we want to process standard actions too,
	 *  	                            >0 if OK and we want to replace standard actions.
	 */
	public function beforePDFCreation($parameters, &$object, &$action)
	{
		global $conf, $user, $langs;
		global $hookmanager;

		$outputlangs = $langs;

		$ret = 0;
		$deltemp = array();
		dol_syslog(get_class($this) . '::executeHooks action=' . $action);

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) { // do something only for the context 'somecontext1' or 'somecontext2'
		}

		return $ret;
	}

	/**
	 * Execute action
	 *
	 * @param	array	$parameters     Array of parameters
	 * @param   Object	$pdfhandler     PDF builder handler
	 * @param   string	$action         'add', 'update', 'view'
	 * @return  int 		            <0 if KO,
	 *                                  =0 if OK but we want to process standard actions too,
	 *                                  >0 if OK and we want to replace standard actions.
	 */
	public function afterPDFCreation($parameters, &$pdfhandler, &$action)
	{
		global $conf, $user, $langs;
		global $hookmanager;

		$outputlangs = $langs;

		$ret = 0;
		$deltemp = array();
		dol_syslog(get_class($this) . '::executeHooks action=' . $action);

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {
			// do something only for the context 'somecontext1' or 'somecontext2'
		}

		return $ret;
	}



	/**
	 * Overloading the loadDataForCustomReports function : returns data to complete the customreport tool
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function loadDataForCustomReports($parameters, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$langs->load("dolibarrassistant@dolibarrassistant");

		$this->results = array();

		$head = array();
		$h = 0;

		if ($parameters['tabfamily'] == 'dolibarrassistant') {
			$head[$h][0] = dol_buildpath('/module/index.php', 1);
			$head[$h][1] = $langs->trans("Home");
			$head[$h][2] = 'home';
			$h++;

			$this->results['title'] = $langs->trans("DolibarrAssistant");
			$this->results['picto'] = 'dolibarrassistant@dolibarrassistant';
		}

		$head[$h][0] = 'customreports.php?objecttype=' . $parameters['objecttype'] . (empty($parameters['tabfamily']) ? '' : '&tabfamily=' . $parameters['tabfamily']);
		$head[$h][1] = $langs->trans("CustomReports");
		$head[$h][2] = 'customreports';

		$this->results['head'] = $head;

		return 1;
	}



	/**
	 * Overloading the restrictedArea function : check permission on an object
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int 		      			  	<0 if KO,
	 *                          				=0 if OK but we want to process standard actions too,
	 *  	                            		>0 if OK and we want to replace standard actions.
	 */
	public function restrictedArea($parameters, &$action, $hookmanager)
	{
		global $user;

		if ($parameters['features'] == 'myobject') {
			if ($user->rights->dolibarrassistant->myobject->read) {
				$this->results['result'] = 1;
				return 1;
			} else {
				$this->results['result'] = 0;
				return 1;
			}
		}

		return 0;
	}

	/**
	 * Execute action completeTabsHead
	 *
	 * @param   array           $parameters     Array of parameters
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         'add', 'update', 'view'
	 * @param   Hookmanager     $hookmanager    hookmanager
	 * @return  int                             <0 if KO,
	 *                                          =0 if OK but we want to process standard actions too,
	 *                                          >0 if OK and we want to replace standard actions.
	 */
	public function completeTabsHead(&$parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf, $user;

		if (!isset($parameters['object']->element)) {
			return 0;
		}
		if ($parameters['mode'] == 'remove') {
			// utilisé si on veut faire disparaitre des onglets.
			return 0;
		} elseif ($parameters['mode'] == 'add') {
			$langs->load('dolibarrassistant@dolibarrassistant');
			// utilisé si on veut ajouter des onglets.
			$counter = count($parameters['head']);
			$element = $parameters['object']->element;
			$id = $parameters['object']->id;
			// verifier le type d'onglet comme member_stats où ça ne doit pas apparaitre
			// if (in_array($element, ['societe', 'member', 'contrat', 'fichinter', 'project', 'propal', 'commande', 'facture', 'order_supplier', 'invoice_supplier'])) {
			if (in_array($element, ['context1', 'context2'])) {
				$datacount = 0;

				$parameters['head'][$counter][0] = dol_buildpath('/dolibarrassistant/dolibarrassistant_tab.php', 1) . '?id=' . $id . '&amp;module=' . $element;
				$parameters['head'][$counter][1] = $langs->trans('DolibarrAssistantTab');
				if ($datacount > 0) {
					$parameters['head'][$counter][1] .= '<span class="badge marginleftonlyshort">' . $datacount . '</span>';
				}
				$parameters['head'][$counter][2] = 'dolibarrassistantemails';
				$counter++;
			}
			if ($counter > 0 && (int) DOL_VERSION < 14) {
				$this->results = $parameters['head'];
				// return 1 to replace standard code
				return 1;
			} else {
				// en V14 et + $parameters['head'] est modifiable par référence
				return 0;
			}
		}
	}





	function printLeftBlock()
	{
		global $user, $conf, $langs;
		unset($_SESSION["AssistantQuestion"]);
		$langs->loadLangs(array("dolibarrassistant@dolibarrassistant"));

		$htmlChatScript = '
		<link
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
      rel="stylesheet"
    />
    <link
      href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&display=swap"
      rel="stylesheet"
    />
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.2.0/mdb.min.css"
      rel="stylesheet"
    />
		
		<!-- Chat Box Area -->
  <section class="bot-wrapper">
    <div class="bot-content">
      <div class="card botBox" id="botBox" style="border-radius: 15px">
        <div
          class="card-header d-flex justify-content-between align-items-center p-3 bg-info text-white border-bottom-0"
          style="
                border-top-left-radius: 15px;
                border-top-right-radius: 15px;
              ">
          <p class="mb-0 fw-bold">DoliAssistant</p>
          <i class="fas fa-times" onclick="toggleChatBox()"></i>
        </div>
        <div class="card-body">
          <!-- ======== ConverSation Start ======== -->
          <div id="conversation_area" class="bot-box-conversation">
            <!-- Message Left Item -->
            <div class="d-flex flex-row justify-content-start mb-4">
              <img src="'.DOL_URL_ROOT.'/custom/dolibarrassistant/img/dolibarr.png" alt="avatar 1"
                style="width: 45px; height: 100%" />
              <div class="p-3 ms-3" style="
                      border-radius: 15px;
                      background-color: rgba(57, 192, 237, 0.2);
                    ">
                <p class="small mb-0">
                  '.$langs->trans('ImYourAssistant').'
                </p>
              </div>
            </div>
            <!-- Message Left Item End

            <!-- Message Right Item
            <div class="d-flex flex-row justify-content-end mb-4">
              <div class="p-3 me-3 border" style="border-radius: 15px; background-color: #fbfbfb">
                <p class="small mb-0">
                  Thank you, I really like your product.
                </p>
              </div>
              <img src="'.DOL_URL_ROOT.'/public/theme/common/user_anonymous.png" alt="avatar 1"
                style="width: 45px; height: 100%" />
            </div>
            <!-- Message Right Item End -->

            <!-- Message Left Item
            <div class="d-flex flex-row justify-content-start mb-4">
              <img src="'.DOL_URL_ROOT.'/public/theme/common/user_anonymous.png" alt="avatar 1"
                style="width: 45px; height: 100%" />
              <div class="p-3 ms-3" style="
                      border-radius: 15px;
                      background-color: rgba(57, 192, 237, 0.2);
                    ">
                <p class="small mb-0">
                  You can start loop here. So the "Message Left Item"
                  message will come from Bot.
                </p>
              </div>
            </div>
            <!-- Message Left Item End -->

            <!-- Message Right Item 
            <div class="d-flex flex-row justify-content-end mb-4">
              <div class="p-3 me-3 border" style="border-radius: 15px; background-color: #fbfbfb">
                <p class="small mb-0">
                  Okay Understand. So the "Message Right Item" message will
                  go from the user.
                </p>
              </div>
              <img src="'.DOL_URL_ROOT.'/public/theme/common/user_anonymous.png" alt="avatar 1"
                style="width: 45px; height: 100%" />
            </div>
            <!-- Message Right Item End -->

            <!-- Message Left Item
            <div class="d-flex flex-row justify-content-start mb-4">
              <img src="https://mdbcdn.b-cdn.net/img/Photos/new-templates/bootstrap-chat/ava1-bg.webp" alt="avatar 1"
                style="width: 45px; height: 100%" />
              <div class="p-3 ms-3" style="
                      border-radius: 15px;
                      background-color: rgba(57, 192, 237, 0.2);
                    ">
                <p class="small mb-0">Yes Correct. You are right.</p>
              </div>
            </div>
            <!-- Message Left Item End -->

            <!-- Message Right Item
            <div class="d-flex flex-row justify-content-end mb-4">
              <div class="p-3 me-3 border" style="border-radius: 15px; background-color: #fbfbfb">
                <p class="small mb-0">
                  All Right. Thank you for your help.
                </p>
              </div>
              <img src="'.DOL_URL_ROOT.'/public/theme/common/user_anonymous.png" alt="avatar 1"
                style="width: 45px; height: 100%" />
            </div>
            <!-- Message Right Item End -->
          </div>
          <!-- ======== ConverSation END ======== -->
          <div class="writing-area">
            <div class="form-outline">
              <textarea class="form-control" id="messageInput" rows="4"></textarea>
              <label class="form-label" for="messageInput">'.$langs->trans('TypeYourMessage').'</label>
            </div>
            <button id="sendBtn" class="btn btn-primary mt-2">OK</button>
          </div>
        </div>
      </div>
      <button id="botStartBtn" class="bot-start-btn" data-status="0">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
          <!--! Font Awesome Pro 6.4.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. -->
          <path
            d="M64 0C28.7 0 0 28.7 0 64V352c0 35.3 28.7 64 64 64h96v80c0 6.1 3.4 11.6 8.8 14.3s11.9 2.1 16.8-1.5L309.3 416H448c35.3 0 64-28.7 64-64V64c0-35.3-28.7-64-64-64H64z"
            fill="#fff" />
        </svg>
      </button>
    </div>
  </section>
  <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.2.0/mdb.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
  <script>
    // elm
	AIPrompt="";
    botBox = document.getElementById("botBox");
    botStartBtn = document.getElementById("botStartBtn");
    chatIconSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--! Font Awesome Pro 6.4.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. --><path d="M64 0C28.7 0 0 28.7 0 64V352c0 35.3 28.7 64 64 64h96v80c0 6.1 3.4 11.6 8.8 14.3s11.9 2.1 16.8-1.5L309.3 416H448c35.3 0 64-28.7 64-64V64c0-35.3-28.7-64-64-64H64z" fill="#fff" /> </svg>`;
    CLoseIconSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">  <!--! Font Awesome Pro 6.4.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. -->  <path d="M432 256c0 17.7-14.3 32-32 32L48 288c-17.7 0-32-14.3-32-32s14.3-32 32-32l352 0c17.7 0 32 14.3 32 32z" fill="#fff" /></svg>`;
    // toggle box
    function toggleChatBox() {
      botBox.classList.toggle("show__chat");
      if (botStartBtn.getAttribute("data-status") === "1") {
        botStartBtn.innerHTML = chatIconSvg;
        botStartBtn.setAttribute("data-status", "0");
      } else {
        botStartBtn.innerHTML = CLoseIconSvg;
        botStartBtn.setAttribute("data-status", "1");
        // scroll to bottom of conversation
        $("#conversation_area").scrollTop($("#conversation_area")[0].scrollHeight);
      }
    }

    // insert user message
    function insertUserMessage(message) {
      var elm = `<div class="d-flex flex-row justify-content-end mb-4">
              <div class="p-3 me-3 border" style="border-radius: 15px; background-color: #fbfbfb">
                <p class="small mb-0">
                  ${message}
                </p>
              </div>
              <img src="'.DOL_URL_ROOT.'/public/theme/common/user_anonymous.png" alt="avatar 1" style="width: 45px; height: 100%">
            </div>`;
      var wrapper = $("#conversation_area");
      var messageInput = $("#messageInput");

      // append new message
      wrapper.append(elm);
      // scroll to bottom
      wrapper.scrollTop(wrapper[0].scrollHeight);
      // clear message textarea
      messageInput.val("")
    }

    // insert bot response
    function insertBotResponse(response) {
      var elm = `<div class="d-flex flex-row justify-content-start mb-4">
              <img src="'.DOL_URL_ROOT.'/custom/dolibarrassistant/img/dolibarr.png" alt="avatar 1" style="width: 45px; height: 100%">
              <div class="p-3 ms-3" style="
                      border-radius: 15px;
                      background-color: rgba(57, 192, 237, 0.2);
                    ">
                <p class="small mb-0">${response}</p>
              </div>
            </div>`;
      var wrapper = $("#conversation_area");
      var messageInput = $("#messageInput");
      // append new message
      wrapper.append(elm);
      // scroll to bottom
      wrapper.scrollTop(wrapper[0].scrollHeight);
      // clear message textarea
      messageInput.val("");
    }

    // send message
    function sendMessageRequest(message) {
      // insert user message
	  if (message.includes("AssistantCustomerID")==false) if (message.includes("AssistantCustomerName")==false) if (message.includes("AssistantAIresponse")==false) insertUserMessage(message);

      // path of php
			var host = window.location.protocol + "//" + window.location.host + "'.DOL_URL_ROOT.'";
      var apiPath = host+"/custom/dolibarrassistant/class/chat.php";

      axios.post(apiPath, {
        message: message
      }).then((response) => {
        // set second for wait
        var waitingSec = 500;
        setTimeout(() => {
          if (message.includes("AssistantCustomerID")==false) if (message.includes("AssistantCustomerName")==false) if (message.includes("AssistantAIresponse")==false) insertBotResponse(response.data)
        }, waitingSec);
      }).catch((error) => {
        console.log(error);
      })
    }
	
	function sendYes()
	{
		sendMessageRequest("'.$langs->trans("Yes").'");
	}
	

	
	

	
	

	

	

    // get message and call send function
    function sendMessage() {
      if ($("#messageInput").val()) {
        // call send message function with message input
        if (AssistantAI==1) sendMessageRequestAI($("#messageInput").val());
		else if (AssistantAI==2) sendMessageRequestIMG($("#messageInput").val());
		else sendMessageRequest ($("#messageInput").val());
      }
    }

    // on click btn
    $(document).ready(function () {
	  AssistantAI=0;
      botStartBtn.addEventListener("click", toggleChatBox);
      $("#sendBtn").click(function () {
        // on click send btn
        sendMessage();
      })
    });
  </script>
			';


		$this->resprints = $htmlChatScript;

		return 0;
	}
}