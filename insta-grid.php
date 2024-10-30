<?php
/*
*	Plugin Name: Instagram photo grid
*	Plugin URI:  http://wordpress.org/plugins/insta-grid
*	Description: Plugin provides a widget of recent photos from your instagram account.
*	Author: Sergiy Dzysyak
*	Version: 1.1
*	Author URI: http://erlycoder.com/
*	Text Domain: insta-grid
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}


if( !class_exists('Insta_Grid') ){
	class Insta_Grid {
		/**
		 * Constructor
		 * Sets hooks, actions, shortcodes, filters.
		 *
		 */
		function __construct(){
			if(class_exists('Memcache')){ 
				$this->mc = new Memcache; 
				
				$mc_server = (defined('MEMCACHE_SERVER'))?MEMCACHE_SERVER:'localhost';
				$mc_port = (defined('MEMCACHE_PORT'))?MEMCACHE_PORT:11211;
				$this->mc->addServer($mc_server, $mc_port); 
			}
			
			load_plugin_textdomain( 'insta-grid', false, basename( __DIR__ ) . '/languages' );
			
			add_action( 'init', array( $this, 'init_scripts_and_styles' ) );
			register_activation_hook( __FILE__, [$this, 'plugin_install']);
			register_deactivation_hook( __FILE__, [$this, 'plugin_uninstall']);
			
			add_filter('get_instagram_list', array($this, 'get_instagram_list'), 10, 2);
			add_shortcode( 'insta_grid', array($this, 'insta_grid_shortcode'));
			
			add_action( 'enqueue_block_assets', array($this, 'block_assets') );
			
			if(is_admin()){
				add_action('admin_init', array($this, 'admin_init'));
				add_action('admin_menu', array( $this, 'plugin_setup_menu'));
				$plugin = plugin_basename( __FILE__ );
				add_filter( "plugin_action_links_$plugin", array( $this, 'plugin_add_settings_link') );
				add_action( 'wp_ajax_cache_refresh', array( $this, 'refresh_lists_cache') );
			}else{
				add_action( 'wp_enqueue_scripts', array($this, 'plugin_styles') );
			}
		}
		
		/**
		 * Plugin custom front-end styles
		 *
		 */
		function plugin_styles() {
			wp_enqueue_style('insta-grid/insta-grid', plugins_url( 'accets/basic.css', __FILE__ ) ); 
		}
		
		/**
		 * Plugin settings link.
		 * 
		 */
		function plugin_add_settings_link( $links ) {
			$settings_link = '<a href="options-general.php?page=insta_grid">' . __( 'Settings' ) . '</a>';
			array_push( $links, $settings_link );
		  	return $links;
		}
		
		/**
		 * Plugin menu options.
		 * 
		 */
		function plugin_setup_menu(){
			add_options_page( __("Instagram grid", 'insta-grid'), __("Instagram grid", 'insta-grid'),  "manage_options", "insta_grid", array($this, "plugin_settings"));
		}
		
		/**
		 * Plugin settings page. Includes basic settings and layout options.
		 * 
		 */
		function plugin_settings(){
			global $wpdb;
			
			$redirect_uri =  admin_url('/options-general.php?page=insta_grid');
			
			if(!empty($_GET['code'])){
				$apiData = array(
				  'client_id'       => get_option('InstagramClientID'),
				  'client_secret'   => get_option('InstagramClientSecret'),
				  'grant_type'      => 'authorization_code',
				  'redirect_uri'    => $redirect_uri,
				  'code'            => $_GET['code']
				);

				$apiHost = 'https://api.instagram.com/oauth/access_token';

				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $apiHost);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  
				curl_setopt($ch,CURLOPT_SSL_VERIFYHOST, false);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($apiData));
				curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				$jsonData = curl_exec($ch);
				curl_close($ch);

				$user = @json_decode($jsonData, true);
				
				if(!empty($user['access_token'])){ update_option('InstagramAccessToken', $user['access_token']); }
				?><meta http-equiv="refresh" content="0; url=<?php echo $redirect_uri; ?>" /><?php
				exit();
			}
			
			$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'config';
			
			?>
	<!-- Create a header in the default WordPress 'wrap' container -->
	<div class="wrap">
	 
		<h1><?php _e("Instagram grid", 'insta-grid'); ?></h1>
		
		<h2 class="nav-tab-wrapper">
		    <a href="?page=insta_grid&tab=config" class="nav-tab <?php echo $active_tab == 'config' ? 'nav-tab-active' : ''; ?>"><?php _e("Configuration", 'insta-grid'); ?></a>
		</h2>
		
		<?php settings_errors('insta-grid-plugin-config-group'); ?>
		 
		<form method="post" action="options.php">
		<?php if( $active_tab == 'config' ) { ?>
		<?php settings_fields( 'insta-grid-plugin-config-group' ); ?>
		<?php do_settings_sections( 'insta-grid-plugin-config-group' ); ?>
		<h2><?php _e("Instagram account settings", 'insta-grid'); ?></h2>
		<p><?php //_e("For security reasons we suggest to define these valuse in your wp-config.php, as follows:", 'insta-grid'); ?></p>
		<p><?php _e("You should define the following redirect url while registering", 'insta-grid'); ?> <a href="https://www.instagram.com/developer/clients/manage/" target="_blank"><?php _e("Instagram client", 'insta-grid'); ?></a>:<br/>
		<code><?php echo $redirect_uri; ?></code>
		</p>
		<table class="form-table">
			<tr>
				<td width="15%"><?php _e("Instagram Client ID", 'insta-grid'); ?></td>
				<td width="20%"><input type="text" name="InstagramClientID" id="InstagramClientID" placeholder="<?php _e("Client ID", 'insta-grid'); ?>" value="<?php echo esc_attr( get_option('InstagramClientID') ); ?>"/></td>
				<td><?php _e("Can be created", 'insta-grid'); ?> <a href="https://www.instagram.com/developer/clients/manage/" target="_blank"><?php _e("here", 'insta-grid'); ?></a>.</td>
		    </tr>
		    <tr>
				<td width="15%"><?php _e("Instagram Client Secret", 'insta-grid'); ?></td>
				<td><input type="text" name="InstagramClientSecret" id="InstagramClientSecret" placeholder="<?php _e("Client Secret", 'insta-grid'); ?>" value="<?php echo esc_attr( get_option('InstagramClientSecret') ); ?>"/></td>
				<td><?php _e("Same as above", 'insta-grid'); ?>.</td>
		    </tr>
		    <?php 
		    	
		    if((!empty(get_option('InstagramClientID')))&&(get_option('InstagramClientSecret'))){
				$url = "https://api.instagram.com/oauth/authorize?client_id=".get_option('InstagramClientID')."&redirect_uri={$redirect_uri}&scope=basic&response_type=code";
		    ?>
			<?php if(!empty(get_option("InstagramAccessToken"))){ ?>
			<tr>
				<td width="15%"><?php _e("Instagram Access Token", 'insta-grid'); ?></td>
				<td><input readonly="readonly" type="text" name="InstagramAccessToken" id="InstagramAccessToken" placeholder="<?php _e("Access Token", 'insta-grid'); ?>" value="<?php echo esc_attr( get_option('InstagramAccessToken') ); ?>"/></td>
				<td><?php _e("Active access token", 'insta-grid'); ?>.</td>
		    </tr>
		    <?php } ?>
		    <tr>
				<td colspan="2"><a href="<?php echo $url;?>"><?php _e("Login With Instagram", 'insta-grid'); ?></a></td>
				<td><?php _e("Login to get or refresh Access Token and enable front-end", 'insta-grid'); ?>.</td>
		    </tr>
		    
		    <tr>
				<td width="15%"><?php _e("Refresh list of Instagram images", 'insta-grid'); ?></td>
				<td><button type="button" onclick="javascript: jQuery.post(ajaxurl, {action: 'cache_refresh'}, function(response) { if((response=='ok0')||(response=='ok')){ jQuery('#status_ok').show().delay('5000').fadeOut('slow'); } }); "><?php _e("Refresh cache", 'insta-grid'); ?></a></button> <span id="status_ok" style="color: green; display: none;"> <?php _e("Cache refreshed", 'insta-grid'); ?></span></td>
				<td><?php _e("List is downloaded from Instagram once per hour", 'insta-grid'); ?>.</td>
		    </tr>
		    
		    <tr>
				<td colspan="3">
					<?php _e("Short code example", 'insta-grid'); ?>:<br/>
					<code>[insta_grid cols='3' rows='3' width='100%' align='center']</code>
				</td>
		    </tr>
		    <?php } ?>
		</table>
		<?php } ?>
		
		
		<?php submit_button(); ?>

	</form>
		 
	</div><!-- /.wrap -->
	<?php 
		}
		
		/**
		 * Plugin save settings.
		 * 
		 */
		public static function admin_init() {
			register_setting( 'insta-grid-plugin-config-group', 'InstagramClientID');
			register_setting( 'insta-grid-plugin-config-group', 'InstagramClientSecret');
			
			
		}
		
		/**
		 * Enqueue block styles.
		 *
		 */		
		function block_assets(){
			wp_enqueue_style(
				'insta-grid-plugin/insta-grid-plugin-editor',
				plugins_url( 'accets/basic.css', __FILE__ ),
				array( 'wp-edit-blocks' )
			);
		}
		
		/**
		 * Init plugin. Init scripts, styles and blocks.
		 */		
		function init_scripts_and_styles(){
			wp_register_script(
				'insta-grid',
				plugins_url( 'js/block.js', __FILE__ ),
				array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-edit-post', 'wp-data', 'wp-editor' )
			);

			if ( function_exists( 'register_block_type' ) ) register_block_type('insta-grid/insta-grid', array(
				'editor_script' => 'insta-grid',
				'render_callback' => [$this, 'insta_grid_shortcode'],
				'attributes' => array(
		            'cols' => array(
		                'type' => 'string'
		            ),
		            'rows' => array(
		                'type' => 'string'
		            ),
		            'width' => array(
		                'type' => 'string'
		            ),
		            'align' => array(
		                'type' => 'string'
		            )
		        )
			) );
			
			if ( function_exists( 'wp_set_script_translations' ) ) {
				/**
				 * May be extended to wp_set_script_translations( 'my-handle', 'my-domain',
				 * plugin_dir_path( MY_PLUGIN ) . 'languages' ) ). For details see
				 * https://make.wordpress.org/core/2018/11/09/new-javascript-i18n-support-in-wordpress/
				 */
				wp_set_script_translations( 'insta-grid', 'insta-grid' );
			}
		}
		
		/**
		 * Get cached Instagram data or reload it from Instagram API.
		 *
		 * @param string $name - name of the dataset
		 * @param string $url - API call url
		 */		
		private function getCached($name, $url){
			if(class_exists('Memcache')){ 
				$json = $this->mc->get($name);
				if(empty($json)){
					$json = file_get_contents($url);
					$this->mc->set($name, $json, 0, 900);
				}
				
				return $json;
			}else{
				$path = wp_upload_dir()['basedir']."/cache/{$name}.json";
				
				if((!file_exists($path)) || ((time()-filemtime($path))>900)){
					$json = file_get_contents($url);
				
					file_put_contents($path, $json);
					chmod($path, 0666);
				}else{
					$json = file_get_contents($path);
				}
				
				return $json;
			}
		}
		
		/**
		 * Get instagam data list.
		 *
		 * @param string $view_type - name of the dataset.
		 * @param string $keyword - not in use yet.
		 * @return string - JSON of the dataset.
		 */		
		function get_instagram_list($view_type, $keyword=''){
			switch($view_type){
			case 'recent':
				$url = "https://api.instagram.com/v1/users/self/media/recent/?access_token=".get_option('InstagramAccessToken');
				$json = $this->getCached("insta_grid_recent", $url);
			
				return $json;
			break;
			default:
				return 'error';
			}
			
		}
		
		/**
		 * AJAX call from admin interface refreshes Instagram data cache.
		 * 
		 */
		function refresh_lists_cache(){
			apply_filters('get_instagram_list', 'recent');
			
			echo "ok";
			exit();
		}
		
		/**
		 * Plugin short code rendering.
		 * Block code server-side rendering.
		 * 
		 * @param array $attrs - short code attributes.
		 * @return string - Rendered HTML code.
		 */
		function insta_grid_shortcode($attrs){
			$cfg = shortcode_atts( array(
				'cols' => '3',
				'rows' => '3',
				'width' => '100%',
				'align' => 'center',
			), $attrs );
			
			switch($cfg['align']){
			case 'left':
				$cfg['align'] = "0 auto 0 0";
			break;
			case 'right':
				$cfg['align'] = "0 0 0 auto";
			break;
			case 'center':
			default:
				$cfg['align'] = "0 auto";
			}
			
			$res = apply_filters('get_instagram_list', 'recent');
			
			$rows = json_decode($res, true)['data'];
			
			$images = array(); $count = 0; $tot = $cfg['rows']*$cfg['cols'];
			if(is_array($rows)) foreach($rows as $row){
				$images[] = array('thumb'=>$row['images']['thumbnail'], 'low'=>$row['images']['low_resolution'], 'standard'=>$row['images']['standard_resolution'], 'title'=>@$row['images']['caption']['text']);
				$count++;
				if($count>=$tot){  break; }
			}
			
			ob_start();
			include plugin_dir_path(__FILE__)."/tpl/recent_basic.php";

			return ob_get_clean();
		}
		
		/**
		 * Plugin install routines. Check for dependencies.
		 * 
		 * Installation routines.
		 */
		public function plugin_install() {
			if(!is_dir(wp_upload_dir()['basedir']."/cache")){
				if(is_writable(wp_upload_dir()['basedir'])){
					mkdir(wp_upload_dir()['basedir']."/cache");
					chmod(wp_upload_dir()['basedir']."/cache", 0777);
				}else{
					wp_die('Sorry, but this plugin requires /wp-content/uploads folder exist and be writable.');
				}
			}
		}
		
		public function plugin_uninstall() {
		}
	}
	
	$insta_grid_init = new Insta_Grid();
}


class Insta_Grid_Widget extends WP_Widget {

	/**
	 * Sets up a new Instagram Grid widget instance.
	 *
	 */
	public function __construct() {
		$widget_ops = array(
			'classname' => 'Insta_Grid_Widget',
			'description' => __( 'Instagram photo grid.' ),
			'customize_selective_refresh' => true,
		);
		parent::__construct( 'Insta_Grid_Widget', __( 'Instagram photo grid' ), $widget_ops );
		$this->alt_option_name = 'Insta_Grid_Widget';
	}

	/**
	 * Outputs the content for the current widget instance.
	 *
	 * @param array $args     Display arguments including 'before_widget', and 'after_widget'.
	 * @param array $instance Settings for the current widget instance.
	 */
	public function widget( $args, $instance ) {
		if ( ! isset( $args['widget_id'] ) ) {
			$args['widget_id'] = $this->id;
		}

		$insta_grid_rows = ( ! empty( $instance['insta_grid_rows'] ) ) ? absint( $instance['insta_grid_rows'] ) : 5;
		$insta_grid_cols = ( ! empty( $instance['insta_grid_cols'] ) ) ? absint( $instance['insta_grid_cols'] ) : 5;
		
		
		echo $args['before_widget'];
		if(!empty($instance['insta_grid_title'])) echo '<h4 class="widget-title">'.$instance['insta_grid_title'].'</h4>';
		echo do_shortcode("[insta_grid cols='{$insta_grid_cols}' rows='{$insta_grid_rows}' width='100%' align='center']");	
		
		echo $args['after_widget'];
	}

	/**
	 * Handles updating the settings for the current widget instance.
	 *
	 * @param array $new_instance New settings for this instance as input by the user via WP_Widget::form().
	 * @param array $old_instance Old settings for this instance.
	 * @return array Updated settings to save.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['insta_grid_rows'] = (int) $new_instance['insta_grid_rows'];
		$instance['insta_grid_cols'] = (int) $new_instance['insta_grid_cols'];
		$instance['insta_grid_title'] = (string) $new_instance['insta_grid_title'];
		return $instance;
	}

	/**
	 * Outputs the settings form for the widget.
	 *
	 * @param array $instance Current settings.
	 */
	public function form( $instance ) {
		$cols    = isset( $instance['insta_grid_cols'] ) ? absint( $instance['insta_grid_cols'] ) : 3;
		$rows    = isset( $instance['insta_grid_rows'] ) ? absint( $instance['insta_grid_rows'] ) : 3;
		$title    = isset( $instance['insta_grid_title'] ) ? $instance['insta_grid_title'] : "Instagram";
?>
		<p><label for="<?php echo $this->get_field_id( 'insta_grid_title' ); ?>"><?php _e( 'Title:' ); ?></label><br/>
		<input class="widefat" id="<?php echo $this->get_field_id( 'insta_grid_title' ); ?>" name="<?php echo $this->get_field_name( 'insta_grid_title' ); ?>" type="text" value="<?php echo $title; ?>"/></p>

		<p><label for="<?php echo $this->get_field_id( 'insta_grid_cols' ); ?>"><?php _e( 'Number of columns to show:' ); ?></label>
		<input class="tiny-text" id="<?php echo $this->get_field_id( 'insta_grid_cols' ); ?>" name="<?php echo $this->get_field_name( 'insta_grid_cols' ); ?>" type="number" step="1" min="1" value="<?php echo $cols; ?>" size="3" /></p>
		
		<p><label for="<?php echo $this->get_field_id( 'insta_grid_rows' ); ?>"><?php _e( 'Number of rows to show:' ); ?></label>
		<input class="tiny-text" id="<?php echo $this->get_field_id( 'insta_grid_rows' ); ?>" name="<?php echo $this->get_field_name( 'insta_grid_rows' ); ?>" type="number" step="1" min="1" value="<?php echo $rows; ?>" size="3" /></p>


<?php
	}
}

// register My_Widget
add_action( 'widgets_init', function(){
	register_widget( 'Insta_Grid_Widget' );
});
		
?>
