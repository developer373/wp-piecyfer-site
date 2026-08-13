<?php
// include only file
if (!defined('ABSPATH')) {
    die('Do not open this file directly.');
}

global $wf_mtnc;

$mtnc_mess_arr    = $wf_mtnc->frontend_login();
$mtnc_ebody_class = '';

$mtnc_options       = $wf_mtnc->get_options();
$mtnc_site_title       = str_replace('%sitetagline%', get_bloginfo('description'), str_replace('%sitetitle%', get_bloginfo('name'), $mtnc_options['title']));
$mtnc_site_description = $mtnc_options['description'];
$mtnc_theme_id = $mtnc_options['theme_global'];
if(isset($_GET['maintenance-preview']) && strlen($_GET['maintenance-preview']) == 32 && array_key_exists($_GET['maintenance-preview'], $mtnc_options['themes'])){ //phpcs:ignore
    $mtnc_theme_id = esc_attr($_GET['maintenance-preview']); //phpcs:ignore
}
$mtnc_theme = $mtnc_options['themes'][$mtnc_theme_id];

if($mtnc_options['no_cache_headers']){
    nocache_headers();
}

if ($mtnc_options['blockse']) {
    $mtnc_protocol = 'HTTP/1.0';
    if ($_SERVER['SERVER_PROTOCOL'] === 'HTTP/1.1') { //phpcs:ignore
        $mtnc_protocol = 'HTTP/1.1';
    }

    header($mtnc_protocol . ' 503 Service Unavailable', true, 503);
    header('Retry-After: 3600');
}

$mtnc_logo       = (isset($mtnc_options['social_preview']) && !empty($mtnc_options['social_preview'])) ? esc_attr($mtnc_options['social_preview']) : null;
$mtnc_logo_ext   = null;

if (!empty($mtnc_logo)) {
    $mtnc_logo_ext = pathinfo($mtnc_logo, PATHINFO_EXTENSION);
    $mtnc_logo_ext = str_replace('.', '', $mtnc_logo_ext);
}

if (!empty($mtnc_options['background_image'])) {
    $mtnc_body_bg = $mtnc_options['background_image'];
}

$mtnc_bunny_fonts = $wf_mtnc->add_bunny_fonts($mtnc_theme);
if(!empty($mtnc_theme->body_font) && !in_array($mtnc_theme->body_font, array('Arial','Helvetica','Georgia','Times New Roman','Tahoma','Verdana','Geneva'))){
    $mtnc_bunny_fonts[] = $mtnc_theme->body_font;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php esc_attr(bloginfo('charset')); ?>" />
    <?php
    if (function_exists('wp_site_icon')) {
        wp_site_icon();
    }
    ?>
    <title><?php echo esc_attr($mtnc_site_title); ?></title>
    <?php if (isset($mtnc_options['favicon']) && !empty($mtnc_options['favicon'])){ ?>
        <link rel="shortcut icon" href="<?php echo esc_url_raw($mtnc_options['favicon']); ?>" />
    <?php } ?>

    <meta name="viewport" content="width=device-width, maximum-scale=1, initial-scale=1, minimum-scale=1">
    <meta name="description" content="<?php echo esc_attr($mtnc_site_description); ?>" />
    <meta http-equiv="X-UA-Compatible" content="" />
    <meta property="og:site_name" content="<?php echo esc_attr($mtnc_site_title) . ' - ' . esc_attr($mtnc_site_description); ?>" />
    <meta property="og:title" content="<?php echo esc_attr($mtnc_site_title); ?>" />
    <meta property="og:type" content="Maintenance" />
    <meta property="og:url" content="<?php echo esc_url(site_url()); ?>" />
    <meta property="og:description" content="<?php echo esc_attr($mtnc_site_description); ?>" />
    <?php
    if (!empty($mtnc_logo)) {
    ?>
        <meta property="og:image" content="<?php echo esc_url($mtnc_logo); ?>" />
        <meta property="og:image:url" content="<?php echo esc_url($mtnc_logo); ?>" />
        <meta property="og:image:secure_url" content="<?php echo esc_url($mtnc_logo); ?>" />
        <meta property="og:image:type" content="<?php echo esc_attr($mtnc_logo_ext); ?>" />
    <?php
    }
    ?>
    <link rel="profile" href="http://gmpg.org/xfn/11" />
    <link rel="pingback" href="<?php esc_url(bloginfo('pingback_url')); ?>" />

    <?php
    global $wp_styles;
    
    if(!empty($mtnc_bunny_fonts)) {
        echo '<link rel="stylesheet" href="' . esc_url('https://fonts.bunny.net/css?family=' . implode('|', $mtnc_bunny_fonts)). '">'; //phpcs:ignore
    }
    echo '<script type="text/javascript" src="' . esc_url(includes_url('js/jquery/jquery.js')) . '"></script>'; //phpcs:ignore
    echo '<script src="' . esc_url(MTNC_URL . 'frontend/js/frontend.js?ver=' . $wf_mtnc->version) . '" id="frontend-js"></script>'; //phpcs:ignore
    echo '<link rel="stylesheet" href="' . esc_url( includes_url( 'css/dashicons.min.css' ) ) . '">';
    
    // all.css loading in index.php inline by 2 steps
    wp_register_style('mtnc-style', MTNC_URI . 'frontend/css/style.css', '', filemtime(MTNC_PATH . 'frontend/css/style.css'));
    $wp_styles->do_items('mtnc-style');


    $mtnc_options_style = '';
    if (!empty($mtnc_theme->background_color)) {
        $mtnc_background_color = esc_attr($mtnc_theme->background_color);
        $mtnc_options_style .= 'body {background-color: ' . $mtnc_background_color . '}';
        $mtnc_options_style .= '.preloader {background-color: ' . $mtnc_background_color . '}';
    }

    if (!empty($mtnc_options['analytics'])) {
        echo "<script>
        (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
        (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
        m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
        })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');
        ga('create', '" . esc_html($mtnc_options['analytics']) . "', 'auto');
        ga('send', 'pageview');
      </script>";
    }

    // Background cover
    if (!empty($mtnc_theme->background_image)) {
        $mtnc_options_style .=  '
        .mtnc-background-image{
            background-image: url("' . esc_html($mtnc_theme->background_image) . '");
            width: 110%;
            height: 110%;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            margin:-5%;';
        if (!empty($mtnc_theme->background_size_opt)) {
            $mtnc_options_style .=  'background-size: ' . esc_html($mtnc_theme->background_size_opt) . ';';
        }
        if (!empty($mtnc_theme->background_position)) {
            $mtnc_options_style .=  'background-position: ' . esc_html($mtnc_theme->background_position) . ';';
        }
        $mtnc_options_style .=  '
        }' . "\r\n";
    }

    if (!empty($mtnc_theme->background_blur) && $mtnc_theme->background_blur > 0) {
        $mtnc_options_style .= '.mtnc-background-image{';
        $mtnc_options_style .= '-webkit-filter: blur(' . esc_html($mtnc_theme->background_blur) . 'px);';
        $mtnc_options_style .= '-moz-filter: blur(' . esc_html($mtnc_theme->background_blur) . 'px);';
        $mtnc_options_style .= '-o-filter: blur(' . esc_html($mtnc_theme->background_blur) . 'px);';
        $mtnc_options_style .= '-ms-filter: blur(' . esc_html($mtnc_theme->background_blur) . 'px);';
        $mtnc_options_style .= 'filter:blur(' . esc_html($mtnc_theme->background_blur) . 'px);';
        $mtnc_options_style .= 'filter:progid:DXImageTransform.Microsoft.Blur(PixelRadius=' . esc_html($mtnc_theme->background_blur) . ', enabled=\'true\');';
        $mtnc_options_style .= '}';
    }


    if (!empty($mtnc_theme->body_font)) {
        $mtnc_options_style .= 'body {font-family: ' . esc_attr($mtnc_theme->body_font) . '; }';
    }

    if (!empty($mtnc_theme->body_link_color)) {
        $mtnc_a_color     = esc_attr($mtnc_theme->body_link_hover_color);
        $mtnc_options_style .= 'a, #login-form a.lost-pass, .maintenance a{color: ' . $mtnc_a_color . '; }';
        $mtnc_options_style .= 'a:hover, .login-form a.lost-pass:hover, .maintenance a:hover{color: ' . $mtnc_a_color . '; }';
    }

    if (!empty($mtnc_theme->body_font_color)) {
        $mtnc_font_color     = esc_attr($mtnc_theme->body_font_color);
        $mtnc_options_style .= 'body, #login-form, .site-title, .preloader i, .login-form, .login-form a.lost-pass, .btn-open-login-form, .site-content, .user-content-wrapper, .user-content, footer{color: ' . $mtnc_font_color . ';} ';
        $mtnc_options_style .= 'a.close-user-content, #mailchimp-box form input[type="submit"], .login-form input#submit.button  {border-color:' . $mtnc_font_color . '} ';
        $mtnc_options_style .= 'input[type="submit"]:hover{background-color:' . $mtnc_font_color . '} ';
        $mtnc_options_style .= 'input:-webkit-autofill, input:-webkit-autofill:focus{-webkit-text-fill-color:' . $mtnc_font_color . '} ';
    }

    if (!empty($mtnc_theme->login_background_color)) {
        $mtnc_login_background_color = esc_attr($mtnc_theme->login_background_color);
        $mtnc_options_style .= "body .login-form-container{background-color:{$mtnc_login_background_color}}";
        $mtnc_options_style .= ".btn-open-login-form,.side-button{background-color:{$mtnc_login_background_color}}";
        $mtnc_options_style .= "input:-webkit-autofill, input:-webkit-autofill:focus{-webkit-box-shadow:0 0 0 50px {$mtnc_login_background_color} inset}";
        $mtnc_options_style .= "input[type='submit']:hover{color:{$mtnc_login_background_color}} ";
        $mtnc_options_style .= "#custom-subscribe #submit-subscribe:before{background-color:{$mtnc_login_background_color}} ";
    }

    if (!empty($mtnc_theme->custom_css)) {
        $mtnc_options_style .= wp_kses_stripslashes($mtnc_theme->custom_css);
    }

    if($mtnc_theme->content_overlay){
        $mtnc_overlay_bg_color = esc_attr($mtnc_theme->content_overlay_color);
        $mtnc_options_style .= '.mtnc-overlay{background-color: ' . esc_attr($mtnc_theme->content_overlay_color) . ';}';
        if(isset($mtnc_theme->content_overlay_shadow_color)) {
            $mtnc_options_style .= '.mtnc-overlay{box-shadow: 0 0 10px 0 ' . esc_attr($mtnc_theme->content_overlay_shadow_color) . ';}';
        }
        $mtnc_options_style .= '.mtnc-overlay{max-width: ' . esc_attr($mtnc_theme->content_width) . 'px;}';

        

        switch($mtnc_theme->content_position){
            case 'left':
                $mtnc_options_style .= '.mtnc-overlay{margin: 80px auto auto 80px;}';
            break;
            case 'right':
                $mtnc_options_style .= '.mtnc-overlay{margin: 80px 80px auto auto;}';
            break;
            case 'center':
                $mtnc_options_style .= '.mtnc-overlay{margin: 80px auto auto auto;}';
            break;
            case 'middle':
                $mtnc_options_style .= '.mtnc-overlay{margin: 0; position: absolute; top: 50%; left: 50%; width: 100%; transform: translate(-50%, -50%); padding-bottom:50px;}';
            break;
            case 'bottom':
                $mtnc_options_style .= '.mtnc-overlay{margin: 0 auto; position: absolute; left: 50%; transform: translate(-50%, -5%); bottom: 0; padding-bottom:50px;}';
            break;
        }
    }

    echo '<style type="text/css">';
    $wf_mtnc::wp_kses_wf($mtnc_options_style);
    echo '</style>';

    if(!empty($theme->custom_css)){
        echo '<style type="text/css">';
        $wf_mtnc::wp_kses_wf($theme->custom_css);
        echo '</style>';
    }

    

    ?>
    <?php do_action('mtnc_add_gg_analytics_code'); ?>
   
</head>

<body class="maintenance <?php echo esc_html($mtnc_ebody_class); ?>">

<div class="preloader">
<?php 
if($mtnc_theme->preloader_background_image){
    echo '<img src="' . esc_html($mtnc_theme->preloader_background_image) . '" />';
} else {
    echo '<i class="fi-widget" aria-hidden="true"></i>';
}
?>
</div>

<?php do_action('mtnc_before_main_container'); ?>
    <div class="main-container">
        <?php if(!$wf_mtnc->check_maintenance_locked()){ ?>
            <?php do_action('mtnc_before_content_section'); ?>
            <div id="wrapper">
                <?php
                $mtnc_meta_overlay_html = '';  // HTML that should not be inside the overlay as it is translated via CSS and it breaks position
                if($mtnc_theme->content_overlay){
                    echo '<div class="mtnc-overlay">';
                }

                $mtnc_load_recaptcha = false;
                foreach ($mtnc_theme->modules_order as $mtnc_module_id) {
                    if(!property_exists($mtnc_theme->modules, $mtnc_module_id)){
                        continue;
                    }
                    $mtnc_module = $mtnc_theme->modules->{$mtnc_module_id};
                    $mtnc_module_style = $wf_mtnc->get_module_style($mtnc_module);
                    
                    echo '<div class="mtnc_module" id="' . esc_attr($mtnc_module_id) . '" style="' . esc_html($mtnc_module_style) . '">';

                    if(!$mtnc_theme->content_overlay){
                        echo '<div style="margin: 0 auto; overflow: hidden; max-width: ' . esc_attr($mtnc_theme->content_width) . 'px">';
                    }

                    switch ($mtnc_module->type) {
                        case 'logo':
                            $mtnc_styles = '';
                            if (!empty($mtnc_module->groups->logo->fields->width->value)) {
                                $mtnc_styles .= 'width:' . $mtnc_module->groups->logo->fields->width->value . str_replace('percent', '%', $mtnc_module->groups->logo->fields->width->unit_value) . ';';
                            }
                            if (!empty($mtnc_module->groups->logo->fields->height->value)) {
                                $mtnc_styles .= 'height:' . $mtnc_module->groups->logo->fields->height->value . str_replace('percent', '%', $mtnc_module->groups->logo->fields->height->unit_value) . ';';
                            }
                            $wf_mtnc::wp_kses_wf('<img ' . (!empty($mtnc_module->groups->logo->fields->title->value) ? 'title="' . $mtnc_module->groups->logo->fields->title->value . '"' : '') . ' style="' . $mtnc_styles . '" src="' . $mtnc_module->groups->logo->fields->logo->value . '" />');
                            break;
                        case 'header':
                            $mtnc_styles = '';
                            if (!empty($mtnc_module->groups->header->fields->font->value)) {
                                $mtnc_styles .= 'font-family:' . $mtnc_module->groups->header->fields->font->value . ';';
                            }
                            if (!empty($mtnc_module->groups->header->fields->font_size->value)) {
                                $mtnc_styles .= 'font-size:' . $mtnc_module->groups->header->fields->font_size->value . $mtnc_module->groups->header->fields->font_size->unit_value . ';';
                            }
                            if (!empty($mtnc_module->groups->header->fields->color->value)) {
                                $mtnc_styles .= 'color:' . $mtnc_module->groups->header->fields->color->value . ';';
                            }
                            if (!empty($mtnc_module->groups->header->fields->line_height->value)) {
                                $mtnc_styles .= 'line-height:' . $mtnc_module->groups->header->fields->line_height->value . $mtnc_module->groups->header->fields->line_height->unit_value . ';';
                            }
                            if (!empty($mtnc_module->groups->header->fields->text_align->value)) {
                                $mtnc_styles .= 'text-align:' . $mtnc_module->groups->header->fields->text_align->value . ';';
                            }
                            $wf_mtnc::wp_kses_wf('<h2 style="' . $mtnc_styles . '">' . $mtnc_module->groups->header->fields->text->value . '</h2>');
                            break;
                        case 'footer':
                        case 'content':
                            $mtnc_styles = '';
                            if (!empty($mtnc_module->groups->col1->fields->font->value)) {
                                $mtnc_styles .= 'font-family:\'' . $mtnc_module->groups->col1->fields->font->value . '\';';
                            }
                            if (!empty($mtnc_module->groups->col1->fields->font_size->value)) {
                                $mtnc_styles .= 'font-size:' . $mtnc_module->groups->col1->fields->font_size->value . $mtnc_module->groups->col1->fields->font_size->unit_value . ';';
                            }
                            if (!empty($mtnc_module->groups->col1->fields->color->value)) {
                                $mtnc_styles .= 'color:' . $mtnc_module->groups->col1->fields->color->value . ';';
                            }
                            if (!empty($mtnc_module->groups->col1->fields->line_height->value)) {
                                $mtnc_styles .= 'line-height:' . $mtnc_module->groups->col1->fields->line_height->value . $mtnc_module->groups->col1->fields->line_height->unit_value . ';';
                            }
                            $wf_mtnc::wp_kses_wf('<div style="' . $mtnc_styles . '">' . $mtnc_module->groups->col1->fields->text->value . '</div>');
                            break;
                    }

                    if(!$mtnc_theme->content_overlay){
                        echo '</div>';
                    }

                    echo '</div>';
                }

                if($mtnc_theme->content_overlay){
                    echo '</div>';
                }

                $wf_mtnc::wp_kses_wf($mtnc_meta_overlay_html);
                
                ?>
            </div> <!-- end wrapper -->
            <?php
            if($mtnc_load_recaptcha){
                echo '<script src="https://www.google.com/recaptcha/api.js"></script>'; //phpcs:ignore
            }
            ?>
            <?php do_action('mtnc_after_content_section'); ?>
            <?php do_action('mtnc_user_content_section'); ?>
        <?php } // check_site_locked ?>
        <?php
        if ($mtnc_theme->background_type == 'video') {
            if (stripos($mtnc_theme->background_video_fallback, 'undefined index') !== false) {
                $mtnc_theme->background_video_fallback = '';
            }
            if(strpos($mtnc_theme->background_video, '?') !== false){
                $mtnc_bg_video = explode('?', $mtnc_theme->background_video, 2);
                $mtnc_theme->background_video = $mtnc_bg_video[0];
            }
            echo '<div class="video-background"><div class="video-foreground ' . (!empty($mtnc_theme->background_video_fallback) ? 'mobile-fallback' : '') . esc_html($mtnc_theme->background_video_filter) . '" ' . (!empty($mtnc_theme->background_video_fallback) ? 'style="background-image:url(' . esc_html($mtnc_theme->background_video_fallback) . ');"' : '') . '>
            <iframe src="https://www.youtube.com/embed/' . esc_html($mtnc_theme->background_video) . '?controls=0&amp;showinfo=0&amp;rel=0&amp;autoplay=1&loop=1&amp;mute=1&version=3&playlist=' . esc_html($mtnc_theme->background_video) . '" frameborder="0"></iframe></div></div>';
        } else {
            echo '<div class="mtnc-background-image ' . esc_html($mtnc_theme->background_image_filter) . '"></div>';
        }
        ?>
    </div>
    
    <?php do_action('mtnc_after_main_container'); ?>
    <?php 
    if (isset($mtnc_options['login_button']) && $mtnc_options['login_button'] == 1){ 
    ?>
        <div class="login-form-container" style="display:block;">
            <?php
            $mtnc_mess_arr    = $wf_mtnc->get_custom_login_code();
            if (!empty($mtnc_mess_arr[0])) {
                $mtnc_ebody_class = 'error';
            }

            $mtnc_user_login = esc_attr($mtnc_mess_arr[3]);
            $mtnc_class_login = esc_attr($mtnc_mess_arr[1]);
            $mtnc_class_password = esc_attr($mtnc_mess_arr[2]);
            $mtnc_error = esc_attr($mtnc_mess_arr[0]);

            $mtnc_out_login_form = $mtnc_form_error = '';
            if (($mtnc_class_login === 'error') || ($mtnc_class_password === 'error')) {
              $mtnc_form_error = ' active error';
            }
        
            echo '<form name="login-form" id="login-form" class="login-form' . esc_html($mtnc_form_error) . '" method="post">';
            echo '<label>' . esc_html__('User Login', 'maintenance') . '</label>';
            echo '<span class="login-error">' . esc_html($mtnc_error) . '</span>';
            echo '<span class="licon ' . esc_html($mtnc_class_login) . '"><input type="text" name="log" id="log" value="' . esc_html($mtnc_user_login) . '" size="20"  class="input username" placeholder="' . esc_html__('Username', 'maintenance') . '"/></span>';
            echo '<span class="picon ' . esc_html($mtnc_class_password) . '"><input type="password" name="pwd" id="login_password" value="" size="20"  class="input password" placeholder="' . esc_html__('Password', 'maintenance') . '" /></span>';
            echo '<a class="lost-pass" href="' . esc_url(wp_lostpassword_url()) . '" title="' . esc_html__('Lost Password', 'maintenance') . '">' . esc_html__('Lost Password', 'maintenance') . '</a>';
            echo '<input type="submit" class="button" name="submit" id="submit" value="' . esc_html__('Login', 'maintenance') . '" tabindex="4" />';
            echo '<input type="hidden" name="is_custom_login" value="1" />';
            $wf_mtnc::wp_kses_wf(wp_nonce_field('mtnc_login', 'mtnc_login_check'));
            echo '</form>';
            ?>

            <div id="btn-open-login-form" class="side-button login-button" alt="<?php echo esc_html($mtnc_options['wplogin_button_tooltip']); ?>" title="<?php echo esc_html($mtnc_options['wplogin_button_tooltip']); ?>">
              <i class="fab fa-wordpress"></i>
            </div>
        </div>

        
    <?php 
    }     

    if ($mtnc_options['password_button'] == '1') {
        echo '<div class="side-button auth-button" title="' . (isset($mtnc_options['login_button_tooltip']) ? esc_html($mtnc_options['login_button_tooltip']) : 'Direct Access login') . '" id="mtnc-access-show-form"><i class="fas fa-unlock-alt"></i></div>';
    }

    do_action('mtnc_load_options_style');
    do_action('mtnc_load_custom_scripts');

    if (!is_callable('is_plugin_active')) {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }
?>

</body>

</html>