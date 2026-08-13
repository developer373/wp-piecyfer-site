<?php
/*
	Plugin Name: Maintenance
	Plugin URI: https://wpmaintenancemode.com/
	Description: Put your site in maintenance mode, away from the public view. Use maintenance plugin if your website is in development or you need to change a few things, run an upgrade. Make it only accessible to logged in users.
	Version: 4.32
	Author: WebFactory Ltd
	Author URI: https://www.webfactoryltd.com/
	License: GPL2

	Copyright 2013-2026  WebFactory Ltd  (email : support@webfactoryltd.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// include only file
if (!defined('ABSPATH')) {
    die('Do not open this file directly.');
}

define('MTNC_FILE', __FILE__);
define('MTNC_URL', trailingslashit(plugins_url('', __FILE__)));
define('MTNC_PATH', trailingslashit(plugin_dir_path(__FILE__)));
define('MTNC_URI', trailingslashit(plugin_dir_url(__FILE__)));
define('MTNC_INCLUDES', MTNC_PATH . trailingslashit('inc'));
define('MTNC_LOAD', MTNC_PATH . trailingslashit('frontend'));
define('MTNC_THEMES_PATH', MTNC_PATH . trailingslashit('themes'));

class MTNC
{
    protected static $instance = null;
    public $version = 0;
    public $plugin_url = '';
    public $plugin_dir = '';
    public $modules;
    public $theme_id;
    public $theme;
    protected $options = array();

    /**
     * Creates a new MTNC object and implements singleton
     *
     * @return MTNC
     */
    public static function getInstance()
    {
        if (!is_a(self::$instance, 'MTNC')) {
            self::$instance = new MTNC();
        }

        return self::$instance;
    } // getInstance

    /**
     * Initialize properties, hook to filters and actions
     *
     * @return null
     */
    private function __construct()
    {
        $this->version = $this->get_plugin_version();
        $this->plugin_dir = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);
        $this->load_options();

        $options = $this->get_options();
        $this->theme_id = isset($_GET['theme_id']) && array_key_exists($_GET['theme_id'], $options['themes']) ? wp_unslash($_GET['theme_id']) : $options['theme_global']; //phpcs:ignore
        $this->theme = $options['themes'][$this->theme_id];

        //Admin Interface
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_filter('plugin_row_meta', array($this, 'plugin_meta_links'), 10, 2);
        add_filter('admin_footer_text', array($this, 'admin_footer_text'));
        add_action('wp_before_admin_bar_render', array($this, 'admin_bar'));
        add_action('wp_head', array($this, 'admin_bar_style'));
        add_action('admin_head', array($this, 'admin_bar_style'));

        //Admin actions
        add_action('admin_action_mtnc_change_status', array($this, 'change_status'));
        add_action('admin_action_mtnc_install_wpfssl', array(&$this, 'install_wpfssl'));
        add_action('admin_action_mtnc_install_wpcaptcha', array(&$this, 'install_wpcaptcha'));
        add_action('admin_action_mtnc_install_weglot', array(&$this, 'install_weglot'));
        add_action('admin_action_mtnc_install_weglot', array(&$this, 'install_weglot'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(&$this, 'plugin_action_links'));
        add_action('admin_action_mtnc_reset_settings', array($this, 'mtnc_reset_settings'));

        //AJAX
        add_action('wp_ajax_mtnc_action', array($this, 'ajax_action'));
        add_action('wp_ajax_maintenance_dismiss_notice', array($this, 'ajax_dismiss_notice'));

        //Frontend
        add_action('template_include', array($this, 'template_include'), 999999);
    } // __construct

    /**
     * Get plugin version from file header
     *
     * @return string
     */
    public function get_plugin_version()
    {
        $plugin_data = get_file_data(__FILE__, array('version' => 'Version'), 'plugin');

        return $plugin_data['version'];
    } // get_plugin_version

    // add settings link to plugins page
    static function plugin_action_links($links)
    {
        $settings_link = '<a href="' . admin_url('options-general.php?page=maintenance') . '" title="Manage redirects">Configure</a>';
        $pro_link = '<a href="' . admin_url('options-general.php?page=maintenance#open-pro-dialog') . '" title="Get PRO"><b>Get PRO</b></a>';

        array_unshift($links, $pro_link);
        array_unshift($links, $settings_link);

        return $links;
    } // plugin_action_links

    /**
     * Load and prepare the options array. If needed create a new DB entry.
     *
     * @return array
     */
    private function load_options()
    {
        $options = get_option('mtnc-options', array('options' => array()));
        $change = false;

        if (!is_array($options)) {
            $options = array('options' => array());
        }

        if (!is_array($options['options'])) {
            $options['options'] = array();
        }

        if (empty($options['meta'])) {
            $options['meta'] = array('first_version' => $this->version, 'first_install' => current_time('timestamp', true));
            $change = true;
        }

        if (!isset($options['dismissed_notices']) || !is_array($options['dismissed_notices'])) {
            $options['dismissed_notices'] = array();
            $change = true;
        }

        $default_theme_id = md5(time() . 'default');
        $def_options = array(
            'status' => '0',
            'mode' => 'layout',
            'mtnc_page' => 0,
            'header_text' => 'Our site is under maintenance!',
            //SEO
            'title' => get_bloginfo('name') . ' is under maintenance',
            'description' => 'We are doing some work on our site. Please come back later. We\'ll be up and running in no time.',
            'target_keyword' => 'maintenance',
            'excludese' => '0',
            'blockse' => '0',
            'favicon' => '',
            'social_preview' => '',
            'analytics' => '',
            'tracking_pixel' => '',
            'twitter_site' => '',
            'facebook_site' => '',
            //Access
            'show_logged_in' => '1',
            'ip_whitelist' => '',
            'per_url_settings' => '',
            'per_url_enable_disable' => '',
            'direct_access_link' => '',
            'custom_login_url' => '',
            'login_button' => '1',
            'wplogin_button_tooltip' => 'Access WordPress admin',
            'maintenance_password' => '',
            'site_password' => '',
            'password_button' => '0',
            'login_button_text' => 'Access the Site',
            'login_message' => 'Please enter the password to access the site',
            'login_wrong_password_text' => 'Wrong password',
            'login_button_tooltip' => 'Direct Access login',
            //Advanced
            'no_cache_headers' => '1',
            'disable_toolbar_menu' => '',
            'force_ssl' => '',
            'enable_rest' => '',
            'custom_wp_maintenance' => '',
            'custom_wp_maintenance_title' => '',
            'custom_wp_maintenance_content' => '',

        );

        $def_options['theme_global'] = $default_theme_id;
        $def_options['themes'] = array($default_theme_id => json_decode(
                '{
                    "modules": {
                        "header-dbd02cd1": {
                        "name": "Header",
                        "type": "header",
                        "groups": {
                            "header": {
                            "name": "Header",
                            "active": true,
                            "fields": {
                                "text": {
                                "type": "text",
                                "name": "text",
                                "label": "Text",
                                "value": "Site is undergoing maintenance",
                                "class": "full-width",
                                "desc": ""
                                },
                                "text_align": {
                                "type": "radio",
                                "name": "text_align",
                                "label": "Text Align",
                                "class": "",
                                "values": {
                                    "left": "Left",
                                    "center": "Center",
                                    "right": "Right"
                                },
                                "value": "center"
                                },
                                "font_size": {
                                "type": "range",
                                "name": "font_size",
                                "label": "Font Size",
                                "value": "47",
                                "min": 6,
                                "max": 120,
                                "class": "",
                                "units": {
                                    "px": "px",
                                    "pt": "pt",
                                    "em": "em"
                                },
                                "unit_value": "px"
                                },
                                "color": {
                                "type": "color",
                                "name": "text_color",
                                "label": "Color",
                                "value": "rgba(255,255,255,1)",
                                "desc": ""
                                },
                                "font": {
                                "type": "font",
                                "name": "font",
                                "label": "Font",
                                "value": "Open Sans",
                                "class": "full-width",
                                "desc": ""
                                },
                                "line_height": {
                                "type": "number",
                                "name": "line_height",
                                "label": "Line Height",
                                "value": "2",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "em",
                                "class": "number_small",
                                "desc": ""
                                }
                            }
                            },
                            "layout": {
                            "name": "Layout",
                            "fields": {
                                "padding_label": {
                                "type": "label",
                                "name": "padding_label",
                                "label": "Padding"
                                },
                                "padding_top": {
                                "type": "number",
                                "name": "padding_top",
                                "label": "Top",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_top.png",
                                "value": "10",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_bottom": {
                                "type": "number",
                                "name": "padding_bottom",
                                "label": "Bottom",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_bottom.png",
                                "value": "10",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_left": {
                                "type": "number",
                                "name": "padding_left",
                                "label": "Left",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_left.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_right": {
                                "type": "number",
                                "name": "padding_right",
                                "label": "Right",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_right.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_label": {
                                "type": "label",
                                "name": "margin_label",
                                "label": "Margin"
                                },
                                "margin_top": {
                                "type": "number",
                                "name": "margin_top",
                                "label": "Top",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_top.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_bottom": {
                                "type": "number",
                                "name": "margin_bottom",
                                "label": "Bottom",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_bottom.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_left": {
                                "type": "number",
                                "name": "margin_left",
                                "label": "Left",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_left.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_right": {
                                "type": "number",
                                "name": "margin_right",
                                "label": "Right",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_right.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "background_label": {
                                "type": "label",
                                "name": "background_label",
                                "label": "Background"
                                },
                                "background_color": {
                                "type": "color",
                                "name": "background_color",
                                "nolabel": true,
                                "value": "rgba(255,255,255,0)",
                                "desc": ""
                                },
                                "background": {
                                "type": "image",
                                "name": "background",
                                "nolabel": true,
                                "value": "",
                                "class": "",
                                "desc": ""
                                }
                            }
                            }
                        }
                        },
                        "content-471365b7": {
                        "name": "Content",
                        "type": "content",
                        "groups": {
                            "col1": {
                            "name": "Footer",
                            "active": true,
                            "fields": {
                                "text": {
                                "type": "textarea",
                                "name": "text",
                                "label": "Text",
                                "value": "Maintenance mode is on",
                                "class": "full-width",
                                "desc": ""
                                },
                                "font_size": {
                                "type": "number",
                                "name": "font_size",
                                "label": "Font Size",
                                "value": "36",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "pt": "pt",
                                    "em": "em"
                                },
                                "unit_value": "px"
                                },
                                "color": {
                                "type": "color",
                                "name": "text_color",
                                "label": "Color",
                                "value": "rgba(255,255,255,1)",
                                "desc": ""
                                },
                                "font": {
                                "type": "font",
                                "name": "font",
                                "label": "Font",
                                "value": "Open Sans",
                                "class": "full-width",
                                "desc": ""
                                },
                                "line_height": {
                                "type": "number",
                                "name": "line_height",
                                "label": "Line Height",
                                "value": "1",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "em",
                                "class": "number_small",
                                "desc": ""
                                }
                            }
                            },
                            "layout": {
                            "name": "Layout",
                            "fields": {
                                "padding_label": {
                                "type": "label",
                                "name": "padding_label",
                                "label": "Padding"
                                },
                                "padding_top": {
                                "type": "number",
                                "name": "padding_top",
                                "label": "Top",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_top.png",
                                "value": "100",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_bottom": {
                                "type": "number",
                                "name": "padding_bottom",
                                "label": "Bottom",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_bottom.png",
                                "value": "10",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_left": {
                                "type": "number",
                                "name": "padding_left",
                                "label": "Left",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_left.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_right": {
                                "type": "number",
                                "name": "padding_right",
                                "label": "Right",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_right.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_label": {
                                "type": "label",
                                "name": "margin_label",
                                "label": "Margin"
                                },
                                "margin_top": {
                                "type": "number",
                                "name": "margin_top",
                                "label": "Top",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_top.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_bottom": {
                                "type": "number",
                                "name": "margin_bottom",
                                "label": "Bottom",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_bottom.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_left": {
                                "type": "number",
                                "name": "margin_left",
                                "label": "Left",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_left.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_right": {
                                "type": "number",
                                "name": "margin_right",
                                "label": "Right",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_right.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "background_label": {
                                "type": "label",
                                "name": "background_label",
                                "label": "Background"
                                },
                                "background_color": {
                                "type": "color",
                                "name": "background_color",
                                "nolabel": true,
                                "value": "rgba(255,255,255,0)",
                                "desc": ""
                                },
                                "background": {
                                "type": "image",
                                "name": "background",
                                "nolabel": true,
                                "value": "",
                                "class": "",
                                "desc": ""
                                }
                            }
                            }
                        }
                        },
                        "content-9742e2f8": {
                        "name": "Content",
                        "type": "content",
                        "groups": {
                            "col1": {
                            "name": "Footer",
                            "active": true,
                            "fields": {
                                "text": {
                                "type": "textarea",
                                "name": "text",
                                "label": "Text",
                                "value": "Site will be available soon. Thank you for your patience!",
                                "class": "full-width",
                                "desc": ""
                                },
                                "font_size": {
                                "type": "number",
                                "name": "font_size",
                                "label": "Font Size",
                                "value": "20",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "pt": "pt",
                                    "em": "em"
                                },
                                "unit_value": "px"
                                },
                                "color": {
                                "type": "color",
                                "name": "text_color",
                                "label": "Color",
                                "value": "rgba(255,255,255,1)",
                                "desc": ""
                                },
                                "font": {
                                "type": "font",
                                "name": "font",
                                "label": "Font",
                                "value": "Open Sans",
                                "class": "full-width",
                                "desc": ""
                                },
                                "line_height": {
                                "type": "number",
                                "name": "line_height",
                                "label": "Line Height",
                                "value": "1",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "em",
                                "class": "number_small",
                                "desc": ""
                                }
                            }
                            },
                            "layout": {
                            "name": "Layout",
                            "fields": {
                                "padding_label": {
                                "type": "label",
                                "name": "padding_label",
                                "label": "Padding"
                                },
                                "padding_top": {
                                "type": "number",
                                "name": "padding_top",
                                "label": "Top",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_top.png",
                                "value": "10",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_bottom": {
                                "type": "number",
                                "name": "padding_bottom",
                                "label": "Bottom",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_bottom.png",
                                "value": "10",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_left": {
                                "type": "number",
                                "name": "padding_left",
                                "label": "Left",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_left.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_right": {
                                "type": "number",
                                "name": "padding_right",
                                "label": "Right",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_right.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_label": {
                                "type": "label",
                                "name": "margin_label",
                                "label": "Margin"
                                },
                                "margin_top": {
                                "type": "number",
                                "name": "margin_top",
                                "label": "Top",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_top.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_bottom": {
                                "type": "number",
                                "name": "margin_bottom",
                                "label": "Bottom",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_bottom.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_left": {
                                "type": "number",
                                "name": "margin_left",
                                "label": "Left",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_left.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_right": {
                                "type": "number",
                                "name": "margin_right",
                                "label": "Right",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_right.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "background_label": {
                                "type": "label",
                                "name": "background_label",
                                "label": "Background"
                                },
                                "background_color": {
                                "type": "color",
                                "name": "background_color",
                                "nolabel": true,
                                "value": "rgba(255,255,255,0)",
                                "desc": ""
                                },
                                "background": {
                                "type": "image",
                                "name": "background",
                                "nolabel": true,
                                "value": "",
                                "class": "",
                                "desc": ""
                                }
                            }
                            }
                        }
                        },
                        "footer-a29820d6": {
                        "name": "Footer",
                        "type": "footer",
                        "groups": {
                            "col1": {
                            "name": "Footer",
                            "active": true,
                            "fields": {
                                "text": {
                                "type": "textarea",
                                "name": "text",
                                "label": "Text",
                                "value": "© Maintenance 2026",
                                "class": "full-width",
                                "desc": ""
                                },
                                "font_size": {
                                "type": "number",
                                "name": "font_size",
                                "label": "Font Size",
                                "value": "24",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "pt": "pt",
                                    "em": "em"
                                },
                                "unit_value": "px"
                                },
                                "color": {
                                "type": "color",
                                "name": "text_color",
                                "label": "Color",
                                "value": "rgba(255,255,255,1)",
                                "desc": ""
                                },
                                "font": {
                                "type": "font",
                                "name": "font",
                                "label": "Font",
                                "value": "Open Sans",
                                "class": "full-width",
                                "desc": ""
                                },
                                "line_height": {
                                "type": "number",
                                "name": "line_height",
                                "label": "Line Height",
                                "value": "1",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "em",
                                "class": "number_small",
                                "desc": ""
                                }
                            }
                            },
                            "layout": {
                            "name": "Layout",
                            "fields": {
                                "padding_label": {
                                "type": "label",
                                "name": "padding_label",
                                "label": "Padding"
                                },
                                "padding_top": {
                                "type": "number",
                                "name": "padding_top",
                                "label": "Top",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_top.png",
                                "value": "10",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_bottom": {
                                "type": "number",
                                "name": "padding_bottom",
                                "label": "Bottom",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_bottom.png",
                                "value": "10",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_left": {
                                "type": "number",
                                "name": "padding_left",
                                "label": "Left",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_left.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_right": {
                                "type": "number",
                                "name": "padding_right",
                                "label": "Right",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_right.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_label": {
                                "type": "label",
                                "name": "margin_label",
                                "label": "Margin"
                                },
                                "margin_top": {
                                "type": "number",
                                "name": "margin_top",
                                "label": "Top",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_top.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_bottom": {
                                "type": "number",
                                "name": "margin_bottom",
                                "label": "Bottom",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_bottom.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_left": {
                                "type": "number",
                                "name": "margin_left",
                                "label": "Left",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_left.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_right": {
                                "type": "number",
                                "name": "margin_right",
                                "label": "Right",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_right.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "background_label": {
                                "type": "label",
                                "name": "background_label",
                                "label": "Background"
                                },
                                "background_color": {
                                "type": "color",
                                "name": "background_color",
                                "nolabel": true,
                                "value": "rgba(255,255,255,0)",
                                "desc": ""
                                },
                                "background": {
                                "type": "image",
                                "name": "background",
                                "nolabel": true,
                                "value": "",
                                "class": "",
                                "desc": ""
                                }
                            }
                            }
                        }
                        }
                    },
                    "modules_order": {
                        "header-dbd02cd1": "header-dbd02cd1",
                        "content-471365b7": "content-471365b7",
                        "content-9742e2f8": "content-9742e2f8",
                        "footer-a29820d6": "footer-a29820d6"
                    },
                    "name": "Default",
                    "theme_thumbnail": "",
                    "theme_status": "",
                    "body_font_size": "16",
                    "body_font_color": "#FFFFFF",
                    "body_font": "Open Sans",
                    "body_link_color": "#0096FF",
                    "body_link_hover_color": "#57BAFF",
                    "background_type": "image",
                    "background_video": "",
                    "background_video_fallback": "",
                    "background_video_filter": "",
                    "background_size_opt": "cover",
                    "background_position": "center center",
                    "background_image_filter": "",
                    "background_blur": 0,
                    "background_image": "' . trailingslashit(MTNC_URL) . 'img/mt-sample-background.jpg",
                    "preloader_background_image": "",
                    "background_color": "rgba(0,0,0,1)",
                    "login_background_color": "rgba(0,0,0,1)",
                    "content_overlay": "",
                    "content_width": 916,
                    "content_overlay_color": "rgba(0,0,0,0.2)",
                    "content_overlay_shadow_color": "rgba(0,0,0,0.2)",
                    "content_position": "middle",
                    "modules_spacing": 20,
                    "custom_css": ".mtnc_module,\r\n.mtnc_module h2{\r\n\tfont-weight:300;\r\n}"
                    }'
            )
        );

        if (sizeof($options['options']) < sizeof($def_options)) {
            $options['options'] = array_merge($def_options, (array) $options['options']);
            $change = true;
        }

        /* Detect upgrade from free */
        $mtnc_free_options = get_option('maintenance_options', false);



        if ($mtnc_free_options !== false && array_key_exists('state', $mtnc_free_options) && !array_key_exists('upgraded', $options)) {
            if(!empty($mtnc_free_options['state']) && $mtnc_free_options['state'] == '1'){
                $options['options']['status'] = '1';
            } else {
                $options['options']['status'] = '0';
            }

            $options['options']['theme_global'] = $default_theme_id;
            $options['options']['themes'] = [];
            $options['options']['themes'][$default_theme_id] = json_decode('{"modules":{"logo-ea1e7873":{"name":"Logo","type":"logo","groups":{"logo":{"name":"Logo","active":true,"fields":{"logo":{"type":"image","name":"logo","label":"Logo","value":"","class":"","desc":""},"title":{"type":"text","name":"title","label":"Logo Title","value":"","class":"full-width","desc":""},"width":{"type":"number","name":"width","label":"Width","value":"","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","desc":"Leave blank for auto"},"height":{"type":"number","name":"height","label":"Height","value":"","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","desc":"Leave blank for auto"}}},"layout":{"name":"Layout","fields":{"padding_label":{"type":"label","name":"padding_label","label":"Padding"},"padding_top":{"type":"number","name":"padding_top","label":"Top","image_label":"MTNCURL\/img\/icons\/padding_top.png","value":"10","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"padding_bottom":{"type":"number","name":"padding_bottom","label":"Bottom","image_label":"MTNCURL\/img\/icons\/padding_bottom.png","value":"10","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"padding_left":{"type":"number","name":"padding_left","label":"Left","image_label":"MTNCURL\/img\/icons\/padding_left.png","value":"0","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"padding_right":{"type":"number","name":"padding_right","label":"Right","image_label":"MTNCURL\/img\/icons\/padding_right.png","value":"0","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"margin_label":{"type":"label","name":"margin_label","label":"Margin"},"margin_top":{"type":"number","name":"margin_top","label":"Top","image_label":"MTNCURL\/img\/icons\/margin_top.png","value":"0","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"margin_bottom":{"type":"number","name":"margin_bottom","label":"Bottom","image_label":"MTNCURL\/img\/icons\/margin_bottom.png","value":"0","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"margin_left":{"type":"number","name":"margin_left","label":"Left","image_label":"MTNCURL\/img\/icons\/margin_left.png","value":"0","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"margin_right":{"type":"number","name":"margin_right","label":"Right","image_label":"MTNCURL\/img\/icons\/margin_right.png","value":"0","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"background_label":{"type":"label","name":"background_label","label":"Background"},"background_color":{"type":"color","name":"background_color","nolabel":true,"value":"rgba(255,255,255,0)","desc":""},"background":{"type":"image","name":"background","nolabel":true,"value":"","class":"","desc":""}}}}},"content-a60d7054":{"name":"Content","type":"content","groups":{"col1":{"name":"Footer","active":true,"fields":{"text":{"type":"textarea","name":"text","label":"Text","value":"<span style=\"color: rgb(255, 255, 255); font-family: \"Open Sans\"; text-align: center; background-color: rgb(17, 17, 17);\">Site will be available soon. Thank you for your patience!<\/span>","class":"full-width","desc":""},"font_size":{"type":"number","name":"font_size","label":"Font Size","value":"14","class":"number_small","units":{"px":"px","pt":"pt","em":"em"},"unit_value":"px"},"color":{"type":"color","name":"text_color","label":"Color","value":"rgba(255,255,255,1)","desc":""},"font":{"type":"font","name":"font","label":"Font","value":"Arial","class":"full-width","desc":""},"line_height":{"type":"number","name":"line_height","label":"Line Height","value":"1","units":{"px":"px","percent":"%","em":"em"},"unit_value":"em","class":"number_small","desc":""}}},"layout":{"name":"Layout","fields":{"padding_label":{"type":"label","name":"padding_label","label":"Padding"},"padding_top":{"type":"number","name":"padding_top","label":"Top","image_label":"MTNCURL\/img\/icons\/padding_top.png","value":"10","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"padding_bottom":{"type":"number","name":"padding_bottom","label":"Bottom","image_label":"MTNCURL\/img\/icons\/padding_bottom.png","value":"10","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"padding_left":{"type":"number","name":"padding_left","label":"Left","image_label":"MTNCURL\/img\/icons\/padding_left.png","value":"0","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"padding_right":{"type":"number","name":"padding_right","label":"Right","image_label":"MTNCURL\/img\/icons\/padding_right.png","value":"0","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"margin_label":{"type":"label","name":"margin_label","label":"Margin"},"margin_top":{"type":"number","name":"margin_top","label":"Top","image_label":"MTNCURL\/img\/icons\/margin_top.png","value":"0","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"margin_bottom":{"type":"number","name":"margin_bottom","label":"Bottom","image_label":"MTNCURL\/img\/icons\/margin_bottom.png","value":"80","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"margin_left":{"type":"number","name":"margin_left","label":"Left","image_label":"MTNCURL\/img\/icons\/margin_left.png","value":"0","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"margin_right":{"type":"number","name":"margin_right","label":"Right","image_label":"MTNCURL\/img\/icons\/margin_right.png","value":"0","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"background_label":{"type":"label","name":"background_label","label":"Background"},"background_color":{"type":"color","name":"background_color","nolabel":true,"value":"rgba(255,255,255,0)","desc":""},"background":{"type":"image","name":"background","nolabel":true,"value":"","class":"","desc":""}}}}},"header-4b5742b4":{"name":"Header","type":"header","groups":{"header":{"name":"Header","active":true,"fields":{"text":{"type":"text","name":"text","label":"Text","value":"Maintenance mode is on","class":"full-width","desc":""},"text_align":{"type":"radio","name":"text_align","label":"Text Align","class":"","values":{"left":"Left","center":"Center","right":"Right"},"value":"center"},"font_size":{"type":"range","name":"font_size","label":"Font Size","value":"30","min":6,"max":120,"class":"","units":{"px":"px","pt":"pt","em":"em"},"unit_value":"px"},"color":{"type":"color","name":"text_color","label":"Color","value":"rgba(255,255,255,1)","desc":""},"font":{"type":"font","name":"font","label":"Font","value":"Arial","class":"full-width","desc":""},"line_height":{"type":"number","name":"line_height","label":"Line Height","value":"1","units":{"px":"px","percent":"%","em":"em"},"unit_value":"em","class":"number_small","desc":""}}},"layout":{"name":"Layout","fields":{"padding_label":{"type":"label","name":"padding_label","label":"Padding"},"padding_top":{"type":"number","name":"padding_top","label":"Top","image_label":"MTNCURL\/img\/icons\/padding_top.png","value":"10","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"padding_bottom":{"type":"number","name":"padding_bottom","label":"Bottom","image_label":"MTNCURL\/img\/icons\/padding_bottom.png","value":"10","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"padding_left":{"type":"number","name":"padding_left","label":"Left","image_label":"MTNCURL\/img\/icons\/padding_left.png","value":"0","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"padding_right":{"type":"number","name":"padding_right","label":"Right","image_label":"MTNCURL\/img\/icons\/padding_right.png","value":"0","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"margin_label":{"type":"label","name":"margin_label","label":"Margin"},"margin_top":{"type":"number","name":"margin_top","label":"Top","image_label":"MTNCURL\/img\/icons\/margin_top.png","value":"80","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"margin_bottom":{"type":"number","name":"margin_bottom","label":"Bottom","image_label":"MTNCURL\/img\/icons\/margin_bottom.png","value":"0","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"margin_left":{"type":"number","name":"margin_left","label":"Left","image_label":"MTNCURL\/img\/icons\/margin_left.png","value":"0","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"margin_right":{"type":"number","name":"margin_right","label":"Right","image_label":"MTNCURL\/img\/icons\/margin_right.png","value":"0","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"background_label":{"type":"label","name":"background_label","label":"Background"},"background_color":{"type":"color","name":"background_color","nolabel":true,"value":"rgba(255,255,255,0)","desc":""},"background":{"type":"image","name":"background","nolabel":true,"value":"","class":"","desc":""}}}}},"footer-b527dd0c":{"name":"Footer","type":"footer","groups":{"col1":{"name":"Footer","active":true,"fields":{"text":{"type":"textarea","name":"text","label":"Text","value":"<div style=\"text-align: center;\">\u00a9 Maintenance 2022<br><\/div>","class":"full-width","desc":""},"font_size":{"type":"number","name":"font_size","label":"Font Size","value":"14","class":"number_small","units":{"px":"px","pt":"pt","em":"em"},"unit_value":"px"},"color":{"type":"color","name":"text_color","label":"Color","value":"rgba(255,255,255,1)","desc":""},"font":{"type":"font","name":"font","label":"Font","value":"Arial","class":"full-width","desc":""},"line_height":{"type":"number","name":"line_height","label":"Line Height","value":"1","units":{"px":"px","percent":"%","em":"em"},"unit_value":"em","class":"number_small","desc":""}}},"layout":{"name":"Layout","fields":{"padding_label":{"type":"label","name":"padding_label","label":"Padding"},"padding_top":{"type":"number","name":"padding_top","label":"Top","image_label":"MTNCURL\/img\/icons\/padding_top.png","value":"10","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"padding_bottom":{"type":"number","name":"padding_bottom","label":"Bottom","image_label":"MTNCURL\/img\/icons\/padding_bottom.png","value":"10","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"padding_left":{"type":"number","name":"padding_left","label":"Left","image_label":"MTNCURL\/img\/icons\/padding_left.png","value":"0","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"padding_right":{"type":"number","name":"padding_right","label":"Right","image_label":"MTNCURL\/img\/icons\/padding_right.png","value":"0","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"margin_label":{"type":"label","name":"margin_label","label":"Margin"},"margin_top":{"type":"number","name":"margin_top","label":"Top","image_label":"MTNCURL\/img\/icons\/margin_top.png","value":"0","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"margin_bottom":{"type":"number","name":"margin_bottom","label":"Bottom","image_label":"MTNCURL\/img\/icons\/margin_bottom.png","value":"0","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"margin_left":{"type":"number","name":"margin_left","label":"Left","image_label":"MTNCURL\/img\/icons\/margin_left.png","value":"0","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"margin_right":{"type":"number","name":"margin_right","label":"Right","image_label":"MTNCURL\/img\/icons\/margin_right.png","value":"0","class":"number_small","units":{"px":"px","percent":"%","em":"em"},"unit_value":"px","wrapper_class":"col-4"},"background_label":{"type":"label","name":"background_label","label":"Background"},"background_color":{"type":"color","name":"background_color","nolabel":true,"value":"rgba(255,255,255,0)","desc":""},"background":{"type":"image","name":"background","nolabel":true,"value":"","class":"","desc":""}}}}}},"modules_order":{"logo-ea1e7873":"logo-ea1e7873","header-4b5742b4":"header-4b5742b4","content-a60d7054":"content-a60d7054","footer-b527dd0c":"footer-b527dd0c"},"name":"My Theme","body_font_size":"16","body_font_color":"#FFFFFF","body_font":"Orbitron","body_link_color":"#0096FF","body_link_hover_color":"#57BAFF","background_type":"image","background_video":"","background_video_fallback":"","background_video_filter":"","background_size_opt":"cover","background_position":"center center","background_image_filter":"","background_image":"","background_color":"rgba(64,64,64,1)","content_overlay":"","content_width":600,"content_overlay_color":"rgba(0,0,0,0.2)","content_overlay_shadow_color":"rgba(0,0,0,0)","content_position":"middle","modules_spacing":10,"custom_css":"","theme_thumbnail":"","theme_status":"","background_blur":10,"preloader_background_image":""}');

            $logo_src = wp_get_attachment_image_src($mtnc_free_options['logo'], 'full');
            $options['options']['themes'][$default_theme_id]->modules->{'logo-ea1e7873'}->groups->logo->fields->logo->value = esc_url($logo_src[0]);
            $options['options']['themes'][$default_theme_id]->modules->{'logo-ea1e7873'}->groups->logo->fields->width->value = $mtnc_free_options['logo_width'];
            $options['options']['themes'][$default_theme_id]->modules->{'logo-ea1e7873'}->groups->logo->fields->height->value = $mtnc_free_options['logo_height'];

            //SEO
            $options['options']['title'] = $mtnc_free_options['page_title'];
            $options['options']['description'] = $mtnc_free_options['description'];

            //Access
            $options['options']['login_button'] = $mtnc_free_options['is_login'];

            //Design
            $old_bg = wp_get_attachment_image_src($mtnc_free_options['body_bg'], 'full');
            $options['options']['themes'][$default_theme_id]->background_image = esc_url($old_bg[0]);
            $options['options']['themes'][$default_theme_id]->background_blur = $mtnc_free_options['blur_intensity'];
            $options['options']['themes'][$default_theme_id]->background_color = $mtnc_free_options['body_bg_color'];
            $options['options']['themes'][$default_theme_id]->login_background_color = $mtnc_free_options['controls_bg_color'];


            $options['options']['themes'][$default_theme_id]->body_font_color = $mtnc_free_options['font_color'];
            $options['options']['themes'][$default_theme_id]->body_font = $mtnc_free_options['body_font_family'];
            $options['options']['themes'][$default_theme_id]->background_color = $mtnc_free_options['body_bg_color'];
            $options['options']['themes'][$default_theme_id]->custom_css = 'body,h2{font-weight:300;}';

            $options['options']['themes'][$default_theme_id]->modules->{'header-4b5742b4'}->groups->header->fields->text->value = '<span style="text-align: center;">' . $mtnc_free_options['heading'] . '</span>';
            $options['options']['themes'][$default_theme_id]->modules->{'header-4b5742b4'}->groups->header->fields->font_size->value = 45;
            $options['options']['themes'][$default_theme_id]->modules->{'header-4b5742b4'}->groups->header->fields->font->value = $mtnc_free_options['body_font_family'];
            $options['options']['themes'][$default_theme_id]->modules->{'header-4b5742b4'}->groups->layout->fields->margin_top->value = 80;

            $options['options']['themes'][$default_theme_id]->modules->{'content-a60d7054'}->groups->col1->fields->text->value = '<span style="text-align: center;">' . $mtnc_free_options['description'] . '</span>';
            $options['options']['themes'][$default_theme_id]->modules->{'content-a60d7054'}->groups->col1->fields->font_size->value = 16;
            $options['options']['themes'][$default_theme_id]->modules->{'content-a60d7054'}->groups->col1->fields->font->value = $mtnc_free_options['body_font_family'];
            $options['options']['themes'][$default_theme_id]->modules->{'content-a60d7054'}->groups->layout->fields->margin_bottom->value = 80;

            $options['options']['themes'][$default_theme_id]->modules->{'footer-b527dd0c'}->groups->col1->fields->text->value = '<span style="text-align: center;">' . $mtnc_free_options['footer_text'] . '</span>';
            $options['options']['themes'][$default_theme_id]->modules->{'footer-b527dd0c'}->groups->col1->fields->font_size->value = 16;
            $options['options']['themes'][$default_theme_id]->modules->{'footer-b527dd0c'}->groups->col1->fields->font->value = $mtnc_free_options['body_font_family'];

            $options['upgraded'] = time();
            $change = true;
        }

        if ($change) {
            update_option('mtnc-options', $options, true);
        }

        $this->options = $options;
        return $options;
    } // load_options

    // change status via admin bar
    public function change_status()
    {
        check_admin_referer('mtnc_change_status');

        if (empty($_GET['new_status'])) {
            wp_safe_redirect(admin_url());
            exit;
        }

        $options = $this->get_options();

        if ($_GET['new_status'] == 'enabled') {
            $options['status'] = '1';
        } else {
            $options['status'] = '0';
        }

        $this->update_options('options', $options);

        if (!empty($_GET['redirect'])) {
            wp_safe_redirect(wp_unslash($_GET['redirect']));
        } else {
            wp_safe_redirect(admin_url());
        }

        exit;
    } // change_status

    /**
     * Dismiss notice via AJAX call
     *
     * @return null
     */
    function ajax_dismiss_notice()
    {
        check_ajax_referer('maintenance_dismiss_notice');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('You are not allowed to run this action.');
        }

        $notice_name = trim(sanitize_text_field(wp_unslash($_GET['notice_name'] ?? '')));
        if (!$this->dismiss_notice($notice_name)) {
            wp_send_json_error('Notice is already dismissed.');
        } else {
            wp_send_json_success();
        }
    } // ajax_dismiss_notice


    /**
     * Dismiss notice by adding it to dismissed_notices options array
     *
     * @param string  $notice_name  Notice to dismiss.
     *
     * @return bool
     */
    function dismiss_notice($notice_name)
    {
        if ($this->get_dismissed_notices($notice_name)) {
            return false;
        } else {
            $notices = $this->get_dismissed_notices();
            $notices[$notice_name] = true;

            $this->update_options('dismissed_notices', $notices);
            return true;
        }
    } // dismiss_notice


    /**
     * Get all dismissed notices, or check for one specific notice
     *
     * @param string  $notice_name  Optional. Check if specified notice is dismissed.
     *
     * @return bool|array
     */
    function get_dismissed_notices($notice_name = '')
    {
        $notices = $this->options['dismissed_notices'];

        if (empty($notice_name)) {
            return $notices;
        } else {
            if (empty($notices[$notice_name])) {
                return false;
            } else {
                return true;
            }
        }
    } // get_dismissed_notices


    /**
     * Retrieve available modules
     *
     * @return array
     */
    public function get_modules()
    {
        $modules = array();
        $modules['logo'] = array('name' => 'Logo', 'pro' => false);
        $modules['header'] = array('name' => 'Header', 'pro' => false);
        $modules['footer'] = array('name' => 'Footer', 'pro' => false);
        $modules['content'] = array('name' => 'Content', 'pro' => false);
        $modules['content2col'] = array('name' => 'Content 2 Column', 'pro' => true);
        $modules['content3col'] = array('name' => 'Content 3 Column', 'pro' => true);
        $modules['form'] = array('name' => 'Form', 'pro' => true);
        $modules['video'] = array('name' => 'Video', 'pro' => true);
        $modules['countdown'] = array('name' => 'Countdown', 'pro' => true);
        $modules['progressbar'] = array('name' => 'Progress Bar', 'pro' => true);
        $modules['social'] = array('name' => 'Social Icons', 'pro' => true);
        $modules['map'] = array('name' => 'Map', 'pro' => true);
        $modules['html'] = array('name' => 'Custom HTML', 'pro' => true);
        $modules['divider'] = array('name' => 'Divider', 'pro' => true);

        $modules = apply_filters('mtnc_modules_list', $modules);

        return $modules;
    }

    /**
     * Get meta part of plugin options
     *
     * @return array
     */
    public function get_meta()
    {
        return $this->options['meta'];
    } // get_meta

    public function get_fonts()
    {
        $fonts = array('Arial', 'Helvetica', 'Georgia', 'Times New Roman', 'Tahoma', 'Verdana', 'Geneva', 'ABeeZee', 'Abel', 'Abhaya Libre', 'Abril Fatface', 'Aclonica', 'Acme', 'Actor', 'Adamina', 'Advent Pro', 'Aguafina Script', 'Akronim', 'Aladin', 'Aldrich', 'Alef', 'Alegreya', 'Alegreya SC', 'Alegreya Sans', 'Alegreya Sans SC', 'Alex Brush', 'Alfa Slab One', 'Alice', 'Alike', 'Alike Angular', 'Allan', 'Allerta', 'Allerta Stencil', 'Allura', 'Almendra', 'Almendra Display', 'Almendra SC', 'Amarante', 'Amaranth', 'Amatic SC', 'Amethysta', 'Amiko', 'Amiri', 'Amita', 'Anaheim', 'Andada', 'Andika', 'Angkor', 'Annie Use Your Telescope', 'Anonymous Pro', 'Antic', 'Antic Didone', 'Antic Slab', 'Anton', 'Arapey', 'Arbutus', 'Arbutus Slab', 'Architects Daughter', 'Archivo', 'Archivo Black', 'Archivo Narrow', 'Aref Ruqaa', 'Arima Madurai', 'Arimo', 'Arizonia', 'Armata', 'Arsenal', 'Artifika', 'Arvo', 'Arya', 'Asap', 'Asap Condensed', 'Asar', 'Asset', 'Assistant', 'Astloch', 'Asul', 'Athiti', 'Atma', 'Atomic Age', 'Aubrey', 'Audiowide', 'Autour One', 'Average', 'Average Sans', 'Averia Gruesa Libre', 'Averia Libre', 'Averia Sans Libre', 'Averia Serif Libre', 'Bad Script', 'Bahiana', 'Baloo', 'Baloo Bhai', 'Baloo Bhaijaan', 'Baloo Bhaina', 'Baloo Chettan', 'Baloo Da', 'Baloo Paaji', 'Baloo Tamma', 'Baloo Tammudu', 'Baloo Thambi', 'Balthazar', 'Bangers', 'Barlow', 'Barlow Condensed', 'Barlow Semi Condensed', 'Barrio', 'Basic', 'Battambang', 'Baumans', 'Bayon', 'Belgrano', 'Bellefair', 'Belleza', 'BenchNine', 'Bentham', 'Berkshire Swash', 'Bevan', 'Bigelow Rules', 'Bigshot One', 'Bilbo', 'Bilbo Swash Caps', 'BioRhyme', 'BioRhyme Expanded', 'Biryani', 'Bitter', 'Black Ops One', 'Bokor', 'Bonbon', 'Boogaloo', 'Bowlby One', 'Bowlby One SC', 'Brawler', 'Bree Serif', 'Bubblegum Sans', 'Bubbler One', 'Buda', 'Buenard', 'Bungee', 'Bungee Hairline', 'Bungee Inline', 'Bungee Outline', 'Bungee Shade', 'Butcherman', 'Butterfly Kids', 'Cabin', 'Cabin Condensed', 'Cabin Sketch', 'Caesar Dressing', 'Cagliostro', 'Cairo', 'Calligraffitti', 'Cambay', 'Cambo', 'Candal', 'Cantarell', 'Cantata One', 'Cantora One', 'Capriola', 'Cardo', 'Carme', 'Carrois Gothic', 'Carrois Gothic SC', 'Carter One', 'Catamaran', 'Caudex', 'Caveat', 'Caveat Brush', 'Cedarville Cursive', 'Ceviche One', 'Changa', 'Changa One', 'Chango', 'Chathura', 'Chau Philomene One', 'Chela One', 'Chelsea Market', 'Chenla', 'Cherry Cream Soda', 'Cherry Swash', 'Chewy', 'Chicle', 'Chivo', 'Chonburi', 'Cinzel', 'Cinzel Decorative', 'Clicker Script', 'Coda', 'Coda Caption', 'Codystar', 'Coiny', 'Combo', 'Comfortaa', 'Maintenance', 'Concert One', 'Condiment', 'Content', 'Contrail One', 'Convergence', 'Cookie', 'Copse', 'Corben', 'Cormorant', 'Cormorant Garamond', 'Cormorant Infant', 'Cormorant SC', 'Cormorant Unicase', 'Cormorant Upright', 'Courgette', 'Cousine', 'Coustard', 'Covered By Your Grace', 'Crafty Girls', 'Creepster', 'Crete Round', 'Crimson Text', 'Croissant One', 'Crushed', 'Cuprum', 'Cutive', 'Cutive Mono', 'Damion', 'Dancing Script', 'Dangrek', 'David Libre', 'Dawning of a New Day', 'Days One', 'Dekko', 'Delius', 'Delius Swash Caps', 'Delius Unicase', 'Della Respira', 'Denk One', 'Devonshire', 'Dhurjati', 'Didact Gothic', 'Diplomata', 'Diplomata SC', 'Domine', 'Donegal One', 'Doppio One', 'Dorsa', 'Dosis', 'Dr Sugiyama', 'Duru Sans', 'Dynalight', 'EB Garamond', 'Eagle Lake', 'Eater', 'Economica', 'Eczar', 'El Messiri', 'Electrolize', 'Elsie', 'Elsie Swash Caps', 'Emblema One', 'Emilys Candy', 'Encode Sans', 'Encode Sans Condensed', 'Encode Sans Expanded', 'Encode Sans Semi Condensed', 'Encode Sans Semi Expanded', 'Engagement', 'Englebert', 'Enriqueta', 'Erica One', 'Esteban', 'Euphoria Script', 'Ewert', 'Exo', 'Exo 2', 'Expletus Sans', 'Fanwood Text', 'Farsan', 'Fascinate', 'Fascinate Inline', 'Faster One', 'Fasthand', 'Fauna One', 'Faustina', 'Federant', 'Federo', 'Felipa', 'Fenix', 'Finger Paint', 'Fira Mono', 'Fira Sans', 'Fira Sans Condensed', 'Fira Sans Extra Condensed', 'Fjalla One', 'Fjord One', 'Flamenco', 'Flavors', 'Fondamento', 'Fontdiner Swanky', 'Forum', 'Francois One', 'Frank Ruhl Libre', 'Freckle Face', 'Fredericka the Great', 'Fredoka One', 'Freehand', 'Fresca', 'Frijole', 'Fruktur', 'Fugaz One', 'GFS Didot', 'GFS Neohellenic', 'Gabriela', 'Gafata', 'Galada', 'Galdeano', 'Galindo', 'Gentium Basic', 'Gentium Book Basic', 'Geo', 'Geostar', 'Geostar Fill', 'Germania One', 'Gidugu', 'Gilda Display', 'Give You Glory', 'Glass Antiqua', 'Glegoo', 'Gloria Hallelujah', 'Goblin One', 'Gochi Hand', 'Gorditas', 'Goudy Bookletter 1911', 'Graduate', 'Grand Hotel', 'Gravitas One', 'Great Vibes', 'Griffy', 'Gruppo', 'Gudea', 'Gurajada', 'Habibi', 'Halant', 'Hammersmith One', 'Hanalei', 'Hanalei Fill', 'Handlee', 'Hanuman', 'Happy Monkey', 'Harmattan', 'Headland One', 'Heebo', 'Henny Penny', 'Herr Von Muellerhoff', 'Hind', 'Hind Guntur', 'Hind Madurai', 'Hind Siliguri', 'Hind Vadodara', 'Holtwood One SC', 'Homemade Apple', 'Homenaje', 'IBM Plex Mono', 'IBM Plex Sans', 'IBM Plex Sans Condensed', 'IBM Plex Serif', 'IM Fell DW Pica', 'IM Fell DW Pica SC', 'IM Fell Double Pica', 'IM Fell Double Pica SC', 'IM Fell English', 'IM Fell English SC', 'IM Fell French Canon', 'IM Fell French Canon SC', 'IM Fell Great Primer', 'IM Fell Great Primer SC', 'Iceberg', 'Iceland', 'Imprima', 'Inconsolata', 'Inder', 'Indie Flower', 'Inika', 'Inknut Antiqua', 'Irish Grover', 'Istok Web', 'Italiana', 'Italianno', 'Itim', 'Jacques Francois', 'Jacques Francois Shadow', 'Jaldi', 'Jim Nightshade', 'Jockey One', 'Jolly Lodger', 'Jomhuria', 'Josefin Sans', 'Josefin Slab', 'Joti One', 'Judson', 'Julee', 'Julius Sans One', 'Junge', 'Jura', 'Just Another Hand', 'Just Me Again Down Here', 'Kadwa', 'Kalam', 'Kameron', 'Kanit', 'Kantumruy', 'Karla', 'Karma', 'Katibeh', 'Kaushan Script', 'Kavivanar', 'Kavoon', 'Kdam Thmor', 'Keania One', 'Kelly Slab', 'Kenia', 'Khand', 'Khmer', 'Khula', 'Kite One', 'Knewave', 'Kotta One', 'Koulen', 'Kranky', 'Kreon', 'Kristi', 'Krona One', 'Kumar One', 'Kumar One Outline', 'Kurale', 'La Belle Aurore', 'Laila', 'Lakki Reddy', 'Lalezar', 'Lancelot', 'Lateef', 'Lato', 'League Script', 'Leckerli One', 'Ledger', 'Lekton', 'Lemon', 'Lemonada', 'Libre Barcode 128', 'Libre Barcode 128 Text', 'Libre Barcode 39', 'Libre Barcode 39 Extended', 'Libre Barcode 39 Extended Text', 'Libre Barcode 39 Text', 'Libre Baskerville', 'Libre Franklin', 'Life Savers', 'Lilita One', 'Lily Script One', 'Limelight', 'Linden Hill', 'Lobster', 'Lobster Two', 'Londrina Outline', 'Londrina Shadow', 'Londrina Sketch', 'Londrina Solid', 'Lora', 'Love Ya Like A Sister', 'Loved by the King', 'Lovers Quarrel', 'Luckiest Guy', 'Lusitana', 'Lustria', 'Macondo', 'Macondo Swash Caps', 'Mada', 'Magra', 'Maiden Orange', 'Maitree', 'Mako', 'Mallanna', 'Mandali', 'Manuale', 'Marcellus', 'Marcellus SC', 'Marck Script', 'Margarine', 'Marko One', 'Marmelad', 'Martel', 'Martel Sans', 'Marvel', 'Mate', 'Mate SC', 'Maven Pro', 'McLaren', 'Meddon', 'MedievalSharp', 'Medula One', 'Meera Inimai', 'Megrim', 'Meie Script', 'Merienda', 'Merienda One', 'Merriweather', 'Merriweather Sans', 'Metal', 'Metal Mania', 'Metamorphous', 'Metrophobic', 'Michroma', 'Milonga', 'Miltonian', 'Miltonian Tattoo', 'Mina', 'Miniver', 'Miriam Libre', 'Mirza', 'Miss Fajardose', 'Mitr', 'Modak', 'Modern Antiqua', 'Mogra', 'Molengo', 'Molle', 'Monda', 'Monofett', 'Monoton', 'Monsieur La Doulaise', 'Montaga', 'Montez', 'Montserrat', 'Montserrat Alternates', 'Montserrat Subrayada', 'Moul', 'Moulpali', 'Mountains of Christmas', 'Mouse Memoirs', 'Mr Bedfort', 'Mr Dafoe', 'Mr De Haviland', 'Mrs Saint Delafield', 'Mrs Sheppards', 'Mukta', 'Mukta Mahee', 'Mukta Malar', 'Mukta Vaani', 'Muli', 'Mystery Quest', 'NTR', 'Nanum Brush Script', 'Nanum Gothic', 'Nanum Gothic Coding', 'Nanum Myeongjo', 'Nanum Pen Script', 'Neucha', 'Neuton', 'New Rocker', 'News Cycle', 'Niconne', 'Nixie One', 'Nobile', 'Nokora', 'Norican', 'Nosifer', 'Nothing You Could Do', 'Noticia Text', 'Noto Sans', 'Noto Serif', 'Nova Cut', 'Nova Flat', 'Nova Mono', 'Nova Oval', 'Nova Round', 'Nova Script', 'Nova Slim', 'Nova Square', 'Numans', 'Nunito', 'Nunito Sans', 'Odor Mean Chey', 'Offside', 'Old Standard TT', 'Oldenburg', 'Oleo Script', 'Oleo Script Swash Caps', 'Open Sans', 'Open Sans Condensed', 'Oranienbaum', 'Orbitron', 'Oregano', 'Orienta', 'Original Surfer', 'Oswald', 'Over the Rainbow', 'Overlock', 'Overlock SC', 'Overpass', 'Overpass Mono', 'Ovo', 'Oxygen', 'Oxygen Mono', 'PT Mono', 'PT Sans', 'PT Sans Caption', 'PT Sans Narrow', 'PT Serif', 'PT Serif Caption', 'Pacifico', 'Padauk', 'Palanquin', 'Palanquin Dark', 'Pangolin', 'Paprika', 'Parisienne', 'Passero One', 'Passion One', 'Pathway Gothic One', 'Patrick Hand', 'Patrick Hand SC', 'Pattaya', 'Patua One', 'Pavanam', 'Paytone One', 'Peddana', 'Peralta', 'Permanent Marker', 'Petit Formal Script', 'Petrona', 'Philosopher', 'Piedra', 'Pinyon Script', 'Pirata One', 'Plaster', 'Play', 'Playball', 'Playfair Display', 'Playfair Display SC', 'Podkova', 'Poiret One', 'Poller One', 'Poly', 'Pompiere', 'Pontano Sans', 'Poppins', 'Port Lligat Sans', 'Port Lligat Slab', 'Pragati Narrow', 'Prata', 'Preahvihear', 'Press Start 2P', 'Pridi', 'Princess Sofia', 'Prociono', 'Prompt', 'Prosto One', 'Proza Libre', 'Puritan', 'Purple Purse', 'Quando', 'Quantico', 'Quattrocento', 'Quattrocento Sans', 'Questrial', 'Quicksand', 'Quintessential', 'Qwigley', 'Racing Sans One', 'Radley', 'Rajdhani', 'Rakkas', 'Raleway', 'Raleway Dots', 'Ramabhadra', 'Ramaraja', 'Rambla', 'Rammetto One', 'Ranchers', 'Rancho', 'Ranga', 'Rasa', 'Rationale', 'Ravi Prakash', 'Redressed', 'Reem Kufi', 'Reenie Beanie', 'Revalia', 'Rhodium Libre', 'Ribeye', 'Ribeye Marrow', 'Righteous', 'Risque', 'Roboto', 'Roboto Condensed', 'Roboto Mono', 'Roboto Slab', 'Rochester', 'Rock Salt', 'Rokkitt', 'Romanesco', 'Ropa Sans', 'Rosario', 'Rosarivo', 'Rouge Script', 'Rozha One', 'Rubik', 'Rubik Mono One', 'Ruda', 'Rufina', 'Ruge Boogie', 'Ruluko', 'Rum Raisin', 'Ruslan Display', 'Russo One', 'Ruthie', 'Rye', 'Sacramento', 'Sahitya', 'Sail', 'Saira', 'Saira Condensed', 'Saira Extra Condensed', 'Saira Semi Condensed', 'Salsa', 'Sanchez', 'Sancreek', 'Sansita', 'Sarala', 'Sarina', 'Sarpanch', 'Satisfy', 'Scada', 'Scheherazade', 'Schoolbell', 'Scope One', 'Seaweed Script', 'Secular One', 'Sedgwick Ave', 'Sedgwick Ave Display', 'Sevillana', 'Seymour One', 'Shadows Into Light', 'Shadows Into Light Two', 'Shanti', 'Share', 'Share Tech', 'Share Tech Mono', 'Shojumaru', 'Short Stack', 'Shrikhand', 'Siemreap', 'Sigmar One', 'Signika', 'Signika Negative', 'Simonetta', 'Sintony', 'Sirin Stencil', 'Six Caps', 'Skranji', 'Slabo 13px', 'Slabo 27px', 'Slackey', 'Smokum', 'Smythe', 'Sniglet', 'Snippet', 'Snowburst One', 'Sofadi One', 'Sofia', 'Sonsie One', 'Sorts Mill Goudy', 'Source Code Pro', 'Source Sans Pro', 'Source Serif Pro', 'Space Mono', 'Special Elite', 'Spectral', 'Spectral SC', 'Spicy Rice', 'Spinnaker', 'Spirax', 'Squada One', 'Sree Krushnadevaraya', 'Sriracha', 'Stalemate', 'Stalinist One', 'Stardos Stencil', 'Stint Ultra Condensed', 'Stint Ultra Expanded', 'Stoke', 'Strait', 'Sue Ellen Francisco', 'Suez One', 'Sumana', 'Sunshiney', 'Supermercado One', 'Sura', 'Suranna', 'Suravaram', 'Suwannaphum', 'Swanky and Moo Moo', 'Syncopate', 'Tangerine', 'Taprom', 'Tauri', 'Taviraj', 'Teko', 'Telex', 'Tenali Ramakrishna', 'Tenor Sans', 'Text Me One', 'The Girl Next Door', 'Tienne', 'Tillana', 'Timmana', 'Tinos', 'Titan One', 'Titillium Web', 'Trade Winds', 'Trirong', 'Trocchi', 'Trochut', 'Trykker', 'Tulpen One', 'Ubuntu', 'Ubuntu Condensed', 'Ubuntu Mono', 'Ultra', 'Uncial Antiqua', 'Underdog', 'Unica One', 'UnifrakturCook', 'UnifrakturMaguntia', 'Unkempt', 'Unlock', 'Unna', 'VT323', 'Vampiro One', 'Varela', 'Varela Round', 'Vast Shadow', 'Vesper Libre', 'Vibur', 'Vidaloka', 'Viga', 'Voces', 'Volkhov', 'Vollkorn', 'Vollkorn SC', 'Voltaire', 'Waiting for the Sunrise', 'Wallpoet', 'Walter Turncoat', 'Warnes', 'Wellfleet', 'Wendy One', 'Wire One', 'Work Sans', 'Yanone Kaffeesatz', 'Yantramanav', 'Yatra One', 'Yellowtail', 'Yeseva One', 'Yesteryear', 'Yrsa', 'Zeyada', 'Zilla Slab', 'Zilla Slab Highlight');
        return $fonts;
    }

    /**
     * Get options part of plugin options
     *
     * @return array
     */
    public function get_options()
    {
        return $this->options['options'];
    } // get_options

    /**
     * Get all plugin options
     *
     * @return array
     */
    public function get_all_options()
    {
        return $this->options;
    } // get_all_options

    /**
     * Update specified plugin options key
     *
     * @param string  $key   Data to save.
     * @param string  $data  Option key.
     *
     * @return bool
     */
    public function update_options($key, $data)
    {
        if (false === in_array($key, array('meta', 'dismissed_notices', 'options'))) {
            user_error('Unknown options key.', E_USER_ERROR);
            return false;
        }

        $this->options[$key] = $data;
        $tmp = update_option('mtnc-options', $this->options);

        return $tmp;
    } // set_options

    /**
     * Add plugin menu entry under Tools menu
     *
     * @return null
     */
    public function admin_menu()
    {
        add_menu_page('Maintenance', 'Maintenance', 'manage_options', 'maintenance', array($this, 'plugin_page'), $this->plugin_url . 'img/icon-small.png');
    } // admin_menu


    /**
     * Returns all WP pointers
     *
     * @return array
     */
    function get_pointers()
    {
        $pointers = array();

        $pointers['welcome'] = array('target' => '#toplevel_page_maintenance', 'edge' => 'left', 'align' => 'right', 'content' => 'Thank you for installing the <b style="font-weight: 800;">Maintenance</b> plugin!<br>Open <a href="' . admin_url('admin.php?page=maintenance') . '">Maintenance</a> to access Maintenance settings.');

        return $pointers;
    } // get_pointers


    /**
     * Enqueue CSS and JS files
     *
     * @return null
     */
    public function admin_enqueue_scripts($hook)
    {
        $options = $this->get_options();
        $pointers = $this->get_pointers();

        $dismissed_notices = $this->get_dismissed_notices();

        foreach ($dismissed_notices as $notice_name => $tmp) {
            if ($tmp) {
                unset($pointers[$notice_name]);
            }
        }

        if (!empty($pointers) && !$this->is_plugin_page() && current_user_can('manage_options')) {
            $pointers['_nonce_dismiss_pointer'] = wp_create_nonce('maintenance_dismiss_notice');

            wp_enqueue_style('wp-pointer');

            wp_enqueue_script('maintenance-pointers', $this->plugin_url . 'js/pointers.js', array('jquery'), $this->version, true);
            wp_enqueue_script('wp-pointer');
            wp_localize_script('wp-pointer', 'mtnc_pointers', $pointers);
        }

        if (!$this->is_plugin_page()) {
            return;
        }

        $this->dismiss_notice('welcome');

        $js_localize = array(
            'undocumented_error' => esc_html__('An undocumented error has occurred. Please refresh the page and try again.', 'maintenance'),
            'documented_error' => esc_html__('An error has occurred.', 'maintenance'),
            'plugin_name' => 'Maintenance',
            'url' => MTNC_URL,
            'icon_url' => $this->plugin_url . 'img/loader-icon.png',
            'nonce_action' => wp_create_nonce('mtnc_action'),
            'theme_global' => $options['theme_global'],
            'theme' => $this->theme,
            'theme_id' => $this->theme_id,
            'fonts' => $this->get_fonts(),
            'max_upload_size' => wp_max_upload_size(),
            'settings_url' => admin_url('admin.php?page=maintenance'),
            'is_plugin_page' => $this->is_plugin_page(),
            'wpfssl_install_url' => add_query_arg(
                array(
                    'action' => 'mtnc_install_wpfssl',
                    '_wpnonce' => wp_create_nonce('install_wpfssl'),
                    'rnd' => wp_rand()
                ),
                admin_url('admin.php')
            ),
            'wpcaptcha_install_url' => add_query_arg(
                array(
                    'action' => 'mtnc_install_wpcaptcha',
                    '_wpnonce' => wp_create_nonce('install_wpcaptcha'),
                    'rnd' => wp_rand()
                ),
                admin_url('admin.php')
            ),
            'weglot_install_url' => add_query_arg(
                array(
                    'action' => 'mtnc_install_weglot',
                    '_wpnonce' => wp_create_nonce('install_weglot'),
                    'rnd' => wp_rand()
                ),
                admin_url('admin.php')
            ),
            'weglot_dialog_upsell_title' => '<img alt="' . esc_attr__('Weglot', 'maintenance') . '" title="' . esc_attr__('Weglot', 'maintenance') . '" src="' . MTNC_URI . 'img/weglot-logo-white.png' . '">',
        );

        if ($this->is_plugin_page()) {
            wp_enqueue_style('maintenance-admin', $this->plugin_url . 'css/maintenance-admin.css', array(), $this->version);
            wp_enqueue_script('jquery-ui-tabs');
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('jquery-ui-position');
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('wp-jquery-ui-dialog');
            wp_enqueue_script('jquery-ui-dialog');

            wp_enqueue_style('maintenance-summernote', $this->plugin_url . 'css/summernote-lite.min.css', array(), $this->version);
            wp_enqueue_script('maintenance-summernote', $this->plugin_url . 'js/summernote-lite.min.js', array('jquery'), $this->version, true); //WYSIWYG Module Editor
            wp_enqueue_style('maintenance-fonticonpicker', $this->plugin_url . 'css/jquery.fonticonpicker.min.css', array(), $this->version);

            wp_enqueue_style('maintenance-font-roboto', 'https://fonts.bunny.net/css2?family=Ubuntu:ital,wght@0,300;0,400;0,500;0,700;1,300;1,400;1,500;1,700&display=swap', array(), $this->version);
            wp_enqueue_style('maintenance-material-icons', 'https://fonts.googleapis.com/icon?family=Material+Icons', array(), $this->version);

            wp_enqueue_style('maintenance-sweetalert2', $this->plugin_url . 'css/sweetalert2.min.css', array(), $this->version);
            wp_enqueue_style('maintenance-select2', $this->plugin_url . 'css/select2.css', array(), $this->version);

            wp_enqueue_script('maintenance-sweetalert2', $this->plugin_url . 'js/maintenance-sweetalert2.min.js', array('jquery'), $this->version, true);
            wp_enqueue_script('maintenance-select2', $this->plugin_url . 'js/select2.min.js', array('jquery'), $this->version, true);
            wp_enqueue_script('maintenance-colorpicker', $this->plugin_url . 'js/colorpicker/jscolor.js', false, $this->version, true);
            wp_enqueue_script('maintenance', $this->plugin_url . 'js/maintenance-admin.js', array('jquery'), $this->version, true);
            wp_enqueue_script('maintenance-fonticonpicker', $this->plugin_url . 'js/jquery.fonticonpicker.min.js', array('jquery'), $this->version, true);
            wp_enqueue_script('maintenance-ace-editor', $this->plugin_url . 'js/editor/ace.js', false, $this->version, true); // Custom CSS Editor
            wp_enqueue_script('maintenance-webfonts', $this->plugin_url . 'js/webfont.js', false, $this->version, true); //Visual font picker preview

            wp_localize_script('maintenance', 'mtnc', $js_localize);
            wp_enqueue_media();

            wp_dequeue_style('uiStyleSheet');
            wp_dequeue_style('wpcufpnAdmin');
            wp_dequeue_style('unifStyleSheet');
            wp_dequeue_style('wpcufpn_codemirror');
            wp_dequeue_style('wpcufpn_codemirrorTheme');
            wp_dequeue_style('collapse-admin-css');
            wp_dequeue_style('jquery-ui-css');
            wp_dequeue_style('tribe-common-admin');
            wp_dequeue_style('file-manager__jquery-ui-css');
            wp_dequeue_style('file-manager__jquery-ui-css-theme');
            wp_dequeue_style('wpmegmaps-jqueryui');
            wp_dequeue_style('wp-botwatch-css');
        }
    } // admin_enqueue_scripts

    function frontend_login()
    {
        global $wp_query, $error;
        $mt_options   = $this->get_options();
        $user_connect = false;
        if (!is_array($wp_query->query_vars)) {
            $wp_query->query_vars = array();
        }
        $error_message  = $user_login = $user_pass = $error = '';
        $class_login    = 'user-icon';
        $class_password = 'pass-icon';

        if (isset($_POST['is_custom_login'])) {
            $user_login = '';
            if (isset($_POST['log']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mtnc_login_check'] ?? '')), 'mtnc_login')) {
                $user_login = sanitize_user(wp_unslash($_POST['log']));
            }
            $user_login = sanitize_user($user_login);
            $user_pass  = '';
            if (isset($_POST['pwd']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mtnc_login_check'] ?? '')), 'mtnc_login')) {
                $user_pass = trim(sanitize_text_field(wp_unslash($_POST['pwd'])));
            }
            $access                  = array();
            $access['user_login']    = esc_attr($user_login);
            $access['user_password'] = $user_pass;
            $access['remember']      = true;

            $user         = null;
            $user         = new WP_User(0, $user_login);

            if (is_ssl()) {
                $ssl = true;
            } else {
                $ssl = false;
            }

            $user_connect = wp_signon($access, $ssl);
            if (is_wp_error($user_connect)) {
                if ($user_connect->get_error_code() === 'invalid_username') {
                    $error_message = esc_html__('Login is incorrect!', 'maintenance');

                    $class_login    = 'error';
                    $class_password = 'error';
                } elseif ($user_connect->get_error_code() === 'incorrect_password') {
                    $error_message  = esc_html__('Password is incorrect!', 'maintenance');
                    $class_password = 'error';
                } else {
                    $error_message  = esc_html__('Login and password are incorrect!', 'maintenance');
                    $class_login    = 'error';
                    $class_password = 'error';
                }
            } else {
                wp_safe_redirect(home_url('/'));
                exit;
            }
        }

        return array($error_message, $class_login, $class_password, $user_login);
    } // frontend_login


    public function admin_bar_style()
    {
        $options = $this->get_options();

        // admin bar has to be anabled, user an admin and custom filter true
        if ($options['disable_toolbar_menu'] || false === is_admin_bar_showing() || false === current_user_can('manage_options')) {
            return;
        }

        // no sense in loading a new CSS file for 2 lines of CSS
        $custom_css = '<style type="text/css">#wpadminbar i.mtnc-status-dot { font-size: 17px; margin-top: -7px; color: #02ca02; height: 17px; display: inline-block; } #wpadminbar i.mtnc-status-dot-enabled { color: #64bd63; } #wpadminbar i.mtnc-status-dot-disabled { color: #FE2D2D; } #wpadminbar #mtnc-status-wrapper { display: inline; border: 1px solid rgba(240,245,250,0.7); padding: 0; margin: 0 0 0 5px; background: rgb(35, 40, 45); } #wpadminbar .mtnc-status-btn { padding: 0 7px; color: #fff; } #wpadminbar #mtnc-status-wrapper.off #mtnc-status-off { background: #FE2D2D;} #wpadminbar #mtnc-status-wrapper.on #mtnc-status-on { background: #64bd63; }#wp-admin-bar-mtnc img.logo { height: 17px; margin-bottom: 4px; padding-right: 3px; } #wp-admin-bar-mtnc a img { height: 17px; margin-bottom: -2px; padding-right: 3px; display:inline-block; } #wpadminbar #wp-admin-bar-mtnc-status .ab-empty-item { margin-bottom: 2px; }</style>';

        self::wp_kses_wf($custom_css);
    } // admin_bar_style


    // add admin bar menu and status
    public function admin_bar()
    {
        global $wp_admin_bar;
        $options = $this->get_options();

        // only show to admins
        if ($options['disable_toolbar_menu'] || false === current_user_can('manage_options')) {
            return;
        }

        $plugin_name = 'Maintenance';
        $plugin_logo = MTNC_URL . '/img/icon-transparent.png';
        $request_uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? ''));
        if ($options['status'] == '1') {
            $main_label = '<img src="' . $plugin_logo . '" alt="Maintenance mode is enabled" title="Maintenance mode is enabled"><span class="ab-label">' . $plugin_name . ' <i class="mtnc-status-dot mtnc-status-dot-enabled">&#9679;</i></span>';
            $class = 'mtnc-enabled';
            $action_url = add_query_arg(array('action' => 'mtnc_change_status', 'new_status' => 'disabled', 'redirect' => urlencode($request_uri)), admin_url('admin.php'));
            $action_url = wp_nonce_url($action_url, 'mtnc_change_status');
            $action = 'Maintenance mode';
            $action .= '<a href="' . $action_url . '" id="mtnc-status-wrapper" class="on"><span id="mtnc-status-off" class="mtnc-status-btn">OFF</span><span id="mtnc-status-on" class="mtnc-status-btn">ON</span></a>';
        } else {
            $main_label = '<img src="' . $plugin_logo . '" alt="Maintenance mode is enabled" title="Maintenance mode is enabled"><span class="ab-label">' . $plugin_name . ' <i class="mtnc-status-dot mtnc-status-dot-disabled">&#9679;</i></span>';
            $class = 'mtnc-disabled';
            $action_url = add_query_arg(array('action' => 'mtnc_change_status', 'new_status' => 'enabled', 'redirect' => urlencode($request_uri)), admin_url('admin.php'));
            $action_url = wp_nonce_url($action_url, 'mtnc_change_status');
            $action = 'Maintenance mode';
            $action .= '<a href="' . $action_url . '" id="mtnc-status-wrapper" class="off"><span id="mtnc-status-off" class="mtnc-status-btn">OFF</span><span id="mtnc-status-on" class="mtnc-status-btn">ON</span></a>';
        }

        $wp_admin_bar->add_menu(array(
            'parent' => '',
            'id' => 'mtnc',
            'title' => $main_label,
            'href' => admin_url('admin.php?page=maintenance'),
            'meta' => array('class' => $class),
        ));
        $wp_admin_bar->add_node(array(
            'id' => 'mtnc-status',
            'title' => $action,
            'href' => false,
            'parent' => 'mtnc',
        ));
        $wp_admin_bar->add_node(array(
            'id' => 'mtnc-preview',
            'title' => 'Preview',
            'href' => home_url('/') . '?maintenance-preview',
            'parent' => 'mtnc',
            'meta' => array('target' => '_blank'),
        ));
        $wp_admin_bar->add_node(array(
            'id' => 'mtnc-settings',
            'title' => 'Settings',
            'href' => admin_url('admin.php?page=maintenance'),
            'parent' => 'mtnc',
        ));
    } // admin_bar

    /**
     * Perform an action via AJAX
     *
     * @return bool
     */
    public function ajax_action()
    {
        check_ajax_referer('mtnc_action');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You are not allowed to run this action.', 'maintenance'));
        }

        $options = $this->get_options();

        $action = trim(wp_unslash($_REQUEST['mtnc_action'] ?? '')); //phpcs:ignore
        $extra_data = wp_unslash($_REQUEST['extra_data'] ?? ''); //phpcs:ignore
        if (is_string($extra_data)) {
            parse_str($extra_data, $extra_data);
        } elseif (is_array($extra_data)) {
            $extra_data = array_slice($extra_data, 0, 20);
        } else {
            $extra_data = false;
        }

        switch ($action) {
            case 'save':
                $new_options = array(
                    'theme_global' => $options['theme_global'],
                    'themes' => $options['themes'],
                    'status' => @absint($extra_data['mtnc_status']),
                    'mode' => 'layout',
                    'mtnc_page' => 0,
                    //SEO
                    'title' => strip_tags($extra_data['title']),
                    'analytics' => strip_tags($this->convert_ga($extra_data['analytics'])),
                    //Access
                    'per_url_settings' => wp_strip_all_tags($extra_data['per_url_settings']),
                    'per_url_enable_disable' => wp_strip_all_tags($extra_data['per_url_enable_disable']),
                    'login_button' => @absint($extra_data['login_button']),
                    'wplogin_button_tooltip' => wp_strip_all_tags($extra_data['wplogin_button_tooltip']),
                );

                $new_options['theme_global'] = sanitize_text_field(wp_unslash($_REQUEST['theme_global'] ?? ''));

                //theme JSON will get broken by sanitization through wp_kses_post or normal functions, fields will be escaped when rendering on front-end
                $theme = $_REQUEST['theme']; //phpcs:ignore
                $theme_id = sanitize_text_field(wp_unslash($_REQUEST['theme_id'] ?? ''));
                $theme_new = sanitize_text_field(wp_unslash($_REQUEST['theme_new'] ?? ''));
                $theme_name = sanitize_text_field(wp_unslash($_REQUEST['theme_name'] ?? ''));

                if (isset($_REQUEST['theme_new']) && $theme_new == true) {
                    $theme_id = md5(time() . $theme_name);
                }

                $new_options['themes'][$theme_id] = json_decode(stripslashes($theme));
                $new_options['themes'][$theme_id]->name = $theme_name;

                //Design General
                if (isset($extra_data['theme_thumbnail'])) {
                    $new_options['themes'][$theme_id]->theme_thumbnail = wp_strip_all_tags($extra_data['theme_thumbnail']);
                }
                if (isset($extra_data['theme_status'])) {
                    $new_options['themes'][$theme_id]->theme_status = wp_strip_all_tags($extra_data['theme_status']);
                }

                $new_options['themes'][$theme_id]->body_font_size = wp_strip_all_tags($extra_data['body_font_size']);
                $new_options['themes'][$theme_id]->body_font_color = wp_strip_all_tags($extra_data['body_font_color']);
                $new_options['themes'][$theme_id]->body_font = wp_strip_all_tags($extra_data['body_font']);
                $new_options['themes'][$theme_id]->body_link_color = wp_strip_all_tags($extra_data['body_link_color']);
                $new_options['themes'][$theme_id]->body_link_hover_color = wp_strip_all_tags($extra_data['body_link_hover_color']);

                //Design General
                $new_options['themes'][$theme_id]->background_type = wp_strip_all_tags($extra_data['background_type']);
                $new_options['themes'][$theme_id]->background_video = wp_strip_all_tags($extra_data['background_video']);
                $new_options['themes'][$theme_id]->background_video_fallback = isset($extra_data['background_video_fallback']) ? wp_strip_all_tags($extra_data['background_video_fallback']) : '';
                $new_options['themes'][$theme_id]->background_video_filter = wp_strip_all_tags($extra_data['background_video_filter']);
                $new_options['themes'][$theme_id]->background_size_opt = wp_strip_all_tags($extra_data['background_size_opt']);
                $new_options['themes'][$theme_id]->background_position = wp_strip_all_tags($extra_data['background_position']);
                $new_options['themes'][$theme_id]->background_image_filter = wp_strip_all_tags($extra_data['background_image_filter']);
                $new_options['themes'][$theme_id]->background_image = wp_strip_all_tags($extra_data['background_image']);
                $new_options['themes'][$theme_id]->background_blur = (int)$extra_data['background_blur'];
                $new_options['themes'][$theme_id]->login_background_color = wp_strip_all_tags($extra_data['login_background_color']);


                $new_options['themes'][$theme_id]->preloader_background_image = wp_strip_all_tags($extra_data['preloader_background_image']);

                $new_options['themes'][$theme_id]->background_color = wp_strip_all_tags($extra_data['background_color']);
                $new_options['themes'][$theme_id]->content_overlay = isset($extra_data['content_overlay']) ? wp_strip_all_tags($extra_data['content_overlay']) : '';
                $new_options['themes'][$theme_id]->content_width = (int) $extra_data['content_width'];
                $new_options['themes'][$theme_id]->content_overlay_color = wp_strip_all_tags($extra_data['content_overlay_color']);
                $new_options['themes'][$theme_id]->content_overlay_shadow_color = wp_strip_all_tags($extra_data['content_overlay_shadow_color']);
                $new_options['themes'][$theme_id]->content_position = wp_strip_all_tags($extra_data['content_position']);
                $new_options['themes'][$theme_id]->modules_spacing = (int) $extra_data['modules_spacing'];
                $new_options['themes'][$theme_id]->custom_css = wp_strip_all_tags($extra_data['custom_css']);


                $this->update_options('options', $new_options);
                wp_send_json_success(array('theme_id' => $theme_id, 'theme' => $new_options['themes'][$theme_id]));
                break;
        }
    }


    /**
     * Test if we're on Maintenance's admin page
     *
     * @return bool
     */
    public function is_plugin_page()
    {
        if (!function_exists('get_current_screen')) {
            return false;
        }

        $current_screen = get_current_screen();

        if (!empty($current_screen) && $current_screen->id == 'toplevel_page_maintenance') {
            return true;
        } else {
            return false;
        }
    } // is_plugin_page

    /**
     * Main/starter function for displaying plugin's admin page
     *
     * @return null
     */
    public function plugin_page()
    {
        $options = $this->get_options();
        // double check for admin privileges
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sorry, you are not allowed to access this page.', 'maintenance'));
        }

        echo '<div class="wrap">
            <form class="mtnc-body mtnc-clearfix">
            <header>
                <div class="mtnc-header mtnc-clearfix">
                    <img src="' . esc_html(MTNC_URL . '/img/logo.png') . '" id="logo-icon" class="mtnc-logo" title="Maintenance" alt="Maintenance">';

        echo '<div id="header-right">
                            <div id="header-status" title="Click to change Maintenance status">
                                <label for="mtnc_status">Maintenance</label>

                                <div class="onoffswitch">
                                    <input type="checkbox" id="mtnc_status" name="mtnc_status" class="onoffswitch-checkbox" ' . checked($options['status'], 1, false) . ' value="1">
                                    <label class="onoffswitch-label" for="mtnc_status"></label>
                                </div>
                            </div>
                        </div>';

        echo '</div>
            </header>';

        $mtnc_notice = get_transient('mtnc_notice_' . get_current_user_id());
        delete_transient('mtnc_notice_' . get_current_user_id());
        if ($mtnc_notice !== false) {
            self::wp_kses_wf($mtnc_notice);
        }

        echo '<div id="loading-tabs"><img class="mtnc_rotating" src="' . esc_url($this->plugin_url . 'img/loader-icon.png') . '" alt="Loading. Please wait." title="Loading. Please wait."></div>';

        echo '<div id="mtnc-tabs-wrapper">';
        // Tab ID should be level1 or level1-level2 separated by dash, for example "welcome" or "design-background"
        echo '<div class="mtnc-form-body">
            <nav>
                <div class="mtnc-float-left">
                    <div class="mtnc-mobile-menu"></div>
                    <ul class="mtnc-main-menu">';

        echo '<li><a href="#themes-premade"><span class="material-icons">format_paint</span>' . esc_html__('Themes', 'maintenance') . '</a>
                </li>
                <li><a href="#design"><span class="material-icons">brush</span>' . esc_html__('Design', 'maintenance') . '</a>
                    <div class="mtnc-submenu">
                        <a title="Page Settings" href="#design">General</a>
                        <a title="Layout" href="#design-layout">Layout</a>
                                    <a title="Background" href="#design-background">Background</a>
                        <a title="Custom CSS" href="#design-css">Custom CSS</a>
                    </div>
                </li>
                <li><a href="#seo"><span class="material-icons">insights</span>' . esc_html__('SEO', 'maintenance') . '</a></li>
                <li><a href="#access"><span class="material-icons">lock</span>' . esc_html__('Access', 'maintenance') . '</a>
                    <div class="mtnc-submenu">
                        <a title="Access Settings" href="#access">Settings</a>
                        <a title="Layout" href="#access-password">Password</a>
                    </div>
                </li>
                <li><a href="#advanced"><span class="material-icons">settings</span>' . esc_html__('Advanced', 'maintenance') . '</a></li>
                <li class="pro-menu"><a href="#" data-pro-feature="main-menu" class="open-pro-dialog"><span class="material-icons">star</span>PRO</a></li>';
        echo '</ul>
                </div>
            </nav>';

        echo '<div class="mtnc-float-right">';


        echo '<div style="display: none;" class="mtnc-tab" id="themes-premade">';
        $this->tab_themes_premade();
        echo '</div>';

        echo '<div style="display: none;" class="mtnc-tab" id="access">';
        $this->tab_access();
        echo '</div>';

        echo '<div style="display: none;" class="mtnc-tab" id="access-password">';
        $this->tab_access_password();
        echo '</div>';

        echo '<div style="display: none;" class="mtnc-tab" id="design">';
        $this->tab_design_general();
        echo '</div>';

        echo '<div style="display: none;" class="mtnc-tab" id="design-layout">';
        $this->tab_design_layout();
        echo '</div>';

        echo '<div style="display: none;" class="mtnc-tab" id="design-background">';
        $this->tab_design_background();
        echo '</div>';

        echo '<div style="display: none;" class="mtnc-tab" id="design-css">';
        $this->tab_design_css();
        echo '</div>';

        echo '<div style="display: none;" class="mtnc-tab" id="seo">';
        $this->tab_seo();
        echo '</div>';

        echo '<div style="display: none;" class="mtnc-tab" id="advanced">';
        $this->tab_advanced();
        echo '</div>';


        echo '</div>'; // tabs

        echo '</div>'; // mtnc-form-body


        echo '</div>'; // mtnc-tabs-wrapper

        echo '<div id="mtnc-sidebar-wrapper">';

        echo '<div class="sidebar-box pro-ad-box">
                <p class="textcenter"><a href="https://wpmaintenancemode.com/?ref=eps-free-sidebar-box" target="_blank"><img src="' . esc_url(MTNC_URI . 'img/logo.png') . '" alt="WP Maintenance PRO" title="WP Maintenance PRO"></a><br><b>PRO version</b> is here! Grab the discount - <b>all prices are LIFETIME!</b></p>

                <ul class="plain-list">
                    <li>Use 200+ Awesome Themes</li>
                    <li>Unlock all Page Builder Elements</li>
                    <li>8.5+ million Premium Images</li>
                    <li>Easy Access for Clients through Access links</li>
                    <li>Licenses &amp; Sites Manager (remote SaaS dashboard)</li>
                    <li>White-label Mode + Complete Plugin Rebranding</li>
                    <li>Email support from plugin developers</li>
                    </ul>

               <p class="textcenter"><a href="#" class="open-pro-dialog button button-buy" data-pro-feature="sidebar-box">Get PRO Now</a></p>
             </div>';

        if (!defined('WPCAPTCHA_PLUGIN_FILE')) {
            echo '<div class="sidebar-box pro-ad-box" id="promo-wpcaptcha">';
            echo '<p class="textcenter"><a href="#" class="textcenter install-wpcaptcha"><img style="max-width: 90%;" src="' . esc_url(MTNC_URI . 'img/wp-captcha-logo.png') . '" alt="Advanced Google reCAPTCHA" title="Advanced Google reCAPTCHA"></a></p>';
            echo '<p class="textcenter"><br><a href="#" class="install-wpcaptcha button button-primary">Install &amp; activate the free Advanced Google reCAPTCHA</a></p>';
            echo '<p><a href="https://wordpress.org/plugins/advanced-google-recaptcha/" target="_blank">Advanced Google reCAPTCHA</a> is a free WP plugin that protects your site from various bad actors. It\'s maintained by the same team as this Maintenance plugin. It has <b>+200,000 users, 5-star rating</b>, and is hosted on the official WP repository.</p>';
            echo '</div>';
        }

        if (!$this->is_weglot_active()) {
            echo '<div class="sidebar-box pro-ad-box" id="promo-weglot">';
            echo '<h3>50% of your customers don\'t speak english</h3>';
            echo '<p>55% of online visitors prefer to browse in their mother tongue. If you have an audience speaking multiple languages, making your website multilingual is a must-have. To instantly translate your website and your maintenance page.</p>';
            echo '<p class="textcenter"><br><a href="#" class="button button-primary open-weglot-upsell">Install the Weglot Translate freemium plugin</a></p>';
            echo '</div>';
        }

        if (0 && !defined('WPFSSL_OPTIONS_KEY')) {
            echo '<div class="sidebar-box pro-ad-box" id="promo-wpfssl">';
            echo '<h3>Solve all SSL problems with the free WP Force SSL plugin</h3>';
            echo '<p class="textcenter"><a href="#" class="textcenter install-wpfssl"><img style="max-width: 90%;" src="' . esc_url(MTNC_URI . 'img/wp-force-ssl-logo.png') . '" alt="WP Force SSL" title="WP Force SSL"></a></p>';
            echo '<p class="textcenter"><br><a href="#" class="install-wpfssl button button-primary">Install &amp; activate the free WP Force SSL plugin</a></p>';
            echo '<p><a href="https://wordpress.org/plugins/wp-force-ssl/" target="_blank">WP Force SSL</a> is a free WP plugin maintained by the same team as this Maintenance plugin. It has <b>+180,000 users, 5-star rating</b>, and is hosted on the official WP repository.</p>';
            echo '</div>';
        }

        echo '<div class="sidebar-box pro-ad-box" id="promo-review2">';
            echo '<h3>Help us keep the plugin free &amp; maintained</h3>';
            echo '<p><b>Your review means a lot!</b> Please help us spread the word so that others know this plugin is free and well maintained! Thank you very much for <a href="https://wordpress.org/support/plugin/maintenance/reviews/#new-post" target="_blank">reviewing the Maintanance plugin with ★★★★★ stars</a>!</p>';
            echo '<p><a href="https://wordpress.org/support/plugin/maintenance/reviews/#new-post" target="_blank" class="button button-primary">Leave a Review</a> &nbsp;&nbsp; <a href="#" class="hide-review-box2">I already left a review ;)</a></p>';
        echo '</div>';

        echo '</div>'; // mtnc-sidebar-wrapper


        echo '</form>'; // mtnc-body

        echo '</div>'; // wrap

        self::wp_kses_wf($this->pro_dialog());
    } // plugin_page

    function is_weglot_active()
    {
        if (!function_exists('is_plugin_active') || !function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (is_plugin_active('weglot/weglot.php')) {
            $weglot_info = get_plugin_data(ABSPATH . 'wp-content/plugins/weglot/weglot.php');
            if (version_compare($weglot_info['Version'], '2.5', '<')) {
                return false;
            } else {
                return true;
            }
        } else {
            return false;
        }
    } // mtcn_is_weglot_active

    // check if Weglot is completely set up
    function is_weglot_setup()
    {
        if (!$this->is_weglot_active()) {
            return false;
        }

        $active_languages = weglot_get_destination_languages();
        if (!empty($active_languages)) {
            return true;
        } else {
            return false;
        }
    } // is_weglot_setup

    function pro_dialog()
    {
        $out = '';

        $out .= '<div id="mtnc-pro-dialog" style="display: none;" title="WP Maintenance PRO is here!"><span class="ui-helper-hidden-accessible"><input type="text"/></span>';

        $out .= '<div class="center logo"><a href="https://wpmaintenancemode.com/?ref=mtnc-free-pricing-table" target="_blank"><img src="' . MTNC_URI . 'img/logo.png' . '" alt="WP Maintenance PRO" title="WP Maintenance PRO"></a><br>';

        $out .= '<span>Limited PRO Discount - <b>all prices are LIFETIME</b>! Pay once &amp; use forever!</span>';
        $out .= '</div>';

        $out .= '<table id="mtnc-pro-table">';
        $out .= '<tr>';
        $out .= '<td class="center">Lifetime Personal License</td>';
        $out .= '<td class="center">Lifetime Team License</td>';
        $out .= '<td class="center">Lifetime Agency License</td>';
        $out .= '</tr>';

        $out .= '<tr class="prices">';
        $out .= '<td class="center"><del>$49 /year</del><br><span>$69</span> <b>/lifetime</b></td>';
        $out .= '<td class="center"><del>$89 /year</del><br><span>$79</span> <b>/lifetime</b></td>';
        $out .= '<td class="center"><del>$199 /year</del><br><span>$119</span> <b>/lifetime</b></td>';
        $out .= '</tr>';

        $out .= '<tr>';
        $out .= '<td><span class="dashicons dashicons-yes"></span><b>1 Site License</b></td>';
        $out .= '<td><span class="dashicons dashicons-yes"></span><b>5 Sites License</b></td>';
        $out .= '<td><span class="dashicons dashicons-yes"></span><b>100 Sites License</b></td>';
        $out .= '</tr>';

        $out .= '<tr>';
        $out .= '<td><span class="dashicons dashicons-yes"></span>All Plugin Features</td>';
        $out .= '<td><span class="dashicons dashicons-yes"></span>All Plugin Features</td>';
        $out .= '<td><span class="dashicons dashicons-yes"></span>All Plugin Features</td>';
        $out .= '</tr>';

        $out .= '<tr>';
        $out .= '<td><span class="dashicons dashicons-yes"></span>Lifetime Updates &amp; Support</td>';
        $out .= '<td><span class="dashicons dashicons-yes"></span>Lifetime Updates &amp; Support</td>';
        $out .= '<td><span class="dashicons dashicons-yes"></span>Lifetime Updates &amp; Support</td>';
        $out .= '</tr>';

        $out .= '<tr>';
        $out .= '<td><span class="dashicons dashicons-yes"></span>+200 Themes</td>';
        $out .= '<td><span class="dashicons dashicons-yes"></span>+200 Themes</td>';
        $out .= '<td><span class="dashicons dashicons-yes"></span>+200 Themes</td>';
        $out .= '</tr>';

        $out .= '<tr>';
        $out .= '<td><span class="dashicons dashicons-yes"></span>+8.5 Million HD Images</td>';
        $out .= '<td><span class="dashicons dashicons-yes"></span>+8.5 Million HD Images</td>';
        $out .= '<td><span class="dashicons dashicons-yes"></span>+8.5 Million HD Images</td>';
        $out .= '</tr>';

        $out .= '<tr>';
        $out .= '<td><span class="dashicons dashicons-yes"></span>5 new themes each month guaranteed</td>';
        $out .= '<td><span class="dashicons dashicons-yes"></span>5 new themes each month guaranteed</td>';
        $out .= '<td><span class="dashicons dashicons-yes"></span>5 new themes each month guaranteed</td>';
        $out .= '</tr>';

        $out .= '<tr>';
        $out .= '<td><span class="dashicons dashicons-no"></span>Licenses &amp; Sites Manager</td>';
        $out .= '<td><span class="dashicons dashicons-yes"></span>Licenses &amp; Sites Manager</td>';
        $out .= '<td><span class="dashicons dashicons-yes"></span>Licenses &amp; Sites Manager</td>';
        $out .= '</tr>';

        $out .= '<tr>';
        $out .= '<td><span class="dashicons dashicons-no"></span>White-label Mode</td>';
        $out .= '<td><span class="dashicons dashicons-yes"></span>White-label Mode</td>';
        $out .= '<td><span class="dashicons dashicons-yes"></span>White-label Mode</td>';
        $out .= '</tr>';

        $out .= '<tr>';
        $out .= '<td><span class="dashicons dashicons-no"></span>Full Plugin Rebranding</td>';
        $out .= '<td><span class="dashicons dashicons-no"></span>Full Plugin Rebranding</td>';
        $out .= '<td><span class="dashicons dashicons-yes"></span>Full Plugin Rebranding</td>';
        $out .= '</tr>';

        $out .= '<tr>';
        $out .= '<td><a class="button button-buy" data-href-org="https://wpmaintenancemode.com/buy/?product=personal-free&ref=pricing-table" href="https://wpmaintenancemode.com/buy/?product=personal-free&ref=pricing-table" target="_blank">Lifetime License<br>$69 -&gt; BUY NOW</a></td>';
        $out .= '<td><a class="button button-buy" data-href-org="https://wpmaintenancemode.com/buy/?product=team-free&ref=pricing-table" href="https://wpmaintenancemode.com/buy/?product=team-free&ref=pricing-table" target="_blank">Lifetime License<br>$79 -&gt; BUY NOW</a></td>';
        $out .= '<td><a class="button button-buy" data-href-org="https://wpmaintenancemode.com/buy/?product=agency-free&ref=pricing-table" href="https://wpmaintenancemode.com/buy/?product=agency-free&ref=pricing-table" target="_blank">Lifetime License<br>$119 -&gt; BUY NOW</a></td>';
        $out .= '</tr>';

        $out .= '</table>';
        
        $out .= '<div class="upsell-footer">Need the plugin only for a <b>short period of time</b>? <a class="link-buy" target="_blank" data-href-org="https://wpmaintenancemode.com/buy/?product=personal-monthly&ref=pricing-table" href="https://wpmaintenancemode.com/buy/?product=personal-monthly&ref=pricing-table"><b>Get it for only $9.99</b><small> /month</small></a> &amp; cancel any time!</div>';

        $out .= '<div class="center footer"><b>100% No-Risk Money Back Guarantee!</b> If you don\'t like the plugin over the next 7 days, we will happily refund 100% of your money. No questions asked! Payments are processed by our merchant of records - <a href="https://paddle.com/" target="_blank">Paddle</a>.</div></div>';


        // weglot install dialog
        $out .=  '<div id="weglot-upsell-dialog" style="display: none;" title="Weglot"><span class="ui-helper-hidden-accessible"><input type="text"/></span>';
        $out .=  '<div style="padding: 20px; font-size: 15px;">';
        $out .=  '<ul class="mtnc-list">';
        $out .=  '<li>Best-rated WordPress multilingual plugin</li>';
        $out .=  '<li>Simple 5-minute set-up. No coding required</li>';
        $out .=  '<li>Accelerated translation management: AI powered & human translations with access to professional translators</li>';
        $out .=  '<li>Compatible with any WordPress theme or plugin</li>';
        $out .=  '<li>Optimized for multilingual SEO</li>';
        $out .=  '<li>14-day Free trial and free plan available</li>';
        $out .=  '</ul>';
        $out .=  '<p class="upsell-footer"><a class="button button-primary" id="install-weglot">Install &amp; activate Weglot to make your website multilingual</a></p>';
        $out .=  '</div>';
        $out .=  '</div>';
        // weglot install dialog

        return $out;
    } // pro_dialog

    public function tab_footer($tab = false)
    {
        echo '<div class="mtnc-tab-footer">';


        echo '<a class="mtnc-btn mtnc-btn-secondary mtnc-preview" style="margin: 0 15px 0 0;" href="' . esc_url(home_url('?maintenance-preview=' . $this->theme_id)) . '" target="_blank">Preview Maintenance Page</a>';
        echo '<div data-caption="Save Changes" name="mtnc_submit" class="mtnc-btn mtnc-btn-save mtnc_submit">Save Changes</div>';

        echo '</div>';
    }


    /**
     * Echoes content for themes tab
     *
     * @return null
     */
    private function tab_themes_premade()
    {
        echo '<div class="mtnc-tile-title">Themes</div>';
        echo '<p>Are you in a hurry? Looking for something that looks great for your site? Pick one of <b>+200 premium pre-built themes</b> and be done in 5 minutes! Our PRO plugin comes with built-in SEO analyzer, a collection of over <b>7 million images</b> and it can connect to any mailing system like Mailchimp so you can start collecting emails from day one! Did we mention you can <b>rebrand the plugin</b> and control all client sites from the plugin\'s centralized Dashboard?</p>';

        $options = $this->get_options();
        echo '<div id="mtnc-themes-wrapper">';

        $themes = [
            '5f2f8c65307b6f3097f2ca4d25d5cb26' => 'Adventure Blog',
            '883f61d5131f6c7b558ccde9c5d1f411' => 'Analytics',
            '00cf8a2ca9efec1e8aea483568e2a8d8' => 'Baby Store',
            'ccdbb99fd1dfecd36e13d5a4cff45d21' => 'Burger Palace',
            '0a0c5efe1e95f91a42bc9e6e6ca884dd' => 'Business',
            '781cb3af776a041533735bdfc59e811a' => 'Car Repair',
            'e8e6f070f05eae0367699e6dc7963439' => 'Design Agency',
            '06142f926b2da71d8dddfba3254a78cb' => 'Digital Marketing Agency',
            'd41b1b0a6d4cb304e886121b3118cfa0' => 'Fashion',
            '7f96d3918bd5840258a6dce654f4b0dc' => 'Flower Shop',
            'b6e3012a316272608e1bbfac4d6b5a78' => 'Food Recommendation',
            '310e65d9600903508451d5670ab0c33f' => 'Freelance',
            '1c498ed60de01a93c2a4cac0ab50ddc2' => 'Gaming',
            'da24f016888540131b49800c7b8cb07e' => 'Health Service',
            '4dc670fa8069dd1293b85cf6235ceb25' => 'Hiking',
            'bb9f78a54648fe776fe7cdce018d4649' => 'Interior Design',
            '0888b7c6cfdfde868ad842b76f47dbdc' => 'Jewelry Shop',
            '9dd2a18f38bcfdcb6fce7b037c22e1ec' => 'Marketing',
            'bce5308440264fa4a8ce9cf1b38f3242' => 'Mobile App',
            'b20f2da4e5cd0753638723ff12383378' => 'Non-Profit Organization',
            '2c6c47a437172cf970e9027ab7c4f680' => 'Photography',
            'ea2584e286d8e0304994f4d9d9e4d335' => 'Podcast',
            'f7432f296c75f398c018ebbd0118cf1f' => 'Product Marketing',
            '274bd92fd91aadc05fe0637f614633d8' => 'Restaurant',
            '1ff8ca16c5010eec8797eb5416373c6d' => 'Skincare',
            'eb668b7221bb4ed50c8edc8aebb68ba4' => 'Sport',
            'a61a5890d7db6cef943729dc082f24e0' => 'Tech Conference',
            '1e26f176878e01991d42463cce0d3182' => 'Training',
            '906d50132e2caf64ad57d9c76b07f78c' => 'Travel Vlog',
            'd1dd1f82d0d557460f22ac7058c291e0' => 'Wedding',
            '35b404155b3be97d198dadf05ddfc960' => 'Wellness',
            '293e20f552ddd1e851351a93408be322' => 'Winery',
        ];

        echo '<div class="theme-card">';
        echo '<div class="theme-card-background" style="background-image:url(\'' . esc_url(MTNC_URL) . 'img/themes/default.jpg' . '\')"></div>';
        echo '<div class="theme-card-title" title="Default">';
        echo 'Default Theme';
        echo '</div>';
        echo '<div class="theme-card-buttons">';
        $request_uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? ''));
        echo '<a data-confirm-title="Are you sure you want to reset to the default theme? Other settings will not be affected." data-confirm-button="Reset theme" data-confirm-text="All theme settings are reset to default values. <strong>There is NO undo!</strong>" href="' . esc_url(add_query_arg(array('_wpnonce' => wp_create_nonce('mtnc_admin_action'), 'action' => 'mtnc_reset_settings', 'reset' => 'theme', 'redirect' => urlencode($request_uri)), admin_url('admin.php'))) . '" class="mtnc-btn mtnc-btn-small mtnc-confirm">Reset theme</a>';
        echo '</div>';
        echo '</div>';

        foreach ($themes as $theme_id => $theme_name) {
            echo '<div class="theme-card">';
            echo '<div class="theme-card-background" style="background-image:url(\'' . esc_url(MTNC_URL . 'img/themes/' . $theme_id . '.jpg') . '\')"></div>';
            echo '<div class="theme-card-title" title="' . esc_html($theme_name) . '">';
            echo esc_html(substr($theme_name, 0, 30));
            echo '</div>';
            echo '<div class="theme-card-buttons">';
            echo '<a href="" class="open-pro-dialog mtnc-btn mtnc-btn-small" data-pro-feature="theme-business">Install</a>';
            echo '<a href="' . esc_url('https://themes.wpmaintenancemode.com?maintenance-preview=' . $theme_id) . '" class="mtnc-btn mtnc-btn-small mtnc-btn-secondary" target="_blank">Preview</a>';
            echo '</div>';
            echo '<div class="ribbon" title="PRO theme. Click \'Get this theme\' for more info."><i><span class="dashicons dashicons-star-filled"></span></i></div>';
            echo '</div>';
        }

        echo '</div>';

        $this->tab_footer();
    } // tab_themes

    /**
     * Echoes content for access tab
     *
     * @return null
     */
    private function tab_access()
    {
        $options = $this->get_options();

        echo '<div class="mtnc-tile-title">Access</div>';
        echo '<p>By default, if Maintenance mode is enabled, all site visitors except the logged in ones (regardless of their user role) will see the Maintenance page instead of the "normal" site.<br>The easiest way to show the site to clients or friends is to share the Secret Access Link, or whitelist their IP address if it doesn\'t change too often.</p>';

        echo '<div class="mtnc-double-group clearfix">';
        echo '<div class="mtnc-form-group">';
        echo '<label for="show_logged_in" class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="show_logged_in">Show Normal Site to Logged in Users <sup>PRO</sup></label>';
        echo '<div class="toggle-wrapper">';
        echo '<input id="show_logged_in" type="checkbox" class="mtnc-form-ios open-pro-dialog pro-disabled" name="show_logged_in" value="1">';
        echo '<label for="show_logged_in" class="toggle"><span class="toggle_handler"></span></label>';
        echo '</div>';

        echo '<p class="mtnc-form-help-block">Logged in users (regardless of their user role) will not be affected by the maintenance mode and will see the "normal" site.</p>';
        echo '</div>';
        echo '<div class="mtnc-form-group">';
        echo '<label for="ip_whitelist" class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="ip_whitelist">IP Whitelisting <sup>PRO</sup></label>';
        echo '<textarea rows="2" class="mtnc-form-control open-pro-dialog pro-disabled" name="ip_whitelist" id="ip_whitelist"></textarea>';
        $ip_address = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        echo '<p class="mtnc-form-help-block">Noted IPs will not be affected by the maintenance mode, and their users will see the "normal" site.<br>Write one IP per line. Wildcards are not supported. If the user\'s IP changes, he will no longer be whitelisted. Your IP address is: ' . esc_html($ip_address) . '</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="mtnc-double-group clearfix">';
        echo '<div class="mtnc-form-group">';
        echo '<label for="per_url_settings" class="mtnc-strong">URL Based Rules</label>';
        echo '<select name="per_url_settings" id="per_url_settings">';
        $per_url_settings = array(
            array('val' => '', 'label' => esc_html__('Disabled', 'maintenance')),
            array('val' => 'whitelist', 'label' => esc_html__('Listed URLs will NEVER be affected by maintenance mode', 'maintenance')),
            array('val' => 'blacklist', 'label' => esc_html__('ONLY listed URLs CAN BE affected by maintenance mode', 'maintenance')),
        );
        self::wp_kses_wf($this->create_select_options($per_url_settings, $options['per_url_settings'], false));
        echo '</select>';
        echo '<p class="mtnc-form-help-block">Use this option to set per-URL rules and enable maintenance mode on the entire site except for selected pages, or enable it on just some pages and leave all others accessible to visitors. If the second option is used, all other access rules apply too.</p>';
        echo '</div>';

        echo '<div class="mtnc-form-group per-url-wrapper">';
        echo '<label for="per_url_enable_disable" class="mtnc-strong">URL List</label>';
        echo '<textarea rows="3" class="mtnc-form-control" name="per_url_enable_disable" id="per_url_enable_disable">' . esc_attr($options['per_url_enable_disable']) . '</textarea>';
        echo '<p class="mtnc-form-help-block">Enter one URL per line. Start and end URLs with a forward slash (/).</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="mtnc-double-group mtnc-clearfix">';
        echo '<div class="mtnc-form-group">';
        echo '<label for="direct_access_link" class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="secret-access-link">Secret Access Link <sup>PRO</sup></label>';
        echo '<span style="vertical-align: middle;">' . esc_url(trailingslashit(get_home_url())) . '?</span><input type="text" name="direct_access_link" id="direct_access_link" value="" placeholder="preview-full-site" class="mtnc-form-control small_80_percent open-pro-dialog pro-disabled"><a data-base-url="' . esc_url(trailingslashit(get_home_url())) . '?" href="javascript: void(0);" class="copy-secret-link"><span class="dashicons dashicons-clipboard"></span></a>';
        echo '<p class="mtnc-form-help-block">Share this link with people who need to see the normal site behind the maintenance page.</p>';
        echo '</div>';

        echo '<div class="mtnc-form-group">';
        echo '<label for="custom_login_url" class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="custom-login-url">Custom Login URL <sup>PRO</sup></label>';
        echo '<span style="vertical-align: middle;"><span style="vertical-align: middle;">' . esc_url(trailingslashit(get_home_url())) . '?</span><input type="text" name="custom_login_url" id="custom_login_url" value="" placeholder="my-login-url/" class="mtnc-form-control open-pro-dialog pro-disabled small_80_percent"></span>';
        echo '<p class="mtnc-form-help-block">If you\'re using a custom login URL and can\'t access it, enter the custom login URL here. That URL will never be affected by the maintenance mode.</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="mtnc-double-group mtnc-clearfix">';
        echo '<div class="mtnc-form-group">';
        echo '<label for="login_button" class="mtnc-strong">Show Login Button</label>';
        echo '<div class="toggle-wrapper">';

        echo '<input id="login_button" type="checkbox" name="login_button" value="1" ' . checked('1', (isset($options['login_button']) ? esc_html($options['login_button']) : ''), false) . '>';
        echo '<label for="login_button" class="toggle"><span class="toggle_handler"></span></label>';
        echo '</div>';
        echo '<p class="mtnc-form-help-block">Show a discrete button that links to the WordPress login page in the lower right corner of the maintenance page.</p>';
        echo '</div>';

        echo '<div class="mtnc-form-group">';
        echo '<label for="wplogin_button_tooltip" class="mtnc-strong">WordPress Login Button Tooltip</label>';
        echo '<span style="vertical-align: middle;"><input type="text" name="wplogin_button_tooltip" id="wplogin_button_tooltip" value="' . (isset($options['wplogin_button_tooltip']) ? esc_attr($options['wplogin_button_tooltip']) : 'Access WordPress admin') . '" placeholder="" class="mtnc-form-control small_80_percent"></span>';
        echo '<p class="mtnc-form-help-block">Text for the "Access WordPress admin" button tooltip.</p>';
        echo '</div>';
        echo '</div>';
        $this->tab_footer();
    } // tab_access

    /**
     * Echoes content for access password tab
     *
     * @return null
     */
    private function tab_access_password()
    {
        $options = $this->get_options();

        echo '<div class="mtnc-tile-title">Password Protection</div>';
        echo '<p>By default, if Maintenance mode is enabled, all site visitors except the logged in ones (regardless of their user role) will see the Maintenance page instead of the "normal" site.<br>The easiest way to show the site to clients or friends is to share the Secret Access Link, or whitelist their IP address if it doesn\'t change too often.</p>';

        echo '<div class="mtnc-double-group mtnc-clearfix">';
        echo '<div class="mtnc-form-group">';
        echo '<label for="site_password" class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="direct-access-password">Direct Access Password <sup>PRO</sup></label>';
        echo '<span style="vertical-align: middle;"><input type="text" name="site_password" id="site_password" value="" placeholder="" class="mtnc-form-control small_80_percent open-pro-dialog pro-disabled"></span>';
        echo '<p class="mtnc-form-help-block">Direct Access Password is a user-friendly way (especially when working with clients) to give selected visitors access to the "normal" site. Enter the password and send users the following link: <a href="' . esc_url(get_home_url()) . '/#access-site-form">' . esc_url(get_home_url()) . '/#access-site-form</a> or enable the option on the right to show the password form button.<br>The password has to be at least 4 characters long</p>';
        echo '</div>';

        echo '<div class="mtnc-form-group">';
        echo '<label for="password_button" class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="password-form-button-text">Password Form Button <sup>PRO</sup></label>';
        echo '<div class="toggle-wrapper">';
        echo '<input id="password_button" type="checkbox" class="mtnc-form-ios open-pro-dialog pro-disabled" name="password_button" value="1">';
        echo '<label for="password_button" class="toggle"><span class="toggle_handler"></span></label>';
        echo '</div>';
        echo '<p class="mtnc-form-help-block">Show a discrete button to the direct access password form in the lower right corner of the maintenance page.</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="mtnc-double-group mtnc-clearfix">';
        echo '<div class="mtnc-form-group">';
        echo '<label for="maintenance_password_toggle" class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="maintenance-page-password">Password to Protect the Maintenance Page <sup>PRO</sup></label>';
        echo '<div class="toggle-wrapper">';
        echo '<input id="maintenance_password_toggle" type="checkbox" class="mtnc-form-ios open-pro-dialog pro-disabled" name="maintenance_password_toggle" value="1">';
        echo '<label for="maintenance_password_toggle" class="toggle"><span class="toggle_handler"></span></label>';
        echo '</div>';
        echo '<p class="mtnc-form-help-block">Protect the entire site with a password you choose. Only those with the password can view the maintenance page.</p>';
        echo '</div>';

        echo '<div class="mtnc-form-group" id="maintenance_password_wrapper">';
        echo '<label for="maintenance_password" class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="maintenance-page-password">Password <sup>PRO</sup></label>';
        echo '<span style="vertical-align: middle;"><input type="text" name="maintenance_password" id="maintenance_password" value="" placeholder="" class="mtnc-form-control small_80_percent open-pro-dialog pro-disabled"></span>';
        echo '<p class="mtnc-form-help-block">The password has to be at least 4 characters long.</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="mtnc-double-group mtnc-clearfix">';
        echo '<div class="mtnc-form-group">';
        echo '<label for="login_message" class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="maintenance-page-password-message">Password Popup Message <sup>PRO</sup></label>';
        echo '<span style="vertical-align: middle;"><input type="text" name="login_message" id="login_message" value="Please enter the password to access the site" placeholder="" class="mtnc-form-control open-pro-dialog pro-disabled small_80_percent"></span>';
        echo '<p class="mtnc-form-help-block">The message displayed in the password popup above the password input. </p>';
        echo '</div>';

        echo '<div class="mtnc-form-group">';
        echo '<label for="login_button_text" class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="maintenance-page-password-button">Password Popup Button Message <sup>PRO</sup></label>';
        echo '<span style="vertical-align: middle;"><input type="text" name="login_button_text" id="login_button_text" value="Access the Site" placeholder="" class="mtnc-form-control small_80_percent open-pro-dialog pro-disabled"></span>';
        echo '<p class="mtnc-form-help-block">Text for the password popup button.</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="mtnc-double-group mtnc-clearfix">';
        echo '<div class="mtnc-form-group">';
        echo '<label for="login_wrong_password_text" class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="maintenance-page-password-wrong">Wrong Password Text <sup>PRO</sup></label>';
        echo '<span style="vertical-align: middle;"><input type="text" name="login_wrong_password_text" id="login_wrong_password_text" value="Wrong password" placeholder="" class="mtnc-form-control small_80_percent open-pro-dialog pro-disabled"></span>';
        echo '<p class="mtnc-form-help-block">Text for the "Wrong Password" message.</p>';
        echo '</div>';

        echo '<div class="mtnc-form-group">';
        echo '<label for="login_button_tooltip" class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="maintenance-page-direct-button">Maintenance Direct Access Button Tooltip <sup>PRO</sup></label>';
        echo '<span style="vertical-align: middle;"><input type="text" name="login_button_tooltip" id="login_button_tooltip" value="Direct Access login" placeholder="" class="mtnc-form-control small_80_percent open-pro-dialog pro-disabled"></span>';
        echo '<p class="mtnc-form-help-block">Text for the "Direct Access login" button tooltip.</p>';
        echo '</div>';
        echo '</div>';
        $this->tab_footer();
    } // tab_access_password

    /**
     * Admin footer text
     *
     * @since 5.0
     *
     * @return null
     */
    public function admin_footer_text($text)
    {
        if (!$this->is_plugin_page()) {
            return $text;
        }

        $text = '<i class="wfsab-footer">Thank you for creating with <a href="' . esc_url($this->generate_web_link('admin_footer')) . '" title="' . esc_html__('Visit Maintenance page for more info', 'maintenance') . '" target="_blank">Maintenance</a> v' . $this->version . '</i>';

        return $text;
    } // admin_footer_text

    /**
     * Add links to plugin's description in plugins table
     *
     * @param array  $links  Initial list of links.
     * @param string $file   Basename of current plugin.
     *
     * @return array
     */
    function plugin_meta_links($links, $file)
    {
        if ($file !== plugin_basename(__FILE__)) {
            return $links;
        }

        $support_link = '<a target="_blank" href="' . $this->generate_web_link('plugins-table-right', '/support/') . '" title="Get help">Support</a>';
        $home_link = '<a target="_blank" href="' . $this->generate_web_link('plugins-table-right') . '" title="Plugin Homepage">Plugin Homepage</a>';

        $links[] = $support_link;
        $links[] = $home_link;

        return $links;
    } // plugin_meta_links


    /**
     * Helper function for generating UTM tagged links
     *
     * @param string  $placement  Optional. UTM content param.
     * @param string  $page       Optional. Page to link to.
     * @param array   $params     Optional. Extra URL params.
     * @param string  $anchor     Optional. URL anchor part.
     *
     * @return string
     */
    public function generate_web_link($placement = '', $page = '/', $params = array(), $anchor = '')
    {
        $base_url = 'https://wpmaintenancemode.com';

        if ('/' != $page) {
            $page = '/' . trim($page, '/') . '/';
        }
        if ($page == '//') {
            $page = '/';
        }

        $parts = array_merge(array('utm_source' => 'maintenance-free', 'utm_content' => $placement), $params);

        if (!empty($anchor)) {
            $anchor = '#' . trim($anchor, '#');
        }

        $out = $base_url . $page . '?' . http_build_query($parts, '', '&amp;') . $anchor;

        return $out;
    } // generate_web_link


    function generate_dashboard_link($placement = '', $page = '/', $params = array(), $anchor = '')
    {
        $base_url = 'https://dashboard.wpmaintenancemode.com';

        if ('/' != $page) {
            $page = '/' . trim($page, '/') . '/';
        }
        if ($page == '//') {
            $page = '/';
        }

        $parts = array_merge(array('utm_source' => 'maintenance-free', 'utm_medium' => 'plugin', 'utm_content' => $placement, 'utm_campaign' => 'maintenance-free-v' . $this->version), $params);

        if (!empty($anchor)) {
            $anchor = '#' . trim($anchor, '#');
        }

        $out = $base_url . $page . '?' . http_build_query($parts, '', '&amp;') . $anchor;

        return $out;
    } // generate_dashboard_link


    /**
     * Echoes content for design header such as theme name
     *
     * @return null
     */
    private function design_header()
    {
        $options = $this->get_options();

        echo '<div class="mtnc-theme-title">';
        if ($options['theme_global'] == $this->theme_id) {
            echo '<span class="mtnc-theme-active dashicons dashicons-star-filled" title="This is the currently active theme"></span>';
        }
        echo 'Editing theme <span class="mtnc-theme-title-name">' . esc_html($this->theme->name) . '</span>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=maintenance#themes')) . '">switch theme</a>';
        echo '</div>';
    }

    /**
     * Echoes content for design-general tab
     *
     * @return null
     */
    private function tab_design_general()
    {
        $fonts = $this->get_fonts();
        $options = $this->get_options();

        echo '<div class="mtnc-double-group mtnc-clearfix">';
        echo '<div class="mtnc-form-group">';
        echo '<label for="show_logged_in" class="mtnc-strong">Multilingual Support</label>';
        echo '<div class="toggle-wrapper">';
        echo '<input id="weglot_translate" type="checkbox" class="mtnc-form-ios open-weglot-upsell" name="content_overlay" value="1">';
        echo '<label for="weglot_translate" class="toggle open-weglot-upsell"><span class="toggle_handler"></span></label>';
        echo '<p class="mtnc-form-help-block">55% of online visitors prefer to browse in their mother tongue. If you have an audience speaking multiple languages, making your website multilingual is a must-have. To instantly translate your website and your maintenance, <a href="#" class="open-weglot-upsell">install the Weglot plugin</a> (free plan and free trial available). It seamlessly integrates with Maintenance and is compatible with all themes &amp; plugins.</label></p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '<div class="mtnc-double-group mtnc-clearfix">';
        echo '<div class="mtnc-form-group mtnc-swal-input-wrapper">';
        echo '<label for="body_font_size" class="mtnc-strong">Content Text Size</label>';
        echo '<div class="range-slider-wrapper">';
        echo '<input type="range" name="body_font_size" value="' . esc_attr($this->theme->body_font_size) . '" min="6" max="300" class="range-slider">';
        echo '</div>';
        echo '<span class="range_value" data-unit="px">' . esc_attr($this->theme->body_font_size) . '</span>px';
        echo '<p class="mtnc-form-help-block">Don\'t be afraid to make to font a bit bigger. Default: 16px;</p>';
        echo '</div>';

        echo '<div class="mtnc-form-group">';
        echo '<label for="body_font_color" class="mtnc-strong">Content Text Color</label>';
        echo '<input type="text" name="body_font_color" id="body_font_color" value="' . esc_html($this->theme->body_font_color) . '" class="jscolor mtnc-form-control color {required:false}">';
        echo '<p class="mtnc-form-help-block">Make sure the contrast is good. Whiteish text on a dark background is always a good pick.</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="mtnc-double-group mtnc-clearfix">';
        echo '<div class="mtnc-form-group">';
        echo '<label for="body_font" class="mtnc-strong">Content Font</label>';
        echo '<select name="body_font" id="body_font" class="mtnc-bunny-fonts">';
        // Listing fonts from the array
        foreach ($fonts as $font) {
            echo '<option value="' . esc_html($font) . '"' . selected($font, $this->theme->body_font, false) . '>' . esc_html($font) . '</option>' . "\n";
        }
        echo '</select>';
        echo '<h3>' . esc_html__('This is how the content font is going to look!', 'maintenance') . '</h3>';
        echo '<p class="mtnc-form-help-block">Choose from over 700 Fonts. Make sure other modules use the same, or matching fonts. Maintenance now uses Bunny Fonts instead of Google fonts. Bunny Fonts is an open-source, privacy-first web font platform designed to put privacy back into the internet. With a zero-tracking and no-logging policy, <strong>Bunny Fonts helps you stay fully GDPR compliant</strong> and puts your user\'s personal data into their own hands. Additionally, you can enjoy lightning-fast load times thanks to bunny.net\'s global CDN network to help improve SEO and deliver a better user experience.</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="mtnc-double-group mtnc-clearfix">';
        echo '<div class="mtnc-form-group">';
        echo '<label for="body_link_color" class="mtnc-strong">Link Color</label>';
        echo '<input type="text" name="body_link_color" id="body_link_color" value="' . esc_html($this->theme->body_link_color) . '" class="jscolor mtnc-form-control color {required:false}">';
        echo '<p class="mtnc-form-help-block">Make sure it stands out but make it fit your color scheme.</p>';
        echo '</div>';

        echo '<div class="mtnc-form-group">';
        echo '<label for="body_link_hover_color" class="mtnc-strong">Link Hover Color</label>';
        echo '<input type="text" name="body_link_hover_color" id="body_link_hover_color" value="' . esc_html($this->theme->body_link_hover_color) . '" class="jscolor mtnc-form-control color {required:false}">';
        echo '<p class="mtnc-form-help-block">Generally it\'s a lighter or darker variation of the normal link color.</p>';
        echo '</div>';

        echo '</div>';

        echo '<div class="mtnc-double-group mtnc-clearfix">';
        echo '<div class="mtnc-form-group">';
        echo '<label for="show_logged_in" class="mtnc-strong">Content Overlay</label>';
        echo '<div class="toggle-wrapper">';
        echo '<input id="content_overlay" type="checkbox" class="mtnc-form-ios" name="content_overlay" value="1" ' . checked('1', $this->theme->content_overlay, false) . '>';
        echo '<label for="content_overlay" class="toggle"><span class="toggle_handler"></span></label>';
        echo '<p class="mtnc-form-help-block">If enabled, applies a transparent background to the entire content section of the page.</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="mtnc-form-group overlay_parameters">';
        echo '<label for="body_font" class="mtnc-strong">Content Position</label>';
        $content_positions = array(
            'left' => 'Top Left',
            'right' => 'Top Right',
            'center' => 'Top Center',
            'middle' => 'Middle Center',
            'bottom' => 'Bottom Center',
        );
        echo '<select name="content_position" id="content_position">';
        foreach ($content_positions as $position => $label) {
            echo '<option value="' . esc_html($position) . '"' . selected($position, $this->theme->content_position, false) . '>' . esc_html($label) . '</option>' . "\n";
        }
        echo '</select>';
        echo '<p class="mtnc-form-help-block">Content box position on the page.</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="mtnc-double-group mtnc-clearfix overlay_parameters">';
        echo '<div class="mtnc-form-group" ' . (!$this->theme->content_overlay ? 'style="display:none;"' : '') . '>';
        echo '<label for="content_overlay_color" class="mtnc-strong">Content Overlay Color</label>';
        echo '<input type="text" name="content_overlay_color" id="content_overlay_color" value="' . esc_html($this->theme->content_overlay_color) . '"  data-jscolor="{format: \'rgba\',previewSize: 36}" class="jscolor mtnc-form-control color {required:false}">';
        echo '<p class="mtnc-form-help-block">Background color for the content overlay.</p>';
        echo '</div>';

        echo '<div class="mtnc-form-group" ' . (!$this->theme->content_overlay ? 'style="display:none;"' : '') . '>';
        echo '<label for="content_overlay_shadow_color" class="mtnc-strong">Content Overlay Shadow Color</label>';
        echo '<input type="text" name="content_overlay_shadow_color" id="content_overlay_shadow_color" value="' . (isset($this->theme->content_overlay_shadow_color) ? esc_html($this->theme->content_overlay_shadow_color) : '') . '"  data-jscolor="{format: \'rgba\',previewSize: 36}" class="jscolor mtnc-form-control color {required:false}">';
        echo '<p class="mtnc-form-help-block">Shadow color for the content overlay.</p>';
        echo '</div>';

        echo '</div>';

        echo '<div class="mtnc-double-group mtnc-clearfix">';
        echo '<div class="mtnc-form-group mtnc-swal-input-wrapper">';
        echo '<label for="content_width" class="mtnc-strong">Content Width</label>';
        echo '<div class="range-slider-wrapper">';
        echo '<input type="range" name="content_width" value="' . esc_attr($this->theme->content_width) . '" min="200" max="1600" class="range-slider">';
        echo '</div>';
        echo '<span class="range_value" data-unit="px">' . esc_attr($this->theme->content_width) . '</span>px';
        echo '<p class="mtnc-form-help-block">Maximum content width. Default: 600px.</p>';
        echo '</div>';

        echo '<div class="mtnc-form-group mtnc-swal-input-wrapper">';
        echo '<label for="modules_spacing" class="mtnc-strong">Modules Spacing</label>';
        echo '<div class="range-slider-wrapper">';
        echo '<input type="range" name="modules_spacing" value="' . esc_attr($this->theme->modules_spacing) . '" min="0" max="50" class="range-slider">';
        echo '</div>';
        echo '<span class="range_value" data-unit="px">' . esc_attr($this->theme->modules_spacing) . '</span>px';
        echo '<p class="mtnc-form-help-block">Vertical spacing between design modules. The selected value is added to both top and bottom margins of the module. Default: 10px.</p>';
        echo '</div>';
        echo '</div>';

        $this->tab_footer('design');
    } // tab_design_general


    /**
     * Echoes content for design-background tab
     *
     * @return null
     */
    private function tab_design_background()
    {
        $options = $this->get_options();
        $filters = array(
            array('val' => '', 'label' => 'No Filter'),
            array('label' => '1977', 'val' => ' _1977'),
            array('label' => 'Aden', 'val' => ' aden'),
            array('label' => 'Black & White', 'val' => ' blackwhite'),
            array('label' => 'Brannan', 'val' => ' brannan'),
            array('label' => 'Brooklyn', 'val' => ' brooklyn'),
            array('label' => 'Clarendon', 'val' => ' clarendon'),
            array('label' => 'Earlybird', 'val' => ' earlybird'),
            array('label' => 'Gingham', 'val' => ' gingham'),
            array('label' => 'Hudson', 'val' => ' hudson'),
            array('label' => 'Inkwell', 'val' => ' inkwell'),
            array('label' => 'Kelvin', 'val' => ' kelvin'),
            array('label' => 'Lark', 'val' => ' lark'),
            array('label' => 'Lo-Fi', 'val' => ' lofi'),
            array('label' => 'Maven', 'val' => ' maven'),
            array('label' => 'Mayfair', 'val' => ' mayfair'),
            array('label' => 'Moon', 'val' => ' moon'),
            array('label' => 'Nashville', 'val' => ' nashville'),
            array('label' => 'Perpetua', 'val' => ' perpetua'),
            array('label' => 'Reyes', 'val' => ' reyes'),
            array('label' => 'Rise', 'val' => ' rise'),
            array('label' => 'Slumber', 'val' => ' slumber'),
            array('label' => 'Stinson', 'val' => ' stinson'),
            array('label' => 'Toaster', 'val' => ' toaster'),
            array('label' => 'Valencia', 'val' => ' valencia'),
            array('label' => 'Walden', 'val' => ' walden'),
            array('label' => 'Willow', 'val' => ' willow'),
            array('label' => 'X-pro II', 'val' => ' xpro2'),
        );


        echo '<div class="mtnc-tile-title">Background</div>';
        echo '<p>The background image makes or breaks the whole page. Take your time to choose a perfect image from our gallery of 400,000+ images and then make it pop by using filters. If you don\'t have much content to show, then choose a video background.</p>';

        echo '<div class="mtnc-section-content">';
        echo '<div class="mtnc-double-group mtnc-clearfix">';
        echo '<div class="mtnc-form-group">';
        echo '<label for="background_type" class="mtnc-strong">Background Type</label>';
        echo '<select name="background_type" id="background_type">';
        $bg_type = array(
            array('val' => 'image', 'label' => 'Image'),
            array('val' => 'video', 'label' => 'Video'),
        );
        $this->create_select_options($bg_type, $this->theme->background_type);
        echo '</select>';

        echo '<p class="mtnc-form-help-block">Video background draws attention away from the content, so use it wisely, only in situations when you don\'t have much content to put on the page. In all other cases, an image background is a better choice.</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="background-type background-type-video">';
        echo '<div class="mtnc-double-group mtnc-clearfix">';
        echo '<div class="mtnc-form-group">';
        echo '<label for="background_video" class="mtnc-strong">Background Video ID</label>';
        echo '<input type="text" name="background_video" id="background_video" value="' . esc_attr($this->theme->background_video) . '" placeholder="UFHQzF593ak" class="mtnc-form-control">';
        echo '<p class="mtnc-form-help-block">Only YouTube videos are supported. Enter only the video ID, ie: <i>UFHQzF593ak</i>, found in the YouTube URL. Video is played muted and looped.<br>If the video ID is valid a preview will be shown below.</p>';
        echo '<div id="video-preview" class="rise">';
        echo '<div class="video-container"></div>';
        echo '</div>';
        echo '</div>';

        echo '<div class="mtnc-form-group">';
        echo '<label for="background_video_filter" class="mtnc-strong">Background Video Filter</label>';
        echo '<select name="background_video_filter" id="background_video_filter">';
        $this->create_select_options($filters, $this->theme->background_video_filter);
        echo '</select>';
        echo '<p class="mtnc-form-help-block">The filter is immediately applied to the video preview.</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="mtnc-double-group mtnc-clearfix">';
        echo '<div class="mtnc-upload-group mtnc-clearfix">';
        echo '<div class="mtnc-form-group border-fix">';
        echo '<div class="mtnc-image-upload-wrapper">';
        echo '<label class="mtnc-strong">Mobile Video Fallback Image</label>';
        if (stripos($this->theme->background_video_fallback, 'undefined index') !== false) {
            $this->theme->background_video_fallback = '';
        }
        if (!empty($this->theme->background_video_fallback)) {
            echo '<span class="mtnc-preview-area" id="video-fallback-preview"><img src="' . esc_attr($this->theme->background_video_fallback) . '" /></span>';
        } else {
            echo '<span class="mtnc-preview-area" id="video-fallback-preview">Select an image from our 400,000+ images gallery, or upload your own</span>';
        }

        echo '<input type="hidden" name="fallback" id="fallback" class="mm_upload_image_input" value="' . esc_attr($this->theme->background_video_fallback) . '">';
        echo '<button type="button" name="fallback_upload" id="fallback_upload" class="mtnc-btn mtnc-upload mtnc-free-images" style="margin-top: 4px">Open images gallery</button>';
        echo '<span class="mtnc-upload-append">';
        if (!empty($this->theme->background_video_fallback)) {
            echo '&nbsp;<a href="javascript: void(0);" class="mtnc-remove-image">' . esc_html__('Remove', 'maintenance') . '</a>';
        }
        echo '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '<div class="background-type background-type-image">';
        echo '<div class="mtnc-upload-group mtnc-clearfix">';
        echo '<div class="mtnc-form-group border-fix">';
        echo '<div class="mtnc-image-upload-wrapper">';
        echo '<label class="mtnc-strong">Background Image</label>';

        echo '<input type="hidden" name="background_image" id="background_image" class="mtnc-image-upload-input" value="' . esc_attr($this->theme->background_image) . '">';

        echo '<div class="mtnc-image-upload-preview-wrapper" ' . (isset($this->theme->background_image) ? 'style="background-image:url(\'' . esc_attr($this->theme->background_image) . '\')"' : '') . '>';
        if (empty($this->theme->background_image)) {
            echo '<img src="' . esc_url(MTNC_URL . '/img/icons/image.png') . '">';
            echo '<span class="mtnc-preview-area" id="background-preview">Select an image from our 400,000+ images gallery, or upload your own</span>';
        }
        echo '<button type="button" name="bg_upload" id="bg_upload" class="mtnc-btn mtnc-upload mtnc-free-images" style="margin-top: 4px">Open images gallery</button>';
        if (!empty($this->theme->background_image)) {
            echo '<button type="button" class="button mtnc-image-upload-remove" style="margin-top: 4px">Remove</button>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '<div class="mtnc-double-group mtnc-clearfix">';
        echo '<div class="mtnc-form-group">';
        echo '<label for="background_size_opt" class="mtnc-strong">Background Image Size</label>';
        echo '<select name="background_size_opt" id="background_size_opt">';
        $bkg_opt = array(
            array('val' => 'auto', 'label' => 'Auto'),
            array('val' => 'contain', 'label' => 'Contain'),
            array('val' => 'cover', 'label' => 'Cover'),
        );
        $this->create_select_options($bkg_opt, $this->theme->background_size_opt);
        echo '</select>';

        echo '<p class="mtnc-form-help-block">Auto - display image in original size; Contain - resize the image so it\'s fully visible; Cover - resize the image to cover the entire screen.</p>';
        echo '</div>';

        echo '<div class="mtnc-form-group">';
        echo '<label for="background_image_filter" class="mtnc-strong">Background Image Filter</label>';
        echo '<select name="background_image_filter" id="background_image_filter">';
        $this->create_select_options($filters, $this->theme->background_image_filter);
        echo '</select>';
        echo '<p class="mtnc-form-help-block">The filter is immediately applied to the background image thumbnail above for a preview.</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="mtnc-double-group mtnc-clearfix">';
        echo '<div class="mtnc-form-group">';
        echo '<label for="background_position" class="mtnc-strong">Background Image Position</label>';
        echo '<select name="background_position" id="background_position">';
        $positions = array(
            array('val' => '', 'label' => 'Auto'),
            array('val' => 'left top', 'label' => 'Top left'),
            array('val' => 'center top', 'label' => 'Top center'),
            array('val' => 'right top', 'label' => 'Top right'),
            array('val' => 'left center', 'label' => 'Center left'),
            array('val' => 'center center', 'label' => 'Center'),
            array('val' => 'right center', 'label' => 'Center right'),
            array('val' => 'left bottom', 'label' => 'Bottom left'),
            array('val' => 'center bottom', 'label' => 'Bottom center'),
            array('val' => 'right bottom', 'label' => 'Bottom right'),
        );
        $this->create_select_options($positions, $this->theme->background_position);
        echo '</select>';

        echo '<p class="mtnc-form-help-block">If defined, the position defines the screen and image corners that will be aligned. It works best with the "cover" size option.</p>';
        echo '</div>';

        echo '<div class="mtnc-form-group mtnc-swal-input-wrapper">';
        echo '<label for="background_blur" class="mtnc-strong">Background Blur</label>';
        echo '<div class="range-slider-wrapper">';
        echo '<input type="range" name="background_blur" value="' . (isset($this->theme->background_blur) ? esc_attr($this->theme->background_blur) : '') . '" min="0" max="50" class="range-slider">';
        echo '</div>';
        echo '<span class="range_value" data-unit="px">' . (isset($this->theme->background_blur) ? esc_attr($this->theme->background_blur) : '') . '</span>px';
        echo '<p class="mtnc-form-help-block">Blur the background image to make the content more visible</p>';
        echo '</div>';


        echo '</div>';

        echo '<div class="mtnc-double-group mtnc-clearfix">';
        echo '<div class="mtnc-form-group">';
        echo '<label for="background_color" class="mtnc-strong">' . esc_html__('Background Color', 'maintenance') . '</label>';
        echo '<input name="background_color" id="background_color" value="' . (isset($this->theme->background_color) ? esc_attr($this->theme->background_color) : '') . '" data-jscolor="{format: \'rgba\',previewSize: 36}" class="mtnc-form-control {required:false}">';
        echo '<p class="mtnc-form-help-block">If the background image is set, the color will not be visible once the image is loaded.</p>';
        echo '</div>';

        echo '<div class="mtnc-form-group">';
        echo '<label for="login_background_color" class="mtnc-strong">' . esc_html__('Login Background Color', 'maintenance') . '</label>';
        echo '<input name="login_background_color" id="login_background_color" value="' . (isset($this->theme->login_background_color) ? esc_attr($this->theme->login_background_color) : '') . '" data-jscolor="{format: \'rgba\',previewSize: 36}" class="mtnc-form-control {required:false}">';
        echo '<p class="mtnc-form-help-block">Background color of the login sidebar.</p>';
        echo '</div>';



        echo '</div>';

        echo '<div class="background-type background-type-image">';
        echo '<div class="mtnc-upload-group mtnc-clearfix">';
        echo '<div class="mtnc-form-group border-fix">';
        echo '<div class="mtnc-image-upload-wrapper">';
        echo '<label class="mtnc-strong">Preloader Image</label>';

        echo '<input type="hidden" name="preloader_background_image" id="preloader_background_image" class="mtnc-image-upload-input" value="' . (isset($this->theme->preloader_background_image) ? esc_attr($this->theme->preloader_background_image) : '') . '">';

        echo '<div class="mtnc-image-upload-preview-wrapper" ' . (isset($this->theme->preloader_background_image) ? 'style="background-image:url(\'' . esc_attr($this->theme->preloader_background_image) . '\')"' : '') . '>';
        if (empty($this->theme->preloader_background_image)) {
            echo '<img src="' . esc_html(MTNC_URL . '/img/icons/image.png') . '">';
            echo '<span class="mtnc-preview-area" id="background-preview">Select an image from our 400,000+ images gallery, or upload your own</span>';
        }
        echo '<button type="button" name="bg_upload" id="bg_upload" class="mtnc-btn mtnc-upload mtnc-free-images" style="margin-top: 4px">Open images gallery</button>';
        if (!empty($this->theme->preloader_background_image)) {
            echo '<button type="button" class="button mtnc-image-upload-remove" style="margin-top: 4px">Remove</button>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<p class="mtnc-form-help-block">Preloader image to replace the default gear icon.</p>';
        echo '</div>';
        echo '</div>';



        echo '</div>';
        $this->tab_footer('design');
    } // tab_design_background

    /**
     * Echoes content for design-layout tab
     *
     * @return null
     */
    private function tab_design_layout()
    {
        $modules = $this->get_modules();


        // Modules
        echo '<div class="arrange-wrapper" id="mtnc-theme-builder-modules-wrapper"><span class="arrange-label">Available Modules</span>';
        echo '<ul id="mtnc-theme-builder-modules" class="mtnc-theme-builder">';
        foreach ($modules as $module => $module_properties) {
            echo '<li data-type="' . esc_html($module) . '" ' . ($module_properties['pro'] === true ? 'class="pro-module open-pro-dialog" data-pro-feature="layout-module"' : 'class="available-module"') . '>';
            if ($module_properties['pro'] === true) {
                echo '<div class="pro-module-label">PRO</div>';
            }
            echo '<img style="max-height: 45px;" src="' . esc_url(MTNC_URL . 'img/modules/' . $module . '.png') . '" title="Drag to rearrange the module on maintenance page, or move it to inactive modules"><br />';
            echo '<span class="module-name">' . esc_html($module_properties['name']) . '</span>';
            echo '<div class="module-actions"><span class="module-name">' . esc_html($module_properties['name']) . '</span>';
            echo '<a title="Drag to rearrange the module on maintenance page, or move it to inactive modules" href="#" class="mtnc-move-module"><span class="dashicons dashicons-move"></span></a>';
            echo '<a title="Edit module" href="#" class="mtnc-edit-module" title="Edit module"><span class="dashicons dashicons-edit"></span></a>';
            echo '<a title="Clone module" href="#" class="mtnc-clone-module" title="Clone module"><span class="dashicons dashicons-admin-page"></span></a>';
            echo '<a href="#" class="mtnc-remove-module" title="Remove module from maintenance page"><span class="dashicons dashicons-trash"></span></a>';
            echo '</li>';
        }
        echo '</ul></div>';

        //Layout
        echo '<div class="arrange-wrapper mtnc-theme-builder-right-col"><span class="arrange-label">Active Modules (hover over each one to show the edit menu)</span>';
        echo '<div class="arrange-wrapper" id="mtnc-theme-builder-layout-wrapper">';
        echo '<div class="browser-header">
                <div class="browser-button-wrapper">
                    <div class="browser-button browser-button-red"></div>
                    <div class="browser-button browser-button-yellow"></div>
                    <div class="browser-button browser-button-green"></div>
                </div>
                <div class="browser-bar">
                    <span class="material-icons">navigate_before</span>
                    <span class="material-icons">navigate_next</span>
                    <span class="material-icons">refresh</span>
                    <div class="browser-input"></div>
                </div>
            </div>';
        echo '<ul id="mtnc-theme-builder-layout" class="mtnc-theme-builder">';
        echo '</ul>';
        echo '</div>';



        $this->tab_footer('design');
        echo '</div>';

        echo '<input type="hidden" name="theme" id="theme" value="">';
    } // tab_design_layout

    /**
     * Echoes content for design-css tab
     *
     * @return null
     */
    public function tab_design_css()
    {
        echo '<div class="mtnc-tile-title">Custom CSS</div>';
        echo '<p>Custom CSS applies to current theme only. Please double-check any custom code you enter in the settings below. Any typos or mistakes will affect the appearance of the page.</p>';
        echo '<div id="custom_css_editor"></div>';
        echo '<textarea class="mtnc-form-control" id="custom_css" name="custom_css">' . esc_html($this->theme->custom_css) . '</textarea>';
        echo '<p class="mtnc-form-help-block">Write only the CSS code. Do not include the &lt;style&gt; tags. Code is placed in the page\'s &lt;head&gt; tag.</p>';
        $this->tab_footer('design');
    } //tab_design_css

    /**
     * Echoes content for settings tab
     *
     * @return null
     */
    private function tab_seo()
    {
        $options = $this->get_options();
        echo '<div class="mtnc-tile-title">SEO</div>';
        echo '<p>Carefully craft your content in order to rank your site as best as possible from day one. Use the SEO Analysis tool to improve weak areas.</p>';

        //SEO Preview and Analysis
        echo '<div class="mtnc-double-group clearfix" style="margin-top:20px; padding-bottom:20px; display: flex;">';
        echo '<div class="mtnc-seo-snippet mtnc-form-group">';
        echo '<div class="mtnc-strong">SEO Snippet Preview</div>';
        echo '<br />';
        echo '<div class="mtnc-seo-snippet-preview">';
        echo '<h3 id="mtnc-seo-snippet-title">' . esc_attr(stripslashes($options['title'])) . '</h3>';
        echo '<cite id="mtnc-seo-snippet-url">' . esc_url(home_url()) . '</cite>';
        echo '<div id="mtnc-seo-snippet-description">' . esc_attr(stripslashes($options['description'])) . '</div>';
        echo '<br />';
        echo '<label for="target_keyword" class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="seo-target-keyword">Target Keyword <sup>PRO</sup></label>';
        echo '<input type="text" name="target_keyword" id="target_keyword" value="maintenance" placeholder="Pick the main keyword or phrase that this page focuses on" class="mtnc-form-control pro-option open-pro-dialog pro-disabled" data-pro-feature="seo-target-keyword">';
        echo '<div id="mtnc-seo-gage">
        <svg height="100%" version="1.1" width="280px" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" style="overflow: hidden; position: relative; top: -0.96875px;"><desc style="-webkit-tap-highlight-color: rgba(0, 0, 0, 0);">Created with Raphaël 2.2.0</desc><defs style="-webkit-tap-highlight-color: rgba(0, 0, 0, 0);"><filter id="inner-shadow-mtnc-seo-gage"><feOffset dx="0" dy="3"></feOffset><feGaussianBlur result="offset-blur" stdDeviation="5"></feGaussianBlur><feComposite operator="out" in="SourceGraphic" in2="offset-blur" result="inverse"></feComposite><feFlood flood-color="black" flood-opacity="0.2" result="color"></feFlood><feComposite operator="in" in="color" in2="inverse" result="shadow"></feComposite><feComposite operator="over" in="shadow" in2="SourceGraphic"></feComposite></filter></defs><path fill="#edebeb" stroke="none" d="M93.40556249999999,123.36240000000001L64.49249999999999,123.36240000000001A77.1015,77.1015,0,0,1,218.69549999999998,123.36240000000001L189.78243750000001,123.36240000000001A48.188437500000006,48.188437500000006,0,0,0,93.40556249999999,123.36240000000001Z" style="-webkit-tap-highlight-color: rgba(0, 0, 0, 0);" filter="url(#inner-shadow-mtnc-seo-gage)"></path><path fill="#f6b501" stroke="none" d="M93.40556249999999,123.36240000000001L64.49249999999999,123.36240000000001A77.1015,77.1015,0,0,1,153.65533190629938,47.210147407604L149.13233244143711,75.7672421297525A48.188437500000006,48.188437500000006,0,0,0,93.40556249999999,123.36240000000001Z" style="-webkit-tap-highlight-color: rgba(0, 0, 0, 0);" filter="url(#inner-shadow-mtnc-seo-gage)"></path><text x="141.594" y="24.09421875" text-anchor="middle" font-family="sans-serif" font-size="15px" stroke="none" fill="#666666" style="-webkit-tap-highlight-color: rgba(0, 0, 0, 0); text-anchor: middle; font-family: sans-serif; font-size: 15px; font-weight: bold; fill-opacity: 1;" font-weight="bold" fill-opacity="1"><tspan dy="0" style="-webkit-tap-highlight-color: rgba(0, 0, 0, 0);">Page SEO Score</tspan></text><text x="141.594" y="120.94352941176471" text-anchor="middle" font-family="Arial" font-size="23px" stroke="none" fill="#010101" style="-webkit-tap-highlight-color: rgba(0, 0, 0, 0); text-anchor: middle; font-family: Arial; font-size: 23px; font-weight: bold; fill-opacity: 1;" font-weight="bold" fill-opacity="1"><tspan dy="0" style="-webkit-tap-highlight-color: rgba(0, 0, 0, 0);">55</tspan></text><text x="141.594" y="137.80529864253396" text-anchor="middle" font-family="Arial" font-size="10px" stroke="none" fill="#b3b3b3" style="-webkit-tap-highlight-color: rgba(0, 0, 0, 0); text-anchor: middle; font-family: Arial; font-size: 10px; font-weight: normal; fill-opacity: 1;" font-weight="normal" fill-opacity="1"><tspan dy="0" style="-webkit-tap-highlight-color: rgba(0, 0, 0, 0);"></tspan></text><text x="78.94903124999999" y="137.80529864253396" text-anchor="middle" font-family="Arial" font-size="10px" stroke="none" fill="#b3b3b3" style="-webkit-tap-highlight-color: rgba(0, 0, 0, 0); text-anchor: middle; font-family: Arial; font-size: 10px; font-weight: normal; fill-opacity: 1;" font-weight="normal" fill-opacity="1"><tspan dy="0" style="-webkit-tap-highlight-color: rgba(0, 0, 0, 0);">0</tspan></text><text x="204.23896874999997" y="137.80529864253396" text-anchor="middle" font-family="Arial" font-size="10px" stroke="none" fill="#b3b3b3" style="-webkit-tap-highlight-color: rgba(0, 0, 0, 0); text-anchor: middle; font-family: Arial; font-size: 10px; font-weight: normal; fill-opacity: 1;" font-weight="normal" fill-opacity="1"><tspan dy="0" style="-webkit-tap-highlight-color: rgba(0, 0, 0, 0);">100</tspan></text></svg>
        </div></div>';
        echo '</div>';

        echo '<div class="mtnc-seo-analysis mtnc-form-group">';
        echo '<div class="mtnc-strong">SEO Analysis</div>';
        echo '<br />';
        echo '<div id="mtnc-seo-results" class="clearfix"><div class="mtnc-strong">Problems:</div><ul><li class="mtnc-test-bad">Target keyword does not appear in page <a class="goto-anchor" href="#description">meta description</a>.</li><li class="mtnc-test-bad">Keyword density in content is 4.76%, which is over the advised 2% maximum; the keyword was found 1 times in <a class="mtnc-change-tab" href="#design-layout">content</a>.</li><li class="mtnc-test-bad">Target keyword does not appear in the <a class="mtnc-change-tab" href="#design-logo">logo title</a>.</li><li class="mtnc-test-bad">Target keyword does not appear in the <a class="mtnc-change-tab" href="#design-header">page header</a>.</li></ul><div class="mtnc-strong">Potential Improvements:</div><ul><li class="mtnc-test-warning">Page <a href="#title" class="goto-anchor">SEO title</a> is too short. Keep the length between 40 and 60 characters.</li></ul><div class="mtnc-strong">Good Results:</div><ul><li class="mtnc-test-good">The page is not blocking search engines.</li><li class="mtnc-test-good">Target keyword appears in the page title.</li><li class="mtnc-test-good">Page meta description length is good.</li><li class="mtnc-test-good">Target keyword appears in the page URL.</li><li class="mtnc-test-good">Target keyword is set.</li></ul></div>';
        echo '</div>';
        echo '</div>';

        echo '<div class="mtnc-double-group clearfix" id="seotitle">';
        //SEO Title
        echo '<div class="mtnc-form-group">';
        echo '<label for="title" class="mtnc-strong">SEO Title</label>';
        echo '<input type="text" name="title" id="title" data-site-title="' . esc_html(get_bloginfo('name')) . '" value="' . esc_attr(stripslashes($options['title'])) . '" placeholder="%sitetitle% is under maintenance" class="mtnc-form-control">';
        echo '<div class="mtnc-seo-progress " id="mtnc-seo-progress-title">';
        echo '<div class="mtnc-seo-progress-bar"></div>';
        echo '</div>';
        echo '<p class="mtnc-form-help-block">Recommended format: <i>Primary Keyword - Secondary Keyword - Brand Name</i> with length up to 60 characters.<br>Use <i>%sitetitle%</i> and <i>%sitetagline%</i> to grab settings from WP.</p>';
        echo '</div>';

        //Meta Description
        echo '<div class="mtnc-form-group">';
        echo '<label for="description" class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="seo-description">Meta Description <sup>PRO</sup></label>';
        echo '<textarea type="text" name="description" id="description" data-site-description="' . esc_html(get_bloginfo('description')) . '" rows="3" class="mtnc-form-control open-pro-dialog pro-disabled">' . esc_html(get_bloginfo('description')) . '</textarea>';
        echo '<div class="mtnc-seo-progress " id="mtnc-seo-progress-description">';
        echo '<div class="mtnc-seo-progress-bar"></div>';
        echo '</div>';
        echo '<p class="mtnc-form-help-block">Write for humans, not search engines! This text will incite people to click on your site on Google. The length should be 50 - 300 characters.</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="mtnc-double-group clearfix" id="mtnc-blockse">';

        //Show normal website to Search Engines?
        echo '<div class="mtnc-form-group">';
        echo '<label for="excludese" class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="seo-show-normal-site">Show normal website to Search Engines? <sup>PRO</sup></label>';
        echo '<div class="toggle-wrapper">';
        echo '<input type="checkbox" class="mtnc-form-ios open-pro-dialog open-pro-dialog pro-disabled" name="excludese" id="excludese" value="1">';
        echo '<label for="excludese" class="toggle"><span class="toggle_handler"></span></label>';
        echo '</div>';
        echo '<p class="mtnc-form-help-block">If enabled, search engines will always see your normal page, never the maintenance one. We do not recommend enabling this feature.</p>';
        echo '</div>';

        //Discourage Search Engine crawlers
        echo '<div class="mtnc-form-group">';
        echo '<label for="blockse" class="mtnc-strong">Enable 503 <i>(Service Unavailable)</i> status code to discourage Search Engine crawlers</label>';
        echo '<div class="toggle-wrapper">';
        echo '<input type="checkbox" class="mtnc-form-ios" name="blockse" id="blockse" value="1" ' . checked('1', $options['blockse'], false) . '>';
        echo '<label for="blockse" class="toggle"><span class="toggle_handler"></span></label>';
        echo '</div>';
        echo '<p class="mtnc-form-help-block">If your site is already indexed and you\'re just taking it down for a while, enable this option. It temporarily discourages search engines from crawling the site by telling them it\'s unavailable by sending a <i>503 Service Unavailable</i> response.</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="mtnc-double-group mtnc-clearfix" style="flex-wrap: wrap;">';
        //Favicon
        echo '<div class="mtnc-form-group border-fix">';
        echo '<div class="mtnc-image-upload-wrapper">';
        echo '<label class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="seo-favicon">Favicon Image <sup>PRO</sup></label>';
        echo '<input type="hidden" name="favicon" id="favicon" class="mtnc-image-upload-input" value="' . esc_attr($options['favicon']) . '">';

        echo '<div class="mtnc-image-upload-preview-wrapper" ' . (isset($options['favicon']) ? 'style="background-image:url(\'' . esc_attr($options['favicon']) . '\')"' : '') . '>';
        if (empty($options['favicon'])) {
            echo '<img src="' . esc_html(MTNC_URL . '/img/icons/image.png') . '">';
        }
        echo '<button type="button" name="bg_upload" class="mtnc-btn open-pro-dialog" style="margin-top: 4px" data-pro-feature="seo-favicon">Open images gallery</button>';

        echo '</div>';
        echo '<p class="mtnc-form-help-block" style="padding: 0 10px;">Make sure the image is square (1:1 ratio). PNG will do just fine, about 64x64px. Don\'t go over 256x256px.</p>';
        echo '</div>';
        echo '</div>';

        //Social Preview Image
        echo '<div class="mtnc-form-group border-fix">';
        echo '<div class="mtnc-image-upload-wrapper">';
        echo '<label class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="seo-social-preview">Social Preview Image <sup>PRO</sup></label>';
        echo '<input type="hidden" name="social_preview" id="social_preview" class="mtnc-image-upload-input" value="' . esc_attr($options['social_preview']) . '">';

        echo '<div class="mtnc-image-upload-preview-wrapper" ' . (isset($options['favicon']) ? 'style="background-image:url(\'' . esc_attr($options['social_preview']) . '\')"' : '') . '>';
        if (empty($options['social_preview'])) {
            echo '<img src="' . esc_html(MTNC_URL . '/img/icons/image.png') . '">';
        }
        echo '<button type="button" name="bg_upload" class="mtnc-btn  open-pro-dialog" data-pro-feature="seo-social-preview" style="margin-top: 4px">Open images gallery</button>';

        echo '</div>';

        echo '<p class="mtnc-form-help-block" style="padding: 0 10px;">Image ratio should be 1:2. Facebook recommends 1200x630px. Minimum should be 600x315px.</p>';
        echo '</div>';
        echo '</div>';


        echo '</div>';

        echo '<div class="mtnc-double-group mtnc-clearfix">';
        //Google Analytics Tracking ID
        echo '<div class="mtnc-form-group">';
        echo '<label for="analytics" class="mtnc-strong">Google Analytics Tracking ID</label>';
        echo '<input name="analytics" id="analytics" placeholder="UA-123456-99" value="' . esc_attr($options['analytics']) . '">';

        echo '<p class="mtnc-form-help-block">Enter only the Google Analytics Profile ID, ie: UA-123456-99. You\'ll find it in the GA tracking code.</p>';
        echo '</div>';

        //Tracking Pixel and 3rd Party Analytics Code
        echo '<div class="mtnc-form-group">';
        echo '<label for="tracking_pixel" class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="seo-tracking-pixel">Tracking Pixel &amp; 3rd Party Analytics Code <sup>PRO</sup></label>';
        echo '<textarea name="tracking_pixel" id="tracking_pixel" class="open-pro-dialog pro-disabled" placeholder="Tracking pixel code or any 3rd party tracking code, including <script> tags"></textarea>';
        echo '<p class="mtnc-form-help-block">Copy&amp;paste the complete code, including the opening and closing <i>&lt;script&gt;</i> tags. The code is outputted in the page\'s header section.</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="mtnc-double-group clearfix">';
        //Facebook Page URL
        echo '<div class="mtnc-form-group"><label class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="seo-facebook-page" for="facebook_site">Facebook Page URL <sup>PRO</sup></label>';
        echo '<input class="mtnc-form-control open-pro-dialog pro-disabled" placeholder="https://www.facebook.com/page-name-123/" type="text" id="facebook_site" name="facebook_site" value="">';
        echo '<p class="mtnc-form-help-block">Full URL to your Facebook page, including the <i>https://</i> prefix.</p>';
        echo '</div>';

        //Twitter @username
        echo '<div class="mtnc-form-group"><label class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="seo-twitter" for="twitter_site">X (Twitter) @username <sup>PRO</sup></label>';
        echo '<input class="mtnc-form-control open-pro-dialog pro-disabled" placeholder="@mytwitterhandle" type="text" id="twitter_site" name="twitter_site" value="">';
        echo '<p class="mtnc-form-help-block">X (Twitter) handle name including the @ sign, ie: @john.</p>';
        echo '</div>';
        echo '</div>';
        $this->tab_footer();
    } // tab_seo

    /**
     * Echoes content for advanced tab
     *
     * @return null
     */
    private function tab_advanced()
    {
        $options = $this->get_options();

        echo '<div class="mtnc-tile-title">Advanced</div>';
        echo '<p>Please double-check any custom code you enter in the settings below. Any typos or mistakes will affect the appearance of the page.</p>';

        echo '<div class="mtnc-double-group clearfix">';
        echo '<div class="mtnc-form-group">';
        echo '<a class="mtnc-btn open-pro-dialog pro-button" data-pro-feature="export-settings">Export Settings <sup>PRO</sup></a>';
        echo '<p class="mtnc-form-help-block">All settings are exported except themes. If you would like to export themes, please go to the Themes tab. You can safely transfer (export and then import) settings between different domains/sites.</p>';
        echo '</div>';

        echo '<div class="mtnc-form-group">';
        echo '<div data-caption="Import Settings" class="mtnc-btn open-pro-dialog pro-button" data-pro-feature="import-settings" style="float:left;">Import Settings <sup>PRO</sup></div>';
        echo '<p class="mtnc-form-help-block">All settings are imported and overwritten. Themes are not imported. If you want to import themes go to the Themes tab.</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="mtnc-double-group clearfix">';
        echo '<div class="mtnc-form-group">';
        $request_uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? ''));
        echo '<a data-confirm-title="Are you sure you want to reset all Maintenance settings, including the theme?" data-confirm-button="Reset settings" data-confirm-text="All settings are reset to default values. <strong>There is NO undo!</strong>" href="' . esc_url(add_query_arg(array('_wpnonce' => wp_create_nonce('mtnc_admin_action'), 'action' => 'mtnc_reset_settings', 'reset' => 'settings', 'redirect' => urlencode($request_uri)), admin_url('admin.php'))) . '" class="mtnc-btn mtnc-confirm">Reset Settings</a>';
        echo '<p class="mtnc-form-help-block">All settings are reset to default values. There is NO undo.</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="mtnc-double-group clearfix">';

        echo '<div class="mtnc-form-group">';
        echo '<label for="no_cache_headers" class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="seo-no-cache-headers">Send no-cache Headers <sup>PRO</sup></label>';
        echo '<div class="toggle-wrapper">';
        echo '<input id="no_cache_headers" type="checkbox" class="mtnc-form-ios open-pro-dialog pro-disabled" name="no_cache_headers" value="1">';
        echo '<label for="no_cache_headers" class="toggle"><span class="toggle_handler"></span></label>';
        echo '</div>';
        echo '<p class="mtnc-form-help-block">If you don\'t want the maintenance page\'s preview to be cached by Facebook and other social media, enable this option. Once you switch to the normal site, social media preview (visible when sharing the site\'s link) will immediately be refreshed. Normal visitors won\'t notice any differences with the option enabled.</p>';
        echo '</div>';

        $plugin_name = 'Maintenance';

        echo '<div class="mtnc-form-group">';
        echo '<label for="disable_toolbar_menu" class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="disable-menu">Disable Maintenance Toolbar Menu <sup>PRO</sup></label>';
        echo '<div class="toggle-wrapper">';
        echo '<input id="disable_toolbar_menu" type="checkbox" class="mtnc-form-ios open-pro-dialog pro-disabled" name="disable_toolbar_menu" value="1">';
        echo '<label for="disable_toolbar_menu" class="toggle"><span class="toggle_handler"></span></label>';
        echo '</div>';
        echo '<p class="mtnc-form-help-block">By default, a helpful ' . esc_html($plugin_name) . ' menu and status are added to the admin and front-end toolbar. If your toolbar is too crowded, disable the menu.</p>';
        echo '</div>';

        echo '</div>';

        echo '<div class="mtnc-double-group clearfix">';

        echo '<div class="mtnc-form-group">';
        echo '<label for="force_ssl" class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="force-ssl">Force SSL on maintenance page  <sup>PRO</sup></label>';
        echo '<div class="toggle-wrapper">';
        echo '<input id="force_ssl" type="checkbox" class="mtnc-form-ios open-pro-dialog pro-disabled" name="force_ssl" value="1">';
        echo '<label for="force_ssl" class="toggle"><span class="toggle_handler"></span></label>';
        echo '</div>';
        echo '<p class="mtnc-form-help-block">If you have a valid SSL certificate installed on your site but people are still visiting the non-secure HTTP version, you can redirect them to HTTPS. The redirection only works for the maintenance page, not for the entire site and not for the preview.<br>
              DO NOT enable this option unless you have a valid SSL certificate installed. Check if you do by opening your site via the HTTPS protocol - <i><a href="' . esc_html(str_ireplace('http://', 'https://', home_url('/'))) . '" target="_blank">' . esc_html(str_ireplace('http://', 'https://', home_url('/'))) . '</a></i>.</p>';
        echo '</div>';

        echo '<div class="mtnc-form-group">';
        echo '<label for="enable_rest" class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="rest-api">Enable WordPress Rest API  <sup>PRO</sup></label>';
        echo '<div class="toggle-wrapper">';
        echo '<input id="enable_rest" type="checkbox" class="mtnc-form-ios open-pro-dialog pro-disabled" name="enable_rest" value="1">';
        echo '<label for="enable_rest" class="toggle"><span class="toggle_handler"></span></label>';
        echo '</div>';
        echo '<p class="mtnc-form-help-block">Allow WordPress REST API calls while maintenance mode is enabled.</p>';
        echo '</div>';

        echo '</div>';

        echo '<div class="mtnc-form-group clearfix">';
        echo '<label for="custom_wp_maintenance" class="mtnc-strong pro-option open-pro-dialog" data-pro-feature="customize-builtin-page">Customize the built-in WordPress maintenance page <sup>PRO</sup></label>';
        echo '<div class="toggle-wrapper">';
        echo '<input id="custom_wp_maintenance" type="checkbox" class="mtnc-form-ios open-pro-dialog pro-disabled" name="custom_wp_maintenance" value="1">';
        echo '<label for="custom_wp_maintenance" class="toggle"><span class="toggle_handler"></span></label>';
        echo '</div>';
        echo '<p class="mtnc-form-help-block">The built-in WordPress maintenance mode page is automatically shown to everybody (visitors and admins) for a few moments when WordPress is doing updates to themes, plugins, or core. It\'s a built-in feature and its behaviour can\'t be changed. This page can\'t contain any interactive elements such as a contact forms because when updates are performed the database and some files might not be accessible.<br>
        By default the page is quite ugly and the content can\'t be easily changed (you can read about it in this <a href="https://wpreset.com/disable-enable-modify-wordpress-maintenance-page/" target="_blank">article</a>). This option enables you do have a nicer maintenance page with your custom text on it. It\'s still automatically enabled and disabled by WordPress.</p>';
        echo '</div>';

        echo '<div class="mtnc-form-group clearfix custom_wp_maintenance_options">';
        echo '<label for="custom_wp_maintenance_title" class="mtnc-strong">Maintenance page title</label>';
        echo '<input class="mtnc-form-control" placeholder="" type="text" id="custom_wp_maintenance_title" name="custom_wp_maintenance_title" value="">';
        echo '</div>';

        echo '<div class="mtnc-form-group clearfix custom_wp_maintenance_options">';
        echo '<label for="custom_wp_maintenance_title" class="mtnc-strong">Maintenance page content</label>';
        wp_editor(stripslashes($options['custom_wp_maintenance_content']), 'custom_wp_maintenance_content', $settings = array(
            'textarea_rows' => 10,
            'media_buttons' => 1,
            'teeny' => false,
            'editor_class' => 'skip_save',
        ));
        echo '</div>';

        $this->tab_footer();
    } // tab_advanced


    /**
     * Reset Settings
     *
     * @return null
     */
    public function mtnc_reset_settings()
    {
        check_admin_referer('mtnc_admin_action');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You are not allowed to run this action.', 'maintenance'));
        }

        $options = $this->get_options();
        $default_theme_id = md5(time() . 'default');
        $reset = sanitize_text_field(wp_unslash($_GET['reset'] ?? '')) == 'settings' ? 'settings':'theme';

        if($reset == 'theme'){
            $new_options = $options;
        } else {
            $new_options = array(
                'status' => '0',
                'mode' => 'layout',
                'mtnc_page' => 0,
                'header_text' => 'Our site is under maintenance!',
                //SEO
                'title' => get_bloginfo('name') . ' is under maintenance',
                'description' => 'We are doing some work on our site. Please come back later. We\'ll be up and running in no time.',
                'target_keyword' => 'maintenance',
                'excludese' => '0',
                'blockse' => '0',
                'favicon' => '',
                'social_preview' => '',
                'analytics' => '',
                'tracking_pixel' => '',
                'twitter_site' => '',
                'facebook_site' => '',
                //Access
                'show_logged_in' => '1',
                'ip_whitelist' => '',
                'per_url_settings' => '',
                'per_url_enable_disable' => '',
                'direct_access_link' => '',
                'custom_login_url' => '',
                'login_button' => '1',
                'wplogin_button_tooltip' => 'Access WordPress admin',
                'maintenance_password' => '',
                'site_password' => '',
                'password_button' => '0',
                'login_button_text' => 'Access the Site',
                'login_message' => 'Please enter the password to access the site',
                'login_wrong_password_text' => 'Wrong password',
                'login_button_tooltip' => 'Direct Access login',
                //Advanced
                'no_cache_headers' => '1',
                'disable_toolbar_menu' => '',
                'force_ssl' => '',
                'enable_rest' => '',
                'custom_wp_maintenance' => '',
                'custom_wp_maintenance_title' => '',
                'custom_wp_maintenance_content' => '',
                'upgraded' => $options['upgraded']
            );
        }

        $new_options['theme_global'] = $default_theme_id;
        $new_options['themes'] = array($default_theme_id => json_decode(
                '{
                    "modules": {
                        "header-dbd02cd1": {
                        "name": "Header",
                        "type": "header",
                        "groups": {
                            "header": {
                            "name": "Header",
                            "active": true,
                            "fields": {
                                "text": {
                                "type": "text",
                                "name": "text",
                                "label": "Text",
                                "value": "Site is undergoing maintenance",
                                "class": "full-width",
                                "desc": ""
                                },
                                "text_align": {
                                "type": "radio",
                                "name": "text_align",
                                "label": "Text Align",
                                "class": "",
                                "values": {
                                    "left": "Left",
                                    "center": "Center",
                                    "right": "Right"
                                },
                                "value": "center"
                                },
                                "font_size": {
                                "type": "range",
                                "name": "font_size",
                                "label": "Font Size",
                                "value": "47",
                                "min": 6,
                                "max": 120,
                                "class": "",
                                "units": {
                                    "px": "px",
                                    "pt": "pt",
                                    "em": "em"
                                },
                                "unit_value": "px"
                                },
                                "color": {
                                "type": "color",
                                "name": "text_color",
                                "label": "Color",
                                "value": "rgba(255,255,255,1)",
                                "desc": ""
                                },
                                "font": {
                                "type": "font",
                                "name": "font",
                                "label": "Font",
                                "value": "Open Sans",
                                "class": "full-width",
                                "desc": ""
                                },
                                "line_height": {
                                "type": "number",
                                "name": "line_height",
                                "label": "Line Height",
                                "value": "2",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "em",
                                "class": "number_small",
                                "desc": ""
                                }
                            }
                            },
                            "layout": {
                            "name": "Layout",
                            "fields": {
                                "padding_label": {
                                "type": "label",
                                "name": "padding_label",
                                "label": "Padding"
                                },
                                "padding_top": {
                                "type": "number",
                                "name": "padding_top",
                                "label": "Top",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_top.png",
                                "value": "10",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_bottom": {
                                "type": "number",
                                "name": "padding_bottom",
                                "label": "Bottom",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_bottom.png",
                                "value": "10",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_left": {
                                "type": "number",
                                "name": "padding_left",
                                "label": "Left",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_left.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_right": {
                                "type": "number",
                                "name": "padding_right",
                                "label": "Right",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_right.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_label": {
                                "type": "label",
                                "name": "margin_label",
                                "label": "Margin"
                                },
                                "margin_top": {
                                "type": "number",
                                "name": "margin_top",
                                "label": "Top",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_top.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_bottom": {
                                "type": "number",
                                "name": "margin_bottom",
                                "label": "Bottom",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_bottom.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_left": {
                                "type": "number",
                                "name": "margin_left",
                                "label": "Left",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_left.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_right": {
                                "type": "number",
                                "name": "margin_right",
                                "label": "Right",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_right.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "background_label": {
                                "type": "label",
                                "name": "background_label",
                                "label": "Background"
                                },
                                "background_color": {
                                "type": "color",
                                "name": "background_color",
                                "nolabel": true,
                                "value": "rgba(255,255,255,0)",
                                "desc": ""
                                },
                                "background": {
                                "type": "image",
                                "name": "background",
                                "nolabel": true,
                                "value": "",
                                "class": "",
                                "desc": ""
                                }
                            }
                            }
                        }
                        },
                        "content-471365b7": {
                        "name": "Content",
                        "type": "content",
                        "groups": {
                            "col1": {
                            "name": "Footer",
                            "active": true,
                            "fields": {
                                "text": {
                                "type": "textarea",
                                "name": "text",
                                "label": "Text",
                                "value": "Maintenance mode is on",
                                "class": "full-width",
                                "desc": ""
                                },
                                "font_size": {
                                "type": "number",
                                "name": "font_size",
                                "label": "Font Size",
                                "value": "36",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "pt": "pt",
                                    "em": "em"
                                },
                                "unit_value": "px"
                                },
                                "color": {
                                "type": "color",
                                "name": "text_color",
                                "label": "Color",
                                "value": "rgba(255,255,255,1)",
                                "desc": ""
                                },
                                "font": {
                                "type": "font",
                                "name": "font",
                                "label": "Font",
                                "value": "Open Sans",
                                "class": "full-width",
                                "desc": ""
                                },
                                "line_height": {
                                "type": "number",
                                "name": "line_height",
                                "label": "Line Height",
                                "value": "1",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "em",
                                "class": "number_small",
                                "desc": ""
                                }
                            }
                            },
                            "layout": {
                            "name": "Layout",
                            "fields": {
                                "padding_label": {
                                "type": "label",
                                "name": "padding_label",
                                "label": "Padding"
                                },
                                "padding_top": {
                                "type": "number",
                                "name": "padding_top",
                                "label": "Top",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_top.png",
                                "value": "100",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_bottom": {
                                "type": "number",
                                "name": "padding_bottom",
                                "label": "Bottom",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_bottom.png",
                                "value": "10",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_left": {
                                "type": "number",
                                "name": "padding_left",
                                "label": "Left",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_left.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_right": {
                                "type": "number",
                                "name": "padding_right",
                                "label": "Right",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_right.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_label": {
                                "type": "label",
                                "name": "margin_label",
                                "label": "Margin"
                                },
                                "margin_top": {
                                "type": "number",
                                "name": "margin_top",
                                "label": "Top",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_top.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_bottom": {
                                "type": "number",
                                "name": "margin_bottom",
                                "label": "Bottom",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_bottom.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_left": {
                                "type": "number",
                                "name": "margin_left",
                                "label": "Left",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_left.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_right": {
                                "type": "number",
                                "name": "margin_right",
                                "label": "Right",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_right.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "background_label": {
                                "type": "label",
                                "name": "background_label",
                                "label": "Background"
                                },
                                "background_color": {
                                "type": "color",
                                "name": "background_color",
                                "nolabel": true,
                                "value": "rgba(255,255,255,0)",
                                "desc": ""
                                },
                                "background": {
                                "type": "image",
                                "name": "background",
                                "nolabel": true,
                                "value": "",
                                "class": "",
                                "desc": ""
                                }
                            }
                            }
                        }
                        },
                        "content-9742e2f8": {
                        "name": "Content",
                        "type": "content",
                        "groups": {
                            "col1": {
                            "name": "Footer",
                            "active": true,
                            "fields": {
                                "text": {
                                "type": "textarea",
                                "name": "text",
                                "label": "Text",
                                "value": "Site will be available soon. Thank you for your patience!",
                                "class": "full-width",
                                "desc": ""
                                },
                                "font_size": {
                                "type": "number",
                                "name": "font_size",
                                "label": "Font Size",
                                "value": "20",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "pt": "pt",
                                    "em": "em"
                                },
                                "unit_value": "px"
                                },
                                "color": {
                                "type": "color",
                                "name": "text_color",
                                "label": "Color",
                                "value": "rgba(255,255,255,1)",
                                "desc": ""
                                },
                                "font": {
                                "type": "font",
                                "name": "font",
                                "label": "Font",
                                "value": "Open Sans",
                                "class": "full-width",
                                "desc": ""
                                },
                                "line_height": {
                                "type": "number",
                                "name": "line_height",
                                "label": "Line Height",
                                "value": "1",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "em",
                                "class": "number_small",
                                "desc": ""
                                }
                            }
                            },
                            "layout": {
                            "name": "Layout",
                            "fields": {
                                "padding_label": {
                                "type": "label",
                                "name": "padding_label",
                                "label": "Padding"
                                },
                                "padding_top": {
                                "type": "number",
                                "name": "padding_top",
                                "label": "Top",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_top.png",
                                "value": "10",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_bottom": {
                                "type": "number",
                                "name": "padding_bottom",
                                "label": "Bottom",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_bottom.png",
                                "value": "10",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_left": {
                                "type": "number",
                                "name": "padding_left",
                                "label": "Left",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_left.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_right": {
                                "type": "number",
                                "name": "padding_right",
                                "label": "Right",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_right.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_label": {
                                "type": "label",
                                "name": "margin_label",
                                "label": "Margin"
                                },
                                "margin_top": {
                                "type": "number",
                                "name": "margin_top",
                                "label": "Top",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_top.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_bottom": {
                                "type": "number",
                                "name": "margin_bottom",
                                "label": "Bottom",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_bottom.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_left": {
                                "type": "number",
                                "name": "margin_left",
                                "label": "Left",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_left.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_right": {
                                "type": "number",
                                "name": "margin_right",
                                "label": "Right",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_right.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "background_label": {
                                "type": "label",
                                "name": "background_label",
                                "label": "Background"
                                },
                                "background_color": {
                                "type": "color",
                                "name": "background_color",
                                "nolabel": true,
                                "value": "rgba(255,255,255,0)",
                                "desc": ""
                                },
                                "background": {
                                "type": "image",
                                "name": "background",
                                "nolabel": true,
                                "value": "",
                                "class": "",
                                "desc": ""
                                }
                            }
                            }
                        }
                        },
                        "footer-a29820d6": {
                        "name": "Footer",
                        "type": "footer",
                        "groups": {
                            "col1": {
                            "name": "Footer",
                            "active": true,
                            "fields": {
                                "text": {
                                "type": "textarea",
                                "name": "text",
                                "label": "Text",
                                "value": "© Maintenance 2026",
                                "class": "full-width",
                                "desc": ""
                                },
                                "font_size": {
                                "type": "number",
                                "name": "font_size",
                                "label": "Font Size",
                                "value": "24",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "pt": "pt",
                                    "em": "em"
                                },
                                "unit_value": "px"
                                },
                                "color": {
                                "type": "color",
                                "name": "text_color",
                                "label": "Color",
                                "value": "rgba(255,255,255,1)",
                                "desc": ""
                                },
                                "font": {
                                "type": "font",
                                "name": "font",
                                "label": "Font",
                                "value": "Open Sans",
                                "class": "full-width",
                                "desc": ""
                                },
                                "line_height": {
                                "type": "number",
                                "name": "line_height",
                                "label": "Line Height",
                                "value": "1",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "em",
                                "class": "number_small",
                                "desc": ""
                                }
                            }
                            },
                            "layout": {
                            "name": "Layout",
                            "fields": {
                                "padding_label": {
                                "type": "label",
                                "name": "padding_label",
                                "label": "Padding"
                                },
                                "padding_top": {
                                "type": "number",
                                "name": "padding_top",
                                "label": "Top",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_top.png",
                                "value": "10",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_bottom": {
                                "type": "number",
                                "name": "padding_bottom",
                                "label": "Bottom",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_bottom.png",
                                "value": "10",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_left": {
                                "type": "number",
                                "name": "padding_left",
                                "label": "Left",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_left.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "padding_right": {
                                "type": "number",
                                "name": "padding_right",
                                "label": "Right",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/padding_right.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_label": {
                                "type": "label",
                                "name": "margin_label",
                                "label": "Margin"
                                },
                                "margin_top": {
                                "type": "number",
                                "name": "margin_top",
                                "label": "Top",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_top.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_bottom": {
                                "type": "number",
                                "name": "margin_bottom",
                                "label": "Bottom",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_bottom.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_left": {
                                "type": "number",
                                "name": "margin_left",
                                "label": "Left",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_left.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "margin_right": {
                                "type": "number",
                                "name": "margin_right",
                                "label": "Right",
                                "image_label": "' . trailingslashit(MTNC_URL) . 'img/icons/margin_right.png",
                                "value": "0",
                                "class": "number_small",
                                "units": {
                                    "px": "px",
                                    "percent": "%",
                                    "em": "em"
                                },
                                "unit_value": "px",
                                "wrapper_class": "col-4"
                                },
                                "background_label": {
                                "type": "label",
                                "name": "background_label",
                                "label": "Background"
                                },
                                "background_color": {
                                "type": "color",
                                "name": "background_color",
                                "nolabel": true,
                                "value": "rgba(255,255,255,0)",
                                "desc": ""
                                },
                                "background": {
                                "type": "image",
                                "name": "background",
                                "nolabel": true,
                                "value": "",
                                "class": "",
                                "desc": ""
                                }
                            }
                            }
                        }
                        }
                    },
                    "modules_order": {
                        "header-dbd02cd1": "header-dbd02cd1",
                        "content-471365b7": "content-471365b7",
                        "content-9742e2f8": "content-9742e2f8",
                        "footer-a29820d6": "footer-a29820d6"
                    },
                    "name": "Default",
                    "theme_thumbnail": "",
                    "theme_status": "",
                    "body_font_size": "16",
                    "body_font_color": "#FFFFFF",
                    "body_font": "Open Sans",
                    "body_link_color": "#0096FF",
                    "body_link_hover_color": "#57BAFF",
                    "background_type": "image",
                    "background_video": "",
                    "background_video_fallback": "",
                    "background_video_filter": "",
                    "background_size_opt": "cover",
                    "background_position": "center center",
                    "background_image_filter": "",
                    "background_blur": 0,
                    "background_image": "' . trailingslashit(MTNC_URL) . 'img/mt-sample-background.jpg",
                    "preloader_background_image": "",
                    "background_color": "rgba(0,0,0,1)",
                    "login_background_color": "rgba(0,0,0,1)",
                    "content_overlay": "",
                    "content_width": 916,
                    "content_overlay_color": "rgba(0,0,0,0.2)",
                    "content_overlay_shadow_color": "rgba(0,0,0,0.2)",
                    "content_position": "middle",
                    "modules_spacing": 20,
                    "custom_css": ".mtnc_module,\r\n.mtnc_module h2{\r\n\tfont-weight:300;\r\n}"
                    }'
            )
        );

        $this->update_options('options', $new_options);

        if (!empty($_GET['redirect'])) {
            if($reset == 'theme'){
                wp_safe_redirect(admin_url('admin.php?page=maintenance&theme-reset=true'));
            } else {
                wp_safe_redirect(admin_url('admin.php?page=maintenance&settings-reset=true'));
            }
        }

        die();
    }

    /**
     * Create select options
     *
     * @param array  $options Options
     * @param string  $selected  current value
     * @param bool  $output  current value
     *
     * @return string html if $output is false
     */
    public function create_select_options($options, $selected = null, $output = true)
    {
        $out = "\n";
        foreach ($options as $option) {
            $disabled = '';
            if (isset($option['disabled'])) {
                $disabled .= ' disabled="disabled" ';
            }

            $out .= '<option value="' . $option['val'] . '" ' . selected($option['val'], $selected, false) . ' ' . $disabled . '>' . $option['label'] . '</option>';
        } // foreach

        if ($output) {
            self::wp_kses_wf($out);
        } else {
            return $out;
        }
    } // create_select_options

    /**
     * Generate CSS Style for a module for front-end display
     *
     * @param object  $module object
     *
     * @return string CSS styles
     */
    public function get_module_style($module)
    {
        $styles = 'padding:0px; margin:' . round($this->theme->modules_spacing / 2) . 'px auto; text-align:center;';

        //Padding
        if (!empty($module->groups->layout->fields->padding_top->value)) {
            $styles .= 'padding-top:' . $module->groups->layout->fields->padding_top->value . str_replace('percent', '%', $module->groups->layout->fields->padding_top->unit_value) . ';';
        }
        if (!empty($module->groups->layout->fields->padding_right->value)) {
            $styles .= 'padding-right:' . $module->groups->layout->fields->padding_right->value . str_replace('percent', '%', $module->groups->layout->fields->padding_right->unit_value) . ';';
        }
        if (!empty($module->groups->layout->fields->padding_bottom->value)) {
            $styles .= 'padding-bottom:' . $module->groups->layout->fields->padding_bottom->value . str_replace('percent', '%', $module->groups->layout->fields->padding_bottom->unit_value) . ';';
        }
        if (!empty($module->groups->layout->fields->padding_left->value)) {
            $styles .= 'padding-left:' . $module->groups->layout->fields->padding_left->value . str_replace('percent', '%', $module->groups->layout->fields->padding_left->unit_value) . ';';
        }

        //Margin
        if (!empty($module->groups->layout->fields->margin_top->value)) {
            $styles .= 'margin-top:' . $module->groups->layout->fields->margin_top->value . str_replace('percent', '%', $module->groups->layout->fields->margin_top->unit_value) . ';';
        }
        if (!empty($module->groups->layout->fields->margin_right->value)) {
            $styles .= 'margin-right:' . $module->groups->layout->fields->margin_right->value . str_replace('percent', '%', $module->groups->layout->fields->margin_right->unit_value) . ';';
        }
        if (!empty($module->groups->layout->fields->margin_bottom->value)) {
            $styles .= 'margin-bottom:' . $module->groups->layout->fields->margin_bottom->value . str_replace('percent', '%', $module->groups->layout->fields->margin_bottom->unit_value) . ';';
        }
        if (!empty($module->groups->layout->fields->margin_left->value)) {
            $styles .= 'margin-left:' . $module->groups->layout->fields->margin_left->value . str_replace('percent', '%', $module->groups->layout->fields->margin_left->unit_value) . ';';
        }

        //Background
        if (!empty($module->groups->layout->fields->background_color->value)) {
            $styles .= 'background-color:' . $module->groups->layout->fields->background_color->value . ';';
        }

        if (!empty($module->groups->layout->fields->background->value)) {
            $styles .= 'background:url(' . $module->groups->layout->fields->background->value . ');';
        }

        if ($module->type == 'footer') {
            $styles .= 'position:absolute; bottom:0px; left: 0px;';
        }

        return $styles;
    }

    /**
     * Convert GA Code
     *
     * @param string GA Code
     *
     * @return string GA Code
     */
    public function convert_ga($code)
    {
        if (empty($code) || strpos($code, '<script') === false) {
            return $code;
        }

        preg_match_all('/(UA-[0-9]{3,10}-[0-9]{1,3})/i', $code, $matches, PREG_SET_ORDER, 0);
        if (!empty($matches[0][0])) {
            return $matches[0][0];
        } else {
            return '';
        }
    }


    /**
     * Overwrite current page template with Maintenance page
     *
     * @param object  original_template
     *
     * @return object new template
     */
    public function template_include($original_template)
    {
        $original_template = $this->load_maintenance_page($original_template);
        return $original_template;
    }

    public function check_maintenance_unlocked()
    {
        $mtnc_options = $this->get_options(true);
        if (isset($_COOKIE['maintenance_unlocked']) && $_COOKIE['maintenance_unlocked'] == md5($mtnc_options['maintenance_password'])) {
            return true;
        } else {
            return false;
        }
    }

    public function check_site_unlocked()
    {
        $mtnc_options = $this->get_options(true);
        if (isset($_COOKIE['site_unlocked']) && $_COOKIE['site_unlocked'] == md5($mtnc_options['site_password'])) {
            return true;
        } else {
            return false;
        }
    }

    public function check_maintenance_locked()
    {
        $mtnc_options = $this->get_options(true);
        if (isset($mtnc_options['maintenance_password']) && strlen($mtnc_options['maintenance_password']) > 0 && !$this->check_maintenance_unlocked()) {
            return true;
        }
        return false;
    }

    public function check_site_locked()
    {
        $mtnc_options = $this->get_options(true);
        if (isset($mtnc_options['site_password']) && strlen($mtnc_options['site_password']) > 0 && !$this->check_site_unlocked()) {
            return true;
        }
        return false;
    }

    public function load_maintenance_page($original_template)
    {
        $mtnc_options = $this->get_options(true);
        // just to be on the safe side
        if (defined('DOING_CRON') && DOING_CRON) {
            return false;
        }
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return false;
        }
        if (defined('WP_CLI') && WP_CLI) {
            return false;
        }

        // init custom enter no main url
        $custom_url_link = $mtnc_options['direct_access_link'];
        if (!empty($custom_url_link)) {
            if (isset($_GET[$custom_url_link])) { //phpcs:ignore
                setcookie("skip_maintenance_mode", true, time() + 24 * 60 * 60, '/');
                $_COOKIE['skip_maintenance_mode'] = true;
            }
        }

        // Getting custom login URL for the admin
        $login_url = wp_login_url();

        // This is the server address of the current page
        $request_uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? ''));
        $request_uri = $this->slashit(strtolower(wp_parse_url($request_uri, PHP_URL_PATH)));
        $server_url = get_home_url() . $request_uri;

        // Checking for the custom_login_url value
        if (empty($mtnc_options['custom_login_url'])) {
            $mtnc_options['custom_login_url'] = '';
        } else {
            $mtnc_options['custom_login_url'] = get_home_url() . $this->slashit($mtnc_options['custom_login_url']);
        }

        if (!is_admin() || isset($_GET['maintenance-preview'])) { //phpcs:ignore
            if ('1' == $mtnc_options['status'] || apply_filters('mtnc_force_display', false) || isset($_GET['maintenance-preview'])) { //phpcs:ignore
                if (
                    false === strpos($server_url, '/wp-login.php')
                    && false === strpos($server_url, '/wp-admin/')
                    && false === strpos($server_url, '/async-upload.php')
                    && false === strpos($server_url, '/upgrade.php')
                    && false === strpos($server_url, '/xmlrpc.php')
                    && false === strpos($server_url, $login_url)
                    && !isset($_GET['mainwpsignature']) //phpcs:ignore
                ) {

                    $show_maintenance_page = 1;

                    if (!empty($mtnc_options['custom_login_url']) && false !== strpos($server_url, $mtnc_options['custom_login_url'])) {
                        $show_maintenance_page = 0;
                    }

                    // search engines
                    if ($mtnc_options['excludese'] == '1' && $this->check_referrer()) {
                        $show_maintenance_page = 0;
                    }

                    // if logged in
                    if ($mtnc_options['show_logged_in'] == '1' && is_user_logged_in()) {
                        $show_maintenance_page = 0;
                    }

                    // IP whitelist
                    $all_ips = explode("\n", $mtnc_options['ip_whitelist']);
                    $all_ips = array_map('trim', $all_ips);
                    $current_ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));

                    if (@in_array($current_ip, $all_ips)) {
                        $show_maintenance_page = 0;
                    }

                    // urls list
                    $all_urls = explode("\n", trim($mtnc_options['per_url_enable_disable']));
                    $all_urls = array_map('trim', $all_urls);

                    // whitelisted / blacklisted URLs
                    if ($mtnc_options['per_url_settings'] == 'whitelist' && !empty($all_urls)) {
                        if (in_array($request_uri, $all_urls)) {
                            $show_maintenance_page = 0;
                        }
                    } elseif ($mtnc_options['per_url_settings'] == 'blacklist' && !empty($all_urls)) {
                        if (!in_array($request_uri, $all_urls)) {
                            $show_maintenance_page = 0;
                        }
                    }

                    if (!empty($_COOKIE['skip_maintenance_mode'])) {
                        $show_maintenance_page = 0;
                    }

                    if (isset($_COOKIE['site_unlocked']) && $_COOKIE['site_unlocked'] == md5($mtnc_options['site_password'])) {
                        $show_maintenance_page = 0;
                    }

                    $show_maintenance_page = apply_filters('mtnc_force_display', $show_maintenance_page);

                    if ($show_maintenance_page == 1 || isset($_GET['maintenance-preview'])) { //phpcs:ignore
                        if (file_exists(MTNC_LOAD . 'index.php')) {
                            add_filter('script_loader_tag', 'mtnc_defer_scripts', 10, 2);
                            return MTNC_LOAD . 'index.php';
                        } else {
                            return $original_template;
                        }
                    }
                }
            }
        }

        return $original_template;
    }

    public function slashit($url)
    {
        if (strpos($url, '?') === false && substr($url, -4, 1) != '.') {
            $url = trailingslashit($url);
        }

        if ($url != '/') {
            $url = '/' . ltrim($url, '/');
        }

        return $url;
    } // slashit

    public function get_headers_503()
    {
        $mtnc_options = $this->get_options(true);
        nocache_headers();
        if (!empty($mtnc_options['blockse'])) {
            $protocol = 'HTTP/1.0';
            if (isset($_SERVER['SERVER_PROTOCOL']) && 'HTTP/1.1' === $_SERVER['SERVER_PROTOCOL']) {
                $protocol = 'HTTP/1.1';
            }
            header("$protocol 503 Service Unavailable", true, 503);

            $v_curr_date_end = '';
            $vdate_end = date_i18n('Y-m-d', strtotime(current_time('mysql', 0)));
            $vtime_end = date_i18n('h:i a', strtotime('12:00 pm'));

            if (!empty($mtnc_options['expiry_date_end'])) {
                $vdate_end = $mtnc_options['expiry_date_end'];
            }
            if (!empty($mtnc_options['expiry_time_end'])) {
                $vtime_end = $mtnc_options['expiry_time_end'];
            }
            if ($mtnc_options['state'] && !empty($mtnc_options['expiry_date_end']) && !empty($mtnc_options['expiry_time_end'])) {
                $date_concat = $vdate_end . ' ' . $vtime_end;
                $v_curr_date = strtotime($date_concat);
                if (strtotime(current_time('mysql', 0)) < $v_curr_date) {
                    header('Retry-After: ' . gmdate('D, d M Y H:i:s', $v_curr_date));
                } else {
                    header('Retry-After: 3600');
                }
            } else {
                header('Retry-After: 3600');
            }
        }
    }

    public function add_bunny_fonts($theme)
    {
        $fonts = array();

        foreach ($theme->modules as $module) {
            foreach ($module->groups as $group) {
                foreach ($group->fields as $field) {
                    if ($field->type == 'font') {
                        $font_link = $this->get_bunny_font(esc_attr($field->value));
                        if (!empty($font_link)) {
                            $fonts[] = $font_link;
                        }
                    }
                }
            }
        }
        return $fonts;
    } //add_bunny_fonts

    /**
     * Get Google link
     *
     * @return string link
     */
    public function get_bunny_font($font = null)
    {
        $font_params = $full_link = $gg_fonts = '';

        $gg_fonts = json_decode($this->get_bunny_fonts());

        if (property_exists($gg_fonts, $font)) {
            $curr_font = $gg_fonts->{$font};
            if (!empty($curr_font)) {
                foreach ($curr_font->variants as $values) {
                    if (!empty($values->id)) {
                        $font_params .= $values->id . ',';
                    } elseif (!empty($values)) {
                        $font_params .= $values . ',';
                    }
                }

                $font_params = trim($font_params, ',');
                $full_link = $font . ':' . $font_params;
            }
        }

        return $full_link;
    } //get_bunny_font

    /**
     * Get Google fonts from json
     *
     * @return string Google fonts
     */
    public function get_bunny_fonts()
    {
        $gg_fonts = file_get_contents(MTNC_PATH . 'css/font/bunnyfonts.json');
        return $gg_fonts;
    } //get_bunny_fonts

    public function get_custom_login_code()
    {
        global $wp_query,
            $error;
        $mtnc_options = $this->get_options();
        $user_connect = false;
        if (!is_array($wp_query->query_vars)) {
            $wp_query->query_vars = array();
        }
        $error_message = $user_login = $user_pass = $error = '';
        $is_role_check = true;
        $class_login = 'user-icon';
        $class_password = 'pass-icon';
        $using_cookie = false;

        if (isset($_POST['is_custom_login'])) {
            $user_login = '';
            if (isset($_POST['log']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mtnc_login_check'] ?? '')), 'mtnc_login')) {
                $user_login = sanitize_text_field(wp_unslash($_POST['log']));
            }
            $user_login = sanitize_user($user_login);
            $user_pass = '';
            if (isset($_POST['pwd']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mtnc_login_check'] ?? '')), 'mtnc_login')) {
                $user_pass = sanitize_text_field(wp_unslash($_POST['pwd']));
            }
            $access = array();
            $access['user_login'] = esc_attr($user_login);
            $access['user_password'] = $user_pass;
            $access['remember'] = true;

            $user = null;
            $user = new WP_User(0, $user_login);
            $current_role = current($user->roles);

            if (!empty($mtnc_options['roles_array'])) {
                foreach (array_keys($mtnc_options['roles_array']) as $key) {
                    if ($key === $current_role) {
                        $is_role_check = false;
                    }
                }
            } else {
                $is_role_check = true;
            }

            if (!$is_role_check) {
                $error_message = esc_html__('Permission access denied!', 'maintenance');
                $class_login = 'error';
                $class_password = 'error';
            } else {

                if (is_ssl()) {
                    $ssl = true;
                } else {
                    $ssl = false;
                }

                $user_connect = wp_signon($access, $ssl);
                if (is_wp_error($user_connect)) {
                    if ($user_connect->get_error_code() === 'invalid_username') {
                        $error_message = esc_html__('Login is incorrect!', 'maintenance');

                        $class_login = 'error';
                        $class_password = 'error';
                    } elseif ($user_connect->get_error_code() === 'incorrect_password') {
                        $error_message = esc_html__('Password is incorrect!', 'maintenance');
                        $class_password = 'error';
                    } else {
                        $error_message = esc_html__('Login and password are incorrect!', 'maintenance');
                        $class_login = 'error';
                        $class_password = 'error';
                    }
                } else {
                    wp_safe_redirect(home_url('/'));
                    exit;
                }
            }
        }

        if (!$user_connect) {
            $this->get_headers_503();
        }

        return array($error_message, $class_login, $class_password, $user_login);
    }

    /**
     * Check referrer if it's a SERP crawler
     *
     * @return bool true if crawler
     */
    public function check_referrer()
    {
        // List of crawlers to check for
        $crawlers = array(
            'Abacho' => 'AbachoBOT',
            'Accoona' => 'Acoon',
            'AcoiRobot' => 'AcoiRobot',
            'Adidxbot' => 'adidxbot',
            'AltaVista robot' => 'Altavista',
            'Altavista robot' => 'Scooter',
            'ASPSeek' => 'ASPSeek',
            'Atomz' => 'Atomz',
            'Bing' => 'bingbot',
            'BingPreview' => 'BingPreview',
            'CrocCrawler' => 'CrocCrawler',
            'Dumbot' => 'Dumbot',
            'eStyle Bot' => 'eStyle',
            'FAST-WebCrawler' => 'FAST-WebCrawler',
            'GeonaBot' => 'GeonaBot',
            'Gigabot' => 'Gigabot',
            'Google' => 'Googlebot',
            'ID-Search Bot' => 'IDBot',
            'Lycos spider' => 'Lycos',
            'MSN' => 'msnbot',
            'MSRBOT' => 'MSRBOT',
            'Rambler' => 'Rambler',
            'Scrubby robot' => 'Scrubby',
            'Yahoo' => 'Yahoo',
        );

        // Checking for the crawler over here
        if ($this->string_to_array(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '')), $crawlers)) {
            return true;
        }
        return false;
    } //check_referrer

    /**
     * Function to match the user agent with the crawlers array
     *
     * @param string  string to find
     * @param array  array to look in
     *
     * @return bool true if match found
     */
    public function string_to_array($str, $array)
    {
        $regexp = '~(' . implode('|', array_values($array)) . ')~i';
        return (bool) preg_match($regexp, $str);
    } //string_to_array

    static function wp_kses_wf($html)
    {
        add_filter('safe_style_css', function ($styles) {
            $styles_wf = array(
                'text-align',
                'margin',
                'color',
                'float',
                'border',
                'background',
                'background-color',
                'border-bottom',
                'border-bottom-color',
                'border-bottom-style',
                'border-bottom-width',
                'border-collapse',
                'border-color',
                'border-left',
                'border-left-color',
                'border-left-style',
                'border-left-width',
                'border-right',
                'border-right-color',
                'border-right-style',
                'border-right-width',
                'border-spacing',
                'border-style',
                'border-top',
                'border-top-color',
                'border-top-style',
                'border-top-width',
                'border-width',
                'caption-side',
                'clear',
                'cursor',
                'direction',
                'font',
                'font-family',
                'font-size',
                'font-style',
                'font-variant',
                'font-weight',
                'height',
                'letter-spacing',
                'line-height',
                'margin-bottom',
                'margin-left',
                'margin-right',
                'margin-top',
                'overflow',
                'padding',
                'padding-bottom',
                'padding-left',
                'padding-right',
                'padding-top',
                'text-decoration',
                'text-indent',
                'vertical-align',
                'width',
                'display',
            );

            foreach ($styles_wf as $style_wf) {
                $styles[] = $style_wf;
            }
            return $styles;
        });

        $allowed_tags = wp_kses_allowed_html('post');
        $allowed_tags['input'] = array(
            'type' => true,
            'style' => true,
            'class' => true,
            'id' => true,
            'checked' => true,
            'disabled' => true,
            'name' => true,
            'size' => true,
            'placeholder' => true,
            'value' => true,
            'data-*' => true,
            'size' => true,
            'disabled' => true
        );

        $allowed_tags['textarea'] = array(
            'type' => true,
            'style' => true,
            'class' => true,
            'id' => true,
            'checked' => true,
            'disabled' => true,
            'name' => true,
            'size' => true,
            'placeholder' => true,
            'value' => true,
            'data-*' => true,
            'cols' => true,
            'rows' => true,
            'disabled' => true,
            'autocomplete' => true
        );

        $allowed_tags['select'] = array(
            'type' => true,
            'style' => true,
            'class' => true,
            'id' => true,
            'checked' => true,
            'disabled' => true,
            'name' => true,
            'size' => true,
            'placeholder' => true,
            'value' => true,
            'data-*' => true,
            'multiple' => true,
            'disabled' => true
        );

        $allowed_tags['option'] = array(
            'type' => true,
            'style' => true,
            'class' => true,
            'id' => true,
            'checked' => true,
            'disabled' => true,
            'name' => true,
            'size' => true,
            'placeholder' => true,
            'value' => true,
            'selected' => true,
            'data-*' => true
        );
        $allowed_tags['optgroup'] = array(
            'type' => true,
            'style' => true,
            'class' => true,
            'id' => true,
            'checked' => true,
            'disabled' => true,
            'name' => true,
            'size' => true,
            'placeholder' => true,
            'value' => true,
            'selected' => true,
            'data-*' => true,
            'label' => true
        );

        $allowed_tags['a'] = array(
            'href' => true,
            'data-*' => true,
            'class' => true,
            'style' => true,
            'id' => true,
            'target' => true,
            'data-*' => true,
            'role' => true,
            'aria-controls' => true,
            'aria-selected' => true,
            'disabled' => true
        );

        $allowed_tags['div'] = array(
            'style' => true,
            'class' => true,
            'id' => true,
            'data-*' => true,
            'role' => true,
            'aria-labelledby' => true,
            'value' => true,
            'aria-modal' => true,
            'tabindex' => true
        );

        $allowed_tags['h2'] = array(
            'style' => true,
            'class' => true,
            'id' => true,
            'data-*' => true,
            'role' => true,
            'aria-labelledby' => true,
            'value' => true,
            'aria-modal' => true,
            'tabindex' => true,
            'font-family' => true,
        );

        $allowed_tags['li'] = array(
            'style' => true,
            'class' => true,
            'id' => true,
            'data-*' => true,
            'role' => true,
            'aria-labelledby' => true,
            'value' => true,
            'aria-modal' => true,
            'tabindex' => true
        );

        $allowed_tags['span'] = array(
            'style' => true,
            'class' => true,
            'id' => true,
            'data-*' => true,
            'aria-hidden' => true
        );

        $allowed_tags['style'] = array(
            'class' => true,
            'id' => true,
            'type' => true,
            'font-family' => true,
        );

        $allowed_tags['fieldset'] = array(
            'class' => true,
            'id' => true,
            'type' => true
        );

        $allowed_tags['link'] = array(
            'class' => true,
            'id' => true,
            'type' => true,
            'rel' => true,
            'href' => true,
            'media' => true
        );

        $allowed_tags['form'] = array(
            'style' => true,
            'class' => true,
            'id' => true,
            'method' => true,
            'action' => true,
            'data-*' => true
        );

        echo wp_kses($html, $allowed_tags);

        add_filter('safe_style_css', function ($styles) {
            $styles_wf = array(
                'text-align',
                'margin',
                'color',
                'float',
                'border',
                'background',
                'background-color',
                'border-bottom',
                'border-bottom-color',
                'border-bottom-style',
                'border-bottom-width',
                'border-collapse',
                'border-color',
                'border-left',
                'border-left-color',
                'border-left-style',
                'border-left-width',
                'border-right',
                'border-right-color',
                'border-right-style',
                'border-right-width',
                'border-spacing',
                'border-style',
                'border-top',
                'border-top-color',
                'border-top-style',
                'border-top-width',
                'border-width',
                'caption-side',
                'clear',
                'cursor',
                'direction',
                'font',
                'font-family',
                'font-size',
                'font-style',
                'font-variant',
                'font-weight',
                'height',
                'letter-spacing',
                'line-height',
                'margin-bottom',
                'margin-left',
                'margin-right',
                'margin-top',
                'overflow',
                'padding',
                'padding-bottom',
                'padding-left',
                'padding-right',
                'padding-top',
                'text-decoration',
                'text-indent',
                'vertical-align',
                'width'
            );

            foreach ($styles_wf as $style_wf) {
                if (($key = array_search($style_wf, $styles)) !== false) {
                    unset($styles[$key]);
                }
            }
            return $styles;
        });
    }

    function is_plugin_installed($slug)
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins = get_plugins();

        if (!empty($all_plugins[$slug])) {
            return true;
        } else {
            return false;
        }
    } // is_plugin_installed

    // auto download / install / activate WP Force SSL plugin
    function install_wpfssl()
    {
        check_ajax_referer('install_wpfssl');

        if (false === current_user_can('administrator')) {
            wp_die('Sorry, you have to be an admin to run this action.');
        }

        $plugin_slug = 'wp-force-ssl/wp-force-ssl.php';
        $plugin_zip = 'https://downloads.wordpress.org/plugin/wp-force-ssl.latest-stable.zip';

        @include_once ABSPATH . 'wp-admin/includes/plugin.php';
        @include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        @include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        @include_once ABSPATH . 'wp-admin/includes/file.php';
        @include_once ABSPATH . 'wp-admin/includes/misc.php';
        echo '<style>
		body{
			font-family: sans-serif;
			font-size: 14px;
			line-height: 1.5;
			color: #444;
		}
		</style>';

        echo '<div style="margin: 20px; color:#444;">';
        echo 'If things are not done in a minute <a target="_parent" href="' . esc_url(admin_url('plugin-install.php?s=force%20ssl%20webfactory&tab=search&type=term')) . '">install the plugin manually via Plugins page</a><br><br>';
        echo 'Starting ...<br><br>';

        wp_cache_flush();
        $upgrader = new Plugin_Upgrader();
        echo 'Check if WP Force SSL is already installed ... <br />';
        if ($this->is_plugin_installed($plugin_slug)) {
            echo 'WP Force SSL is already installed! <br /><br />Making sure it\'s the latest version.<br />';
            $upgrader->upgrade($plugin_slug);
            $installed = true;
        } else {
            echo 'Installing WP Force SSL.<br />';
            $installed = $upgrader->install($plugin_zip);
        }
        wp_cache_flush();

        if (!is_wp_error($installed) && $installed) {
            echo 'Activating WP Force SSL.<br />';
            $activate = activate_plugin($plugin_slug);

            if (is_null($activate)) {
                echo 'WP Force SSL Activated.<br />';

                echo '<script>setTimeout(function() { top.location = "admin.php?page=maintenance"; }, 1000);</script>';
                echo '<br>If you are not redirected in a few seconds - <a href="admin.php?page=maintenance" target="_parent">click here</a>.';
            }
        } else {
            echo 'Could not install WP Force SSL. You\'ll have to <a target="_parent" href="' . esc_url(admin_url('plugin-install.php?s=force%20ssl%20webfactory&tab=search&type=term')) . '">download and install manually</a>.';
        }

        echo '</div>';
    } // install_wpfssl

    // auto download / install / activate Advanced Google reCAPTCHA plugin
    function install_wpcaptcha()
    {
        check_ajax_referer('install_wpcaptcha');

        if (false === current_user_can('administrator')) {
            wp_die('Sorry, you have to be an admin to run this action.');
        }

        $plugin_slug = 'advanced-google-recaptcha/advanced-google-recaptcha.php';
        $plugin_zip = 'https://downloads.wordpress.org/plugin/advanced-google-recaptcha.latest-stable.zip';

        @include_once ABSPATH . 'wp-admin/includes/plugin.php';
        @include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        @include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        @include_once ABSPATH . 'wp-admin/includes/file.php';
        @include_once ABSPATH . 'wp-admin/includes/misc.php';
        echo '<style>
		body{
			font-family: sans-serif;
			font-size: 14px;
			line-height: 1.5;
			color: #444;
		}
		</style>';

        echo '<div style="margin: 20px; color:#444;">';
        echo 'If things are not done in a minute <a target="_parent" href="' . esc_url(admin_url('plugin-install.php?s=advanced%20recaptcha%20webfactory&tab=search&type=term')) . '">install the plugin manually via Plugins page</a><br><br>';
        echo 'Starting ...<br><br>';

        wp_cache_flush();
        $upgrader = new Plugin_Upgrader();
        echo 'Check if Advanced Google reCAPTCHA is already installed ... <br />';
        if ($this->is_plugin_installed($plugin_slug)) {
            echo 'Advanced Google reCAPTCHA is already installed! <br /><br />Making sure it\'s the latest version.<br />';
            $upgrader->upgrade($plugin_slug);
            $installed = true;
        } else {
            echo 'Installing Advanced Google reCAPTCHA.<br />';
            $installed = $upgrader->install($plugin_zip);
        }
        wp_cache_flush();

        if (!is_wp_error($installed) && $installed) {
            echo 'Activating Advanced Google reCAPTCHA.<br />';
            $activate = activate_plugin($plugin_slug);

            if (is_null($activate)) {
                echo 'Advanced Google reCAPTCHA Activated.<br />';

                echo '<script>setTimeout(function() { top.location = "admin.php?page=maintenance"; }, 1000);</script>';
                echo '<br>If you are not redirected in a few seconds - <a href="admin.php?page=maintenance" target="_parent">click here</a>.';
            }
        } else {
            echo 'Could not install Advanced Google reCAPTCHA. You\'ll have to <a target="_parent" href="' . esc_url(admin_url('plugin-install.php?s=advanced%20recaptcha%20webfactory&tab=search&type=term')) . '">download and install manually</a>.';
        }

        echo '</div>';
    } // install_wpcaptcha

    // auto download / install / activate Weglot plugin
    function install_weglot()
    {
        check_ajax_referer('install_weglot');

        if (false === current_user_can('administrator')) {
            wp_die('Sorry, you have to be an admin to run this action.');
        }

        $plugin_slug = 'weglot/weglot.php';
        $plugin_zip = 'https://downloads.wordpress.org/plugin/weglot.latest-stable.zip';

        @include_once ABSPATH . 'wp-admin/includes/plugin.php';
        @include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        @include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        @include_once ABSPATH . 'wp-admin/includes/file.php';
        @include_once ABSPATH . 'wp-admin/includes/misc.php';
        echo '<style>
      body{
          font-family: sans-serif;
          font-size: 14px;
          line-height: 1.5;
          color: #444;
      }
      </style>';

        echo '<div style="margin: 20px; color:#444;">';
        echo 'If things are not done in a minute <a target="_parent" href="' . esc_url(admin_url('plugin-install.php?s=weglot&tab=search&type=term')) . '">install the plugin manually via Plugins page</a><br><br>';
        echo 'Starting ...<br><br>';

        wp_cache_flush();
        $upgrader = new Plugin_Upgrader();
        echo 'Check if Weglot is already installed ... <br />';
        if ($this->is_plugin_installed($plugin_slug)) {
            echo 'Weglot is already installed! <br /><br />Making sure it\'s the latest version.<br />';
            $upgrader->upgrade($plugin_slug);
            $installed = true;
        } else {
            echo 'Installing Weglot.<br />';
            $installed = $upgrader->install($plugin_zip);
        }
        wp_cache_flush();

        if (!is_wp_error($installed) && $installed) {
            echo 'Activating Weglot.<br />';
            $activate = activate_plugin($plugin_slug);

            if (is_null($activate)) {
                echo 'Weglot Activated.<br />';

                echo '<script>setTimeout(function() { top.location = "admin.php?page=maintenance"; }, 1000);</script>';
                echo '<br>If you are not redirected in a few seconds - <a href="admin.php?page=maintenance" target="_parent">click here</a>.';
            }
        } else {
            echo 'Could not install Weglot. You\'ll have to <a target="_parent" href="' . esc_url(admin_url('plugin-install.php?s=weglot&tab=search&type=term')) . '">download and install manually</a>.';
        }

        echo '</div>';
    } // install_weglot

    /**
     * Disabled; we use singleton pattern so magic functions need to be disabled
     *
     * @return null
     */
    public function __clone() {}

    /**
     * Disabled; we use singleton pattern so magic functions need to be disabled
     *
     * @return null
     */
    public function __sleep() {}

    /**
     * Disabled; we use singleton pattern so magic functions need to be disabled
     *
     * @return null
     */
    public function __wakeup() {}
} // MTNC class

// Create plugin instance and hook things up
global $wf_mtnc; //phpcs:ignore
$wf_mtnc = MTNC::getInstance(); //phpcs:ignore
