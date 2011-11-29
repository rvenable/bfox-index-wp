<?php
/**
 * The template for displaying Bible Tool Archive pages.
 *
 * Usually used to create a Bible reader with access to all of the Bible Tools.
 *
 */

/* For BuddyPress, we actually reuse the activity directory theme files */
if (defined('BP_VERSION') && bp_is_active('activity')) {
	if (locate_template(array('activity/index.php'))) {
		load_bfox_template('activity/index-bfox_tool');
		exit;
	}
}

get_header(); ?>

<section id="primary">
	<div id="content" role="main">

	<?php if ( has_bfox_tool() ) : ?>

		<header class="page-header">
			<h1 class="page-title">
				<?php _e('Bible Tools'); ?>
			</h1>
		</header>

		<?php load_bfox_template('searchform-bfox_tool'); ?>

		<div class="<?php echo bfox_tool_class_for_form_update('searchform'); ?>" data-url="<?php echo bfox_tool_context_ajax_url('main'); ?>">
			<?php load_bfox_template('content-bfox_tool'); ?>
		</div>

	<?php else : ?>

		<article id="post-0" class="post no-results not-found">
			<header class="entry-header">
				<h1 class="entry-title"><?php _e( 'Bible Tool Not Found' ); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content">
				<p><?php _e( 'Apologies, but the Bible Tool was not found.' ); ?></p>
			</div><!-- .entry-content -->
		</article><!-- #post-0 -->

	<?php endif; ?>

	</div><!-- #content -->
</section><!-- #primary -->


<?php get_sidebar(); ?>
<?php get_footer(); ?>