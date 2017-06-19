<?php
/**
 * Created by PhpStorm.
 * User: Alexandr
 * Date: 23.05.2017
 * Time: 12:16
 */

namespace joomplace\plugins\system\thumbler;


class Processor
{
    public static $params = '{}';
    /*
     *	available parameters
     *
     *	'noimage' => fall back image
     *	'title' => makes an <img title=""/>
     *	'alt' => makes an <img alt=""/>
     *	'set_attrs' => true if need <img width="" height=""/>
     *	'width' => resulting file width
     *	'height' => resulting file height, ignored and calculated if 'square' is true
     *	'sizing' => image sizing, may be 'no' || 'spaced' || 'filled' || 'full', 'full' is default
     *	'square' => true makes resulting file square
     *	'fill_color' => fill color for whitespaces, transparent if not set
     *	'compression' => quality 0..100 for .jpeg
     *
     */
    static function renderThumb($image, $settings = array()){
        // avoid strict
        $keys = array('noimage', 'title', 'alt' , 'set_attrs', 'width', 'height', 'sizing', 'square', 'fill_color', 'compression' );
        foreach($keys as $key){
            if(!array_key_exists($key,$settings)) $settings[$key] = '';
        }
        $output = '<img src="';
        $component = self::getWorkingComponent();
        $params = JComponentHelper::getParams($component);

        if($settings['title']) $title = $settings['title']; else $title = $params->get('thmbl_title', false);
        if($settings['alt']) $alt = $settings['alt']; else $alt = $params->get('thmbl_alt', false);
        if($settings['set_attrs']) $set_attrs = $settings['set_attrs']; else $set_attrs = $params->get('thmbl_set_attrs', false);

        if($settings['width']) $width = $settings['width']; else $width = $params->get('thmbl_width', 100);
        if($settings['height']) $height = $settings['height']; else $height = $params->get('thmbl_height', false);

        if(is_file($image) || is_file(JPATH_SITE.str_replace(JUri::root(true),'/',$image))){
            $image = (is_file($image))?$image:JPATH_SITE.str_replace(JUri::root(true),'/',$image);
            $output .= $outsrc = self::getThumb($image, $settings);
        }else{
            if($settings['noimage']) $noimage = $settings['noimage']; else $noimage = $params->get('thmbl_noimage', false);
            $output .= $outsrc = self::getThumb($noimage, $settings);
        }
        $output .= '" ';
        if($title) $output .= 'title="'.$title.'" ';
        if($alt) $output .= 'alt="'.$alt.'" ';
        if($set_attrs){
            if($width) $width .= 'width="'.$width.'" ';
            if($height) $height .= 'height="'.$height.'" ';
        }
        $output .= ' />';

        if($outsrc){
            return $output;
        }
        else{
            return false;
        }
    }

    /*
     *	available parameters
     *
     *	'width' => resulting file width
     *	'height' => resulting file height, ignored and calculated if 'square' is true
     *	'sizing' => image sizing, may be 'no' || 'spaced' || 'filled' || 'full', 'full' is default
     *	'square' => true makes resulting file square
     *	'fill_color' => fill color for whitespaces, transparent if not set
     *	'compression' => quality 0..100 for .jpeg
     *
     */
    static function getThumb($image, $settings = array(), $watermark = true, $purge_cache = false){
        $params = new \Joomla\Registry\Registry(self::$params);
        $settings = new \Joomla\Registry\Registry($settings);
        $params->merge($settings);

        $width = $params->get('width',false);
        $height = $params->get('height', false);
        $square = $params->get('square',false);
        $sizing = $params->get('sizing','filled');
        $directory = trim($params->get('directory',"cache" . DIRECTORY_SEPARATOR ."images"), DIRECTORY_SEPARATOR);
        if($params->get('subdirectory',false)){
            if($params->get('sub',true)){
                $directory .= DIRECTORY_SEPARATOR . $params->get('subdirectory');
            }
        }
        if($watermark){
            $watermark = $params->get('watermark','plugins/system/thumbler/watermark.png');
        }
        $thumb_rel_path = $directory . DIRECTORY_SEPARATOR;
        if($params->get('type_sub',true)){
            $base_ctype = ($square?'s':'') . $width."&". $height . ($sizing?$sizing:'') . base64_encode($params->get('background',''));
            $thumb_rel_path .= $base_ctype . DIRECTORY_SEPARATOR;
        }

        $thumb_path = JPATH_SITE . DIRECTORY_SEPARATOR . $thumb_rel_path;
        $thumb_rel_url = \JURI::root(true).'/'.$thumb_rel_path;

        if(is_file($image) || is_file(JPATH_SITE.$image)){
            $image = (is_file($image))?$image:JPATH_SITE.$image;
        }else{
            $image = JPATH_SITE.'/'.$params->get('noimage', 'plugins/system/thumbler/noimage.jpg');
        }

        $image_name = basename($image);
        if(!$image_name){
            return null;
        }
        if(is_file($thumb_path.$image_name) && !$purge_cache){
            return trim($thumb_rel_url.$image_name,'\\');
        }else{
            if(!file_exists($thumb_path)){
                \jimport('joomla.filesystem.file');
                \jimport('joomla.filesystem.folder');
                $destination= explode(DIRECTORY_SEPARATOR , $thumb_rel_path);
                $creatingFolder = JPATH_SITE;
                foreach($destination as $folder){
                    if($folder){
                        $creatingFolder.= DIRECTORY_SEPARATOR . $folder;
                        if (!\JFolder::create($creatingFolder, 0755)){
                            return $image;
                        }
                    }
                }
            }

            /*
             * TODO: extend color support to HEX and Names
             */
            $fill_color = explode(",",$params->get('background',''));
            if(!isset($fill_color[1])){
                $fill_color = array('','','');
            }

            list($iwidth, $iheight, $itype) = getimagesize($image);

            /*
             * Coordinates preset
             */
            $x=0;
            $y=0;
            $xd=0;
            $yd=0;
            /*
             * Calculate width and height
             */
            if(!$square){
                if(!$width || !$height){
                    if(!$width && !$height){
                        $width = $params->get('width',100);
                    }
                    $ratio = $iwidth/$iheight;
                    if($width && !$height){
                        $height = $width/$ratio;
                    }else if(!$width && $height){
                        $width = $height*$ratio;
                    }
                }
            }else{
                if($width){
                    $height = $width;
                }else if($height){
                    $width = $height;
                }else{
                    $width = $height = $params->get('width',100);
                }
            }

            /*
             * Calculate resize options
             */
            if($sizing=="no"){
                /*
                 * No sizing, no scale, just crop
                 */
                $wsizer = $width/$iwidth;
                $hsizer = $height/$iheight;
            }else{
                if($sizing=="spaced"){
                    /*
                     * Size up with blank space preference
                     */
                    if($width/$iwidth > $height/$iheight){
                        $hsizer = 1;
                        $wsizer = ($width*$iheight)/($iwidth*$height);
                        $xd = ($wsizer*$width - $iwidth) / 2;
                    }else{
                        $wsizer = 1;
                        $hsizer = ($height*$iwidth)/($iheight*$width);
                        $yd = ($height - $height/$hsizer)/2;
                    }
                }
                else{
                    /*
                     * Size up with no blank space preference
                     */
                    if($sizing=="filled"){
                        /*
                         * Calculate positioning
                         */
                        if($width/$iwidth > $height/$iheight){
                            $hsizer = ($height*$iwidth)/($width*$iheight);
                            $wsizer = 1;
                            $y = ($iheight - $hsizer*$iheight)/2;
                        }else{
                            $hsizer = 1;
                            $wsizer = ($width*$iheight)/($height*$iwidth);
                            $x = ($iwidth - $wsizer*$iwidth) / 2;
                        }
                    }else{
                        /*
                         * No sizing, all sides simple scale
                         */
                        $hsizer = 1;
                        $wsizer = 1;
                    }
                }
            }

            switch ($itype) {
                case 3:
                    $file = imagecreatefrompng($image);
                    break;
                case 2:
                    $file = imagecreatefromjpeg($image);
                    break;
                case 1:
                    $file = imagecreatefromgif($image);
                    break;
                case 6:
                    $file = imagecreatefromwbmp($image);
                    break;
            }

            /*
             * Process and save an image
             */
            $thumb = imagecreatetruecolor($width, $height);
            if($fill_color[0]=='' || $fill_color[1]=='' || $fill_color[2]==''){
                $trans = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
                imagecolortransparent($thumb, $trans);
                imagefill($thumb, 0, 0, $trans);
            }else{
                imagefill($thumb, (int)$fill_color[0], (int)$fill_color[0], (int)$fill_color[0]);
            }
            imagecopyresampled ($thumb ,$file , $xd , $yd , $x , $y , $width/$wsizer , $height/$hsizer , $iwidth , $iheight );
            imagedestroy($file);

            /*
             * Add watermark
             */
            if($watermark){
                $wmsizing = $params->get('watermark_sizing',60)/100;
                $watermark = JPATH_SITE.self::getThumb($watermark,array('width'=>round($width*$wmsizing)),false, true);
                list($wwidth,$wheight,$wtype) = getimagesize($watermark);
                switch ($wtype) {
                    case 3:
                        $watermark = imagecreatefrompng($watermark);
                        break;
                    case 2:
                        $watermark = imagecreatefromjpeg($watermark);
                        break;
                    case 1:
                        $watermark = imagecreatefromgif($watermark);
                        break;
                    case 6:
                        $watermark = imagecreatefromwbmp($watermark);
                        break;
                }
                imagecopyresampled ($thumb ,$watermark , $width*(1-$wmsizing) , 0 , 0 , 0 , $wwidth , $wheight , $wwidth , $wheight );
                imagedestroy($watermark);
            }

            $q = $params->get('quality', 100 - $params->get('compression',0));
            switch ($itype) {
                case 3:
                    imagealphablending($thumb, false);
                    imagesavealpha($thumb, true);
                    imagepng($thumb, $thumb_path.$image_name, round($q*9/100), PNG_ALL_FILTERS);
                    break;
                case 2:
                    imagejpeg($thumb, $thumb_path.$image_name, $q);
                    break;
                case 1:
                    imagegif($thumb, $thumb_path.$image_name);
                    break;
            }
            imagedestroy($thumb);

            return trim($thumb_rel_url.$image_name,'\\');
        }
    }
}