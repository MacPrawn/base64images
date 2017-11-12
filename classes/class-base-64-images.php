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
        const SETTINGS_SECTION = 'base-64-images-settings-general';
        
        const POST_META_BASE64_IMAGE = '_base64_image';
        
        const DEFAULT_MAX_SIZE = 150; // in kilobytes
        const MAX_SIZE = 'base64_max_size';
        
        public static $token = 'base-64-images';
        public static function clear_all_cached_images() {
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
        }
        public static function install() {
            $plugin = Base64ImagesPlugin();
            update_option(Base64Images::$token.'-version', $plugin->version);
        }
        public static function uninstall() {
            Base64Images::clear_all_cached_images();
            delete_option(Base64Images::$token.'-version');
        }
        
        public $name = 'Base64 Images Plugin';
        public $version = '1.1.3';
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
            
            $max_size = intVal(get_option(Base64Images::MAX_SIZE, Base64Images::DEFAULT_MAX_SIZE)) * 1024;
            //echo '[['.$max_size.' vs '.filesize($image_path).']]';
            if(file_exists($image_path) && (!$max_size || (filesize($image_path) <= $max_size))) {
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
            
            add_action('deleted_post', array($this, 'clear_cached_image_for_deleted_post'));
            
            add_filter('wp_update_attachment_metadata', array($this, 'clear_cached_image'), 10, 2);
            add_filter('get_image_tag_class', array($this, 'get_image_tag_class'), 1000, 4);
            add_filter('wp_get_attachment_image_src', array($this, 'wp_get_attachment_image_src'), 10, 4);
            add_filter('the_content', array($this, 'the_content'), 999999);
            
            add_action('admin_init', array($this, 'initialize_admin'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
            add_action('update_option_'.Base64Images::MAX_SIZE, array($this, 'settings_max_size_changed'), 10, 2);
            add_filter('plugin_action_links_'.Base64ImagesBasename, array($this, 'plugin_list_settings'));
        }
        public function __clone () {
            _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?', 'base-64-images-plugin-strings'), $this->version);
        }
        
        public function initialize() {
            load_plugin_textdomain('base-64-images-plugin-strings', false, $this->plugin_path.'languages/');
        }
        public function initialize_admin() {
            add_settings_section(Base64Images::SETTINGS_SECTION, __('Base64 Images Settings', 'base-64-images-plugin-strings'), array($this, 'general_settings_section'), 'media');
            add_settings_field(Base64Images::MAX_SIZE, __('Encode Images up to', 'base-64-images-plugin-strings'), array($this, 'size_setting'), 'media', Base64Images::SETTINGS_SECTION, array(
                'label_for' => Base64Images::MAX_SIZE
            ));
            
            register_setting('media', Base64Images::MAX_SIZE);
        }
        public function admin_enqueue_scripts($hook) {
            if($hook == 'options-media.php') {
                wp_enqueue_style('base64images-admin-css', plugins_url('../admin.css', __FILE__));
            }
        }
        public function general_settings_section($args) {
        ?>
            <hr>
            <div id="base64images-settings-section" class="base64images-settings-section">
                <div class="base64images-logo-container">
                    <a href="https://nibnut.com" target="_blank">
                        <img src="<?php echo plugins_url('../img/nibnut-logo.png', __FILE__); ?>" />
                    </a>
                </div>
            </div>
        <?php
        }
        public function size_setting($args) {
            $option = intVal(get_option(Base64Images::MAX_SIZE, Base64Images::DEFAULT_MAX_SIZE));
        ?>
            <input name="<?php echo Base64Images::MAX_SIZE; ?>" id="<?php echo Base64Images::MAX_SIZE; ?>" type="number" value="<?php echo $option; ?>" class="code" /> (in kb)
            <p>
                A base64-encoded image will be bigger (heavier) than its regular counterpart. 
                For that reason, it is not a good idea to encode big images.
            </p>
            <p>
                Small images like icons, thumbnails, etc are good candidates for encoding.
            </p>
            <p>
                Here you can control the maximum size of images the plugin should encode.
            </p>
        <?php
        }
        public function settings_max_size_changed($old_value, $new_value) {
            Base64Images::clear_all_cached_images();
        }
        public function plugin_list_settings($links) {
            $action_links = array(
                'settings' => '<a href="'.admin_url('options-media.php#base64images-settings-section').'">'.__('Settings', 'base-64-images-plugin-strings').'</a>',
            );
            return array_merge($action_links, $links);
        }
        
        public function clear_cached_image_for_deleted_post($post_id) {
            delete_post_meta($post_id, Base64Images::POST_META_BASE64_IMAGE);
        }
        public function clear_cached_image($meta_data, $post_id) {
            delete_post_meta($post_id, Base64Images::POST_META_BASE64_IMAGE);
            return $meta_data;
        }
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
                    if($attachment_id && preg_match('/src\s*?\=\s*?[\'"]([^\'"]+?)[\'"]/', $replacement, $submatches)) {
                        $original_url = $submatches[1];
                        $url = $this->base64image($attachment_id, $original_url);
                        if($url != $original_url) {
                            $start = strpos($replacement, ' src');
                            $length = strlen($submatches[0]);
                            $replacement = preg_replace('/'.preg_quote($submatches[0], '/').'/im', 'src="'.$url.'"', $replacement);
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