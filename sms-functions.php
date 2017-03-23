<?php


/**
 * @param $url
 * @param $header
 * @return mixed
 */
function cURL($url, $header)
{
    global $debugmode; //Require global variable $debugmode from config.php.
    $ch = curl_init(); //Initiate a curl session

    //Create curl array to set the API url, headers, and necessary flags.
    $curlOpts = array(
        CURLOPT_URL => $url, //URL to send the curl request to
        CURLOPT_RETURNTRANSFER => true, //Request data returned instead of output
        CURLOPT_HTTPHEADER => $header, //Header to include, mainly for authorization purposes
        CURLOPT_FOLLOWLOCATION => true, //Follow 301/302 redirects
        CURLOPT_HEADER => 1, //Use header
    );
    curl_setopt_array($ch, $curlOpts); //Set the curl array to $curlOpts

    $answerTData = curl_exec($ch); //Set $answerTData to the curl response to the API.
    $headerLen = curl_getinfo($ch, CURLINFO_HEADER_SIZE);  //Get the header length of the curl response
    $curlBodyTData = substr($answerTData, $headerLen); //Remove header data from the curl string.
    if($debugmode) //If the global $debugmode variable is set to true
    {
        var_dump($answerTData); //Dump the raw data.
    }
    // If there was an error, show it
    if (curl_error($ch)) {
        die(curl_error($ch));
    }
    curl_close($ch); //Close the curl connection for cleanup.

    $jsonDecode = json_decode($curlBodyTData); //Decode the JSON returned by the CW API.

    if(array_key_exists("code",$jsonDecode)) { //Check if array contains error code
        if($jsonDecode->code == "NotFound") { //If error code is NotFound
            die("Connectwise record was not found."); //Report that the ticket was not found.
        }
        if($jsonDecode->code == "Unauthorized") { //If error code is an authorization error
            die("401 Unauthorized, check API key to ensure it is valid."); //Fail case.
        }
        else { //Else other error
            die("Unknown Error Occurred, check API key and other API settings. Error " . $jsonDecode->code . ": " . $jsonDecode->message); //Fail case, including the message and code output from connectwise.
        }
    }
    if(array_key_exists("errors",$jsonDecode)) //If connectwise returned an error.
    {
        $errors = $jsonDecode->errors; //Make array easier to access.

        die("ConnectWise Error: " . $errors[0]->message); //Return CW error
    }

    return $jsonDecode; //Return the decoded output.
}

/**
 * @param $url
 * @param $header
 * @param $postfieldspre
 * @return mixed
 */
function cURLPost($url, $header, $request, $postfieldspre)
{
    global $debugmode; //Require global variable $debugmode from config.php
    $ch = curl_init(); //Initiate a curl session
    $array = null;
    if(is_array($postfieldspre))
    {
        $postfields = json_encode($postfieldspre); //Format the array as JSON
        $array = true;
    }
    else
    {
        $postfields = $postfieldspre;
        $array = false;
    }

    //Same as previous curl array but includes required information for PATCH commands.
    $curlOpts = array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $header,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CUSTOMREQUEST => $request,
        CURLOPT_POSTFIELDS => $postfields,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 1,
    );
    curl_setopt_array($ch, $curlOpts);

    $answerTCmd = curl_exec($ch);
    $headerLen = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $curlBodyTCmd = substr($answerTCmd, $headerLen);

    if($debugmode)
    {
        var_dump($answerTCmd);
    }

    // If there was an error, show it
    if (curl_error($ch)) {
        die(curl_error($ch));
    }
    curl_close($ch);
    if($curlBodyTCmd == "ok") //Slack catch
    {
        return null;
    }

    if(!$array) //Twilio catch
    {
        $xml = simplexml_load_string($curlBodyTCmd, "SimpleXMLElement", LIBXML_NOCDATA);
        $json = json_encode($xml);
        $jsonDecode = json_decode($json,TRUE);
    }
    else
    {
        $jsonDecode = json_decode($curlBodyTCmd); //Decode the JSON returned by the CW API.
    }


    if(array_key_exists("code",$jsonDecode)) { //Check if array contains error code
        if($jsonDecode->code == "NotFound") { //If error code is NotFound
            die("Connectwise record was not found."); //Report that the ticket was not found.
        }
        else if($jsonDecode->code == "Unauthorized") { //If error code is an authorization error
            die("401 Unauthorized, check API key to ensure it is valid."); //Fail case.
        }
        else if($jsonDecode->code == NULL)
        {
            //do nothing.
        }
        else {
            die("Unknown Error Occurred, check API key and other API settings. Error " . $jsonDecode->code . ": " . $jsonDecode->message); //Fail case.
        }
    }
    if(array_key_exists("errors",$jsonDecode)) //If connectwise returned an error.
    {
        $errors = $jsonDecode->errors; //Make array easier to access.

        die("ConnectWise Error: " . $errors[0]->message); //Return CW error
    }
    if(array_key_exists("RestException",$jsonDecode)) // Catch for Twilio errors
    {
        die("Error " . $jsonDecode["RestException"]["Message"] . "(" . $jsonDecode["RestException"]["Code"] . "): " . $jsonDecode["RestException"]["Detail"]);
    }

    return $jsonDecode;
}