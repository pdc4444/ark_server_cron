<?php
// src/Service/HelperService.php
namespace App\Service;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class HelperService
{
    CONST SHARD_CONFIG = 'shard_config.ini';
    CONST GAME_CONFIG = 'GameUserSettings.ini';
    CONST GAME_INI = 'Game.ini';
    CONST CRON_CONFIG = 'ark_server_cron.cfg';

    public function extractShardNames($shard_array)
    {   
        $shards = [];
        foreach ($shard_array as $shard) {
            $shards[] = $shard;
        }
        return $shards;
    }

    public function translateAnswer($user_choice, $shard_array)
    {
        if ($user_choice == 'All') {
            return $user_choice;
        }
        foreach ($shard_array as $shard_number => $session_name) {
            if ($user_choice == $session_name) {
                return $shard_number;
            }
        }
    }

    public function summarizeShardInfo($raw_shard_data)
    {
        $important_shard_data = [];
        foreach ($raw_shard_data as $shard_name => $shard_data) {
            if($shard_name != 'installed'){
                $important_shard_data[$shard_name]['Shard Number'] = $shard_name;
                $important_shard_data[$shard_name]['Query Port'] = $shard_data[SELF::SHARD_CONFIG]['ShardSettings']['QueryPort'];
                $important_shard_data[$shard_name]['Game Port'] = $shard_data[SELF::SHARD_CONFIG]['ShardSettings']['GamePort'];
                $important_shard_data[$shard_name]['RCON Port'] = $shard_data[SELF::SHARD_CONFIG]['ShardSettings']['RCONPort'];
                $important_shard_data[$shard_name]['Map'] = $shard_data[SELF::SHARD_CONFIG]['ShardSettings']['Server_Map'];
                $important_shard_data[$shard_name]['Session Name'] = $shard_data[SELF::GAME_CONFIG]['SessionSettings']['SessionName'];
                $important_shard_data[$shard_name]['Session Password'] = $shard_data[SELF::GAME_CONFIG]['ServerSettings']['ServerPassword'];
                $important_shard_data[$shard_name]['Max Players'] = $shard_data[SELF::SHARD_CONFIG]['ShardSettings']['MaxPlayers'];
                $important_shard_data[$shard_name]['Running'] = $shard_data['Status']['Running'];
                $important_shard_data[$shard_name]['Process Id'] = $shard_data['Status']['Process Id'];
                if (isset($shard_data[SELF::GAME_CONFIG]['ServerSettings']['activemods'])) {
                    $important_shard_data[$shard_name]['Mod Ids'] = str_replace(' ', '', (str_replace(',', "\n", $shard_data[SELF::GAME_CONFIG]['ServerSettings']['activemods'])));
                }
            }
        }
        return $important_shard_data;
    }

    /**
     * 
     */
    public function shell_cmd($cmd_array) 
    {
        $process = new Process($cmd_array);
        $process->setTimeout(7200);    //2 hour total timeout
        $process->setIdleTimeout(120); //2 minute idle timeout
        $process->start();

        foreach ($process as $type => $data) {
            if ($process::OUT === $type) {
                echo $data;
            } else { // $process::ERR === $type
                echo $data;
            }
        }
    }
}