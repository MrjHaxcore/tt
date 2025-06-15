<?php

//default display options
function dacl_default_display_options() {
	$defaults = array(
		'dacl_enable_archive'            => "1",
		'dacl_archive_slug'              => 'dacl_coupons',
		'dacl_nav_title'                 => __('All Coupons', 'deals-and-coupons-lite'),
		'dacl_archive_limit'             => 9,
		'dacl_enable_single'             => "1",
		'dacl_single_template'           => 'post',
		'dacl_single_position'           => 'right',
		'dacl_call_to_action'            => __('Get This Deal', 'deals-and-coupons-lite'),
		'dacl_click_to_reveal_text'      => __('Click to Reveal', 'deals-and-coupons-lite'),
		'dacl_click_to_reveal_behavior'  => 'newtabpopup',
		'dacl_no_code_needed_text'       => __('No Code Needed', 'deals-and-coupons-lite'),
		'dacl_description_height'        => '90px',
		'dacl_wrapper_width'             => '1200px',
		'dacl_wrapper_padding'           => '20px',
		'dacl_related_coupons_locations' => array(0 => "coupon"),
		'dacl_related_coupons_text'      => __('Related Coupons', 'deals-and-coupons-lite')
	);
	return apply_filters('dacl_default_display_options', $defaults);
}

//display options callback
function dacl_display_options_callback() {
	echo "<p class='deals-and-coupons-subheading'>" . esc_html__('Change the primary display settings for your coupons.', 'deals-and-coupons-lite') . "</p>";
}

// Add sanitization callback for display options
function dacl_sanitize_display_options($input) {
	// Create a new array for sanitized input
	$new_input = array();

	// Sanitize each option based on its type
	foreach ($input as $key => $value) {
		if (
			strpos($key, 'enable_') !== false || strpos($key, 'dacl_enable_') !== false ||
			$key === 'dacl_single_template_image' ||
			strpos($key, 'hide_') !== false
		) {
			// Sanitize checkboxes
			$new_input[$key] = isset($value) ? '1' : '0';
		} elseif (strpos($key, 'slug') !== false) {
			// Sanitize slugs
			$new_input[$key] = sanitize_title($value);
		} elseif (strpos($key, 'url') !== false || strpos($key, 'image') !== false) {
			// Sanitize URLs
			$new_input[$key] = esc_url_raw($value);
		} elseif (strpos($key, 'html') !== false || strpos($key, 'content') !== false) {
			// Sanitize HTML content
			$new_input[$key] = wp_kses_post($value);
		} elseif ($key === 'dacl_related_coupons_locations') {
			// Handle array values for related coupons locations
			if (is_array($value)) {
				$new_input[$key] = array_map('sanitize_text_field', $value);
			} else {
				// If somehow not an array, initialize as empty array
				$new_input[$key] = array();
			}
		} else {
			// Default sanitization for text fields
			$new_input[$key] = sanitize_text_field($value);
		}
	}

	return $new_input;
}

//initialize styling options
function dacl_initialize_display_options() {
	// Register settings with sanitization callback
	register_setting(
		'dacl_display_options',
		'dacl_display_options',
		'dacl_sanitize_display_options'
	);

	$display_options = get_option('dacl_display_options');

	// Ensure dacl_related_coupons_locations is an array
	if (isset($display_options['dacl_related_coupons_locations']) && !is_array($display_options['dacl_related_coupons_locations'])) {
		$display_options['dacl_related_coupons_locations'] = array('dacl_coupon');
		update_option('dacl_display_options', $display_options);
	}

	//get/create option
	if (false == get_option('dacl_display_options')) {
		add_option('dacl_display_options', apply_filters('dacl_default_display_options', dacl_default_display_options()));
	}

	//archive section
	add_settings_section('dacl_archive', __('Coupon Archive', 'deals-and-coupons-lite'), __('Customize your Coupon Archive.', 'deals-and-coupons-lite'), 'dacl_display_options');

	//Enable Coupon Archive Template
	add_settings_field(
		'dacl_enable_archive',
		dacl_title(__('Enable Custom Template', 'deals-and-coupons-lite'), 'dacl_enable_archive', 'https://wpcoupons.io/docs/coupon-archive/#enable-coupon-archive-template'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_archive',
		array(
			'id'      => 'dacl_enable_archive',
			'class'   => 'deals-and-coupons-input-controller',
			'tooltip' => __('If checked, this will auto generate a coupon archive using the archive slug below. Disable this if you would rather use the [coupon-archive] shortcode to print the coupon archive inside of a page. This can be useful if your theme has some conflicts with our archive template.', 'deals-and-coupons-lite')
		)
	);

	//Archive URL
	add_settings_field(
		'dacl_archive_slug',
		dacl_title(__('Archive URL', 'deals-and-coupons-lite'), 'dacl_archive_slug', 'https://wpcoupons.io/docs/coupon-archive/#archive-url'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_archive',
		array(
			'input'       => 'text',
			'id'          => 'dacl_archive_slug',
			'placeholder' => 'dacl_coupons',
			'tooltip'     => __('This will change the slug for the coupon archive URL as well as the single coupon URLs. Default: http://example.com/coupons/', 'deals-and-coupons-lite')
		)
	);

	//Coupon Type Slug
	add_settings_field(
		'dacl_type_slug',
		dacl_title(__('Coupon Type Slug', 'deals-and-coupons-lite'), 'dacl_type_slug', 'https://wpcoupons.io/docs/coupon-archive/#coupon-type-slug'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_archive',
		array(
			'input'       => 'text',
			'id'          => 'dacl_type_slug',
			'class'       => 'dacl_enable_archive' . (empty($display_options['dacl_enable_archive']) ? ' hidden' : ''),
			'placeholder' => 'all-' . (!empty($display_options['dacl_archive_slug']) ? $display_options['dacl_archive_slug'] : 'dacl_coupons'),
			'tooltip'     => __('This will change the parent slug for the coupon type pagination URLs used on the coupon archive. This cannot be the same as your Archive URL.', 'deals-and-coupons-lite')
		)
	);

	//Nav Title
	add_settings_field(
		'dacl_nav_title',
		dacl_title(__('Nav Title', 'deals-and-coupons-lite'), 'dacl_nav_title', 'https://wpcoupons.io/docs/coupon-archive/#nav-title'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_archive',
		array(
			'input'       => 'text',
			'id'          => 'dacl_nav_title',
			'placeholder' => __('All Coupons', 'deals-and-coupons-lite'),
			'tooltip'     => __('This will change the text for the first navigation element on the coupon archive template and shortcode. Default: All Coupons', 'deals-and-coupons-lite')
		)
	);

	//Nav Style
	add_settings_field(
		'dacl_nav_style',
		dacl_title(__('Nav Style', 'deals-and-coupons-lite'), 'dacl_nav_style', 'https://wpcoupons.io/docs/coupon-archive/#nav-style'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_archive',
		array(
			'input'   => 'select',
			'id'      => 'dacl_nav_style',
			'options' => array(
				''         => __('Default', 'deals-and-coupons-lite'),
				'vertical' => __('Vertical', 'deals-and-coupons-lite'),
				'dropdown' => __('Dropdown', 'deals-and-coupons-lite')
			),
			'tooltip' => __('This will change the style and placement of your coupon archive navigation.', 'deals-and-coupons-lite')
		)
	);

	//Enable Tag Subnav
	add_settings_field(
		'dacl_enable_subnav',
		dacl_title(__('Enable Tag Subnav', 'deals-and-coupons-lite'), 'dacl_enable_subnav', 'https://wpcoupons.io/docs/coupon-archive/#enable-tag-subnav'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_archive',
		array(
			'id'      => 'dacl_enable_subnav',
			'tooltip' => __('If checked, this will enable tags to be used as subnavigation for the Coupon Archive and Archive Shortcode. You may need to refresh your permalink settings after enabling this option. Default: Unchecked', 'deals-and-coupons-lite')
		)
	);

	//Archive Layout
	add_settings_field(
		'dacl_archive_layout',
		dacl_title(__('Layout', 'deals-and-coupons-lite'), 'dacl_archive_layout', 'https://wpcoupons.io/docs/coupon-archive/#archive-layout'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_archive',
		array(
			'input'   => 'select',
			'id'      => 'dacl_archive_layout',
			'options' => array(
				'2'            => __('2 Columns', 'deals-and-coupons-lite'),
				''             => __('3 Columns (Default)', 'deals-and-coupons-lite'),
				'4'            => __('4 Columns', 'deals-and-coupons-lite'),
				'5'            => __('5 Columns', 'deals-and-coupons-lite'),
				'list'         => __('List View', 'deals-and-coupons-lite'),
				'list-compact' => __('List Compact View', 'deals-and-coupons-lite'),
				'list-minimal' => __('List Minimal View', 'deals-and-coupons-lite')
			),
			'tooltip' => __('This will change the layout of the main coupon archive as well as the [coupon-archive] shortcode.', 'deals-and-coupons-lite')
		)
	);

	//Archive Sort Order
	add_settings_field(
		'dacl_archive_sort_order',
		dacl_title(__('Sort Order', 'deals-and-coupons-lite'), 'dacl_archive_sort_order', 'https://wpcoupons.io/docs/coupon-archive/#archive-sort-order'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_archive',
		array(
			'input'   => 'select',
			'id'      => 'dacl_archive_sort_order',
			'options' => array(
				''         => __('Date (Default)', 'deals-and-coupons-lite'),
				'modified' => __('Last Modified', 'deals-and-coupons-lite'),
				'title'    => __('Title', 'deals-and-coupons-lite'),
				'rand'     => __('Random', 'deals-and-coupons-lite')
			),
			'tooltip' => __('This will change the sort order of the main coupon archive as well as the [coupon-archive] shortcode.', 'deals-and-coupons-lite')
		)
	);

	//Archive Post Limit
	add_settings_field(
		'dacl_archive_limit',
		dacl_title(__('Post Limit', 'deals-and-coupons-lite'), 'dacl_archive_limit', 'https://wpcoupons.io/docs/coupon-archive/#archive-post-limit'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_archive',
		array(
			'input'       => 'text',
			'id'          => 'dacl_archive_limit',
			'placeholder' => 9,
			'tooltip'     => __('This will change the number of posts per page displayed in the main Coupon Archive. Default: 9', 'deals-and-coupons-lite')
		)
	);

	//Facebook Image
	add_settings_field(
		'dacl_archive_facebook_image',
		dacl_title(__('Facebook Image', 'deals-and-coupons-lite'), 'dacl_archive_facebook_image', 'https://wpcoupons.io/docs/open-graph/'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_archive',
		array(
			'input'       => 'image',
			'id'          => 'dacl_archive_facebook_image',
			'placeholder' => '',
			'tooltip'     => __('Add an image URL here to print out an Open Graph image tag used for Facebook on your coupon archive. This is only relevant when using our Coupon Archive Template.', 'deals-and-coupons-lite')
		)
	);

	//Twitter Image
	add_settings_field(
		'dacl_archive_twitter_image',
		dacl_title(__('Twitter Image', 'deals-and-coupons-lite'), 'dacl_archive_twitter_image', 'https://wpcoupons.io/docs/twitter-image-meta-tag/'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_archive',
		array(
			'input'       => 'image',
			'id'          => 'dacl_archive_twitter_image',
			'placeholder' => '',
			'tooltip'     => __('Add an image URL here to print out an image tag used for Twitter. This is only relevant when using our Coupon Archive Template.', 'deals-and-coupons-lite')
		)
	);

	//single coupon section
	add_settings_section('dacl_single', __('Single Coupons', 'deals-and-coupons-lite'), __('Customize your Single Coupon posts.', 'deals-and-coupons-lite'), 'dacl_display_options');

	//Enable Single Coupon Template
	add_settings_field(
		'dacl_enable_single',
		dacl_title(__('Enable Custom Template', 'deals-and-coupons-lite'), 'dacl_enable_single', 'https://wpcoupons.io/docs/single-coupons/#enable-single-coupon-template'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_single',
		array(
			'id'      => 'dacl_enable_single',
			'class'   => 'deals-and-coupons-input-controller',
			'tooltip' => __("If checked, single coupon posts will display using our custom template. Disable this if you would rather use your theme's default post template to display single coupon posts. This can be useful if your theme has some conflicts with our custom single post template.", 'deals-and-coupons-lite')
		)
	);

	//Single Coupon Template
	add_settings_field(
		'dacl_single_template',
		dacl_title(__('Theme Template', 'deals-and-coupons-lite'), 'dacl_single_template', 'https://wpcoupons.io/docs/single-coupons/#single-coupon-theme-template'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_single',
		array(
			'input'   => 'select',
			'id'      => 'dacl_single_template',
			'options' => array(
				'post' => __('Post Template', 'deals-and-coupons-lite'),
				'page' => __('Page Template', 'deals-and-coupons-lite'),
			),
			'class'   => 'dacl_enable_single' . (!empty($display_options['dacl_enable_single']) ? ' hidden' : ''),
			'tooltip' => __('If the previous option is unchecked, this will allow you to choose which default template is used for single coupon posts. Default: Post Template', 'deals-and-coupons-lite')
		)
	);

	//Single Coupon Panel Position
	add_settings_field(
		'dacl_single_position',
		dacl_title(__('Coupon Panel Position', 'deals-and-coupons-lite'), 'dacl_single_position', 'https://wpcoupons.io/docs/single-coupons/#single-coupon-panel-position'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_single',
		array(
			'input'   => 'select',
			'id'      => 'dacl_single_position',
			'options' => array(
				'right' => __('Right', 'deals-and-coupons-lite'),
				'left'  => __('Left', 'deals-and-coupons-lite'),
				'top'   => __('Top', 'deals-and-coupons-lite')
			),
			'class'   => 'dacl_enable_single' . (empty($display_options['dacl_enable_single']) ? ' hidden' : ''),
			'tooltip' => __('This will change the position of the coupon panel on the single post template. Default: Right', 'deals-and-coupons-lite')
		)
	);

	//Display Single Template Image
	add_settings_field(
		'dacl_single_template_image',
		dacl_title(__('Display Coupon Panel Image', 'deals-and-coupons-lite'), 'dacl_single_template_image', 'https://wpcoupons.io/docs/single-coupons/#display-single-template-image'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_single',
		array(
			'id'      => 'dacl_single_template_image',
			'class'   => 'dacl_enable_single' . (!empty($display_options['dacl_enable_single']) ? ' hidden' : ''),
			'tooltip' => __("If checked, the featured image will be displayed on the coupon panel in your Single Coupon Posts. This option is only relevant when our Enable Single Coupon Template option is turned off. It is helpful for those who have themes that do not display featured images on their page and post templates. Default: Unchecked", 'deals-and-coupons-lite')
		)
	);

	//Hide Single Coupon Post Title
	add_settings_field(
		'dacl_hide_single_template_title',
		dacl_title(__('Hide Content Title', 'deals-and-coupons-lite'), 'dacl_hide_single_template_title', 'https://wpcoupons.io/docs/single-coupons/#hide-single-template-title'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_single',
		array(
			'id'      => 'dacl_hide_single_template_title',
			'class'   => 'dacl_enable_single' . (empty($display_options['dacl_enable_single']) ? ' hidden' : ''),
			'tooltip' => __("If checked, this will prevent the post title from printing above the content area on your Single Coupon Posts. This option is only relevant when our Enable Single Coupon Template option is turned on. Default: Unchecked", 'deals-and-coupons-lite')
		)
	);

	//Hide Breadcrumbs
	add_settings_field(
		'dacl_hide_breadcrumbs',
		dacl_title(__('Hide Breadcrumbs', 'deals-and-coupons-lite'), 'dacl_hide_breadcrumbs', 'https://wpcoupons.io/docs/single-coupons/#hide-breadcrumbs'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_single',
		array(
			'id'      => 'dacl_hide_breadcrumbs',
			'class'   => 'dacl_enable_single' . (empty($display_options['dacl_enable_single']) ? ' hidden' : ''),
			'tooltip' => __("This will disable the breadcrumbs archive link that displays above the content on the Single Coupon Posts. Default: Unchecked", 'deals-and-coupons-lite')
		)
	);

	//Breadcrumb URL
	add_settings_field(
		'dacl_breadcrumb_url',
		dacl_title(__('Breadcrumb URL', 'deals-and-coupons-lite'), 'dacl_breadcrumb_url', 'https://wpcoupons.io/docs/single-coupons/#breadcrumb-url'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_single',
		array(
			'id'      => 'dacl_breadcrumb_url',
			'input'   => 'text',
			'class'   => 'dacl_enable_single' . (empty($display_options['dacl_enable_single']) ? ' hidden' : ''),
			'tooltip' => __("Manually specify the full URL where the breadcrumbs link to.", 'deals-and-coupons-lite')
		)
	);

	//Banner Title Tag
	add_settings_field(
		'dacl_banner_title_tag',
		dacl_title(__('Title Tag', 'deals-and-coupons-lite'), 'dacl_banner_title_tag', 'https://wpcoupons.io/docs/single-coupons/#banner-title-tag'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_single',
		array(
			'input'   => 'select',
			'id'      => 'dacl_banner_title_tag',
			'options' => array(
				''   => 'h1 (' . __('Default', 'deals-and-coupons-lite') . ')',
				'h2' => 'h2',
				'h3' => 'h3',
				'h4' => 'h4',
				'h5' => 'h5',
				'h6' => 'h6',
				'p'  => 'p',
				'span' => 'span'
			),
			'tooltip' => __('This will control the HTML tag used to display the title on your single coupon post banner.', 'deals-and-coupons-lite')
		)
	);

	//coupon panels section
	add_settings_section('dacl_panels', __('Coupon Panels', 'deals-and-coupons-lite'), __('Customize your Coupon Panel display options.', 'deals-and-coupons-lite'), 'dacl_display_options');

	//Call to Action
	add_settings_field(
		'dacl_call_to_action',
		dacl_title(__('Call to Action Text', 'deals-and-coupons-lite'), 'dacl_call_to_action', 'https://wpcoupons.io/docs/coupon-panels/#call-to-action'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_panels',
		array(
			'input'       => 'text',
			'id'          => 'dacl_call_to_action',
			'placeholder' => __('Get This Deal', 'deals-and-coupons-lite'),
			'tooltip'     => __('This will change the call to action text on all coupon panels and buttons. Default: Get This Deal | Max Length: 25 Characters', 'deals-and-coupons-lite')
		)
	);

	//Display Discount Codes
	add_settings_field(
		'dacl_display_discount_codes',
		dacl_title(__('Display Discount Codes', 'deals-and-coupons-lite'), 'dacl_display_discount_codes', 'https://wpcoupons.io/docs/coupon-panels/#display-discount-codes'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_panels',
		array(
			'id'      => 'dacl_display_discount_codes',
			'class'   => 'deals-and-coupons-input-controller',
			'tooltip' => __('If checked, discount codes will be displayed on coupon panels across all views. Default: Unchecked', 'deals-and-coupons-lite')
		)
	);

	//Click to Reveal
	add_settings_field(
		'dacl_click_to_reveal',
		dacl_title(__('Click to Reveal', 'deals-and-coupons-lite'), 'dacl_click_to_reveal', 'https://wpcoupons.io/docs/coupon-panels/#click-to-reveal-coupons'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_panels',
		array(
			'id'      => 'dacl_click_to_reveal',
			'class'   => 'deals-and-coupons-input-controller dacl_display_discount_codes' . (empty($display_options['dacl_display_discount_codes']) ? ' hidden' : ''),
			'tooltip' => __('If checked, discount codes will be hidden by default and require the user to click to reveal a popup with the discount code, coupon info, and a link to the deal. The Display Discount Codes option must also be enabled for this to work. Default: Unchecked', 'deals-and-coupons-lite')
		)
	);

	//Click to Reveal Text
	add_settings_field(
		'dacl_click_to_reveal_text',
		dacl_title(__('Click to Reveal Text', 'deals-and-coupons-lite'), 'dacl_click_to_reveal_text', 'https://wpcoupons.io/docs/coupon-panels/#click-to-reveal-text'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_panels',
		array(
			'input'       => 'text',
			'id'          => 'dacl_click_to_reveal_text',
			'class'       => 'dacl_display_discount_codes dacl_click_to_reveal' . (empty($display_options['dacl_display_discount_codes']) || empty($display_options['dacl_click_to_reveal']) ? ' hidden' : ''),
			'placeholder' => __('Click to Reveal', 'deals-and-coupons-lite'),
			'tooltip'     => __('This will change the Click to Reveal text that is displayed on coupon panels when Click to Reveal is enabled. Default: Click to Reveal', 'deals-and-coupons-lite')
		)
	);

	//Click to Reveal Behavior
	add_settings_field(
		'dacl_click_to_reveal_behavior',
		dacl_title(__('Click to Reveal Behavior', 'deals-and-coupons-lite'), 'dacl_click_to_reveal_behavior', 'https://wpcoupons.io/docs/coupon-panels/#click-to-reveal-behavior'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_panels',
		array(
			'input'   => 'select',
			'id'      => 'dacl_click_to_reveal_behavior',
			'options' => array(
				'newtabpopup' => __('New Tab + Popup', 'deals-and-coupons-lite'),
				'popup'       => __('Popup Only', 'deals-and-coupons-lite')
			),
			'class'   => 'dacl_display_discount_codes dacl_click_to_reveal' . (empty($display_options['dacl_display_discount_codes']) || empty($display_options['dacl_click_to_reveal']) ? ' hidden' : ''),
			'tooltip' => __('If the Click to Reveal option is enabled, this will control the behavior of the popup and new tab.', 'deals-and-coupons-lite')
		)
	);

	//Hide No Code Needed
	add_settings_field(
		'dacl_hide_no_code_needed',
		dacl_title(__('Hide No Code Needed', 'deals-and-coupons-lite'), 'dacl_hide_no_code_needed', 'https://wpcoupons.io/docs/coupon-panels/#hide-no-code-needed'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_panels',
		array(
			'id'      => 'dacl_hide_no_code_needed',
			'class'   => 'deals-and-coupons-input-controller deals-and-coupons-input-controller-reverse',
			'tooltip' => __('If checked, the No Code Needed box will not be displayed on single coupon posts if there is no discount code present. Default: Unchecked', 'deals-and-coupons-lite')
		)
	);

	//No Code Needed Text
	add_settings_field(
		'dacl_no_code_needed_text',
		dacl_title(__('No Code Needed Text', 'deals-and-coupons-lite'), 'dacl_no_code_needed_text', 'https://wpcoupons.io/docs/coupon-panels/#no-code-needed-text'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_panels',
		array(
			'input'       => 'text',
			'id'          => 'dacl_no_code_needed_text',
			'class'       => 'dacl_hide_no_code_needed' . (!empty($display_options['dacl_hide_no_code_needed']) ? ' hidden' : ''),
			'placeholder' => __('No Code Needed', 'deals-and-coupons-lite'),
			'tooltip'     => __('This will change the text displayed on single coupon posts when no discount code is present. Default: No Code Needed', 'deals-and-coupons-lite')
		)
	);

	//Title Tag
	add_settings_field(
		'dacl_title_tag',
		dacl_title(__('Title Tag', 'deals-and-coupons-lite'), 'dacl_title_tag', 'https://wpcoupons.io/docs/coupon-panels/#title-tag'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_panels',
		array(
			'input'   => 'select',
			'id'      => 'dacl_title_tag',
			'options' => array(
				'h1' => 'h1',
				''   => 'h2 (' . __('Default', 'deals-and-coupons-lite') . ')',
				'h3' => 'h3',
				'h4' => 'h4',
				'h5' => 'h5',
				'h6' => 'h6',
				'p'  => 'p',
				'span' => 'span'
			),
			'tooltip' => __('This will control the HTML tag used to display the title on your coupon panels.', 'deals-and-coupons-lite')
		)
	);

	//Coupon Description Height
	add_settings_field(
		'dacl_description_height',
		dacl_title(__('Description Height', 'deals-and-coupons-lite'), 'dacl_description_height', 'https://wpcoupons.io/docs/coupon-panels/#description-height'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_panels',
		array(
			'input'       => 'text',
			'id'          => 'dacl_description_height',
			'placeholder' => '90px',
			'tooltip'     => __('This will change the height of the coupon description container on your coupon panels. Default: 90px', 'deals-and-coupons-lite')
		)
	);

	//Display Expiration Dates
	add_settings_field(
		'dacl_display_expiration',
		dacl_title(__('Display Expiration Dates', 'deals-and-coupons-lite'), 'dacl_display_expiration', 'https://wpcoupons.io/docs/coupon-panels/#display-expiration-dates'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_panels',
		array(
			'id'      => 'dacl_display_expiration',
			'tooltip' => __('If checked, expiration dates will be displayed on coupon panels across all views if they are set. Default: Unchecked', 'deals-and-coupons-lite')
		)
	);

	//Expiration Behavior
	add_settings_field(
		'dacl_expiration_behavior',
		dacl_title(__('Expiration Behavior', 'deals-and-coupons-lite'), 'dacl_expiration_behavior', 'https://wpcoupons.io/docs/coupon-panels/#expiration-behavior'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_panels',
		array(
			'input'   => 'select',
			'id'      => 'dacl_expiration_behavior',
			'options' => array(
				''      => __('Move to Draft', 'deals-and-coupons-lite'),
				'label' => __('Label as Expired', 'deals-and-coupons-lite')
			),
			'tooltip' => __('This will control the behavior of coupon posts that pass their expiration date.', 'deals-and-coupons-lite')
		)
	);

	//Expiration Recurrence
	add_settings_field(
		'dacl_expiration_recurrence',
		dacl_title(__('Expiration Recurrence', 'deals-and-coupons-lite'), 'dacl_expiration_recurrence', 'https://wpcoupons.io/docs/coupon-panels/#expiration-recurrence'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_panels',
		array(
			'input'   => 'select',
			'id'      => 'dacl_expiration_recurrence',
			'options' => array(
				''           => __('Daily', 'deals-and-coupons-lite'),
				'twicedaily' => __('Twice Daily', 'deals-and-coupons-lite'),
				'hourly'     => __('Hourly', 'deals-and-coupons-lite')
			),
			'tooltip' => __('This will control the frequency that coupon post expiration dates are verified.', 'deals-and-coupons-lite')
		)
	);

	//Link Behavior
	add_settings_field(
		'dacl_link_behavior',
		dacl_title(__('Link Behavior', 'deals-and-coupons-lite'), 'dacl_link_behavior', 'https://wpcoupons.io/docs/coupon-panels/#link-behavior'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_panels',
		array(
			'input'   => 'select',
			'id'      => 'dacl_link_behavior',
			'options' => array(
				''       => __('Default', 'deals-and-coupons-lite'),
				'direct' => __('Direct', 'deals-and-coupons-lite'),
				'split'  => __('Split', 'deals-and-coupons-lite')
			),
			'tooltip' => __('This will control the behavior of coupon panel links.', 'deals-and-coupons-lite')
		)
	);

	//Hide Coupon Type Labels
	add_settings_field(
		'dacl_hide_coupon_type_tags',
		dacl_title(__('Hide Coupon Type Labels', 'deals-and-coupons-lite'), 'dacl_hide_coupon_type_tags', 'https://wpcoupons.io/docs/coupon-panels/#hide-coupon-type-labels'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_panels',
		array(
			'id'      => 'dacl_hide_coupon_type_tags',
			'tooltip' => __('If checked, the Coupon Type tags will no longer be displayed on coupon panels in any view. Default: Unchecked', 'deals-and-coupons-lite')
		)
	);

	//Placeholder Image
	add_settings_field(
		'dacl_placeholder_image',
		dacl_title(__('Placeholder Image', 'deals-and-coupons-lite'), 'dacl_placeholder_image', 'https://wpcoupons.io/docs/coupon-panels/#placeholder-image'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_panels',
		array(
			'input'       => 'image',
			'id'          => 'dacl_placeholder_image',
			'placeholder' => '',
			'tooltip'     => __('Add an image URL here to replace the placeholder image that is used for coupon panels with no featured image.', 'deals-and-coupons-lite')
		)
	);

	//configuration section
	add_settings_section('dacl_config', __('Configuration', 'deals-and-coupons-lite'), __('Configure the global display options for Deals and Coupons.', 'deals-and-coupons-lite'), 'dacl_display_options');

	//Template Wrapper Width
	add_settings_field(
		'dacl_wrapper_width',
		dacl_title(__('Page Width', 'deals-and-coupons-lite'), 'dacl_wrapper_width', 'https://wpcoupons.io/docs/configuration/#page-width'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_config',
		array(
			'input'       => 'text',
			'id'          => 'dacl_wrapper_width',
			'placeholder' => '1200px',
			'tooltip'     => __('This will change the width of the content area for the coupon archive and single coupon posts. Default: 1200px', 'deals-and-coupons-lite')
		)
	);

	//Template Wrapper Padding
	add_settings_field(
		'dacl_wrapper_padding',
		dacl_title(__('Page Padding', 'deals-and-coupons-lite'), 'dacl_wrapper_padding', 'https://wpcoupons.io/docs/configuration/#page-padding'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_config',
		array(
			'input'       => 'text',
			'id'          => 'dacl_wrapper_padding',
			'placeholder' => '20px',
			'tooltip'     => __('This will change the responsive padding of the content area. Default: 20px', 'deals-and-coupons-lite')
		)
	);

	//Force Global Widget Coupon
	add_settings_field(
		'dacl_force_global_widget',
		dacl_title(__('Force Global Widget', 'deals-and-coupons-lite'), 'dacl_force_global_widget', 'https://wpcoupons.io/docs/configuration/#force-global-widget'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_config',
		array(
			'input'   => 'select',
			'id'      => 'dacl_force_global_widget',
			'options' => dacl_coupon_array(),
			'tooltip' => __('This will force the coupon widget to display a specific coupon globally.', 'deals-and-coupons-lite')
		)
	);

	//Force Home Page Coupon
	add_settings_field(
		'dacl_force_home',
		dacl_title(__('Force Home Page Widget', 'deals-and-coupons-lite'), 'dacl_force_home', 'https://wpcoupons.io/docs/configuration/#force-home-page-widget'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_config',
		array(
			'input'   => 'select',
			'id'      => 'dacl_force_home',
			'options' => dacl_coupon_array(),
			'tooltip' => __('This will force the coupon widget to display a specific coupon if on the home page (overrides global widget coupon).', 'deals-and-coupons-lite')
		)
	);

	//Attribution Link
	add_settings_field(
		'dacl_attribution',
		dacl_title(__('Display Attribution Link', 'deals-and-coupons-lite'), 'dacl_attribution', 'https://wpcoupons.io/docs/configuration/#attribution-link'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_config',
		array(
			'id'      => 'dacl_attribution',
			'class'   => 'deals-and-coupons-input-controller',
			'tooltip' => __('If checked, this will display a "Powered by Deals and Coupons" link underneath the coupon widget. If you are in our affiliate program, make sure to enter your ID below.', 'deals-and-coupons-lite')
		)
	);

	//Affiliate ID
	add_settings_field(
		'dacl_affiliate_id',
		dacl_title(__('Affiliate ID', 'deals-and-coupons-lite'), 'dacl_affiliate_id', 'https://wpcoupons.io/docs/configuration/#affiliate-id'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_config',
		array(
			'input'   => 'text',
			'id'      => 'dacl_affiliate_id',
			'class'   => 'dacl_attribution' . (empty($display_options['dacl_attribution']) ? ' hidden' : ''),
			'tooltip' => __('Enter your affiliate ID here if you have attribution links enabled to add it to the referral URL.', 'deals-and-coupons-lite')
		)
	);

	//configuration section
	add_settings_section('dacl_config_related', __('Related Coupons', 'deals-and-coupons-lite'), __('Configure related coupons.', 'deals-and-coupons-lite'), 'dacl_display_options');

	//Display Related Coupons
	add_settings_field(
		'dacl_display_related_coupons',
		dacl_title(__('Display Related Coupons', 'deals-and-coupons-lite'), 'dacl_display_related_coupons', 'https://wpcoupons.io/docs/related-coupons/#display'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_config_related',
		array(
			'id'      => 'dacl_display_related_coupons',
			'tooltip' => __('If checked, a row of related coupons will be displayed below your post content. See below to manage which locations you would like related coupons to show up. Default: Unchecked', 'deals-and-coupons-lite')
		)
	);

	//Related Coupons Locations
	add_settings_field(
		'dacl_related_coupons_locations',
		dacl_title(__('Locations', 'deals-and-coupons-lite'), 'dacl_related_coupons_locations', 'https://wpcoupons.io/docs/related-coupons/#locations'),
		'dacl_print_related_coupons_locations',
		'dacl_display_options',
		'dacl_config_related',
		array(
			'id'      => 'dacl_related_coupons_locations',
			'tooltip' => __('Please select the post types you would like your related coupons to show up on. Default: Coupons', 'deals-and-coupons-lite')
		)
	);

	//Related Coupons Layout
	add_settings_field(
		'dacl_related_coupons_layout',
		dacl_title(__('Layout', 'deals-and-coupons-lite'), 'dacl_related_coupons_layout', 'https://wpcoupons.io/docs/related-coupons/#layout'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_config_related',
		array(
			'input'   => 'select',
			'id'      => 'dacl_related_coupons_layout',
			'options' => array(
				'2'            => __('2 Columns', 'deals-and-coupons-lite'),
				''             => __('3 Columns (Default)', 'deals-and-coupons-lite'),
				'4'            => __('4 Columns', 'deals-and-coupons-lite'),
				'5'            => __('5 Columns', 'deals-and-coupons-lite'),
				'list'         => __('List View', 'deals-and-coupons-lite'),
				'list-compact' => __('List Compact View', 'deals-and-coupons-lite'),
				'list-minimal' => __('List Minimal View', 'deals-and-coupons-lite')
			),
			'tooltip' => __('This will change the layout of your related coupons.', 'deals-and-coupons-lite')
		)
	);

	//Related Coupons Text
	add_settings_field(
		'dacl_related_coupons_text',
		dacl_title(__('Heading Text', 'deals-and-coupons-lite'), 'dacl_related_coupons_text', 'https://wpcoupons.io/docs/related-coupons/#text'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_config_related',
		array(
			'input'       => 'text',
			'id'          => 'dacl_related_coupons_text',
			'placeholder' => __('Related Coupons', 'deals-and-coupons-lite'),
			'tooltip'     => __('This will change the text that is displayed above your related coupons. Default: Related Coupons', 'deals-and-coupons-lite')
		)
	);

	//Related Coupons Heading Tag
	add_settings_field(
		'dacl_related_coupons_heading_tag',
		dacl_title(__('Heading Tag', 'deals-and-coupons-lite'), 'dacl_related_coupons_heading_tag', 'https://wpcoupons.io/docs/related-coupons/#heading-tag'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_config_related',
		array(
			'input'   => 'select',
			'id'      => 'dacl_related_coupons_heading_tag',
			'options' => array(
				'h1' => 'h1',
				''   => 'h2 (' . __('Default', 'deals-and-coupons-lite') . ')',
				'h3' => 'h3',
				'h4' => 'h4',
				'h5' => 'h5',
				'h6' => 'h6',
				'p'  => 'p',
				'span' => 'span'
			),
			'tooltip' => __('This will control the HTML tag used to display the heading above your related coupons.', 'deals-and-coupons-lite')
		)
	);

	//Related Coupons Hook
	add_settings_field(
		'dacl_related_coupons_hook',
		dacl_title(__('Custom Hook', 'deals-and-coupons-lite'), 'dacl_related_coupons_hook', 'https://wpcoupons.io/docs/related-coupons/#hook'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_config_related',
		array(
			'input'   => 'text',
			'id'      => 'dacl_related_coupons_hook',
			'tooltip' => __('This will cause the related coupons section to print out on the specified hook instead of directly after the_content.', 'deals-and-coupons-lite')
		)
	);

	//Related Coupons Hook Priority
	add_settings_field(
		'dacl_related_coupons_hook_priority',
		dacl_title(__('Custom Hook Priority', 'deals-and-coupons-lite'), 'dacl_related_coupons_hook_priority', 'https://wpcoupons.io/docs/related-coupons/#hook-priority'),
		'dacl_print_input',
		'dacl_display_options',
		'dacl_config_related',
		array(
			'input'   => 'text',
			'id'      => 'dacl_related_coupons_hook_priority',
			'tooltip' => __('When using the previous option to print related coupons on a custom hook, this will set the priority that the function prints in relation to others using the same hook.', 'deals-and-coupons-lite')
		)
	);
}

//print form inputs
function dacl_print_related_coupons_locations($args) {
	$option = 'dacl_display_options';
	$options = get_option('dacl_display_options');

	$post_types = get_post_types(array('public' => true), 'objects', 'and');
	if (!empty($post_types)) {
		if (isset($post_types['attachment'])) {
			unset($post_types['attachment']);
		}
		//echo "<input type='hidden' name='enabled[" . $type . "][" . $handle . "][post_types]' value='' />";
		foreach ($post_types as $key => $value) {
			echo "<label for='related-coupons-" . esc_attr($key) . "' style='margin-right: 10px;'>";
			echo "<input type='checkbox' name='" . esc_attr($option) . "[" . esc_attr($args['id']) . "][]' id='related-coupons-" . esc_attr($key) . "' value='" . esc_attr($key) . "' ";
			if (isset($options['dacl_related_coupons_locations']) && is_array($options['dacl_related_coupons_locations']) && in_array($key, $options['dacl_related_coupons_locations'], true)) {
				echo "checked";
			}
			echo " />" . esc_html($value->label);
			echo "</label>";
		}
	}

	if (!empty($args['tooltip'])) {
		dacl_tooltip($args['tooltip']);
	}
}

//return label from coupon id
function dacl_coupon_post_label($id) {

	$title = get_the_title($id);
	if (!empty($title)) {
		return $title;
	}
	return false;
}
