<?php

/**
 * Class to transform templates from Left-to-Right into Right-to-Left scheme
 * Parses the files in the directory and changes the required stuff
 * @author Alex@phpguide.co.il
 * @version 2.2
 * @example

   <?php

    // Put your design in "somedirectory1" directory
    // Run the code as shown below
 
    new ltr2rtl('somedirectory1');

 */
class ltr2rtl
{

    /** @var array[strings] image_extensions � list of imagetype file extensions */
    private static $image_extensions = array('jpg', 'png', 'gif', 'bmp', 'jpeg');

    /** @var array[strings] filetype extensions that will get affected by the ltr transformer  */
    private static $affected_filetypes = array('css','html','htm','js','tpl','txt');

    /** @ var verbose — defines whether to echo the progress info or not */
    public static $verbose = true;

    /** @var string directory containing files to be transformed */
    private $working_dir;
    
    private $affected_files_count = 0;

    private static $session_var_name = 'manualSelection';

    /**
     * transforms all the files in the directory into rtl
     * @param string $dir — directory containing styles
     * @param bool $auto_flip_images — indicates whether images should get flipped automatically or manualy
     */
    public function __construct($dir, $auto_flip_images = false)
    {
        @set_time_limit(0);
        @session_start();

        $this->working_dir = $dir;
        $this->validate_directory();

        try
        {
            $this->flip_images($auto_flip_images);
        }
        catch (FileNotFound $e)
        {
            self::verbose( $e->getMessage() );
        }
        catch(WaitForManualFlipping $e)
        {
            return;
        }
        

        $this->fix_files();

    }


    private function validate_directory()
    { // Check first argument to be a valid directory
        if (!is_dir($this->working_dir))
        {
            $path = pathinfo($this->working_dir);
            $path['dirname'] = realpath($path['dirname']);
            if (empty($path['dirname']))
            {
                $path['dirname'] = dirname(__FILE__);
            }
            throw new Exception("Directory ".$this->working_dir." does not exist.
                      Looked for " . $path['dirname'] . DIRECTORY_SEPARATOR . $path['basename']);
        }
    }


    private function fix_files()
    {
        foreach($this->liable_files() as $file)
        {
            try
            {
                $this->process_file($file->__toString());
            }
            catch( FileNotFound $e)
            {
                self::verbose($e->getMessage());
            }
        }
    }
    
    private function process_file($file)
    {

        self::verbose("Proccessing $file");
        $content = file_get_contents($file);
        $content = self::change_direction($content);
        $content = self::fix_margin_and_padding($content);
        $content = self::fix_body_direction($content);

        file_put_contents($file, $content);
        self::rename_file($file);
        $this->affected_files_count++;
    }

    private static function rename_file($filename)
    {
        $new_filename = self::change_direction($filename);
        if( $new_filename !== $filename )
        {
            rename($filename, $new_filename);
        }
    }


   
    /**
     * @param string $input substitute right with left, and opposite
     * @return string inverted and replaced string
     */
    private static function change_direction($input)
    {
        return str_ireplace
        ( 
            Array('left', 'right', '777---777'), 
            Array('777---777', 'left', 'right'), 
            $input
        ); 
    }
    
    
    
    /**
     * Switches margin-left with margin-right and padding-left to padding-right
     * @param string $input - replaces left padding with right padding and right padding with left padding
     * @return stirng � replaced result 
     */
    private static function fix_margin_and_padding($input)
    {
        return preg_replace
        (
            "#( margin|padding ) : \s*
            (\d{1,4}) (px|pt|cm|inch|%)? \s+
            (\d{1,4}) (px|pt|cm|inch|%)? \s+
            (\d{1,4}) (px|pt|cm|inch|%)? \s+
            (\d{1,4}) (px|pt|cm|inch|%)? \s* ; #ixu",
            "\\1: \\2\\3 \\8\\9 \\6\\7 \\4\\5 ; ", 
            $input
        );
    }
    
    /**
     * Adds dir='rtl' attribute to the body tag
     * @param string $input the document to search for the <body> tag in it
     * @return string replaced result
     */
    private static function fix_body_direction($input)
    {
	return preg_replace
        (
            "# \< body (.*) \> #ixu", 
            "<body dir='rtl' \\1>", 
            $input
        );
    }
    
    

    /**
     * @return Iterator List of all images requiring transformation
     */
    private function all_images()
    {
        return $this->get_list_of_files_by_extensions(self::$image_extensions);
    }

    /**
     * @return Iterator List of all files requiring transformation
     */
    private function liable_files()
    {
        return $this->get_list_of_files_by_extensions(self::$affected_filetypes);
    }

    /**
     * @param array $extensions
     * @return Iterator list of files
     */
    private function get_list_of_files_by_extensions(array $extensions)
    {
        static $iterator, $recursored;
        if ( $iterator === null )
        {
            $iterator = new RecursiveDirectoryIterator($this->working_dir );
            $recursored = new RecursiveIteratorIterator($iterator);
        }

        $regexp = "#\.(".implode('|', $extensions).")$#i";
        return new RegexIterator($recursored, $regexp, RegexIterator::MATCH);
    }


    /**
     *@var $automatic - flip all the images automatically or ask the user what images to flip and what not
     */
    private function flip_images($automatic)
    {   
        if($automatic)
        {
            $this->auto_images_flipping();
        }
        else
        {
            $this->manual_images_flipping();
        }
    }

    private function auto_images_flipping()
    {
        foreach($this->all_images() as $image)
        {
            $this->flip_image($image->__toString());
        }
    }
    
    private function manual_images_flipping()
    {
        if(isset($_POST[self::$session_var_name]))
        {
            array_walk( $_POST[self::$session_var_name], __CLASS__ . '::flip_image_if_needed');
            unset($_SESSION[self::$session_var_name]);
        }
        else
        {
            $this->offer_manual_flipping();
            throw new WaitForManualFlipping();
        }
    }
    
    private static function flip_image_if_needed($id_in_list, $should_flip)
    {
        if ( $should_flip && isset($_SESSION[self::$session_var_name][$id_in_list]) )
        {
            $this->flip_image($_SESSION[self::$session_var_name][$id_in_list]);
        }
    }
    
    private function offer_manual_flipping()
    {
        $url = mb_substr( $_SERVER['REQUEST_URI'], 0, mb_strrpos($_SERVER['REQUEST_URI'], '/'));
        $path = 'http://'.$_SERVER["SERVER_NAME"].$url.'/';



        echo '<h3>Some images require flipping</h3> <br/>
              Please select which images would you like to keep as is and<br/>
              press <b>Dont flip</b> for <b style="color:orangered">images with text</b> and images that dont need to be mirrored!<br/>

                <br/><br/>

             <style>
            .flip-horizontal
            {
                -moz-transform: scaleX(-1);
                -webkit-transform: scaleX(-1);
                -o-transform: scaleX(-1);
                transform: scaleX(-1);
                filter: fliph; /*IE*/
            }
            .imgtable img
            {
                max-width:150px;
                max-height:150px;
            }
            .imgtable td
            {
                width:160px;
                height:160px;
                padding:5px;
                background:#F2F2F2;
            }

            * html .imgtable img {
               width: expression( document.body.clientWidth > 150 ? "150px" : "auto" );
            }

            * html div#division {
               height: expression( this.scrollHeight > 150 ? "150px" : "auto" );
            }

            </style>

             <form method="post">
             <table class="imgtable">

                ';

        $someid = 0;
        $_SESSION[self::$session_var_name] = array();

        foreach ($this->all_images() as $imagefile)
        {
            // windows workaround
            $url = str_replace(DIRECTORY_SEPARATOR, '/', $path.$imagefile->__toString());

            $_SESSION[self::$session_var_name][++$someid] = $imagefile;


            echo "
            <tr>
                <td>
                    <label> Don't flip
                    <input type='radio' name='manualSelection[$someid]' value='0'>
                    </label>
                </td>
                <td> <img src='$url'> </td>
                <td> <img src='$url' class='flip-horizontal'></td>
                <td>
                    <label> Flip
                    <input type='radio' name='manualSelection[$someid]' value='1' checked='checked'>
                    </label>
                </td>
            </tr>

            ";
        }
        echo '</table><input type="submit"/></form>';
    }

  // </editor-fold>


    private function flip_image($file)
    {
        try
        {
            self::flip_image_file($file);
            self::rename_file($file);
            self::verbose("Flipped image $file ");
            $this->affected_files_count++;
        }
        catch(Exception $e)
        {
            
        }
    }

    /**
     * Flips image from file horizontally
     * @param string $file � image filename
     * @return void
     */
    private static function flip_image_file($file)
    {
        
        $size = getimagesize($file);
        if ($size === false)
        {
            throw new FileNotFound($file ." not found");
        }
        $format = strtolower(substr($size['mime'], strpos($size['mime'], '/')+1));
        $icfunc = "imagecreatefrom" . $format;
        $ioutfunc = "image" . $format;

        if (!function_exists($icfunc)) return false;
        $img = $icfunc($file);
        
        $size_x = imagesx($img);
        $size_y = imagesy($img);

        $temp = imagecreatetruecolor($size_x, $size_y);
        
        imagealphablending($temp, false);
        $color = imagecolortransparent($temp, imagecolorallocatealpha($temp, 0, 0, 0, 127));
        imagefill($temp, 0, 0, $color);
        imagesavealpha($temp, true);

        imagecopyresampled($temp, $img, 0, 0, ($size_x-1), 0, $size_x, $size_y, 0-$size_x, $size_y);      
        $ioutfunc($temp, $file);
                
        imagedestroy($img);
        imagedestroy($temp);

    }


    /**
    * Echoes the passed string if verbose messages are enabled
    * @param mixed $output the string to verbose
    */
    private static function verbose($output)
    {
        if ( ! self::$verbose ) return;
        echo $output, "<br/>"; @ob_flush(); flush();
    }

}

class WaitForManualFlipping extends Exception {};
class FileNotFound extends Exception {};

//new ltr2rtl('somedirectory');