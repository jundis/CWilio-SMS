<?php


ini_set('display_errors', 1); //Display errors in case something occurs
header('Content-Type: text/plain'); //Set the header to return JSON, required by Slack
require_once 'sms-config.php'; //Require the config file.
require_once 'sms-functions.php';

parse_str(file_get_contents('php://input'), $data); //Decode incoming body from connectwise callback.

//Security Checks
if($data==NULL)
{
    die("No message data submitted. This is expected behavior if you are just browsing to this page with a web browser.");
}

if(!array_key_exists("AccountSid",$data)) die("Error, invalid entry");
if(!array_key_exists("To",$data)) die("Error, invalid entry.");
if($data["AccountSid"] != $accountsid) die("Not a valid account sid.");
if($data["To"] != $countrycode . $twilionumber) die("Invalid phone to message.");

//Variables
$slackperson = $data["From"];
$userphone = $data["From"];
$companyid = 0;
while(strlen($userphone) >= 11)
{
    $userphone = substr($userphone, 1);
}
$contacturl = $connectwise . "/$cwbranch/apis/3.0/company/contacts?childconditions=communicationItems/value%20like%20%22" . $userphone . "%22";

$contact = cURL($contacturl,$cwHeader);

if ($contact != null) {
    $contact = $contact[0];
    $slackperson = $contact->firstName . " " . $contact->lastName . " (" . $data["From"] . ")";
    $companyid = $contact->company->id;
    $contactid = $contact->id;
}

$postfields = array(
        "channel" => "#" . $slackchannel,
        "username" => $slackperson,
        "text" => $data["Body"]
    );

cURLPost($slackwebhook, $slackHeader, "POST", $postfields);

$mysql = mysqli_connect($mysqlserver, $mysqlusername, $mysqlpassword, $mysqldatabase);
if (!$mysql)
{
    die("Connection error: " . mysqli_connect_error());
}

$val1 = mysqli_real_escape_string($mysql,$data["From"]);
$val2 = mysqli_real_escape_string($mysql,"Slack");
$val3 = mysqli_real_escape_string($mysql,$data["Body"]);
$val4 = date("m-d-Y H:i:sa",strtotime("Now"));
$sql = "INSERT INTO logging (whofrom, whoto, message, date) VALUES ('" . $val1 . "', '" . $val2 . "', '" . $val3 . "', '" . $val4 . "')";
if (!mysqli_query($mysql,$sql))
{
    die("Error: " . mysqli_error($mysql));
}

$ticketnumber = 0;

$sql = "SELECT * FROM threads WHERE phonenumber='" . $val1 . "'";
$result = mysqli_query($mysql, $sql); //Run result
$rowcount = mysqli_num_rows($result);
if($rowcount > 1) //If there were too many rows matching query
{
    die("Error: too many threads somehow?"); //This should NEVER happen.
}
else if ($rowcount == 1) //If exactly 1 row was found.
{
    $row = mysqli_fetch_assoc($result); //Row association.

    $thread = $row["id"];
    if($ticketnumber == 0)
    {
        $ticketnumber = $row["ticketnumber"];
    }
}
else
{
    $thread = 0;
}

if($thread==0)
{
    if($companyid==0 || !isset($contactid))
    {
        $ticketpostarray = array(
            "summary" => "New SMS Ticket from " . $slackperson,
            "company" => array(
                "name" => $dumpcompany
            ),
            "initialDescription" => $data["Body"],
            "initialInternalAnalysis" => "Ticket submitted by " . $data["From"] . " to Slack. We were unable to match this to a contact record."
        );
    }
	else
	{
		$ticketpostarray = array(
			"summary" => "New SMS Ticket from " . $slackperson,
			"company" => array(
				"id" => $companyid
			),
			"contact" => array(
				"id" => $contactid
			),
			"initialDescription" => $data["Body"],
			"initialInternalAnalysis" => "Ticket submitted by " . $data["From"] . " to Slack. Matched to the contact record if possible."
		);
	}

    $dataTCmd = cURLPost( //Function for POST requests in cURL
        $connectwise . "/$cwbranch/apis/3.0/service/tickets", //URL
        $cwPostHeader, //Header
        "POST", //Request type
        $ticketpostarray
    );

    if(array_key_exists("id",$dataTCmd))
    {
        $ticketnumber = $dataTCmd->id;

        $postfields = array(
            "channel" => "#" . $slackchannel,
            "username" => $slackperson,
            "text" => "Ticket #" . $ticketnumber . " created from this message."
        );

        cURLPost($slackwebhook, $slackHeader, "POST", $postfields);
    }

    $val5 = mysqli_real_escape_string($mysql,$ticketnumber);
    $val6 = strtotime("Now");
    $sql = "INSERT INTO threads (phonenumber, lastmessage, ticketnumber) VALUES ('" . $val1 . "', '" . $val6 . "', '" . $val5 . "')";
    if (!mysqli_query($mysql,$sql))
    {
        die("Error: " . mysqli_error($mysql));
    }

    die();
}
else
{
    $val5 = mysqli_real_escape_string($mysql,$thread);
    $val6 = strtotime("Now");
    $sql = "UPDATE threads SET lastmessage='" . $val6 . "' WHERE id=" . $val5;
    if (!mysqli_query($mysql,$sql))
    {
        die("Error: " . mysqli_error($mysql));
    }
}

if($ticketnumber != 0)
{
    $noteurl = $connectwise . "/$cwbranch/apis/3.0/service/tickets/" . $ticketnumber . "/notes";
    $postfieldspre = array("detailDescriptionFlag" => "True", "customerUpdatedFlag" => "True", "text" => "New SMS from " . $data["From"] . " to Slack: " . $data["Body"]);
    $dataTNotes = cURLPost($noteurl, $cwPostHeader, "POST", $postfieldspre);

}