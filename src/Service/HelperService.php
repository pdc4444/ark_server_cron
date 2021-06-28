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
            if ($shard_name != 'installed') {
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
     * This function uses the Symfony Process component. You can pass an array that will initiate a shell command.
     * Additionally, this function draws the cli header and keeps an elapsed timer during the duration of the process being run.
     * We return the captured output, be it stdout or stderr.
     * 
     * @param array $cmd_array - ["echo", "this is a command"]
     * @param object $console_controller - An instance of the UserConsoleController object.
     * @param string $msg - A message that can be passed to customize the feedback given during process run.
     * @return string $output - A large string that contains any output captured from the running process.
     */
    public function shell_cmd($cmd_array, $console_controller, $msg = '')
    {
        $process = new Process($cmd_array);
        $process->setTimeout(7200);    //2 hour total timeout
        $process->setIdleTimeout(120); //2 minute idle timeout
        $start_time = new \DateTime();
        $process->start();
        $output = '';

        $console_controller->drawCliHeader();
        $first_print = TRUE;
        while ($process->isRunning()) {
            // waiting for process to finish
            if ($msg !== '') {
                $elapsed = HelperService::elapsedTime($start_time);
                $console_feedback = $msg . "\nTime Elapsed: " . $elapsed . "\n";
                if ($first_print === FALSE) {
                    echo "\e[2A" . $console_feedback;
                } else {
                    echo $console_feedback;
                }
                $output .= $process->getIncrementalOutput();
                $first_print = FALSE;
            }
        }
        return $output;
    }

    /**
     * This function takes a start time and returns the difference in H:I:S format
     * 
     * @param object $start_time - An instance of the DateTime object that represents the start time
     * @return string - The elapsed time in string format.
     */
    public function elapsedTime($start_time)
    {
        $now = new \DateTime();
        $elapsed = $now->diff($start_time);
        return $elapsed->format("%H:%I:%S");
    }

    public function delTree($dir)
    {
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file") && !is_link($dir)) ? HelperService::delTree("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    public function recursiveChmod($path, $filemode, $dirmode) {
        if (is_dir($path)) {
            if (!chmod($path, $dirmode)) {
                $dirmode_str = decoct($dirmode);
                throw new \RuntimeException("Failed applying filemode '$dirmode_str' on directory '$path'\n  `-> the directory '$path' will be skipped from recursive chmod\n");
            }
            $dh = opendir($path);
            while (($file = readdir($dh)) !== false) {
                if($file != '.' && $file != '..') {  // skip self and parent pointing directories
                    $fullpath = $path . '/' . $file;
                    HelperService::recursiveChmod($fullpath, $filemode,$dirmode);
                }
            }
            closedir($dh);
        } else {
            if (is_link($path)) {
                print "link '$path' is skipped\n";
                return;
            }
            if (!chmod($path, $filemode)) {
                $filemode_str = decoct($filemode);
                throw new \RuntimeException("Failed applying filemode '$filemode_str' on file '$path'\n");
            }
        }
    } 
}