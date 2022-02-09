<?php
/*
Plugin Name: HandL UTM Grabber
Plugin URI: https://www.haktansuren.com/handl-utm-grabber
Description: The easiest way to capture UTMs on your (optin) forms.
Author: Haktan Suren
Version: 2.7.20
Author URI: https://www.haktansuren.com/
*/

define( 'HANDL_UTM_V3_LINK', 'https://utmgrabber.com' );

require_once "external/zapier.php";

add_filter('widget_text', 'do_shortcode');

add_action('init', 'CaptureUTMs');
function CaptureUTMs(){

    if ( is_admin() || $GLOBALS['pagenow'] === 'wp-login.php' || defined( 'DOING_CRON' ) ) {
        return "";
    }

	if (!isset($_COOKIE['handl_original_ref']))
		$_COOKIE['handl_original_ref'] = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

	if (!isset($_COOKIE['handl_landing_page']) && isset($_SERVER["SERVER_NAME"]) && isset($_SERVER["REQUEST_URI"]))
		$_COOKIE['handl_landing_page'] = ( isset($_SERVER["HTTPS"]) ? 'https://' : 'http://' ) . $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];

	if(isset($_SERVER["HTTP_X_FORWARDED_FOR"]) && $_SERVER["HTTP_X_FORWARDED_FOR"] != "")
		$_COOKIE['handl_ip'] = $_SERVER["HTTP_X_FORWARDED_FOR"];
	else
		$_COOKIE['handl_ip'] = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : '';

	$_COOKIE['handl_ref'] =  isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

	if (isset($_SERVER["SERVER_NAME"]) && isset($_SERVER["REQUEST_URI"]))
	    $_COOKIE['handl_url'] =  ( isset($_SERVER["HTTPS"]) ? 'https://' : 'http://' ) . $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];

	$fields = array('utm_source','utm_medium','utm_term', 'utm_content', 'utm_campaign', 'gclid', 'handl_original_ref', 'handl_landing_page', 'handl_ip', 'handl_ref', 'handl_url', 'email', 'username');

    $cookie_field = '';
	foreach ($fields as $id=>$field){
		if (isset($_GET[$field]) && $_GET[$field] != '')
			$cookie_field = htmlspecialchars($_GET[$field],ENT_QUOTES, 'UTF-8');
		elseif(isset($_COOKIE[$field]) && $_COOKIE[$field] != ''){
			$cookie_field = $_COOKIE[$field];
		}else{
			$cookie_field = '';
		}

		$domain = isset($_SERVER["SERVER_NAME"]) ? $_SERVER["SERVER_NAME"] : '';
		if ( strtolower( substr($domain, 0, 4) ) == 'www.' ) $domain = substr($domain, 4);
        if ( substr($domain, 0, 1) != '.' && $domain != "localhost" && $domain != "handl-sandbox" ) $domain = '.'.$domain;

		setcookie($field, $cookie_field , time()+60*60*24*30, '/', $domain );

		$_COOKIE[$field] = $cookie_field;

		add_shortcode($field, function() use ($field) {return urldecode($_COOKIE[$field]);});
		add_shortcode($field."_i", function($atts,$content) use ($field) {return sprintf($content,urldecode($_COOKIE[preg_replace("/_i$/","",$field)]));});

		//This is for Gravity Forms
		add_filter( 'gform_field_value_'.$field, function() use ($field) {return urldecode($_COOKIE[$field]); } );
	}
}

function handl_utm_grabber_enqueue(){
	wp_enqueue_script( 'js.cookie', plugins_url( '/js/js.cookie.js' , __FILE__ ), array( 'jquery' ) );
	wp_enqueue_script( 'handl-utm-grabber', plugins_url( '/js/handl-utm-grabber.js' , __FILE__ ), array( 'jquery','js.cookie' ) );
	wp_localize_script( 'handl-utm-grabber', 'handl_utm', HUGGenerateUTMsForURL() );
}
add_action( 'wp_enqueue_scripts', 'handl_utm_grabber_enqueue' );

function handl_utm_grabber_enqueue_admin(){
    wp_register_script( 'handl-utm-grabber-admin', plugins_url( '/js/admin.js' , __FILE__ ), array( 'jquery') );
    wp_register_style( 'handl-utm-grabber-admin-css', plugins_url('/css/admin.css', __FILE__) );
    wp_enqueue_style('handl-utm-grabber-admin-css');
}
add_action( 'admin_enqueue_scripts', 'handl_utm_grabber_enqueue_admin' );

function handl_utm_grabber_enable_shortcode($val){
	return do_shortcode($val);
}
add_filter('salesforce_w2l_field_value', 'handl_utm_grabber_enable_shortcode');
add_filter( 'wpcf7_form_elements', 'handl_utm_grabber_enable_shortcode' );

function handl_utm_grabber_couponhunt_theme_support($value, $post_id, $field){
	if ( get_option( 'hug_append_all' ) == 1 )
		return add_query_arg( HUGGenerateUTMsForURL(), $value );
	else
		return $value;
}
add_filter( "acf/load_value/name=url", "handl_utm_grabber_couponhunt_theme_support", 10, 3);

function handl_utm_grabber_menu() {

    add_menu_page(
        'HandL UTM Grabber',
        'UTM',
        'manage_options',
        'handl-utm-grabber.php',
        'handl_utm_grabber_menu_page',
        get_icon_svg_handl(),
        '99.3875'
    );

	add_submenu_page(
		'handl-utm-grabber.php',
		'Apps',
		'Apps',
		'manage_options',
		'handl_apps',
		'handl_apps'
	);

    add_submenu_page(
        'handl-utm-grabber.php',
        '',
        '<span style="font-size: 17px"></span> Premium',
        'manage_options',
        'handl_go_premium',
        'handl_go_premium'
    );

	add_action( 'admin_init', 'register_handl_utm_grabber_settings' );
}
add_action( 'admin_menu', 'handl_utm_grabber_menu' );

function handl_go_premium(){

    if ( empty( $_GET['page'] ) ) {
        return;
    }

    if ( 'handl_go_premium' === $_GET['page'] ) {
        wp_redirect( handl_v3_generate_links('HandL_Go_Premium_Link','','wordpress_menu_link') ) ;
        die;
    }
}

function handl_apps(){
	wp_enqueue_script('handl-utm-grabber-admin');

	?>
    <div class='wrap' id="handl-utm-apps">
        <h2><span class="dashicons dashicons-screenoptions" style='line-height: 1.1;font-size: 30px; padding-right: 10px;'></span> HandL UTM Grabber: Apps</h2>
        <p>We compiled the list of applications we highly recommend to you!</p>
            <div class="card">
                <a target="_blank" href="https://docs.utmgrabber.com/books/103-internal-apps/page/handl-gclid-reporter?utm_campaign=HandLGCLIDReporter&utm_source=WordPress_FREE&utm_medium=wordpress_apps_page">
                    <img src="<?php print(plugins_url('img/gclid_reporter.png',__FILE__));?>"></img>
                </a>
                <div class="container">
                    <a target="_blank" href="https://docs.utmgrabber.com/books/103-internal-apps/page/handl-gclid-reporter?utm_campaign=HandLGCLIDReporter&utm_source=WordPress_FREE&utm_medium=wordpress_apps_page">
                        <h4>
                            <b>GCLID Reporter (FREE*)</b>
                        </h4>
                    </a>
                    <p>If you are using Google Ads, you should try this app.<br><br>
                        *Temporarily
                    </p>
                </div>
            </div>
        </a>
    </div>
    <?php
}

function handl_on_admin_init(){
    handl_go_premium();
}
add_action( 'admin_init', 'handl_on_admin_init');


function register_handl_utm_grabber_settings() {
	register_setting( 'handl-utm-grabber-settings-group', 'hug_append_all' );
	register_setting( 'handl-utm-grabber-settings-group', 'hug_zapier_url' );
}

function handl_utm_grabber_menu_page(){
//    wp_enqueue_style('handl-utm-grabber-admin-css');
    wp_enqueue_script('handl-utm-grabber-admin');
?>
	<div class='wrap' id="handl-utm-menu">
		<h2><span class="dashicons dashicons-admin-settings" style='line-height: 1.1;font-size: 30px; padding-right: 10px;'></span> HandL UTM Grabber: Settings</h2>
		<form method='post' action='options.php'>
			<?php settings_fields( 'handl-utm-grabber-settings-group' ); ?>
			<?php do_settings_sections( 'handl-utm-grabber-settings-group' ); ?>
			<table class='form-table'>
				<tr>
					<th scope='row'>Append UTM</th>
					<td>
						<fieldset>
							<legend class='screen-reader-text'>
								<span>Append UTM</span>
							</legend>
							<label for='hug_append_all'>
								<input name='hug_append_all' id='hug_append_all' type='checkbox' value='1' <?php print checked( '1', get_option( 'hug_append_all' ) ) ?> />
								Append UTM variables to all the links automatically (BETA)
							</label>
							<p class='description' id='handl-utm-grabber-append-all-description'>This feature is still in BETA, please give us feedback <a target='blank' href='https://www.haktansuren.com/handl-utm-grabber/?utm_campaign=HandL+UTM+Grabber+Feedback&utm_content=Append+All+Feedback#reply-title'>here</a></p>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope='row'>Zapier Webhook URL</th>
					<td>
				        <fieldset>
							<legend class='screen-reader-text'>
								<span>Set Up Zapier!</span>
							</legend>
							<label for='hug_zapier_url'>
								<input style="width: 500px" name='hug_zapier_url' id='hug_zapier_url' type='text' value='<?php print get_option( 'hug_zapier_url' ) ? get_option( 'hug_zapier_url' ) : '' ?>'/>
							</label>
							<p class='description' id='handl-utm-grabber-zapier-description'>Check out the website to <a target='blank' href='https://www.haktansuren.com/zapier-for-contact-form-7-utms-lead-tracking-step-by-step/?utm_campaign=HandL+UTM+Grabber+Feedback&utm_content=Zapier'>learn more...</a></p>
							<?php if ( get_option( 'hug_zapier_log' ) ){ ?>
							<button class="accordion" type="button">View Zapier Log (Latest Call Made)</button>
                            <div class="panel">
                                <pre><?php print_r(get_option( 'hug_zapier_log' )); ?></pre>
                            </div>
							<?php } ?>
						</fieldset>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>

        <div class="utm-notifications">
            <h3>Notifications</h3>
            <?php
            $notifications = getHandLNotifications();
            foreach ($notifications as $key=>$n) {
                $nonce = wp_create_nonce($key);
                $ishide = get_user_option($key."_read");
                if (!$ishide) {
	                printf( "
                    <div class='handl-notification-holder'>
                        <div class='handl-notification'>%s</div>
                        <div class='handl-button'><a href='#' class='dismiss-handl-notification' data-hide='1' data-id='%s' data-nonce='%s'><span class='dashicons dashicons-hidden'></span></a></div>
                    </div>",
		                $n,
		                $key,
		                $nonce
	                );
                }
            }
            ?>
            <script>
                jQuery(document).on( 'click', '.dismiss-handl-notification', function() {
                    var thiss = jQuery(this)
                    jQuery.post(
                        ajaxurl,
                        {
                            'action': 'handl_mark_read_notifications',
                            'nonce': jQuery(this).data('nonce'),
                            'id': jQuery(this).data('id'),
                            'hide': jQuery(this).data('hide')
                        }
                    ).done(function( data ) {
                        var data = JSON.parse(data)
                        if (data.success){
                            thiss.parent().parent().remove()
                        }
                    });


                })
            </script>
    <!--        <p style="font-size:1.25em">🚨🚨🚨 <b>LIMITED TIME:</b> We are <b>doubling the number of licenses</b> for any plan only for the WP community. <a target="_blank" href="--><?php //print handl_v3_generate_links('4WPCommunity', '', 'wordpress_settings_page'); ?><!--">Click here</a> to score the deal. <b>Limited seat</b> available, act now!</p>-->
        </div>

        <div class="handl-upsell">
            <h2>Upgrade To HandL UTM Grabber V3</h2>
            <ul>
                <li>Track <b>all</b> UTM and custom parameters</li>
                <li>Google Ads <b>ValueTrack</b> Tracking</li>
                <li><b>Facebook Ads</b> Tracking</li>
                <li><b>First/Last touch</b> tracking</b></li>
                <li>Adjust <b>cookie time</b> as you wish</li>
                <li>Latest Wordpress &amp; PHP <b>7.4</b> Support</li>
                <li><b>Server &amp; client side</b> tracking</li>
                <li>EU GDPR <b>compliant</b> tracking</li>
                <li>Support for many major <b>opt-in forms</b></li>
                <li>Tracks <b>clientid</b> from Google Analytics</li>
                <li>Track source from <b>Organic</b> Traffic</li>
                <li>Seemless across <b>subdomain/domain-level tracking</b></li>
                <li>Pass <b>all tracked data</b> to different domains/iframes</li>
                <li><b>WooCommerce</b> Support</li>
                <li>Google <b>offline conversions</b></li>
                <li>Site 2 Site <b>(S2S) Postback</b></li>
                <li class="mute">Facebook <b>offline conversions</b> <code>Coming Soon</code></li>
                <li>And many more...</li>

            </ul>
            <a href="<?php print handl_v3_generate_links('HandL_Get_Premium_Button','','wordpress_settings_page'); ?>" target="_blank" class="handl-btn">Upgrade to V3</a>
        </div>
	</div>
<?php
}

function HUG_Append_All($content) {
  if ($content != '' && get_option( 'hug_append_all' ) == 1 ){
    if (!function_exists('str_get_html'))
      require_once('simple_html_dom.php');
    $html = str_get_html($content);

    $as = $html->find('a');

    $search = array();
    $replace = array();
    foreach ($as as $a){

      $a_original = $a->href;

      if ($a_original == '') continue;
      if (preg_match('/javascript:void/',$a_original)) continue;
      if (preg_match('/^#/',$a_original)) continue;

      $search[] = "/['\"]".preg_quote($a_original,'/')."['\"]/";
      $replace[] = add_query_arg( HUGGenerateUTMsForURL(), html_entity_decode($a_original) );
    }
    $content = preg_replace($search, $replace, $content);
  }
  return $content;
}
add_filter( 'the_content', 'HUG_Append_All', 999 );

function handl_utm_variables(){
    return array('utm_source','utm_medium','utm_term', 'utm_content', 'utm_campaign', 'gclid');
}

function HUGGenerateUTMsForURL(){
  $fields = handl_utm_variables();
  $utms = array();
  foreach ($fields as $id=>$field){
    if (isset($_COOKIE[$field]) && $_COOKIE[$field] != '')
      $utms[$field] = sanitizeQueryArgs($_COOKIE[$field]);
  }
  return $utms;
}

function sanitizeQueryArgs($cookie_str){
//    var_dump($cookie_str);
//    print "<br>";
    // To be continued :)
	$cookie_str = wp_strip_all_tags($cookie_str);
	$cookie_str = preg_replace("/onactivate|onafterprint|onafterscriptexecute|onanimationcancel|onanimationend|onanimationiteration|onanimationstart|onauxclick|onbeforeactivate|onbeforecopy|onbeforecut|onbeforedeactivate|onbeforepaste|onbeforeprint|onbeforescriptexecute|onbeforeunload|onbegin|onblur|onbounce|oncanplay|oncanplaythrough|onchange|onclick|onclose|oncontextmenu|oncopy|oncuechange|oncut|ondblclick|ondeactivate|ondrag|ondragend|ondragenter|ondragleave|ondragover|ondragstart|ondrop|ondurationchange|onend|onended|onerror|onfinish|onfocus|onfocusin|onfocusout|onfullscreenchange|onhashchange|oninput|oninvalid|onkeydown|onkeypress|onkeyup|onload|onloadeddata|onloadedmetadata|onloadend|onloadstart|onmessage|onmousedown|onmouseenter|onmouseleave|onmousemove|onmouseout|onmouseover|onmouseup|onmousewheel|onmozfullscreenchange|onpagehide|onpageshow|onpaste|onpause|onplay|onplaying|onpointerdown|onpointerenter|onpointerleave|onpointermove|onpointerout|onpointerover|onpointerrawupdate|onpointerup|onpopstate|onprogress|onreadystatechange|onrepeat|onreset|onresize|onscroll|onsearch|onseeked|onseeking|onselect|onselectionchange|onselectstart|onshow|onstart|onsubmit|ontimeupdate|ontoggle|ontouchend|ontouchmove|ontouchstart|ontransitioncancel|ontransitionend|ontransitionrun|ontransitionstart|onunhandledrejection|onunload|onvolumechange|onwaiting|onwebkitanimationend|onwebkitanimationiteration|onwebkitanimationstart|onwebkittransitionend|onwheel/i","",$cookie_str);
	$cookie_str = preg_replace("/\\\&quot;\s+/", '', $cookie_str);
	$cookie_str = preg_replace("/\s+\\\&quot;/", '', $cookie_str);
//    var_dump($cookie_str);
//	print "<br>";
	return $cookie_str;
}

function HandLUTMGrabberWooCommerceUpdateOrderMeta( $order_id ) {
	$fields = array('utm_source','utm_medium','utm_term', 'utm_content', 'utm_campaign', 'gclid', 'handl_original_ref', 'handl_landing_page', 'handl_ip', 'handl_ref', 'handl_url');
	foreach ($fields as $field){
		if (isset($_COOKIE[$field]) && $_COOKIE[$field] != '')
		update_post_meta( $order_id, $field, esc_attr($_COOKIE[$field]));
	}
}
add_action('woocommerce_checkout_update_order_meta', 'HandLUTMGrabberWooCommerceUpdateOrderMeta');

//ConvertPlug UTM Support
//function handl_utm_grabber_setting($a){
//	return do_shortcode($a);
//}
//add_filter('smile_render_setting', 'handl_utm_grabber_setting',10,1);

function handl_utm_nav_menu_link_attributes($atts, $item, $args){
	if (isset($atts['href']) && $atts['href'] != '' && get_option( 'hug_append_all' ) == 1){
		$atts['href'] = add_query_arg( HUGGenerateUTMsForURL(), $atts['href'] );
	}
	return $atts;
}
add_filter('nav_menu_link_attributes', 'handl_utm_nav_menu_link_attributes', 10 ,3);

function handl_utm_grabber_merge_tags(){
  require_once 'external/ninja.php';
  Ninja_Forms()->merge_tags[ 'handl_utm_merge_tags' ] = new HandLUTM_MergeTags();
}
add_action( 'ninja_forms_loaded', 'handl_utm_grabber_merge_tags' );

if ( ! function_exists( 'handl_admin_notice__success' ) ) {
    function handl_admin_notice__success() {
        global $pagenow;
        if ($pagenow == 'plugins.php'){
            $field = 'check_v2717_doc';
            if (!get_option($field)) {
                ?>
                <style>
                    .handl-notice-dismiss{
                        display: block;
                    }

                    .handl-notice-title{
                        font-size: 14px;
                        font-weight: 600;
                    }

                    .handl-notice-list li{
                        float: left;
                        margin-right: 20px;
                    }

                    .handl-notice-list li a{
                        color: #ed494d;
                        text-decoration: none;
                    }

                    .handl-notice-list:after{
                        clear: both;
                        content: "";
                        display: block;
                    }

                    .handl-notice-dismiss .new-plugin{
                        font-size: 20px;
                        line-height: 1;
                    }

                    .handl-notice-dismiss .new-plugin a{
                        text-decoration: none;
                    }
                </style>
                <div class="notice notice-success handl-notice-dismiss is-dismissible">
                    <p class='handl-notice-title'>Enjoy using our community version? You will love HandL UTM Grabber V3 even more  </p>
                    <ul>
                        <li>📈 Are you using <b>Google Ads?</b> <a href="https://docs.utmgrabber.com/books/103-internal-apps/page/handl-gclid-reporter?utm_campaign=HandLGCLIDReporter&utm_source=WordPress_FREE&utm_medium=wordpress_settings_page" target="_blank">Click here</a> to generate your <b>GCLID</b> report for <b>FREE (temporarily)</b></li>

<!--                        <li>🎁 💰 <b>Black Friday: Don't miss the biggest sale of the year</b>. <a target="_blank" href="--><?php //print handl_v3_generate_links("BlackFriday2020", "", "wordpress_notification");?><!--">Click here</a> to get <b>50% off</b>. Make your Black Friday memorable with this limited-time, festive deal.</li>-->
<!--                        <li> <p style="font-size:1.25em">💵 <b>BLACK FRIDAY SALES FOR WP COMMUNITY:</b> $20 OFF on every plans. <a target="_blank" href="--><?php //print handl_v3_generate_links('20OFFPromo', '', 'dash-widget'); ?><!--">Click here</a> to score the deal. Limited availability. Act now!</p></li>-->
<!--                        <li> <p style="font-size:1.25em">🚨🚨🚨 <b>LIMITED SEAT AVAILABLE:</b> We are <b>doubling the number of licenses</b> for any plan exclusive to the WP community. <a target="_blank" href="--><?php //print handl_v3_generate_links('4WPCommunity', '', 'dash-widget'); ?><!--">Click here</a> to score the deal. Act now!</p></li>-->
<!--                        <li> ℹ️ Get your <a href="https://handldigital.com/free-utm-audit/?utm_campaign=UTMAudit&utm_source=WordPress_FREE&utm_medium=dash-widget" target="_blank">FREE marketing/UTM audit</a> here. 100% human reply. No credit card required.</li>-->
                    </ul>

                </div>
                <script>
                    jQuery(document).on( 'click', '.handl-notice-dismiss>.notice-dismiss', function() {

                        jQuery.post(
                            ajaxurl,
                            {
                                'action': 'handl_notice_dismiss',
                                'field':   '<?php print $field;?>'
                            }
                        );

                    })
                </script>
                <?php
            }
        }
    }
}
//add_action( 'admin_notices', 'handl_admin_notice__success' );

if ( ! function_exists( 'handl_notice_dismiss' ) ) {
    function handl_notice_dismiss() {
        add_option( 'check_v2717_doc', '1', '', 'yes' ) or update_option( 'check_v2717_doc', '1' );
        die();
    }
}
add_action( 'wp_ajax_handl_notice_dismiss', 'handl_notice_dismiss' );

function handl_grab_related_plugins(){
    $plugins = get_plugins();
//    print_r($plugins);
    $known_form_plugins = array(
        'Elementor',
        'Elementor Pro',
        'Contact Form 7',
        'Ninja Forms',
        'Gravity Forms',
        'Formidable Forms',
        'Formidable Forms Pro',
        'Thrive Leads',
        'Bloom',
        'Caldera Forms',
        'ConvertPlug',
        'ConvertPlus',
        'Optin Forms',
        'WPForms Lite'
    );
    $known_cart_plugins = array(
        'WooCommerce',
    );
    $results = array();
    foreach ($plugins as $plugin){
        if ( in_array($plugin['Name'],$known_form_plugins) ){
            $results['forms'][] = $plugin['Name']." ".$plugin['Version'];
        }

        if ( in_array($plugin['Name'],$known_cart_plugins) ){
            $results['carts'][] = $plugin['Name']." ".$plugin['Version'];
        }
    }
    return $results;
}
//add_action( 'admin_init', 'handl_grab_related_plugins');

add_action( 'wp_dashboard_setup', 'handl_add_dashboard_widget' );
function handl_add_dashboard_widget(){
    wp_add_dashboard_widget(
        'handl-dashboard-overview',
        'Up your marketing game, tracking more',
        'handl_display_dashboard_widget'
    );
}

function handl_display_dashboard_widget(){
    $relatedPlugins = handl_grab_related_plugins();
    if (isset($relatedPlugins['forms'])):
        $forms = implode(",", $relatedPlugins['forms']);
    ?>
    <div class="handl-dash-widget">
        <p>
        <div class="dashicons dashicons-megaphone" aria-hidden="true"></div> Did you know you could do much more with <b>HandL UTM Grabber & <?php print $forms; ?></b> by capturing important parameters such as utm_, gclid, fbclid and syncing it to your favorite CRM?. Find out <a target="_blank" href="<?php print handl_v3_generate_links(sanitize_title($forms), '', 'dash-widget'); ?>">more here</a>.
        </p>
    </div>
    <?php endif; ?>

    <?php
    if (isset($relatedPlugins['carts'])):
        $carts = implode(",", $relatedPlugins['carts']);
    ?>
    <div class="handl-dash-widget">
        <p>
        <div class="dashicons dashicons-megaphone" aria-hidden="true"></div> Are you sending offline conversions to Facebook or Google Analytics from <b><?php print $carts; ?></b>? Offline conversions increase your marketing revenue 5-15% more! Find out <a target="_blank" href="<?php print handl_v3_generate_links(sanitize_title($carts), '', 'dash-widget'); ?>">more here</a>.
        </p>
    </div>
    <?php endif; ?>

    <p class="handl-dash-footer">
        <?php foreach ( handl_dashboard_overview_widget_footer_actions() as $action_id => $action ) : ?>
        <a href="<?php echo esc_attr( $action['link'] ); ?>" target="_blank">
            <?php echo esc_html( $action['title'] ); ?>
            <span class="screen-reader-text">(opens in a new window)</span>
            <span aria-hidden="true" class="dashicons dashicons-external"></span>
        </a>
        <?php endforeach; ?>
    </p>
    <?php
}

function handl_dashboard_overview_widget_footer_actions() {
    $actions = array(
        'go-premium' => array(
            'title' => 'Premium Support',
            'link' => handl_v3_generate_links('HandL_Premium_Support','','dashboard_widget'),
        ),
    );

    return $actions;
}

function get_icon_svg_handl( $base64 = true ) {
    $svg = '<?xml version="1.0" standalone="no"?> <!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 20010904//EN"  "http://www.w3.org/TR/2001/REC-SVG-20010904/DTD/svg10.dtd"> <svg version="1.0" xmlns="http://www.w3.org/2000/svg"  width="100%" height="100%" viewBox="0 0 512.000000 512.000000"  preserveAspectRatio="xMidYMid meet"> <g transform="translate(0.000000,512.000000) scale(0.100000,-0.100000)" fill="#a0a5aa" stroke="none"> <path d="M306 4659 c-2 -8 -10 -32 -17 -54 -7 -22 -18 -49 -25 -60 -7 -11 -16 -37 -19 -57 -4 -21 -11 -38 -15 -38 -4 0 -16 -29 -25 -65 -10 -36 -21 -65 -26 -65 -4 0 -13 -22 -20 -50 -7 -27 -16 -50 -19 -50 -4 0 -15 -28 -25 -62 -10 -35 -22 -67 -26 -73 -15 -22 -77 -192 -84 -232 -7 -46 3 -65 43 -77 12 -4 22 -12 22 -17 0 -5 7 -9 16 -9 15 0 95 -39 104 -52 3 -3 18 -10 33 -13 15 -4 27 -11 27 -15 0 -4 20 -14 45 -21 25 -7 45 -16 45 -20 0 -4 11 -10 25 -13 14 -4 25 -10 25 -15 0 -5 14 -12 30 -16 17 -4 30 -10 30 -15 0 -4 12 -11 26 -14 15 -4 33 -14 41 -21 8 -8 23 -15 33 -15 10 0 20 -3 22 -7 4 -10 110 -63 126 -63 6 0 12 -3 12 -7 0 -5 27 -19 60 -33 33 -14 62 -31 65 -38 3 -8 10 -113 16 -235 6 -122 14 -231 19 -242 5 -11 14 -139 21 -285 12 -274 12 -275 22 -300 3 -8 13 -175 22 -370 9 -195 21 -363 26 -372 5 -10 9 -50 9 -90 0 -128 18 -253 46 -323 14 -36 30 -65 35 -65 5 0 9 -7 9 -15 0 -23 108 -124 162 -151 43 -22 60 -24 174 -24 111 0 132 3 173 23 25 12 48 25 49 30 2 4 12 7 22 7 10 0 25 7 33 15 8 7 27 17 43 21 16 4 35 13 42 21 7 7 20 13 28 13 8 0 14 4 14 9 0 5 14 12 30 16 17 4 30 10 30 15 0 4 20 13 45 19 25 6 45 15 45 20 0 5 12 12 26 15 14 4 43 18 64 31 21 13 50 27 64 31 14 3 26 10 26 14 0 5 14 11 30 15 17 4 30 10 30 15 0 5 14 11 30 15 17 4 30 11 30 16 0 5 6 9 13 9 6 0 35 12 62 25 28 14 56 25 63 25 6 0 12 4 12 9 0 5 12 12 27 16 15 3 30 10 33 13 6 8 94 52 104 52 4 0 14 7 22 15 9 8 24 15 35 15 10 0 19 5 19 10 0 6 4 10 10 10 13 0 124 51 130 60 3 4 18 10 34 14 15 4 35 14 43 21 22 22 37 18 51 -12 18 -42 55 -106 70 -121 6 -7 12 -19 12 -25 0 -7 17 -30 38 -52 20 -23 43 -53 51 -68 8 -15 18 -27 22 -27 11 0 79 -71 79 -82 0 -4 6 -8 13 -8 7 0 20 -8 29 -17 26 -28 45 -42 73 -55 14 -6 25 -15 25 -19 0 -4 11 -10 25 -13 14 -4 25 -10 25 -15 0 -5 14 -12 30 -16 17 -4 30 -11 30 -15 0 -5 16 -12 35 -16 19 -3 35 -10 35 -14 0 -5 19 -11 43 -15 23 -4 48 -12 56 -19 20 -15 422 -15 442 0 8 7 33 15 57 19 23 4 42 11 42 16 0 5 9 9 19 9 11 0 26 6 33 14 8 7 29 17 46 21 18 3 32 11 32 16 0 5 11 11 24 15 14 3 27 12 30 20 3 8 12 14 19 14 8 0 25 9 38 20 13 11 39 32 57 48 18 15 41 35 50 46 9 10 37 41 62 69 25 28 52 58 61 68 10 9 23 28 30 42 8 13 25 44 38 68 13 24 27 46 31 49 4 3 11 16 15 30 4 14 19 49 33 79 13 30 27 73 30 97 2 24 9 44 13 44 5 0 9 99 9 220 l0 220 -422 -2 -423 -3 -3 -217 -2 -218 130 0 c71 0 130 -3 130 -8 0 -4 -8 -15 -17 -23 -10 -9 -32 -33 -50 -53 -18 -20 -35 -36 -38 -36 -6 0 -67 -41 -75 -51 -3 -3 -18 -9 -35 -13 -16 -4 -33 -13 -37 -19 -11 -17 -280 -17 -285 1 -3 6 -12 12 -21 12 -21 0 -82 32 -119 62 -53 43 -133 129 -133 142 0 8 -7 19 -14 26 -8 6 -23 35 -32 63 -9 29 -20 54 -25 57 -13 9 -29 121 -29 204 0 85 17 206 29 206 5 0 14 19 21 43 7 23 21 54 31 69 11 14 19 29 19 33 0 14 92 110 139 145 18 14 38 30 44 35 33 28 148 55 234 55 49 0 93 -4 98 -9 6 -4 33 -14 60 -21 28 -8 52 -16 55 -19 12 -14 70 -51 80 -51 5 0 10 -4 10 -8 0 -5 18 -28 40 -51 22 -23 47 -53 56 -67 16 -24 18 -24 175 -24 87 0 160 4 163 8 3 5 30 7 60 4 30 -2 85 -1 121 3 54 5 65 10 65 25 0 10 -4 22 -10 25 -5 3 -10 16 -10 29 0 47 -122 279 -173 331 -10 9 -17 21 -17 27 0 18 -92 113 -170 176 -25 20 -53 44 -63 54 -10 10 -24 18 -32 18 -8 0 -15 5 -15 10 0 6 -5 10 -11 10 -7 0 -34 14 -61 30 -27 17 -72 36 -99 42 -27 6 -49 15 -49 19 0 4 -25 11 -55 14 -30 4 -58 11 -61 16 -7 12 -324 12 -324 0 0 -4 -27 -11 -60 -15 -33 -4 -60 -11 -60 -15 0 -5 -16 -12 -36 -16 -40 -7 -122 -43 -142 -62 -7 -7 -18 -13 -23 -13 -5 0 -15 -5 -22 -10 -7 -6 -33 -26 -59 -46 -27 -19 -48 -39 -48 -44 0 -6 -5 -10 -12 -10 -16 0 -129 -117 -153 -157 -11 -18 -23 -33 -27 -33 -4 0 -15 -15 -24 -32 -9 -18 -20 -35 -23 -38 -3 -3 -13 -18 -21 -35 -8 -16 -19 -37 -25 -45 -7 -8 -19 -36 -29 -62 -13 -34 -23 -48 -43 -53 -14 -3 -32 -13 -40 -20 -8 -8 -23 -15 -34 -15 -10 0 -19 -4 -19 -10 0 -5 -6 -10 -14 -10 -8 0 -21 -6 -28 -13 -7 -8 -26 -17 -42 -21 -16 -4 -35 -14 -43 -21 -8 -8 -23 -15 -34 -15 -10 0 -19 -4 -19 -10 0 -5 -4 -10 -10 -10 -16 0 -125 -52 -128 -61 -2 -5 -9 -9 -15 -9 -12 1 -158 -71 -167 -81 -3 -3 -17 -9 -32 -13 -16 -3 -28 -11 -28 -16 0 -6 -6 -10 -12 -10 -7 0 -50 -18 -96 -40 -46 -22 -87 -40 -92 -40 -6 0 -10 -4 -10 -9 0 -5 -12 -12 -27 -16 -15 -3 -30 -10 -33 -13 -7 -10 -95 -52 -109 -52 -6 0 -11 -4 -11 -9 0 -10 -31 -24 -38 -17 -5 5 -15 149 -51 736 -11 173 -22 344 -26 380 -3 36 -13 184 -22 330 -8 146 -20 267 -24 268 -5 2 -9 14 -9 26 0 25 -29 118 -40 126 -3 3 -12 20 -19 38 -8 17 -16 32 -20 32 -4 0 -15 14 -24 30 -10 17 -24 30 -31 30 -8 0 -16 7 -20 15 -3 8 -12 15 -21 15 -8 0 -15 4 -15 9 0 5 -21 20 -47 33 -27 13 -50 25 -53 28 -8 8 -92 47 -117 55 -13 3 -23 10 -23 15 0 5 -13 11 -30 15 -16 4 -30 10 -30 15 0 5 -13 11 -30 15 -16 4 -30 10 -30 15 0 4 -12 11 -27 15 -15 3 -30 10 -33 13 -7 10 -95 52 -108 52 -6 0 -12 4 -14 8 -4 10 -111 62 -127 62 -6 0 -11 4 -11 10 0 5 -12 13 -27 16 -16 4 -30 10 -33 13 -3 3 -41 24 -85 46 -44 22 -86 46 -93 53 -7 6 -20 12 -28 12 -16 0 -95 40 -104 52 -3 4 -25 10 -48 14 -31 5 -43 3 -46 -7z m945 -716 c50 -28 86 -62 100 -96 7 -18 18 -35 23 -39 6 -4 11 -42 11 -86 0 -61 -4 -83 -18 -96 -9 -10 -17 -24 -17 -31 0 -17 -57 -75 -75 -75 -7 0 -15 -6 -18 -12 -2 -9 -27 -13 -78 -13 l-74 0 -52 52 c-62 62 -79 111 -70 209 5 49 13 73 34 102 15 20 31 38 35 40 3 2 16 12 29 23 12 10 28 19 36 19 7 0 13 5 13 10 0 17 86 11 121 -7z m225 -2368 c9 -8 21 -15 27 -16 23 -1 66 -66 78 -115 6 -27 15 -56 19 -64 3 -8 3 -26 -1 -40 -4 -14 -13 -45 -19 -69 -13 -48 -80 -121 -112 -121 -10 0 -18 -4 -18 -10 0 -5 -25 -10 -55 -10 -30 0 -55 5 -55 10 0 6 -6 10 -13 10 -14 0 -45 23 -83 61 -28 28 -44 83 -44 156 0 71 14 115 46 150 13 14 24 30 24 34 0 5 6 9 14 9 8 0 21 6 29 14 28 29 135 29 163 1z"/> </g> </svg>';

    if ( $base64 ) {
        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
    }

    return $svg;
}

function handl_utm_grabber_action_links( $links ) {
    $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=handl-utm-grabber.php' ) ) . '">Settings</a>';
    array_unshift( $links, $settings_link );

    $premium_link = '<a href="'.handl_v3_generate_links('HandL_Premium_Support','','plugin_page').'" target="_blank"><b>Premium Support</b></a>';
    array_unshift( $links, $premium_link );

    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'handl_utm_grabber_action_links' );


function get_test_handl_version(){
    return array(
        'label' => 'Upgrade your plugin to the premium version.',
        'status'      => 'recommended',
        'badge'       => array(
            'color' => 'blue',
            'label' => 'UTM'
        ),
        'description' => 'To get the full benefit from your tracking experience. We highly recommend updating the plugin to the latest and premium version.',
        'actions'     => '<a href="'.handl_v3_generate_links('handl_version','','health_check').'" target="_blank"><b>Click here</b> <span aria-hidden="true" class="dashicons dashicons-external"></span></a> to learn more',
        'test'        => 'handl_version',
    );
}
function get_test_handl_free_audit(){
	return array(
		'label' => 'Scan your site to make sure your campaign tracking works as expected',
		'status'      => 'recommended',
		'badge'       => array(
			'color' => 'blue',
			'label' => 'UTM'
		),
		'description' => 'Your site might get benefit from our marketing review',
		'actions'     => '<a href="https://handldigital.com/free-utm-audit/?utm_campaign=UTMAudit&utm_source=WordPress_FREE&utm_medium=health_check" target="_blank"><b>Take completely FREE audit </b> <span aria-hidden="true" class="dashicons dashicons-external"></span></a> to improve your tracking and boost up your revenue.',
		'test'        => 'handl_free_audit',
	);
}
function handl_utm_site_status_filters($tests){

    $tests['direct']['handl_version'] = array(
            'test' => 'get_test_handl_version',
    );
	$tests['direct']['handl_free_audit'] = array(
		'test' => 'get_test_handl_free_audit'
	);
	$tests['direct']['handl_caching_enabled'] = array(
		'test' => 'get_test_handl_caching_enabled'
	);
	if (is_plugin_active('contact-form-7/wp-contact-form-7.php')) {
	    $tests['direct']['handl_cf7_shortcodes_used'] = array(
		    'test' => 'get_test_handl_cf7_shortcodes_used'
	    );
    }
	if (is_plugin_active('gravityforms/gravityforms.php')) {
		$tests['direct']['handl_gf_shortcodes_used'] = array(
			'test' => 'get_test_handl_gf_shortcodes_used'
		);
	}
	if (is_plugin_active('ninja-forms/ninja-forms.php')) {
		$tests['direct']['handl_nf_shortcodes_used'] = array(
			'test' => 'get_test_handl_nf_shortcodes_used'
		);
	}
    return $tests;
}
add_filter( 'site_status_tests', 'handl_utm_site_status_filters' );

function get_test_handl_caching_enabled() {
    $cache_exist = false;

	$recommendation='';
	if ( function_exists( 'is_wpe' ) || function_exists( 'is_wpe_snapshot' ) ){
		$cache_exist = true;
		$recommendation .= "<li>- You are using WP Engine as your server provider: WP Engine uses server caching and it is known that it stripes query arguments and cookies.</li>";
    }

	if ( is_plugin_active('wp-rocket/wp-rocket.php') ){
		$cache_exist = true;
		$recommendation .= "<li>- You are using WP Rocket. WP Rocket does caching and it is known that it stripes query arguments and cookies.</li>";
	}

	if (isset($_ENV['PANTHEON_ENVIRONMENT'])) {
		$recommendation .= "<li>- You are using Pantheon as your server provider: Pantheon uses server caching and it is known that it stripes query arguments and cookies.</li>";
	}

	$positive = "<p>We could not find and caching plugin installed. Hence you should be good collecting UTMs no problem.</p>";
	$negative = "<p>You might be occasionally missing UTMs or COOKIE parameters due to caching. Here is the list of things we could find that may adversely impacting the data collection.</p>
	    <ul>$recommendation</ul>
	";

	$positive_action = 'If you are having trouble collecting UTMs, or if you think you are missing some UTMs, <a href="https://wordpress.org/support/plugin/handl-utm-grabber/" target="_blank">  create a support ticket here <span aria-hidden="true" class="dashicons dashicons-external"></span></a>, we\'d be happy to take a look at it for you';
	$negative_action = $positive_action;

	return array(
		'label' => 'You might be missing some UTMs due to server caching',
		'status'      => $cache_exist ? 'recommended' : 'good',
		'badge'       => array(
			'color' => $cache_exist ? 'red' : 'blue',
			'label' => 'UTM'
		),
		'description' => $cache_exist ? $negative : $positive,
		'actions'     => $cache_exist ? $negative_action : $positive_action,
		'test'        => 'handl_caching_enabled',
	);
}

function get_test_handl_cf7_shortcodes_used() {
	$posts = WPCF7_ContactForm::find( array(
		'post_status'    => 'any',
		'posts_per_page' => - 1,
	) );

	$utm_variables = handl_utm_variables();
	$cf7_forms = array();
	$cf7_forms_id2_name = array();
	$cf7_forms_feedback = array();
	foreach ( $posts as $post ) {
	    $formID = $post->id();
		$cf7_forms_id2_name[$formID] = $post->title();
		$cf7_forms[$formID] = true;
		/** @var WPCF7_ContactForm $post */
		$props = $post->get_properties();

		foreach ($utm_variables as $variable){
		    if ( !preg_match("/".preg_quote("[${variable}",'/')."/", $props['form'] ) ){
			    $cf7_forms[$formID] = false;
			    $cf7_forms_feedback[$formID][] = $variable;
			    //break;
            }
        }
	}

	$all_forms_good = array_filter($cf7_forms, function ($element) {
        return ($element !== true);
    });

	$recommendation = '';
	if (sizeof($cf7_forms_feedback) > 0){
	    foreach ($cf7_forms_feedback as $id=>$fb){
	        $recommendation .= "<p><b>$cf7_forms_id2_name[$id]</b>: ".implode(",",$fb)."</p>";
        }
    }

	$positive = "<p>All of your Contact Form 7 set up properly. You are good to go!</p>";
	$negative = "<p>Your Contact Form 7 forms are not capturing all the UTMs recommended. See the list of forms below having problems and resolve to make sure you do not miss any data</p>
	    $recommendation
	";

	$positive_action = 'You want to up your game? <a href="https://docs.utmgrabber.com/books/101-getting-started-for-handl-utm-grabber-v3/page/native-wp-shortcodes?utm_campaign=utm_proper_cf7&utm_source=WordPress_FREE&utm_medium=health_check" target="_blank"> Click here to get the list of things you can track more <span aria-hidden="true" class="dashicons dashicons-external"></span></a>';
	$negative_action = '<a href="https://docs.utmgrabber.com/books/contact-form-7-integration/page/contact-form-7-utm-tracking?utm_campaign=utm_proper_cf7&utm_source=WordPress_FREE&utm_medium=health_check" target="_blank"> Click here to learn the best practice of collecting UTM parameters in Contact Form 7 <span aria-hidden="true" class="dashicons dashicons-external"></span></a>';

	return array(
		'label' => 'Are your capturing/tracking UTMs properly in your Contact Form 7?',
		'status'      => sizeof($all_forms_good) > 0 ? 'recommended' : 'good',
		'badge'       => array(
			'color' => sizeof($all_forms_good) > 0 ? 'red' : 'blue',
			'label' => 'UTM'
		),
		'description' => sizeof($all_forms_good) > 0 ? $negative : $positive,
		'actions'     => sizeof($all_forms_good) > 0 ? $negative_action : $positive_action,
		'test'        => 'handl_cf7_shortcodes_used',
	);
}

function get_test_handl_gf_shortcodes_used() {
	$posts = GFAPI::get_forms();

	$utm_variables = handl_utm_variables();
	$gf_forms = array();
	$gf_forms_id2_name = array();
	$gf_forms_feedback = array();
	foreach ( $posts as $post ) {
		$formID = $post['id'];
		$gf_forms_id2_name[$formID] = $post['title'];
		$gf_forms[$formID] = true;

		$fields = $post['fields'];

		foreach ($utm_variables as $variable){
		    $check = false;
		    foreach ($fields as $field){
		        if ($field['inputName'] != ''){
			        if ( $variable == $field['inputName'] ){
				        $check = true;
			        }
                }
            }
			if (!$check){
				$gf_forms[$formID] = false;
				$gf_forms_feedback[$formID][] = $variable;
            }
		}
	}

	$all_forms_good = array_filter($gf_forms, function ($element) {
		return ($element !== true);
	});

	$recommendation = '';
	if (sizeof($gf_forms_feedback) > 0){
		foreach ($gf_forms_feedback as $id=>$fb){
			$recommendation .= "<p><b>$gf_forms_id2_name[$id]</b>: ".implode(",",$fb)."</p>";
		}
	}

	$positive = "<p>All of your Gravity forms set up properly. You are good to go!</p>";
	$negative = "<p>Your Gravity forms are not capturing all the UTMs recommended. See the list of forms below having problems and resolve to make sure you do not miss any data</p>
	    $recommendation
	";

	$positive_action = 'You want to up your game? <a href="https://docs.utmgrabber.com/books/101-getting-started-for-handl-utm-grabber-v3/page/native-wp-shortcodes?utm_campaign=utm_proper_gf&utm_source=WordPress_FREE&utm_medium=health_check" target="_blank"> Click here to get the list of things you can track more <span aria-hidden="true" class="dashicons dashicons-external"></span></a>';
	$negative_action = '<a href="https://docs.utmgrabber.com/books/gravity-forms-integration/page/gravity-forms-integration?utm_campaign=utm_proper_gf&utm_source=WordPress_FREE&utm_medium=health_check" target="_blank"> Click here to learn the best practice of collecting UTM parameters in Gravity Forms <span aria-hidden="true" class="dashicons dashicons-external"></span></a>';

	return array(
		'label' => 'Are your capturing/tracking UTMs properly in your Gravity Form?',
		'status'      => sizeof($all_forms_good) > 0 ? 'recommended' : 'good',
		'badge'       => array(
			'color' => sizeof($all_forms_good) > 0 ? 'red' : 'blue',
			'label' => 'UTM'
		),
		'description' => sizeof($all_forms_good) > 0 ? $negative : $positive,
		'actions'     => sizeof($all_forms_good) > 0 ? $negative_action : $positive_action,
		'test'        => 'handl_gf_shortcodes_used',
	);
}

function get_test_handl_nf_shortcodes_used() {
	$posts = Ninja_Forms()->form()->get_forms();
	/** @var NF_Database_Models_Form $post */
	$utm_variables = handl_utm_variables();
	$nf_forms = array();
	$nf_forms_id2_name = array();
	$nf_forms_feedback = array();
	foreach ( $posts as $post ) {
	    $form = $post->get_settings();
		$formID = $post->get_id();
		$nf_forms_id2_name[$formID] = $form['title'];
		$nf_forms[$formID] = true;

		$fields = $form['formContentData'];

		foreach ($utm_variables as $variable){
			$check = false;
			foreach ($fields as $field){
				if ($field != ''){
					if ( preg_match("/".preg_quote("${variable}",'/')."/", $field) ){
						$check = true;
					}
				}
			}
			if (!$check){
				$nf_forms[$formID] = false;
				$nf_forms_feedback[$formID][] = $variable;
			}
		}
	}

	$all_forms_good = array_filter($nf_forms, function ($element) {
		return ($element !== true);
	});

	$recommendation = '';
	if (sizeof($nf_forms_feedback) > 0){
		foreach ($nf_forms_feedback as $id=>$fb){
			$recommendation .= "<p><b>$nf_forms_id2_name[$id]</b>: ".implode(",",$fb)."</p>";
		}
	}

	$positive = "<p>All of your Ninja Forms set up properly. You are good to go!</p>";
	$negative = "<p>Your Ninja forms are not capturing all the UTMs recommended. See the list of forms below having problems and resolve to make sure you do not miss any data</p>
	    $recommendation
	";

	$positive_action = 'You want to up your game? <a href="https://docs.utmgrabber.com/books/101-getting-started-for-handl-utm-grabber-v3/page/native-wp-shortcodes?utm_campaign=utm_proper_nf&utm_source=WordPress_FREE&utm_medium=health_check" target="_blank"> Click here to get the list of things you can track more <span aria-hidden="true" class="dashicons dashicons-external"></span></a>';
	$negative_action = '<a href="https://docs.utmgrabber.com/books/ninja-forms-integration/page/ninja-forms-integration?utm_campaign=utm_proper_nf&utm_source=WordPress_FREE&utm_medium=health_check" target="_blank"> Click here to learn the best practice of collecting UTM parameters in Ninja Forms <span aria-hidden="true" class="dashicons dashicons-external"></span></a>';

	return array(
		'label' => 'Are your capturing/tracking UTMs properly in your Ninja Form?',
		'status'      => sizeof($all_forms_good) > 0 ? 'recommended' : 'good',
		'badge'       => array(
			'color' => sizeof($all_forms_good) > 0 ? 'red' : 'blue',
			'label' => 'UTM'
		),
		'description' => sizeof($all_forms_good) > 0 ? $negative : $positive,
		'actions'     => sizeof($all_forms_good) > 0 ? $negative_action : $positive_action,
		'test'        => 'handl_nf_shortcodes_used',
	);
}

function handl_utm_add_menu( WP_Admin_Bar $wp_admin_bar ){
    if (current_user_can('administrator')){
	    $handl_notifications = getHandLNotifications();
	    foreach ($handl_notifications as $key=>$n) {
		    if ( get_user_option( $key . "_read" ) )
			    unset($handl_notifications[$key]);
	    }

	    $notifications = '';
	    if (sizeof($handl_notifications) > 0){
		    $notifications = '<div class="wp-core-ui wp-ui-notification handl-issue-counter"><span aria-hidden="true">'.sizeof($handl_notifications).'</span><span class="screen-reader-text">'.sizeof($handl_notifications).' notification</span></div>';
	    }


	    $admin_bar_menu_args = [
		    'id'    => 'handl-utm-grabber',
		    'title' => '<div class="wp-menu-image svg handl-svg" style="background-image: url(\''.get_icon_svg_handl().'\')"></div>UTM '.$notifications,
		    'href'  => admin_url( 'admin.php?page=handl-utm-grabber.php' ),
	    ];
	    $wp_admin_bar->add_menu( $admin_bar_menu_args );
    }
}
//Coming Soon
add_action( 'admin_bar_menu', 'handl_utm_add_menu', 90 );

function getHandLNotifications(){
    return [
//	    'black_friday_2020_double' => '🎁 💰 <b>Black Friday Sale is ON</b> Don\'t miss the biggest sale of the year. <a target="_blank" href="'.handl_v3_generate_links("BlackFriday2020", "", "wordpress_notification").'">Click here</a> to get <b>50% off</b>. Limited availability. Act now!',
//            'black_friday_2020_20off' => '💵 <b>BLACK FRIDAY SALES FOR WP COMMUNITY:</b> $20 OFF on every plans. <a target="_blank" href="'.handl_v3_generate_links("20OFFPromo", "", "wordpress_notification").'">Click here</a> to score the deal. Limited availability. Act now!',
	    'ios14_privacy' => ' Are you ready for the new IOS 14 privacy related changes? <a href="https://docs.utmgrabber.com/books/102-getting-started-for-handl-utm-grabber-v3/page/what-does-new-ios-14-release-privacy-change-in-terms-of-tracking?utm_campaign=IOS14Privacy&utm_source=WordPress_FREE&utm_medium=wordpress_settings_page" target="_blank">Click here</a> to learn what the new changes mean to you',
	    'gclid_reporter' => '📈 Are you collecting <b>GCLID?</b> <a href="https://docs.utmgrabber.com/books/103-internal-apps/page/handl-gclid-reporter?utm_campaign=HandLGCLIDReporter&utm_source=WordPress_FREE&utm_medium=wordpress_settings_page" target="_blank">Click here</a> to generate your <b>GCLID</b> report for FREE (temporarily)',
        'free_audit' => '🔍 Get your <a href="https://handldigital.com/free-utm-audit/?utm_campaign=UTMAudit&utm_source=WordPress_FREE&utm_medium=wordpress_settings_page" target="_blank">FREE marketing/UTM audit</a> here. 100% human reply. No credit card required.',
        'documentation' => '📚 Have you seen our knowledge-base site? <a target="_blank" href="https://docs.utmgrabber.com/books?utm_campaign=HandLDocumentation&utm_source=WordPress_FREE&utm_medium=wordpress_notification">Click here</a> to access.',
    ];
}

function handl_mark_read_notifications() {
    $response = [];
    $response['success'] = 0;
    if (isset($_POST) && isset($_POST['nonce']) && isset($_POST['id']) && is_admin() && current_user_can('administrator') ){
        $key = $_POST['id'];
        $nonce = $_POST['nonce'];
//	    $state = $_POST['hide'] ? 0 : 1;

        if (wp_verify_nonce($nonce, $key)){
	        $notifications = getHandLNotifications();
	        if (isset($notifications[$key])){
		        $user_meta_key = $key."_read";
		        update_user_option( get_current_user_id(), $user_meta_key, 1 );
		        $response['success'] = 1;
	        }
        }
    }
    echo json_encode($response);
	exit();
}
add_action( 'wp_ajax_handl_mark_read_notifications', 'handl_mark_read_notifications' );

function handl_v3_generate_links($utm_campaign = '', $utm_source = 'WordPress_FREE', $utm_medium = ''){
    $utm_source = $utm_source != '' ? $utm_source : 'WordPress_FREE';
    return add_query_arg(array(
        "utm_campaign" => $utm_campaign,
        "utm_source" => $utm_source,
        "utm_medium" => $utm_medium
    ),HANDL_UTM_V3_LINK);
}



