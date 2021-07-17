<?php
// src/Service/ShardImportService.php
namespace App\Service;
use Symfony\Component\ErrorHandler\Errorhandler;
use App\Service\ShardService;
use App\Service\HelperService;
use App\Service\ShardGeneratorService;
use App\Service\RestoreService;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
ErrorHandler::register();

class ShardImportService extends ShardService
{
	CONST TEMP_PATH = DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;
	
	public function __construct()
	{
		parent::__construct();
	}
	
	public function run($file_to_import)
	{
		if (file_exists($file_to_import) && strpos($file_to_import, '.zip') !== FALSE) {
			//Unpack the zip and get the Saved directory
			$extracted_folder = SELF::TEMP_PATH . 'Saved';
			file_exists($extracted_folder) ? HelperService::delTree($extracted_folder) : FALSE;
			HelperService::unzip($file_to_import, SELF::TEMP_PATH);
			RestoreService::removeComment($extracted_folder);

			//Create a new shard directory using the shard generator service
			$shard_generator = new ShardGeneratorService();
			$shard_generator->build();
			$shard_generator->finalizeBuild();

			//perform a check to see if the name we are restoring matches one of the known installed shards increment name if needed
			array_key_exists('installed', $this->shards) ? $shard_names = array_values($this->shards['installed']) : $shard_names = [];
			$game_config_loc = $extracted_folder . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'LinuxServer' . DIRECTORY_SEPARATOR . HelperService::GAME_CONFIG;
			$extracted_shard_name = $this->extractShardName($game_config_loc);
			$new_shard_name = $this->shardNameChecker($extracted_shard_name, $shard_names);
			($new_shard_name == $extracted_shard_name) ? TRUE : $this->writeNewShardName($game_config_loc, $new_shard_name);

			//move the saved directory to the new proper location
			$new_shard_saved_location = $shard_generator->generated_shard_location . DIRECTORY_SEPARATOR . 'ShooterGame' . DIRECTORY_SEPARATOR . 'Saved';
			$filesystem = new Filesystem();
			file_exists($new_shard_saved_location) ? HelperService::delTree($new_shard_saved_location) : FALSE;
			$filesystem->rename($extracted_folder, $new_shard_saved_location);
			HelperService::recursiveChmod($new_shard_saved_location, 0755, 0755);

			return TRUE;
		}

		return FALSE;
	}

	private function shardNameChecker($chosen_name, $shard_names)
	{
		$counter = 1;
		$potential_name = $chosen_name;
		while (in_array($potential_name, $shard_names)) {
			$potential_name = $chosen_name . '_' . $counter;
			$counter++;
		}
		return $potential_name;
	}

	private function extractShardName($game_config_loc)
	{
		file_exists($game_config_loc) ? $raw_data = file_get_contents($game_config_loc) : FALSE;
		if (isset($raw_data)) {
			$config_file_array = explode("\n", $raw_data);
			foreach ($config_file_array as $key => $value) {
				if (strpos($value, 'SessionName') !== FALSE) {
					return trim(explode('=', $value)[1]);
				}
			}
		} else {
			throw new \RuntimeException('Unable to read the GameUserSetting.ini. We tried here: ' . $game_config_loc);
		}

	}

	private function writeNewShardName($game_config_loc, $new_shard_name)
	{
		$raw_data = file_get_contents($game_config_loc);
		$config_file_array = explode("\n", $raw_data);
		foreach ($config_file_array as $key => $value) {
			if (strpos($value, 'SessionName') !== FALSE) {
				$config_file_array[$key]  = 'SessionName=' . $new_shard_name;
			}
		}
		$config_file_string = implode("\n", $config_file_array);
		file_put_contents($game_config_loc, $config_file_string, 0755);
	}
   
}