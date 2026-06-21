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
 * \file    dolibarrassistant/css/dolibarrassistant.css.php
 * \ingroup dolibarrassistant
 * \brief   CSS file for module DolibarrAssistant.
 */

//if (!defined('NOREQUIREUSER')) define('NOREQUIREUSER','1');	// Not disabled because need to load personalized language
//if (!defined('NOREQUIREDB'))   define('NOREQUIREDB','1');	// Not disabled. Language code is found on url.
if (!defined('NOREQUIRESOC')) {
  define('NOREQUIRESOC', '1');
}
//if (!defined('NOREQUIRETRAN')) define('NOREQUIRETRAN','1');	// Not disabled because need to do translations
//if (!defined('NOCSRFCHECK'))   define('NOCSRFCHECK', 1);		// Should be disable only for special situation
if (!defined('NOTOKENRENEWAL')) {
  define('NOTOKENRENEWAL', 1);
}
if (!defined('NOLOGIN')) {
  define('NOLOGIN', 1); // File must be accessed by logon page so without login
}
//if (! defined('NOREQUIREMENU'))   define('NOREQUIREMENU',1);  // We need top menu content
if (!defined('NOREQUIREHTML')) {
  define('NOREQUIREHTML', 1);
}
if (!defined('NOREQUIREAJAX')) {
  define('NOREQUIREAJAX', '1');
}

session_cache_limiter('public');
// false or '' = keep cache instruction added by server
// 'public'  = remove cache instruction added by server
// and if no cache-control added later, a default cache delay (10800) will be added by PHP.

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
  $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
  $i--;
  $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
  $res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/../main.inc.php")) {
  $res = @include substr($tmp, 0, ($i + 1)) . "/../main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
  $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
  $res = @include "../../../main.inc.php";
}
if (!$res) {
  die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';

// Load user to have $user->conf loaded (not done by default here because of NOLOGIN constant defined) and load permission if we need to use them in CSS
/*if (empty($user->id) && !empty($_SESSION['dol_login'])) {
	$user->fetch('',$_SESSION['dol_login']);
	$user->getrights();
}*/


// Define css type
header('Content-type: text/css');
// Important: Following code is to cache this file to avoid page request by browser at each Dolibarr page access.
// You can use CTRL+F5 to refresh your browser cache.
if (empty($dolibarr_nocache)) {
  header('Cache-Control: max-age=10800, public, must-revalidate');
} else {
  header('Cache-Control: no-cache');
}

?>

* {
margin: 0;
padding: 0;
box-sizing: border-box;
}

:root {
--margin: 1rem;
--box-max-width: 375px;
--theme-color: #39c0ed;
}

.bot-wrapper {
display: flex;
justify-content: right;
align-items: end;
width: max-content;
height: max-content;
position: fixed;
bottom: 0;
right: 0;
padding: 0 1rem;
z-index: 10000;
}

.bot-content {
display: flex;
align-items: end;
flex-direction: column;
gap: 1rem;
}

.bot-start-btn {
background: var(--theme-color);
color: #fff;
outline: 0;
border: none;
padding: 0.8rem;
width: 50px;
height: 50px;
border-radius: 100%;
margin-right: var(--margin);
margin-bottom: var(--margin);
}

#botBox {
margin-right: var(--margin);
width: 100%;
max-width: var(--box-max-width);
display: none;
margin: 0;
}

#botBox.show__chat {
display: block;
}

#botBox .form-outline .form-control ~ .form-notch div {
pointer-events: none;
border: 1px solid;
border-color: #eee;
box-sizing: border-box;
background: transparent;
}

#botBox .form-outline .form-control ~ .form-notch .form-notch-leading {
left: 0;
top: 0;
height: 100%;
border-right: none;
border-radius: 0.65rem 0 0 0.65rem;
}

#botBox .form-outline .form-control ~ .form-notch .form-notch-middle {
flex: 0 0 auto;
max-width: calc(100% - 1rem);
height: 100%;
border-right: none;
border-left: none;
}

#botBox .form-outline .form-control ~ .form-notch .form-notch-trailing {
flex-grow: 1;
height: 100%;
border-left: none;
border-radius: 0 0.65rem 0.65rem 0;
}

#botBox .form-outline .form-control:focus ~ .form-notch .form-notch-leading {
border-top: 0.125rem solid #39c0ed;
border-bottom: 0.125rem solid #39c0ed;
border-left: 0.125rem solid #39c0ed;
}

#botBox .form-outline .form-control:focus ~ .form-notch .form-notch-leading,
#botBox .form-outline .form-control.active ~ .form-notch .form-notch-leading {
border-right: none;
transition: all 0.2s linear;
}

#botBox .form-outline .form-control:focus ~ .form-notch .form-notch-middle {
border-bottom: 0.125rem solid;
border-color: #39c0ed;
}

#botBox .form-outline .form-control:focus ~ .form-notch .form-notch-middle,
#botBox .form-outline .form-control.active ~ .form-notch .form-notch-middle {
border-top: none;
border-right: none;
border-left: none;
transition: all 0.2s linear;
}

#botBox .form-outline .form-control:focus ~ .form-notch .form-notch-trailing {
border-top: 0.125rem solid #39c0ed;
border-bottom: 0.125rem solid #39c0ed;
border-right: 0.125rem solid #39c0ed;
}

#botBox .form-outline .form-control:focus ~ .form-notch .form-notch-trailing,
#botBox .form-outline .form-control.active ~ .form-notch .form-notch-trailing {
border-left: none;
transition: all 0.2s linear;
}

#botBox .form-outline .form-control:focus ~ .form-label {
color: #39c0ed;
}

#botBox .form-outline .form-control ~ .form-label {
color: #bfbfbf;
}

#botBox .fa,
#botBox .fa-brands,
#botBox .fa-duotone,
#botBox .fa-light,
#botBox .fa-regular,
#botBox .fa-solid,
#botBox .fa-thin,
#botBox .fab,
#botBox .fad,
#botBox .fal,
#botBox .far,
#botBox .fas,
#botBox .fat {
cursor: pointer;
}

#botBox .card-body {
padding: 0 !important;
}

#botBox .bot-box-conversation {
max-height: 350px;
overflow-y: auto;
padding: 0.5rem 0.5rem 0 0.5rem;
}

#botBox .writing-area {
padding: 1.5rem;
}