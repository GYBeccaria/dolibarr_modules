<?php
/* Copyright (C) - 2023	Andreu Bisquerra Gaya    <jove@bisquerra.com>

 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *       \file       htdocs/public/ticket/index.php
 *       \ingroup    ticket
 *       \brief      Public page to add and manage ticket
 */

if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOLOGIN')) {
	define('NOLOGIN', '1');       // If this page is public (can be called outside logged session)
}
if (!defined('NOIPCHECK')) {
	define('NOIPCHECK', '1');     // Do not check IP defined into conf $dolibarr_main_restrict_ip
}
if (!defined('NOBROWSERNOTIF')) {
	define('NOBROWSERNOTIF', '1');
}

// For MultiCompany module.
// Do not use GETPOST here, function is not defined and define must be done before including main.inc.php
$entity = (!empty($_GET['entity']) ? (int) $_GET['entity'] : (!empty($_POST['entity']) ? (int) $_POST['entity'] : 1));
if (is_numeric($entity)) {
	define("DOLENTITY", $entity);
}

// Load Dolibarr environment
require '../../../main.inc.php';
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
?>
<link
      href="../css/dolibarrassistant.css.php"
      rel="stylesheet"
    />
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
              <img src="<?php echo DOL_URL_ROOT;?>/custom/dolibarrassistant/img/dolibarr.png" alt="avatar 1"
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
	
	function sendMessageRequestAI(message) {
			insertUserMessage(message);
			if (AIPrompt=="product") message="'.$langs->trans("DescriptionAIPrompt").'"+message;
			else message="'.$langs->trans("ProposalAIPrompt").' "+message;
			AIPrompt="";
     	// insert user message
			var secret = "'.$conf->global->ASSISTANT_OPENAI.'";
			var apiPath = "https://api.openai.com/v1/chat/completions";

			axios.post(apiPath,{
					model: "gpt-3.5-turbo",
					messages: [{ role: "user", content: message }],
					max_tokens: 4000,
					temperature: 0,
			},{
					headers: {
						"Content-Type": "application/json",
						Authorization: `Bearer ${secret}`,
					},
				}).then((response) => {
					// answer
					var answer =  response.data.choices[0].message.content;
					insertBotResponse(answer);
					sendMessageRequest("AssistantAIresponse"+answer);
					insertBotResponse("'.$langs->trans("IAddIt").' <a onclick=\'AssistantAI=0;sendYes()\' href=\'#\'>'.$langs->trans("Yes").'</a> '.$langs->trans("OrNewTopic").'");
				}).catch((error) => {
					insertBotResponse("Something went wrong. Please try again")
			})
    }
	
	
	// insert bot response
    function insertBotResponseIMG(response, img) {
      var elm = `<div class="d-flex flex-row justify-content-start mb-4">
              <img src="' . DOL_URL_ROOT . '/custom/dolibarrassistant/img/dolibarr.png" alt="avatar 1" style="width: 45px; height: 100%">
              <div class="p-3 ms-3" style="
                      border-radius: 15px;
                      background-color: rgba(57, 192, 237, 0.2);
                    ">
					<img style="width: 100%" src="`+response+`" alt="Response Image" />
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
     function sendMessageRequestIMG(message) {
			
			insertUserMessage(message);
     	// insert user message
			var secret = "'.$conf->global->ASSISTANT_OPENAI.'";
			var apiPath = "https://api.openai.com/v1/images/generations";

			axios.post(apiPath,{
					"prompt": message,
					"n": 2,
					"size": "1024x1024"
			},{
					headers: {
						"Content-Type": "application/json",
						Authorization: `Bearer ${secret}`,
					},
				}).then((response) => {
					// answer
					var answer =  response.data.data[0].url;
					console.log(answer);
					insertBotResponseIMG(answer, true);
					insertBotResponse("'.$langs->trans("IAddIt").' <a onclick=\'AssistantAI=0;sendYes()\' href=\'#\'>'.$langs->trans("Yes").'</a> '.$langs->trans("OrNewTopic").'");
					
					// send request to save image
					var host = window.location.protocol + "//" + window.location.host + "'.DOL_URL_ROOT.'";
					var apiPath = host+"/custom/dolibarrassistant/class/save_image.php";
					axios.post(apiPath, {
						url: answer,
						message: message
					}).then((response) => {
						console.log(response);
					}).catch((error) => {
						console.log(error);
					})
				}).catch((error) => {
					console.log(error);
				})
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