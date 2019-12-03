<?php

/*

When the ark server is running the game will automatically create a backup save every 2.5 hours for the MapName.ark file in the SavedArks folder. This is basically like an autosave like in a single player game.
If something where to happen like a game breaking bug where someone died, it would be handy to be able to restore the game state from before this event occurred. Unfortunately, the ARK devs never coded in an option
to allow the user to set the backup interval save rate. All they provided is a way to make the current primary save occur more frequently, but this does not affect actual game save backups.

The script below will create a backup save directory for each shard. On startup the script gets the current save checksum as well as appropriate file and directory information. Every 3 minutes the script will
check to see if the file checksum has changed, if so a backup of the file will be saved to the save directory. A maximum save snapshot constant is user setable at the top of this script (default is 20). This script
will automatically purge older backups.

TODO: Make the restore process scripted and user friendly (currently you have to manually copy the file over)
TODO: Tie this script in with ark_server_cron.php instead of having it free floating!

*/

define('BACKUP_FILE_LIMIT', 20);

//Check to see if the script is already running, if so exit...
$processes = shell_exec("ps -ef | grep -i 'ark_cron_autosaver.php' | grep -v 'grep'");
$process_array = explode("\n", trim($processes, "\n"));
if (count($process_array) > 1) {
    exit("More than one script is active, exiting! \n");
}

//Get the ark cron configuration settings:
$config_obj = new LoadConfigFiles();
$shard_dir = $config_obj->server_shard_directory;

$shard_directories = scandir($shard_dir);
$saved_ark_dir = '/ShooterGame/Saved/SavedArks';
$shard_information = [];

foreach ($shard_directories as $dir) {
    if ($dir != '.' && $dir != '..') {
        $shard_information[$dir]['location'] = $shard_dir . $dir;
        $shard_information[$dir]['map'] = getShardMap($shard_information[$dir]['location']);
        $shard_information[$dir]['saved_file'] = $shard_dir . $dir . $saved_ark_dir . DIRECTORY_SEPARATOR . $shard_information[$dir]['map'] . '.ark';
        $shard_information[$dir]['autosaver_dir'] = getAutoSaverDirectory($shard_dir . $dir . $saved_ark_dir);
        $shard_information[$dir]['saved_checksum'] = sha1_file($shard_information[$dir]['saved_file'], false);
    }
}

while (true) {
    $time = date('Y-m-d__H_i_s', strtotime('now'));
    foreach ($shard_information as $shard => $shard_specifics) {
        $current_checksum = sha1_file($shard_specifics['saved_file'], false);
        if ($current_checksum != $shard_specifics['saved_checksum']) {
            backupCurrentSave($shard_specifics['saved_file'], $shard_specifics['autosaver_dir'], $shard_specifics['map'], $time);
            rotateOldSaves($shard_specifics['autosaver_dir']);
            $shard_information[$shard]['saved_checksum'] = $current_checksum;
        }
    }
    sleep(180);
}

function rotateOldSaves($backup_dir)
{
    $sorted_files = sortSaveFiles($backup_dir);
    while (count($sorted_files) > BACKUP_FILE_LIMIT) {
        $file_to_delete = current($sorted_files);
        unlink($backup_dir . DIRECTORY_SEPARATOR . $file_to_delete);
        $sorted_files = sortSaveFiles($backup_dir);
    }
}

function sortSaveFiles($backup_dir)
{
    $file_array = [];
    $directory_contents = scandir($backup_dir);
    foreach ($directory_contents as $item) {
        if ($item != '.' && $item != '..' && strpos($item, '.ark_') !== FALSE) {
            $item_array = explode('.ark_', $item);
            $item_datetime = $item_array[1];
            $item_datetime_parts = explode('__', $item_datetime);
            $item_date = $item_datetime_parts[0];
            $item_time = str_replace('_', ':', $item_datetime_parts[1]);
            $date_time = $item_date . ' ' . $item_time;
            $file_array[$item]['time'] = $date_time;
            $file_array[$item]['file'] = $item;
        }
    }

    usort($file_array, function($a, $b) {
        $ad = new DateTime($a['time']);
        $bd = new DateTime($b['time']);
        
        if ($ad == $bd) {
            return 0;
        }
        
        return $ad < $bd ? -1 : 1;
    });
    return array_column($file_array, 'file');
}

function backupCurrentSave($file, $backup_dir, $map_name, $time)
{
    copy($file, $backup_dir . DIRECTORY_SEPARATOR . $map_name . '.ark_' . $time);
}

function getAutoSaverDirectory($shard_save_dir)
{
    $directory_contents = scandir($shard_save_dir);
    if (in_array('autosaver_dir', $directory_contents) === FALSE) {
        mkdir($shard_save_dir . DIRECTORY_SEPARATOR . 'autosaver_dir', 0777, TRUE);
    }
    return $shard_save_dir . DIRECTORY_SEPARATOR . 'autosaver_dir';
}

function getShardMap($shard_location)
{
    $shard_config_file = $shard_location . DIRECTORY_SEPARATOR . 'ShooterGame/Saved/Config/LinuxServer/shard_config.ini';
    $file_contents = file_get_contents($shard_config_file);
    $file_array = explode("\n", $file_contents);
    foreach ($file_array as $file_line) {
        $file_line_array = explode('=', $file_line);
        if ($file_line_array[0] == 'Server_Map') {
            return $file_line_array[1];
        }
    }
}

Class LoadConfigFiles
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
			exit('Server not installed, please run ark_server_cron.php first. Exiting...');
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

?>
