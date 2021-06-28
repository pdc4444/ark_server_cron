<?php
// src/Service/UpdateService.php
namespace App\Service;
use App\Service\ShardService;
use App\Service\HelperService;
use Symfony\Component\ErrorHandler\Errorhandler;

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


}