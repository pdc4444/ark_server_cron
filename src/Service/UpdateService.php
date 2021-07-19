<?php
// src/Service/UpdateService.php
namespace App\Service;
use App\Service\ShardService;
use App\Service\HelperService;
use App\Service\ShardGeneratorService;
use Symfony\Component\ErrorHandler\Errorhandler;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
ErrorHandler::register();

class UpdateService extends ShardService
{

    //This will make it update in the background, I'll need to make a sub process that watches this update
    CONST STEAM_CMD_ARRAY = ['STEAM_CMD_BINARY', '+login anonymous', '+force_install_dir ROOT_SERVER_FILE_DIR', '+app_update 376030', 'validate +exit'];
    CONST SUCCESS_MSG = "Success! App '376030' fully installed.";
    CONST UPDATER_RUNNING = "Ark Server files are being updated. Please stand by.";

    public $update_status;
    public $user_console_controller;
    public $generated_update_command;

    public function __construct()
    {
        parent::__construct();
    }

    public function run()
    {
        $log = $this->updateRootServerFiles();
        $this->update_status = $this->verifySuccessMessage($log);
    }

    private function updateRootServerFiles()
    {
        $base_cmd_array = SELF::STEAM_CMD_ARRAY;
        foreach ($base_cmd_array as $key => $value) {
            if (strpos($value, 'STEAM_CMD_BINARY') !== FALSE) {
                $base_cmd_array[$key] = str_replace('STEAM_CMD_BINARY', $this->steam_cmd, $base_cmd_array[$key]);
            } else if (strpos($value, 'ROOT_SERVER_FILE_DIR') !== FALSE) {
                $base_cmd_array[$key] = str_replace('ROOT_SERVER_FILE_DIR', $this->root_server_files, $base_cmd_array[$key]);
            }
        }
        $this->generated_update_command = implode(' ', $base_cmd_array);
        return HelperService::shell_cmd($base_cmd_array, $this->user_console_controller, SELF::UPDATER_RUNNING);
    }

    private function verifySuccessMessage($log)
    {
        $log_array = explode("\n", $log);
        foreach ($log_array as $line) {
            if (!empty($line) && strpos(strtolower(SELF::SUCCESS_MSG), strtolower($line)) !== FALSE) {
                return TRUE;
            }
        }
        return FALSE;
    }

    public function updateEachShard()
    {
        $filesystem = new Filesystem();
        $shard_generator = new ShardGeneratorService(FALSE);
        $shards = [];
        foreach ($this->shards as $key => $shard_data) {
            if ($key != 'installed' && $shard_data[HelperService::SHARD_CONFIG]['ShardSettings']['enabled'] == '1') {
                $shards[] = $shard_data['Path'];
            }
        }
        foreach ($shards as $shard) {
            $shard_path = '/' . trim($shard, '/');
            $shard_path_parts = explode('/', $shard_path);
            $shard_name = current(array_reverse($shard_path_parts));
            $saved_data = $shard_path . DIRECTORY_SEPARATOR . 'ShooterGame' . DIRECTORY_SEPARATOR . 'Saved';
            $tmp_location = DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $shard_name;

            //Remove any duplicate directories if they exist
            if (file_exists($tmp_location)) {
                $filesystem->remove($tmp_location);
            }

            //Move Saved data to a safe location
            $filesystem->rename($saved_data, $tmp_location);

            //Remove the old shard folder
            $filesystem->remove($shard_path);

            //Rebuild the symlinks for the shard folder
            $shard_generator->rebuild($this->root_server_files, $shard_path);

            //Moved Saved data back to the proper location
            $filesystem->rename($tmp_location, $saved_data);

            //Fix the folder permissions
            $filesystem->chmod($saved_data, 0755, 0000, true);
        }
    }
}