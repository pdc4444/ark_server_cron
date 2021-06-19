<?php
// src/Service/UpdateService.php
namespace App\Service;
use App\Service\ShardService;
use App\Service\HelperService;
use Symfony\Component\ErrorHandler\Errorhandler;

class UpdateService extends ShardService
{

    //This will make it update in the background, I'll need to make a sub process that watches this update
    // CONST STEAM_CMD_BASE_STRING = 'STEAM_CMD_BINARY +login anonymous +force_install_dir ROOT_SERVER_FILE_DIR +app_update 376030 validate +exit > /dev/null 2>&1 & ';
    CONST STEAM_CMD_BASE_STRING = 'STEAM_CMD_BINARY +login anonymous +force_install_dir ROOT_SERVER_FILE_DIR +app_update 376030 validate +exit';
    CONST SUCCESS_MSG = "Success! App '376030' fully installed.";

    public $update_status;
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
        $cmd = str_replace('STEAM_CMD_BINARY', $this->steam_cmd, SELF::STEAM_CMD_BASE_STRING);
        $cmd = str_replace('ROOT_SERVER_FILE_DIR', $this->root_server_files, $cmd);
        $this->generated_update_command = $cmd;
        return Errorhandler::call('shell_exec', $cmd);
    }

    private function verifySuccessMessage($log)
    {
        $log_array = explode("\n", $log);
        foreach ($log_array as $line) {
            if (strpos(strtolower(SELF::SUCCESS_MSG), strtolower($line)) !== FALSE) {
                return TRUE;
            }
        }
        return FALSE;
    }


}