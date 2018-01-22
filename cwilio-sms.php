<?php


ini_set('display_errors', 1); //Display errors in case something occurs
header('Content-Type: application/json'); //Set the header to return JSON, required by Slack

require_once 'sms-config.php';
require_once 'sms-functions.php';

if(empty($_GET['token']) || ($_GET['token'] != $smsslacktoken)) die("Slack token invalid."); //If Slack token is not correct, kill the connection. This allows only Slack to access the page for security purposes.
if(empty($_GET['text'])) die("No text provided."); //If there is no text added, kill the connection.
if($_GET['channel_name'] != $slackchannel) die("Invalid channel");
$exploded = explode(" ",$_GET['text']); //Explode the string attached to the slash command for use in variables.

$phonenumber = NULL;
$ticketnumber = 0;

if(!is_numeric($exploded[0])) {
    //Check to see if the first command in the text array is actually help, if so redirect to help webpage detailing slash command use.
    if ($exploded[0]=="help") {
        die(json_encode(array("parse" => "full", "response_type" => "in_channel","text" => "Please visit " . $helpurl . " for more help information","mrkdwn"=>true)));
    }
    else if ($exploded[0]=="attach")
    {
        $mysql = mysqli_connect($mysqlserver, $mysqlusername, $mysqlpassword, $mysqldatabase);
        if (!$mysql)
        {
            die("Connection error: " . mysqli_connect_error());
        }
        if(!array_key_exists(1,$exploded))
        {
            die("No ticket number provided. /sms attach <ticket> <phone>");
        }
        if(!array_key_exists(2,$exploded))
        {
            die("No phone number provided. /sms attach <ticket> <phone>");
        }
        if(strlen($exploded[1]) <= 8)
        {
            $val1 = $countrycode . $exploded[2];
            $val1 = mysqli_real_escape_string($mysql,$val1);
            $sql = "SELECT * FROM threads WHERE phonenumber='" . $val1 . "'";
            $result = mysqli_query($mysql, $sql); //Run result
            if(mysqli_num_rows($result) > 0) //If there were too many rows matching query
            {
                while($row = mysqli_fetch_assoc($result))
                {
                    $val5 = mysqli_real_escape_string($mysql,$row["id"]);
                    $val6 = mysqli_real_escape_string($mysql,$exploded[1]);
                    $sql = "UPDATE threads SET ticketnumber='" . $val6 . "' WHERE id=" . $val5;
                    if (!mysqli_query($mysql,$sql))
                    {
                        die("Error: " . mysqli_error($mysql));
                    }
                }
                die("Records attached.");
            }
            else
            {
                die("No threads in database matching that phone number.");
            }
        }
        else
        {
            $val1 = $countrycode . $exploded[1];
            $val1 = mysqli_real_escape_string($mysql,$val1);
            $sql = "SELECT * FROM threads WHERE phonenumber='" . $val1 . "'";
            $result = mysqli_query($mysql, $sql); //Run result
            if(mysqli_num_rows($result) > 0) //If there were too many rows matching query
            {
                while($row = mysqli_fetch_assoc($result))
                {
                    $val5 = mysqli_real_escape_string($mysql,$row["id"]);
                    $val6 = mysqli_real_escape_string($mysql,$exploded[2]);
                    $sql = "UPDATE threads SET ticketnumber='" . $val6 . "' WHERE id=" . $val5;
                    if (!mysqli_query($mysql,$sql))
                    {
                        die("Error: " . mysqli_error($mysql));
                    }
                }
                die("Records attached.");
            }
            else
            {
                die("No threads in database matching that phone number.");
            }
        }
    }
    else if ($exploded[0]=="stop"||$exploded[0]=="detach")
    {
        $mysql = mysqli_connect($mysqlserver, $mysqlusername, $mysqlpassword, $mysqldatabase);
        if (!$mysql)
        {
            die("Connection error: " . mysqli_connect_error());
        }
        if(!array_key_exists(1,$exploded))
        {
            die("No ticket or phone provided");
        }
        if(strlen($exploded[1]) <= 8)
        {
            $val1 = mysqli_real_escape_string($mysql,$exploded[1]);
            $sql = "SELECT * FROM threads WHERE ticketnumber='" . $val1 . "'";
            $result = mysqli_query($mysql, $sql); //Run result
            if(mysqli_num_rows($result) > 0) //If there were too many rows matching query
            {
                while($row = mysqli_fetch_assoc($result))
                {
                    $sql = "DELETE FROM threads WHERE id=" . $row["id"];
                    mysqli_query($mysql,$sql);
                }
                die("Records deleted.");
            }
            else
            {
                die("No threads in database matching that ticket number.");
            }
        }
        else
        {
            $val1 = $countrycode . $exploded[1];
            $val1 = mysqli_real_escape_string($mysql,$val1);
            $sql = "SELECT * FROM threads WHERE phonenumber='" . $val1 . "'";
            $result = mysqli_query($mysql, $sql); //Run result
            if(mysqli_num_rows($result) > 0) //If there were too many rows matching query
            {
                while($row = mysqli_fetch_assoc($result))
                {
                    $sql = "DELETE FROM threads WHERE id=" . $row["id"];
                    mysqli_query($mysql,$sql);
                }
                die("Records deleted.");
            }
            else
            {
                die("No threads in database matching that phone number.");
            }
        }
    }
    else //Else search CW for name
    {
        $split = explode("|",$_GET["text"]);
        $contact = explode(" ",$split[0]);

        if(!array_key_exists(1,$split)) die("Must contain contact and message separated by pipe symbol. E.x. /sms John Doe|Hello!");
        if(!array_key_exists(1,$contact)) die("Must include first and last name. E.x. /sms John Doe|Hello!");

        $contacturl = $connectwise . "/$cwbranch/apis/3.0/company/contacts?conditions=inactiveFlag=False%20and%20firstName%20like%20%27" . $contact[0] . "%27%20and%20lastName%20like%20%27" . $contact[1] . "%27";

        $contactdata = cURL($contacturl,$cwHeader);

        if(empty($contactdata)) die("No contact matching " . $split[0] . " found.");
        $contactdata = $contactdata[0];
        foreach($contactdata->communicationItems as $type)
        {
            if($type->type->name == $phonetype)
            {
                $phonenumber = $countrycode . $type->value;
            }
        }
        if($phonenumber == NULL)
        {
            die("User does not have a cell phone number in ConnectWise.");
        }

        $message = $split[1];
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

if($phonenumber == NULL)
{
    if(strlen($exploded[0]) <= 8)
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
        $ticketnumber = $exploded[0];
    }
    else
    {
        $phonenumber = $countrycode . preg_replace("/[^0-9]/", "",$exploded[0]);
    }

    unset($exploded[0]);
    $message = implode(" ",$exploded);
}

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

$sql = "SELECT * FROM threads WHERE phonenumber='" . $val2 . "'";
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
    $val5 = mysqli_real_escape_string($mysql,$ticketnumber);
    $val6 = strtotime("Now");
    $sql = "INSERT INTO threads (phonenumber, lastmessage, ticketnumber) VALUES ('" . $val2 . "', '" . $val6 . "', '" . $val5 . "')";
    if (!mysqli_query($mysql,$sql))
    {
        die("Error: " . mysqli_error($mysql));
    }
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
    $postfieldspre = array("detailDescriptionFlag" => "True", "text" => "New SMS from " . $_GET["user_name"] . " to " . $phonenumber . ": " . $message);
    $dataTNotes = cURLPost($noteurl, $cwPostHeader, "POST", $postfieldspre);

}

if(array_key_exists("Message",$twilresponse))
{
    if ($timeoutfix == true) {
        cURLPost($_GET["response_url"], array("Content-Type: application/json"), "POST", array("parse" => "full", "response_type" => "ephemeral","text" => "Your message has been sent to $phonenumber."));
    } else {
        die("Your message has been sent to $phonenumber."); //Return success
    }
}