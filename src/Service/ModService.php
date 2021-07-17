<?php
// src/Service/ModService.php
namespace App\Service;
use App\Service\ShardService;
use App\Service\HelperService;
use Symfony\Component\ErrorHandler\Errorhandler;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
ErrorHandler::register();

/**
 * This service is responsible for downloading any mods being referenced by the Ark server shards. A complete list of mods is compiled and then we use 
 * steamcmd to download the mods. Symfony's Finder component is used to determine where the steamapps folder and corresponding mods have downloaded.
 * The service then goes through each mod folder and decompresses the .z files in addition to removing leftover cruft. Next a .mod file is compiled
 * through examination of the mod.info and other mod meta data file. Finally after the .mod fils has compiled the mod folders and .mod files are moved into ShooterGate/Content/Mods
 * Symfony's filesystem component does the work for moving folders / files.
 */
class ModService extends ShardService
{
    CONST GENERAL_ERROR = "Error while attempting to extract the mod_archive for this mod: ";

    private $mod_list;                   //This is an array of mod ids populated by the compileModListFunction()
    private $steamapps_location;         //This is the absolute location of the steamapps folder which is populated by the findSteamApps() function
    private $absolute_path_to_mods = []; //After we use steamcmd to download the mods the full paths to each mod folder is stored in here
    private $z_file_list = [];           //This is a variable which is reset at the start of every individual mod decompression. It stores the absolute path to each .z file found when a specific mod is examined.
    private $mod_map_names = [];         //This variable resets after the start of each mods processing. This variable contains the name of the mod as read from the mod.info file
    private $mod_meta_data = [];         //This variable resets after the start of each mods processing. When we process the modmeta.info file each key -> value is stored here for reference later.
    private $filesystem;                 //An instance of Symfony's Filesystem class. Used for moving mods into their correct location
    private $current_work;               //The current absolute path to the mod we're working on. Used to report an issue if a problem occurs.

    public function __construct()
    {
        parent::__construct();
        $this->compileModList();
    }

    /**
     * Here we are leveraging the ShardService to reference the content of the GAME_CONFIG file for each shard. If a mod is detected it gets added to our array of mods in $this->mods_list
     */
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

    /**
     * This function is where we actually kick off the code flow.
     */
    public function run()
    {
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

        //Compile the list of mod directories that have been downloaded
        $this->compileModDirectoryList();

        //Extract the .z files
        $this->zExtraction();

        $this->filesystem = new FileSystem();
        foreach ($this->absolute_path_to_mods as $mod_path) {
            $this->mod_map_names = [];
            $this->mod_meta_data = [];
            $base_mod_path = $mod_path . DIRECTORY_SEPARATOR . 'WindowsNoEditor';

            $mod_info_path = $base_mod_path . DIRECTORY_SEPARATOR . 'mod.info';
            $this->parseModInfo($mod_info_path);

            $mod_meta_data_path = $base_mod_path . DIRECTORY_SEPARATOR . 'modmeta.info';
            $this->parseModMetaData($mod_meta_data_path);

            $mod_path_parts = explode(DIRECTORY_SEPARATOR, $mod_path);
            $mod_id = trim(current(array_reverse($mod_path_parts)));
            $mod_file_location = $base_mod_path . DIRECTORY_SEPARATOR . $mod_id . '.mod';
            $this->compileMod($mod_file_location, $mod_id);
            $this->placeMods($mod_id, $base_mod_path);
        }

    }

    /**
     * This function is responsible for moving the mod files from the steamcmd downloaded directory to the root server file directory.
     * The .mod files need to live in ShooterGame/Content/Mods and each individual mod_id folder needs to be in the same directory
     * 
     * @param String $mod_id - The steam workshop id of the mod
     * @param String $base_mod_path - The base path to the mod folder
     */
    private function placeMods($mod_id, $base_mod_path)
    {
        //Remove the mod folder from root server ark files if it exists
        $ark_mod_root_folder = $this->root_server_files . DIRECTORY_SEPARATOR . 'ShooterGame' . DIRECTORY_SEPARATOR . 'Content' . DIRECTORY_SEPARATOR . 'Mods';
        $ark_mod_folder = $ark_mod_root_folder . DIRECTORY_SEPARATOR . $mod_id;
        $this->filesystem->remove($ark_mod_folder);

        //Copy contents of steamapps/workshop/content/346110/$mod_id/WindowsNoEditor to $ark_mod_folder
        $this->filesystem->mirror($base_mod_path, $ark_mod_folder);

        //Move the .mod file from $ark_mod_folder to $ark_mod_root_folder
        $ark_mod_file_destination = $ark_mod_root_folder . DIRECTORY_SEPARATOR . $mod_id . '.mod';
        if (file_exists($ark_mod_file_destination)) {
            $this->filesystem->remove($ark_mod_file_destination);
        }
        $this->filesystem->rename($ark_mod_folder . DIRECTORY_SEPARATOR . $mod_id . '.mod', $ark_mod_file_destination);
    }

    /**
     * This function takes the data read from the mod_metadata file and compiles the .mod file.
     * Raw bytes are written to the .mod file to emulate what steam creates so the mod can be used by the ark server.
     * 
     * @param String $mod_file_path - The path to the where the .mod file needs to live in
     * @param String $mod_id - The id of the mod that we're building
     */
    private function compileMod($mod_file_path, $mod_id)
    {
        $mod_file = fopen($mod_file_path, 'w+');
        $padding_bits = pack('ixxxx', $mod_id);
        fwrite($mod_file, $padding_bits);
        $this->write_ue4_string($mod_file, "ModName");
        $this->write_ue4_string($mod_file, "");

        $map_count = count($this->mod_map_names);

        //The mod map name is really just what the mod author called the mod
        fwrite($mod_file, pack('i', $map_count));
        foreach ($this->mod_map_names as $map_name) {
            $this->write_ue4_string($mod_file, $map_name);
        }

        //I don't really know what this is for, but it's needed to emulate what steam is doing when steam writes the .mod files
        fwrite($mod_file, pack('I', 4280483635));
        fwrite($mod_file, pack('i', 2));

        //The Modtype value must be written to the file as a string and not an actual int.
        foreach ($this->mod_meta_data as $key => $value) {
            if ($key == 'ModType') {
                (intval($value) == 1) ? $mod_type = '1' : $mod_type = '0';
            }
        }

        fwrite($mod_file, $mod_type);
        $meta_data_count = count($this->mod_meta_data);
        fwrite($mod_file, pack('i', $meta_data_count));
        
        //The array of mod data that we acquired from parseModMetaData() function has it's key and value pair written to the file here
        foreach ($this->mod_meta_data as $key => $value) {
            $this->write_ue4_string($mod_file, $key);
            $this->write_ue4_string($mod_file, $value);
        }
    }

    /**
     * This function writes raw strings to the .mod file.
     * The length of the string represented by an int must be written before the string
     * The string can be written in raw byte form
     * The end of the string must have the string '0' packed in char form
     * 
     * @param Object $file - This is the file we're writing and it's passed by reference
     * @param String $string - The string that we are writing to the file
     */
    private function write_ue4_string(&$file, $string)
    {
        $length = strlen($string) + 1;
        $packed_data = pack('i', $length);
        fwrite($file, $packed_data);
        fwrite($file, $string);
        fwrite($file, pack('c', '0'));
    }

    /**
     * This function reads the mod meta data file and is responsible for building an array that gets used to compile the .mod file
     * $this->mod_meta_data is populated by this function.
     * 
     * @param String $meta_data_path - The path to the modmeta.info file which gets downloaded with the mod
     */
    private function parseModMetaData($meta_data_path)
    {
        $meta_file = fopen($meta_data_path, 'r');

        //The first 4 bytes contains the total amount of key->value pairs which is used to build our array
        $raw_total_pairs = fread($meta_file, 4);
        $total_pairs = current(unpack('i', $raw_total_pairs));
        
        //Here we parse through each key->value pair and write it to $this->mod_meta_data
        for ($i = 0; $i < $total_pairs; $i++) {
            $key = $this->read_ue4_string($meta_file);
            $value = $this->read_ue4_string($meta_file);

            empty($key) ? $key = '' : FALSE;
            empty($value) ? $value = '' : FALSE;
            $this->mod_meta_data[$key] = $value;
        }
    }

    /**
     * This function strips out any additional terminal control characters / miscellaneous bytes from the passed string
     * 
     * @param String $string - The string which we are tidying up
     * @return String - The string that has been sanitized
     */
    private function tidyUpData($string)
    {
        return preg_replace('/[\x00-\x1F\x7F]/u', '', $string);   
    }


    /**
     * This function reads the mod.info file in order to populate the name of the mod.
     * $this->mod_map_names array is populated via this function
     * 
     * @param String $mod_info_path - This is the path to the mod.info file
     */
    private function parseModInfo($mod_info_path)
    {
        $mod_file = fopen($mod_info_path, 'r');
        $this->mod_map_names[] = $this->read_ue4_string($mod_file);
        $raw_map_count = fread($mod_file, 4);
        $map_count = current(unpack("i", $raw_map_count));
        for ($i = 0; $i < $map_count; $i++) {
            $this->mod_map_names[] = $this->read_ue4_string($mod_file);
        }
    }

    /**
     * Each string has 4 bytes describing how long the string is and that information is used to read the string from the raw file with fread()
     * 
     * @param Object $file_pointer - An object from fopen()
     * @return Mixed - The read string from the file or FALSE
     */
    private function read_ue4_string(&$file_pointer)
    {
        $string_header = fread($file_pointer, 4);
        $string_header_byte_count = current(unpack("i", $string_header));
        if ($string_header_byte_count > 0) {
            return $this->tidyUpData(
                utf8_encode(
                    fread($file_pointer, $string_header_byte_count)
                )
            );
        }
        return FALSE;
    }

    /**
     * This function finds all the mod directories that have been downloaded via steamcmd
     * $this->absolute_path_to_mods is populated in this function.
     */
    private function compileModDirectoryList()
    {
        $root_mod_directories = $this->steamapps_location . DIRECTORY_SEPARATOR . 'workshop' . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . '346110';
        $mod_directories = scandir($root_mod_directories);
        foreach ($mod_directories as $mod_directory) {
            if ($mod_directory !== '.' && $mod_directory !== '..') {
                $absolute_path = $root_mod_directories . DIRECTORY_SEPARATOR . $mod_directory;
                $this->absolute_path_to_mods[] = $absolute_path;
            }
        }
    }

    /**
     * Through Symfony's Finder() class we attempt to find the steamapps folder in the root directory of the filesystem.
     * This function may take a little while depending on how many files the server has. At the moment I cannot think of a 
     * better way to find the steamapps folder. Finding this folder is required to start parsing through the downloaded mods
     * $this->steamapps_location is populated by this function.
     */
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

    /**
     * This function is responsible for parsing through each mod and extracting any found .z files.
     */
    private function zExtraction()
    {
        foreach ($this->absolute_path_to_mods as $absolute_path) {
            $this->z_file_list = [];
            $this->current_work = $absolute_path;
            $this->compileZFileList($absolute_path);
            $this->performExtraction();
        }
    }

    /**
     * This function scans a mod directory and populates an array of files that need to be extracted.
     * Any files that have '.uncompressed_size' attached to their file name are removed since they are not needed for mod extraction.
     * $this->z_file_list is populated by this function()
     * 
     * @param String $dir - The path to the mod that we want to compile the z file list for
     */
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

    /**
     * This function parses through each $z_file and uses fopen and fread to perform a series of unpacks.
     * The end result is an uncompressed file that takes it's place.
     */
    private function performExtraction()
    {
        $general_error = SELF::GENERAL_ERROR . $this->current_work . "\n";
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
                    echo $general_error . "Header-Index mismatch. Header indicates it should only have $unpacked_size bytes when uncompressed but the index indicates $size_indexed bytes.\n";
                    continue;
                }

                $data_to_write = '';
                $read_data = 0;
                foreach ($compression_index as $index_array) {
                    $compressed_data = fread($raw_file, $index_array['compressed']);
                    $uncompressed_data = zlib_decode($compressed_data);

                    //Verify the size of the data is consistent with the archive index
                    if (strlen($uncompressed_data) == $index_array['uncompressed']) {
                        $data_to_write .= $uncompressed_data;
                        $read_data += 1;

                        if(strlen($uncompressed_data) != $unpacked_chunk_size && $read_data != count($compression_index)) {
                            echo $general_error . "Index contains more than one partial chunk: was $uncompressed_data when the full chunk size is $unpacked_chunk_size, chunk $read_data/" . count($compression_index) . "\n";
                            continue 2;                            
                        }
                    } else {
                        echo $general_error . "Uncompressed chunk size is not the same as the archive index\n";
                        continue 2;
                    }
                }
            } else {
                echo $general_error . "Header or data types of chunks are not correct.\n";
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