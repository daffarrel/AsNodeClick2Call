#!/usr/local/bin/php
<?php
/*------------------------------------------------------------------------------
	MOVEIVR AGI Script
	Ha Truong
	truongvietha87@gmail.com
------------------------------------------------------------------------------*/

/*
 * Including the Asterisk Manager library
 */
//require "moveivr/AsteriskManager.php";
//require "moveivr/db.class.php";
require "moveivr/astlib.class.php";

define("DEBUG", true);
define("TEST-MODE", true);

define("TRIP_REVIEW_LIST_URL", "http://ivr.movetma.com/Movetivrapi/V1/GetTripReviewList");
define("CANCEL_TRIP_LIST_URL", "http://ivr.movetma.com/Movetivrapi/V1/GetCancelTripList");
define("CANCEL_TRIP_URL", "http://ivr.movetma.com/Movetivrapi/V1/SetTripCancel");
define("CARPOOL_RENEWAL_LIST_URL", "http://ivr.movetma.com/Movetivrapi/V1/GetCarpoolRenewalList");
define("CARPOOL_RENEWAL_URL", "http://ivr.movetma.com/Movetivrapi/V1/SetCarpoolRenewal");
define("WHERE_IS_MY_RIDE_URL", "http://ivr.movetma.com/Movetivrapi/V1/GetWhereisMyRide");

function newMOHClass($ast, $db, $class, $folder)
{
  // insert item to moh db table with format like
  // [default]
  // mode=files
  // directory=moh
  $cmd = "INSERT INTO musiconhold (name, directory, mode) VALUES ('".$class."','".$folder."','files')";
  if (DEBUG){ 
    $ast->verbose("newMOHClass querycmd: $cmd");
  } 
  $db->runQuery($cmd);
}

// convert text to speech 
function text2speech($filename, $text) {
  $starttime = microtime(true);

  //$cmd = "/usr/local/bin/swift  -o /tmp/$filename.wav -p audio/channels=1,audio/sampling-rate=8000 '".$text."'";
  $cmd = "/usr/local/bin/swift  -o /tmp/$filename.wav -p audio/channels=1,audio/sampling-rate=8000 ". '"' . $text . '"';
  //exec($cmd);
  //passthru($cmd . " 2>&1 &");
  system($cmd);
  $endtime = microtime(true);
  $time_taken = $endtime-$starttime;
  return $time_taken;
}

// setup moh for current channel
function mohSetup($ast, $db, $filename, $text) {
  $folder = "/tmp/$filename";
  exec("mkdir $folder");
  $cmd = "/usr/local/bin/swift  -o $folder/$filename.wav -p audio/channels=1,audio/sampling-rate=8000 '".$text."'";
  exec($cmd);
  newMOHClass($ast, $db, $filename, $folder);
}


// format time function
// "PickupTime":960
// This should be announced as "will pick you at <break strength='strong'/> four <break strength='strong'/> P.M."
// or. "PickupTime":980
// Should be announced as "will pick you at <break strength='strong'/> four <break strength='strong'/> twenty <break strength='strong'/> P.M."
function time2text($ast, $pickupTime) {
  //$ast->verbose("time2text for: " . $pickupTime);
  $pickupText = "<break strength='strong'/>";

  $divTime = (int)$pickupTime / 60;
  $divTimeRound = round($divTime, 0, PHP_ROUND_HALF_DOWN);
  $minute = (int)$pickupTime - $divTimeRound*60;

  if ($divTimeRound > 12)
    $toPickTime = $divTimeRound - 12;
  else
    $toPickTime = $divTimeRound;

  if ($minute > 0)
    $pickupText .= " $toPickTime <break strength='strong'/> $minute <break strength='strong'/>";
  else
    $pickupText .= " $toPickTime <break strength='strong'/>";

  if ($divTimeRound < 12)
    $pickupText .= " AM";
  else
    $pickupText .= " PM";

  //$ast->verbose("Time pickup text: " . $pickupText);
  return $pickupText;
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
  $authPhone = substr($authInput, 0, 10);
  $authPin = substr($authInput, -4);
	$ast->set_variable("AUTH-PHONE", $authPhone);
	$ast->set_variable("AUTH-PIN", $authPin);	
  $calleridNum = $ast->get_variable("CALLERID(num)");

  $requestUrl = TRIP_REVIEW_LIST_URL . "?phone=$authPhone&code=$authPin&callerid=$calleridNum";
  $responseData = getAPICaller($ast, $requestUrl);
  $status = $responseData->status;
  // check if request success to server
  if ($status == "OK")
    $ast->set_variable("AUTH-VALID", "Y");
  else
    $ast->set_variable("AUTH-VALID", "N");

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

    $PickupTimeText = time2text($ast, (int)$PickupTime);
    $DropOffTimeText = time2text($ast, (int)$DropOffTime);

  	$text = "Trip number $count_trip going from $Pickup_Address to $Dropoff_Address on $Travel_Date"
  	         . " provided by $Vendor.  $Vendor will pick you at $PickupTimeText for you appointment at $DropOffTimeText. ";

    //if (DEBUG)
    //  $ast->verbose("$text");
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
  $ast->set_variable("TRIP-REVIEW-LIST-AUDIO", "$filename");
  $ast->set_variable("TOTAL-REVIEW-TRIP", "$totalTrip");  
  $time_taken = text2speech($filename, $trip_list_text);
  $ast->verbose("getTripReviewList() time taken: $time_taken");

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
    $PickupTimeText = time2text($ast, (int)$PickupTime);

    $tripData = "Trip going from $Pickup_Address to $Dropoff_Address on $Travel_Date provided by $Vendor at $PickupTimeText. ";
  	$text = "Press $count_trip to cancel " . $tripData;
  	
  	$trip_list_text = $trip_list_text . $text;
  	// store TRIPTID so we can use it later
  	$ast->set_variable("TRIP".$count_trip,$tripId);
    $ast->set_variable("TRIP-DATA".$count_trip,$tripData);
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
    $tripCancelData = $ast->get_variable("TRIP-DATA".$userChoice);


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
        $token = $trip->Token;

        // You have selected to cancel the trip going from from < Pickup_Address > to < Dropoff_Address> 
        // on <Travel_Date> at  <PickupTime>. Is this the correct trip? Press 1 for yes Press 2 for no
        $trip_cancel_text = "You have selected to cancel the " . $tripCancelData  
                          . " Is this the correct trip? Press 1 for yes. Press 2 for no.";
        $filename = "TripCancelConfirm-$tripId-" . $callUID;
        text2speech($filename, $trip_cancel_text);
        $ast->set_variable("TRIP-CANCEL-CONFIRM", "$filename");
        $ast->set_variable("CANCELTRIPID", "$tripCancelID");
        $ast->set_variable("CANCELTRIPDATA", "$tripCancelData");
        $ast->set_variable("CANCELTRIPTOKEN", "$token");
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
  $callUID = $ast->get_variable("UNIQUEID");

	$tripID = $ast->get_variable("CANCELTRIPID");
  $tripData = $ast->get_variable("CANCELTRIPDATA");

	// two more fields required for this request (now is just test data)
	$tripIDList="".$tripID.",";
	$token = $ast->get_variable("CANCELTRIPTOKEN"); 

	$requestUrl = CANCEL_TRIP_URL . "?phone=$authPhone&code=$authPin&callerid=$calleridNum&tripidlist=$tripIDList&token=$token";
  $responseData = getAPICaller($ast, $requestUrl);

  $status = $responseData->status;
  $message = "";
  // check if request success to server
  if ($status == "OK"){
    // {"result":[{"Tripid":"T13068","TripStatus":"C"}],"status":"OK","message":""}
    // Your trip going from < Pickup_Address > to < Dropoff_Address> on <Travel_Date> 
    // has been cancelled. Would you like to hear this again? Press 1 for yes Press 2 for no 
    $message = "Your " . $tripData . "has been cancelled. ";
  }
  else {
  	$message = "Your request to cancel ". $tripData ." has been failed. ";
  }

  $message .=  "Would you like to hear this again? Press 1 for yes Press 2 for no.";
  $filename = "TripCancelResponse-$tripID-" . $callUID;
  text2speech($filename, $message);
  $ast->set_variable("TRIP-CANCEL-RESPONSE", "$filename");

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


  $callUID = $ast->get_variable("UNIQUEID");

  $count_item = 0; 
  $carpool_list_text = "";

  foreach ($responseData->result as $carpool) {
    // Press n to renew carpool going from <Pickup_Address> to <Dropoff_Address> 
    // going on <DaysofWeek> ending on <End Date>.
    $count_item++;
    $SoId = $carpool->SoId;
    $Pickup_Address = $carpool->Pickup_Address;
    $Dropoff_Address = $carpool->Dropoff_Address;
    $DaysOfWeek = $carpool->DaysOfWeek;
    $EndDate = $carpool->EndDate;
    $token = $carpool->Token;

    $carpoolData = "carpool going from $Pickup_Address to $Dropoff_Address going on $DaysOfWeek ending on $EndDate ";
    $text = "Press $count_item to renew " . $carpoolData;
    
    $carpool_list_text .= $text;
    // store SOID so we can use it later
    $ast->set_variable("CARPOOL".$count_item,$SoId);
    $ast->set_variable("CARPOOL-DATA".$count_item,$carpoolData);
    $ast->set_variable("CARPOOL-TOKEN".$count_item,$token);
  }

  if ($totalItems == 0)
    $carpool_list_text = "You have no Carpool at this time!";

  $filename = "CarpoollList-" . $callUID;
  text2speech($filename, $carpool_list_text);
  $ast->set_variable("CARPOOL-LIST-AUDIO", "$filename");

  // Would you like to hear this again? Press (n+1) for yes. Press (n+2) for no.
  $yes_key = $totalItems + 1;
  $no_key = $totalItems + 2;
  $confirm_text = "Would you like to hear this again? Press $yes_key for yes.  Press $no_key for no ";
  $filename = "CarpoollList-HearAgain-" . $callUID;
  text2speech($filename, $confirm_text);
  $ast->set_variable("CARPOOL-LIST-AGAIN-AUDIO", "$filename");
  // set total carpool value to keep track
  $ast->set_variable("TOTAL-CARPOOL", "$totalItems");

	if (DEBUG)
		$ast->verbose("getCarpoolRenewalList() Stopped with $totalItems item.");
}

// carpool renewal confirm
function carpoolRenewalConfirm($ast) {
  if (DEBUG)
    $ast->verbose("carpoolRenewalConfirm() Start");

  $callUID = $ast->get_variable("UNIQUEID");
  $userChoice = $ast->get_variable("USERCHOICE");
  $carpoolId = $ast->get_variable("CARPOOL".$userChoice);
  $carpoolData = $ast->get_variable("CARPOOL-DATA".$userChoice);
  $carpoolToken = $ast->get_variable("CARPOOL-TOKEN".$userChoice);

  // You have selected to renew carpool going <Pickup_Address> to <Dropoff_Address> 
  // going on <DaysofWeek> ending on <End Date>. 
  // Press 1 to renew for 1 month, Press 2 to renew for 2 months, Press 3 to renew to 3 months.
  $carpool_text = "You have selected to renew  " . $carpoolData  
                  . " Press 1 to renew for 1 month, Press 2 to renew for 2 months, Press 3 to renew to 3 months.";
  $filename = "CarpoolConfirm-$carpoolId-" . $callUID;
  text2speech($filename, $carpool_text);
  $ast->set_variable("CARPOOL-RENEWAL-CONFIRM-AUDIO", "$filename");
  $ast->set_variable("CARPOOLID", "$carpoolId");
  $ast->set_variable("CARPOOLDATA", "$carpoolData");
  $ast->set_variable("CARPOOLTOKEN", "$carpoolToken");
  //$ast->set_variable("CARPOOLDURATION", "$userChoice");

  if (DEBUG)
    $ast->verbose("carpoolRenewalConfirm() done.");
}

// set carpool renewwal
function setCarpoolRenewal($ast){
	if (DEBUG)
		$ast->verbose("setCarpoolRenewal() Start");

  	$authPhone = $ast->get_variable("AUTH-PHONE");
  	$authPin = $ast->get_variable("AUTH-PIN");
  	$calleridNum = $ast->get_variable("CALLERID(num)");

  	// more fields required for this request (now is just test data)
    $callUID = $ast->get_variable("UNIQUEID");
  	$SoId = $ast->get_variable("CARPOOLID");
  	$token = $ast->get_variable("CARPOOLTOKEN");
  	$DurationInMonths = $ast->get_variable("CARPOOLDURATION");
    $carpoolData = $ast->get_variable("CARPOOLDATA");

  	$additionalFields = "&SoId=$SoId&DurationInMonths=$DurationInMonths&token=$token";

  	$requestUrl = CARPOOL_RENEWAL_URL . "?phone=$authPhone&code=$authPin&callerid=$calleridNum" . $additionalFields;
    $responseData = getAPICaller($ast, $requestUrl);

    $status = $responseData->status;
    $message = "";

    // check if request success to server
    if ($status == "OK"){
      // {"result":[{"SoId":"S142","StartDate":"08/27/2014","RenewedEndDate":"01/03/2015"}],"status":"OK","message":""}
      // Your Carpool has been renewed until <RenewedEndDate>. 
      // Would you like to hear this again? Press 1 for Yes Press 2 for No 
      $newDate = $responseData->result[0]->RenewedEndDate;
      $message = "Your Carpool has been renewed until $newDate.";
    }
    else {
      $message = "Your request to renew ". $carpoolData ." has been failed. ";
    }

    $message .=  "Would you like to hear this again? Press 1 for yes Press 2 for no.";
    $filename = "CarpoolRenewResponse-$SoId-" . $callUID;
    text2speech($filename, $message);
    $ast->set_variable("CARPOOL-RENEWAL-RESPONSE-AUDIO", "$filename");

	if (DEBUG)
		$ast->verbose("setCarpoolRenewal() done.");
}


// get whereIsMyRide information
function whereIsMyRide($ast){
	if (DEBUG)
		$ast->verbose("whereIsMyRide() Start");

    $callUID = $ast->get_variable("UNIQUEID");
  	$authPhone = $ast->get_variable("AUTH-PHONE");
  	$authPin = $ast->get_variable("AUTH-PIN");
  	$calleridNum = $ast->get_variable("CALLERID(num)");

    $ast->set_variable("VEHICLE", "N");  

  	$requestUrl = WHERE_IS_MY_RIDE_URL . "?phone=$authPhone&code=$authPin&callerid=$calleridNum";
    $responseData = getAPICaller($ast, $requestUrl);
    $message = '';

    // check if request success to server
    if ($responseData->status == "OK"){
    	// Playback: Your vehicle will arrive at <Pickup_Address> at <ETA>
      $PickupAddress = $responseData->result[0]->Pickup_Address;;
      $PickupTime = $responseData->result[0]->PickupTime;;
      $PickupTimeText = time2text($ast, (int)$PickupTime);
      $message = "Your vehicle will arrive at $PickupAddress at $PickupTimeText .";
    }
    else {
    	$message = $responseData->message;
    	// $ast->exec("Festival", "$message");
    }

    if (strlen($message) > 0) {
      $filename = "WhereMyRide-" . $callUID;
      text2speech($filename, $message);
      $ast->set_variable("WHERE-MYRIDE-RESPONSE-AUDIO", "$filename");   
      $ast->set_variable("VEHICLE", "Y");   
    }

	if (DEBUG)
		$ast->verbose("whereIsMyRide() Stopped");
}


// prepare MOH class for channel
// values list: DIALOPTIONS TO-NUMBER CONNECT-EXT MESSAGE TRIPID
function click2callMOH($ast, $db){
  if (DEBUG)
    $ast->verbose("click2callMOH() Start");

  $callUID = $ast->get_variable("UNIQUEID");
  $message = $ast->get_variable("MESSAGE");
  $dialOptions = $ast->get_variable("DIALOPTIONS");
  $toNumber = $ast->get_variable("TO-NUMBER");

  if (strlen($message)>0){
    $filename = "click2call_moh_$toNumber_$callUID";
    mohSetup($ast, $db, $filename, $message);
    $dialOptions .= "m";
    $ast->set_variable("DIALOPTIONS", "$dialOptions");
    $ast->set_variable("CHANNEL(musicclass)", "$filename");
  }

  if (DEBUG)
    $ast->verbose("click2callMOH() Stopped");
}

// save CDR for click2call
function click2callCDR($ast){
  if (DEBUG)
    $ast->verbose("click2callMOH() Start");


  if (DEBUG)
    $ast->verbose("click2callMOH() Stopped");
}

// process new incoming call
// to replace this: exten => 7099,1,Goto(moveivr-incoming-call,${EXTEN},1)
function newIncomingCall($ast){
  if (DEBUG)
    $ast->verbose("newIncomingCall() Start");

  $exten = $ast->get_variable("EXTEN");
  //$ast->exec("GOTO", "moveivr-incoming-call,$exten,1");
  $ast->set_context("moveivr-incoming-call");
  $ast->set_extension($exten);
  $ast->set_priority(1);
  
  if (DEBUG)
    $ast->verbose("newIncomingCall() Stopped");
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
		  setCarpoolRenewal($ast);
			break;
    case "CARPOOL-RENEWAL-CONFIRM":
      carpoolRenewalConfirm($ast);
      break;
		case "WHERE-IS-MY-RIDE":
		  whereIsMyRide($ast);
			break;
    case "CLICK2CALL-MOH":
      click2callMOH($ast, $db);
      break;
    case "CLICK2CALL-CDR":
      click2callCDR($ast);
      break;
    case "NEW-INCOMING-CALL":
      newIncomingCall($ast);
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
//$db = new DB();
$db = None;
Main($ast, $db, $argv);

?>
