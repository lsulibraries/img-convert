<?php

class Converter {

    // Print a usage message
    function printUsage($argv){
        $space = function($i){
            $spc = "";
            foreach(range(0,$i) as $unused){
                $spc .= " ";
            }
            return $spc;
        };

        $buf = function($str) use($space){
            return $space(strlen($space(10)) - strlen($str));
        };

        $ind = $space(5);

        $str =  sprintf("\nUsage: php %s src target width height quality \n\n", $argv[0]);
        $str .= sprintf("%ssrc%sdirectory from which to fetch TIFFs for conversion\n", $ind, $buf("src"));
        $str .= sprintf("%starget%sdirectory into which converted files will be saved\n", $ind, $buf('target'));
        $str .= sprintf("%swidth%starget resize width; calculated if entered as 0; ignored if both dimensions are 0.\n", $ind, $buf('width'));
        $str .= sprintf("%sheight%starget resize height; calculated if entered as 0; ignored if both dimensions are 0.\n", $ind, $buf('height'));
        $str .= sprintf("%squality%starget compression quality 1-100.\n", $ind, $buf('quality'));
        $str .= sprintf("%sformat%starget output file format (jp2, tiff, etc).\n", $ind, $buf('format'));
        $str .= sprintf("%sindex%simage index - required for multi-image input files.\n", $ind, $buf('index'));
        print $str;
    }

    public $src, $target, $width, $height, $quality, $windows, $executable, $command, $index, $outputFormat;

    public $argsRequired = 7;

    public function __construct($argv){

        // Source directory; don't include the trailing slash.
        $this->src     = new SplFileInfo($argv[1]);

        // Destination for converted files; exclude trailing slash.
        $this->target  = new SplFileInfo($argv[2]);

        // Resize width; 0 for calculated.
        $this->width   = $argv[3] === "0" ? '' : $argv[3];

        // Resize height; 0 for calculated.
        $this->height  = $argv[4] === "0" ? '' : $argv[4];

        // Quality (1-100);
        $this->quality = $argv[5];

        // output file format
        $this->outputFormat = isset($argv[6]) ? $argv[6] : 'jp2';

        // multipage image index
        $this->index = !isset($argv[6]) ? null : $argv[7];

        // Setup environment-dependent properties.
        defined('DS') ? null : define('DS', DIRECTORY_SEPARATOR);
        $this->windows    = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $this->executable = $this->windows ? 'converte.exe.' : 'convert';
        $this->identify   = $this->windows ? 'identify.exe.' : 'identify';

        // init command
        $dimensions = $this->width == '' && $this->height == '' ? '' : sprintf("-resize %sx%s", $this->width, $this->height);
        $this->command = sprintf("%s %%s[0] -depth 8 %s -quality %d -quiet %%s", $this->executable, $dimensions, $this->quality);
    }

    public function testMultipageInput(SplFileInfo $file){
        if(null === $this->index){
            $fileInfo = array();
            exec($this->identify ." -quiet  ". $this->escapePath($file->getRealPath()), $fileInfo);

            if(count($fileInfo > 1)){
                $str  = sprintf("\nMultipage input image present: %s\n", $file->getRealPath());
                $str .= sprintf("Please specify the index of the image to convert\n");
                $str .= sprintf("Possibilities are:\n");
                foreach($fileInfo as $key => $info){
                    $str .= sprintf("%s for %s\n", $key, $info);
                }
                print($str);
                exit(0);
            }
        }
    }

    /**
     * Main processing loop.
     *
     * @var SplFileInfo $src source directory of Tiffs for conversion.
     * @var SplFileInfo $src Target directory for converted images.
     */
    function processDir(SplFileInfo $src, SplFileInfo $target){
        printf("Processing directory %s\n", $src->getRealPath());

        if(!file_exists($target->getPathname())){
            printf("Creating missing target directory %s\n", $target->getPathname());
            mkdir($target->getPathname());
        }

        foreach(scandir($src->getRealPath()) as $file){
            $file = new SplFileInfo($src->getRealPath().DS.$file);

            // Do not try to process '.' and '..' directories.
            if($file->getFileName() === '.' || $file->getFileName() === '..'){
                printf("Skipping dot file '%s'\n", $file->getFilename());
                continue;
            }

            // Recurse into subdirectories.
            if($file->isDir()){

                printf("Descending into sub-directory %s\n", $file->getPathname());
                $subtarget = new SplFileInfo($target->getPathname().DS.$file->getFilename());
                $this->processDir($file, $subtarget);

                continue;
            }

            // Don't process anything other than tiffs.
            // TODO let input format be user-defined.
            if(substr($file->getExtension(), 0, 3) !== 'tif'){
                printf("Extension %s\n", substr($file->getExtension(), 0, 3));
                printf("Skipping non-TIFF file %s\n", $file->getFilename());
                continue;
            }

            $this->testMultipageInput($file);
        
            //$newPath = $target.'/'.baseFilename($file).'.jp2';
            $newPath = sprintf("%s%s%s.%s", $target->getRealPath(), DS, $this->baseFilename($file), $this->outputFormat);

            // Print message and start timer.
            printf("Processing %s\n", $file->getRealPath());

            $cmd = sprintf($this->command, $this->escapePath($file->getRealPath()), $this->escapePath($newPath));
            printf("  %s\n", $cmd);
            $start = microtime(true);

            // Execute imagemagick executable.
            exec($cmd);

            // Stop timer and finish status message.
            $lapsed = microtime(true) - $start;
            printf("  compressed %f Mb to %f Mb in %f s\n\n", $this->mb($file->getSize()), $this->mb(filesize($newPath)), $lapsed);
        }
    }

    /**
     * Convert bytes to megabytes
     * @var int $bytes number of bytes
     * @return float megabytes
     */
    function mb($bytes){
        return $bytes/1024/1024;
    }

    /**
     * Remove the file extension from a filename.
     * Depends on file extension being the last characters after the last period '.'.
     * @var SplFileInfo $file a file
     * @return string the base filename without the extension
     */
    function baseFilename(SplFileInfo $file){
        $newFilenameParts = explode('.', $file->getFilename());
        array_pop($newFilenameParts);
        return implode($newFilenameParts);
    }

    /**
     * For Unix, escape whitespace.
     * @var string $str the input string
     * @return input string with spaces escaped with '\'
     */
    function escapePath($str){
        return preg_replace('/ /', '\ ', $str);
    }
}


$converter = new Converter($argv);

if(count($argv) < $converter->argsRequired){
    $converter->printUsage($argv);
    exit(0);
}

//var_dump($converter);die();

$converter->processDir($converter->src, $converter->target);

?>