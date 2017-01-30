<?php
    if(!defined('ABSPATH')) exit; // Exit if accessed directly
    
    class Base64ImagesSettings extends Base64ImagesBaseClass {
        const SETTINGS_NAME = 'b64i-settings';
        
        const FIELD_TYPE_CHECKBOX = 'checkbox';
        
        public function setting($name='') {
            $options = get_option(Base64ImagesSettings::SETTINGS_NAME);
            if(!$options) $options = array(
                'global-settings' => array(
                    'encode-non-content-images' => 1
                )
            );
            if($name) $options = (isset($options[$name]) ? $options[$name] : '');
            
            return $options;
        }
        public function get_settings_sections () {
            $settings = $this->setting();
            
            $settings_sections = array(
                array(
                    'id' => 'global-settings',
                    'title' => __('General Settings', 'base-64-images-plugin-strings'),
                    'fields' => array(
                        array(
                            'id' => 'encode-non-content-images',
                            'label' => __('Encode images', 'base-64-images-plugin-strings'),
                            'label_for' => 'encode-non-content-images',
                            'name' => Base64ImagesSettings::SETTINGS_NAME.'[global-settings][encode-non-content-images]',
                            'value' => (isset($settings['global-settings']['encode-non-content-images']) ? $settings['global-settings']['encode-non-content-images'] : ''),
                            'type' => Base64ImagesSettings::FIELD_TYPE_CHECKBOX,
                            'help' => __('Images inserted in posts and pages will be encoded always. This setting is for other images (i.e. feature images, etc...) Should those be encoded too?', 'base-64-images-plugin-strings')
                        )
                    )
                )
            );
            
            $plugin = Base64ImagesPlugin();
            return (array)apply_filters($plugin->token.'-settings-sections', $settings_sections);
        }
        public function render_field($data) {
            $type = (isset($data['type']) ? $data['type'] : '');
            
            $name = esc_attr($data['name']);
            $id = esc_attr($data['id']);
            switch($type) {
                case Base64ImagesSettings::FIELD_TYPE_CHECKBOX:
                    echo '<label for="'.$id.'">'."\n";
                    echo '<input id="'.$id.'" name="'.$name.'" type="checkbox" value="1"'.checked(esc_attr($data['value']), '1', false).' />'."\n";
                    echo $data['label'].'</label>'."\n";
                break;
                
                default:
                    echo '<input type="text" name="'.$name.'" id="'.$id.'" value="'.esc_attr($data['value']).'" class="regular-text code" />';
                break;
            }
            
            if(isset($data['help']) && $data['help']) {
                echo '<p class="description">'.$data['help'].'</p>';
            }
        }
        public function validate_settings($input) {
            $plugin = Base64ImagesPlugin();
            $settings = $this->setting();
            $sections = $plugin->settings->get_settings_sections();
            if(!empty($sections)) {
                foreach($sections as $section) {
                    if(!empty($section['fields'])) {
                        foreach($section['fields'] as $field) {
                            $value = $input[$section['id']][$field['id']];
                            
                            switch($field['type']) {
                                case Base64ImagesSettings::FIELD_TYPE_CHECKBOX:
                                    if(preg_match('/^(1|t|y)/i', $value)) $value = 1;
                                    else $value = 0;
                                break;
                            }
                            
                            if($value !== null) $settings[$section['id']][$field['id']] = $value;
                        }
                    }
                }
            }
            
            return $settings;
        }
    }
?>
