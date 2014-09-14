#!/usr/bin/php
<?php
/*------------------------------------------------------------------------------
	MOVEIVR AGI Script
	Ha Truong
	truongvietha87@gmail.com
------------------------------------------------------------------------------*/

/*
 * Including the Asterisk Manager library
 */
require "moveivr/AsteriskManager.php";
require "moveivr/db.class.php";
require "moveivr/astlib.class.php";

define("DEBUG", true);
define("TEST-MODE", true);

define("TRIP-REVIEW-LIST-URL", "http://ivr.movetma.com/Movetivrapi/V1/GetTripReviewList");
define("CANCEL-TRIP-LIST-URL", "http://ivr.movetma.com/Movetivrapi/V1/GetCancelTripList");
define("CANCEL-TRIP-URL", "http://ivr.movetma.com/Movetivrapi/V1/SetTripCancel");
define("CARPOOL-RENEWAL-LIST-URL", "http://ivr.movetma.com/Movetivrapi/V1/GetCarpoolRenewalList");
define("CARPOOL-RENEWAL-URL", "http://ivr.movetma.com/Movetivrapi/V1/SetCarpoolRenewal");
define("WHERE-IS-MY-RIDE-URL", "http://ivr.movetma.com/Movetivrapi/V1/GetWhereisMyRide");



// excu API GET request to server and parse JSON data response
function getAPICaller($ast, $url) {

  $ast->verbose("getAPICaller url: "$url);
  
	//open connection
	$ch = curl_init();
	
	//set the url
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);


  // Execute
  $json_data = curl_exec($ch);
  //$json_data = str_replace('"{',"{",$json_data);
  //$json_data = str_replace('}"',"}",$json_data);
  
  //close connection
  curl_close($ch);

  // JSON parsing step
  $order   = array("\r\n", "\n", "\r", "\\");
  $replace = '';
  $newstr = str_replace($order, $replace, $json_data);
  $data = json_decode(''.$newstr);

  return $data;
}

// do authentication task
function userAuthentication($ast, $authInput){
	if (DEBUG)
		$ast->verbose("userAuthentication() Start");

  // parsing from user input
	$ast->set_variable("AUTH-PHONE", substr($authInput, 0, 10));
  $ast->set_variable("AUTH-PIN", substr($authInput, -4));	

  // for now always set valid authen data to Yes
  $ast->set_variable("AUTH-VALID", "Y");

	if (DEBUG)
		$ast->verbose("userAuthentication() Stop");
}

// get trip review list information
function getTripReviewList($ast){
	if (DEBUG)
		$ast->verbose("getTripReviewList() Start");

  $authPhone = $ast->get_variable("AUTH-PHONE");
  $authPin = $ast->get_variable("AUTH-PIN");
  $calleridNum = $ast->get_variable("CALLERID(num)");

  $requestUrl = TRIP-REVIEW-LIST-URL . "?phone=$authPhone&code=$authPin&callerid=$calleridNum";
  $responseData = getAPICaller($ast, $requestUrl);

	if (DEBUG)
		$ast->verbose("getTripReviewList() Stop");
}

/*----------------------------------------------------------
	MOVEIVR main processing
----------------------------------------------------------*/
function Main($ast, $db, $argv){
	if (DEBUG){
		$ast->verbose("*** MOVEIVR AGI script ***");
	}
	$command = $argv[1];
	
	switch($command)
	{
		case "AUTH":
		  $authInput = $ast->get_variable("AUTHINPUT");
			userAuthentication($ast, $authInput);
			break;
		case "GET-TRIP-REVIEW-LIST":
		  $authInput = $ast->get_variable("AUTHINPUT");
			userAuthentication($ast, $authInput);
			break;
		default:
			break;
	}

}


/*----------------------------------------------------------
	MOVEIVR program run here
----------------------------------------------------------*/
// Application init here
$ast = new AGI();
$db = new DB();
Main($ast, $db, $argv);

?>
