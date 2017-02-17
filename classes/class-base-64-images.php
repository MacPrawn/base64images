<?php
    if(!defined('ABSPATH')) exit; // Exit if accessed directly
    
    class Base64ImagesBaseClass {
        static protected $_instances = array();
        static public function instance() {
            $class = get_called_class();
            if(!isset(static::$_instances[$class])) static::$_instances[$class] = new $class();
            return static::$_instances[$class];
        }
    }
    
    class Base64Images extends Base64ImagesBaseClass {
        const POST_META_BASE64_IMAGE = '_base64_image';
        
        public $name = 'Base64 Images Plugin';
        public $token = 'base-64-images';
        public $version = '1.0.0';
        public $plugin_url;
        public $plugin_path;
        public $admin;
        public $settings;
        
        private function base64image($id, $url) {
            if(!wp_attachment_is_image($id) || preg_match('/^data\:image/', $url)) return $url;
            
            $meta_key = Base64Images::POST_META_BASE64_IMAGE.'.'.md5($url);
            $img_url = get_post_meta($id, $meta_key, true);
            if($img_url) return $img_url;
            
            $image_path = preg_replace('/^.*?wp-content\/uploads\//i', '', $url);
            if(($uploads = wp_get_upload_dir()) && (false === $uploads['error']) && (0 !== strpos($image_path, $uploads['basedir']))) {
                if(false !== strpos($image_path, 'wp-content/uploads')) $image_path = trailingslashit($uploads['basedir'].'/'._wp_get_attachment_relative_path($image_path)).basename($image_path);
                else $image_path = $uploads['basedir'].'/'.$image_path;
            }
            if(file_exists($image_path)) {
                $filetype = wp_check_filetype($image_path);
                // Read image path, convert to base64 encoding
                $imageData = base64_encode(file_get_contents($image_path));
                // Format the image SRC:  data:{mime};base64,{data};
                $img_url = 'data:image/'.$filetype['ext'].';base64,'.$imageData;
                update_post_meta($id, $meta_key, $img_url);
                return $img_url;
            }
            
            return $url;
        }
        
        function __construct() {
            $this->plugin_url = preg_replace('/\/classes/', '', plugin_dir_url(__FILE__));
            $this->plugin_path = preg_replace('/\/classes/', '', plugin_dir_path(__FILE__));
            
            require_once('class-'.$this->token.'-settings.php');
            $this->settings = Base64ImagesSettings::instance();
            
            if(is_admin()) {
                require_once('class-'.$this->token.'-admin.php');
                $this->admin = Base64ImagesAdmin::instance();
            }
            
            register_activation_hook(__FILE__, array($this, 'install'));
            register_uninstall_hook(__FILE__, array('Base64Images', 'uninstall'));
            
            add_action('init', array($this, 'initialize'));
            
            add_action('deleted_post', array($this, 'clear_cached_image'));
            add_action('wp_update_attachment_metadata', array($this, 'clear_cached_image'));
            
            //add_filter('image_downsize', array($this, 'image_downsize'), 10, 3);
            add_filter('get_image_tag', array($this, 'get_image_tag'), 10, 6);
            add_filter('wp_get_attachment_image_src', array($this, 'wp_get_attachment_image_src'), 10, 4);
            add_filter('the_content', array($this, 'the_content'), 999999);
        }
        public function __clone () {
            _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?', 'base-64-images-plugin-strings'), $this->version);
        }
        
        public function initialize() {
            load_plugin_textdomain('base-64-images-plugin-strings', false, $this->plugin_path.'languages/');
        }
        public function install() {
            update_option($this->token.'-version', $this->version);
        }
        public static function uninstall() {
            // cleanup base64 encodings in post content?
            delete_option($this->token.'-version');
        }
        
        public function clear_cached_image($post_id) {
            delete_post_meta($post_id, Base64Images::POST_META_BASE64_IMAGE);
        }
        /*
        public function image_downsize($downsize, $id, $size) {
            // if the image's url is a data-encoded url, bypass original image_downsize because url will not work for image sizes. (replace parts of url path...)
            
            $insert_request = isset($_POST['attachment']) && !empty($_POST['attachment']);
            if($insert_request) return false; // insertion in post content. This will be handled by the_content filter.
            
            $is_image = wp_attachment_is_image($id);
            if($is_image) {
                $is_intermediate = false;
                $width = $height = 0;
                $img_real_url = wp_get_attachment_url($id);
                $img_real_url_basename = wp_basename($img_real_url);
                if($intermediate = image_get_intermediate_size($id, $size)) {
                    $img_real_url = str_replace($img_real_url_basename, $intermediate['file'], $img_real_url);
                    $width = $intermediate['width'];
                    $height = $intermediate['height'];
                    $is_intermediate = true;
                } elseif($size == 'thumbnail') {
                    // fall back to the old thumbnail
                    if(($thumb_file = wp_get_attachment_thumb_file($id)) && $info = getimagesize($thumb_file)) {
                        $img_real_url = str_replace($img_real_url_basename, wp_basename($thumb_file), $img_real_url);
                        $width = $info[0];
                        $height = $info[1];
                        $is_intermediate = true;
                    }
                }
                if(!$width && !$height) {
                    $meta = wp_get_attachment_metadata($id);
                    if(isset($meta['width'], $meta['height'])) {
                        $width = $meta['width'];
                        $height = $meta['height'];
                    }
                }
                
                $meta_key = Base64Images::POST_META_BASE64_IMAGE.'.'.$width.'x'.$height;
                $img_url = get_post_meta($id, $meta_key, true);
                if(!$img_url) {
                    $image_path = get_post_meta($id, '_wp_attached_file', true);
                    if(($uploads = wp_get_upload_dir()) && (false === $uploads['error']) && (0 !== strpos($image_path, $uploads['basedir']))) {
                        if(false !== strpos($image_path, 'wp-content/uploads')) $image_path = trailingslashit($uploads['basedir'].'/'._wp_get_attachment_relative_path($image_path)).basename($image_path);
                        else $image_path = $uploads['basedir'].'/'.$image_path;
                    }
                    if(file_exists($image_path)) {
                        $filetype = wp_check_filetype($image_path);
                        // Read image path, convert to base64 encoding
                        $imageData = base64_encode(file_get_contents($image_path));
                        // Format the image SRC:  data:{mime};base64,{data};
                        $img_url = 'data:image/'.$filetype['ext'].';base64,'.$imageData;
                        update_post_meta($id, $meta_key, $img_url);
                    }
                }
                
                if($img_url) {
                    // we have the actual image size, but might need to further constrain it if content_width is narrower
                    list($width, $height) = image_constrain_size_for_editor($width, $height, $size);
                    
                    return array($img_url, $width, $height, $is_intermediate);
                }
            }
            return false;
        }
        */
        public function get_image_tag($html, $id, $alt, $title, $align, $size) {
            // Add image ID to <img> so our content filter can work it's magic
            // In theory, WP already adds the id in the img tag's class (wp-image-<id>) BUT because filters could, potentially remove this, I do not want to rely on it.
            return preg_replace('/<img/i', '<img data-wp-image-id="'.$id.'"', $html);
        }
        public function wp_get_attachment_image_src($image, $attachment_id, $size, $icon) {
            if(!$image) return $image;
            $image[0] = $this->base64image($attachment_id, $image[0]);
            return $image;
        }
        public function the_content($content) {
            while(preg_match('/(<img[^>]+?)data\-wp\-image\-id\="(\d+)"([^>]*?>)/i', $content, $matches)) {
                $full_match = $matches[0];
                $replacement = $matches[1].$matches[3]; // remove data-wp-image-id so we don't fall into an infinite loop!
                $attachment_id = intVal($matches[2]);
                if($attachment_id && preg_match('/src\s*?\=\s*?[\'"]([^\'"]+?)[\'"]/', $replacement, $matches)) {
                    $original_url = $matches[1];
                    $url = $this->base64image($attachment_id, $original_url);
                    if($url != $original_url) {
                        $start = strpos($replacement, ' src');
                        $length = strlen($matches[0]);
                        //$replacement = substr($replacement, $start, $length).' src="'.$url.'"'.substr($replacement, $length + 1);
                        $replacement = preg_replace('/'.preg_quote($matches[0], '/').'/im', 'src="'.$url.'"', $replacement);
                        $replacement = preg_replace('/srcset\s*?\=\s*?[\'"]([^\'"]+?)[\'"]/', '', $replacement);
                        $replacement = preg_replace('/sizes\s*?\=\s*?[\'"]([^\'"]+?)[\'"]/', '', $replacement);
                    }
                }
                if($full_match) $content = preg_replace('/'.preg_quote($full_match, '/').'/im', $replacement, $content);
            }
            
            return $content;
        }
    }
?>