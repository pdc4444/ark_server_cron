<?php
// src/Service/HelperService.php
namespace App\Service;

class HelperService
{
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
                $important_shard_data[$shard_name]['Query Port'] = $shard_data['shard_config.ini']['QueryPort'];
                $important_shard_data[$shard_name]['Game Port'] = $shard_data['shard_config.ini']['GamePort'];
                $important_shard_data[$shard_name]['RCON Port'] = $shard_data['shard_config.ini']['RCONPort'];
                $important_shard_data[$shard_name]['Map'] = $shard_data['shard_config.ini']['Server_Map'];
                $important_shard_data[$shard_name]['Session Name'] = $shard_data['GameUserSettings.ini']['SessionSettings']['SessionName'];
                $important_shard_data[$shard_name]['Session Password'] = $shard_data['GameUserSettings.ini']['ServerSettings']['ServerPassword'];
                $important_shard_data[$shard_name]['Max Players'] = $shard_data['shard_config.ini']['MaxPlayers'];
                $important_shard_data[$shard_name]['Running'] = $shard_data['Status']['Running'];
                $important_shard_data[$shard_name]['Process Id'] = $shard_data['Status']['Process Id'];
                $important_shard_data[$shard_name]['Mod Ids'] = str_replace(' ', '', (str_replace(',', "\n", $shard_data['GameUserSettings.ini']['ServerSettings']['activemods'])));
            }
        }
        return $important_shard_data;
    }
}