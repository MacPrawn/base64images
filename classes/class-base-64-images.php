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
            
            add_action('deleted_post', array($this, 'clear_cached_image'))
            add_action('wp_update_attachment_metadata', array($this, 'clear_cached_image'))
            
            add_filter('image_downsize', array($this, 'image_downsize'), 10, 3);
            add_filter('get_image_tag', array($this, 'get_image_tag'), 10, 6);
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
        public function image_downsize($downsize, $id, $size) {
            $insert_request = isset($_POST['attachment']) && !empty($_POST['attachment']);
            $settings = $this->settings->setting();
            if(!$insert_request && !$settings['global-settings']['encode-non-content-images']) return false;
            
            $is_image = wp_attachment_is_image($id);
            if($is_image) {
                $img_url = get_post_meta($id, Base64Images::POST_META_BASE64_IMAGE, true);
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
                        update_post_meta($id, Base64Images::POST_META_BASE64_IMAGE, $img_url);
                    }
                }
                
                if($img_url) {
                    $width = $height = 0;
                    $meta = wp_get_attachment_metadata($id);
                    if(isset($meta['width'], $meta['height'])) {
                        $width = $meta['width'];
                        $height = $meta['height'];
                    }
                
                    // we have the actual image size, but might need to further constrain it if content_width is narrower
                    list($width, $height) = image_constrain_size_for_editor($width, $height, $size);
                    
                    return array($img_url, $width, $height, false);
                }
            }
            return false;
        }
        
        public function get_image_tag($html, $id, $alt, $title, $align, $size) {
            // In theory, WP already adds the id in the img tag's class (wp-image-<id>) BUT because filters could, potentially remove this, I do not want to rely on it.
            // This id is used when the plugin is uninstalled, to revert base64 src attributes to regular URL ones.
            if(preg_match('/\ssrc\s*?\=\s*["\']data\:image/i', $html)) {
                $html = preg_replace('/<img/i', '<img data-wp-image-id="'.$id.'"', $html);
            }
            return $html;
        }
    }
?>