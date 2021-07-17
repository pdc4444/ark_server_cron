<?php
// src/Service/ShardGeneratorService.php
namespace App\Service;
use Symfony\Component\ErrorHandler\Errorhandler;
use App\Service\ShardService;
use App\Service\HelperService;
ErrorHandler::register();

/**
 * The ShardGeneratorService is responsible for recursively creating symbolic links to the root ark server files. Each shard is it's own hosted map and the only unique files are the contents of ShooterGame\Saved.
 * It works in conjuction with ShardService to help the user define specific server settings in a Q and A format via UserConsoleController within the command line interface.
 */
class ShardGeneratorService extends ShardService
{
	CONST USER_CFG_STRING = 'Please define the value for this setting: ';
	CONST INVALID_VALUE = 'Invalid value detected, please insert a valid setting value.';

	private $generated_shard_name;            //The name of the shard selected by the generateNewShardName() function.
	public $generated_shard_location;         //The location of the shard set by the generateNewShardName() function.
	private $temp_directory_name;             //The name of the temporary directory where we are building all the shard files. Should be something like 'building_#'. This is set by createNewShardDirectory()
	private $temp_directory_location;         //The location of the temporary directory where we are building all the shard files. Also set by createNewShardDirectory()
	private $config_file_location_array = []; //An array to the paths of each configuration file required to run an ark server shard. Set by generateConfigFiles()
    
	public function __construct($construct_parent = TRUE)
	{
		if ($construct_parent) {
			parent::__construct();
		}
    }

	/**
	 * This function creates a new shard directory and makes it ready for additional configuration via the configureBuild() function
	 */
    public function build()
    {
		$this->generateNewShardName();
		$this->createNewShardDirectory();
		$this->recursivelyCreateSymLinks($this->root_server_files, $this->temp_directory_location);
		$this->createRemainingDirectories();
	}

	public function rebuild($root_server_files, $shard_location)
	{
		$this->createNewDirectory($shard_location);
		$this->recursivelyCreateSymLinks($root_server_files, $shard_location);
	}
	
	/**
	 * This function creates the empty configuration files and interprets the contents of ark_server_cron/src/ini for each configuration file in order
	 * to ask the user questions about how to set up the new shard that they are generating.
	 * 
	 * @param Object $console_controller - An instance of the UserConsoleController class which is expected to be passed from ShardGeneratorCommand
	 */
	public function configureBuild($console_controller)
	{
		$this->generateConfigFiles();
		$file_counter = 1;
		$total_files = count(SELF::SHARD_CFG_FILES);
		foreach (SELF::SHARD_CFG_FILES as $cfg_file) {
			$configuration_script = str_replace('.ini', '_service.ini', $cfg_file);
			$script_location = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'ini' . DIRECTORY_SEPARATOR . $configuration_script;
			$file_setting_array = parse_ini_file($script_location, TRUE);
			$console_controller->cli_header = 'Configuring ' . $cfg_file . ' ' . $file_counter . '/' . $total_files;
			$this->determineConfiguration($console_controller, $file_setting_array, $cfg_file);
			$file_counter++;
		}
	}

	/**
	 * This function is responsible for interacting with the user and writing the user's selected settings to the shard configuration files.
	 * User interaction is largely handled by UserConsoleController with different question types having their own logic paths for user interaction.
	 * 
	 * @param Object $console_controller - An instance of the UserConsoleController class which is expected to be passed from ShardGeneratorCommand
	 * @param Array $settings_array - An array of parsed ini values expected to be passed from the configureBuild function
	 * @param String $cfg_file - The name of the configuration file that we are processing.
	 */
	private function determineConfiguration($console_controller, $settings_array, $cfg_file)
	{
		$location = $this->config_file_location_array[$cfg_file];
		foreach ($settings_array as $header => $setting_array) {
			$this->writeConfiguration('[' . $header . ']', $location);
			$console_controller->question_count = 0;
			$console_controller->total_questions = count($setting_array);
			foreach ($setting_array as $setting_name => $settings_details) {
				$help = $settings_details['Help'];
				$default = $settings_details['Default'];
				$type = $settings_details['Value_Type'];

				if ($type === 'server_mandatory') {
					$console_controller->question_count++;
					$line = $setting_name . '=' . $default;
					$this->writeConfiguration($line, $location);
					continue;
				}

				$console_controller->reset();
				$console_controller->question = SELF::USER_CFG_STRING . $setting_name;
				$console_controller->help_text = $help . $console_controller::LINE_BREAK . 'Default Value: ' . $default;

				if ($type === 't/f') {
					$console_controller->options_list = ['?' => ['Default', 'True', 'False']];
				}

				$value_verified = FALSE;
				while ($value_verified === FALSE) {
					$answer = $console_controller->askQuestion();
					$value_verified = $this->verifyValue($setting_name, $answer);
					if ($value_verified === FALSE && strpos($console_controller->help_text, SELF::INVALID_VALUE) === FALSE) {
						$console_controller->help_text = $console_controller->help_text . $console_controller::LINE_BREAK . SELF::INVALID_VALUE;
					}
				}

				is_array($answer) ? $choice = $answer['?'] : $choice = $answer;
				if ($choice == 'Default' || $choice == '') {
					$choice = $default;
				}
				$line = $setting_name . '=' . $choice;
				$this->writeConfiguration($line, $location);
			}
		}
	}

	/**
	 * This function is responsible for verifying values chosen by the user during the determineConfiguration function.
	 * The format is a switch statement to allow for future expandability should any additional values need to be verified.
	 * 
	 * @param String $setting_name - The name of the setting that we are verifying the value of
	 * @param String $answer - The user input that we are verifying the value of
	 * @return Boolean TRUE is returned if the value has been verified, else FALSE and the user must enter a new value within the determineConfiguration() function
	 */
	private function verifyValue($setting_name, $answer)
	{
		switch ($setting_name) {
			case 'SessionName':
				foreach ($this->shards as $shard_name => $shard_data) {
					//The SessionName must be unique, this is the logic that enforces it
					if (strpos($shard_name, 'shard_') !== FALSE && $shard_data[HelperService::GAME_CONFIG]['SessionSettings']['SessionName'] == $answer) {
						return FALSE;
					}
				}
				break;
		}
		return TRUE;
	}

	/**
	 * This function literally just wraps the file_put_contents function within Symfony's ErrorHandler so that we may throw an error on any potential problems.
	 */
	private function writeConfiguration($line, $file_location)
	{
		ErrorHandler::call(static function () use ($line, $file_location){
			file_put_contents($file_location, $line . "\n", FILE_APPEND);
		});
	}

	/**
	 * Once the shard directory has been built and the configuration files hydrated this function renames the shard folder from 'building_#' to 'shard_#'
	 * The rename function is wrapped in Symfony's ErrorHandler so that we may throw an error for any potential problem.
	 */
	public function finalizeBuild()
	{
		$temp_path = $this->temp_directory_location;
		$final_path = $this->generated_shard_location;
		ErrorHandler::call(static function () use ($temp_path, $final_path){
			rename($temp_path, $final_path);
		});
	}

	/**
	 * This function looks at the server shard directory and looks to see if any shards exist. We start at 1 and increment until a directory is not found.
	 * Once a directory is not found the selected shard name is stored in a class variable, along with the location of the folder.
	 */
    private function generateNewShardName()
	{
		$counter = 1;
        while (TRUE) {
			$shard_name = 'shard_' . $counter;
			$temp_name = 'building_' . $counter;
			$possible_shard_folder = $this->server_shard_directory . $shard_name;
			$possible_temp_folder = $this->server_shard_directory . $temp_name;
            if (file_exists($possible_shard_folder) === FALSE && file_exists($possible_temp_folder) === FALSE) {
				$this->generated_shard_name = $shard_name;
				$this->generated_shard_location = $this->server_shard_directory . $shard_name;
                return;
            }
            $counter++;
        }
	}

	/**
	 * This function takes the generated shard name and replaces shard with building to designate that this shard is not yet generated.
	 * A new folder is created with called something like 'building_#' using the createNewDirectory() function.
	 */
	private function createNewShardDirectory()
	{
		$this->temp_directory_name = str_replace('shard_', 'building_', $this->generated_shard_name);
		$this->temp_directory_location = $this->server_shard_directory . $this->temp_directory_name;
		$this->createNewDirectory($this->temp_directory_location);
	}
	
	/**
	 * This function creates the remaining directories that are not shared via symbolic links.
	 * All the directories that live in ShooterGame/Saved/* need to be created manually so that we can store the expected config files within them.
	 * Directory creation is done through createNewDirectory() of course.
	 */
	private function createRemainingDirectories()
	{
		$saved = $this->temp_directory_location . DIRECTORY_SEPARATOR . 'ShooterGame' . DIRECTORY_SEPARATOR . 'Saved';
		$config = $saved . DIRECTORY_SEPARATOR . 'Config';
		$linux_server = $config . DIRECTORY_SEPARATOR . 'LinuxServer';
		$directories = [$saved, $config, $linux_server];
		
		foreach ($directories as $dir) {
			if (Errorhandler::call('file_exists', $dir) === FALSE) {
				$this->createNewDirectory($dir);
			}
		}
	}
	
	/**
	 * This function looks at a target directory and creates an entire tree of symlinks to that directory in a new location.
	 * This is used to share the ark server files with each shard.
	 * 
	 * @param String $original_directory - The directory you want to create recursive symlinks to
	 * @param String $new_location - The directory you want the new tree of symbolic links to live.
	 */
    private function recursivelyCreateSymLinks($original_directory, $new_location)
	{
		$original_directory_contents = ErrorHandler::call('scandir', $original_directory);
		foreach ($original_directory_contents as $item) {
			if ($item != '.' && $item != '..') {
				$item_location = $original_directory . DIRECTORY_SEPARATOR . $item;
				if (is_dir($item_location) === FALSE && $item != 'ShooterGameServer') {
					$target = $new_location . DIRECTORY_SEPARATOR . $item;
					$real_file = $original_directory . DIRECTORY_SEPARATOR . $item;
					$this->createSymbolicLink($target, $real_file);
				}
				if (is_dir($item_location) === TRUE) {
					$target = $new_location . DIRECTORY_SEPARATOR . $item;
					$this->createNewDirectory($target);
					$this->recursivelyCreateSymLinks($item_location, $target);
				}
				if ($item == 'ShooterGameServer') {
					$dest_full_path = $new_location . DIRECTORY_SEPARATOR . $item;
					$this->copyFile($item_location, $dest_full_path);
				}
			}
		}
	}
	
	/**
	 * This function is a call to symlink, but wrapped in Symfony's ErrorHandler::call so that we can report on potential issues interacting with the filesystem.
	 */
	private function createSymbolicLink($target, $real_file)
	{
		ErrorHandler::call(static function () use ($target, $real_file){
			symlink($real_file, $target);
		});
	}
	
	/**
	 * This function wraps the mkdir function in ErrorHandler::call. It's done this way to easily observe and report on potential issues using mkdir.
	 */
	private function createNewDirectory($directory_location)
	{
		ErrorHandler::call(static function () use ($directory_location){
			mkdir($directory_location, 0755, FALSE);
		});
	}

	/**
	 * Like the createNewDirectory() function the copy() and chmod() functions are wrapped in Symfony's ErrorHandler::call(), useful to report on
	 * potential problems that may arise from interacting with the file system.
	 */
	private function copyFile($tgt_full_path, $dest_full_path)
	{
		ErrorHandler::call(static function () use ($tgt_full_path, $dest_full_path){
			copy($tgt_full_path, $dest_full_path);
			chmod($dest_full_path, 0755);
		});
	}

	/**
	 * This function creates empty configuration files ready for hydration.
	 * The created configuration files are stored in each shards folder such as /shard_#/ShooterGame/Saved/Config/LinuxServer/
	 */
	private function generateConfigFiles()
	{	
		foreach (SELF::SHARD_CFG_FILES as $cfg_file) {
			$path = $this->temp_directory_location . SELF::SHARD_CFG_PATH . DIRECTORY_SEPARATOR . $cfg_file;
			$this->config_file_location_array[$cfg_file] = $path;
			file_put_contents($path, '');
		}
	}
    
}