<?php
    if(!defined('ABSPATH')) exit; // Exit if accessed directly
    
    class Base64ImagesAdmin extends Base64ImagesBaseClass {
        const SETTINGS_PAGE = 'b64i-settings';
        
        public function __construct () {
            add_action('admin_init', array($this, 'initialize'));
            add_action('admin_menu', array($this, 'setup_menu'));
            
            add_filter('plugin_action_links_base64images/base64images.php', array($this, 'render_action_links'));
        }
        
        public function initialize() {
            $plugin = Base64ImagesPlugin();
            $sections = $plugin->settings->get_settings_sections();
            if(!empty($sections)) {
                register_setting(Base64ImagesAdmin::SETTINGS_PAGE, Base64ImagesSettings::SETTINGS_NAME, array($this, 'validate_settings'));
                foreach($sections as $section) {
                    $section_id = $plugin->token.'-'.$section['id'].'-settings';
                    add_settings_section($section_id, $section['title'], array($this, 'render_settings_section'), Base64ImagesAdmin::SETTINGS_PAGE);
                    
                    if(!empty($section['fields'])) {
                        foreach($section['fields'] as $field) {
                            add_settings_field($field['id'], $field['label'], array($plugin->settings, 'render_field'), Base64ImagesAdmin::SETTINGS_PAGE, $section_id, $field);
                        }
                    }
                }
            }
        }
        public function setup_menu() {
            add_options_page(
                __('Base64 Images Plugin Settings', 'base-64-images-plugin-strings'),
                __('Base64 Images', 'base-64-images-plugin-strings'),
                'manage_options',
                Base64ImagesAdmin::SETTINGS_PAGE,
                array($this, 'settings_screen')
            );
        }
        
        public function validate_settings($input) {
            $plugin = Base64ImagesPlugin();
            return $plugin->settings->validate_settings($input);
        }
        public function render_settings_section($args) {}
        public function settings_screen() {
            global $title;
            
            if(!current_user_can('manage_options')) wp_die('You do not have sufficient permissions to access this page.');
            
            $plugin = Base64ImagesPlugin();
            include($plugin->plugin_path.'views/plugin-settings.php');
        }
        
        public function render_action_links($links) {
            $settings_link = '<a href="/wp-admin/options-general.php?page='.Base64ImagesAdmin::SETTINGS_PAGE.'">Settings</a>'; 
            array_unshift($links, $settings_link); 
            return $links; 
        }
    }
?>