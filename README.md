# CWilio-SMS
A conglomeration of my CWilio and CWSlack repositories to create a ConnectWise integrated Slack SMS system using the Twilio API

Using just the Twilio/Slack part of this may be helpful, but it really shines once you integrate ConnectWise as it will then log the conversation to the internal notes of a ticket.

Example pictures: http://imgur.com/a/SzMzk


#### Note: This project is currently being maintained for bugs only, all further new feature development is being done on the hosted successor at https://mspic.io which contains CWilio-SMS along with CWSlack and new functionality

# Installation

This assumes Twilio, ConnectWise, and Slack are functional and ready to go.

1. Download the repository and copy all files to a web accessible location on a webserver that supports cURL, PHP, and MySQL/MariaDB. Tables and Database will be setup automatically if the proper permissions are available.
2. Setup a new Slack slash command and webhook and save the token and URL. Slash command must use GET not POST.
3. Open sms-config.php in your favourite text editor and fill out the values according to the comments. ALL values are required excluding the "Other" section and below.
4. From the channel specified in $slackchannel, try /sms (phone number) (message)
5. Setup a cron job or scheduled task on your server to run the PHP file cwilio-sms-cron.php **every hour.**  
   ```Cron: 0 */1 * * * /usr/bin/php /var/www/cwilio-sms-cron.php >/dev/null 2>&1```
6. If you have issues, please set $debugmode to true in sms-config.php and log a GitHub issue if you can't determine the issue.

# CW API Key Setup

1. Login to ConnectWise
2. In the top right, click on your name
3. Go to "My Account"
4. Select the "API Keys" tab
5. Click the Plus icon to create a new key
6. Provide a description and click the Save icon.
7. Save this information, you cannot retrieve the private key ever again so if lost you will need to create new ones.

# Command Usage

## /sms

- /sms (phone number) (message) - Sends a messsage to specified phone number
- /sms (ticket number) (message) - Sends a message to the number of the ticket contact, attaches ticket to thread.
- /sms (First name) (Last name)|(message) - Sends a message to the specified ConnectWise contact.
- /sms attach (phone number) (ticket number) - Attaches a current thread to a ticket for logging
- /sms detach (phone number) OR (ticket number) - Stops an ongoing thread.
