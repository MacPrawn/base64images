<?php
    /*
    * Plugin Name: Base64 Images
    * Plugin URI: https://bitbucket.org/MacPrawn/base64images
    * Description: Automatically base64 encodes media images on your site.
    * Version: 1.1.1
    * Author: Jean Le Clerc
    * Author URI: http://nibnut.com
    * License: GPLv2 or later
    * License URI: https://www.gnu.org/licenses/gpl-2.0.html
    * Requires at least: 4.0.0
    * Tested up to: 4.8.1
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
    * Base64 Images is free software: you can redistribute it and/or modify
    * it under the terms of the GNU General Public License as published by
    * the Free Software Foundation, either version 2 of the License, or
    * any later version.
    * 
    * Base64 Images is distributed in the hope that it will be useful,
    * but WITHOUT ANY WARRANTY; without even the implied warranty of
    * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
    * GNU General Public License for more details.
    * 
    * You should have received a copy of the GNU General Public License
    * along with Base64 Images. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
    */
    
    if(!defined('ABSPATH')) exit; // Exit if accessed directly
    
    if(!defined('Base64ImagesBasename')) define('Base64ImagesBasename', plugin_basename(__FILE__));
    
    require_once('classes/class-base-64-images.php');
    function Base64ImagesPlugin() {
        return Base64Images::instance();
    }
    add_action('plugins_loaded', 'Base64ImagesPlugin');
    register_activation_hook(__FILE__, array('Base64Images', 'install'));
    register_uninstall_hook(__FILE__, array('Base64Images', 'uninstall'));
?>
