<?php
// src/Service/HelperService.php
namespace App\Service;
use Symfony\Component\ErrorHandler\Errorhandler;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use \ZipArchive;
ErrorHandler::register();

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

    public function enabledCheck($service)
    {
        //Check to see if the shard is enabled or not
        foreach ($service->shards as $key => $shard_info) {
            if ($key !== 'installed' && $shard_info[HelperService::SHARD_CONFIG]['ShardSettings']['enabled'] != '1') {
                unset($service->shards['installed'][$key]);
            }
        }
        return $service;
    }

    public function summarizeShardInfo($raw_shard_data)
    {
        $important_shard_data = [];
        foreach ($raw_shard_data as $shard_name => $shard_data) {
            if ($shard_name != 'installed') {
                $important_shard_data[$shard_name]['Shard Number'] = $shard_name;
                $important_shard_data[$shard_name]['Query Port'] = $shard_data[SELF::SHARD_CONFIG]['ShardSettings']['QueryPort'];
                $important_shard_data[$shard_name]['Game Port'] = $shard_data[SELF::SHARD_CONFIG]['ShardSettings']['GamePort'];
                $important_shard_data[$shard_name]['UDP Port'] = $shard_data[SELF::SHARD_CONFIG]['ShardSettings']['UdpPort'];
                $important_shard_data[$shard_name]['RCON Port'] = $shard_data[SELF::SHARD_CONFIG]['ShardSettings']['RCONPort'];
                $important_shard_data[$shard_name]['Map'] = $shard_data[SELF::SHARD_CONFIG]['ShardSettings']['Server_Map'];
                $important_shard_data[$shard_name]['Session Name'] = $shard_data[SELF::GAME_CONFIG]['SessionSettings']['SessionName'];
                $important_shard_data[$shard_name]['Session Password'] = $shard_data[SELF::GAME_CONFIG]['ServerSettings']['ServerPassword'];
                $important_shard_data[$shard_name]['Max Players'] = $shard_data[SELF::SHARD_CONFIG]['ShardSettings']['MaxPlayers'];
                ($shard_data[SELF::SHARD_CONFIG]['ShardSettings']['enabled'] == '1') ? $enabled = 'Yes' : $enabled = 'No';
                $important_shard_data[$shard_name]['Shard Enabled'] = $enabled;
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
     * @param Array $cmd_array - ["echo", "this is a command"]
     * @param Object $console_controller - An instance of the UserConsoleController object.
     * @param String $msg - A message that can be passed to customize the feedback given during process run.
     * @return String $output - A large string that contains any output captured from the running process.
     */
    public function shell_cmd($cmd_array, $console_controller = NULL, $msg = '')
    {
        $process = new Process($cmd_array);
        $process->setTimeout(7200);    //2 hour total timeout
        $process->setIdleTimeout(120); //2 minute idle timeout
        $start_time = new \DateTime();
        $process->start();
        $output = '';

        is_null($console_controller) ? TRUE : $console_controller->drawCliHeader();
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
     * @param Object $start_time - An instance of the DateTime object that represents the start time
     * @return String - The elapsed time in string format.
     */
    public function elapsedTime($start_time)
    {
        $now = new \DateTime();
        $elapsed = $now->diff($start_time);
        return $elapsed->format("%H:%I:%S");
    }

    /**
     * This function takes a directory path and recursively deletes all files & folders in said path.
     * 
     * @param String $dir - The path to the directory we wish to delete
     * @return Boolean - If the directory was successfully removed we return TRUE else FALSE
     */
    public function delTree($dir)
    {
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            $current_target = $dir . DIRECTORY_SEPARATOR . $file;
            (is_dir($current_target) && !is_link($dir)) ? HelperService::delTree($current_target) : Errorhandler::call('unlink', $current_target);
        }
        return Errorhandler::call('rmdir', $dir);
    }

    /**
     * Takes a file path and recursively sets the permissions for a folder and it's children
     * 
     * @param String $path - The path that we want to recursively modify the permissions of
     * @param String $file_mode - The permission settings that we want files to be. Expected format is like so: '0755'
     * @param String $dir_mode - The permission settings that we want directories to be. Expected format is like so: '0755'
     */
    public function recursiveChmod($path, $file_mode, $dir_mode) {
        if (is_dir($path)) {
            if (!chmod($path, $dir_mode)) {
                $dirmode_str = decoct($dir_mode);
                throw new \RuntimeException("Failed applying file mode '$dirmode_str' on directory '$path'\n  `-> the directory '$path' will be skipped from recursive chmod\n");
            }
            $dh = opendir($path);
            while (($file = readdir($dh)) !== false) {
                if($file != '.' && $file != '..') {  // skip self and parent pointing directories
                    $fullpath = $path . '/' . $file;
                    HelperService::recursiveChmod($fullpath, $file_mode, $dir_mode);
                }
            }
            closedir($dh);
        } else {
            if (is_link($path)) {
                return;
            }
            if (!chmod($path, $file_mode)) {
                $filemode_str = decoct($file_mode);
                throw new \RuntimeException("Failed applying filemode '$filemode_str' on file '$path'\n");
            }
        }
    }

    /**
     * Takes a zip file and extracts it to the given path
     * 
     * @param String $zip_file - The path to the zip file we want to extract
     * @param String $extraction_path - The path to the where we want to extract the contents of the zip file
     */
    public function unzip($zip_file, $extraction_path)
    {
        $zip = new ZipArchive;
        if ($zip->open($zip_file) === TRUE) {
            $zip->extractTo($extraction_path);
            $zip->close();
        } else {
            throw new \RuntimeException('Unable to open the zip archive. Does this path exist? ' . $zip_file);
        }
    }
}