<?php
// src/Service/RestoreService.php
namespace App\Service;
use App\Service\ShardService;
use App\Service\HelperService;
use Symfony\Component\ErrorHandler\Errorhandler;
use Symfony\Component\Console\Helper\ProgressBar;
use App\Controller\UserConsoleController;
use RuntimeException;

class RestoreService extends ShardService
{
    CONST COMMENT_FILE_NAME = "backup_comment.txt";

    public $shard_name;
    public $backup_file_list = array();
    public $tidied_backups = array();
    private $saved_path;

    public function __construct()
    {
        parent::__construct();
    }

    public function run($file_name)
    {
        $this->determineSavedPath();
        HelperService::delTree($this->saved_path);
        $zip_file_path = $this->backup_path . DIRECTORY_SEPARATOR . $file_name;
        HelperService::unzip($zip_file_path, $this->shootergame_path);
        HelperService::recursiveChmod($this->saved_path, 0755, 0755);
        $this->removeComment($this->saved_path);
    }

    public function removeComment($saved_path)
    {
        $backup_comment = $saved_path . DIRECTORY_SEPARATOR . 'backup_comment.txt';
        if (file_exists($backup_comment)) {
            unlink($backup_comment);
        }
    }

    private function determineSavedPath()
    {
        $shard_number = HelperService::translateAnswer($this->shard_name, $this->shards['installed']);
        $this->shootergame_path = $this->shards[$shard_number]['Path'] . DIRECTORY_SEPARATOR . 'ShooterGame';
        $this->saved_path = $this->shootergame_path . DIRECTORY_SEPARATOR . 'Saved';
    }

    public function compileBackupList()
    {
        $dir_contents = scandir($this->backup_path);
		foreach ($dir_contents as $content) {
            $shard_name_from_file = explode("?*|_|*?", $content)[0];
			if ($shard_name_from_file === $this->shard_name) {
				$this->backup_file_list[] = $content;
			}
		}
    }

    public function tidyBackupsForQuery()
    {
        //Arrange the backup file list in descending order
        $backups_desc = $this->backup_file_list;
        foreach ($this->backup_file_list as $key => $backup_file_name) {
            $backup_parts = explode("?*|_|*?", $backup_file_name);
            $timeparts = explode("_", trim($backup_parts[1],'.zip'));
            $time = $timeparts[0];
            $date = $timeparts[1];
            $datetime = $date . " " . $time;
            $backups_desc[$key] = strtotime($datetime);
        }
        array_multisort($backups_desc, SORT_DESC, $this->backup_file_list);

        foreach ($this->backup_file_list as $key => $backup_file_name) {
            $backup_parts = explode("?*|_|*?", $backup_file_name);
            $comment = $this->retrieveZipComment($backup_file_name);
            $timeparts = explode("_", trim($backup_parts[1],'.zip'));
            $time = $timeparts[0];
            $date = $timeparts[1];
            $datetime = $date . " " . $time;
            $this->tidied_backups[$key]['file'] = $backup_file_name;
            $this->tidied_backups[$key]['comment'] = $comment;
            $this->tidied_backups[$key]['time'] = $datetime;
        }
    }

    private function retrieveZipComment($file)
    {
        $full_path = $this->backup_path . DIRECTORY_SEPARATOR . $file;
        $archive = zip_open($full_path);
        while ($file = zip_read($archive)) {
            if (strpos(zip_entry_name($file), 'backup_comment.txt') !== FALSE) {
                return zip_entry_read($file);
            }
        }
        return '';
    }
}