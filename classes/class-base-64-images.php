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
        
        public static function install() {
            $plugin = Base64ImagesPlugin();
            update_option($plugin->token.'-version', $plugin->version);
        }
        public static function uninstall() {
            global $wpdb;
            
            $meta_type = 'post';
            $table = _get_meta_table($meta_type);
            $type_column = sanitize_key($meta_type.'_id');
            $meta_key = Base64Images::POST_META_BASE64_IMAGE.'.%';
            $parent_ids = $wpdb->get_col($wpdb->prepare('SELECT '.$type_column.' FROM '.$table.' WHERE meta_key LIKE %s', $meta_key));
            $query = $wpdb->prepare('DELETE FROM '.$table.' WHERE meta_key LIKE %s', $meta_key);
            $wpdb->query($query);
            
            if(!empty($parent_ids)) {
                foreach($parent_ids as $parent_id) wp_cache_delete($parent_id, $meta_type.'_meta');
            }
            
            delete_option($this->token.'-version');
        }
        
        public $name = 'Base64 Images Plugin';
        public $token = 'base-64-images';
        public $version = '1.0.0';
        public $plugin_url;
        public $plugin_path;
        
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
            
            add_action('init', array($this, 'initialize'));
            
            add_action('deleted_post', array($this, 'clear_cached_image'));
            add_action('wp_update_attachment_metadata', array($this, 'clear_cached_image'));
            
            //add_filter('get_image_tag', array($this, 'get_image_tag'), 10, 6);
            add_filter('get_image_tag_class', array($this, 'get_image_tag_class'), 1000, 4);
            add_filter('wp_get_attachment_image_src', array($this, 'wp_get_attachment_image_src'), 10, 4);
            add_filter('the_content', array($this, 'the_content'), 999999);
        }
        public function __clone () {
            _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?', 'base-64-images-plugin-strings'), $this->version);
        }
        
        public function initialize() {
            load_plugin_textdomain('base-64-images-plugin-strings', false, $this->plugin_path.'languages/');
        }
        public function clear_cached_image($post_id) {
            delete_post_meta($post_id, Base64Images::POST_META_BASE64_IMAGE);
        }
        /*
        public function get_image_tag($html, $id, $alt, $title, $align, $size) {
            // Add image ID to <img> so our content filter can work it's magic
            // In theory, WP already adds the id in the img tag's class (wp-image-<id>) BUT because filters could, potentially remove this, I do not want to rely on it.
            return preg_replace('/<img/i', '<img data-wp-image-id="'.$id.'"', $html);
        }
        */
        public function get_image_tag_class($class, $id, $align, $size) {
            if(!preg_match('/\bwp\-\image\-'.$id.'\b/', $class)) $class .= ' wp-image-'.$id;
            return $class;
        }
        public function wp_get_attachment_image_src($image, $attachment_id, $size, $icon) {
            if(!$image) return $image;
            $image[0] = $this->base64image($attachment_id, $image[0]);
            return $image;
        }
        public function the_content($content) {
            if(preg_match_all('/<img[^>]+?\bwp\-image\-(\d+)\b[^>]*?>/i', $content, $matches)) {
                for($loop = 0; $loop < count($matches[0]); $loop++) {
                    $full_match = $matches[0][$loop];
                    $replacement = $full_match;
                    $attachment_id = intVal($matches[1][$loop]);
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
                    if($full_match != $replacement) $content = preg_replace('/'.preg_quote($full_match, '/').'/im', $replacement, $content);
                }
            }
            return $content;
        }
    }
?>