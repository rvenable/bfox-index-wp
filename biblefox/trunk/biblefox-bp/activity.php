<?php

/*
 * Initialization Functions
 */

function bfox_activity_init() {
	global $bp, $biblefox;
	$biblefox->activity_refs = new BfoxRefsTable($bp->activity->table_name);
}
add_action('plugins_loaded', 'bfox_activity_init', 99);
add_action('admin_menu', 'bfox_activity_init', 99);

function bfox_activity_install() {
	global $biblefox;
	$biblefox->activity_refs->check_install(1);
}
add_action('admin_menu', 'bfox_activity_install');

/*
 * Management Functions
 */

function bfox_bp_activity_after_save($activity) {
	global $bp, $biblefox;

	// For blog posts, index the full post content and tags
	if ($activity->component == $bp->blogs->id && $activity->type == 'new_blog_post') {
		switch_to_blog($activity->item_id);

		// Get the blog post
		$post = get_post($activity->secondary_item_id);

		// Index the post content and tags
		$refs = BfoxBlog::content_to_refs($post->post_content);
		$tags = wp_get_post_tags($post->ID, array('fields' => 'names'));
		foreach ($tags as $tag) $refs->add_refs(BfoxBlog::tag_to_refs($tag));

		restore_current_blog();
	}
	// For forum posts, index the full post text and tags
	elseif ($activity->component == $bp->groups->id && ($activity->type == 'new_forum_post' || $activity->type == 'new_forum_topic')) {
		// Get the forum post
		if ($activity->type == 'new_forum_topic') list($post) = bp_forums_get_topic_posts(array('topic_id' => $activity->secondary_item_id, 'per_page' => 1));
		else $post = bp_forums_get_post($activity->secondary_item_id);

		// Index the post text
		$refs = BfoxBlog::content_to_refs($post->post_text);

		// Only index the tags for the first post in a topic
		if ($activity->type == 'new_forum_topic') {
			$tags = bb_get_topic_tags($post->topic_id, array('fields' => 'names'));
			foreach ($tags as $tag) $refs->add_refs(BfoxBlog::tag_to_refs($tag));
		}
	}
	// For all other activities, just index the activity content
	else {
		$refs = BfoxBlog::content_to_refs($activity->content);
	}

	$biblefox->activity_refs->save_item($activity->id, apply_filters('bfox_bp_activity_after_save_refs', $refs, $activity));
}
add_action('bp_activity_after_save', 'bfox_bp_activity_after_save');

function bfox_bp_activity_deleted_activities($activity_ids) {
	global $biblefox;
	$biblefox->activity_refs->delete_simple_items($activity_ids);
}
add_action('bp_activity_deleted_activities', 'bfox_bp_activity_deleted_activities');

/*
 * Activity Query Functions
 */

function bfox_bp_has_activities_filter($filters, $args) {
	if (!empty($args['bfox_refs'])) {
		$refs = new BfoxRefs($args['bfox_refs']);
		if ($refs->is_valid()) $filters['bfox_refs'] = $refs;
	}
	return $filters;
}
add_filter('bp_has_activities_filter', 'bfox_bp_has_activities_filter', 10, 2);

function bfox_bp_activity_get_from_sql($from_sql, $filter) {
	if (isset($filter['bfox_refs'])) {
		global $biblefox;
		$from_sql .= ', ' . $biblefox->activity_refs->from_sql();
	}
	return $from_sql;
}
add_filter('bp_activity_get_from_sql', 'bfox_bp_activity_get_from_sql', 10, 2);

function bfox_bp_activity_get_where_conditions($wheres, $filter) {
	if (isset($filter['bfox_refs'])) {
		global $biblefox;
		$wheres []= $biblefox->activity_refs->join_where('a.id');
		$wheres []= $biblefox->activity_refs->refs_where($filter['bfox_refs']);
	}
	return $wheres;
}
add_filter('bp_activity_get_where_conditions', 'bfox_bp_activity_get_where_conditions', 10, 2);

?>