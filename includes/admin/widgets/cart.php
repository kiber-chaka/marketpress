<?php

//Product tags cloud
class MarketPress_Shopping_Cart_Widget extends WP_Widget {

	function MarketPress_Shopping_Cart_Widget() {
		$widget_ops = array( 'classname' => 'mp_widget mp_widget_cart', 'description' => __( 'Shows dynamic shopping cart contents along with a checkout button for your MarketPress store.', 'mp' ) );
		parent::__construct( 'mp_cart_widget', __( 'Shopping Cart', 'mp' ), $widget_ops );
	}

	function widget( $args, $instance ) {
		global $mp;

		if ( get_query_var( 'pagename' ) == 'cart' )
			return;

		if ( $instance[ 'only_store_pages' ] && !mp_is_shop_page() )
			return;

		extract( $args );

		echo $before_widget;
		$title = $instance[ 'title' ];
		if ( !empty( $title ) ) {
			echo $before_title . apply_filters( 'widget_title', $title ) . $after_title;
		};

		if ( !empty( $instance[ 'custom_text' ] ) )
			echo '<div class="custom_text">' . $instance[ 'custom_text' ] . '</div>';

		echo '<div id="mp-cart-widget" class="mp_cart_widget_content">';
		echo MP_Cart::get_instance()->cart_products_html('widget');
		echo '</div><!-- end mp-cart-widget -->';

		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance						 = $old_instance;
		$instance[ 'title' ]			 = stripslashes( wp_filter_nohtml_kses( $new_instance[ 'title' ] ) );
		$instance[ 'custom_text' ]		 = stripslashes( wp_filter_kses( $new_instance[ 'custom_text' ] ) );
		$instance[ 'only_store_pages' ]	 = !empty( $new_instance[ 'only_store_pages' ] ) ? 1 : 0;

		return $instance;
	}

	function form( $instance ) {
		$instance			 = wp_parse_args( (array) $instance, array( 'title' => __( 'Shopping Cart', 'mp' ), 'custom_text' => '', 'only_store_pages' => 0 ) );
		$title				 = $instance[ 'title' ];
		$custom_text		 = $instance[ 'custom_text' ];
		$only_store_pages	 = isset( $instance[ 'only_store_pages' ] ) ? (bool) $instance[ 'only_store_pages' ] : false;
		?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'mp' ) ?> <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" /></label></p>
		<p><label for="<?php echo $this->get_field_id( 'custom_text' ); ?>"><?php _e( 'Custom Text:', 'mp' ) ?><br />
				<textarea class="widefat" id="<?php echo $this->get_field_id( 'custom_text' ); ?>" name="<?php echo $this->get_field_name( 'custom_text' ); ?>"><?php echo esc_attr( $custom_text ); ?></textarea></label>
		</p>
		<p><input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id( 'only_store_pages' ); ?>" name="<?php echo $this->get_field_name( 'only_store_pages' ); ?>"<?php checked( $only_store_pages ); ?> />
			<label for="<?php echo $this->get_field_id( 'only_store_pages' ); ?>"><?php _e( 'Only show on store pages', 'mp' ); ?></label></p>
		<?php
	}

}

add_action( 'widgets_init', create_function( '', 'return register_widget("MarketPress_Shopping_Cart_Widget");' ) );
?>