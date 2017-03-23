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
while(strlen($userphone) >= 11)
{
    $userphone = substr($userphone, 1);
}
$contacturl = $connectwise . "/$cwbranch/apis/3.0/company/contacts?childconditions=communicationItems/value%20like%20%22" . $userphone . "%22";

$contact = cURL($contacturl,$cwHeader);

if ($contact != null) {
    $contact = $contact[0];
    $slackperson = $contact->firstName . " " . $contact->lastName . " (" . $data["From"] . ")";
}

$postfields = array(
        "channel" => "#" . $slackchannel,
        "username" => $slackperson,
        "text" => $data["Body"]
    );

cURLPost($slackwebhook, $slackHeader, "POST", $postfields);