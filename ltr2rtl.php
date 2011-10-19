<?php

/**
 * Class to transform templates from Left-to-Right into Right-to-Left scheme
 * Parses the files in the directory and changes the required stuff
 * @author Alex@phpguide.co.il
 * @version 2.1
 * @todo 
 * 1. Make images flipping prior to other file changes
 * @example 

   <?php
*/
    // Put your design in "somedirectory1" directory
    // Run the code as shown below
 
    ltr2rtl::work('ilya');

 
class ltr2rtl
{
    
    /** @var array[strings] image_extensions � list of imagetype file extensions */
    private static $image_extensions = array('jpg', 'png', 'gif', 'bmp');
    
    /** @var array[strings] filetype extensions that will get affected by the ltr transformer  */
    private static $affected_filetypes = array('css','html','htm','js','tpl','txt');

    /** @ var output — defines whether to echo the progress info or not */
    public static $output = true;

  
    
    /**
     * transforms all the files in the directory into rtl
     * @param string $dir — directory containing styles
     * @param bool $flip_images — indicates whether images should get flipped automatically or been asked first
     */
    public static function work($dir, $flip_images = false)
    {
        @set_time_limit(0);

        // Check first argument to be a valid directory
        if(!is_dir($dir)) 
        {
            $path = pathinfo($dir);
            $path['dirname'] = realpath($path['dirname']);
            if(empty($path['dirname'])) $path['dirname'] = dirname(__FILE__);
            throw new Exception("Directory $dir does not exist. 
                      Looked for ".$path['dirname'].DIRECTORY_SEPARATOR.$path['basename']);
        }
        
        // If no automatic image fliping - store image paths in session
        if(!$flip_images) @session_start();
        
        
        if(isset($_POST['image_flip']))
        {
            self::process_post_image_flipping_data();
            return true;
        }
        
        
        // Look for images along with required file types
        $filetypes = array_merge( self::$affected_filetypes , self::$image_extensions);
        
        // Holds reference to the array of images in the directory for different proccessing
        $imageslist = array();
        
        // Basic counter
        $affected_files_count = 0;
        
        
        self::output('Starting directory scan');
        $oDir = new RecursiveIteratorIterator (new RecursiveDirectoryIterator ($dir));

        foreach ($oDir as $file) :
               
            // Get file's name from SplFileInfo
            $file = $file->__toString();
            
            // Get file's extension
            // $ext = $file->getExtension();
            $ext = mb_substr($file, mb_strrpos($file, '.')+1);
        
            // skip files that shouldnt be affected
            if(!in_array($ext, $filetypes)) continue;
            
            // change file name
            // "leftHtmlFrame.htm" --becomes--> "rightHtmlFrame.htm"
            if ( stripos($file, 'left') !== false || stripos($file, 'right') !== false )
            {
                $newfile = self::change_direction($file);
                rename($file, $newfile);
                
                self::output("Renamed file $file into $newfile");
                $file = & $newfile;
            } 
            
            if (in_array($ext, self::$image_extensions) )
            {
                $imageslist[] = $file;
                continue;
            }
            
            self::output("Proccessing $file");
            $content = file_get_contents($file); 
            $content = self::change_direction($content); 
            $content = self::fix_margin_and_padding($content); 
            $content = self::fix_body_direction($content);

            file_put_contents($file, $content);  
            $affected_files_count++;

        endforeach;
        
        
        
        // flip images
        if($flip_images)
        {
            foreach ($imageslist as $imagefile) 
            {
                $affected_files_count++;
                self::flip_image_file($imagefile); 
            }
        }
        else
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
            $_SESSION['flip_images'] = array();
            
            foreach ($imageslist as $imagefile) 
            {
                // windows workaround
                $url = str_replace(DIRECTORY_SEPARATOR, '/', $path.$imagefile); 
                
                $_SESSION['flip_images'][++$someid] = $imagefile;
                
                
                echo "
                <tr>
                    <td> 
                        <label> Don't flip
                        <input type='radio' name='image_flip[$someid]' value='0'> 
                        </label>
                    </td>
                    <td> <img src='$url'> </td>
                    <td> <img src='$url' class='flip-horizontal'></td>
                    <td>
                        <label> Flip
                        <input type='radio' name='image_flip[$someid]' value='1' checked='checked'> 
                        </label>
                    </td>
                </tr>
                
                ";
            }
            echo '</table><input type="submit"/></form>';
        }
        
        self::output("<b> Finished </b> &mdash; $affected_files_count files have been touched");
        
        if(!$flip_images) return false;
        return true; // true - proccess is over, false - not yet
    }
    
    
    
    // <editor-fold defaultstate="collapsed" desc=" Private members ">  
    
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
     * Flips image from file horizontally
     * @param string $file � image filename
     * @return void
     */
    private static function flip_image_file($file)
    {
        
        $size = getimagesize($file);
        if ($size === false) return false;
               
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
        self::output("Flipped image $file ");
                
        imagedestroy($img);
        imagedestroy($temp);

    }



  /**
   * Echoes the passed string if output messages are enabled
   * @param mixed $output the string to output
   */
  private static function output($output)
  {
      if ( ! self::$output ) return;
      echo $output, "<br/>"; @ob_flush(); flush();
  }
  
  
  /** Goes threw the list of images in the directory and flips those the user selected to flip
   * using data from _POST  */
  private static function process_post_image_flipping_data()
  {
      foreach($_SESSION['flip_images'] as $id => $imagefile)
      {
          $should_flip = true;
          if(isset($_POST['image_flip'][$id]) && $_POST['image_flip'][$id] === '0') $should_flip = false;
          if($should_flip) self::flip_image_file ($imagefile);
      }
      unset($_SESSION['flip_images']);
      return true;
  }
  
  
  
  // </editor-fold>
  
}

