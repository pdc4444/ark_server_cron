<?php
// src/Service/ModService.php
namespace App\Service;
use App\Service\ShardService;
use App\Service\HelperService;
use Symfony\Component\ErrorHandler\Errorhandler;
// use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
// use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
ErrorHandler::register();

class ModService extends ShardService
{
    public $mod_list;
    private $steamapps_location;
    private $absolute_path_to_mods = [];
    private $z_file_list = [];

    public function __construct()
    {
        parent::__construct();
        $this->compileModList();
    }

    private function compileModList()
    {
        foreach ($this->shards as $key => $shard_data) {
            if (strpos($key, 'shard') !== FALSE && array_key_exists('activemods', $shard_data[HelperService::GAME_CONFIG]['ServerSettings'])) {
                $shard_mods = str_replace(' ', '', $shard_data[HelperService::GAME_CONFIG]['ServerSettings']['activemods']);
                isset($mods) ? $mods = $mods . ',' . $shard_mods : $mods = $shard_mods;
            }
        }
        $this->mod_list = array_unique(explode(',', $mods));
    }

    public function run()
    {
        // print_r($this);
        //Find the steamapps folder
        $this->findSteamApps();

        // Remove the steamapps folder if it exists
        if (file_exists($this->steamapps_location)) {
            HelperService::delTree($this->steamapps_location);
        }

        //Download Each mod
        foreach ($this->mod_list as $mod) {
            $arg = [$this->steam_cmd, "+login anonymous", "+workshop_download_item 346110 " . $mod, "+quit"];
            HelperService::shell_cmd($arg, $this->user_console_controller, "Downloading mod: " . $mod);
        }

        //Find the steamapps folder post mod download if it did not exist before
        empty($this->steamapps_location) ? $this->findSteamApps() : FALSE;

        //Extract the .z files
        $this->zExtraction();
    }

    private function findSteamApps()
    {
        $directory = '/';
        $finder = new Finder();
        $finder->name('steamapps');
        $finder->ignoreUnreadableDirs()->directories()->in($directory)->exclude([trim($this->root_server_files, '/'), trim($this->server_shard_directory,'/')]);
        $found = FALSE;
        if ($finder->hasResults()) {
            foreach($finder as $dir) {
                empty($found) ? $found = $dir->getRealPath() : FALSE;
            }
        }
        $this->steamapps_location = $found;
    }

    private function zExtraction()
    {
        //Compile the list of directories that will need to be extracted
        $root_mod_directories = $this->steamapps_location . DIRECTORY_SEPARATOR . 'workshop' . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . '346110';
        $mod_directories = scandir($root_mod_directories);
        foreach ($mod_directories as $mod_directory) {
            if ($mod_directory !== '.' && $mod_directory !== '..') {
                $absolute_path = $root_mod_directories . DIRECTORY_SEPARATOR . $mod_directory;
                $this->absolute_path_to_mods[] = $absolute_path;
                $this->z_file_list = [];
                $this->compileZFileList($absolute_path);
                $this->performExtraction();
            }
        }
    }

    private function compileZFileList($dir)
    {
        $dir_contents = scandir($dir);
        foreach ($dir_contents as $item) {
            $item_location = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($item_location) && $item !== '.' && $item !== '..') {
                $this->compileZFileList($item_location);
            } else if (strpos($item, '.z') !== FALSE && strpos($item, '.uncompressed_size') === FALSE && strpos($item_location, 'WindowsNoEditor') !== FALSE) {
                $this->z_file_list[] = $item_location;
            } else if (strpos($item, '.uncompressed_size') !== FALSE) {
                Errorhandler::call('unlink', $item_location);
            }
        }
    }

    private function performExtraction()
    {
        foreach ($this->z_file_list as $z_file) {
            $raw_file = fopen($z_file, 'r');

            // 00 (8 bytes) signature (6 bytes) and format ver (2 bytes)
            $signature = fread($raw_file, 8);
            $unpacked_header = current(unpack("q", $signature));

            // 08 (8 bytes) unpacked/uncompressed chunk size
            $uncompressed_chunk_size = fread($raw_file, 8);
            $unpacked_chunk_size = current(unpack("q", $uncompressed_chunk_size));

            // 10 (8 bytes) packed/compressed full size
            $packed = fread($raw_file, 8);
            $packed_size = current(unpack("q", $packed));

            // 18 (8 bytes) unpacked/uncompressed size
            $unpacked = fread($raw_file, 8);
            $unpacked_size = current(unpack("q", $unpacked));

            //If the header is not equal to this size then the integrity of the archive is in question
            if ($unpacked_header == 2653586369 && (is_int($unpacked_chunk_size) && is_int($packed_size) && is_int($unpacked_size))) {
                //Obtain the Archive Compression Index
                $size_indexed = 0;
                $compression_index = [];
                while ($size_indexed < $unpacked_size) {
                    $raw_compressed = fread($raw_file, 8);
                    $raw_uncompressed = fread($raw_file, 8);
                    $compressed = current(unpack("q", $raw_compressed));
                    $uncompressed = current(unpack("q", $raw_uncompressed));
                    $compression_index[] = ['compressed' => $compressed, 'uncompressed' => $uncompressed];
                    $size_indexed += $uncompressed;
                }

                if ($unpacked_size != $size_indexed) {
                    echo "Header-Index mismatch. Header indicates it should only have $unpacked_size bytes when uncompressed but the index indicates $size_indexed bytes.\n";
                    continue;
                }

                $data_to_write = '';
                $read_data = 0;
                foreach ($compression_index as $index_array) {
                    // print_r($index_arrays);
                    $compressed_data = fread($raw_file, $index_array['compressed']);
                    $uncompressed_data = zlib_decode($compressed_data);
                    // var_dump($uncompressed_data);

                    //Verify the size of the data is consistent with the archive index
                    if (strlen($uncompressed_data) == $index_array['uncompressed']) {
                        $data_to_write .= $uncompressed_data;
                        $read_data += 1;

                        if(strlen($uncompressed_data) != $unpacked_chunk_size && $read_data != count($compression_index)) {
                            echo "Index contains more than one partial chunk: was $uncompressed_data when the full chunk size is $unpacked_chunk_size, chunk $read_data/" . count($compression_index) . "\n";
                            continue 2;                            
                        }
                    } else {
                        echo "Uncompressed chunk size is not the same as the archive index\n";
                        continue 2;
                    }
                }
            } else {
                echo "Header or data types of chunks are not ints\n";
                continue;
            }

            $destination_file = trim($z_file, '.z');
            $decompressed_file = fopen($destination_file, 'w');
            fwrite($decompressed_file, $data_to_write);
            fclose($decompressed_file);
            Errorhandler::call('unlink', $z_file);
        }
    }
}