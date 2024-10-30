<?php
/*
Plugin Name: Big Cartel Product Importer
Plugin URI: http://www.webdevstudios.com
Description: Import your products from Big Cartel to a Product custom post type in WordPress.
Version: 1.0.2
Author: WebDevStudios
Author URI: http://www.webdevstudios.com
License: GPLv2
*/

/**
 * Enqueue some styles.
 */
function big_cartel_importer_styles() {
	wp_enqueue_style( 'big_cartel_settings_styles', plugins_url( '/big-cartel-importer/css/big-cartel-styles.css', dirname( __FILE__ ) ) );
}
add_action( 'init', 'big_cartel_importer_styles' );

/**
 * Class WDS_BC_Importer
 */
class WDS_BC_Importer {

	/**
	 * BigCartel object.
	 *
	 * @since 1.0.0
	 * @var mixed
	 */
	public $bc_object = null;

	/**
	 * WDS_BC_Importer constructor.
	 */
	public function __construct() {

		// Setup all our necessary variables.
		$plugin_dir_path  = dirname( __FILE__ );
		$this->options    = get_option( 'big_cartel_importer_plugin_options' );
		$this->store_name = ( isset( $this->options['store_name'] ) ) ? esc_html( $this->options['store_name'] ) : '';

		if ( ! empty( $this->store_name ) ) {
			// Set a URL to check if the store is in maintenance mode.
			$maintenance = wp_remote_get( 'http://api.bigcartel.com/' . $this->store_name . '/products.js' );
		}

		// If status is OK, proceed.
		if ( ! empty ( $maintenance ) && 200 === wp_remote_retrieve_response_code( $maintenance ) ) {
			$this->bc_object = ( ! empty( $this->store_name ) ) ? json_decode( file_get_contents( 'http://api.bigcartel.com/' . $this->store_name . '/products.js' ) ) : '';
		}

		$this->metabox_settings = array(
			'id'       => 'big-cartel-metabox',
			'title'    => 'Product Information',
			'page'     => 'bc_import_products',
			'context'  => 'normal',
			'priority' => 'high',
			'fields'   => array(
				array(
					'name' => 'ID',
					'desc' => 'Big Cartel product ID number.',
					'id'   => 'big_cartel_importer_id',
					'type' => 'text',
					'std'  => ''
				),
				array(
					'name' => 'Price',
					'desc' => 'Enter the price of the product without a dollar sign.',
					'id'   => 'big_cartel_importer_price',
					'type' => 'text',
					'std'  => ''
				),
				array(
					'name' => 'Big Cartel URL',
					'desc' => 'The URL for the product in your Big Cartel store.',
					'id'   => 'big_cartel_importer_link',
					'type' => 'text',
					'std'  => ''
				)
			)
		);

		// Hook in all our necessary functions.
		add_action( 'init', array( &$this, 'register_post_types' ) );
		add_action( 'init', array( &$this, 'register_taxonomies' ) );
		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
		add_action( 'admin_init', array( &$this, 'register_admin_settings' ) );
		add_action( 'admin_init', array( &$this, 'process_settings_save') );
		add_action( 'admin_menu', array( &$this, 'add_meta_box' ) );
		add_action( 'save_post', array( &$this, 'save_post' ) );
	}

	/**
	 * Register our custom post type.
	 */
	public function register_post_types() {

		register_post_type( 'bc_import_products', array(
				'labels'             => array(
				'name'               => 'Products',
				'singular_name'      => 'Product',
				'add_new'            => 'Add New',
				'add_new_item'       => 'Add New Product',
				'edit_item'          => 'Edit Product',
				'new_item'           => 'New Product',
				'all_items'          => 'All Products',
				'view_item'          => 'View Product',
				'search_items'       => 'Search Products',
				'not_found'          => 'No Products found',
				'not_found_in_trash' => 'No Products found in Trash',
				'parent_item_colon'  => '',
				'menu_name'          => 'Products'
			  ),
			'hierarchical'       => false,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'products' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'menu_position'      => null,
			'supports'           => array( 'title', 'editor', 'thumbnail' )
		) );
	}

	/**
	 * Register our taxonomy.
	 */
	public function register_taxonomies() {

		register_taxonomy( 'product-categories', 'bc_import_products', array(
			'labels'            => array(
				'name'                       => _x( 'Product Categories', 'taxonomy general name' ),
				'singular_name'              => _x( 'Product Category', 'taxonomy singular name' ),
				'search_items'               => __( 'Search Product Categories' ),
				'popular_items'              => __( 'Common Product Categories' ),
				'all_items'                  => __( 'All Product Categories' ),
				'parent_item'                => null,
				'parent_item_colon'          => null,
				'edit_item'                  => __( 'Edit Product Category' ),
				'update_item'                => __( 'Update Product Category' ),
				'add_new_item'               => __( 'Add New Product Category' ),
				'new_item_name'              => __( 'New Product Category' .' Name' ),
				'separate_items_with_commas' => __( 'Separate Product Categories with commas' ),
				'add_or_remove_items'        => __( 'Add or remove Product Categories' ),
				'choose_from_most_used'      => __( 'Choose from the most used Product Categories' )
			),
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'product-categories' ),
			)
		);

	}

	/**
	 * Add our menu items.
	 */
	public function admin_menu() {
		add_options_page( 'Big Cartel Importer', 'Big Cartel Importer', 'administrator', __FILE__, array( $this, 'admin_page' ) );
	}

	/**
	 * Register settings and fields.
	 */
	public function register_admin_settings() {
		register_setting( 'big_cartel_importer_plugin_options', 'big_cartel_importer_plugin_options', array( &$this, 'validate_settings' ) );
		add_settings_section( 'big_cartel_importer_main_options', '', '', __FILE__ );
		add_settings_field( 'store_name', 'Big Cartel Store Name: ', array( &$this, 'settings_store_name' ), __FILE__, 'big_cartel_importer_main_options' );
	}

	/**
	 * Build the form fields.
	 */
	public function settings_store_name() {
		// Get the total post count.
		$count_posts = wp_count_posts( 'bc_import_products' );
		$total_posts = $count_posts->publish + $count_posts->future + $count_posts->draft + $count_posts->pending + $count_posts->private;

		// Get the total term count.
		$count_terms = wp_count_terms( 'product-categories' );

		if ( ! empty( $this->store_name ) ) {
			// Set a URL to check if the store is in maintenance mode.
			$maintenance = wp_remote_get( 'http://api.bigcartel.com/' . $this->store_name . '/products.js' );
		}

		$options = get_option( 'big_cartel_importer_plugin_options' );
		echo "<div class='input-wrap'><div class='left'><input name='big_cartel_importer_plugin_options[store_name]' type='text' value='{$options['store_name']}' /></div>
		<div class='right'>If your store URL is: http://<strong>yourstorename</strong>.bigcartel.com, enter <strong>yourstorename</strong> in the text field.</div>";

		if ( is_wp_error( $maintenance ) || 200 !== wp_remote_retrieve_response_code( $maintenance ) ) {
			printf(
				'<span>%s</span></div>',
				esc_html__( 'Your store is currently in maintenance mode and can not have its products imported.', 'wdsbc' )
			);
		} else {
			printf(
				'<span>%s</span></div>',
				sprintf(
					esc_html__(
						'You have imported %s products in %s categories.',
						'wdsbc'
					),
					'<strong>' . $total_posts . '</strong>',
					'<strong>' . $count_terms . '</strong>'
				)
			);
		}
	}

	/**
	 * Sanitize the value.
	 *
	 * @todo Actually sanitize our return values.
	 *
	 * @param array $big_cartel_importer_plugin_options Array of options.
	 * @return array
	 */
	public function validate_settings( $big_cartel_importer_plugin_options ) {
		return $big_cartel_importer_plugin_options;
	}


	/**
	 * Build the admin page.
	 */
	public function admin_page() { ?>
		<div id="theme-options-wrap">
			<div class="icon32" id="icon-tools"></div>
			<h2><?php esc_html_e( 'Big Cartel Importer Options', 'wdsbc' ); ?></h2>
			<p><?php esc_html_e( 'Set the URL of your Big Cartel store to pull in your products.', 'wdsbc' ); ?></p>
			<form id="options-form" method="post" action="options.php" enctype="multipart/form-data">
				<?php settings_fields( 'big_cartel_importer_plugin_options' ); ?>
				<?php do_settings_sections(__FILE__); ?>
				<p class="submit"><input name="submit" type="submit" class="button-primary" value="<?php esc_attr_e( 'Run Import', 'wdsbc' ); ?>" /></p>
			</form>
		</div>
	<?php }

	/**
	 * Output the post data and create our posts.
	 */
	public function import_products() {

		// Grab the JSON feed as an array.
		if ( isset( $this->bc_object ) && ! empty( $this->bc_object ) ) {
			// Get our store name.
			$this->store_name = $_POST['big_cartel_importer_plugin_options']['store_name'];

			$options = get_option( 'big_cartel_importer_plugin_options' );
			foreach( $this->bc_object as $item ) {

				// Get the post status.
				$product_status = $item->status;
				$product_status != 'sold-out' ? $product_post_status = 'publish' : $product_post_status = 'private';

				// Format the date so we can set the post date as the product creation date.
				$product_publish_date = date( 'Y-m-d H:i:s', strtotime( $item->created_at ) );

				// Set some other variables in place.
				if ( isset( $item->id ) ) $product_id = intval( $item->id );
				if ( isset( $item->name ) ) $product_name = esc_html( $item->name );
				if ( isset( $item->description ) ) $product_description = wp_kses_post( $item->description );
				if ( isset( $item->price ) ) $product_price = intval( $item->price );
				if ( isset( $item->permalink ) ) $product_link = esc_url( 'http://'. $this->store_name .'.bigcartel.com/product/'. $item->permalink );
				if ( isset( $item->images[0]->url ) ) $product_image = esc_url( $item->images[0]->url );

				// Get the category list.
				$product_category_list = array();
				foreach( $item->categories as $item_category ) {
					// Build the array of attached product categories from BC.
					$product_category_list[] = $item_category->name;
					$category_name = $item_category->name;
				}
				$product_categories = implode( ', ', $product_category_list );

				// Setup the array for wp_insert_post.
				$my_post = array(
					'post_title'   => $product_name,
					'post_content' => $product_description,
					'post_status'  => $product_post_status,
					'post_author'  => 1,
					'post_date'    => $product_publish_date,
					'post_type'    => 'bc_import_products',
					'tax_input'    => array( 'product-categories' => array( $product_categories ) )
				);

				if ( ! get_page_by_title( $my_post['post_title'], 'OBJECT', 'bc_import_products' ) ) {

					// Insert the post into the database and set the post and term ID.
					$post_id = wp_insert_post( $my_post );

					// Get the list of categories attached to a product.
					$terms = array();
					foreach( $item->categories as $item_category ) {
						$terms[] = $item_category->name;
					}

					// Attach the categories to the posts.
					wp_set_object_terms( $post_id, $terms, 'product-categories' );

					update_post_meta( $post_id, 'big_cartel_importer_id', $product_id );
					update_post_meta( $post_id, 'big_cartel_importer_price', $product_price );
					update_post_meta( $post_id, 'big_cartel_importer_link', $product_link );

					// This will import the images to the media library.
					if ( isset( $item->images[0]->url ) ) {
						$image_url  = esc_url( $item->images[0]->url );
						$upload_dir = wp_upload_dir();
						$image_data = file_get_contents( $image_url );
						$filename   = basename( $image_url );

						if( wp_mkdir_p( $upload_dir['path'] ) )
						    $file = $upload_dir['path'] . '/' . $filename;
						else
						    $file = $upload_dir['basedir'] . '/' . $filename;

						file_put_contents( $file, $image_data );

						// Now let's assign the image to the corresponding post.
						$wp_filetype = wp_check_filetype( $filename, null );

						$attachment = array(
							'post_mime_type' => $wp_filetype['type'],
							'post_title'     => sanitize_file_name( $filename ),
							'post_content'   => '',
							'post_status'    => 'inherit'
						);

						$attach_id = wp_insert_attachment( $attachment, $file, $post_id );

						require_once( ABSPATH . 'wp-admin/includes/image.php' );

						$attach_data = wp_generate_attachment_metadata( $attach_id, $file );

						wp_update_attachment_metadata( $attach_id, $attach_data );

						set_post_thumbnail( $post_id, $attach_id );
					}

				} elseif ( $existing_post = get_page_by_title( $my_post['post_title'], 'OBJECT', 'bc_import_products' ) ) {

					// Insert the post into the database and set the post and term ID.
					$my_post['ID'] = intval( $existing_post->ID );
					$post_id = wp_update_post( $my_post );

					// Get the list of categories attached to a product.
					$terms = array();
					foreach( $item->categories as $item_category ) {
						$terms[] = $item_category->name;
					}

					// Attach the categories to the posts.
					wp_set_object_terms( $post_id, $terms, 'product-categories' );

					update_post_meta( $post_id, 'big_cartel_importer_id', $product_id );
					update_post_meta( $post_id, 'big_cartel_importer_price', $product_price );
					update_post_meta( $post_id, 'big_cartel_importer_link', $product_link );

					// This will import the images to the media library.
					if ( isset( $item->images[0]->url ) ) {
						$image_url  = esc_url( $item->images[0]->url );
						$upload_dir = wp_upload_dir();
						$image_data = file_get_contents( $image_url );
						$filename   = basename( $image_url );

						if( wp_mkdir_p( $upload_dir['path'] ) )
						    $file = $upload_dir['path'] . '/' . $filename;
						else
						    $file = $upload_dir['basedir'] . '/' . $filename;

						file_put_contents( $file, $image_data );

						// Now let's assign the image to the corresponding post.
						$wp_filetype = wp_check_filetype( $filename, null );

						$attachment = array(
							'post_mime_type' => $wp_filetype['type'],
							'post_title'     => sanitize_file_name( $filename ),
							'post_content'   => '',
							'post_status'    => 'inherit'
						);

						$attach_id = wp_insert_attachment( $attachment, $file, $post_id );

						require_once( ABSPATH . 'wp-admin/includes/image.php' );

						$attach_data = wp_generate_attachment_metadata( $attach_id, $file );

						wp_update_attachment_metadata( $attach_id, $attach_data );

						set_post_thumbnail( $post_id, $attach_id );
					}

				}

			}
		}
	}

	/**
	 * Add terms for each of our imported products.
	 */
	public function add_terms() {

		// Grab each category listed in the BC array and make it a taxonomy term.
		if ( isset( $this->bc_object ) && ! empty( $this->bc_object ) ) {
			foreach( $this->bc_object[1]->categories as $category ) {
				$term_name = $category->name;
				wp_insert_term( $term_name, 'product-categories' );
			}
		}

	}

	/**
	 * Import our products and add our new taxonomy terms on settings save.
	 */
	public function process_settings_save() {

		if ( empty( $_POST ) ) {
			return;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( ! empty( $this->store_name ) ) {
			// Set a URL to check if the store is in maintenance mode.
			$maintenance = wp_remote_get( 'http://api.bigcartel.com/' . $this->store_name . '/products.js' );
		}

		// If status is OK, proceed.
		if ( ! empty ( $maintenance ) && 200 === wp_remote_retrieve_response_code( $maintenance ) ) {

			if ( isset( $_POST['big_cartel_importer_plugin_options']['store_name'] ) && ! empty( $_POST['big_cartel_importer_plugin_options']['store_name'] ) ) {

				// Update our class variables.
				$this->store_name = $_POST['big_cartel_importer_plugin_options']['store_name'];
				$response = wp_remote_get( 'http://api.bigcartel.com/' . $this->store_name . '/products.js' );
				$this->bc_object  = json_decode( wp_remote_retrieve_body( $response ) );

				// Add our terms and import our products.
				$this->add_terms();
				$this->import_products();
			}

		}
	}


	/**
	 * Add the meta box.
	 */
	public function add_meta_box() {
		add_meta_box( $this->metabox_settings['id'], $this->metabox_settings['title'], array( $this, 'metabox_fields' ), $this->metabox_settings['page'], $this->metabox_settings['context'], $this->metabox_settings['priority'] );
	}

	/**
	 * Display the box on the post edit page
	 */
	public function metabox_fields() {
		global $post;

		// Setup a nonce.
		echo '<input type="hidden" name="big_cartel_importer_nonce" value="'. wp_create_nonce( basename( __FILE__ ) ) .'" />';

		// Display it all!
		echo '<table class="form-table">';
		foreach ( $this->metabox_settings['fields'] as $field ) {
			$meta = get_post_meta( $post->ID, $field['id'], true );
			echo '<tr><th style="width: 20%"><label for="'. $field['id'] .'">'. $field['name'] .'</label></th><td>';
			switch ( $field['type'] ) {
				case 'text':
					echo '<input type="text" name="', $field['id'], '" id="', $field['id'], '" value="', $meta ? $meta : $field['std'], '" size="30" style="width:97%" /><br />'. $field['desc'];
					break;
			}
			echo '</td></tr>';
		}
		echo '</table>';
	}

	/**
	 * Save our meta data.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_post( $post_id ) {

		if ( ! isset( $_POST['big_cartel_importer_nonce'] ) || ! wp_verify_nonce( $_POST['big_cartel_importer_nonce'], basename( __FILE__ ) ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( 'page' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}
		} elseif ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( $this->metabox_settings['fields'] as $field ) {
			$old = get_post_meta( $post_id, $field['id'], true );
			$new = $_POST[ $field['id'] ];

			if ( $new && $new != $old ) {
				update_post_meta( $post_id, $field['id'], $new );
			} elseif ( '' == $new && $old ) {
				delete_post_meta( $post_id, $field['id'], $old );
			}
		}
	}
}
new WDS_BC_Importer;
