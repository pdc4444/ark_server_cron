<?php
// src/Service/BackupService.php
namespace App\Service;
use App\Service\ShardService;
use App\Service\HelperService;
use Symfony\Component\ErrorHandler\Errorhandler;
use Symfony\Component\Console\Helper\ProgressBar;
use App\Controller\UserConsoleController;
use \ZipArchive;

class BackupService extends ShardService
{

    CONST SAVED_PATH_FRAGMENT = "ShooterGame" . DIRECTORY_SEPARATOR . "Saved";
    CONST COMMENT_FILE_NAME = "backup_comment.txt";

    private $shards_to_backup = [];
    private $now;
    private $progress_bar;
    private $progress_bar_max = 0;
    private $files_to_zip = [];
    private $output;
    private $bar_section;
    private $text_section;

    public function __construct()
    {
        parent::__construct();
        $this->now = date('H:i:s_Y-m-d', strtotime('now'));
    }

    public function run($selected_shard, $user_comment = '', $output)
    {
        $this->output = $output;
        $this->compileFoldersToBackup($selected_shard);
        $this->addUserComment($user_comment);
        foreach ($this->shards_to_backup as $shard_data) {
            $this->shardBackup($shard_data);
        }
    }

    private function shardBackup($shard_data)
    {
        $destination = $this->backup_path . DIRECTORY_SEPARATOR . $shard_data['SessionName'] . '?*|_|*?' . $this->now . '.zip';
        $this->bar_section = $this->output->section();
        $this->text_section = $this->output->section();
        $this->progress_bar = new ProgressBar($this->bar_section);
        $this->createZipArchive($shard_data['Path'], $destination);
    }

    private function addUserComment($comment)
    {
        foreach ($this->shards_to_backup as $shard_number => $shard_data) {
            $comment_file = $shard_data['Path'] . DIRECTORY_SEPARATOR . SELF::COMMENT_FILE_NAME;
            if (file_exists($comment_file)) {
                unlink($comment_file);
            }
            ErrorHandler::call('file_put_contents', $comment_file, $comment);
        }
    }

    private function compileFoldersToBackup($selected_shard)
    {
        if ($selected_shard == 'All') {
            foreach ($this->shards as $shard_name => $shard_data) {
                if (strpos($shard_name, 'shard_') !== FALSE) {
                    $this->shards_to_backup[$shard_name]['Path'] = $shard_data['Path'] . SELF::SAVED_PATH_FRAGMENT;
                    $this->shards_to_backup[$shard_name]['SessionName'] = $shard_data[HelperService::GAME_CONFIG]['SessionSettings']['SessionName'];;
                }
            }
        } else {
            $summarized_shard_info = HelperService::summarizeShardInfo($this->shards);
            foreach ($summarized_shard_info as $shard_number => $shard_data) {
                if ($shard_data['Session Name'] == $selected_shard) {
                    $this->shards_to_backup[$shard_number]['Path'] = $this->shards[$shard_number]['Path'] . SELF::SAVED_PATH_FRAGMENT;
                    $this->shards_to_backup[$shard_number]['SessionName'] = $selected_shard;
                }
            }
        }
    }
    
  /**
   * Add files and sub-directories in a folder to zip file.
   * @param string $folder
   * @param ZipArchive $zip_archive
   * @param int $path_length length of string to cut from the folder path.  
   */
  private function folderToZip($folder, &$zip_archive, $path_length) {
    $handle = opendir($folder);
    while (FALSE !== $file = readdir($handle)) {
        if ($file != '.' && $file != '..') {
            $file_path = $folder . DIRECTORY_SEPARATOR . $file;
            $local_path = substr($file_path, $path_length);
        if (is_file($file_path)) {
            $this->text_section->overwrite(UserConsoleController::LINE_BREAK . "Zipping: " . $this->files_to_zip[$this->progress_bar_max]);
            $this->progress_bar_max++;
            $this->progress_bar->clear();
            $this->progress_bar->advance();
            $this->progress_bar->display();
            $zip_archive->addFile($file_path, $local_path);
        } elseif (is_dir($file_path)) {
            $zip_archive->addEmptyDir($local_path);
            $this->folderToZip($file_path, $zip_archive, $path_length);
        }
      }
    }
    closedir($handle);
  }

  /**
   * Create a zip archive of the $source folder at the $output destination
   * @param string $source Directory to be zipped up.
   * @param string $output The destination where the zipped file will be stored.
   */
  private function createZipArchive($source, $output)
  {
    $dir_info = pathInfo($source);
    $parent_dir = $dir_info['dirname'];
    $current_dir = $dir_info['basename'];

    $zip_archive = new ZipArchive();
    $zip_archive->open($output, ZIPARCHIVE::CREATE);
    $zip_archive->addEmptyDir($current_dir);
    $path_length = strlen("$parent_dir" . DIRECTORY_SEPARATOR);
    $this->identifyZipFiles($source, $path_length);
    $this->progress_bar->start($this->progress_bar_max);
    $this->progress_bar_max = 0;
    $this->folderToZip($source, $zip_archive, $path_length);
    $zip_archive->close();
    $this->progress_bar->finish();
  }

  private function identifyZipFiles($folder, $path_length)
  {
    $handle = opendir($folder);
    while (FALSE !== $file = readdir($handle)) {
        if ($file != '.' && $file != '..') {
            $file_path = $folder . DIRECTORY_SEPARATOR . $file;
            $local_path = substr($file_path, $path_length);
        if (is_file($file_path)) {
            $this->files_to_zip[$this->progress_bar_max] = $file_path;
            $this->progress_bar_max++;
        } elseif (is_dir($file_path)) {
            $this->identifyZipFiles($file_path, $path_length);
        }
      }
    }
  }

}