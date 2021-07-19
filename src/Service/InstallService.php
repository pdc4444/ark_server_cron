<?php
// src/Service/ShardGeneratorService.php
namespace App\Service;
use Symfony\Component\ErrorHandler\Errorhandler;
use App\Service\ShardService;
use App\Service\HelperService;
ErrorHandler::register();

class InstallService extends ShardService
{
    CONST STEAM_CMD_DEFAULT = '/usr/games/steamcmd';
    CONST CHECK_STEAM_CMD = 'which steamcmd';
    CONST CONFIG_FILE_VARS = [
        'A' => 'ark_server_files', 
        'B' => 'ark_server_shards',
        'C' => 'ark_server_backup',
        'D' => 'ark_server_cluster',
        'E' => 'steam_cmd',
        'F' => 'port_range'
    ];

    public $general_config = [];

	public function __construct()
	{
        parent::__construct();
        $this->checkSteamCmd();
    }

    public function beginInstallation()
    {
        $this->materializeTheDirectories();
        $this->writeConfig();
        $updater = new UpdateService();
        $updater->run();
    }

    private function checkSteamCmd()
    {   
        //Check the default install location first
        if (file_exists(SELF::STEAM_CMD_DEFAULT) !== FALSE) {
            $result = SELF::STEAM_CMD_DEFAULT;
        }
        //Look for steamcmd using which command (must be in $PATH ENV variables)
        isset($result) ? TRUE : $result = ErrorHandler::call('exec', SELF::CHECK_STEAM_CMD);
        if (empty($result)) {
            throw new \RuntimeException('steamcmd was not found. Make sure it is installed and referenced in your bash $HOME.');
        } else {
            $this->general_config['E'] = $result;
        }
    }

    private function writeConfig()
    {
        $file_contents = "";
        foreach (SELF::CONFIG_FILE_VARS as $key => $variable) {
            $file_contents = $file_contents . $variable . "=" . $this->general_config[$key] . "\n";
        }
        $file_contents = trim($file_contents);
        file_put_contents($this->root_dir . HelperService::CRON_CONFIG, $file_contents);
    }

    private function materializeTheDirectories()
    {
        foreach (SELF::CONFIG_FILE_VARS as $key => $variable) {
            if (!file_exists($this->general_config[$key])) {
                mkdir($this->general_config[$key], 0775, TRUE);
            }
        }
    }
}