<?php

// Print a usage message
function printUsage($argv){
    $space = function($i){
        $spc = "";
        foreach(range(0,$i) as $notUsed){
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
    $str .= sprintf("%squality%starget compression quality 1-100.\n\n", $ind, $buf('quality'));
    print $str;
}

if(count($argv) === 1){
    printUsage($argv);
    exit(0);
}



// Source directory; don't include the trailing slash.
$src     = new SplFileInfo($argv[1]);

// Destination for converted files;p exclude trailing slash.
$target  = new SplFileInfo($argv[2]);

// Resize width; 0 for calculated.
$width   = $argv[3] === "0" ? '' : $argv[3];

// Resize height; 0 for calculated.
$height  = $argv[4] === "0" ? '' : $argv[4];

// Quality (1-100);
$quality = $argv[5];

// Setup environment-dependent properties.
defined('DS') ? null : define('DS', DIRECTORY_SEPARATOR);
$windows    = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$executable = $windows ? 'converte.exe.' : 'convert';



/**
 * Main processing loop.
 *
 * @var SplFileInfo $src source directory of Tiffs for conversion.
 * @var SplFileInfo $src Target directory for converted images.
 */
function processDir(SplFileInfo $src, SplFileInfo $target, $command){
    printf("Processing directory %s\n", $src->getRealPath());

    foreach(scandir($src->getRealPath()) as $file){
        $file = new SplFileInfo($src->getRealPath().DS.$file);

        // Do not try to process '.' and '..' directories.
        if($file->getFileName() === '.' || $file->getFileName() === '..'){
            printf("Skipping dot file '%s'\n", $file->getFilename());
            continue;
        }

        // Recurse into subdirectories.
        if($file->isDir()){
            $subtarget = new SplFileInfo($target->getRealPath().DS.$file->getFilename());

            // Create target subdirectory.
            mkdir($subtarget->getRealPath());

            // Recursive call.
            processDir($file, $subtarget, $command);
            continue;
        }

        // Don't process anything other than tiffs.
        // TODO let input format be user-defined.
        if(substr($file->getExtension(), 0, 3) !== 'tif'){
            printf("Extension %s\n", substr($file->getExtension(), 0, 3));
            printf("Skipping non-TIFF file %s\n", $file->getFilename());
            continue;
        }

        
        //$newPath = $target.'/'.baseFilename($file).'.jp2';
        $newPath = sprintf("%s%s%s.jp2", $target->getRealPath(), DS, baseFilename($file));

        // Print message and start timer.
        printf("Processing %s\n", $file->getRealPath());

        $cmd = sprintf($command, escapePath($file->getRealPath()), escapePath($newPath));
        printf("  %s\n", $cmd);
        $start = microtime(true);

        // Execute imagemagick executable.
        exec($cmd);

        // Stop timer and finish status message.
        $lapsed = microtime(true) - $start;
        printf("  compressed %f Mb to %f Mb in %f s\n\n", mb($file->getSize()), mb(filesize($newPath)), $lapsed);
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
};

$dimensions = $width == '' && $height == '' ? '' : sprintf("-resize %sx%s", $width, $height);
$command = sprintf("%s %%s[0] -depth 8 %s -quality %d -quiet %%s", $executable, $dimensions, $quality);

processDir($src, $target, $command);

?>