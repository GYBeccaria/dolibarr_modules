<?php
/* Copyright (C) 2001-2023	Andreu Bisquerra	<jove@bisquerra.com>
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

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOBROWSERNOTIF')) {
	define('NOBROWSERNOTIF', '1');
}

define("NOCSRFCHECK", 1);

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

$langs->loadLangs(array("dolibarrassistant@dolibarrassistant","other"));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // convert axios value
  $_POST = json_decode(file_get_contents("php://input"), true);

  // message
  $message = $_POST["message"];
  
  if (strpos($message, "AssistantCustomerID") !== false) {
	  $_SESSION["AssistantCustomerID"] = str_replace("AssistantCustomerID", "", $message);
	  exit;
  }
  
  if (strpos($message, "AssistantCustomerName") !== false) {
	  $_SESSION["AssistantCustomerName"] = str_replace("AssistantCustomerName", "", $message);
	  exit;
  }
  
  if (strpos($message, "AssistantAIresponse") !== false) {
	  $_SESSION["AssistantAIresponse"] = str_replace("AssistantAIresponse", "", $message);
	  exit;
  }
  
  if (strpos($message, $langs->trans('createinvoiceto')) !== false) {
	  require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
	  $invoice = new Facture($db);
	  $invoice->socid = $_SESSION["AssistantCustomerID"];
	  $invoice->date = dol_now();
	  $placeid = $invoice->create($user);
	  $_SESSION["AssistantModule"]="invoice";
	  $_SESSION["AssistantObject"]=$placeid;
	  echo $langs->trans('ICreatedFollowingInvoice');
	  echo "<br><a href='".DOL_URL_ROOT."/compta/facture/card.php?facid=".$placeid."'>".$invoice->ref." - ".$_SESSION["AssistantCustomerName"]."</a><br>";
	  echo $langs->trans('InvoiceSomethingElse');
	  unset($_SESSION["AssistantQuestion"]);
	  exit;
  }
  
  if (strpos($message, $langs->trans('createproposalto')) !== false) {
	  require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
	  $proposal = new Propal($db);
	  $proposal->socid = $_SESSION["AssistantCustomerID"];
	  $proposal->date = dol_now();
	  $placeid = $proposal->create($user);
	  $_SESSION["AssistantModule"]="proposal";
	  $_SESSION["AssistantObject"]=$placeid;
	  echo $langs->trans('ICreatedFollowingproposal');
	  echo "<br><a href='".DOL_URL_ROOT."/comm/propal/card.php?id=".$placeid."'>".$proposal->ref." - ".$_SESSION["AssistantCustomerName"]."</a><br>";
	  echo $langs->trans('InvoiceSomethingElse');
	  unset($_SESSION["AssistantQuestion"]);
	  exit;
  }
  
  if (strpos($message, $langs->trans('checkabout')) !== false) {
	  $soc = new Societe($db);
	  $soc->fetch($_SESSION["AssistantCustomerID"]);
	  echo $langs->trans('TheCustomerContactAre');
	  echo "<br><a href='".DOL_URL_ROOT."/societe/card.php?id=".$_SESSION["AssistantCustomerID"]."'>".$_SESSION["AssistantCustomerName"]."</a><br>";
	  if ($soc->email) echo $soc->email."<br>";
	  if ($soc->phone) echo $soc->phone."<br>";
	  echo $langs->trans('SomethingElse');
	  unset($_SESSION["AssistantQuestion"]);
	  exit;
  }
  
  // message conditions

  switch ($message) {
	case $langs->trans('create'):
		echo $langs->trans('ICanHelpCreate');
		$_SESSION["AssistantQuestion"] = "";
      break;
	  
	case $langs->trans('createthird'):
		echo $langs->trans('SayMeThirdName');
	  $_SESSION["AssistantQuestion"] = "askthirdname";
      break;
	  
	case $langs->trans('createproduct'):
		echo $langs->trans('SayMeProductName');
		$_SESSION["AssistantModule"]="product";
		$_SESSION["AssistantQuestion"] = "askproductname";
      break;
	  
	case $langs->trans('createinvoice'):
		if (!empty($_SESSION["AssistantCustomerID"])) {
			echo $langs->trans('YouCanCreateInvoiceFor');
			echo "<br>";
			echo "<a href='#' onclick='sendMessageRequest(\"".$langs->trans('createinvoiceto')." ".$_SESSION["AssistantCustomerName"]."\")'>".$_SESSION["AssistantCustomerName"]."</a>";
			echo "<br>";
			echo $langs->trans('OrTellMeNameThird');
		}
		else echo $langs->trans('TellMeNameThird');
		$_SESSION["AssistantQuestion"] = "askthirdnameforinvoice";
      break;
	  
	case $langs->trans('check'):
		if (!empty($_SESSION["AssistantCustomerID"])) {
			echo $langs->trans('DoYouWantSomeThisFirst');
			echo "<br>";
			echo "<a href='#' onclick='sendMessageRequest(\"".$langs->trans('checkabout')." ".$_SESSION["AssistantCustomerName"]."\")'>".$_SESSION["AssistantCustomerName"]."</a>";
			echo "<br>";
			echo $langs->trans('OrTellMeNameThird');
		}
		else echo $langs->trans('TellMeNameThird');
		$_SESSION["AssistantQuestion"] = "askthirdnameforcheck";
      break;
	  
	case $langs->trans('createproposal'):
		if (!empty($_SESSION["AssistantCustomerID"])) {
			echo $langs->trans('YouCanCreateProposalFor');
			echo "<br>";
			echo "<a href='#' onclick='sendMessageRequest(\"".$langs->trans('createproposalto')." ".$_SESSION["AssistantCustomerName"]."\")'>".$_SESSION["AssistantCustomerName"]."</a>";
			echo "<br>";
			echo $langs->trans('OrTellMeNameThird');
		}
		else echo $langs->trans('TellMeNameThird');
		$_SESSION["AssistantQuestion"] = "askthirdnameforproposal";
    break;
	  
	case $langs->trans('automaticdescription'):
		echo $langs->trans('FeatureNotYetAvailable');
	break;
	
	case $langs->trans('generateimage'):
		echo $langs->trans('FeatureNotYetAvailable');
	break;
		
    case $langs->trans('setopenaikey'):
		echo $langs->trans('FeatureNotYetAvailable');
    break;
	  
	case $langs->trans('CommandList'):
		echo $langs->trans('TheseAreExamplesOfWhatICurrentlyUnderstand');
		echo "<br>";
		echo "<a href='#' onclick='sendMessageRequest(\"".$langs->trans('create')."\")'><b>".$langs->trans('create')."</b></a><br>";
		echo "<a href='#' onclick='sendMessageRequest(\"".$langs->trans('createthird')."\")'><b>".$langs->trans('createthird')."</b></a><br>";
		echo "<a href='#' onclick='sendMessageRequest(\"".$langs->trans('createinvoice')."\")'><b>".$langs->trans('createinvoice')."</b></a><br>";
		echo "<a href='#' onclick='sendMessageRequest(\"".$langs->trans('createproposal')."\")'><b>".$langs->trans('createproposal')."</b></a><br>";
		echo "<a href='#' onclick='sendMessageRequest(\"".$langs->trans('createinvoiceto')."\")'><b>".$langs->trans('createinvoiceto')."</b> ???????</a><br>";
		echo "<a href='#' onclick='sendMessageRequest(\"".$langs->trans('createproposalto')."\")'><b>".$langs->trans('createproposalto')."</b> ???????</a><br>";
		echo "<a href='#' onclick='sendMessageRequest(\"".$langs->trans('check')."\")'><b>".$langs->trans('check')."</b></a><br>";
		echo "<a href='#' onclick='sendMessageRequest(\"".$langs->trans('checkabout')."\")'><b>".$langs->trans('checkabout')."</b> ???????</a><br>";
	break;

    default:
	
		//If is a question
		if (!empty($_SESSION["AssistantQuestion"])) {
			if ($_SESSION["AssistantQuestion"] == "askthirdname"){
				require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
				$object = new Societe($db);
				$object->nom=$message;
				$object->client=1;
				$result = $object->create($user);
				$_SESSION["AssistantCustomerID"] = $object->id;
				$_SESSION["AssistantCustomerName"] = $message;
				echo $langs->trans('ThirdCreated');
				echo "<br><a href='".DOL_URL_ROOT."/societe/card.php?socid=".$object->id."'><b>".$message."</b></a><br>";
				echo $langs->trans('ThirdSomethingElse');
			}
			if ($_SESSION["AssistantQuestion"] == "askproductname"){
				require_once DOL_DOCUMENT_ROOT."/product/class/product.class.php";
				$product=new Product($db);
				$product->ref=$message;
				$product->label=$message;
				$product->status=1;
				$idproduct = $product->create($user);
				if ($idproduct < 0) {
					echo $langs->trans('ErrorUnknown');
					exit;
				}	
				$_SESSION["AssistantProductID"] = $idproduct;
				$_SESSION["AssistantProductName"] = $message;
				echo $langs->trans('ProductCreated');
				echo "<br><a href='".DOL_URL_ROOT."/product/card.php?id=".$idproduct."'><b>".$message."</b></a><br>";
				echo $langs->trans('ProductSomethingElse');
			}
			if ($_SESSION["AssistantQuestion"] == "askthirdnameforinvoice"){
				$sql = "SELECT rowid,nom FROM ".MAIN_DB_PREFIX."societe where nom like '%".$message."%'";
				$resql = $db->query($sql);
				$rows = array();
				$i=0;
				$customers="";
				while ($row = $db->fetch_array($resql)) {
					//$rows[] = $row;
					if ($i>2) break;
					$i++;
					$customers=$customers."<a href='#' onclick='sendMessageRequest(\"AssistantCustomerID".$row['rowid']."\");sendMessageRequest(\"AssistantCustomerName".$row['nom']."\");sendMessageRequest(\"".$langs->trans('createinvoiceto')." ".$row['nom']."\")'>".$row['nom']."</a><br>";
				}
				if ($customers=="") echo $langs->trans('NoThirdFoundWithThisName');
				else echo $langs->trans('AreYouReferringToOneOfThese')."<br>".$customers;
			}
			if ($_SESSION["AssistantQuestion"] == "askthirdnameforproposal"){
				$sql = "SELECT rowid,nom FROM ".MAIN_DB_PREFIX."societe where nom like '%".$message."%'";
				$resql = $db->query($sql);
				$rows = array();
				$customers="";
				$i=0;
				while ($row = $db->fetch_array($resql)) {
					//$rows[] = $row;
					if ($i>2) break;
					$i++;
					$customers=$customers."<a href='#' onclick='sendMessageRequest(\"AssistantCustomerID".$row['rowid']."\");sendMessageRequest(\"AssistantCustomerName".$row['nom']."\");sendMessageRequest(\"".$langs->trans('createproposalto')." ".$row['nom']."\")'>".$row['nom']."</a><br>";
				}
				if ($customers=="") echo $langs->trans('NoThirdFoundWithThisName');
				else echo $langs->trans('AreYouReferringToOneOfThese')."<br>".$customers;
			}
			if ($_SESSION["AssistantQuestion"] == "askthirdnameforcheck"){
				$sql = "SELECT rowid,nom FROM ".MAIN_DB_PREFIX."societe where nom like '%".$message."%'";
				$resql = $db->query($sql);
				$rows = array();
				$customers="";
				$i=0;
				while ($row = $db->fetch_array($resql)) {
					//$rows[] = $row;
					if ($i>2) break;
					$i++;
				$customers=$customers."<a href='#' onclick='sendMessageRequest(\"AssistantCustomerID".$row['rowid']."\");sendMessageRequest(\"AssistantCustomerName".$row['nom']."\");sendMessageRequest(\"".$langs->trans('checkabout')." ".$row['nom']."\")'>".$row['nom']."</a><br>";
				}
				if ($customers=="") echo $langs->trans('NoThirdFoundWithThisName');
				else echo $langs->trans('AreYouReferringToOneOfThese')."<br>".$customers;
			}
			if ($_SESSION["AssistantQuestion"] == "AIDescription" && $message==$langs->trans("Yes")){
				echo $langs->trans('FeatureNotYetAvailable');
			}
			
			if ($_SESSION["AssistantQuestion"] == "AIProductDescription" && $message==$langs->trans("Yes")){
				echo $langs->trans('FeatureNotYetAvailable');
			}
			
			if ($_SESSION["AssistantQuestion"] == "AIimage" && $message==$langs->trans("Yes")){
					echo $langs->trans('FeatureNotYetAvailable');
			}
			
			if ($_SESSION["AssistantQuestion"] == "setopenaikey"){
				echo $langs->trans('FeatureNotYetAvailable');
			}
				
			//unset($_SESSION["AssistantQuestion"]);
			exit;
		}
		//If not command match and is not a question:
		else echo $langs->trans('ICantUnderstand');
		break;
  }
}
?>