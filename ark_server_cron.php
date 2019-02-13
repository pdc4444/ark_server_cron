<?php

/*

Summary:
This script can be called via the command line with -Start to start the ark server.
Example command: "/usr/bin/php ark_server_cron.php -Start"
This usage is for initial system startup (fresh boot only)

However, the main goal of this script is to automate backups, backup rotation, and server updating / maintenance.

Backup Flow
1.) Gracefully stop the server process (issue ctrl + C (SIGINT))
2.) Backup the server data to a specific location
3.) Update the server with steamcmd
4.) Bring the server back up.
5.) Perform maintenance on the backups (rotate 7 days or older out)
6.) Rotate the backup logs

*/

//$ark_servers = array('TheIsland_1' => '/mnt/ark/ark_server/ShooterGame/Binaries/Linux/ShooterGameServer TheIsland?listen?');
//TODO: This array needs to dynamically generated so that more than one server can be started up.
//The Name should be generated from the config files instead of being manually assigned.

/*
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
&	The shards are numbered from 1, if you have 3 shards setup (1,2, and 3) and you delete 2 the next time you try to create a new shard the cron will tell you
	that files exist for shard 3 already. This is because we're counting the shards in the shard directory instead of determing the shard number attached to the directory.
&	You can issue the stop command which will result in an undefined index for servers that are not running.
&	Restore is not currently working
*/

if(isset($argv)){
	$cli_args = $argv;
}

$steam_cmd_path = DIRECTORY_SEPARATOR . 'usr' . DIRECTORY_SEPARATOR . 'games' . DIRECTORY_SEPARATOR . 'steamcmd';

$start = new CronStartup();

$backup_path = $start->backup_path;
$root_server_files = $start->root_server_files;
$server_shard_directory = $start->server_shard_directory;

$cron_obj = new CronControl($server_shard_directory);

if($start->new_install === TRUE){
	$cron_obj->user_command = 'New';
	$cron_obj->initiateProcessUserCommand();
	exit();
} else if(array_key_exists('1',$cli_args)){
	$cron_obj->user_command = $cli_args['1'];
	if(array_key_exists('2',$cli_args)){
		$cron_obj->user_option = $cli_args['2'];
	}
	$cron_obj->initiateProcessUserCommand();
} else {
	$cron_obj->userInteraction();
}

//$mod_Ids = AcquireModIds($root_server_files);
/*if($mod_Ids !== NULL){
	UpdateArkServerMods($mod_Ids, $steam_cmd_path, $root_server_files);
}*/
//RotateTheBackups($backup_path);
//RotateTheBackupLogs($backup_path);

Class CronStartup
{
	public $config_file;
	public $backup_path;
	public $root_server_files;
	public $server_shard_directory;
	public $new_install;
	
	//Detect if we have a configuration file, if yes, load the file paths from the config file. If no, start the install process.
	function __construct()
	{
		$this->config_file = __DIR__ . DIRECTORY_SEPARATOR . 'ark_server_cron.cfg';
		if($this->findConfigFile($this->config_file) === TRUE){
			$this->loadConfigFile($this->config_file);
		} else {
			$installer_obj = new CronInstaller();
			$installer_obj->config_file = $this->config_file;
			$installer_obj->startInstaller();
			//Now that a config file has been created, we can load it
			$this->loadConfigFile($this->config_file);
			$this->new_install = TRUE;
		}
	}
	
	function findConfigFile($config_file)
	{
		if(file_exists($config_file) === FALSE){
			return false;
		} else {
			return true;
		}
	}
	
	function loadConfigFile()
	{
		$contents = file_get_contents($this->config_file);
		$content_array = explode("\n",$contents);
		foreach($content_array as $line){
			$line_array = explode('=',$line);
			if(!empty($line_array[0])){
				$this->assignConfigFileVariables($line_array[0], $line_array[1]);
			}
		}
	}
	
	function assignConfigFileVariables($setting, $value)
	{
		switch($setting){
			case 'ark_server_files':
				$this->root_server_files = $value;
				break;
			case 'ark_server_shards':
				$this->server_shard_directory = $value . DIRECTORY_SEPARATOR;
				break;
			case 'ark_server_backup':
				$this->backup_path = $value;
				break;
		}
	}
}

Class CronInstaller
{
	public $config_file;
	/*
	Ask user where they want to install Ark server files to
	Ask user where they want the ark backup path to be
	Ask user where they want the server shards to be located
	Notate file paths in freshly generated config file
	Install the ark server files
	*/
	function startInstaller()
	{
		$file_paths = $this->userQuestionaire();
		$this->generateConfigFile($file_paths);
		$this->createServerDirectories($file_paths);
		$this->installArkServerFiles($file_paths);
	}
	
	function userQuestionaire()
	{
		$file_paths = array();
		echo "Please input the filepaths where specific data will be configured for the server. Keep in mind that the steam user will need permissions in order to write to these directories.\n" . 
		"Example of expected input: '/home/steam/ark_server_files'\n";
		$file_paths['ark_server_files'] = readline("Input the desired location of the Ark Server File Directory: ");
		$file_paths['ark_server_shards'] = readline("Input the desired location of the Ark Server Shards Directory: ");
		$file_paths['ark_server_backup'] = readline("Input the desired location of the Ark backup Directory: ");
		return $file_paths;
	}
	
	function generateConfigFile($file_paths)
	{
		foreach($file_paths as $file => $file_location){
			file_put_contents($this->config_file,$file . '=' . $file_location . "\n", FILE_APPEND);
		}
	}
	
	function createServerDirectories($file_paths)
	{
		foreach($file_paths as $file => $file_location){
			$result = mkdir($file_location,0777,TRUE);
			if($result === FALSE){
				TimeStamp('Unable to create a folder for ' . $file . ' here: ' . $file_location);
				exit(); //Need proper error handling / cleanup this is a dirty exit until further development / testing
			}
		}
	}
	
	function installArkServerFiles($file_paths)
	{
		GLOBAL $steam_cmd_path;
		UpdateTheArkServer::performServerUpdate($steam_cmd_path, $file_paths['ark_server_files']);
	}
}

Class CronControl
{
	public $ark_server_obj;
	public $ark_server_array;
	public $combined_server_info;
	public $user_command;
	public $user_option;
	public $user_interaction;
	
	function __construct($server_shard_directory)
	{
		$this->ark_server_obj = new ArkStartCommands($server_shard_directory);
		$this->ark_server_array = $this->ark_server_obj->server_array_start_commands;
		$this->combined_server_info = $this->combineServerInfo($this->ark_server_obj->running_servers, $this->ark_server_array);
	}
	
	function initiateProcessUserCommand()
	{
		$cmd = strtolower($this->user_command);
		$this->processUserCommand($cmd);
	}
	
	function userInteraction()
	{
		$this->user_interaction = TRUE;
		echo "Welcome to your PHP Based Ark Server controller!\n";
		$command = readline("Please enter your command or type 'help' for a list of options: ");
		$this->processUserCommand($command);
	}
	
	function processUserCommand($cmd)
	{
		if($this->user_interaction === TRUE){
			$this->userFunctionInteractive($this->combined_server_info,$cmd);
		} else {
			switch($cmd){
			case 'start':
				$this->startAllArkServers();
				break;
			case 'stop':
				$this->gracefullyStopTheServer();
				break;
			case 'restart':
				$this->initiateArkRestart();
				break;
			case 'status':
				$this->printServerStatus();
				break;
			case 'new':
				$this->initiateNewShard();
				break;
			case 'update':
				$this->initiateArkUpdate();
				break;
			case 'backup':
				$this->initiateArkBackup();
				break;
			case 'auto':
				$this->initiateArkUpdate();
				$this->startAllArkServers();
				TimeStamp('Ark Server Maintenance Complete');
				break;
			case 'help':
				$this->printHelpfulInfo();
				break;
			case 'about':
				$this->printAboutText();
				break;
			default:
				echo "Invalid argument! Run this command for help\nphp " . __FILE__ . " help\n";
				break;
			}
		}
	}
	
	function combineServerInfo($running_server_array, $server_start_command_array)
	{
		//This function combines the two arrays for running server and server start commands to allow easier processing in the userFunctionInteractive function.
		$combined_array = array();
		foreach($server_start_command_array as $server_name => $start_command){
			$combined_array[$server_name]['start_cmd'] = $start_command;
			$combined_array[$server_name]['shard_dir'] = $this->extractShardDirFromStartCmd($start_command);
			if($running_server_array !== NULL && array_key_exists($server_name,$running_server_array) == TRUE){
				foreach($running_server_array[$server_name] as $attribute => $attribute_value){
					$combined_array[$server_name][$attribute] = $attribute_value;
				}
			}
		}
		return $combined_array;
	}
	
	function extractShardDirFromStartCmd($start_command)
	{
		$shard_location = '';
		$array = explode('/',$start_command);
		foreach($array as $part){
			if(empty($part) === FALSE){
				$shard_location = $shard_location . DIRECTORY_SEPARATOR . $part;
				if(strpos($part,'shard_') !== FALSE){
					break;
				}
			}
		}
		return $shard_location;
	}
	
	function printHelpfulInfo()
	{
		$this->printAboutText();
		echo "\033[36mYou can utilize the following commands:\n";
		echo "\033[35mStart \033[37m- This starts all Ark servers found in the Ark shard directory.\n";
		echo "\n\033[35mStop \033[37m- This sends SIGINT to stop all active Ark servers gracefully.\n\033[31mNote: SIGINT can fail if you attempt to stop the server before it's finished loading after issuing the Start command. In this case SIGKILL will be issued after waiting for 90 seconds. This can lead to corruption, please be careful!\n";
		echo "\n\033[35mRestart \033[37m- This will issue the Stop command, wait for 30 seconds and then issue the Start command.\n";
		echo "\n\033[35mStatus \033[37m- This will print out an array of any actively running Ark server.\n";
		echo "\n\033[35mNew \033[37m- This will create a new shard (server instance) in the Ark shard directory.\n\033[32mA shard consists of the copied binary (ShooterGame) and a unique config with the rest of the necessary server files made as symlinks to the single Ark server directory.\n";
		echo "\n\033[35mUpdate \033[37m- This will stop all servers then backup the saved directories of every Ark Shard and then proceed to update the root server files and every subsequent shard directory.\n";
		echo "\n\033[35mBackup \033[37m- This will stop all servers then create a snapshot for every shard directory in the designated ark backup folder.\n";
		echo "\n\033[35mAuto \033[37m- This is mainly for use with crontab, but the command will stop all servers, then run a backup, then start all Ark servers once again.\n";
		echo "\n\033[35mHelp \033[37m- Literally prints out this help message.\n";
		echo "\033[36mAny command listed above can be passed as a command line argument when executing ark_server_cron.php. Example: using 'ark_server_cron.php start' will start the Ark servers.\033[37m\n";
	}
	
	function printAboutText()
	{
		echo "\n\033[33mThis code is for use with Ark Survival Evolved dedicated server. It allows the user to spin up multiple servers on the same linux environment. Development and testing was performed with Ubuntu 18.04 Server.\n";
		echo "\nAuthor: Peter Cooper\npdc4444@gmail.com\nIf you have any questions, comments, or concerns, please e-mail me. Make sure the subject line says 'Ark Cron'\n\n";
	}
	
	function initiateArkRestart()
	{
		$this->gracefullyStopTheServer();
		sleep(30);
		$this->startAllArkServers();
	}
	
	function initiateArkUpdate()
	{
		GLOBAL $steam_cmd_path;
		GLOBAL $root_server_files;
		GLOBAL $server_shard_directory;
		
		$this->initiateArkBackup();
		new UpdateTheArkServer($steam_cmd_path, $root_server_files, $server_shard_directory);
	}
	
	function initiateArkBackup()
	{
		$this->gracefullyStopTheServer();
		$this->backupSavedData();
	}
	
	function initiateNewShard()
	{
		GLOBAL $root_server_files;
		GLOBAL $server_shard_directory;
		$detected_servers = $this->ark_server_array;
		
		$dir_obj = new BuildNewServerDirectory($root_server_files, $server_shard_directory, FALSE, NULL);
		$shard_location = $dir_obj->new_shard_directory;
		new UpdateShardConfiguration($shard_location, $detected_servers);
	}
	
	function printServerStatus()
	{
		$server_array = $this->ark_server_obj->running_servers;
		if(!empty($server_array)){
			print_r($server_array);
		} else {
			TimeStamp('No Ark Servers Up.');
		}
	}
	
	function startAllArkServers()
	{
		foreach($this->ark_server_array as $server_name => $start_command){
			$this->executeArkServerStartCmd($server_name, $start_command);
		}
	}
	
	function buildUserSelection($combined_server_info)
	{
		$selection_array = array();
		$counter = 1;
		echo "\033[35m[\033[33mA\033[35m] All\033[37m\n";
		foreach($combined_server_info as $name => $server_info){
			$selection_array[$counter]['name'] = $name;
			if(array_key_exists('PID',$server_info) == TRUE){
				//This check is required because servers might not be running and thus there would be no PID to populate the selection array with.
				$selection_array[$counter]['PID'] = $server_info['PID'];
			}
			$selection_array[$counter]['start_cmd'] = $server_info['start_cmd'];
			echo "\033[35m[\033[33m" . $counter . "\033[35m] " . $name . "\033[37m\n";
			$counter++;
		}
		return $selection_array;
	}
	
	function userFunctionInteractive($combined_server_info, $command)
	{
		$accepted_commands = array('start','stop','restart','restore','backup');
		if(in_array($command,$accepted_commands) === FALSE){
			exit($command . " is not a valid option! Run this command for help:\nphp " . __FILE__ . " help\n");
		}
		$selection_array = $this->buildUserSelection($combined_server_info);
		echo "\033[32mPlease input the selection for Ark server you want to " . $command . ", or Q to quit\033[37m\n";
		$selection = strtolower(readline("Input: "));
		if(array_key_exists($selection,$selection_array) !== FALSE){
			switch($command){
				case 'start':
					$this->executeArkServerStartCmd($selection_array[$selection]['name'],$selection_array[$selection]['start_cmd']);
					break;
				case 'stop':
					$this->gracefullyStopTheServer($selection_array[$selection]['PID']);
					break;
				case 'restart':
					if(isset($selection_array[$selection]['PID'])){
						$this->gracefullyStopTheServer($selection_array[$selection]['PID']);
					} else {
						echo $selection_array[$selection]['name'] . " was not running, so we couldn't stop it as part of the restart process. However, we are attempting to start it now...\n";
					}
					$this->executeArkServerStartCmd($selection_array[$selection]['name'],$selection_array[$selection]['start_cmd']);
					break;
				case 'restore':
					$this->startRestoreProcess($selection_array[$selection]['name']);
					break;
				case 'backup':
					$user_comment = $this->promptUserForComment();
					TimeStamp('Here is the user comment ' . $user_comment);
					foreach($this->combined_server_info as $name => $server_info){
						//Check to see if the server is running, if yes stop it and then take a backup
						//This should be moved to backupSavedData()
						if($name == $selection_array[$selection]['name'] && array_key_exists('PID',$server_info)){
							$this->gracefullyStopTheServer($server_info['PID']);
						}
					}
					$this->backupSavedData($selection_array[$selection]['name'], $user_comment);
					break;
			}
		} else if(strtolower($selection) == 'a'){
			$this->user_interaction = FALSE;
			switch($command){
				case 'start':
					$this->startAllArkServers();
					break;
				case 'stop':
					$this->gracefullyStopTheServer();
					break;
				case 'restart':
					$this->initiateArkRestart();
					break;
				case 'backup':
					$user_comment = $this->promptUserForComment();
					TimeStamp('Here is the user comment ' . $user_comment);
					$this->backupSavedData(NULL, $user_comment);
					break;
			}
		} else if(strtolower($selection) == 'q'){
			exit("Quitting...\n");
		} else {
			echo "Invalid selection! Please try again!\n";
			$this->userFunctionInteractive($server_array, $command);
		}
	}
	
	function promptUserForComment()
	{
		$not_allowed = array("\n","\t","\r");
		$user_comment = readline("Please add a comment: ");
		if(empty($user_comment) === TRUE){
			$user_comment = NULL;
		}
		return str_replace($not_allowed,'',$user_comment);
	}
	
	function startRestoreProcess($shard_name)
	{
		GLOBAL $backup_path;
		/*
		1.) build function that populates an array of all backups available then check each to see if a comment is available, if so populate the array with the comment
		2.) Display the information to the user in a selection
		3.) Extract the backup to a temporary location
		4.) Remove the old saved data
		use $this->combined_server_info[$shard_name]['shard_dir'] for easy access to the shard directory
		5.) Move the extracted contents to the correct location
		*/
		
		$raw_backup_file_list = $this->retrieveBackupFileList($shard_name);
		$backup_list_with_comments = $this->retrieveBackupComments($raw_backup_file_list, $backup_path);
		$selected_file = str_replace($backup_path . '/','',$this->askUserRestoreChoice($backup_list_with_comments, $shard_name));
		$file_location = $backup_path . DIRECTORY_SEPARATOR . $selected_file;
		//TimeStamp('here is file_location: ' . $file_location . "\nhere is backup_path: " . $backup_path);
		$extracted_location = $this->extractBackupContents($file_location, $selected_file);
		//Stop Server if it's running
		foreach($this->combined_server_info as $name => $server_info){
			if($name == $shard_name && array_key_exists('PID',$server_info)){
				$this->gracefullyStopTheServer($server_info['PID']);
			}
		}
		$this->swapInBackupData($extracted_location, $this->combined_server_info[$shard_name]['shard_dir']);
		echo "Restoration is complete!\nWe Restored this file: " . $selected_file . " for this server: " . $shard_name . "\n";
	}
	
	function retrieveBackupComments($raw_backup_file_list, $backup_path)
	{
		$new_array = array();
		arsort($raw_backup_file_list); //Lets reverse the order so the newest is first in the list
		foreach($raw_backup_file_list as $key => $backup_file){
			$file_loc = $backup_path . DIRECTORY_SEPARATOR . $backup_file;
			//TimeStamp('fileloc is this ' . $file_loc);
			$new_array[$key]['file_location'] = $file_loc;
			if(strpos($backup_file,'-c-') !== FALSE){
				$new_array[$key]['comment'] = $this->extractBackupComment($backup_file, $backup_path);
			}
		}
		return $new_array;
	}
	
	function extractBackupComment($backup_file, $backup_path)
	{
		$extracted_path = $backup_file . ' ' . trim($backup_file,'.zip') . '/Saved/comment.txt';
		$shell_cmd = 'cd ' . $backup_path . ' && unzip ' . $extracted_path;
		//TimeStamp('here is the shell_cmd from extractBackupComment ' . $shell_cmd);
		shell_exec($shell_cmd);
		
		$comment_path = $backup_path . DIRECTORY_SEPARATOR . trim($backup_file,'.zip') . '/Saved/comment.txt';
		
		if(file_exists($comment_path) === TRUE){
			$shell_cmd = 'cat ' . $comment_path;
			$user_comment = shell_exec($shell_cmd);
		} else {
			$user_comment = NULL;
		}
		
		$this->removeExtractedComment($backup_path . DIRECTORY_SEPARATOR . trim($backup_file,'.zip'));
		return $user_comment;
	}
	
	function removeExtractedComment($dir)
	{
		$shell_cmd = 'rm -r ' . $dir;
		shell_exec($shell_cmd);
	}
	
	function swapInBackupData($extracted_location, $shard_directory)
	{
		//remove the old saved data first
		$shell_cmd = 'rm -r ' . $shard_directory . DIRECTORY_SEPARATOR . 'ShooterGame' . DIRECTORY_SEPARATOR . 'Saved';
		shell_exec($shell_cmd);
		
		//Move the extracted contents to the shard directory
		$shell_cmd = 'mv ' . $extracted_location . DIRECTORY_SEPARATOR . 'Saved ' . $shard_directory . DIRECTORY_SEPARATOR . 'ShooterGame' . DIRECTORY_SEPARATOR;
		shell_exec($shell_cmd);
		
		//Remove Comment if it is there
		$possible_comment_location = $shard_directory . DIRECTORY_SEPARATOR . 'ShooterGame' . DIRECTORY_SEPARATOR . 'Saved' . DIRECTORY_SEPARATOR . 'comment.txt';
		if(file_exists($possible_comment_location)){
			unlink($possible_comment_location);
		}
		
	}
	
	function extractBackupContents($file_location, $selected_file)
	{
		//Copy the backup to /tmp
		$tmp_location = '/tmp/' . $selected_file;
		//TimeStamp('file_location is: ' . $file_location . ' tmp_location is ' . $tmp_location);
		copy($file_location, $tmp_location);
		
		//Unzip the backup file in /tmp
		$shell_cmd = 'unzip ' . $tmp_location . ' -d /tmp/';
		//TimeStamp('Here is the shell_cmd for unzipping the backup file: ' . $shell_cmd);
		shell_exec($shell_cmd);
		
		//Remove Copy of the backup file
		if(file_exists($tmp_location)){
			unlink($tmp_location);
		}
		return trim($tmp_location, '.zip');
	}
	
	function askUserRestoreChoice($raw_backup_file_list, $shard_name)
	{
		$counter = 1;
		$not_allowed = array("\n","\t","\r");
		$selection_array = array();
		echo "\033[32mPlease input the selection for the backup you wish to restore, or Q to quit.\033[37m\n";
		$backup_string_array = array();
		$longest_user_comment = 0;
		foreach($raw_backup_file_list as $array_content){
			$spacer = $this->addSpacePadding(strlen($counter),strlen(count($raw_backup_file_list)));
			if(!isset($header_spacer)){
				//If this isn't set we're setting it to the first created spacer which will always have maximum padding for [ Number ] in the echo
				$header_spacer = $spacer;
			}
			$user_comment = '';
			$file_name = $array_content['file_location'];
			if(array_key_exists('comment',$array_content) && !empty($array_content['comment'])){
				$user_comment = str_replace($not_allowed,'',$array_content['comment']);
				$user_comment_length = strlen($user_comment);
				if($longest_user_comment < $user_comment_length){
					$longest_user_comment = $user_comment_length;
				}
			}
			$time_stamp = $this->isolateBackupTimeStamp($file_name);
			$backup_timestamp_string = $time_stamp['Date'] . ' @ ' . $time_stamp['Time'];
			$backup_comment_string = $user_comment . "\033[37m";
			$backup_string_array[$counter]['raw_string'] = str_replace('REPLACE',$spacer,"\033[35m[\033[33m " . $counter . 'REPLACE' . "\033[35m ] \033[37m| \033[35m") . $backup_timestamp_string . " \033[37m| " . $backup_comment_string;
			$backup_string_array[$counter]['user_comment'] = $backup_comment_string;
			$selection_array[$counter] = $file_name;
			$counter++;
		}
		$header_timestamp_string = "Date & Time";
		$header_comment_string = "Comment";
		$longest_string = $this->findLongestStringInArray($backup_string_array);
		if($longest_user_comment == 0){
			//This is if the server has no user comments whatsoever.
			$longest_user_comment = strlen(' Comment ');
			$longest_string = $longest_string + 9;
		}
		$header_comment_spacer = $this->addSpacePadding(strlen($header_comment_string),$longest_user_comment);
		$padding_string = $this->generatePaddingString($longest_string);
		$timestamp_spacer = $this->addSpacePadding(strlen($header_timestamp_string),strlen($backup_timestamp_string));
		echo $padding_string;
		//Header String
		echo "| \033[35m[\033[33m #" . $header_spacer . "\033[35m ]\033[37m | " . $header_timestamp_string . $timestamp_spacer . " | " . $header_comment_string . $header_comment_spacer . " |\n";
		echo $padding_string;
		$backup_string_array = $this->generateCompleteBackupString($backup_string_array, $longest_user_comment);
		foreach($backup_string_array as $key => $array_contents){
			echo "| " . $array_contents['complete_string'] . "\n";
		}
		echo $padding_string;
		$user_response = readline('Input: ');
		if(array_key_exists($user_response,$selection_array)){
			return $selection_array[$user_response];
		} else if(strtolower($user_response) === 'q'){
			exit();
		} else {
			echo "Invalid selection, please try again!\n";
			$this->startRestoreProcess($shard_name);
		}
	}
	
	function generateCompleteBackupString($backup_string_array, $longest_user_comment)
	{
		$remove_these = array("\033[35m","\033[33m","\033[37m","\n");
		foreach($backup_string_array as $key => $array_content){
			$cleaned_up_comment = str_replace($remove_these,'',$array_content['user_comment']);
			$user_comment_spacer = $this->addSpacePadding(strlen($cleaned_up_comment),$longest_user_comment);
			$raw_string = str_replace($array_content['user_comment'],'',$array_content['raw_string']);
			$backup_string_array[$key]['complete_string'] = str_replace('|',"\033[37m|",$raw_string) . $array_content['user_comment'] . $user_comment_spacer . " |";
		}
		return $backup_string_array;
	}
	
	function generatePaddingString($string)
	{
		//This is another superflous function for making the backup restoration echo 'fancy'.
		$padding_string = '';
		for($i = 0; $i < $string; $i++){
			$padding_string = $padding_string . '-';
		}
		return "|" . $padding_string . "|\n";
	}
	
	function findLongestStringInArray($array)
	{
		//This is another superflous function for making the backup restoration echo 'fancy'.
		$color_control_characters = array("\033[35m","\033[33m","\033[37m");
		$length = 0;
		foreach($array as $array_contents){
			$string = str_replace($color_control_characters,'',$array_contents['raw_string']);
			$string_length = strlen($string);
			if($string_length > $length){
				$length = $string_length;
			}
		}
		return $length + 2; //Including extra length for the added ' |' at the end of each echo
	}
	
	function addSpacePadding($small_length, $larger_length)
	{
		//I want the restoration echo to be evenly spaced. Is this dumb? Probably.
		$padding = 0;
		if($small_length <= $larger_length){
			$padding = $larger_length - $small_length;
		}
		$spacer = '';
		for($i = 0; $i < $padding; $i++){
			$spacer = $spacer . ' ';
		}
		return $spacer;
	}
	
	function isolateBackupTimeStamp($file_name)
	{
		$file_name = str_replace('-c-','',$file_name);
		$raw_array = explode('-',$file_name);
		$date = $raw_array[1];
		$time_stamp = str_replace('_',':',$raw_array[2]);
		$time_stamp = trim($time_stamp,'.zip');
		return array('Date' => $date, 'Time' => $time_stamp);
		
	}
	
	function retrieveBackupFileList($shard_name)
	{
		GLOBAL $backup_path;
		$dir_contents = scandir($backup_path);
		$backup_file_list = array();
		foreach($dir_contents as $content){
			if(strpos($content,$shard_name) !== FALSE){
				$backup_file_list[] = $content;
			}
		}
		return $backup_file_list;
	}
	
	function executeArkServerStartCmd($server_name, $start_command)
	{
		if($this->checkIfAlreadyRunning($server_name) === FALSE){
			TimeStamp('Starting Server => '. $server_name);
			$shell_cmd = 'nohup ' . $start_command . ' > /dev/null 2>&1 & ';
			shell_exec($shell_cmd);
		} else {
			echo "Preventing the start command from being issued for " . $server_name . " because it is already running!\n";
		}
	}
	
	function checkIfAlreadyRunning($server_name)
	{
		$server_array = $this->ark_server_obj->running_servers;
		if($server_array !== NULL && array_key_exists($server_name,$server_array) !== FALSE){
			return true;
		} else {
			return false;
		}
	}
	
	function gracefullyStopTheServer($PID = NULL)
	{
		$server_array = $this->ark_server_obj->running_servers;
		TimeStamp('Attempting to Stop the Ark server(s)');
		if(!empty($server_array)){
			if(isset($this->user_option) === TRUE && isset($this->user_interaction) === FALSE){
				if(array_key_exists($this->user_option,$server_array) === TRUE){
					$PID = $server_array[$this->user_option]['PID'];
				} else {
					echo $this->user_option . " is not running!\n";
				}
			} else if(isset($this->user_interaction) === FALSE){
				//This means the user didn't not pass a second option so we're going to stop all the Ark servers.
				foreach($server_array as $server_name => $running_server_info){
					posix_kill($running_server_info['PID'],SIGINT);
					//Unset this server from $this->ark_server_obj->running_servers
					unset($this->ark_server_obj->running_servers[$server_name]);
				}
			}
			if($PID !== NULL){
				posix_kill($PID,SIGINT);
				$server_name = $this->findRunningServerNameByPID($PID);
				unset($this->ark_server_obj->running_servers[$server_name]);
			}
		}
		$this->verifyServerIsStopped($PID, $server_array);
	}
	
	function findRunningServerNameByPID($PID)
	{
		$haystack = $this->ark_server_obj->running_servers;
		foreach($haystack as $server_name => $server_details){
			if($PID == $server_details['PID']){
				return $server_name;
			}
		}
		return false; //NEED PROPER ERROR HANDLING -- we should never get false
	}
	
	function verifyServerIsStopped($PID, $server_array)
	{
		$server_up = TRUE;
		$counter = 0;
		while($server_up === TRUE){
			if($counter >= 30){
				TimeStamp('Forcibly killing the server(s) because SIGINT failed.');
				if($PID !== NULL){
					//If we waited for 90 seconds and the server hasn't stopped, forcibly kill it
					posix_kill($PID,SIGKILL);
				} else {
					foreach($server_array as $running_server){
						posix_kill($running_server['PID'],SIGKILL);
					}
				}
				$server_up = FALSE;
				break;
			}
			$result = $this->ark_server_obj->returnCheckServerStatus();
			if($PID !== NULL){
				//This part of the function is if the user selects a specific server to stop
				if(isset($selected_server_still_up) === TRUE){
					unset($selected_server_still_up);
				}
				if(!empty($result)){
					foreach($result as $server_result){
						if($server_result['PID'] == $PID){
							$selected_server_still_up = TRUE;
						}
					}
				}
				if(!isset($selected_server_still_up)){
					$server_up = FALSE;
					break;
				}
			} else if(empty($result)){
				$server_up = FALSE;
				break;
			}
			sleep(3);
			$counter++;
		}
	}
	
	function backupSavedData($user_selection = NULL, $user_comment = NULL)
	{
		GLOBAL $backup_path;
		GLOBAL $server_shard_directory;
		
		$shards = ArkStartCommands::findShardInstances($server_shard_directory);
		foreach($shards as $shard_loc){
			$server_name = ArkStartCommands::extractShardSessionSettings($shard_loc, 'SessionName');
			if($user_selection !== NULL && $user_selection == $server_name && $user_comment !== NULL){
				//If the user selects a specific server to backup and adds a comment
				TimeStamp('Performing backup with user comment: ' . $user_comment);
				$this->preformBackupCommands($backup_path, $server_name, $shard_loc, $user_comment);
				break;
			} else if($user_comment === NULL && $user_selection === NULL){
				//No comment and no specific server
				$this->preformBackupCommands($backup_path, $server_name, $shard_loc);
			} else if($user_selection === NULL && $user_comment !== NULL){
				//All servers are to be backed up and there's a new comment
				$this->preformBackupCommands($backup_path, $server_name, $shard_loc, $user_comment);
			}
		}
		TimeStamp('Server File backup complete');
	}
	
	function preformBackupCommands($backup_path, $server_name, $shard_loc, $user_comment = NULL)
	{
		$today = new DateTime();
		$now = $today->format('Y_m_d-h_i_s');
		$folder_name = 'ark_backup_' . $server_name . '-' . $now . '-c-';
		$saved_data_loc = $shard_loc . DIRECTORY_SEPARATOR . 'ShooterGame' . DIRECTORY_SEPARATOR . 'Saved';
		$backup_loc = $backup_path . DIRECTORY_SEPARATOR . $folder_name;
		if(!file_exists($backup_loc)){
			mkdir($backup_loc,0777);
		}
		$shell_cmd = 'cp -a ' . $saved_data_loc . ' ' . $backup_loc;
		TimeStamp('Copying Ark Server Files for this shard: ' . $shard_loc);
		shell_exec($shell_cmd);
		
		if($user_comment !== NULL){
			$shell_cmd = "echo '" . $user_comment . "' > " . $backup_loc . "/Saved/comment.txt";
			TimeStamp('Here is the shell_cmd for user_comment which is NOT NULL: ' . $user_comment);
			shell_exec($shell_cmd);
		}
		/*
		Example command:
		cd /home/steam/ark_server_backup && zip -r9 /home/steam/ark_server_backup/ark_backup_test_ark-2019_01_22-08_36_28 ./ark_backup_test_ark-2019_01_22-08_36_28/
		*/
		$shell_cmd = 'cd ' . $backup_path . ' && zip -r9 ' . $backup_loc . ' ./' . $folder_name . DIRECTORY_SEPARATOR;
		TimeStamp('Compressing Server Saved Data');
		shell_exec($shell_cmd);
		
		$shell_cmd = 'rm -r ' . $backup_loc;
		TimeStamp('Cleaning up uncompressed backup files');
		shell_exec($shell_cmd);
	}
	
	function RotateTheBackupLogs($backup_path)
	{
		TimeStamp('Rotating Backup Log Files');
		$today = new DateTime();
		$backup_log_dir = $backup_path . DIRECTORY_SEPARATOR . 'backup_logs';
		$file_list = scandir($backup_log_dir);
		foreach($file_list as $file){
			if($file !== '.' && $file !== '..' && strpos($file,'.log') !== FALSE){
				$file_path = $backup_log_dir . DIRECTORY_SEPARATOR . $file;
				$file_parts = explode('_',$file);
				$file_date_string = $file_parts[1];
				$file_date = new DateTime($file_date_string);
				$date_diff = $today->diff($file_date);
				$file_age_in_days = intval($date_diff->format('%a'));
				if($file_age_in_days >= 90){
					unlink($file_path);
					TimeStamp('Removing ' . $file_path);
				}
			}
		}
		
	}

	function RotateTheBackups($backup_path)
	{
		TimeStamp('Rotating out old backups');
		$today = new DateTime();
		$backup_list = scandir($backup_path);
		foreach($backup_list as $backup_file){
			if($backup_file !== '.' && $backup_file !== '..' && strpos($backup_file,'.zip') !== FALSE){
				$file_path = $backup_path . DIRECTORY_SEPARATOR . $backup_file;
				$backup_file_parts = explode('-',$backup_file);
				$file_date_string = str_replace('_','-',$backup_file_parts[1]);
				$file_date = new DateTime($file_date_string);
				$date_diff = $today->diff($file_date);
				$file_age_in_days = intval($date_diff->format('%a'));
				if($file_age_in_days >= 90){
					unlink($file_path);
					TimeStamp('Removing ' . $file_path);
				}
			}
		}
		TimeStamp('Backup Rotation Complete');
	}
}

Class ArkStartCommands
{
	CONST START_CMD_PART_BEGIN = DIRECTORY_SEPARATOR . 'ShooterGame' . DIRECTORY_SEPARATOR . 'Binaries' . DIRECTORY_SEPARATOR . 'Linux' . DIRECTORY_SEPARATOR . 'ShooterGameServer ';
	CONST START_CMD_PART_END = '?listen';
	public $server_array_start_commands;
	public $running_servers;
	
	function __construct($shard_directory)
	{
		$this->server_array_start_commands = $this->buildArkServerArray($shard_directory);
		$this->notateRunningServers();
	}
	
	function returnCheckServerStatus()
	{
		return $this->checkServerStatus();
	}
	
	function notateRunningServers()
	{
		$process_array = $this->checkServerStatus();
		if(!empty($process_array)){
			foreach($process_array as $process_info){
				$shard = $this->extractShard($process_info);
				$ports = $this->extractPortsFromProcessInfo($process_info['PID']);
				foreach($this->server_array_start_commands as $ark_server => $ark_start_command){
					if(strpos($ark_start_command,$shard) !== FALSE){
						$this->running_servers[$ark_server]['PID'] = $process_info['PID'];
						$this->running_servers[$ark_server]['Shard_Number'] = $shard;
						$this->running_servers[$ark_server]['ServerMap'] = $process_info['ServerMap'];
						$this->running_servers[$ark_server]['Ports'] = $ports;
					}
				}
			}
		}
	}
	
	function extractPortsFromProcessInfo($PID)
	{
		$shell_cmd = 'ps -ef | grep ' . $PID . ' | grep -vi grep';
		$raw_process_string = shell_exec($shell_cmd);		
		$dirty_array = explode(' ',$raw_process_string);
		foreach($dirty_array as $dirty_string){
			if(strpos($dirty_string,'listen?')){
				$port_array = explode('?',$dirty_string);
			}
		}
		$return_string = '';
		if(isset($port_array) === TRUE){
			foreach($port_array as $key => $port_string){
				if(strpos(strtolower($port_string),'port') !== FALSE){
					$return_string = $return_string . $port_string . ' ';
				}
			}
		}
		$return_string = trim($return_string,' ');
		$return_string = str_replace(' ',', ',$return_string);
		return $return_string;
	}
	
	function extractShard($process_info)
	{
		if(array_key_exists('ServerPath',$process_info) !== FALSE){
			$info_array = explode('/',$process_info['ServerPath']);
			foreach($info_array as $path_part){
				if(strpos($path_part,'shard_') !== FALSE){
					return $path_part;
				}
			}
		}
	}
	
	function checkServerStatus()
	{
		$shell_cmd = 'ps ax | grep -i shootergame | grep -vi grep';
		$results = shell_exec($shell_cmd);
		$server_array = array();
		$result_array = explode("\n",$results);
		foreach($result_array as $result){
			if(!empty($result)){
			$server_array[] = $this->BuildServerStatusArray($result);
			}
		}
		return $server_array;
	}

	function BuildServerStatusArray($string)
	{
		$status_array = array();
		$status_array['PID'] = $this->IsolatePID($string);
		$status_array['ServerPath'] = $this->IsolateServerPath($string);
		$status_array['ServerMap'] = $this->IsolateServerMap($string);
		return $status_array;
	}

	function IsolatePID($string)
	{
		preg_match('/(\s\d+\s\b|\d+\s\b|^\d+\s|^\s\d+|^.+\s\d+\s)/',$string,$matches);
		$PID = trim($matches[0]);
		return $PID;
	}

	function IsolateServerPath($string)
	{
		preg_match('/\/[a-zA-Z].+\s/',$string,$matches);
		$file_path = trim($matches[0]);
		return $file_path;
	}

	function IsolateServerMap($string)
	{
		preg_match('/\s\w+\?/',$string,$matches);
		$server_map = trim(str_replace('?','',$matches[0]));
		return $server_map;
	}
	
	function buildArkServerArray($shard_directory)
	{
		$server_array = array();
		$shards = $this->findShardInstances($shard_directory);
		foreach($shards as $shard_location){
			$shard_contents = scandir($shard_location);
			$shard_map = $this->extractShardSessionSettings($shard_location,'Server_Map');
			$shard_session_name = $this->extractShardSessionSettings($shard_location,'SessionName');
			$shard_query_port = $this->extractShardSessionSettings($shard_location,'QueryPort');
			$shard_game_port = $this->extractShardSessionSettings($shard_location,'GamePort');
			$shard_rcon_port = $this->extractShardSessionSettings($shard_location,'RCONPort');
			$shard_battle_eye = $this->extractShardSessionSettings($shard_location,'battle_eye');
			if($shard_map === FALSE || $shard_session_name === FALSE || $shard_query_port === FALSE || $shard_game_port === FALSE || $shard_rcon_port === FALSE){
				TimeStamp("ERROR -> This shard is missing key configuration and will be skipped " . $shard_location);
			} else {
				$start_string = $shard_location . SELF::START_CMD_PART_BEGIN . $shard_map . SELF::START_CMD_PART_END;
				$start_string = $start_string . '?QueryPort=' . $shard_query_port . '?Port=' . $shard_game_port . '?RCONPort=' . $shard_rcon_port . '?bRawSockets';
				if($shard_battle_eye == 'false'){
					$start_string = $start_string . ' -NoBattlEye';
				}
				$server_array[$shard_session_name] = $start_string;
			}
		}
		return $server_array;
	}
	
	function findShardInstances($shard_directory)
	{
		$shards = array();
		$contents = scandir($shard_directory);
		foreach($contents as $item){
			$item_location = $shard_directory . $item;
			if(strpos($item,'shard_') !== FALSE && is_dir($item_location) === TRUE){
				$shards[] = $item_location;
			}
		}
		return $shards;
	}
	
	function extractShardSessionSettings($shard_folder, $setting_name)
	{
		$config_file_path_part = UpdateShardConfiguration::returnConfigFilePath();
		if($setting_name == 'SessionName'){
			$config_file_path = $shard_folder . $config_file_path_part . 'GameUserSettings.ini';
		} else {
			$config_file_path = $shard_folder . $config_file_path_part . 'shard_config.ini';
		}
		$config_file_contents = file_get_contents($config_file_path);
		$config_file_array = explode("\n",$config_file_contents);
		foreach($config_file_array as $config_file_line){
			if(strpos($config_file_line,$setting_name) !== FALSE){
				$session_name_array = explode('=',$config_file_line);
				return $session_name_array[1];
			}
		}
		return false;
	}
	
}

Class UpdateShardConfiguration
{
	Const CONFIG_FILE_PATH = DIRECTORY_SEPARATOR . 'ShooterGame' . DIRECTORY_SEPARATOR . 'Saved' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'LinuxServer' . DIRECTORY_SEPARATOR;
	Const CONFIG_FILES = array('GameUserSettings.ini','Game.ini','shard_config.ini');
	public $shard_folder_path;
	public $new_config;
	public $settings_we_care_about;
	public $detected_servers;
	
	function __construct($shard_location, $detected_servers)
	{
		TimeStamp('Now reading config files to determine which settings to update');
		$this->loadSettings();
		$this->shard_folder_path = $shard_location . SELF::CONFIG_FILE_PATH;
		$this->detected_servers = $detected_servers;
		if($this->checkSavedFolder($shard_location) === FALSE){
			//This means the config files don't exist and we need to generate them
			$this->new_config = TRUE;
			$this->generateNewGameConfigFiles($shard_location);
		}
		foreach(SELF::CONFIG_FILES as $config_file){
			TimeStamp($config_file);
			$config_file_location = $this->shard_folder_path . $config_file;
			if($this->new_config === TRUE){
				$new_config = $this->generateNewShardConfig($config_file);
			} else {
				$config_data = $this->readShardConfig($config_file_location);
				$new_config = $this->updateShardConfig($config_data, $config_file);
			}
			$this->saveShardConfig($new_config,$config_file_location);
		}
	}
	
	function loadSettings()
	{
		$settings_location = __DIR__ . DIRECTORY_SEPARATOR . 'ark_configuration_file_settings.php';
		echo "this is the settings location\n";
		print_r($settings_location);
		if(!file_exists($settings_location)){
			exit($settings_location . ' not found or cannot be read. Configuration cannot proceed! Exiting');
		} else {
			include($settings_location);
		}
		$this->settings_we_care_about = returnSettings();
	}
	
	function checkSavedFolder($shard_location)
	{
		//Returns true if the folder exists, false if it doesn't exist
		if(!file_exists($this->shard_folder_path)){
			TimeStamp('Creating a new configuration directory at this location ' . $this->shard_folder_path);
			mkdir($this->shard_folder_path,0777,TRUE);
			return false;
		}
		return true;
	}
	
	function generateNewGameConfigFiles($shard_location)
	{	
		//Create shard_config.ini
		file_put_contents($this->shard_folder_path . 'shard_config.ini','');
		
		//Create Game.ini
		file_put_contents($this->shard_folder_path . 'Game.ini','');
	
		//Create GameUserSettings.ini
		file_put_contents($this->shard_folder_path . 'GameUserSettings.ini','');
	}
	
	function readShardConfig($config_file_location)
	{
		$config_file = file_get_contents($config_file_location);
		return explode("\n",$config_file);
	}
	
	function updateShardConfig($config_file_array, $config_file)
	{
		foreach($config_file_array as $key => $line)
		{
			if(strpos($line,'=') !== FALSE){
				$line_array = explode('=',$line);
				$count = count($line_array);
				if($count > 1){
					$setting_name = $line_array[0];
					$setting_value = $line_array[1];
					foreach($this->settings_we_care_about as $setting_array){
						if($setting_array['Name'] == $setting_name && $setting_array['File'] == $config_file){
							if(isset($setting_array['Value_Type']) && $setting_array['Value_Type'] == 'server_mandatory'){
								//We skip these settings because the user should never have to modify them.
								continue;
							}
							$new_setting = $this->promptUserUpdate($setting_name);
							$config_file_array[$key] = $setting_name . '=' . $new_setting;
						}
					}
				}
			}
		}
		return $config_file_array;
	}
	
	function generateNewShardConfig($config_file)
	{
		$header = NULL;
		$settings_array = $this->organizeTheSettings();
		$new_config_file_array = array();
		foreach($settings_array as $file_name => $file_settings){
			if($config_file == $file_name){
				if(array_key_exists('Header',$file_settings) !== FALSE){
					unset($file_settings['Header']);
					foreach($file_settings as $header_name => $setting_group){
						if(!isset($header) || $header != $header_name){
							$header = $header_name;
							$new_config_file_array[] = $header;
						}
						foreach($setting_group as $individual_setting){
							if(isset($individual_setting['Value_Type']) && $individual_setting['Value_Type'] == 'server_mandatory'){
								/*This is for server settings that NEED to be in the config file, but the user will never care to set
								If these aren't set the server will over write the config file name GameUserSettings.ini*/
								$new_config_file_array[] = $individual_setting['Name'] . '=' . $individual_setting['Default'];
							} else {
								$new_setting = $this->promptUserUpdate($individual_setting);
								$new_config_file_array[] = $individual_setting['Name'] . '=' . $new_setting;
							}
						}
					}
				} else {
					//This block will only ever be for shard_config.ini
					foreach($file_settings as $individual_setting){
						$new_setting = $this->promptUserUpdate($individual_setting);
						$new_config_file_array[] = $individual_setting['Name'] . '=' . $new_setting;
					}
				}
			}
		}
		return $new_config_file_array;
	}
	
	function organizeTheSettings()
	{
		$new_setting_array = array();
		foreach($this->settings_we_care_about as $unorganized_array){
			if(isset($unorganized_array['Value_Type'])){
				$value_type = $unorganized_array['Value_Type'];
			} else {
				$value_type = '';
			}
			if(array_key_exists('Header',$unorganized_array) !== FALSE){
				$new_setting_array[$unorganized_array['File']]['Header'] = 'True';
				$new_setting_array[$unorganized_array['File']][$unorganized_array['Header']][] = array('Name' => $unorganized_array['Name'],'Help' => $unorganized_array['Help'], 'Default' => $unorganized_array['Default'], 'Value_Type' => $value_type);
			} else {
				$new_setting_array[$unorganized_array['File']][] = array('Name' => $unorganized_array['Name'],'Help' => $unorganized_array['Help'], 'Default' => $unorganized_array['Default'], 'Value_Type' => $value_type);
			}
		}
		return $new_setting_array;
	}
	
	function promptUserUpdate($setting_array)
	{
		echo "\033[36mPlease set the value for " . $setting_array['Name'] . "\033[37m";
		if($setting_array['Help'] != ''){
			echo "\n\033[35m" . $setting_array['Help'] . "\033[37m";
		}
		if($setting_array['Default'] != ''){
			echo "\n\033[31mHit Enter for default value: " . $setting_array['Default'] . "\033[37m\n";
		} else {
			echo "\n";
		}
		
		if(isset($setting_array['Value_Type'])){
			switch($setting_array['Value_Type']){
				case 't/f':
					$help_text = '"true" or "false" only';
					break;
				case 'numerical':
					$help_text = 'Input numerical value';
					break;
				default:
					$help_text = 'Enter setting value';
					break;
			}
		} else {
			$help_text = 'Enter setting value';
		}
		
		$user_response = readline($help_text . ": ");
		if($user_response == ''){
			$user_response = $setting_array['Default'];
		}
		if($setting_array['Name'] == 'SessionName'){
			$check_result = $this->checkUsedSessionNames($user_response);
			if($check_result === FALSE){
				return $user_response;
			} else {
				//Prompt user to input a unique name
				echo "\n" . $user_response . " is already a used session name for a server. Please input a unique name.\n";
				$this->promptUserUpdate($setting_array); //Restart the prompt
			}
		}
		return $user_response;
	}
	
	function checkUsedSessionNames($user_response)
	{
		//Return TRUE if user_response (aka their input server name) is contained in the detected_servers array; FALSE otherwise
		if(array_key_exists($user_response,$this->detected_servers) !== FALSE){
			return TRUE;
		} else {
			return FALSE;
		}
	}
	
	function saveShardConfig($new_config,$config_file_location)
	{
		unlink($config_file_location);
		$new_config_string = implode("\n",$new_config);
		file_put_contents($config_file_location,$new_config_string);
	}
	
	function returnConfigFilePath()
	{
		return SELF::CONFIG_FILE_PATH;
	}
}

Class BuildNewServerDirectory
{
	public $new_shard_directory;
	
	function __construct($root_server_directory, $server_shard_directory,$update = FALSE, $update_shard_dir = NULL)
	{
		TimeStamp('Creating a new shard directory');
		if($update === FALSE){
			$this->new_shard_directory = $this->createNewShardDirectory($server_shard_directory);
		} else {			
			$this->new_shard_directory = $update_shard_dir;
			TimeStamp('This is the new shard directory: ' . $this->new_shard_directory);
		}
		$this->populateNewShardFolder($root_server_directory,$this->new_shard_directory);
	}
	
	function createNewShardDirectory($server_shard_directory)
	{
		$shard_name = $this->generateNewShardName($server_shard_directory);
		$new_shard_directory = $server_shard_directory . $shard_name;
		TimeStamp('Attempting to create a new shard directory here: ' . $new_shard_directory);
		mkdir($new_shard_directory, 0777, FALSE);
		return $new_shard_directory;
	}
	
	function populateNewShardFolder($root_server_directory,$new_shard_directory)
	{
		$this->recursivelyCreateSymLinks($root_server_directory,$new_shard_directory);
	}
	
	function recursivelyCreateSymLinks($original_directory,$new_location)
	{
		$original_directory_contents = scandir($original_directory);
		foreach($original_directory_contents as $item){
			if($item != '.' && $item != '..'){
				$item_location = $original_directory . DIRECTORY_SEPARATOR . $item;
				if(is_dir($item_location) === FALSE && $item != 'ShooterGameServer'){
					$target = $new_location . DIRECTORY_SEPARATOR . '"' . $item . '"';
					$real_file = $original_directory . DIRECTORY_SEPARATOR . '"' . $item . '"';
					$this->createSymbolicLink($target,$real_file);
				}
				if(is_dir($item_location) === TRUE){
					$target = $new_location . DIRECTORY_SEPARATOR . $item;
					mkdir($target, 0777, FALSE);
					$this->recursivelyCreateSymLinks($item_location,$target);
				}
				if($item == 'ShooterGameServer'){
					$shell_cmd = 'cp ' . $item_location . ' ' . $new_location . DIRECTORY_SEPARATOR . '"' . $item . '"';
					shell_exec($shell_cmd);
				}
			}
		}
	}
	
	function createSymbolicLink($symbolic_file,$real_file)
	{
		$shell_cmd = 'ln -s ' . $real_file . ' ' . $symbolic_file;
		shell_exec($shell_cmd);
	}
	
	function generateNewShardName($server_shard_directory)
	{
		//Ark Shards will be named like so shard_1, shard_2, etc.
		$shard_count = 0;
		$possible_ark_shards = array();
		$contents = scandir($server_shard_directory);
		foreach($contents as $item){
			if($item != '.' || $item != '..'){
				$possible_ark_shards[] = $item;
			}
		}
		foreach($possible_ark_shards as $shard){
			if(is_dir($server_shard_directory . $shard) === TRUE){
				if(strpos($shard,'shard') !== FALSE || strpos($shard,'shard') != '0'){
					$shard_count++;
				}
			}
		}
		$shard_count++;
		return 'shard_' . $shard_count;
	}
	
}

Class UpdateTheArkServer
{
	function __construct($steam_cmd_path, $root_server_files, $shard_data_loc)
	{
		$this->performServerUpdate($steam_cmd_path, $root_server_files);
		$this->updateShardSymlinks($root_server_files,$shard_data_loc);
	}

	function performServerUpdate($steam_cmd_path, $root_server_files)
	{
		$shell_cmd = $steam_cmd_path . ' +login anonymous +force_install_dir ' . $root_server_files . ' +app_update 376030 validate +exit';
		TimeStamp('Installing/Updating Ark Server Root Files');
		shell_exec($shell_cmd);
		TimeStamp('Ark Server Root files have finished installing/updating');
	}
	
	function updateShardSymlinks($root_server_files, $shard_data_loc)
	{
		$shards = ArkStartCommands::findShardInstances($shard_data_loc);
		foreach($shards as $shard_session_loc){
			TimeStamp('Now backing up data for the shard located here: ' . $shard_session_loc);
			$saved_data_loc = $this->moveSavedDataToSafey($shard_session_loc);
			$this->removeOldShardDirectory($shard_session_loc);
			TimeStamp('Building New Server Directory for the shard located here: ' . $shard_session_loc);
			$this->createNewShardDirectory($root_server_files,$shard_session_loc);
			$this->replaceSavedData($saved_data_loc,$shard_session_loc);
		}
	}
	
	function createNewShardDirectory($root_server_files,$shard_session_loc)
	{
		mkdir($shard_session_loc, 0777, FALSE);
		new BuildNewServerDirectory($root_server_files, NULL, TRUE, $shard_session_loc);
	}
	
	function replaceSavedData($saved_data_loc,$shard_session_loc)
	{
		$new_saved_data_loc = $shard_session_loc . DIRECTORY_SEPARATOR . 'ShooterGame' . DIRECTORY_SEPARATOR;
		if(file_exists($new_saved_data_loc . 'Saved')){
			$shell_cmd = 'rm -r ' . $new_saved_data_loc . 'Saved';
			TimeStamp('Removing empty default Saved folder with this command: ' . $shell_cmd);
			shell_exec($shell_cmd);
		}
		$shell_cmd = 'mv ' . $saved_data_loc . ' ' . $new_saved_data_loc;
		TimeStamp('Replacing Saved Data with this command: ' . $shell_cmd);
		shell_exec($shell_cmd);
	}
	
	function moveSavedDataToSafey($shard_session_loc)
	{
		$saved_data_loc = $shard_session_loc . DIRECTORY_SEPARATOR . 'ShooterGame' . DIRECTORY_SEPARATOR . 'Saved';
		$shell_cmd = 'cp -r ' . $saved_data_loc . ' ' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;
		TimeStamp('Moving Saved Data to safety with this command: ' . $shell_cmd);
		shell_exec($shell_cmd);
		return DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'Saved';
	}
	
	function removeOldShardDirectory($shard_loc)
	{
		$shell_cmd = 'rm -r ' . $shard_loc;
		TimeStamp('Removing old data with this command: ' . $shell_cmd);
		shell_exec($shell_cmd);
	}
	
}

function TimeStamp($string)
{
	$now = new DateTime();
	$time = $now->format('Y-m-d' . ' --> ' . 'H:i:s' . ' <-- ');
	$string = $time . $string . "\n";
	echo $string;
}

function AcquireModIds($root_server_files)
{
	TimeStamp('Checking to see if mods are installed');
	$LinuxServerLoc = 'ShooterGame' . DIRECTORY_SEPARATOR . 'Saved' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'LinuxServer';
	$GameUserSettings = file_get_contents($root_server_files . DIRECTORY_SEPARATOR . $LinuxServerLoc . DIRECTORY_SEPARATOR . 'GameUserSettings.ini');
	$GameUserSettingsArray = explode("\n",$GameUserSettings);
	foreach($GameUserSettingsArray as $line){
		if(strpos($line,'ActiveMods=') !== FALSE){
			$mod_line = $line;
		}
	}
	if(isset($mod_line)){
		TimeStamp('Mods found, acquiring list for update');
		$line_array = explode('=',$mod_line);
		$mod_string = $line_array[1];
		$mod_ids = explode(' ',$mod_string);
		return $mod_ids;
	} else {
		return null;
	}
}

function UpdateArkServerMods($mod_ids, $steam_cmd_path, $root_server_files)
{
	$base_cmd = $steam_cmd_path . ' +login anonymous';
	$mod_id_string = '';
	foreach($mod_ids as $mod_id){
		$mod_id_string = ' +workshop_download_item 346110 ' . $mod_id . $mod_id_string;
	}
	$shell_cmd = $base_cmd . $mod_id_string . ' +quit';
	TimeStamp('Updating the Ark Server Mods');
	shell_exec($shell_cmd);
	MoveModsToArkLocation($mod_ids, $steam_cmd_path, $root_server_files);
}

function MoveModsToArkLocation($mod_ids, $steam_cmd_path, $root_server_files)
{
	TimeStamp('Moving Updated Mods to Ark Server Path');
	$home_folder = str_replace('steamcmd/steamcmd.sh','',$steam_cmd_path);
	$mods_folder = $root_server_files . DIRECTORY_SEPARATOR . 'ShooterGame' . DIRECTORY_SEPARATOR . 'Content' . DIRECTORY_SEPARATOR . 'Mods' . DIRECTORY_SEPARATOR;
	$steam_mod_folder = $home_folder . 'Steam' . DIRECTORY_SEPARATOR . 'steamapps' . DIRECTORY_SEPARATOR . 'workshop' . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . '346110';
	foreach($mod_ids as $mod_id){
		$dir = $steam_mod_folder . DIRECTORY_SEPARATOR . $mod_id . DIRECTORY_SEPARATOR;
		$shell_cmd = 'cp -r ' . $dir . ' ' . $mods_folder;
		shell_exec($shell_cmd);
	}	
}
?>
