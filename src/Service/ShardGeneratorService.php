<?php
// src/Service/ShardGeneratorService.php
namespace App\Service;
use Symfony\Component\ErrorHandler\Errorhandler;
use App\Service\ShardService;
use App\Service\HelperService;
ErrorHandler::register();

class ShardGeneratorService extends ShardService
{
	CONST USER_CFG_STRING = 'Please define the value for this setting: ';
	CONST INVALID_VALUE = 'Invalid value detected, please insert a valid setting value.';

	private $generated_shard_name;
	private $generated_shard_location;
	private $temp_directory_name;
	private $temp_directory_location;
	private $config_file_location_array = [];
    
	public function __construct()
	{
        parent::__construct();
    }

    public function build()
    {
		$this->generateNewShardName();
		$this->createNewShardDirectory();
		$this->recursivelyCreateSymLinks($this->root_server_files, $this->temp_directory_location);
		$this->createRemainingDirectories();
	}
	
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

	private function writeConfiguration($line, $file_location)
	{
		ErrorHandler::call(static function () use ($line, $file_location){
			file_put_contents($file_location, $line . "\n", FILE_APPEND);
		});
	}

	public function finalizeBuild()
	{
		$temp_path = $this->temp_directory_location;
		$final_path = $this->generated_shard_location;
		ErrorHandler::call(static function () use ($temp_path, $final_path){
			rename($temp_path, $final_path);
		});
	}

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

	private function createNewShardDirectory()
	{
		$this->temp_directory_name = str_replace('shard_', 'building_', $this->generated_shard_name);
		$this->temp_directory_location = $this->server_shard_directory . $this->temp_directory_name;
		$this->createNewDirectory($this->temp_directory_location);
	}
	
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
	
	private function createSymbolicLink($target, $real_file)
	{
		ErrorHandler::call(static function () use ($target, $real_file){
			symlink($real_file, $target);
		});
	}
	
	private function createNewDirectory($directory_location)
	{
		ErrorHandler::call(static function () use ($directory_location){
			mkdir($directory_location, 0755, FALSE);
		});
	}

	private function copyFile($tgt_full_path, $dest_full_path)
	{
		ErrorHandler::call(static function () use ($tgt_full_path, $dest_full_path){
			copy($tgt_full_path, $dest_full_path);
			chmod($dest_full_path, 0755);
		});
	}

	private function generateConfigFiles()
	{	
		foreach (SELF::SHARD_CFG_FILES as $cfg_file) {
			$path = $this->temp_directory_location . SELF::SHARD_CFG_PATH . DIRECTORY_SEPARATOR . $cfg_file;
			$this->config_file_location_array[$cfg_file] = $path;
			file_put_contents($path, '');
		}
	}
    
}