<?php
/*
Plugin Name: PS Custom Fields
Plugin URI: http://www.performantsoftware.com/wordpress/plugins/custom_fields/
Description: This plugin makes declaring custom fields easier. (All configuration is done in code.)
Version: 1.0.0
Author: Paul Rosen
Author URI: http://paulrosen.net
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

class PsCustomFields {
	private $custom_post_types;
	
	public function __construct($custom_post_types_) {
		$this->custom_post_types = $custom_post_types_;
		
		add_action( 'init', array($this, 'create_post_types') );
		add_action("admin_init", array($this, "admin_init"));
		add_action('save_post', array($this, 'save_details'));
		foreach($this->custom_post_types as $custom_post_type) {
			add_filter("manage_edit-" . $custom_post_type['post_type'] . "_columns", array($this, "custom_post_edit_columns"));
		}
		add_action("manage_posts_custom_column",  array($this, "custom_post_custom_columns"));

		add_action('quick_edit_custom_box', array($this, 'quickedit_posts_custom_box'), 10, 2);
		add_action('admin_footer', array($this, 'quick_edit_javascript'));
		add_filter('post_row_actions', array($this, 'expand_quick_edit_link'), 10, 2);
	}

	///////////////////////////////////////////////////////////////////////////////////////	
	private function get_type_object($type) {
		foreach($this->custom_post_types as $custom_post_type) {
			if ($custom_post_type['post_type'] == $type)
				return $custom_post_type;
		}
		return null;
	}

	public function quick_edit_javascript() {
		global $current_screen;
		$custom_post_type = $this->get_type_object($current_screen->post_type);
		if ($custom_post_type == null) return;
		if ($current_screen->id != 'edit-' . $custom_post_type['post_type']) return; 

		?>
		<script type="text/javascript">
		<!--
		function ps_set_inline_data(custom_data, nonce) {
			// revert Quick Edit menu so that it refreshes properly
			inlineEditPost.revert();
		    for (var key in custom_data) {
		        if (custom_data.hasOwnProperty(key)) {
					var el = document.getElementById('qe_' + key);
					if (el) {
						if (el.tagName === 'SELECT' || el.tagName === 'select') {
							for (var i = 0; i < el.options.length; i++) {
								if (el.options[i].value === custom_data[key]) 
									el.options[i].setAttribute("selected", "selected"); 
								else
									el.options[i].removeAttribute("selected");
							}
						}
						else
							el.value = custom_data[key];
					}
				}
			}
		}
		//-->
		</script>
		<?php
	}	

	public function expand_quick_edit_link($actions, $post) {
		global $current_screen;
		$custom_post_type = $this->get_type_object($current_screen->post_type);
		if ($custom_post_type == null) return $actions;
		if ($current_screen->id != 'edit-' . $custom_post_type['post_type']) return $actions;

		$data = array();
		foreach($custom_post_type['fields'] as $field) {
			$value = get_post_meta( $post->ID, $field['name'], TRUE);
			$data[$field['name']] = $value;
		}
		$data = json_encode($data);
		$data = str_replace('"', "&quot;", $data);
		$data = str_replace("\n", "\\n", $data);

		$nonce = wp_create_nonce( 'ps_inline_set'.$post->ID);
		$actions['inline hide-if-no-js'] = '<a href="#" class="editinline" title="';
		$actions['inline hide-if-no-js'] .= esc_attr( __( 'Edit this item inline' ) ) . '" ';
		$actions['inline hide-if-no-js'] .= " onclick=\"ps_set_inline_data({$data}, '{$nonce}')\">"; 
		$actions['inline hide-if-no-js'] .= __( 'Quick&nbsp;Edit' );
		$actions['inline hide-if-no-js'] .= '</a>';
		return $actions;	
	}
		
	private function create_input_box_label($field) {
		return "<label for='" . $field['name'] . "'>" . $field['label'] . ":</label>\n";
	}

	private function create_input_box($field, $value, $prefix) {
		$html = "";
		if ($field['type'] == 'bool') {
	    	$html .= "<select id='" . $prefix . $field['name'] . "' name='" . $field['name'] . "'>";
			if ($value == 'Yes')
	 			$html .= "<option selected='selected'>Yes</option><option>No</option>";
			else
	 			$html .= "<option>Yes</option><option selected='selected'>No</option>";
			$html .= "</select>";
  		} else {
			if (array_key_exists('placeholder', $field))
				$placeholder = "placeholder='" . $field['placeholder'] . "' ";
			else
				$placeholder = "";
    		$html .= "<input " . $placeholder . "id='" . $prefix . $field['name'] . "' name='" . $field['name'] . "' value='" . $value . "' />";
	  	}
		return $html;
	}
	
	private function create_quickedit_input($field, $value) {
		$html = "<label for='" . $field['name'] . "'><span class='title' style='width:7em;'>" . $field['label'] . "</span>\n";
		$html .= "<span class='input-text-wrap'>" . $this->create_input_box($field, $value, "qe_") . "</span></label>\n";
		return $html;
	}

	public function quickedit_posts_custom_box( $col ) {
		foreach($this->custom_post_types as $custom_post_type) {
			foreach($custom_post_type['fields'] as $field) {
    			if( $col == $field['name'] ) {
					if ($field['name'] == $custom_post_type['fields'][0]['name'])
						echo "<fieldset class='inline-edit-col-right'><div class='inline-edit-col'><div class='inline-edit-group'>\n";
					echo $this->create_quickedit_input($field, '');
					if ($field['name'] == $custom_post_type['fields'][count($custom_post_type['fields'])-1]['name'])
						echo "</div></div></fieldset>\n";
					return;
				}
			}
		}
	}

	// Create the Article post type
	public function create_post_types() {
		foreach($this->custom_post_types as $custom_post_type) {
			register_post_type( $custom_post_type['post_type'], $custom_post_type['post_type_data']);
		}
	}

	// Add the custom field meta-box to the Article editing page.
	public function admin_init(){
		foreach($this->custom_post_types as $custom_post_type) {
			add_meta_box($custom_post_type['meta_box_name'] . "-meta", $custom_post_type['meta_box_label'], 
				array($this, "init_meta_box"), 
				$custom_post_type['post_type'], "side", "low");
		}
	}

	public function ps_new_field($custom, $field) {
		if (array_key_exists($field['name'], $custom))
	  		$value = $custom[$field['name']][0];
		else
			$value = "";
	  echo "<tr>";
	  echo "<td>" . $this->create_input_box_label($field) . "</td>";
	  echo "<td>" . $this->create_input_box($field, $value, "") . "</td>";
	  echo "</tr>";
	}

	public function init_meta_box() {
		global $post;
		$custom = get_post_custom($post->ID);

		foreach($this->custom_post_types as $custom_post_type) {
			if ($post->post_type == $custom_post_type['post_type']) {
				echo "<table style='font-size: inherit;'>";
				foreach($custom_post_type['fields'] as $field) {
					$this->ps_new_field($custom, $field);
				}
				echo "</table>";
			}
		}
	}

	// Save the custom fields when saving a post.
	public function save_details($postID){
		foreach($this->custom_post_types as $custom_post_type) {
  			foreach($custom_post_type['fields'] as $field) {
				if (array_key_exists($field['name'], $_POST))
					update_post_meta($postID, $field['name'], $_POST[$field['name']]);
			}
		}
	}

	// Put the custom fields on the summary view
	public function custom_post_edit_columns($columns){
		global $post;

		foreach($this->custom_post_types as $custom_post_type) {
			if ($post->post_type == $custom_post_type['post_type']) {
				$columns = array(
			    	"cb" => "<input type=\"checkbox\" />",
			    	"title" => "Title"
			  	);

				foreach($custom_post_type['fields'] as $field) {
					$columns[$field['name']] = $field['label'];
				}
			}
	 	}

	  	return $columns;
	}

	// Populate the Articles summary columns with the custom post data.
	public function custom_post_custom_columns($column){
		global $post;

		foreach($this->custom_post_types as $custom_post_type) {
			if ($post->post_type == $custom_post_type['post_type']) {
				foreach($custom_post_type['fields'] as $field) {
					if ($column == $field['name']) {
	      				$custom = get_post_custom();
	      				echo $custom[$field['name']][0];
					}
				}
	  		}
		}
	}
	
}

?>