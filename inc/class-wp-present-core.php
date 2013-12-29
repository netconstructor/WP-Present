<?php
/**
 ** WP Present Core
 **
 ** @since 0.9.4
 **/
class WP_Present_Core {

	const REVISION = 20131204;

	/* Post Type */
	const POST_TYPE_SLUG     = 'slide';
	const POST_TYPE_NAME     = 'Slides';
	const POST_TYPE_SINGULAR = 'Slide';
	const POST_TYPE_CAP_TYPE = 'post';

	/* Taxonomy */
	const TAXONOMY_SLUG      = 'presentation';
	const TAXONOMY_NAME      = 'Presentations';
	const TAXONOMY_SINGULAR  = 'Presentation';

	/* Shortcode */
	const SHORTCODE          = 'wppresent';

	/* Options */
	const OPTION_NAME        = 'presentation-options';
	const OPTION_TITLE       = 'Presentation Options';

	/* Misc */
	const CAPABILITY         = 'edit_others_posts';
	const NONCE_FIELD        = 'wp-present-nonce';
	const DEFAULT_THEME      = 'simple.css'; // moon, night, simple, serif, solarized
	const MAX_NUM_SLIDES     = 250; // not currently used, proposed variable
	const TEXT_DOMAIN        = 'wp-present';

	public $post_types = array( 'slide' );
	public $plugins_url = '';
	public $nonce_fail_message = '';

	/* Define and register singleton */
	private static $instance = false;
	public static function instance() {
		if( ! self::$instance ) {
			self::$instance = new self;
			self::$instance->setup();
		}
		return self::$instance;
	}

	/**
	 * Constructor
     *
	 * @since 0.9.0
	 */
	private function __construct() { }

	/**
	 * Clone
     *
	 * @since 0.9.0
	 */
	private function __clone() { }

	/**
	 * Add actions and filters
	 *
	 * @uses add_action, add_filter
	 * @since 0.9.5
	 */
	function setup() {

		// Setup
		$this->plugins_url = plugins_url( '/wp-present' );
		$this->nonce_fail_message = __( 'Cheatin&#8217; huh?' );

		// Initialize
		add_action( 'init', array( $this, 'action_init_register_post_type' ) );
		add_action( 'init', array( $this, 'action_init_register_taxonomy' ) );
		add_action( 'init', array( $this, 'action_init_register_shortcode' ) );
		add_action( 'init', array( $this, 'action_init_editor_styles' ) );

		// Front End
		add_action( 'wp', array( $this, 'action_wp_show_admin_bar' ), 99 );
		add_action( 'wp_head', array( $this, 'action_wp_head' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'action_wp_enqueue_scripts' ), 99 );
		add_action( 'wp_footer', array( $this, 'action_wp_footer' ), 99 );

		// Template
		add_filter( 'template_include', array( $this, 'filter_template_include' ) );

		// Hide screen options
		add_filter('screen_options_show_screen', '__return_false'); // a test

		// Taxonomy
		add_action( self::TAXONOMY_SLUG . '_edit_form', array( $this, 'taxonomy_edit_form' ), 9, 2 );

		add_action( 'restrict_manage_posts', array( $this, 'action_restrict_manage_posts' ) );
		add_action( 'parse_query', array( $this, 'action_parse_query' ) );

		//Update the post links for slides
		add_filter( 'post_type_link', array( $this, 'append_query_string' ), 10, 2 );
		add_filter( 'get_edit_term_link', array( $this, 'filter_get_edit_term_link' ) );

		// AJAX
		add_action( 'wp_ajax_get_slide', array( $this, 'action_wp_ajax_get_slide' ) );
		add_action( 'wp_ajax_update_slide', array( $this, 'action_wp_ajax_update_slide' ) );
		add_action( 'wp_ajax_new_slide', array( $this, 'action_wp_ajax_new_slide' ) );
		add_action( 'wp_ajax_delete_slide', array( $this, 'action_wp_ajax_delete_slide' ) );
		add_action( 'wp_ajax_update_presentation', array( $this, 'action_wp_ajax_update_presentation' ) );

		// TinyMCE
		add_filter( 'tiny_mce_before_init', array( $this, 'filter_tiny_mce_before_init' ) );
		add_filter( 'mce_external_plugins', array( $this, 'filter_mce_external_plugins' ) );

		// Hide taxonomy description column
		add_filter( 'manage_edit-' . self::TAXONOMY_SLUG . '_columns', array( $this, 'filter_manage_edit_columns' ) );

		// Adds custom image sizes that will play nice with the default slide resolution
		add_action( 'init', array( $this, 'register_image_sizes' ) );
		add_filter( 'image_size_names_choose', array( $this, 'filter_image_size_names_choose' ) );

		// Add specific CSS class by filter
		add_filter('body_class', array( $this, 'filter_body_class' ) );
	}

	/**
	 * Adds a wp-present body class
	 *
	 * @filter body_class
	 * @return array
	 */
	function filter_body_class( $classes ) {
		$classes[] = 'wp-present';
		return $classes;
	}

	/**
	 * Remove the description column from the taxonomy overview page
	 *
	 * @return array
	 */
	public function filter_manage_edit_columns( $theme_columns ) {
		unset( $theme_columns['description'] );
		return $theme_columns;
	}

	/**
	 * Reality check
	 *
	 * @uses maths
	 * @return bool (hopefully)
	 */
	public function is() {
		return ( 2 + 2 ) != 4 ? false : true;
	}

	/**
	 * Register the post type
	 *
	 * @uses add_action()
	 * @return null
	 */
	public function action_init_register_post_type() {
		register_post_type( self::POST_TYPE_SLUG, array(
			'labels' => array(
				//@todo http://codex.wordpress.org/Function_Reference/register_post_type
				'name'          => __( self::POST_TYPE_NAME ),
				'singular_name' => __( self::POST_TYPE_SINGULAR ),
				'add_new_item'  => __( 'Add New ' . self::POST_TYPE_SINGULAR ),
				'edit_item'     => __( 'Edit ' . self::POST_TYPE_SINGULAR ),
				'new_item'      => __( 'New ' . self::POST_TYPE_SINGULAR ),
				'view_item'     => __( 'View ' . self::POST_TYPE_SINGULAR ),
				'search_items'  => __( 'Search' . self::POST_TYPE_NAME ),
			),
			'public'          => true,
			'capability_type' => self::POST_TYPE_CAP_TYPE,
			'has_archive'     => true,
			'show_ui'         => true,
			'show_in_menu'    => true,
			//'menu_position'   => 5,
			'hierarchical'    => true, //@todo within the same category?
			'supports'        => array( 'title', 'editor', 'page-attributes', 'thumbnail' ),
			'taxonomies'      => array( self::TAXONOMY_SLUG )
		) );
	}

	/**
	 * Register the taxonomy
	 *
	 * @uses add_action()
	 * @return null
	 */
	public function action_init_register_taxonomy() {
		register_taxonomy( self::TAXONOMY_SLUG, $this->post_types, array(
			'labels' => array(
				'name'              => _x( self::TAXONOMY_NAME, 'taxonomy general name' ),
				'singular_name'     => _x( self::TAXONOMY_SINGULAR, 'taxonomy singular name' ),
				'search_items'      => __( 'Search ' . self::TAXONOMY_NAME ),
				'all_items'         => __( 'All ' . self::TAXONOMY_NAME ),
				'parent_item'       => __( 'Parent ' . self::TAXONOMY_SINGULAR ),
				'parent_item_colon' => __( 'Parent ' . self::TAXONOMY_SINGULAR . ':' ),
				'edit_item'         => __( 'Edit ' . self::TAXONOMY_SINGULAR ),
				'update_item'       => __( 'Update ' . self::TAXONOMY_SINGULAR ),
				'add_new_item'      => __( 'Add New ' . self::TAXONOMY_SINGULAR ),
				'new_item_name'     => __( 'New ' . self::TAXONOMY_SINGULAR. ' Name' ),
				'menu_name'         => __( self::TAXONOMY_NAME ),
				'view_item'         => __( 'View ' . self::TAXONOMY_SINGULAR )
			),
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => self::TAXONOMY_SLUG )
		) );
	}

	/**
	 * Register the shortcode(s)
	 *
	 * @since  0.9.6
	 * @uses add_shortcode()
	 * @return null
	 */
	public function action_init_register_shortcode() {
		add_shortcode( self::SHORTCODE, array( $this, 'do_shortcode' ) );
	}

	/**
	 * Render a iframe shortcode
	 *
	 * @since  0.9.6
	 * @return string $html
	 */
	function do_shortcode( $atts ) {
		ob_start();
		extract( shortcode_atts( array(
			'src' => '#',
			'w' => '100%',
			/*'h' => '270',*/
		), $atts ) );
		?>
		<iframe class="presentation-iframe" src="<?php echo esc_attr( $src ); ?>" width="<?php echo esc_attr( $w ); ?>" height="<?php echo esc_attr( /*$h*/'' ); ?>" onload="this.contentWindow.focus()" >no iframes</iframe>
		<?php
		return ob_get_clean();
	}

	/**
	 * Register editor styles
	 *
	 * @uses add_action()
	 * @return null
	 */
	public function action_init_editor_styles() { // also should peep at mce_css
		global $pagenow, $post;

		// Only on the edit taxonomy and edit post type admin pages
		$is_tax = ( 'edit-tags.php' == $pagenow || ( isset( $_GET['taxonomy'] ) && self::TAXONOMY_SLUG == $_GET['taxonomy'] ) ) ? true : false;
		$is_cpt = ( 'post.php' == $pagenow && isset( $_GET['post'] ) && self::POST_TYPE_SLUG == get_post_type( $_GET['post'] ) ) ? true : false;
		$is_cpt_new = ( 'post-new.php' == $pagenow && isset( $_GET['post_type'] ) && self::POST_TYPE_SLUG == $_GET['post_type'] ) ? true : false;

		if( ! $is_tax && ! $is_cpt && ! $is_cpt_new )
			return;

		//If not page now tax or slide : return;
		remove_editor_styles();
//		add_editor_style( plugins_url( '/wp-present/css/reset.css' ) );
		add_editor_style( plugins_url( '/wp-present/js/reveal.js/css/reveal.css' ) );
		add_editor_style( plugins_url( '/wp-present/js/reveal.js/css/theme/' . self::DEFAULT_THEME ) );
		add_editor_style( plugins_url( '/wp-present/js/reveal.js/lib/css/zenburn.css' ) );
		add_editor_style( plugins_url( '/wp-present/css/custom.css?v=' . self::REVISION ) );

		//TODO: Make this work to support backgrounds
		//add_editor_style( plugins_url( '/wp-present/css/tinymce.css.php?v=' . self::REVISION . '&post=' . $_REQUEST['post'] ) );
	}

	/**
	 * Enqueue necessary scripts
	 *
	 * @uses wp_enqueue_script
	 * @return null
	 */
	public function action_wp_enqueue_scripts() {
		if( ! is_tax( self::TAXONOMY_SLUG ) )
			return;

		// Deregister theme specific stylesheets
		global $wp_styles;
		foreach( $wp_styles->registered as $handle => $object ) {
			$stylesheet_relative_uri = str_replace( home_url(), '', get_stylesheet_directory_uri() );
			if( ! empty( $stylesheet_relative_uri ) && strpos( $object->src, $stylesheet_relative_uri ) ) {
				unset( $wp_styles->$handle );
				wp_dequeue_style( $handle );
			}
		}

		/* Browser reset styles */
		//wp_enqueue_style( 'reset', $this->plugins_url . '/css/reset.css', '', self::REVISION );

		/* Reveal Styles */
		wp_enqueue_style( 'reveal', $this->plugins_url . '/js/reveal.js/css/reveal.css', '', self::REVISION );
		wp_enqueue_style( 'reveal-theme', $this->plugins_url . '/js/reveal.js/css/theme/' . self::DEFAULT_THEME, array('reveal'), self::REVISION );
		wp_enqueue_style( 'zenburn', $this->plugins_url . '/js/reveal.js/lib/css/zenburn.css', '', self::REVISION, false );

		/* Last run styles */
		wp_enqueue_style( 'custom', $this->plugins_url . '/css/custom.css', array('reveal'), self::REVISION );

		/* Reveal Scripts */
		wp_enqueue_script( 'reveal-head', $this->plugins_url . '/js/reveal.js/lib/js/head.min.js', array( 'jquery' ), self::REVISION, true );
		wp_enqueue_script( 'reveal', $this->plugins_url . '/js/reveal.js/js/reveal.min.js', array( 'jquery' ), self::REVISION, true );
		//wp_enqueue_script( 'reveal-config', $this->plugins_url . '/js/reveal-config.js', array( 'jquery' ), self::REVISION );
	}

	/**
	 * Select appropriate template based on post type and available templates.
	 * Returns an array with name and path keys for available template or false if no template is found.
	 * Based on a similar method from wp-print-friendly
	 *
	 * @uses get_queried_object, is_home, is_front_page, locate_template
	 * @return array or false
	 */
	public function template_chooser() {
		// Get queried object to check post type
		$queried_object = get_queried_object();

		//Get plugin path
		$plugin_path = dirname( dirname( __FILE__ ) );

		$theme_path = get_stylesheet_directory();



//echo $theme_path . '/presentation.php';

	if ( file_exists(  $theme_path . '/presentation.php' ) && $this->is() ) {
			$template = array(
				'name' => 'wp-presents-theme',
				'path' => $theme_path . '/presentation.php'
			);
		}
		elseif ( file_exists( $plugin_path . '/templates/presentation.php' ) && $this->is() ) {
			$template = array(
				'name' => 'wp-presents-default',
				'path' => $plugin_path . '/templates/presentation.php'
			);
		}

		return isset( $template ) ? $template : false;
	}

	/**
	 * Filter template include to return print template if requested.
	 * Based on a similar method from wp-print-friendly
	 *
	 * @param string $template
	 * @filter template_include
	 * @uses this::is_protected
	 * @return string
	 */
	public function filter_template_include( $template ) {
		if ( is_tax( self::TAXONOMY_SLUG ) && ( $taxonomy_template = $this->template_chooser() ) )
			$template = $taxonomy_template['path'];

		return $template;
	}

	/**
	 * Always show the admin bar
	 *
	 * @uses show_admin_bar()
	 * @return null
	 */
	function action_wp_show_admin_bar() {
		if( ! is_tax( self::TAXONOMY_SLUG ) )
			return false;

		show_admin_bar( false );
	}

	/**
	 * Output for the <head>
	 *
	 * @uses is_tax
	 * @return null
	 */
	public function action_wp_head() {
		if( ! is_tax( self::TAXONOMY_SLUG ) )
			return false;
		?>
		<!-- Reveal -->
		<meta name="apple-mobile-web-app-capable" content="yes" />
		<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

		<script type="text/javascript">
			jQuery(function($){
				$('#wpadminbar').show();
				$('#toggle-wpadminbar').click( function() {
					$('#wpadminbar').toggle();
				} );

			});
		</script>
		<?php
	}

	/**
	 * Output for the <footer>
	 *
	 * @uses is_tax
	 * @return null
	 */
	public function action_wp_footer() {
		if( ! is_tax( self::TAXONOMY_SLUG ) )
			return;
		?>
		<script>
		/* Custom jQuery Reveal Code */
		jQuery(document).ready(function($) {

			// Full list of configuration options available here:
			// https://github.com/hakimel/reveal.js#configuration
			Reveal.initialize({
				width: 1024,
				height: 768,
				controls: true,
				progress: true,
				history: true,
				center: true,
				autoSlide: 0, // in milliseconds, 0 to disable
				loop: false,
				mouseWheel: false,
				rollingLinks: false,
				transition: 'default', // default/cube/page/concave/zoom/linear/fade/none
				theme: Reveal.getQueryHash().theme, // available themes are in /css/theme
				transition: Reveal.getQueryHash().transition || 'default', // default/cube/page/concave/zoom/linear/fade/none

				// Optional libraries used to extend on reveal.js
				dependencies: [
					{ src: '<?php echo $this->plugins_url;?>/js/reveal.js/lib/js/classList.js', condition: function() { return !document.body.classList; } },
					{ src: '<?php echo $this->plugins_url;?>/js/reveal.js/plugin/markdown/marked.js', condition: function() { return !!document.querySelector( '[data-markdown]' ); } },
					{ src: '<?php echo $this->plugins_url;?>/js/reveal.js/plugin/markdown/markdown.js', condition: function() { return !!document.querySelector( '[data-markdown]' ); } },
					{ src: '<?php echo $this->plugins_url;?>/js/reveal.js/plugin/highlight/highlight.js', async: true, callback: function() { hljs.initHighlightingOnLoad(); } },
					{ src: '<?php echo $this->plugins_url;?>/js/reveal.js/plugin/zoom-js/zoom.js', async: true, condition: function() { return !!document.body.classList; } },
					{ src: '<?php echo $this->plugins_url;?>/js/reveal.js/plugin/notes/notes.js', async: true, condition: function() { return !!document.body.classList; } },

					//{ src: '<?php echo $this->plugins_url;?>/js/reveal.js/plugin/search/search.js', async: true, condition: function() { return !!document.body.classList; } },
					//{ src: '<?php echo $this->plugins_url;?>/js/reveal.js/plugin/remotes/remotes.js', async: true, condition: function() { return !!document.body.classList; } }
				]


			});
		});
		</script>
		<?php
	}

	/* Find which slides are already found in the DB before auto-populating the backfill
	 *
	 * @return array
	 */
	public function get_associated_slide_ids( $term, $taxonomy ) {
		$term_description =  self::get_term_description( $term, $taxonomy );

		if( ! is_array( $term_description ) )
			return false;

		global $post, $wp_query;
		$num_columns = array();
		$associated_slides =  array();

		// Calculate the number of columns we need
		$columns = array();
		foreach( $term_description as $c => $column ) {
			if( ! empty( $term_description[ $c ] ) )
				$num_columns[] = $c;
		}

		for ( $col = 1; $col <= count( $num_columns ); $col++ ) {
			$slides = $term_description[ 'col-' . $col ];
			foreach( $slides as $key => $slide ) {

				// TODO: This safeguard shouldn't be necessary
				if( ! strpos($slide, '-', 0) )
					continue;


				list( $rubbish, $slide_id ) =  explode( '-', $slide );
				$post = get_post( $slide_id );
				setup_postdata( $post );
				$associated_slides[] = get_the_ID();
				wp_reset_postdata();
			}
		}
		unset( $col );
		return $associated_slides;
	}

	/* Get the term description
	 *
	 * @return array
	 */
	public function get_term_description( $term, $taxonomy ) {
		$obj = get_term( $term, $taxonomy );
		if( ! isset( $obj->term_taxonomy_id ) )
			return '';
		return $term_description =  ! empty( $obj->description ) ? json_decode( $obj->description, $asArray = true ) : '';
	}


	/* Render a slide for reveal.js
	 *
	 * @return null
	 */
	public function admin_render_slide( $post ) {
		setup_postdata( $post );
		?>
		<div id="slide-<?php the_ID(); ?>" class=" portlet widget">
			<div class="widget-top">
				<div class="widget-title-action">
					<a class="widget-action hide-if-no-js" href="#available-widgets"></a>
					<a class="widget-control-edit hide-if-js" href="">
						<span class="edit">Edit</span>
						<span class="add">Add</span>
						<span class="screen-reader-text"><?php the_title(); ?></span>
					</a>
				</div>
				<div class="widget-title">
					<h4><?php the_title(); ?><span class="in-widget-title"></span></h4>
				</div>
			</div>
			<div class="widget-inside" style="display: none;">
				<input class="slide-id" id="input-<?php the_ID(); ?>" type="hidden" value="<?php the_ID(); ?>"></input>
				<div class='widget-preview'>
					<?php the_excerpt(); ?>
				</div>
				<div class="widget-control-actions">
					<a class="widget-control-edit" href="<?php echo get_edit_post_link( get_the_ID() ); ?>" target="_blank">Edit</a>
					<span class='widget-control-separator'>|</span>
					<a class="widget-control-remove" href="#remove">Delete</a>
					<span class='widget-control-separator'>|</span>
					<a class="widget-control-view" href="<?php echo get_permalink( get_the_ID() ); ?>" target="_blank">View</a>
					<div class="clearfix"></div>
					<!--<a class="widget-control-view" href="<?php echo get_edit_post_link( get_the_ID() ); ?>" target="_blank">Advance</a>-->
					<br class="clear">
				</div>
			</div>
		</div>
		<?php
		wp_reset_postdata();
	}

	/* Output the columns in the admin edit taxonomy page
	 *
	 * @return
	 */
	public function admin_render_columns( $term, $taxonomy ) {
		global $post, $wp_query;
		$term_description =  $this->get_term_description( $term, $taxonomy );

		// Calculate the number of columns we need
		$columns = array();

		if( empty( $term_description ) || 0 >= (int) count( $term_description ) )
			return;

		foreach( $term_description as $c => $column ) {
			if( ! empty( $term_description[ $c ] ) )
				$columns[] = $c;
		}
		// Let's take a look at the column array;
		for ( $col = 1; $col <= max( 1, count( $columns ) ); $col++ ) {
			?>
			<div class="column autopop" id="col-<?php echo intval( $col ); ?>">
				<div class="widget-top">
					<div class="widget-title">
						<h4 class="hndle"><?php echo $col; ?><span class="in-widget-title"></span></h4>
					</div>
				</div>
				<div class="column-inner">
				<?php
				$slides = $term_description[ 'col-' . $col ];
				foreach( $slides as $key => $slide ) {

					// TODO: This safeguard shouldn't be necessary
					if( ! strpos($slide, '-', 0) )
						continue;

					list( $rubbish, $slide_id ) =  explode( '-', $slide );
					$post = get_post( $slide_id );
					$this->admin_render_slide( $post );
				}
				?>
				</div><!--/.column-inner-->

			</div>
			<?php
		}
		unset( $col );
	}

	/**
	 * Edit Term Control
	 *
	 * Create image control for wp-admin/edit-tag-form.php.
	 * Hooked into the '{$taxonomy}_edit_form_fields' action.
	 *
	 * @param	stdClass Term object.
	 * @param	string Taxonomy slug
	 * @uses	add_action()
	 * @uses	get_taxonomy()
	 * @uses	get_term_field
	 * @return 	null
	 */
	public function taxonomy_edit_form( $term, $taxonomy ) {
		global $post;
		$associated_slides = $this->get_associated_slide_ids( $term, $taxonomy );
		wp_nonce_field( self::NONCE_FIELD, self::NONCE_FIELD, false );
		?>
		<div class="action-buttons">
			<p>
				<button id="add-button" class="button button-primary">New <?php echo self::POST_TYPE_SINGULAR; ?></button>
				<button id="add-column" class="button">New Column</button>
				<button id="remove-column" class="button">Remove Column</button>
				<!--<button id="tidy-button" class="button">Tidy</button>-->
				<button id="view-button" class="button">View <?php echo self::TAXONOMY_SINGULAR; ?></button>
				<?php // TODO: Add Existing Slide Button ?>
				<span class="spinner">Saving</span>
			</p>
		</div>
		<div id="outer-container"  class="ui-widget-content">
					<!--<h3 class="ui-widget-header">Resizable</h3>-->
					<div id="container">
						<?php
						//THE NEW WAY
						$this->admin_render_columns( $term, $taxonomy );

						// Calculate the number of columns we need
						$columns = array();
						$term_description =  $this->get_term_description( $term, $taxonomy );

					if( ! empty( $term_description ) && 0 < (int) count( $term_description ) ) {
						foreach( $term_description as $c => $column ) {
							if( ! empty( $term_description[ $c ] ) )
								$columns[] = $c;
						}
					}

						// The Slides Query
						$slides_query = new WP_Query( array(
							'post_type' => $this->post_types,
							'post_status' => 'publish',
							'orderby' => 'date',
							'order' => 'ASC',
							'cache_results' => true,
							'tax_query' => array( array(
								'taxonomy' => self::TAXONOMY_SLUG,
								'field' => 'id',
								'terms' => $term->term_id
							) ),
							'posts_per_page' => -1, //consider making this something like 250 or 500 just to set a limit of some sort
							'post__not_in' => $associated_slides
						) );

						// The Loop
						if ( $slides_query->have_posts() ) {
							$col = count( $columns ) + 1; //Start with the number of existing cols
							while ( $slides_query->have_posts() ) {
								$slides_query->the_post();
								?>
								<div class="column backfill" id="col-<?php echo $col; ?>">
									<div class="widget-top">
										<div class="widget-title">
											<h4 class="hndle"><?php echo $col; ?><span class="in-widget-title"></span></h4>
										</div>
									</div>
									<div class="column-inner">
										<?php $this->admin_render_slide( $post ); ?>
									</div>
								</div>
								<?php
								$col++;
							}
							unset( $col );
						} elseif( isset( $associated_slides ) || 0 == count( $associated_slides ) ) { // If there are 0 slides
							//echo '<p>Sorry, No ' . self::POST_TYPE_NAME . ' found!</p>';

							// If taxonomy is empty
							if( empty( $term_description ) ) {
								?>
								<div class="column backfill" id="col-1">
									<div class="widget-top">
										<div class="widget-title">
											<h4 class="hndle"><?php echo '1'; ?><span class="in-widget-title"></span></h4>
										</div>
									</div>
								</div>
								<?php
							}
						}
						?>
						<div class="clearfix"></div>
					</div><!--/#container-->
		</div><!--/#outer-container-->
		<div id="dialog" class="media-modal" title="Edit <?php echo self::POST_TYPE_SINGULAR; ?>" style="display: none;">
			<div class="modal-inner-left">
				<?php WP_Present_Modal_Customizer::instance()->render(); ?>
			</div>
			<div class="modal-inner-right">

				<?php $this->modal_editor(); ?>
			</div>
		</div>
		<?php
		// Cleanup
		wp_reset_postdata();
		unset( $slides_query );
	}

	/**
	 * Filter the taxonomy description
	 *
	 * Decodes the serialized description field
	 *
	 * @return stdClass
	 */
	public function filter_get_terms( $terms, $taxonomies, $args ) {
		global $wpdb, $pagenow;

		/**********************************************
		* NOT currently working for category taxonomy *
		*********************************************/

 		/* Bail if we are not looking at this taxonomy's directory */
		if( 'edit-tags.php' != $pagenow || ( self::TAXONOMY_SLUG != $_GET['taxonomy'] && 'category' != $_GET['taxonomy'] ) || isset( $_GET['tag_ID'] ) )
			return $terms;

		$taxonomy = $taxonomies[0];
		if ( ! is_array( $terms ) && count( $terms ) < 1 )
			return $terms;

		$filtered_terms = array();
		foreach ( $terms as $term ) {
			$term_decoded = json_decode( $term->description );
			if ( is_object( $term_decoded ) )
				$term->description = $term_decoded->description;
			$filtered_terms[] = $term;
		}
		return $filtered_terms;
	}

	/**
	 * Fetch the taxonomy slug
	 *
	 * @return string
	 */
	public function get_taxonomy_slug() {
		return self::TAXONOMY_SLUG;
	}

	/**
	 * FILL THIS OUT
	 *
	 * @return
	 */
	public function action_restrict_manage_posts() {
		global $typenow;

		if ( $typenow == self::POST_TYPE_SLUG ) {
			$selected = isset( $_GET[ self::TAXONOMY_SLUG ] ) ? $_GET[ self::TAXONOMY_SLUG ] : '';
			$info_taxonomy = get_taxonomy( self::TAXONOMY_SLUG );
			wp_dropdown_categories( array(
				'show_option_all' => __( "Show All {$info_taxonomy->label}" ),
				'taxonomy' => self::TAXONOMY_SLUG,
				'name' => self::TAXONOMY_SLUG,
				'orderby' => 'name',
				'selected' => $selected,
				'show_count' => true,
				'hide_empty' => true,
			) );
		}
	}

	/**
	 * FILL THIS OUT
	 *
	 * @return
	 */
	public function action_parse_query( $query ) {
		global $pagenow;

		if ( $pagenow == 'edit.php' && isset( $query->query_vars['post_type'] ) && $query->query_vars['post_type'] == self::POST_TYPE_SLUG
		&& isset( $query->query_vars[ self::TAXONOMY_SLUG] ) && is_numeric( $query->query_vars[ self::TAXONOMY_SLUG ] ) && $query->query_vars[ self::TAXONOMY_SLUG ] != 0 ) {
			$term = get_term_by( 'id', $q_vars[$taxonomy], $taxonomy );
			$query->query_vars[ self::TAXONOMY_SLUG ] = $term->slug;
		}
	}

	/**
	 * Rewrite the slide permalinks in order to play nice with reveal.js
	 *
	 * @return string
	 */
	public function append_query_string( $url, $post ) {
		global $pagenow;

		// Do not do this on the create new post screen since there is no post ID yet
		if( $pagenow != 'post-new.php' && self::POST_TYPE_SLUG == $post->post_type ) {

			$terms = get_the_terms( $post->ID, self::TAXONOMY_SLUG );
			if( is_array( $terms ) ) {
				$terms = array_values( get_the_terms( $post->ID, self::TAXONOMY_SLUG ) );
				$term = $terms[0];
				$url = home_url( implode( '/', array( self::TAXONOMY_SLUG, $term->slug, '#', $post->post_name ) ) );
			}
		}
		return $url;
	}

	/**
	 * Append the slide post type to the query string
	 *
	 * @return null
	 */
	public function filter_get_edit_term_link( $location ) {
		return add_query_arg( array( 'post_type' => self::POST_TYPE_SLUG ), $location );
	}

	/**
	 * Render the TinyMCE editor
	 *
	 * @return null
	 */
    public function modal_editor( $post_id = '' ) {
        wp_editor( $content = '', $editor_id = 'editor_' . self::POST_TYPE_SLUG, array(
			'wpautop' => false, // use wpautop?
			'media_buttons' => true, // show insert/upload button(s)
			'textarea_name' => $editor_id, // set the textarea name to something different, square brackets [] can be used here
			'textarea_rows' => 20,
			'tabindex' => '',
			'tabfocus_elements' => ':prev,:next', // the previous and next element ID to move the focus to when pressing the Tab key in TinyMCE
			'editor_css' => '<style>wp-editor-area{ background: blue; }</style>', // intended for extra styles for both visual and Text editors buttons, needs to include the <style> tags, can use "scoped".
			'editor_class' => '', // add extra class(es) to the editor textarea
			'teeny' => false, // output the minimal editor config used in Press This
			'dfw' => false, // replace the default fullscreen with DFW (needs specific DOM elements and css)
            /*'tinymce' => array(
            	'plugins' => 'inlinepopups, wordpress, wplink, wpdialogs',
             ),*/
			'quicktags' => true // load Quicktags, can be used to pass settings directly to Quicktags using an array()
		) );
    }

	/**
	 * Modify the TinyMCE editor
	 *
	 * @return array
	 */
	public function filter_tiny_mce_before_init( $args ) {
   		$args['body_class'] = 'reveal';
   		$args['height'] = '100%';
   		$args['wordpress_adv_hidden'] = false;
   		//$args['resize'] = "both";
    	return $args;
	}

	/**
	 * Load External TinyMCE plugins
	 *
	 * @return array
	 */
	public function filter_mce_external_plugins() {
		return;
		$plugins = array( 'autoresize', 'autolink', 'code' ); //Add any more plugins you want to load here
		$plugins_array = array();

		//Build the response - the key is the plugin name, value is the URL to the plugin JS
		foreach ($plugins as $plugin ) {
			$plugins_array[ $plugin ] = $this->plugins_url . '/js/tinymce/plugins/' . $plugin . '/editor_plugin.js';
		}
		return $plugins_array;
	}

	/**
	 * AJAX Get Slide
	 *
	 * @return array
	 */
	public function action_wp_ajax_get_slide() {
		// Nonce check
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], self::NONCE_FIELD ) ) {
			wp_die( $this->nonce_fail_message );
		}

		$post_id = $_REQUEST['id'];
		$post = get_post( $post_id );

		$post->post_thumbnail_url = wp_get_attachment_url( get_post_thumbnail_id( $post->ID ) );
		$post->background_color = get_post_meta( $post->ID, 'background-color', true );
		$post->text_color = get_post_meta( $post->ID, 'text-color', true );
		$post->link_color = get_post_meta( $post->ID, 'link-color', true );

		echo json_encode( $post );
		die();
	}

	/**
	 * AJAX Update Slide
	 *
	 * @return array
	 */
	public function action_wp_ajax_update_slide() {
		// Nonce check
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], self::NONCE_FIELD ) ) {
			wp_die( $this->nonce_fail_message );
		}

		global $post, $wpdb;

		$post_id      = $_REQUEST['id'];
		$safe_content = wp_kses_post( $_REQUEST['content'] );
		$safe_title   = sanitize_text_field( $_REQUEST['title'] );
		$thumbnail_id = esc_url( url_to_postid( $_REQUEST['background-image'] ) );

		$safe_background_color = sanitize_text_field( $_REQUEST['background-color'] );
		$safe_text_color       = sanitize_text_field( $_REQUEST['text-color'] );
		$safe_link_color       = sanitize_text_field( $_REQUEST['link-color'] );

		// Work around for getting the attachment id
		$prefix       = $wpdb->prefix;
		$attachment   = $wpdb->get_col($wpdb->prepare( "SELECT ID FROM " . $prefix . "posts" . " WHERE guid='%s';", esc_url( $_REQUEST['background-image'] ) ) );
		$thumbnail_id = ( isset( $attachment[0] ) ) ? $attachment[0] : false;

		$updated_post = array(
			'ID'           => $post_id,
			'post_content' => $safe_content,
			'post_title'   => $safe_title,
		);
		wp_update_post( $updated_post );

		update_post_meta( $post_id, 'background-color', $safe_background_color );
		update_post_meta( $post_id, 'text-color', $safe_text_color );
		update_post_meta( $post_id, 'link-color', $safe_link_color );

		$post = get_post( $post_id );
		setup_postdata( $post );

		// Thumbnail
		if( ! isset( $thumbnail_id ) || empty( $thumbnail_id ) )
			delete_post_thumbnail( $post );
		else
			set_post_thumbnail( $post, $thumbnail_id );

		the_excerpt();
		wp_reset_postdata();

		die();
	}

	/**
	 * AJAX Add Slide
	 *
	 * @return array
	 */
	public function action_wp_ajax_new_slide() {
		// Nonce check
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], self::NONCE_FIELD ) ) {
			wp_die( $this->nonce_fail_message );
		}

		global $post;
		$safe_content = wp_kses_post( $_REQUEST['content'] );
		$safe_title   = sanitize_text_field( $_REQUEST['title'] );

		$presentation = get_term_by( 'id', $_REQUEST['presentation'], self::TAXONOMY_SLUG );

		$new_post = array(
			'post_title'   => ( $safe_title ) ? $safe_title : strip_tags( $safe_content ),
			'post_content' => $safe_content,
			'post_status'  => 'publish',
			'post_type'    => self::POST_TYPE_SLUG
		);

		$post_id = wp_insert_post( $new_post );
		wp_set_object_terms( $post_id , $presentation->name, self::TAXONOMY_SLUG );

		$post = get_post( $post_id );
		$this->admin_render_slide( $post );
		die();
	}

	/**
	 * AJAX Delete Slide
	 *
	 * @return array
	 */
	public function action_wp_ajax_delete_slide() {
		// Nonce check
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], self::NONCE_FIELD ) ) {
			wp_die( $this->nonce_fail_message );
		}

		global $post;
		$post_id = $_REQUEST['id'];

		// Trash this slide
		wp_trash_post( $post_id );
		die();
	}

	/**
	 * AJAX Save Presentation
	 *
	 * @return array
	 */
	public function action_wp_ajax_update_presentation() {
		// Nonce check
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], self::NONCE_FIELD ) ) {
			wp_die( $this->nonce_fail_message );
		}

		$presentation_id  = $_REQUEST['id'];
		$safe_description = sanitize_text_field( $_REQUEST['content'] );

		$updated_presentation = array(
			'description' => $safe_description
		);

		wp_update_term( $presentation_id, self::TAXONOMY_SLUG, $updated_presentation );
		die();
	}

	/**
	 * Register custom image sizes
	 *
	 * @return null
	 */
	public function register_image_sizes() {
		if( function_exists('add_theme_support') && function_exists( 'add_image_size' ) ) {
			add_theme_support('post-thumbnails');
			add_image_size( 'reveal-small', 320, 320, false );
			add_image_size( 'reveal-medium', 640, 640, false );
			add_image_size( 'reveal-large', 1024, 1024, false );
		}
	}

	/**
	 * Alter the Media Modal size dropmenu
	 *
	 * @return array
	 */
	public function filter_image_size_names_choose( $sizes ) {
		global $_wp_additional_image_sizes;
		$sizes = array_merge( $sizes, array(
			'reveal-small'  => __( self::POST_TYPE_SINGULAR . ' Small' ),
			'reveal-medium' => __( self::POST_TYPE_SINGULAR . ' Medium' ),
			'reveal-large'  => __( self::POST_TYPE_SINGULAR . ' Large' ),
		) );
		return $sizes;
	}

} // Class
WP_Present_Core::instance();