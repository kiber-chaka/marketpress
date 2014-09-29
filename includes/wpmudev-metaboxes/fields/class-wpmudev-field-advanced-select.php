<?php

class WPMUDEV_Field_Advanced_Select extends WPMUDEV_Field {
	/**
	 * Runs on parent construct
	 *
	 * @since 1.0
	 * @access public
	 * @param array $args {
	 *		An array of arguments. Optional.
	 *
	 *		@type bool $multiple Whether to allow multi-select or only one option.
	 *		@type string $placeholder The text that shows up when the field is empty.
	 *		@type array $options An array of $key => $value pairs of the available options.
	 *		@type string $format_dropdown_header The text to show in the dropdown header (e.g. select all, select none)
	 * }	 
	 */
	public function on_creation( $args ) {
		$this->args = array_replace_recursive(array(
			'multiple' => true,
			'placeholder' => __('Select Some Options', 'mp'),
			'options' => array(),
			'format_dropdown_header' => '',
		), $args);
		
		$this->args['class'] .= ' wpmudev-advanced-select';
		$this->args['custom']['data-placeholder'] = $this->args['placeholder'];
		$this->args['custom']['data-multiple'] = (int) $this->args['multiple'];
		$this->args['custom']['data-format-dropdown-header'] = $this->args['format_dropdown_header'];
	}

	/**
	 * Prints scripts
	 *
	 * @since 3.0
	 * @access public
	 */	
	public function print_scripts() {
	?>
<script type="text/javascript">
(function($){
	var parseOptions = function(opts){
		var options = opts.split('||'),
				theArray = [];
				
		$(options).each(function(){
			var val = this.split('='),
					obj = { "id" : val[0], "text" : val[1] };
					
			if ( obj.id.indexOf('|disabled') >= 0 ) {
				obj.disabled = true;
			}
			
			theArray.push(obj);
		});
		
		return theArray;
	}
	
	var getOptionText = function(opts, val) {
		var returnVal = '';
		
		$(opts).each(function(){
			if ( this.id == val ) {
				returnVal = this.text;
			}
		});
		
		return returnVal;
	}
	
	var initSelect2 = function(){
		$('.wpmudev-advanced-select').each(function(){
			var $this = $(this),
			options = [];
			
			if ( ! $this.is('select') ) {
				if ( $this.attr('data-options').length > 0 ) {
					options = parseOptions($this.attr('data-options'));
				}
				
				var args = {
					"allowSelectAllNone" : true,
					"multiple" : $this.attr('data-multiple'),
					"placeholder" : $this.attr('data-placeholder'),
					"initSelection" : function(element, callback){
						var data = [];
						
						console.log(element.val());
						$(element.val().split(',')).each(function(){
							data.push({ "id" : this, "text" : getOptionText(options, this) });
						});
						
						callback(data);
					},			
					"data" : options,
					"width" : "100%"					
				}
				
				if ( $this.attr('data-format-dropdown-header') !== undefined ) {
					args.formatDropdownHeader = function() { return $this.attr('data-format-dropdown-header'); };
				}
			
				$this.select2(args);
			} else {
				var args = {
					"dropdownAutoWidth" : true,
					"placeholder" : $this.attr('data-placeholder'),					
				};
				
				if ( $this.attr('data-format-dropdown-header') !== undefined ) {
					args.formatDropdownHeader = function() { return $this.attr('data-format-dropdown-header'); };
				}
				
				$this.select2(args);
			}
		});		
	}
	
	$(document).on('wpmudev_repeater_field/before_add_field_group', function(){
		$('.wpmudev-advanced-select').select2('destroy');
		$('[id^="s2id_"]').remove(); // Remove select2 autogenerated elements. For some reason there is a bug in the destroy method.
	});
	
	$(document).on('wpmudev_repeater_field/after_add_field_group', function(e, $group){
		initSelect2();
	});
	
	$(document).ready(function(){
		initSelect2();
	});
}(jQuery));
</script>
	<?php
	parent::print_scripts();
	}

	/**
	 * Sanitizes the field value before saving to database.
	 *
	 * @since 1.0
	 * @access public
	 * @param $value
	 * @param $post_id
	 */	
	public function sanitize_for_db( $value, $post_id ) {
		$value = trim($value, ',');
		return parent::sanitize_for_db($value, $post_id);
	}

	/**
	 * Displays the field
	 *
	 * @since 1.0
	 * @access public
	 * @param int $post_id
	 */
	public function display( $post_id ) {
		$value = $this->get_value($post_id);
		$vals = is_array($value) ? $value : explode(',', $value);
		$values = array();
		$options = array();
		
		foreach ( $this->args['options'] as $val => $label ) {
			$options[] = $val . '=' . $label;
		}
		
		$this->before_field();
		
		if ( $this->args['multiple'] ) :
			$this->args['custom']['data-options'] = implode('||', $options);
			echo '<input type="hidden" ' . $this->parse_atts() . ' value="' . implode(',', $vals) . '" />';
		else : ?>
			<select <?php echo $this->parse_atts(); ?>>
				<?php foreach ( $this->args['options'] as $val => $label ) : ?>
				<option value="<?php echo $val; ?>"<?php echo in_array($val, $vals) ? ' selected' : ''; ?>><?php echo $label; ?></option>
				<?php endforeach; ?>
			</select>
		<?php
		endif;
		
		$this->after_field();
	}
	
	/**
	 * Enqueues the field's scripts
	 *
	 * @since 1.0
	 * @access public
	 */
	public function enqueue_scripts() {
		wp_enqueue_script('jquery');
		wp_enqueue_script('wpmudev-field-select2', WPMUDEV_Metabox::class_url('ui/select2/select2.min.js'), array('jquery'), WPMUDEV_METABOX_VERSION);
	}
	
	/**
	 * Enqueues the field's styles
	 *
	 * @since 1.0
	 * @access public
	 */
	public function enqueue_styles() {
		wp_enqueue_style('wpmudev-field-select2',  WPMUDEV_Metabox::class_url('ui/select2/select2.css'), array(), WPMUDEV_METABOX_VERSION);
	}
}