<?php

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

$dacl_display_options = get_option('dacl_display_options');
global $dacl_css_overrides;
$dacl_css_overrides = false;

//wrapper output before our custom templates
function dacl_output_content_wrapper() {
	echo "<div id='deals-and-coupons-outer-wrapper'><div id='deals-and-coupons-wrapper'>";
}
add_action("dacl_action_before_main_content", "dacl_output_content_wrapper");

//wrapper output after our custom templates
function dacl_output_content_wrapper_end() {
	echo "</div></div>";
}
add_action("dacl_action_after_main_content", "dacl_output_content_wrapper_end");

//add open graph meta tags to archive
function dacl_print_open_graph_meta() {

	//check for archive template
	if (is_post_type_archive('dacl_coupon') || is_tax('dacl_coupon_type')) {
		$display_options = get_option('dacl_display_options');

		//facebook image
		if (!empty($display_options['dacl_archive_facebook_image'])) {
			echo "<meta property=\"og:image\" content=\"" . esc_url($display_options['dacl_archive_facebook_image']) . "\">\n";
		}

		//twitter image
		if (!empty($display_options['dacl_archive_twitter_image'])) {
			echo "<meta property=\"twitter:image\" content=\"" . esc_url($display_options['dacl_archive_twitter_image']) . "\">\n";
		}
	}
}
add_action('wp_head', 'dacl_print_open_graph_meta', 1);

//add arguments and adjust coupon archive main query
function dacl_archive_query($query) {
	if (is_admin() || !$query->is_main_query()) {
		return;
	}
	if (is_post_type_archive('dacl_coupon') || is_tax('dacl_coupon_type')) {

		$dacl_display_options = get_option('dacl_display_options');

		//make sure coupon post type is include query
		$query->set('post_type', array('dacl_coupon'));

		//set posts per page limit
		if (!empty($dacl_display_options['dacl_archive_limit'])) {
			$dacl_archive_limit = $dacl_display_options['dacl_archive_limit'];
		} else {
			$dacl_archive_limit = 9;
		}
		$query->set('posts_per_page', $dacl_archive_limit);

		//get sticky posts
		$stickies = get_option('dacl_sticky_coupons');

		if (!empty($stickies) && is_array($stickies)) {

			//filter out which ids we need for the requested type/tag
			if (!empty($query->query['dacl_coupon_type'])) {

				//type tax query
				$tax_query = array(array(
					'taxonomy' => 'dacl_coupon_type',
					'field'    => 'slug',
					'terms'    => $query->query['dacl_coupon_type']
				));

				//coupon tag tax query addition
				if (!empty($query->query['dacl_coupon_tag'])) {
					$tax_query['relation'] = 'AND';
					$tax_query[] = array(
						'taxonomy' => 'dacl_coupon_tag',
						'field'    => 'slug',
						'terms'    => $query->query['dacl_coupon_tag']
					);
				}

				//get sticky posts for type/tag and overwrite our original list
				// Note: Using tax_query can be slow on large databases, but this is limited to sticky posts only
				// which should be a small number, so the performance impact should be minimal
				$stickies = get_posts(array(
					'post__in'  => $stickies,
					'showposts' => count($stickies) > 10 ? 10 : -1, // Limit to 10 sticky posts max for performance
					'post_type' => 'dacl_coupon',
					'tax_query' => $tax_query,
					'fields'    => 'ids'
				));
			}

			if (!empty($query->query['paged'])) {

				//calculate and add query offset for sticky coupons
				$offset = ($dacl_archive_limit * ($query->query['paged'] - 1)) - count($stickies);
				$offset = $offset < 0 ? 0 : $offset;
				$query->set('offset', $offset);
			}

			//exclude sticky coupons from the query
			$query->set('post__not_in', $stickies);
		}

		//get current meta query
		$meta_query = $query->get('meta_query');

		if (empty($meta_query)) {
			$meta_query = array();
		}

		//excluded posts (meta option)
		$exclude_meta = array(
			'key' => 'dacl_exclude_archive',
			'type' => 'BINARY',
			'compare' => 'NOT EXISTS'
		);
		array_push($meta_query, $exclude_meta);

		//set final meta query
		$query->set('meta_query', $meta_query);

		$order_by = array();

		//archive sort order
		$sort_order = 'date';
		$sort_direction = 'DESC';
		if (!empty($dacl_display_options['dacl_archive_sort_order'])) {

			$sort_order = $dacl_display_options['dacl_archive_sort_order'];

			//set ascending order if sorting by title
			if ($dacl_display_options['dacl_archive_sort_order'] == 'title') {
				$sort_direction = 'ASC';
			}
		}

		// Note: Using meta_value in orderby can be slow on large databases.
		// For production sites with many coupons, consider using a custom table or caching mechanism
		// for better performance, or implement a custom sorting solution.

		// Build orderby array - we're keeping meta_value for now as it's essential for the coupon sorting functionality
		$order_by = array('meta_value' => 'DESC', $sort_order => $sort_direction);

		//set orderby
		$query->set('orderby', $order_by);

		return;
	}
}
add_action('pre_get_posts', 'dacl_archive_query', 99);

//manipulate archive posts after query has run
function dacl_archive_posts($posts, $query) {

	if (!is_admin() && $query->is_main_query() && (is_post_type_archive('dacl_coupon') || is_tax('dacl_coupon_type')) || $query->get('dacl_archive_query') == 1) {

		//get sticky coupons
		$stickies = get_option('dacl_sticky_coupons');

		//make sure we have stickies and they were excluded from the query
		if (!empty($stickies) && !empty($query->query_vars['post__not_in'])) {

			//get count of posts from previous pages
			$prev_post_count = $query->query_vars['posts_per_page'] * (!empty($query->query_vars['paged']) ? ($query->query_vars['paged'] - 1) : 0);

			//make sure we haven't already displayed stickies on previous pages
			if (empty($query->query_vars['paged']) || (count($stickies) > $prev_post_count)) {

				//get the actual sticky coupon posts
				$sticky_posts = get_posts(
					array(
						'post__in'       => $query->query_vars['post__not_in'],
						'post_type'      => 'dacl_coupon',
						'post_status'    => 'publish',
						'orderby'        => !empty($query->query_vars['orderby']) ? $query->query_vars['orderby'] : '',
						'offset'         => $prev_post_count,
						'posts_per_page' => $query->query_vars['posts_per_page']
					)
				);

				//get rid of stickies that have already been displayed on previous pages
				if (count($sticky_posts) > $prev_post_count) {
					$sticky_posts = array_slice($sticky_posts, $prev_post_count);
				}

				//add stickies to the front of the posts array for display
				$sticky_offset = 0;
				foreach ($sticky_posts as $sticky_post) {
					if (count($posts) >= $query->query_vars['posts_per_page']) {
						array_pop($posts);
					}
					array_splice($posts, $sticky_offset, 0, array($sticky_post));
					$sticky_offset++;
				}
			}
		}
	}

	return $posts;
}
add_filter('the_posts', 'dacl_archive_posts', 10, 2);

//adjust found posts count for correct pagination with sticky coupons added
function dacl_archive_sticky_offset($found_posts, $query) {

	if (!is_admin() && $query->is_main_query() && (is_post_type_archive('dacl_coupon') || is_tax('dacl_coupon_type')) || $query->get('dacl_archive_query') == 1) {

		$stickies = get_option('dacl_sticky_coupons');

		if (!empty($stickies) && !empty($query->query_vars['post__not_in'])) {
			return $found_posts + count($query->query_vars['post__not_in']);
		}
	}
	return $found_posts;
}
add_filter('found_posts', 'dacl_archive_sticky_offset', 1, 2);

//runs when coupon post is deleted
function dacl_delete_coupon_post($post_id) {

	$post = get_post($post_id);

	if ($post->post_type == 'dacl_coupon') {
		dacl_remove_sticky_post($post->ID);
	}
}
add_action('before_delete_post', 'dacl_delete_coupon_post');
add_action('wp_trash_post', 'dacl_delete_coupon_post');

//runs when coupon post status changes from published
function dacl_coupon_post_unpublished($new_status, $old_status, $post) {

	if ($post->post_type == 'dacl_coupon') {
		if ($old_status == 'publish'  &&  $new_status != 'publish') {
			dacl_remove_sticky_post($post->ID);
		}
	}
}
add_action('transition_post_status', 'dacl_coupon_post_unpublished', 20, 3);

//removes coupon post id from the sticky posts array
function dacl_remove_sticky_post($post_id) {

	$stickies = get_option('dacl_sticky_coupons');

	if (is_array($stickies) && in_array($post_id, $stickies, true)) {

		if (($key = array_search($post_id, $stickies)) !== false) {
			unset($stickies[$key]);
			asort($stickies);
			$stickies = array_values($stickies);
			update_option('dacl_sticky_coupons', $stickies);
		}
	}
}

//Exclude Individual Coupons from Search
function dacl_exclude_from_search($query) {
	if (!$query->is_admin && $query->is_search && $query->is_main_query()) {

		$dacl_extra = get_option('dacl_extra_options');

		//check that global is not set
		if (empty($dacl_extra['dacl_exclude_from_search'])) {

			$current_meta = $query->get('meta_query');
			$custom_meta = array(
				'key' => 'dacl_exclude_search',
				'type' => 'BINARY',
				'compare' => 'NOT EXISTS'
			);
			if (!empty($current_meta)) {
				$meta_query = $current_meta[] = $custom_meta;
			} else {
				$meta_query = $custom_meta;
			}
			$query->set('meta_query', array($meta_query));
		}
	}
}
add_action('pre_get_posts', 'dacl_exclude_from_search');

//add details banner to single coupon post content if custom template is disabled
function dacl_filter_content($content) {
	$display_options = get_option('dacl_display_options');
	if (is_singular('dacl_coupon') && (empty($display_options['dacl_enable_single']) || $display_options['dacl_enable_single'] != "1")) {
		$var = "<div style='overflow: hidden;'>";
		$var .= dacl_banner_before();
		$var .= "<div class='deals-and-coupons-post-content'>";
		$var .= $content;
		$var .= "</div>";
		$var .= "</div>";
		return $var;
	}
	return $content;
}
add_filter('the_content', 'dacl_filter_content');

//pagination for our custom templates
function dacl_pagination() {

	if (is_singular()) {
		return;
	}

	global $wp_query;

	//Stop execution if there's only 1 page
	if ($wp_query->max_num_pages <= 1) {
		return;
	}


	$dacl_display_options = get_option('dacl_display_options');

	//set posts per page limit
	if (!empty($dacl_display_options['dacl_archive_limit'])) {
		$dacl_archive_limit = $dacl_display_options['dacl_archive_limit'];
	} else {
		$dacl_archive_limit = 9;
	}

	$paged = get_query_var('paged') ? absint(get_query_var('paged')) : 1;
	$max   = intval($wp_query->max_num_pages);

	//Add current page to the array
	if ($paged >= 1) {
		$links[] = $paged;
	}

	/**	Add the pages around the current page to the array */
	if ($paged >= 3) {
		$links[] = $paged - 1;
		$links[] = $paged - 2;
	}

	if (($paged + 2) <= $max) {
		$links[] = $paged + 2;
		$links[] = $paged + 1;
	}

	echo "<div class='deals-and-coupons-navigation'>";
	echo "<ul>";

	//Previous Post Link 
	if (get_previous_posts_link()) {
		echo "<li>" . wp_kses_post(get_previous_posts_link("« " . __('Prev', 'deals-and-coupons-lite'))) . "</li>";
	}

	//Link to first page, plus ellipses if necessary
	if (!in_array(1, $links, true)) {
		$class = 1 == $paged ? ' class="active"' : '';
		echo "<li" . esc_attr($class) . "><a href='" . esc_url(get_pagenum_link(1)) . "'>1</a></li>";
		if (!in_array(2, $links, true)) {
			echo "<li>…</li>";
		}
	}

	//Link to current page, plus 2 pages in either direction if necessary
	sort($links);
	foreach ((array) $links as $link) {
		$class = $paged == $link ? ' class="active"' : '';
		echo "<li" . esc_attr($class) . "><a href='" . esc_url(get_pagenum_link($link)) . "'>" . absint($link) . "</a></li>";
	}

	//Link to last page, plus ellipses if necessary
	if (!in_array($max, $links, true)) {
		if (!in_array($max - 1, $links, true)) {
			echo "<li>…</li>";
		}
		$class = $paged == $max ? ' class="active"' : '';
		echo "<li" . esc_attr($class) . "><a href='" . esc_url(get_pagenum_link($max)) . "'>" . absint($max) . "</a></li>";
	}

	//Next Post Link
	if (get_next_posts_link()) {
		echo "<li>" . wp_kses_post(get_next_posts_link(__('Next', 'deals-and-coupons-lite') . " »")) . "</li>";
	}

	echo "</ul>";
	echo "</div>";
}

//coupon details banner for single coupon post
function dacl_banner_before() {

	global $post;

	$dacl_display = get_option('dacl_display_options');
	$dacl_styles = get_option('dacl_styling_options');
	$dacl_extra = get_option('dacl_extra_options');

	$id = $post->ID; // Define the $id variable using the post ID
	$dacl_call_to_action = get_post_meta($post->ID, 'dacl_call_to_action', true);
	$dacl_css_class = get_post_meta($post->ID, 'dacl_css_class', true);
	$dacl_discount_url = get_post_meta($post->ID, 'dacl_discount_url', true);
	$dacl_ctr_link = "";

	// Generate dynamic styles
	$custom_css = "
		.deals-and-coupons-banner {
			background: " . ($dacl_styles['dacl_panel_background_color'] ? $dacl_styles['dacl_panel_background_color'] : "") . ";
		}
		.deals-and-coupons-banner .deals-and-coupons-discount-percent {
			background: " . ($dacl_styles['dacl_discount_background_color'] ? $dacl_styles['dacl_discount_background_color'] : "") . ";
			color: " . ($dacl_styles['dacl_discount_text_color'] ? $dacl_styles['dacl_discount_text_color'] : "") . ";
			font-size: " . ($dacl_styles['dacl_discount_font_size'] ? $dacl_styles['dacl_discount_font_size'] : "") . ";
		}
		.deals-and-coupons-banner .deals-and-coupons-expiration {
			color: " . ($dacl_styles['dacl_expiration_text_color'] ? $dacl_styles['dacl_expiration_text_color'] : "") . ";
			font-size: " . ($dacl_styles['dacl_expiration_font_size'] ? $dacl_styles['dacl_expiration_font_size'] : "") . ";
		}
		.deals-and-coupons-banner .deals-and-coupons-discount-code {
			background: " . ($dacl_styles['dacl_discount_code_background_color'] ? $dacl_styles['dacl_discount_code_background_color'] : "") . ";
			color: " . ($dacl_styles['dacl_discount_code_text_color'] ? $dacl_styles['dacl_discount_code_text_color'] : "") . ";
			font-size: " . ($dacl_styles['dacl_discount_code_font_size'] ? $dacl_styles['dacl_discount_code_font_size'] : "") . ";
		}
		.deals-and-coupons-banner .deals-and-coupons-discount-code span {
			color: " . ($dacl_styles['dacl_discount_code_text_color'] ? $dacl_styles['dacl_discount_code_text_color'] : "") . ";
			border-color: " . ($dacl_styles['dacl_discount_code_text_color'] ? $dacl_styles['dacl_discount_code_text_color'] : "") . ";
		}
		.deals-and-coupons-banner-text .coupon-title {
			font-size: " . ($dacl_styles['dacl_title_font_size'] ? $dacl_styles['dacl_title_font_size'] : "") . ";
		}
		.deals-and-coupons-banner .deals-and-coupons-discount-description {
			font-size: " . ($dacl_styles['dacl_description_font_size'] ? $dacl_styles['dacl_description_font_size'] : "") . ";
			line-height: " . ($dacl_styles['dacl_description_line_height'] ? $dacl_styles['dacl_description_line_height'] : "") . ";
		}
		.deals-and-coupons-banner a.deals-and-coupons-button {
			background: " . ($dacl_styles['dacl_link_accent_color'] ? $dacl_styles['dacl_link_accent_color'] : "") . ";
			border-color: " . ($dacl_styles['dacl_link_accent_color'] ? $dacl_styles['dacl_link_accent_color'] : "") . ";
			font-size: " . ($dacl_styles['dacl_button_font_size'] ? $dacl_styles['dacl_button_font_size'] : "") . ";
		}
		.deals-and-coupons-banner a.deals-and-coupons-button:hover {
			background: none;
			color: " . ($dacl_styles['dacl_link_accent_color_hover'] ? $dacl_styles['dacl_link_accent_color_hover'] : "") . ";
			border-color: " . ($dacl_styles['dacl_link_accent_color_hover'] ? $dacl_styles['dacl_link_accent_color_hover'] : "") . ";
		}
		.deals-and-coupons-banner a.deals-and-coupons-breadcrumbs, #deals-and-coupons a.deals-and-coupons-breadcrumbs:visited {
			color: " . ($dacl_styles['dacl_link_accent_color'] ? $dacl_styles['dacl_link_accent_color'] : "") . ";
		}
		.deals-and-coupons-banner a.deals-and-coupons-breadcrumbs:hover {
			color: " . ($dacl_styles['dacl_link_accent_color_hover'] ? $dacl_styles['dacl_link_accent_color_hover'] : "") . ";
		}
	";

	// Enqueue styles
	wp_enqueue_style('deals-and-coupons-style', plugins_url('assets/css/style.min.css', dirname(__FILE__)), array(), DACL_VERSION, 'all');
	wp_add_inline_style('deals-and-coupons-style', esc_html(wp_strip_all_tags($custom_css)));

	if (!empty($dacl_display['dacl_display_expiration'])) {
		$dacl_expiration = get_post_meta($post->ID, "dacl_expiration", true);
		if (!empty($dacl_expiration)) {
			$today = gmdate('m/d/Y');
			if (strtotime($dacl_expiration) > strtotime($today)) {
				$expired_text = __('Expires', 'deals-and-coupons-lite');
			} else {
				$expired_text = __('Expired', 'deals-and-coupons-lite');
				$dacl_css_class .= ' deals-and-coupons-expired';
			}
			$date_format = get_option('date_format');
			$expiration_format = !empty($date_format) ? $date_format : "F j, Y";
			$expration_html = "<div class='deals-and-coupons-expiration'><span class='deals-and-coupons-clock'><svg role='img' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path fill='currentColor' d='M256,8C119,8,8,119,8,256S119,504,256,504,504,393,504,256,393,8,256,8Zm92.49,313h0l-20,25a16,16,0,0,1-22.49,2.5h0l-67-49.72a40,40,0,0,1-15-31.23V112a16,16,0,0,1,16-16h32a16,16,0,0,1,16,16V256l58,42.5A16,16,0,0,1,348.49,321Z'></path></svg></span> " . $expired_text . " " . date_i18n($expiration_format, strtotime($dacl_expiration)) . "</div>";
		}
	}

	$var = '<div class="deals-and-coupons-banner deals-and-coupons-coupon-panel' . (!empty($dacl_css_class) ? ' ' . $dacl_css_class : '') . '">';

	if (!empty($dacl_display['dacl_single_template_image']) && $dacl_display['dacl_single_template_image'] == "1") {
		if (has_post_thumbnail()) {
			$var .= get_the_post_thumbnail($post->ID, 'full');
		} else {
			// @phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- Using direct img tag for placeholder image as it's not an attachment
			$var .= "<img src='" . dacl_placeholder_image_url() . "' title='" . __('Sample Image', 'deals-and-coupons-lite') . "' alt='" . __('Sample Image', 'deals-and-coupons-lite') . "' />";
		}
	}

	$var .= '<div class="deals-and-coupons-banner-content">';
	$var .= '<div class="deals-and-coupons-discounts">';

	$dacl_discount_percent = get_post_meta($post->ID, "dacl_discount_percent", true);

	if (!empty($dacl_discount_percent)) {
		$var .= '<div class="deals-and-coupons-discount-percent">' . $dacl_discount_percent . '</div>';
	}

	if (!empty($dacl_display['dacl_display_discount_codes'])) {

		$dacl_discount_code = get_post_meta($post->ID, "dacl_discount_code", true);

		if (!empty($dacl_extra['dacl_clipboard_js']) && $dacl_extra['dacl_clipboard_js'] == "1" && !empty($dacl_discount_code)) {
			$dacl_copy = "deals-and-coupons-copy";
		} else {
			$dacl_copy = "";
		}

		if (!empty($dacl_discount_code)) {

			//click to reveal button
			if (!empty($dacl_display['dacl_click_to_reveal']) && $dacl_display['dacl_click_to_reveal'] == "1" && !empty($dacl_discount_url)) {

				$dacl_ctr_link = "deals-and-coupons-ctr-link";
				if (!empty($dacl_display['dacl_click_to_reveal_text'])) {
					$dacl_ctr_text = $dacl_display['dacl_click_to_reveal_text'];
				} else {
					$dacl_ctr_text = __('Click to Reveal', 'deals-and-coupons-lite');
				}

				$var .= "<div class='deals-and-coupons-ctr-wrapper'>";
				$var .= "<div class='deals-and-coupons-discount-code deals-and-coupons-ctr " . esc_attr($dacl_copy) . "' rel='" . esc_attr($id) . "' value='" . esc_attr($dacl_discount_url) . "' data-clipboard-text='" . esc_attr($dacl_discount_code) . "'><span class='deals-and-coupons-tag'><svg role='img' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path fill='currentColor' d='M0 252.118V48C0 21.49 21.49 0 48 0h204.118a48 48 0 0 1 33.941 14.059l211.882 211.882c18.745 18.745 18.745 49.137 0 67.882L293.823 497.941c-18.745 18.745-49.137 18.745-67.882 0L14.059 286.059A48 48 0 0 1 0 252.118zM112 64c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48-21.49-48-48-48z'></path></svg></span> " . esc_html($dacl_ctr_text) . "</div>";
				$var .= "</div>";
			} else {
				$var .= '<div class="deals-and-coupons-discount-code ' . esc_attr($dacl_copy) . '" data-clipboard-text="' . esc_attr($dacl_discount_code) . '">';
				$var .= "<span class='deals-and-coupons-tag'><svg role='img' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path fill='currentColor' d='M0 252.118V48C0 21.49 21.49 0 48 0h204.118a48 48 0 0 1 33.941 14.059l211.882 211.882c18.745 18.745 18.745 49.137 0 67.882L293.823 497.941c-18.745 18.745-49.137 18.745-67.882 0L14.059 286.059A48 48 0 0 1 0 252.118zM112 64c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48-21.49-48-48-48z'></path></svg></span> " . esc_html($dacl_discount_code);
				if (!empty($dacl_extra['dacl_clipboard_js']) && $dacl_extra['dacl_clipboard_js'] == "1") {
					$var .= "<span class='deals-and-coupons-copy-text'>" . esc_html__('Copy', 'deals-and-coupons-lite') . "</span>";
				}
				$var .= '</div>';
			}
		} elseif (empty($dacl_display['dacl_hide_no_code_needed'])) {
			$var .= '<div class="deals-and-coupons-discount-code ' . esc_attr($dacl_copy) . '" data-clipboard-text="' . esc_attr($dacl_discount_code) . '">';
			if (!empty($dacl_display['dacl_no_code_needed_text'])) {
				$no_code_needed = $dacl_display['dacl_no_code_needed_text'];
			} else {
				$no_code_needed = __('No Code Needed', 'deals-and-coupons-lite');
			}
			$var .= "<span class='deals-and-coupons-tag'><svg role='img' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path fill='currentColor' d='M0 252.118V48C0 21.49 21.49 0 48 0h204.118a48 48 0 0 1 33.941 14.059l211.882 211.882c18.745 18.745 18.745 49.137 0 67.882L293.823 497.941c-18.745 18.745-49.137 18.745-67.882 0L14.059 286.059A48 48 0 0 1 0 252.118zM112 64c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48-21.49-48-48-48z'></path></svg></span> " . esc_html($no_code_needed);
			$var .= "</div>";
		}
	}

	$var .= '</div>';

	$var .= '<div class="deals-and-coupons-banner-text">';

	$title_tag = !empty($dacl_display['dacl_banner_title_tag']) ? $dacl_display['dacl_banner_title_tag'] : 'h1';

	$var .= "<" . $title_tag . " class='coupon-title'>" . get_the_title() . "</" . $title_tag . ">";

	$dacl_discount_description = get_post_meta($post->ID, "dacl_discount_description", true);

	if (!empty($dacl_discount_description)) {
		$var .= '<div class="deals-and-coupons-discount-description">' . do_shortcode($dacl_discount_description) . '</div>';
	}

	if (!empty($expration_html)) {
		$var .= $expration_html;
	}

	$dacl_discount_url = get_post_meta($post->ID, "dacl_discount_url", true);

	if (!empty($dacl_discount_url)) {

		$dacl_nofollow = !empty($dacl_extra['dacl_force_nofollow']) ? "1" : get_post_meta(get_the_ID(), 'dacl_nofollow', true);
		if (!empty($dacl_nofollow) && $dacl_nofollow == "1") {
			$coupon_nofollow = "nofollow";
		} else {
			$coupon_nofollow = "";
		}


		if (!empty($dacl_call_to_action)) {
			$coupon_cta = $dacl_call_to_action;
		} elseif (!empty($dacl_display['dacl_call_to_action'])) {
			$coupon_cta = $dacl_display['dacl_call_to_action'];
		} else {
			$coupon_cta = __('Get This Deal', 'deals-and-coupons-lite');
		}

		$var .= '<a href="' . $dacl_discount_url . '" class="deals-and-coupons-button ' . $dacl_ctr_link . '" target="_blank" rel="' . $coupon_nofollow . '">' . $coupon_cta . '</a>';
	}

	$var .= '</div>';
	$var .= '</div>';
	$var .= '</div>';

	if (!empty($dacl_extra['dacl_clipboard_js']) && $dacl_extra['dacl_clipboard_js'] == "1") {
		if (has_action('wp_footer', 'dacl_output_clipboard_js') == false) {
			add_action('wp_footer', 'dacl_output_clipboard_js');
		}
	}

	if (!empty($dacl_display['dacl_click_to_reveal'])) {
		if (has_action('wp_footer', 'dacl_output_ctr_popup') == false) {
			add_action('wp_footer', 'dacl_output_ctr_popup');
		}
	}

	return $var;
}

//schedule expire event
if (!wp_next_scheduled('dacl_expire')) {

	//get recurrence setting
	$recurrence = !empty($dacl_display_options['dacl_expiration_recurrence']) ? $dacl_display_options['dacl_expiration_recurrence'] : 'daily';

	//schedule event
	wp_schedule_event(time(), 'daily', 'dacl_expire');
}

//update expiration event on update option
add_action('updated_option', function ($option_name, $old_value, $value) {

	if ($option_name == 'dacl_display_options') {

		//make sure expiration recurrence was changed
		if ($old_value['dacl_expiration_recurrence'] != $value['dacl_expiration_recurrence']) {

			//remove the old scheduled event
			$timestamp = wp_next_scheduled('dacl_expire');
			if (!empty($timestamp)) {
				wp_unschedule_event($timestamp, 'dacl_expire');
			}

			//create the updated schedule event
			$recurrence = !empty($value['dacl_expiration_recurrence']) ? $value['dacl_expiration_recurrence'] : 'daily';
			wp_schedule_event(time(), $recurrence, 'dacl_expire');
		}
	}
}, 10, 3);

//coupon expiration function
function dacl_expire_coupons() {
	$today = gmdate('m/d/Y');


	// Limit to 999 posts per batch for better performance
	$args = array(
		'post_type' => 'dacl_coupon',
		'post_status' => 'publish',
		'meta_key' => 'dacl_expiration',
		'posts_per_page' => 999
	);
	$query = new WP_Query($args);

	if ($query->have_posts()) {

		$dacl_display = get_option('dacl_display_options');

		while ($query->have_posts()) {
			$query->the_post();
			$expiration_date = get_post_meta(get_the_ID(), 'dacl_expiration', true);
			if (!empty($expiration_date)) {
				if (strtotime($expiration_date) < strtotime($today)) {

					if (empty($dacl_display['dacl_expiration_behavior'])) {
						$postdata = array(
							'ID' => get_the_ID(),
							'post_status' => 'draft'
						);
						wp_update_post($postdata);
					}
				}
			}
		}
		wp_reset_postdata();
	}
}
add_action('dacl_expire', 'dacl_expire_coupons');

//Clipboard.js Action
function dacl_register_scripts() {
	// Register clipboard script
	wp_register_script(
		'deals-and-coupons-clipboard-js',
		DACL_PLUGIN_URL . 'assets/js/clipboard-handler.js',
		array('clipboard'),
		DACL_VERSION,
		true
	);

	// Register nav dropdown script
	wp_register_script(
		'deals-and-coupons-nav-dropdown-js',
		DACL_PLUGIN_URL . 'assets/js/nav-dropdown.js',
		array('jquery'),
		DACL_VERSION,
		true
	);

	// Register click-to-reveal script
	wp_register_script(
		'deals-and-coupons-ctr-js',
		DACL_PLUGIN_URL . 'assets/js/click-to-reveal.js',
		array('jquery'),
		DACL_VERSION,
		true
	);

	// Register styles
	wp_register_style(
		'deals-and-coupons-styles',
		DACL_PLUGIN_URL . 'assets/css/style.min.css',
		array(),
		DACL_VERSION
	);
}
add_action('init', 'dacl_register_scripts');

function dacl_enqueue_scripts() {
	$dacl_display = get_option('dacl_display_options');
	$dacl_extra = get_option('dacl_extra_options');

	// Check if we need to load clipboard.js functionality
	if (!empty($dacl_extra['dacl_clipboard_js']) && $dacl_extra['dacl_clipboard_js'] == '1') {
		wp_enqueue_script('deals-and-coupons-clipboard-js');

		// Add localized data for clipboard script
		wp_localize_script('deals-and-coupons-clipboard-js', 'dealsAndCouponsClipboard', array(
			'copied' => __('Copied!', 'deals-and-coupons-lite'),
			'copy' => __('Copy', 'deals-and-coupons-lite'),
			'clickToReveal' => (!empty($dacl_display['dacl_click_to_reveal']) && $dacl_display['dacl_click_to_reveal'] == '1')
		));
	}

	// Check if we need dropdown navigation
	if (!empty($dacl_display['dacl_nav_style']) && $dacl_display['dacl_nav_style'] == 'dropdown') {
		wp_enqueue_script('deals-and-coupons-nav-dropdown-js');
	}

	// Check if we need click-to-reveal functionality
	if (!empty($dacl_display['dacl_click_to_reveal']) && $dacl_display['dacl_click_to_reveal'] == '1') {
		wp_enqueue_script('deals-and-coupons-ctr-js');

		// Get current URL based on context
		$current_url = '';
		if (is_singular()) {
			$current_url = get_permalink();
		} else {
			// For archive pages and other contexts, use the current URL
			global $wp;
			$current_url = home_url(add_query_arg(array(), $wp->request));
		}

		// Craft popup hyperlink with proper escaping
		$wpcctr_href = add_query_arg('wpcctr', '', esc_url(remove_query_arg('wpcctr', $current_url)));

		// Sanitize and validate click to reveal behavior
		$is_popup = (!empty($dacl_display['dacl_click_to_reveal_behavior']) &&
			$dacl_display['dacl_click_to_reveal_behavior'] === 'popup');

		// Add localized data for click-to-reveal script
		wp_localize_script('deals-and-coupons-ctr-js', 'dealsAndCouponsCTR', array(
			'wpcctrHref' => $wpcctr_href,
			'isPopup' => $is_popup
		));
	}

	// Always enqueue the main styles
	wp_enqueue_style('deals-and-coupons-styles');

	// Add inline CSS overrides if needed
	if (function_exists('dacl_css_overrides')) {
		$css_overrides = dacl_css_overrides(false);
		if (!empty($css_overrides)) {
			// Properly escape CSS content for inline styles
			wp_add_inline_style('deals-and-coupons-styles', esc_html(wp_strip_all_tags($css_overrides)));
		}
	}

	// Add custom CSS from plugin options
	if (!empty($dacl_extra['dacl_custom_css'])) {
		wp_add_inline_style('deals-and-coupons-styles', esc_html(wp_strip_all_tags($dacl_extra['dacl_custom_css'])));
	}
}
add_action('wp_enqueue_scripts', 'dacl_enqueue_scripts');

// Legacy function for backward compatibility
function dacl_output_clipboard_js() {
	// This function is kept for backward compatibility
	// The actual functionality is now handled by wp_enqueue_scripts
}
add_action('dacl_clipboard_js', 'dacl_output_clipboard_js', 1);

//Nav Dropdown JS - Legacy function
function dacl_output_nav_dropdown_js() {
	// This function is kept for backward compatibility
	// The actual functionality is now handled by wp_enqueue_scripts
}

//Click to Reveal Action
function dacl_output_ctr_popup() {
	$dacl_extra = get_option('dacl_extra_options');
	$dacl_display = get_option('dacl_display_options');

	if (!empty($_GET['wpcctr'])) {
		// Skip nonce verification as this is a non-critical frontend feature
		// that doesn't modify data and only displays information
		// @phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Sanitize and validate the post ID
		$post_id = absint($_GET['wpcctr']);

		// Verify this is a valid coupon post
		if (!$post_id || get_post_type($post_id) !== 'dacl_coupon') {
			return;
		}

		// Get popup coupon details with proper sanitization
		$dacl_ctr_img = get_the_post_thumbnail_url($post_id, 'full');
		$dacl_ctr_title = get_the_title($post_id);
		$dacl_ctr_discount_percent = get_post_meta($post_id, "dacl_discount_percent", true);
		$dacl_ctr_discount_code = get_post_meta($post_id, "dacl_discount_code", true);
		$dacl_ctr_discount_url = get_post_meta($post_id, 'dacl_discount_url', true);

		// Validate clipboard class
		$dacl_ctr_copy = (!empty($dacl_extra['dacl_clipboard_js']) &&
			$dacl_extra['dacl_clipboard_js'] === "1") ? "deals-and-coupons-copy" : "";

		// Print popup wrapper with proper escaping
?>
		<div id="deals-and-coupons-ctr-popup-wrapper">
			<div style="display: table; height: 100%; width: 100%;">
				<div style="display: table-cell; vertical-align: middle;">
					<div id="deals-and-coupons-ctr-popup">
						<div id="deals-and-coupons-ctr-popup-close">x</div>

						<?php if (!empty($dacl_ctr_img)) : ?>
							<?php // @phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- Using direct img tag as we're working with a URL, not an attachment ID 
							?>
							<img id="deals-and-coupons-ctr-img" src="<?php echo esc_url($dacl_ctr_img); ?>" alt="<?php echo esc_attr($dacl_ctr_title); ?>" />
						<?php endif; ?>

						<?php if (!empty($dacl_ctr_title)) : ?>
							<h2 id="deals-and-coupons-ctr-title"><?php echo esc_html($dacl_ctr_title); ?></h2>
						<?php endif; ?>

						<?php if (!empty($dacl_ctr_discount_percent)) : ?>
							<div id="deals-and-coupons-ctr-discount-percent"><?php echo esc_html($dacl_ctr_discount_percent); ?></div>
						<?php endif; ?>

						<?php if (!empty($dacl_ctr_discount_url)) : ?>
							<div id="deals-and-coupons-ctr-discount-url">
								<?php echo esc_html__('Copy and paste this code at', 'deals-and-coupons-lite'); ?><br />
								<a href="<?php echo esc_url($dacl_ctr_discount_url); ?>"><?php echo esc_html($dacl_ctr_discount_url); ?></a>
							</div>
						<?php endif; ?>

						<?php if (!empty($dacl_ctr_discount_code)) : ?>
							<div id="deals-and-coupons-ctr-discount-code"
								<?php if (!empty($dacl_ctr_copy)) : ?>
								class="<?php echo esc_attr($dacl_ctr_copy); ?>"
								data-clipboard-text="<?php echo esc_attr($dacl_ctr_discount_code); ?>"
								<?php endif; ?>>
								<div><?php echo esc_html($dacl_ctr_discount_code); ?></div>
								<?php if (!empty($dacl_extra['dacl_clipboard_js']) && $dacl_extra['dacl_clipboard_js'] === "1") : ?>
									<span class="deals-and-coupons-copy-text"><?php echo esc_html__('Copy', 'deals-and-coupons-lite'); ?></span>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
<?php
	}
}

//Tag Subnav
if (!empty($dacl_display_options['dacl_enable_subnav']) && $dacl_display_options['dacl_enable_subnav'] == "1") {
	add_action('init', 'dacl_tag_subnav_rewrite');
}
function dacl_tag_subnav_rewrite() {

	global $dacl_display_options;

	$archive_slug = 'dacl_coupons';
	$type_slug = 'dacl_all-coupons';
	if (!empty($dacl_display_options['dacl_archive_slug'])) {
		$archive_slug = $dacl_display_options['dacl_archive_slug'];
	}
	if (!empty($dacl_display_options['dacl_type_slug'])) {
		$type_slug = $dacl_display_options['dacl_type_slug'];
	} else {
		$type_slug = 'all-' . $archive_slug;
	}

	//Rewrite Tag URL
	add_rewrite_rule('^' . $type_slug . '/([^/]*)/([^/]*)/?$', 'index.php?coupon-type=$matches[1]&coupon-tag=$matches[2]', 'top');

	//Rewrite Tag URL w/ Pagination
	add_rewrite_rule('^' . $type_slug . '/([^/]*)/([^/]*)/page/([0-9]+)?$', 'index.php?coupon-type=$matches[1]&coupon-tag=$matches[2]&paged=$matches[3]', 'top');
}

//related coupons
function dacl_related_coupons($id) {

	global $dacl_display_options;
	$dacl_extra = get_option('dacl_extra_options');

	$var = "";

	// Get coupon type and tag terms for the current coupon
	$coupon_type_terms = wp_get_object_terms($id, 'dacl_coupon_type', array('fields' => 'ids'));
	$coupon_tag_terms = wp_get_object_terms($id, 'dacl_coupon_tag', array('fields' => 'ids'));

	$layout = !empty($dacl_display_options['dacl_related_coupons_layout']) ? $dacl_display_options['dacl_related_coupons_layout'] : "3";

	//query arguments
	// Note: Using post__not_in can be inefficient on large sites.
	// Since we're only excluding a single post and limiting results,
	// the performance impact should be minimal.

	// Note: Using meta_query can be slow on large databases.
	// We're limiting the number of posts returned to minimize the impact.
	// For sites with many coupons, consider implementing a custom database table
	// or using a more efficient query method.
	$args = array(
		'post_type' => 'dacl_coupon',
		'post_status' => 'publish',
		'posts_per_page' => is_numeric($layout) ? min((int)$layout, 6) : 3, // Limit to max 6 related posts
		'orderby' => 'rand',
		'post__not_in' => array($id),
	);

	// Add taxonomy query if we have terms
	$tax_query = array();

	if (!empty($coupon_type_terms)) {
		$tax_query[] = array(
			'taxonomy' => 'dacl_coupon_type',
			'field' => 'term_id',
			'terms' => $coupon_type_terms,
		);
	}

	if (!empty($coupon_tag_terms)) {
		$tax_query[] = array(
			'taxonomy' => 'dacl_coupon_tag',
			'field' => 'term_id',
			'terms' => $coupon_tag_terms,
		);
	}

	// Only add tax_query if we have terms
	if (!empty($tax_query)) {
		if (count($tax_query) > 1) {
			$tax_query['relation'] = 'OR';
		}
		$args['tax_query'] = $tax_query;
	}

	// Add meta query to exclude expired coupons and those marked for exclusion
	$args['meta_query'] = array(
		'relation' => 'AND',
		array(
			'key' => 'dacl_exclude_rotation',
			'type' => 'BINARY',
			'compare' => 'NOT EXISTS'
		)
	);

	// Check for expired coupons
	// Since dates are stored in m/d/Y format in the database, we'll use a different approach
	// We'll get all coupons and then filter out expired ones in PHP

	// Get all coupons first without date filtering
	$all_coupons_args = $args;
	$all_coupons_query = new WP_Query($all_coupons_args);
	$valid_coupon_ids = array();

	if ($all_coupons_query->have_posts()) {
		$today = strtotime(current_time('m/d/Y'));

		while ($all_coupons_query->have_posts()) {
			$all_coupons_query->the_post();
			$coupon_id = get_the_ID();
			$expiration_date = get_post_meta($coupon_id, 'dacl_expiration', true);

			// Include coupon if:
			// 1. It has no expiration date, OR
			// 2. It has an expiration date that is in the future (not expired)
			if (empty($expiration_date)) {
				// No expiration date, include it
				$valid_coupon_ids[] = $coupon_id;
			} else {
				// Check if the date is valid and not expired
				$expiration_timestamp = strtotime($expiration_date);
				if ($expiration_timestamp !== false && $expiration_timestamp >= $today) {
					// Valid future date, include it
					$valid_coupon_ids[] = $coupon_id;
				}
			}
		}

		wp_reset_postdata();
	}

	// If we have valid coupons, add them to the query
	if (!empty($valid_coupon_ids)) {
		$args['post__in'] = $valid_coupon_ids;
	} else {
		// No valid coupons found, return empty result
		$args['post__in'] = array(0); // This will return no results
	}

	// We don't need to remove any meta query since we didn't add one for expiration

	//the query
	$relatedPosts = new WP_Query($args);

	$j = 0;

	//loop through query
	if ($relatedPosts->have_posts()) {

		$container_class = "";
		$coupon_class = "";

		if (is_numeric($layout)) {
			$container_class = ' deals-and-coupons-' . esc_attr($layout) . '-col';
		} else {
			$coupon_class = ' ' . esc_attr(str_replace('-', ' ', $layout));
		}

		$var .= "<div class='deals-and-coupons deals-and-coupons-related-coupons'>";

		$heading_tag = !empty($dacl_display_options['dacl_related_coupons_heading_tag']) ? sanitize_key($dacl_display_options['dacl_related_coupons_heading_tag']) : 'h2';

		$var .= "<" . $heading_tag . ">" . esc_html(!empty($dacl_display_options['dacl_related_coupons_text']) ? $dacl_display_options['dacl_related_coupons_text'] : __('Related Coupons', 'deals-and-coupons-lite')) . "</" . $heading_tag . ">";

		$var .= "<div class='deals-and-coupons-coupons-container" . esc_attr($container_class) . "'>";

		while ($relatedPosts->have_posts()) {
			$relatedPosts->the_post();
			$j++;

			$var .= "<div class='deals-and-coupons-coupon" . esc_attr($coupon_class) . "'>";
			$var .= dacl_coupon_panel(get_the_ID());
			$var .= "</div>";
		}

		$var .= "</div>";
		$var .= "</div>";
	} else {
		//no posts found
	}

	//restore original post data
	wp_reset_postdata();

	if (!empty($dacl_extra['dacl_clipboard_js']) && $dacl_extra['dacl_clipboard_js'] == "1") {
		if (has_action('wp_footer', 'dacl_output_clipboard_js') == false) {
			add_action('wp_footer', 'dacl_output_clipboard_js');
		}
	}

	if (!empty($dacl_display_options['dacl_click_to_reveal'])) {
		if (has_action('wp_footer', 'dacl_output_ctr_popup') == false) {
			add_action('wp_footer', 'dacl_output_ctr_popup');
		}
	}

	// Make sure CSS is enqueued when related coupons are displayed
	if (!function_exists('dacl_ensure_styles_loaded')) {
		function dacl_ensure_styles_loaded() {
			if (!wp_style_is('deals-and-coupons-styles', 'enqueued')) {
				wp_enqueue_style('deals-and-coupons-styles');
			}
			// Trigger the CSS overrides to be generated
			global $dacl_css_overrides;
			$dacl_css_overrides = true;
		}
	}
	dacl_ensure_styles_loaded();

	return $var;
}

function dacl_after_post_content($content) {
	global $dacl_display_options;

	if (!in_the_loop() || empty($dacl_display_options['dacl_display_related_coupons']) || !empty($dacl_display_options['dacl_related_coupons_hook']) || (is_singular('dacl_coupon') && !empty($dacl_display_options['dacl_enable_single']) && $dacl_display_options['dacl_enable_single'] == "1")) {
		return $content;
	}

	if (!empty($dacl_display_options['dacl_related_coupons_locations']) && is_array($dacl_display_options['dacl_related_coupons_locations'])) {
		if (in_array(get_post_type(), $dacl_display_options['dacl_related_coupons_locations'], true)) {
			$related_coupons = dacl_related_coupons(get_the_ID());
			$content .= wp_kses_post($related_coupons);
		}
	}

	return $content;
}
add_filter("the_content", "dacl_after_post_content");

function dacl_related_posts_action() {
	global $dacl_display_options;
	if (in_the_loop() && !empty($dacl_display_options['dacl_display_related_coupons']) && (!is_singular('dacl_coupon') || empty($dacl_display_options['dacl_enable_single']))) {
		if (!empty($dacl_display_options['dacl_related_coupons_locations']) && is_array($dacl_display_options['dacl_related_coupons_locations'])) {
			if (in_array(get_post_type(), $dacl_display_options['dacl_related_coupons_locations'], true)) {
				echo wp_kses_post(dacl_related_coupons(get_the_ID()));
			}
		}
	}
}
if (!empty($dacl_display_options['dacl_related_coupons_hook'])) {
	$priority = 10;
	if (!empty($dacl_display_options['dacl_related_coupons_hook_priority']) && is_numeric($dacl_display_options['dacl_related_coupons_hook_priority'])) {
		$priority = $dacl_display_options['dacl_related_coupons_hook_priority'];
	}
	add_action($dacl_display_options['dacl_related_coupons_hook'], "dacl_related_posts_action", $priority);
}



//css overrides
function dacl_css_overrides($include_style_tags = true) {

	global $dacl_css_overrides;
	static $css_content = null;

	if (!$dacl_css_overrides || $css_content === null) {

		$dacl_css_overrides = true;

		$dacl_display = get_option('dacl_display_options');
		$dacl_styles = get_option('dacl_styling_options');

		$css_content = "
			/*Archive Template Only*/
			#deals-and-coupons-outer-wrapper {
				padding-left: " . esc_attr(!empty($dacl_display['dacl_wrapper_padding']) ? $dacl_display['dacl_wrapper_padding'] : "") . ";
				padding-right: " . esc_attr(!empty($dacl_display['dacl_wrapper_padding']) ? $dacl_display['dacl_wrapper_padding'] : "") . ";
			}
			#deals-and-coupons-wrapper {
				max-width: " . esc_attr(!empty($dacl_display['dacl_wrapper_width']) ? $dacl_display['dacl_wrapper_width'] : "") . ";
			}
			/*Navigation*/
			body .deals-and-coupons-nav a, body .deals-and-coupons-nav a:visited {
				font-size: " . esc_attr(!empty($dacl_styles['dacl_nav_font_size']) ? $dacl_styles['dacl_nav_font_size'] : "") . ";
				color: " . esc_attr(!empty($dacl_styles['dacl_link_accent_color']) ? $dacl_styles['dacl_link_accent_color'] : "") . ";
			}
			body .deals-and-coupons-nav a:hover, body .deals-and-coupons-nav a.deals-and-coupons-nav-selected {
				color: " . esc_attr(!empty($dacl_styles['dacl_link_accent_color_hover']) ? $dacl_styles['dacl_link_accent_color_hover'] : "") . ";
			}
			body .deals-and-coupons-subnav a, body .deals-and-coupons-subnav a:visited {
				font-size: " . esc_attr(!empty($dacl_styles['dacl_subnav_font_size']) ? $dacl_styles['dacl_subnav_font_size'] : "") . ";
				color: " . esc_attr(!empty($dacl_styles['dacl_link_accent_color']) ? $dacl_styles['dacl_link_accent_color'] : "") . ";
				border-color: " . esc_attr(!empty($dacl_styles['dacl_link_accent_color']) ? $dacl_styles['dacl_link_accent_color'] : "") . ";
			}
			body .deals-and-coupons-subnav a.active, body .deals-and-coupons-subnav a:hover {
				color: " . esc_attr(!empty($dacl_styles['dacl_link_accent_color_hover']) ? $dacl_styles['dacl_link_accent_color_hover'] : "") . ";
				border-color: " . esc_attr(!empty($dacl_styles['dacl_link_accent_color_hover']) ? $dacl_styles['dacl_link_accent_color_hover'] : "") . ";
			}
			/*Coupon Panel*/
			.deals-and-coupons-coupon-panel {
				background: " . esc_attr(!empty($dacl_styles['dacl_panel_background_color']) ? $dacl_styles['dacl_panel_background_color'] : "") . ";
			}
			.deals-and-coupons-coupon-panel .deals-and-coupons-discount-percent {
				background: " . esc_attr(!empty($dacl_styles['dacl_discount_background_color']) ? $dacl_styles['dacl_discount_background_color'] : "") . ";
				color: " . esc_attr(!empty($dacl_styles['dacl_discount_text_color']) ? $dacl_styles['dacl_discount_text_color'] : "") . ";
				font-size: " . esc_attr(!empty($dacl_styles['dacl_discount_font_size']) ? $dacl_styles['dacl_discount_font_size'] : "") . ";
			}
			.deals-and-coupons-coupon-panel .deals-and-coupons-expiration {
				color: " . esc_attr(!empty($dacl_styles['dacl_expiration_text_color']) ? $dacl_styles['dacl_expiration_text_color'] : "") . ";
				font-size: " . esc_attr(!empty($dacl_styles['dacl_expiration_font_size']) ? $dacl_styles['dacl_expiration_font_size'] : "") . ";
			}
			 .deals-and-coupons-coupon-panel .deals-and-coupons-discount-code {
				background: " . esc_attr(!empty($dacl_styles['dacl_discount_code_background_color']) ? $dacl_styles['dacl_discount_code_background_color'] : "") . ";
				color: " . esc_attr(!empty($dacl_styles['dacl_discount_code_text_color']) ? $dacl_styles['dacl_discount_code_text_color'] : "") . ";
				font-size: " . esc_attr(!empty($dacl_styles['dacl_discount_code_font_size']) ? $dacl_styles['dacl_discount_code_font_size'] : "") . ";
			}
			.deals-and-coupons-coupon-panel .deals-and-coupons-discount-code span {
				color: " . esc_attr(!empty($dacl_styles['dacl_discount_code_text_color']) ? $dacl_styles['dacl_discount_code_text_color'] : "") . ";
				border-color: " . esc_attr(!empty($dacl_styles['dacl_discount_code_text_color']) ? $dacl_styles['dacl_discount_code_text_color'] : "") . ";
			}
			.deals-and-coupons-coupon-panel .deals-and-coupons-ctr:before {
				border-color: " . esc_attr(!empty($dacl_styles['dacl_ctr_folded_corner_color']) ? $dacl_styles['dacl_ctr_folded_corner_color'] . " transparent" : "") . ";
			}
			.deals-and-coupons-coupon-panel .deals-and-coupons-ctr:after {
				border-color: " . esc_attr(!empty($dacl_styles['dacl_discount_code_background_color']) ? $dacl_styles['dacl_discount_code_background_color'] : "") . ";
			}
			.deals-and-coupons-coupon-panel .coupon-separator {
				border-color: " . esc_attr(!empty($dacl_styles['dacl_separator_color']) ? $dacl_styles['dacl_separator_color'] : "") . ";
			}
			.deals-and-coupons-coupon-panel .coupon-title {
				font-size: " . esc_attr(!empty($dacl_styles['dacl_title_font_size']) ? $dacl_styles['dacl_title_font_size'] : "") . ";
			}
			.deals-and-coupons-coupon-panel .coupon-title, .deals-and-coupons-coupon-panel .coupon-title:visited, .deals-and-coupons-coupon-panel .coupon-link, .deals-and-coupons-coupon-panel .coupon-link:visited, .deals-and-coupons-banner .coupon-title {
				color: " . esc_attr(!empty($dacl_styles['dacl_link_accent_color']) ? $dacl_styles['dacl_link_accent_color'] : "") . ";
			}
			.deals-and-coupons-coupon-panel .coupon-title:hover, .deals-and-coupons-coupon-panel .coupon-link:hover {
				color: " . esc_attr(!empty($dacl_styles['dacl_link_accent_color_hover']) ? $dacl_styles['dacl_link_accent_color_hover'] : "") . ";
			}
			.deals-and-coupons-coupon-panel .coupon-description {
				font-size: " . esc_attr(!empty($dacl_styles['dacl_description_font_size']) ? $dacl_styles['dacl_description_font_size'] : "") . ";
				line-height: " . esc_attr(!empty($dacl_styles['dacl_description_line_height']) ? $dacl_styles['dacl_description_line_height'] : "") . ";
				min-height: " . esc_attr(!empty($dacl_display['dacl_description_height']) ? $dacl_display['dacl_description_height'] : "") . ";
				max-height: " . esc_attr(!empty($dacl_display['dacl_description_height']) ? $dacl_display['dacl_description_height'] : "") . ";
			}
			.deals-and-coupons-coupon-panel .coupon-type, .deals-and-coupons-coupon-panel .coupon-type:hover, .deals-and-coupons-coupon-panel .coupon-type:visited {
				color: " . esc_attr(!empty($dacl_styles['dacl_type_text_color']) ? $dacl_styles['dacl_type_text_color'] : "") . ";
				font-size: " . esc_attr(!empty($dacl_styles['dacl_type_font_size']) ? $dacl_styles['dacl_type_font_size'] : "") . ";
			}
			.deals-and-coupons-coupon-panel a.coupon-link {
				font-size: " . esc_attr(!empty($dacl_styles['dacl_cta_font_size']) ? $dacl_styles['dacl_cta_font_size'] : "") . ";
			}
			/*Pagination*/
			body .deals-and-coupons-navigation .page-numbers, body .deals-and-coupons-navigation .page-numbers:visited {
				background-color: " . esc_attr(!empty($dacl_styles['dacl_link_accent_color']) ? $dacl_styles['dacl_link_accent_color'] : "") . ";
			}
			body .deals-and-coupons-navigation .page-numbers.current, body .deals-and-coupons-navigation .page-numbers:hover {
				background-color: " . esc_attr(!empty($dacl_styles['dacl_link_accent_color_hover']) ? $dacl_styles['dacl_link_accent_color_hover'] : "") . ";
			}
			/*Click to Reveal Popup*/
			#deals-and-coupons-ctr-popup #deals-and-coupons-ctr-discount-code span {
				background: " . esc_attr(!empty($dacl_styles['dacl_discount_code_background_color']) ? $dacl_styles['dacl_discount_code_background_color'] : "") . ";
				color: " . esc_attr(!empty($dacl_styles['dacl_discount_code_text_color']) ? $dacl_styles['dacl_discount_code_text_color'] : "") . ";
			}
			#deals-and-coupons-ctr-popup #deals-and-coupons-ctr-discount-url a {
				color: " . esc_attr(!empty($dacl_styles['dacl_link_accent_color']) ? $dacl_styles['dacl_link_accent_color'] : "") . ";
			}
			#deals-and-coupons-ctr-popup #deals-and-coupons-ctr-discount-url a:hover {
				color: " . esc_attr(!empty($dacl_styles['dacl_link_accent_color_hover']) ? $dacl_styles['dacl_link_accent_color_hover'] : "") . ";
			}
			/*Buttons*/
			body a.deals-and-coupons-button {
				background: " . esc_attr(!empty($dacl_styles['dacl_link_accent_color']) ? $dacl_styles['dacl_link_accent_color'] : "") . ";
				border-color: " . esc_attr(!empty($dacl_styles['dacl_link_accent_color']) ? $dacl_styles['dacl_link_accent_color'] : "") . ";
				font-size: " . esc_attr(!empty($dacl_styles['dacl_button_font_size']) ? $dacl_styles['dacl_button_font_size'] : "") . ";
			}
			body a.deals-and-coupons-button:hover {
				color: " . esc_attr(!empty($dacl_styles['dacl_link_accent_color_hover']) ? $dacl_styles['dacl_link_accent_color_hover'] : "") . ";
				border-color: " . esc_attr(!empty($dacl_styles['dacl_link_accent_color_hover']) ? $dacl_styles['dacl_link_accent_color_hover'] : "") . ";
			}
			@media(min-width: 794px) {
				.deals-and-coupons-coupon.list.compact .deals-and-coupons-coupon-panel .coupon-link, .deals-and-coupons-coupon.list.minimal .deals-and-coupons-coupon-panel .coupon-link {
					font-size: " . esc_attr(!empty($dacl_styles['dacl_description_font_size']) ? $dacl_styles['dacl_description_font_size'] : "") . ";
					line-height: " . esc_attr(!empty($dacl_styles['dacl_description_line_height']) ? $dacl_styles['dacl_description_line_height'] : "") . ";
				}
			}
		";
	}

	// Register the dynamic CSS function only once
	if (!function_exists('dacl_enqueue_dynamic_css') && $include_style_tags) {
		function dacl_enqueue_dynamic_css() {
			global $dacl_css_overrides;
			static $css_added = false;

			if ($dacl_css_overrides && !$css_added) {
				$css_added = true;
				$css_content = dacl_css_overrides(false);
				wp_add_inline_style('deals-and-coupons-styles', esc_html(wp_strip_all_tags($css_content)));
			}
		}
		add_action('wp_enqueue_scripts', 'dacl_enqueue_dynamic_css', 20);
	}

	// If we're just getting the CSS content, return it
	if (!$include_style_tags) {
		return $css_content;
	}

	// Otherwise return empty string
	return '';
}

//coupon panel
function dacl_coupon_panel($id, $params = array()) {

	$dacl_display = get_option('dacl_display_options');
	$dacl_extra = get_option('dacl_extra_options');

	$dacl_call_to_action = get_post_meta($id, 'dacl_call_to_action', true);
	if (!empty($params['class'])) {
		$dacl_css_class = $params['class'];
	} else {
		$dacl_css_class = get_post_meta($id, 'dacl_css_class', true);
	}
	$dacl_discount_url = get_post_meta($id, 'dacl_discount_url', true);
	$dacl_ctr_link = "";

	if (!empty($dacl_display['dacl_display_expiration'])) {
		$dacl_expiration = get_post_meta($id, "dacl_expiration", true);
		if (!empty($dacl_expiration)) {
			$today = gmdate('m/d/Y');
			if (strtotime($dacl_expiration) > strtotime($today)) {
				$expired_text = __('Expires', 'deals-and-coupons-lite');
			} else {
				$expired_text = __('Expired', 'deals-and-coupons-lite');
				$dacl_css_class .= ' deals-and-coupons-expired';
			}
			$date_format = get_option('date_format');
			$expiration_format = !empty($date_format) ? $date_format : "F j, Y";
			$expration_html = "<div class='deals-and-coupons-expiration'><span class='deals-and-coupons-clock'><svg role='img' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path fill='currentColor' d='M256,8C119,8,8,119,8,256S119,504,256,504,504,393,504,256,393,8,256,8Zm92.49,313h0l-20,25a16,16,0,0,1-22.49,2.5h0l-67-49.72a40,40,0,0,1-15-31.23V112a16,16,0,0,1,16-16h32a16,16,0,0,1,16,16V256l58,42.5A16,16,0,0,1,348.49,321Z'></path></svg></span> " . $expired_text . " " . date_i18n($expiration_format, strtotime($dacl_expiration)) . "</div>";
		}
	}

	$var = "<div class='deals-and-coupons-coupon-panel" . (!empty($params['template']) ? ' ' . $params['template'] : '') . (!empty($dacl_css_class) ? ' ' . $dacl_css_class : '') . "'>";

	$var .= "<div class='coupon-panel-image-wrapper' style='position: relative; overflow: hidden;'>";

	//coupon discount
	$dacl_discount_percent = get_post_meta($id, "dacl_discount_percent", true);
	if (!empty($dacl_discount_percent)) {
		$var .= "<div class='deals-and-coupons-discount-percent'>" . $dacl_discount_percent . "</div>";
	}

	//coupon code
	if (!empty($dacl_display['dacl_display_discount_codes'])) {

		$dacl_discount_code = get_post_meta($id, "dacl_discount_code", true);

		if (!empty($dacl_extra['dacl_clipboard_js']) && $dacl_extra['dacl_clipboard_js'] == "1" && !empty($dacl_discount_code)) {
			$dacl_copy = "deals-and-coupons-copy";
		} else {
			$dacl_copy = "";
		}

		if (!empty($dacl_discount_code)) {

			//click to reveal button
			if (!empty($dacl_display['dacl_click_to_reveal']) && $dacl_display['dacl_click_to_reveal'] == "1" && !empty($dacl_discount_url)) {

				$dacl_ctr_link = "deals-and-coupons-ctr-link";
				if (!empty($dacl_display['dacl_click_to_reveal_text'])) {
					$dacl_ctr_text = $dacl_display['dacl_click_to_reveal_text'];
				} else {
					$dacl_ctr_text = __('Click to Reveal', 'deals-and-coupons-lite');
				}

				$var .= "<div class='deals-and-coupons-ctr-wrapper'>";
				$var .= "<div class='deals-and-coupons-discount-code deals-and-coupons-ctr " . esc_attr($dacl_copy) . "' rel='" . esc_attr($id) . "' value='" . esc_attr($dacl_discount_url) . "' data-clipboard-text='" . esc_attr($dacl_discount_code) . "'><span class='deals-and-coupons-tag'><svg role='img' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path fill='currentColor' d='M0 252.118V48C0 21.49 21.49 0 48 0h204.118a48 48 0 0 1 33.941 14.059l211.882 211.882c18.745 18.745 18.745 49.137 0 67.882L293.823 497.941c-18.745 18.745-49.137 18.745-67.882 0L14.059 286.059A48 48 0 0 1 0 252.118zM112 64c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48-21.49-48-48-48z'></path></svg></span> " . esc_html($dacl_ctr_text) . "</div>";
				$var .= "</div>";
			} else {
				$var .= '<div class="deals-and-coupons-discount-code ' . esc_attr($dacl_copy) . '" data-clipboard-text="' . esc_attr($dacl_discount_code) . '">';
				$var .= "<span class='deals-and-coupons-tag'><svg role='img' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path fill='currentColor' d='M0 252.118V48C0 21.49 21.49 0 48 0h204.118a48 48 0 0 1 33.941 14.059l211.882 211.882c18.745 18.745 18.745 49.137 0 67.882L293.823 497.941c-18.745 18.745-49.137 18.745-67.882 0L14.059 286.059A48 48 0 0 1 0 252.118zM112 64c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48-21.49-48-48-48z'></path></svg></span> " . esc_html($dacl_discount_code);
				if (!empty($dacl_extra['dacl_clipboard_js']) && $dacl_extra['dacl_clipboard_js'] == "1") {
					$var .= "<span class='deals-and-coupons-copy-text'>" . esc_html__('Copy', 'deals-and-coupons-lite') . "</span>";
				}
				$var .= '</div>';
			}
		} elseif (empty($dacl_display['dacl_hide_no_code_needed'])) {
			$var .= '<div class="deals-and-coupons-discount-code ' . esc_attr($dacl_copy) . '" data-clipboard-text="' . esc_attr($dacl_discount_code) . '">';
			if (!empty($dacl_display['dacl_no_code_needed_text'])) {
				$no_code_needed = $dacl_display['dacl_no_code_needed_text'];
			} else {
				$no_code_needed = __('No Code Needed', 'deals-and-coupons-lite');
			}
			$var .= "<span class='deals-and-coupons-tag'><svg role='img' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path fill='currentColor' d='M0 252.118V48C0 21.49 21.49 0 48 0h204.118a48 48 0 0 1 33.941 14.059l211.882 211.882c18.745 18.745 18.745 49.137 0 67.882L293.823 497.941c-18.745 18.745-49.137 18.745-67.882 0L14.059 286.059A48 48 0 0 1 0 252.118zM112 64c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48-21.49-48-48-48z'></path></svg></span> " . esc_html($no_code_needed);
			$var .= "</div>";
		}
	}

	//setup base link attributes
	$coupon_permalink = get_the_permalink();
	$coupon_permalink_target = "_self";
	$coupon_permalink_rel = "";

	//get direct link status
	$dacl_direct_link = isset($params['direct_link']) ? filter_var($params['direct_link'], FILTER_VALIDATE_BOOLEAN) : get_post_meta($id, 'dacl_direct_link', true);

	//split links flag
	$dacl_split_links = false;

	//setup direct link attributes
	if (!empty($dacl_discount_url) && (!empty($dacl_direct_link) || !empty($dacl_display['dacl_link_behavior']))) {

		//forced permalink
		$direct_permalink = $dacl_discount_url;

		//target
		$direct_permalink_target = !empty($dacl_extra['dacl_direct_links_target']) ? $dacl_extra['dacl_direct_links_target'] : "_blank";

		//nofollow
		$dacl_nofollow = isset($params['nofollow']) ? filter_var($params['nofollow'], FILTER_VALIDATE_BOOLEAN) : (!empty($dacl_extra['dacl_force_nofollow']) ? "1" : get_post_meta($id, 'dacl_nofollow', true));
		$direct_permalink_rel = !empty($dacl_nofollow) ? "nofollow" : "";

		//overwrite link with direct attributes
		if (!empty($dacl_direct_link) || (!empty($dacl_display['dacl_link_behavior']) && $dacl_display['dacl_link_behavior'] == 'direct')) {
			$coupon_permalink = $direct_permalink;
			$coupon_permalink_target = $direct_permalink_target;
			$coupon_permalink_rel = $direct_permalink_rel;
		}
		//set split links flag for later
		elseif (!empty($dacl_display['dacl_link_behavior']) && $dacl_display['dacl_link_behavior'] == 'split') {
			$dacl_split_links = true;
		}
	}

	//coupon featured image
	$var .= "<a href='" . ($coupon_permalink ? $coupon_permalink : "") . "' class='" . $dacl_ctr_link . "' target='" . $coupon_permalink_target . "' title='" . get_the_title() . "' rel='" . $coupon_permalink_rel . "'>";
	if (has_post_thumbnail()) {
		$var .= get_the_post_thumbnail($id, apply_filters('dacl_coupon_panel_image', 'full'));
	} else {
		// @phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- Using direct img tag for placeholder image as it's not an attachment
		$var .= "<img src='" . dacl_placeholder_image_url() . "' title='" . __('Sample Image', 'deals-and-coupons-lite') . "' alt='" . __('Sample Image', 'deals-and-coupons-lite') . "' />";
	}
	$var .= "</a>";
	$var .= "</div>";

	//coupon content
	$var .= "<div class='deals-and-coupons-coupon-content' style='padding: 0px 25px 10px 25px; overflow: hidden;'>";

	$var .= "<a href='" . ($coupon_permalink ? $coupon_permalink : "") . "' class='" . (!$dacl_split_links ? $dacl_ctr_link : '') . "' target='" . $coupon_permalink_target . "' title='" . get_the_title() . "' rel='" . $coupon_permalink_rel . "'>";
	$title_tag = !empty($dacl_display['dacl_title_tag']) ? $dacl_display['dacl_title_tag'] : 'h2';
	$var .= "<" . $title_tag . " class='coupon-title'>" . get_the_title() . "</" . $title_tag . ">";
	$var .= "</a>";

	$var .= "<div class='coupon-description'>";
	$dacl_discount_description = get_post_meta($id, "dacl_discount_description", true);
	if (!empty($dacl_discount_description)) {
		$var .= do_shortcode($dacl_discount_description);
	}
	$var .= "</div>";

	//expiration date
	if (!empty($expration_html)) {
		$var .= $expration_html;
	}

	//overwrite link attributes for cta link if split link flag is set
	if ($dacl_split_links) {
		$coupon_permalink = $direct_permalink;
		$coupon_permalink_target = $direct_permalink_target;
		$coupon_permalink_rel = $direct_permalink_rel;
	}

	if (!empty($dacl_call_to_action)) {
		$coupon_cta = $dacl_call_to_action;
	} elseif (!empty($dacl_display['dacl_call_to_action'])) {
		$coupon_cta = $dacl_display['dacl_call_to_action'];
	} else {
		$coupon_cta = __('Get This Deal', 'deals-and-coupons-lite');
	}

	if (!empty($params['button']) && $params['button'] != false) {
		$var .= "<a href='" . ($coupon_permalink ? $coupon_permalink : "") . "' title='' class='deals-and-coupons-button " . $dacl_ctr_link . "' target='" . $coupon_permalink_target . "' rel='" . $coupon_permalink_rel . "'>" . $coupon_cta . "</a>";
	} else {

		$var .= "<hr class='coupon-separator' />";

		$var .= "<div class='deals-and-coupons-type-cta-container'>";

		$var .= "<a href='" . ($coupon_permalink ? $coupon_permalink : "") . "' target='" . $coupon_permalink_target . "' rel='" . $coupon_permalink_rel . "' class='coupon-link " . $dacl_ctr_link . "'>" . $coupon_cta . " <span class='deals-and-coupons-arrow-right'><svg role='img' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 448 512'><path fill='currentColor' d='M190.5 66.9l22.2-22.2c9.4-9.4 24.6-9.4 33.9 0L441 239c9.4 9.4 9.4 24.6 0 33.9L246.6 467.3c-9.4 9.4-24.6 9.4-33.9 0l-22.2-22.2c-9.5-9.5-9.3-25 .4-34.3L311.4 296H24c-13.3 0-24-10.7-24-24v-32c0-13.3 10.7-24 24-24h287.4L190.9 101.2c-9.8-9.3-10-24.8-.4-34.3z'></path></svg></span></a>";

		if (empty($dacl_display['dacl_hide_coupon_type_tags'])) {
			$coupon_types = wp_get_post_terms($id, 'dacl_coupon_type');
			if (!empty($coupon_types[0])) {
				$var .= "<a href='" . get_term_link($coupon_types[0]) . "' title='" . $coupon_types[0]->name . "' class='dacl_coupon_type'>" . $coupon_types[0]->name . "</a>";
			}
		}

		$var .= "</div>";
	}

	$var .= "</div>";
	$var .= "</div>";

	return $var;
}

//return placeholder image url for coupon
function dacl_placeholder_image_url() {
	$display_options = get_option('dacl_display_options');

	// Debug: Return a direct URL to the image for testing
	$default_image = DACL_PLUGIN_URL . "assets/images/sample-image.png";

	return !empty($display_options['dacl_placeholder_image']) ? $display_options['dacl_placeholder_image'] : $default_image;
}

// Allow some tags in kses
function dacl_allow_some_tags_in_kses($tags, $context) {
	if ($context === 'post') {
		// Add SVG elements to allowed tags
		$tags['svg'] = array(
			'xmlns' => true,
			'viewbox' => true,
			'width' => true,
			'height' => true,
			'fill' => true,
			'stroke' => true,
			'stroke-width' => true,
			'role' => true,
			'aria-hidden' => true,
			'focusable' => true,
			'class' => true,
		);

		$tags['path'] = array(
			'd' => true,
			'fill' => true,
			'stroke' => true,
			'stroke-width' => true,
			'stroke-linecap' => true,
			'stroke-linejoin' => true,
		);

		// Allow rel attribute for div elements
		if (!isset($tags['div'])) {
			$tags['div'] = array();
		}
		$tags['div']['rel'] = true;
		$tags['div']['value'] = true;
		$tags['div']['data-clipboard-text'] = true;
	}

	return $tags;
}
add_filter('wp_kses_allowed_html', 'dacl_allow_some_tags_in_kses', 10, 2);

//check option update for custom inputs and modify result
function dacl_pre_update_option_extra($new_value, $old_value) {

	//restore plugin default options
	if (!empty($new_value['restore_defaults'])) {

		// Verify nonce
		if (!isset($_POST['dacl_import_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dacl_import_nonce'])), 'dacl_import_settings')) {
			add_settings_error('deals-and-coupons-lite', 'deals-and-coupons-restore-error', __('Security check failed. Please try again.', 'deals-and-coupons-lite'), 'error');
			return $old_value;
		}

		$default_display = dacl_default_display_options();
		if (!empty($default_display)) {
			update_option("dacl_display_options", $default_display);
		}

		$default_styling = dacl_default_styling_options();
		if (!empty($default_styling)) {
			update_option("dacl_styling_options", $default_styling);
		}

		$default_extra = dacl_default_extra_options();
		if (!empty($default_extra)) {
			update_option("dacl_extra_options", $default_extra);
		}

		add_settings_error('deals-and-coupons-lite', 'deals-and-coupons-restore-success', __('Successfully restored default options.', 'deals-and-coupons-lite'), 'success');
		return $old_value;
	}

	//export settings button was pressed
	if (!empty($new_value['export_settings'])) {

		// Verify nonce
		if (!isset($_POST['dacl_import_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dacl_import_nonce'])), 'dacl_import_settings')) {
			add_settings_error('deals-and-coupons-lite', 'deals-and-coupons-export-error', __('Security check failed. Please try again.', 'deals-and-coupons-lite'), 'error');
			return $old_value;
		}

		$settings = array();

		$settings['dacl_display_options'] = get_option('dacl_display_options');
		$settings['dacl_styling_options'] = get_option('dacl_styling_options');
		$settings['dacl_extra_options'] = get_option('dacl_extra_options');

		ignore_user_abort(true);

		//setup headers
		nocache_headers();
		header('Content-Type: application/json; charset=utf-8');
		header('Content-Disposition: attachment; filename=deals-and-coupons-settings-export-' . gmdate('Y-m-d') . '.json');
		header('Expires: 0');

		//print encoded file
		echo wp_json_encode($settings);
		exit;
	}


	if (!empty($new_value['import_settings']) || !empty($new_value['import_settings_file'])) {

		// Verify nonce
		if (!isset($_POST['dacl_import_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dacl_import_nonce'])), 'dacl_import_settings')) {
			add_settings_error('deals-and-coupons-lite', 'deals-and-coupons-import-error', __('Security check failed. Please try again.', 'deals-and-coupons-lite'), 'error');
			return $old_value;
		}

		// Check if file upload exists
		if (!isset($_FILES['dacl_import_settings_file']) || !isset($_FILES['dacl_import_settings_file']['tmp_name'])) {
			add_settings_error('deals-and-coupons-lite', 'deals-and-coupons-import-error', __('No import file given.', 'deals-and-coupons-lite'), 'error');
			return $old_value;
		}

		//get temporary file
		$import_file = sanitize_text_field($_FILES['dacl_import_settings_file']['tmp_name']);

		//cancel if there's no file
		if (empty($import_file)) {
			add_settings_error('deals-and-coupons-lite', 'deals-and-coupons-import-error', __('No import file given.', 'deals-and-coupons-lite'), 'error');
			return $old_value;
		}

		//check if uploaded file is valid
		if (!isset($_FILES['dacl_import_settings_file']['name'])) {
			add_settings_error('deals-and-coupons-lite', 'deals-and-coupons-import-error', __('Invalid file upload.', 'deals-and-coupons-lite'), 'error');
			return $old_value;
		}

		$file_name = sanitize_file_name($_FILES['dacl_import_settings_file']['name']);
		$file_parts = explode('.', $file_name);
		$extension = end($file_parts);
		if ($extension != 'json') {
			add_settings_error('deals-and-coupons-lite', 'deals-and-coupons-import-error', __('Please upload a valid .json file.', 'deals-and-coupons-lite'), 'error');
			return $old_value;
		}

		//unpack settings from file
		$settings = (array) json_decode(file_get_contents($import_file), true);

		if (isset($settings['dacl_display_options'])) {
			update_option('dacl_display_options', $settings['dacl_display_options']);
		}

		if (isset($settings['dacl_styling_options'])) {
			update_option('dacl_styling_options', $settings['dacl_styling_options']);
		}

		if (isset($settings['dacl_extra_options'])) {
			update_option('dacl_extra_options', $settings['dacl_extra_options']);
		}

		add_settings_error('deals-and-coupons-lite', 'deals-and-coupons-import-success', __('Successfully imported Deals and Coupons settings.', 'deals-and-coupons-lite'), 'success');

		return $old_value;
	}

	return $new_value;
}

//add filter to update options
function dacl_update_options() {

	//extra
	add_filter('pre_update_option_dacl_extra_options', 'dacl_pre_update_option_extra', 10, 2);
}
add_action('admin_init', 'dacl_update_options');
