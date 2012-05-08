Performant Software Custom Fields
 
To use this, activate the plugin, then initialize it with the following code in your theme's functions.php file:

if (class_exists('PsCustomFields')) {
	new PsCustomFields($custom_post_types);
}

Here's a sample of how the parameter might be set up:

$span_fields = array(
	array( 'label' => 'Date Started', 'name' => 'span_date_started', 'type' => 'date'),
	array( 'label' => 'Date Ended', 'name' => 'span_date_ended', 'type' => 'date'),
	array( 'label' => 'Color', 'name' => 'span_color', 'type' => 'string'),
	array( 'label' => 'Opacity', 'name' => 'span_opacity', 'type' => 'string'),
	array( 'label' => 'Start Label', 'name' => 'span_start_label', 'type' => 'string'),
	array( 'label' => 'End Label', 'name' => 'span_end_label', 'type' => 'string')
);

$span_post_type_data = array(
	'labels' => array(
		'name' => __( 'Spans' ),
		'singular_name' => __( 'Span' )
	),
	'public' => true,
	'has_archive' => true,
	'rewrite' => array('slug' => 'span'),
	'supports' => array('title')
);

$span_post_type = array( 
	'post_type' => 'ps_span',
	'post_type_data' => $span_post_type_data,
	'meta_box_label' => __( 'Span Info' ),
	'meta_box_name' => 'span_info',
	'fields' => $span_fields
);

// Include the post types to be seen by the registration code below.
$custom_post_types = array(
	$article_post_type,
	$span_post_type
);
