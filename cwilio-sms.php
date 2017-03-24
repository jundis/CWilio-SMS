<?php


ini_set('display_errors', 1); //Display errors in case something occurs
header('Content-Type: application/json'); //Set the header to return JSON, required by Slack

require_once 'sms-config.php';
require_once 'sms-functions.php';

if(empty($_GET['token']) || ($_GET['token'] != $smsslacktoken)) die("Slack token invalid."); //If Slack token is not correct, kill the connection. This allows only Slack to access the page for security purposes.
if(empty($_GET['text'])) die("No text provided."); //If there is no text added, kill the connection.
if($_GET['channel_name'] != $slackchannel) die("Invalid channel");
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

if(!array_key_exists(1,$exploded)) die("Not enough parameters, please include a number and a message");

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
        cURLPost($_GET["response_url"], array("Content-Type: application/json"), "POST", array("parse" => "full", "response_type" => "ephemeral", "text" => "Sending message."));
    }
}
//End timeout fix block

$phonenumber = NULL;

if(strlen($exploded[0]) <= 9)
{
    $ticketurl = $connectwise . "/$cwbranch/apis/3.0/service/tickets/" . $exploded[0];
    $ticketdata = cURL($ticketurl,$cwHeader);
    $contacturl = $ticketdata->contact->_info->contact_href;
    $contactdata = cURL($contacturl,$cwHeader);
    foreach($contactdata->communicationItems as $type)
    {
        if($type->type->name == $phonetype)
        {
            $phonenumber = $countrycode . $type->value;
        }
    }
    if($phonenumber == NULL)
    {
        if ($timeoutfix == true) {
            cURLPost($_GET["response_url"], array("Content-Type: application/json"), "POST", array("parse" => "full", "response_type" => "ephemeral","text" => "User does not have a cell phone number in ConnectWise."));
        } else {
            die("User does not have a cell phone number in ConnectWise.");
        }
        die();
    }
}
else
{
    $phonenumber = $countrycode . preg_replace("/[^0-9]/", "",$exploded[0]);
}

unset($exploded[0]);
$message = implode(" ",$exploded);

$postdata = "To=" . urlencode($phonenumber) . "&From=" . urlencode($countrycode . $twilionumber) . "&Body=" . urlencode($message);

$twilresponse = cURLPost("https://api.twilio.com/2010-04-01/Accounts/$accountsid/Messages",$twilHeader,"POST",$postdata);

$mysql = mysqli_connect($mysqlserver, $mysqlusername, $mysqlpassword, $mysqldatabase);
if (!$mysql)
{
    die("Connection error: " . mysqli_connect_error());
}

$val1 = mysqli_real_escape_string($mysql,$_GET['user_name']);
$val2 = mysqli_real_escape_string($mysql,$phonenumber);
$val3 = mysqli_real_escape_string($mysql,$message);
$val4 = date("m-d-Y H:i:sa",strtotime("Now"));
$sql = "INSERT INTO logging (whofrom, whoto, message, date) VALUES ('" . $val1 . "', '" . $val2 . "', '" . $val3 . "', '" . $val4 . "')";
if (!mysqli_query($mysql,$sql))
{
    die("Error: " . mysqli_error($mysql));
}

if(array_key_exists("Message",$twilresponse))
{
    if ($timeoutfix == true) {
        cURLPost($_GET["response_url"], array("Content-Type: application/json"), "POST", array("parse" => "full", "response_type" => "ephemeral","text" => "Your message has been sent to $phonenumber."));
    } else {
        die("Your message has been sent to $phonenumber."); //Return success
    }
}