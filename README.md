## Usage

`php convert.php` for usage information.


- src         directory from which to fetch TIFFs for conversion
- target      directory into which converted files will be saved
- width       target resize width; calculated if entered as 0; ignored if both dimensions are 0.
- height      target resize height; calculated if entered as 0; ignored if both dimensions are 0.
- quality     target compression quality 1-100.
- format      target output file format (jp2, tiff, etc).
- index       image index - required for multi-image input files.


## Example

To convert all the image files in the directory at relative path `input/` into jp2 constrained to 2000 pixels width:

~~~

php convert.php input/ output/ 2000 0 100 jp2 0

~~~
