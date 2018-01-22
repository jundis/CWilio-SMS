<?php


//Connectwise Configuration
$connectwise = "https://cw.domain.com"; //Set your Connectwise URL
$cwbranch = "v4_6_release"; //Set to the portion of your CW URL shown here: https://cw.domain.com/**v4_6_release**/ConnectWise.aspx
$companyname = "company"; //Set your company name from Connectwise. This is the company name field from login.
$apipublickey = "Connectwise Public Key"; //Public API key
$apiprivatekey = "Connectwise Private Key"; //Private API key
$phonetype = "Cell"; //Limit SMS to these numbers
$dumpcompany = 4; //Change to your "Catch All" company, for numbers that can't be identified for new tickets.

//Slack Configuration
$smsslacktoken = "Token here"; //Set token from the Slack slash command screen.
$timeoutfix = false; //Enable to fix any 3000ms response from Slack.
$sendtimeoutwait = false; //Set to true to send a please wait message with every command. Only does something when $timeoutfix is set to true.
$slackwebhook = "Webhook URL here"; //Set your Slack webhook URL here.
$slackchannel = "sms"; //Channel that all communication will route through. No other channel will function for these commands.

//Twilio Configuration
$accountsid = "SID here"; //Find this on https://www.twilio.com/user/account/
$authtoken = "Token here"; //Find this on https://www.twilio.com/user/account/
$twilionumber = "Phone here"; //A Twilio phone number you control to send messages from. Enter without symbols or country code. E.x. 5558675309
$countrycode = "+1"; //If not in the US, change this to your country code.

//MySQL Configuration
$mysqlserver = "127.0.0.1"; //Database server address
$mysqlusername = "root"; //Database username
$mysqlpassword = ""; //Database password
$mysqldatabase = "cwiliosms"; //Database name

// Other
$helpurl = "https://github.com/jundis/CWilio-SMS"; // Change to your documentation on this command.
$debugmode = true; // Only use if instructed, or you're curious.

//
//Don't modify below unless you know what you're doing!
//

$authorization = base64_encode(strtolower($companyname) . "+" . $apipublickey . ":" . $apiprivatekey); //Encode the API, needed for authorization.

$cwPostHeader = array(
    "Authorization: Basic " . $authorization,
    "Content-Type: application/json"
);

$cwHeader = array("Authorization: Basic ". $authorization);

$slackHeader = array("Content-Type: application/json");

$twilHeader = array("application/x-www-form-urlencoded","Authorization: Basic " . base64_encode($accountsid . ":" . $authtoken));

if($debugmode == true)
{
    $timeoutfix = false;
}

$mysql = mysqli_connect($mysqlserver, $mysqlusername, $mysqlpassword);
if (!$mysql)
{
    die("Connection error: " . mysqli_connect_error());
}

$db = mysqli_select_db($mysql, $mysqldatabase);
if(!$db)
{
    $sql = "CREATE DATABASE " . $mysqldatabase;
    if(mysqli_query($mysql, $sql))
    {
        $db = mysqli_select_db($mysql, $mysqldatabase);
    }
    else
    {
        die("Database Error: " . mysqli_error($mysql));
    }
}

$sql = "SELECT id FROM logging";
$result = mysqli_query($mysql, $sql);

if(empty($result)) {
    $sql = "CREATE TABLE logging (id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY, whofrom VARCHAR(25) NOT NULL, whoto VARCHAR(25) NOT NULL, message VARCHAR(200) NOT NULL, date VARCHAR(50) NOT NULL)";

    if (!mysqli_query($mysql, $sql)) {
        die("Companies Table Error: " . mysqli_error($mysql));
    }
}

$sql = "SELECT id FROM threads";
$result = mysqli_query($mysql, $sql);

if(empty($result)) {
    $sql = "CREATE TABLE threads (id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY, phonenumber VARCHAR(12) NOT NULL, lastmessage VARCHAR(50) NOT NULL, ticketnumber VARCHAR(10))";

    if (!mysqli_query($mysql, $sql)) {
        die("Companies Table Error: " . mysqli_error($mysql));
    }
}

mysqli_close($mysql);
