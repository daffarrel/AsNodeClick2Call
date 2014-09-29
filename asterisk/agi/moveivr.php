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

define("TRIP_REVIEW_LIST_URL", "http://ivr.movetma.com/Movetivrapi/V1/GetTripReviewList");
define("CANCEL_TRIP_LIST_URL", "http://ivr.movetma.com/Movetivrapi/V1/GetCancelTripList");
define("CANCEL_TRIP_URL", "http://ivr.movetma.com/Movetivrapi/V1/SetTripCancel");
define("CARPOOL_RENEWAL_LIST_URL", "http://ivr.movetma.com/Movetivrapi/V1/GetCarpoolRenewalList");
define("CARPOOL_RENEWAL_URL", "http://ivr.movetma.com/Movetivrapi/V1/SetCarpoolRenewal");
define("WHERE_IS_MY_RIDE_URL", "http://ivr.movetma.com/Movetivrapi/V1/GetWhereisMyRide");

// convert text to speech 
function text2speech($filename, $text) {
	$cmd = "/usr/local/bin/swift  -o /tmp/$filename.wav -p audio/channels=1,audio/sampling-rate=8000 '".$text."'";
	exec($cmd);
}

// excu API GET request to server and parse JSON data response
function getAPICaller($ast, $url) {

	$ast->verbose("getAPICaller() with URL: " . $url);
	// open connection
	$ch = curl_init();

	//C set the url
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

	// Execute request 
	$json_data = curl_exec($ch);
	// lose connection
	curl_close($ch);

	// JSON parsing step
	$order   = array("\r\n", "\n", "\r", "\\");
	$replace = '';
	$newstr = str_replace($order, $replace, $json_data);
	$data = json_decode(''.$newstr);

	$responseStatus = $data->status;
	$ast->verbose("getAPICaller() done with response status: " . $responseStatus);

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

	$requestUrl = TRIP_REVIEW_LIST_URL . "?phone=$authPhone&code=$authPin&callerid=$calleridNum";
  $responseData = getAPICaller($ast, $requestUrl);

  $totalTrip = count($responseData->result);
  $ast->set_variable("TOTAL-REVIEW-TRIP", "$totalTrip");
  // Response to the caller: You have a trip going from < Pickup_Address > to < Dropoff_Address > 
  // on <Travel_Date> provided by <Vendor>.  <Vendor> will pick you at < PickupTime> for you appointment at <DropoffTime>
  //$ast->exec("Festival","You have $totalTrip trip to review.");
  $callUID = $ast->get_variable("UNIQUEID");
  $count_trip = 0;
  $trip_list_text = "";

  foreach ($responseData->result as $trip) {
  	$count_trip++;
  	$tripId = $trip->Tripid;
  	$Pickup_Address = $trip->Pickup_Address;
  	$Dropoff_Address = $trip->Dropoff_Address;
  	$Travel_Date = $trip->Travel_Date;
  	$Vendor = $trip->Vendor;
  	$PickupTime = $trip->PickupTime;
  	$DropOffTime = $trip->DropOffTime;

  	$text = "Trip number $count_trip going from $Pickup_Address to $Dropoff_Address on $Travel_Date"
  	         . " provided by $Vendor.  $Vendor will pick you at $PickupTime  for you appointment at $DropOffTime. ";

    /*
		$order   = array(",", ";");
  	$replace = '';
  	$text = str_replace($order, $replace, $text); 
  	$text2 = str_replace($order, $replace, $text2); 

  	$ast->exec("Festival", $text);
  	$timestamp = strtotime("$Travel_Date");
  	$ast->say_date($timestamp);
  	$ast->exec("Festival", $text2);
    */
    $trip_list_text = $trip_list_text . $text;
  }

  if ($totalTrip == 0)
    $trip_list_text = "You have no trip to review at this time!";

  $filename = "TripReviewList-" . $callUID;
  text2speech($filename, $trip_list_text);
  $ast->set_variable("TRIP-REVIEW-LIST-AUDIO", "$filename");
   
	if (DEBUG)
		$ast->verbose("getTripReviewList() Stopped with $totalTrip trips to review.");
}

// get trip cancel list information
function getTripCancelList($ast){
	if (DEBUG)
		$ast->verbose("getTripCancelList() Start");

	$authPhone = $ast->get_variable("AUTH-PHONE");
	$authPin = $ast->get_variable("AUTH-PIN");
	$calleridNum = $ast->get_variable("CALLERID(num)");

	$requestUrl = CANCEL_TRIP_LIST_URL . "?phone=$authPhone&code=$authPin&callerid=$calleridNum";
  $responseData = getAPICaller($ast, $requestUrl);

  //print_r($responseData->result[0]->Tripid);
  $totalTrip = count($responseData->result);
  $callUID = $ast->get_variable("UNIQUEID");

  $count_trip = 0; 
  $trip_list_text = "";

  foreach ($responseData->result as $trip) {
  	// Response to the user: Press (n) to cancel trip going from < Pickup_Address > to < Dropoff_Address> 
	  // on <Travel_Date> provided by <vendor> at < PickupTime>.
	  $count_trip++;
  	$tripId = $trip->Tripid;
  	$Pickup_Address = $trip->Pickup_Address;
  	$Dropoff_Address = $trip->Dropoff_Address;
  	$Travel_Date = $trip->Travel_Date;
  	$Vendor = $trip->Vendor;
  	$PickupTime = $trip->PickupTime;

  	$text = "Press $count_trip to cancel trip going from $Pickup_Address to $Dropoff_Address on "
  			. "$Travel_Date provided by $Vendor at $PickupTime.";
  	
  	$trip_list_text = $trip_list_text . $text;
  	// store TRIPTID so we can use it later
  	$ast->set_variable("TRIP".$count_trip,$tripId);
  }

  if ($totalTrip == 0)
    $trip_list_text = "You have no trip at this time!";

  $triplist_filename = "TripCancelList-" . $callUID;
  text2speech($triplist_filename, $trip_list_text);
  $ast->set_variable("TRIP-CANCEL-LIST-AUDIO", "$triplist_filename");

	// Would you like to hear this again? Press (n+1) for yes. Press (n+2) for no.
  $yes_key = $totalTrip + 1;
  $no_key = $totalTrip + 2;
  $confirm_text = "Would you like to hear this again? Press $yes_key for yes.  Press $no_key for no ";
  $filename = "TripCancelList-HearAgain-" . $callUID;
  text2speech($filename, $confirm_text);
  $ast->set_variable("CANCEL-LIST-AGAIN-AUDIO", "$filename");
  // et total trips value to keep track
  $ast->set_variable("TOTAL-CANCEL-TRIP", "$totalTrip");

	if (DEBUG)
		$ast->verbose("getTripCancelList() Stopped with $totalTrip trips.");
}

// cancel trip confirm
function cancelTripConfirm($ast){
	if (DEBUG)
		$ast->verbose("cancelTripConfirm() Start");

  	$userChoice = $ast->get_variable("USERCHOICE");
  	$tripCancelID = $ast->get_variable("TRIP".$userChoice);

  	$authPhone = $ast->get_variable("AUTH-PHONE");
  	$authPin = $ast->get_variable("AUTH-PIN");
  	$calleridNum = $ast->get_variable("CALLERID(num)");
  	$requestUrl = CANCEL_TRIP_LIST_URL . "?phone=$authPhone&code=$authPin&callerid=$calleridNum";
    $responseData = getAPICaller($ast, $requestUrl);
    $totalTrip = count($responseData->result);
    $callUID = $ast->get_variable("UNIQUEID");

    $count_trip = 0;
    $trip_cancel_text = "";
    foreach ($responseData->result as $trip) {
    	$count_trip++;
    	$tripId = $trip->Tripid;
    	// this is trip user selected to cancel
    	if ($tripId == $tripCancelID) {
	    	$Pickup_Address = $trip->Pickup_Address;
	    	$Dropoff_Address = $trip->Dropoff_Address;
	    	$Travel_Date = $trip->Travel_Date;
	    	$Vendor = $trip->Vendor;
	    	$PickupTime = $trip->PickupTime;

	    	// You have selected to cancel the trip going from from < Pickup_Address > to < Dropoff_Address> 
	    	// on <Travel_Date> at  <PickupTime>. Is this the correct trip?
			// Press 1 for yes
			// Press 2 for no
			$trip_cancel_text = "You have selected to cancel the trip going from $Pickup_Address to $Dropoff_Address on"
								." $Travel_Date at $PickupTime. Is this the correct trip? Press 1 for yes. Press 2 for no.";
			$filename = "TripCancelConfirm-$tripId-" . $callUID;
    		text2speech($filename, $trip_cancel_text);
    		$ast->set_variable("TRIP-CANCEL-CONFIRM", "$filename");
    		$ast->set_variable("CANCELTRIPID", "$tripCancelID");
    		break;
    	}
    }

	if (DEBUG)
		$ast->verbose("cancelTripConfirm() done.");
}

// cancel trips
function cancelTrips($ast){
	if (DEBUG)
		$ast->verbose("cancelTrips() Start");

  	$authPhone = $ast->get_variable("AUTH-PHONE");
  	$authPin = $ast->get_variable("AUTH-PIN");
  	$calleridNum = $ast->get_variable("CALLERID(num)");

  	$tripID = $ast->get_variable("CANCELTRIPID");
  	// two more fields required for this request (now is just test data)
  	$tripIDList="".$tripID.",";
  	$token="Movet_241";

  	$requestUrl = CANCEL_TRIP_URL . "?phone=$authPhone&code=$authPin&callerid=$calleridNum&tripidlist=$tripIDList&token=$token";
    $responseData = getAPICaller($ast, $requestUrl);

    $status = $responseData->status;

    // check if request success to server
    if ($status == "OK"){

    }
    else {
    	$ast->exec("Festival", "Something wrong with your request, please come back later.");
    }

	if (DEBUG)
		$ast->verbose("cancelTrips() done.");
}


// get carpool renewwal list information
function getCarpoolRenewalList($ast){
	if (DEBUG)
		$ast->verbose("getCarpoolRenewalList() Start");

  	$authPhone = $ast->get_variable("AUTH-PHONE");
  	$authPin = $ast->get_variable("AUTH-PIN");
  	$calleridNum = $ast->get_variable("CALLERID(num)");

  	$requestUrl = CARPOOL_RENEWAL_LIST_URL. "?phone=$authPhone&code=$authPin&callerid=$calleridNum";
    $responseData = getAPICaller($ast, $requestUrl);

    $totalItems = count($responseData->result);

	if (DEBUG)
		$ast->verbose("getCarpoolRenewalList() Stopped with $totalItems item.");
}


// set carpool renewwal
function setCarpoolRenewwal($ast){
	if (DEBUG)
		$ast->verbose("setCarpoolRenewwal() Start");

  	$authPhone = $ast->get_variable("AUTH-PHONE");
  	$authPin = $ast->get_variable("AUTH-PIN");
  	$calleridNum = $ast->get_variable("CALLERID(num)");

  	// more fields required for this request (now is just test data)
  	$SoId="S124";
  	$token="Movet_241";
  	$DurationInMonths = "4";

  	$additionalFields = "&SoId=$SoId&DurationInMonths=$DurationInMonths&token=$token";

  	$requestUrl = CARPOOL_RENEWAL_URL . "?phone=$authPhone&code=$authPin&callerid=$calleridNum" . $additionalFields;
    $responseData = getAPICaller($ast, $requestUrl);

	if (DEBUG)
		$ast->verbose("setCarpoolRenewwal() done.");
}


// get whereIsMyRide information
function whereIsMyRide($ast){
	if (DEBUG)
		$ast->verbose("whereIsMyRide() Start");

  	$authPhone = $ast->get_variable("AUTH-PHONE");
  	$authPin = $ast->get_variable("AUTH-PIN");
  	$calleridNum = $ast->get_variable("CALLERID(num)");

  	$requestUrl = WHERE_IS_MY_RIDE_URL . "?phone=$authPhone&code=$authPin&callerid=$calleridNum";
    $responseData = getAPICaller($ast, $requestUrl);

    // check if request success to server
    if ($responseData->status == "OK"){
    	// Playback: Your vehicle will arrive at <Pickup_Address> at <ETA>
    }
    else {
    	$message = $responseData->message;
    	$ast->exec("Festival", "$message");
    }
    

	if (DEBUG)
		$ast->verbose("whereIsMyRide() Stopped");
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
		  	getTripReviewList($ast);
			break;
		case "GET-TRIP-CANCEL-LIST":
		  	getTripCancelList($ast);
			break;
		case "CANCEL-TRIP-CONFIRM":
		  	cancelTripConfirm($ast);
			break;
		case "CANCEL-TRIPS":
		  	cancelTrips($ast);
			break;
		case "GET-CARPOOL-RENEWAL-LIST":
		  	getCarpoolRenewalList($ast);
			break;
		case "SET-CARPOOL-RENEWAL":
		  	setCarpoolRenewwal($ast);
			break;
		case "WHERE-IS-MY-RIDE":
		  	whereIsMyRide($ast);
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
