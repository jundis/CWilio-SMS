<?php

// Cleanup cron jobs

ini_set('display_errors', 1); //Display errors in case something occurs
header('Content-Type: application/json'); //Set the header to return JSON, required by Slack

require_once 'sms-config.php';
require_once 'sms-functions.php';

$mysql = mysqli_connect($mysqlserver, $mysqlusername, $mysqlpassword, $mysqldatabase);
if (!$mysql)
{
    die("Connection error: " . mysqli_connect_error());
}

$sql = "SELECT * FROM threads";
$result = mysqli_query($mysql, $sql); //Run result
if(mysqli_num_rows($result) > 0) //If there were too many rows matching query
{
    while($row = mysqli_fetch_assoc($result))
    {
        $eighthours = strtotime("-8 hours");
        if($row["lastmessage"]<=$eighthours)
        {
            $sql = "DELETE FROM threads WHERE id=" . $row["id"];
            mysqli_query($mysql,$sql);
        }
    }
}
else
{
    die("No threads in database");
}