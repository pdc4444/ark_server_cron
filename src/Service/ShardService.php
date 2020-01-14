<?php
// src/Service/ShardService.php
namespace App\Service;
use Symfony\Component\ErrorHandler\Errorhandler;
ErrorHandler::register();

/**
 * This service loads the configuration file settings into memory and looks for any installed shards in which to compile shard details for.
 * Any installed shards will have configuration files that are loaded into the $shards public variable for use with other Ark services.
 * All class variables are publically accessible so any Ark services that need to know the state of things can easily access this information.
 */
class ShardService
{
    CONST SHARD_CFG_PATH = DIRECTORY_SEPARATOR . 'ShooterGame' . DIRECTORY_SEPARATOR . 'Saved' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'LinuxServer';
    CONST SHARD_CFG_FILES = ['shard_config.ini', 'GameUserSettings.ini', 'Game.ini'];

	public $config_file;                 //The path to the configuration file responsible for determining where the root server files, backup path, and shard directory are located.
	public $backup_path;                 //The path to where backups are stored
	public $root_server_files;           //The path to where the root server files are stored
    public $server_shard_directory;      //The path to where the server shard directories are stored
	public $shards;                      //The path to where each individual shard is stored
	
    /**
     * Start the service by checking to see if the configuration file is present.
     * If the config file is missing throw an exception, else load the file and
     * compile the shard info.
     */
	public function __construct()
	{
        $root_dir = str_replace('src/Service', '', __DIR__);
        $this->config_file = $root_dir . 'ark_server_cron.cfg';
		if (file_exists($this->config_file)) {
            $this->loadConfigFile();
            $this->compileShardInfo();
		} else {
            throw new \RuntimeException('ark_server_cron.cfg not found! Have you run the installer yet?');
        }
	}
    
    /**
     * Attempt to load the data from the ark_server_cron.cfg file and
     * begin to populate the ShardService variables via assignConfigFileVariables()
     */
	private function loadConfigFile()
	{
        $contents = ErrorHandler::call('file_get_contents', $this->config_file);
		$content_array = explode("\n", $contents);
		foreach ($content_array as $line) {
			$line_array = explode('=', $line);
			if (!empty($line_array[0])) {
				$this->assignConfigFileVariables($line_array[0], $line_array[1]);
			}
		}
	}
    
    /**
     * Determine the config file name and assign the value to it's proper
     * publicly accessible class variable.
     * 
     * @param string $setting - The name of the setting
     * @param string $value - The value of the setting
     */
	private function assignConfigFileVariables($setting, $value)
	{
		switch ($setting) {
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

    /**
     * Scan the contents of the shard directory and find any installed shards.
     * If shards are found load the configuration of each shard into an array
     * and assign that value to the proper publicly accessible class variable.
     */
    private function compileShardInfo()
    {
        //Get list of shards
        $shard_info = [];
        $dir_contents = ErrorHandler::call('scandir', $this->server_shard_directory);
        foreach ($dir_contents as $key => $name) {
            if (strpos($name, 'shard') !== FALSE) {
                $shard_info['installed'][] = $name;
                $shard_info[$name]['Status'] = $this->determineIfRunning($name);
                $shard_info[$name]['Path'] = $this->server_shard_directory . $name . DIRECTORY_SEPARATOR;
                foreach (SELF::SHARD_CFG_FILES as $config_file) {
                    $shard_info[$name][$config_file] = $this->extractShardInfo($name, $config_file);
                }
            }
        }
        $this->shards = $shard_info;
    }

    /**
     * Take the exact file path and acquire it's contents. 
     * Load the file contents into a array and return it.
     * 
     * @param string $file - The exact path to the file
     * @return array $return_values - A multidimensional array that contains configuration settings
     */
    private function loadIniFile($file)
    {
        $return_values = [];
        $contents = ErrorHandler::call('file_get_contents', $file);
        $content_array = explode("\n",$contents);
		foreach ($content_array as $line) {
            $line_array = explode('=',$line);
			if (isset($line_array[1])) {
				$return_values[$line_array[0]] = $line_array[1];
			}
        }
        return $return_values;
    }

    /**
     * Takes the shard number and looks to see if the shootergame binary is currently running
     * along with the shard name. If the shard is currently running, the process id is extracted
     * from the raw output and returned along with a Running status of 'Yes'.
     * 
     * @param string $shard - The shard number such as 'shard_1'
     * @return array - An array that contains the running status and current pid if applicable.
     */
    private function determineIfRunning($shard)
    {
        $pid = '';
        $running = 'No';
        $shell_cmd = 'ps ax | grep -i shootergame | grep -vi grep | grep ' . $shard;
        $results = ErrorHandler::call('shell_exec', $shell_cmd);
        if ($results !== NULL) {
            $pid = $this->isolatePID($results);
            $running = 'Yes';
        }
        return [
            'Running' => $running,
            'Process Id' => $pid
        ];
    }
    
    /**
     * This function stitches together the file path of the passed $file for the passed $shard.
     * We then load the file contents via the loadIniFile function and return it's $contents.
     * 
     * @param string $shard - The shard number such as 'shard_1'
     * @param string $file - The name of the file we are to extract shard info from
     * @return array $contents - A multidimensional array that contains configuration settings
     */
    private function extractShardInfo($shard, $file)
    {
        $cfg_file = $this->server_shard_directory . $shard . SELF::SHARD_CFG_PATH . DIRECTORY_SEPARATOR . $file;
        $contents = $this->loadIniFile($cfg_file);
        return $contents;
    }

    /**
     * This function extracts the process id from a passed grep result via the preg_match function.
     * 
     * @param string $string - The string acquired from grep that contains running process info
     * @return string $PID - The string of the extracted process id.
     */
	private function isolatePID($string)
	{
        preg_match('/(\s\d+\s\b|\d+\s\b|^\d+\s|^\s\d+|^.+\s\d+\s)/', $string, $matches);
		$PID = trim($matches[0]);
		return $PID;
    }
    
}