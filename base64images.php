<?php
    /*
    * Plugin Name: Base64 Images Plugin
    * Plugin URI: https://bitbucket.org/MacPrawn/base64images
    * Description: This plugin optionally encodes images on your WordPress site, mainly for SEO benefits. (https://varvy.com/pagespeed/base64-images.html)
    * Version: 1.0.0
    * Author: Jean Le Clerc
    * Author URI: http://nibnut.com
    * License: GPL2
    * License URI: https://www.gnu.org/licenses/gpl-2.0.html
    * Requires at least: 4.0.0
    * Tested up to: 4.7.1
    *
    * Text Domain: base64images
    * Domain Path: /languages/
    *
    * Special thanks to:
    * 
    * https://github.com/mattyza/starter-plugin
    * for the inspiration for this plugin's general file and class structure
    * 
    * http://wordpress.stackexchange.com/users/111065/leo1234562014 
    * for his contribution with the base64 encoding and permission to package his initial
    * idea into a re-usable, open source plugin.
    *
    *
    * Base64 Images Plugin is free software: you can redistribute it and/or modify
    * it under the terms of the GNU General Public License as published by
    * the Free Software Foundation, either version 2 of the License, or
    * any later version.
    * 
    * Base64 Images Plugin is distributed in the hope that it will be useful,
    * but WITHOUT ANY WARRANTY; without even the implied warranty of
    * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
    * GNU General Public License for more details.
    * 
    * You should have received a copy of the GNU General Public License
    * along with Base64 Images Plugin. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
    */
    
    if(!defined('ABSPATH')) exit; // Exit if accessed directly
    
    function Base64ImagesPlugin() {
        require_once('classes/class-base-64-images.php');
        return Base64Images::instance();
    }
    add_action('plugins_loaded', 'Base64ImagesPlugin');
?>
