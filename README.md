# ark_server_cron

This is obviously a work in progress. The purpose of this project is to automatically setup an Ark Survival Evolved dedicated server and provide the end user with an easy to use tool which will allow the hosting and matitenance of server tasks.

This has been developed & tested on Ubuntu 18.04 server. This script uses a bash script to onload the requirements of PHP and steamcmd. The rest of the script is run through ark_server_cron.php. You can run this code via as a scheduled task via cron tab to automate things like start, stop, restart, backup, and update.

To install, clone the repository to your server and run the croninstaller bash script as sudo.

Cron Tab Example:
0 6 * * * /usr/bin/php /home/steam/ark_server_cron.php > /raid/local/ark/backup_logs/backup_`date +\%Y-\%m-\%d_\%H-\%M-\%S`.log 2>&1

TODO (11/26/2018):
Features:
COMPLETE - Ability to Start any server or all servers at once
COMPLETE - Ability to Stop any server or all servers at once
Ability to Update server configuration and reference previous shard values so the user can see what the setting is set to and change it accordingly
Ability to restore a backup automatically which should include sifting through and choosing the backup based upon a list (sort of like timeshift)
Ability to throw this script onto a bare server to have it automatically install steam_cmd & the ark server (basically a loader script for virtual machines)

Considerations:
Needs to include Steam User Creation
Needs to allow the user to configure the server file path locations (let's make a config file for the cron)
Needs to set the open files limit
Mod Support!!
Add help text / user interaction
Add better error handling
Add a feature to auto unlock engrams from expansions
Need to make it so that server names are unique to prevent collisions in combineServerInfo() and userFunctionInteractive()
Need to also make it so a user cannot name a shard .zip so it doesn't mess with backup restoration functions

Bugs:
The shards are numbered from 1, if you have 3 shards setup (1,2, and 3) and you delete 2 the next time you try to create a new shard the cron will tell you that files exist for shard 3 already. This is because we're counting the shards in the shard directory instead of determing the shard number attached to the directory.
You can issue the stop command which will result in an undefined index for servers that are not running.
Restore is not currently working
