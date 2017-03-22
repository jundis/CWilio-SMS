<?php


ini_set('display_errors', 1); //Display errors in case something occurs
header('Content-Type: application/json'); //Set the header to return JSON, required by Slack

require_once 'sms-config.php';
require_once 'sms-functions.php';

if(empty($_GET['token']) || ($_GET['token'] != $smsslacktoken)) die("Slack token invalid."); //If Slack token is not correct, kill the connection. This allows only Slack to access the page for security purposes.
if(empty($_GET['text'])) die("No text provided."); //If there is no text added, kill the connection.
$exploded = explode(" ",$_GET['text']); //Explode the string attached to the slash command for use in variables.

if(!is_numeric($exploded[0])) {
    //Check to see if the first command in the text array is actually help, if so redirect to help webpage detailing slash command use.
    if ($exploded[0]=="help") {
        die(json_encode(array("parse" => "full", "response_type" => "in_channel","text" => "Please visit " . $helpurl . " for more help information","mrkdwn"=>true)));
    }
    else //Else search CW for name
    {
        // TO DO
    }
}

//Timeout Fix Block
if($timeoutfix == true)
{
    ob_end_clean();
    header("Connection: close");
    ob_start();
    echo ('{"response_type": "in_channel"}');
    $size = ob_get_length();
    header("Content-Length: $size");
    ob_end_flush();
    flush();
    session_write_close();
    if($sendtimeoutwait==true) {
        cURLPost($_GET["response_url"], array("Content-Type: application/json"), "POST", array("parse" => "full", "response_type" => "ephemeral", "text" => "Please wait..."));
    }
}
//End timeout fix block

if(strlen($exploded[0]) <= 9)
{
    // TO DO, this is a ticket
}
else
{
    $phonenumber = $countrycode . preg_replace("/[^0-9]/", "",$exploded[0]);
}

unset($exploded[0]);
$message = implode(" ",$exploded);

$postdata = "To=" . urlencode($phonenumber) . "&From=" . urlencode($countrycode . $twilionumber) . "&Body=" . urlencode($message);

$test = cURLPost("https://api.twilio.com/2010-04-01/Accounts/$accountsid/Messages",$twilHeader,"POST",$postdata);

var_dump($test);