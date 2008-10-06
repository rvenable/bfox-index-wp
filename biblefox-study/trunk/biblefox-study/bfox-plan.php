<?php

	function bfox_edit_plan_menu($plan = NULL)
	{
		if (!is_null($plan))
		{
			global $bfox_plan;
			$title = $plan->name;
			$summary = $plan->summary;
			$text = $bfox_plan->get_plan_text($plan->id);
			$section_size = $plan->frequency;
			$plan_id = $plan->id;
			$header = 'Edit Reading Plan';
			$action_str = 'Edit';
			$text_box_info = 'Edit the sections in your plan. <br/> Each line is a different section of your reading plan.';
		}
		else
		{
			$header = 'Create a Reading Plan';
			$action_str = 'Create';
			$text_box_info = 'Which passages of the bible would you like to read? <br/> Type the passages below: ';
		}
	?>
<h2><?php echo $header; ?></h2>
<form action="admin.php" method="get">
<input type="hidden" name="page" value="<?php echo BFOX_PLAN_SUBPAGE; ?>">
<input type="hidden" name="hidden_field" value="Y">
<input type="hidden" name="plan_id" value="<?php echo $plan_id; ?>">
Title: <input type="text" size="20" maxlength="128" name="plan_name" value="<?php echo $title; ?>"> <br/>
Summary: <input type="text" size="20" maxlength="128" name="plan_summary" value="<?php echo $summary; ?>"> <br/> <br/>
<?php echo $text_box_info; ?><br/>
<textarea rows="5" cols="40" wrap="physical" name="books"><?php echo $text; ?></textarea><br/> <br/>
How fast will you read this plan?<br/>
<input type="text" size="10" maxlength="40" name="num_chapters" value="<?php echo $section_size; ?>"> chapters per period <br/> <br/>
<input type="submit" value="<?php echo $action_str; ?> Plan" class="button" />
</form>
<?php
	}
	
	function bfox_get_sections_slow($text, $size)
	{
		// NOTE: This function was designed to replace the bfox_get_sections() function for creating a reading plan
		// It ended up being much slower however, since it is doing way too many DB queries
		// The DB queries are called by $this->get_size()
		$refs = new BibleRefs($text);
		$size_vector = new BibleRefVector(array(0, $size, 0));

		$sections = array();
		while ($refs->is_valid())
		{
			$shifted_refs = $refs->shift($size_vector);
			if ($shifted_refs->is_valid())
				$sections[] = $shifted_refs;
		}

		return $sections;
	}

	function bfox_echo_plan_list($plan_list, $skip_read = false)
	{
		$content = '';
		$unread_count = 0;
		foreach ($plan_list->original as $period_id => $original)
		{
			if ($skip_read && isset($plan_list->read[$period_id]) && !isset($plan_list->unread[$period_id])) continue;
			$index = $period_id + 1;
			$content .= "Reading $index: " . $original->get_link();
			if (isset($plan_list->unread[$period_id]))
			{
				if (isset($plan_list->read[$period_id]))
					$content .= " (You still need to read " . $plan_list->unread[$period_id]->get_link() . ")";
				else
					$content .= " (Unread)";
				$unread_count++;
				if ($unread_count == $max_unread) break;
			}
			else
			{
				if (isset($plan_list->read[$period_id]))
					$content .= " (Finished!)";
			}
			$content .= "<br/>";
		}
		return $content;
	}
	
	function bfox_blog_reading_plans($plans, $can_edit = false)
	{
		global $bfox_plan;
		$content = '';
		foreach ($plans as $plan)
		{
			$page = BFOX_PLAN_SUBPAGE;
			$admin_dir = get_option('home') . '/wp-admin';
			$delete_url = "$admin_dir/admin.php?page=$page&amp;action=delete&amp;plan_id=$plan->id";
			$view_url = "$admin_dir/admin.php?page=$page&amp;plan_id=$plan->id";
			$track_url = "$admin_dir/admin.php?page=$page&amp;action=track&amp;plan_id=$plan->id";
			$plan_list = $bfox_plan->get_plan_list($plan->id);

			$content .= "<h3>$plan->name</h3><p>";
			if (isset($plan->summary) && ('' != $plan->summary)) $content .= $plan->summary . '<br/>';
//			if ($can_edit) $content .= "(<a href=\"$delete_url\">remove</a>) ";
			if ($can_edit) $content .= "(<a href=\"$view_url\">edit</a>) ";
			if (!isset($plan_list->read) && !isset($plan_list->unread))
				$content .= "(<a href=\"$track_url\">track your progress</a>)";
			$content .= '</p>';
			$content .= bfox_echo_plan_list($plan_list);
			$content .= "<br/>";
		}
		return $content;
	}
	
	function bfox_get_reading_plan_status()
	{
		global $bfox_plan;
		$plans = $bfox_plan->get_plans();
		$content = '';
		if (0 < count($plans))
		{
			// HACK: hacky way to get a url
			global $bfox_history;
			$url = $bfox_history->get_special_url(false);

			$content .= 'This Bible Study Blog is currently following these reading plans:<br/>';
			foreach ($plans as $plan)
			{
				$content .= "<strong><a href=\"$url\">$plan->name</a></strong>";
				if (isset($plan->summary) && ('' != $plan->summary)) $content .= ': ' .$plan->summary;
				$content .= '<br/>';
			}
		}

		return $content;
	}

	function bfox_get_user_next_readings(PlanBlog $blog_plan, $plan_url, $blog_id)
	{
		global $bfox_plan_progress;
		$blog_plans = $blog_plan->get_plans();
		$content = '';
		if (0 < count($blog_plans))
		{
			foreach ($blog_plans as $plan)
			{
				$content .= "<strong>$plan->name</strong><br/>";
				$progress_plan_id = $bfox_plan_progress->get_plan_id($blog_id, $plan->id);
				if (isset($progress_plan_id))
				{
					$refs_object = $bfox_plan_progress->get_plan_refs($progress_plan_id);
					if (isset($refs_object->last_read))
						$content .= 'The furthest you have read is ' . $refs_object->read[$refs_object->last_read]->get_link() . '.<br/>';
					if (isset($refs_object->first_unread))
						$content .= 'You should read ' . $refs_object->unread[$refs_object->first_unread]->get_link() . ' next.<br/>';
				}
				else
				{
					$track_url = $plan_url . 'action=track&amp;';
					$content .= "Not tracked. You can choose to <a href=\"$track_url\">follow this reading plan</a>.<br/>";
				}
				$content .= '<br/>';
			}
		}
		else
		{
			$content .= "This Bible Study Blog currently has no reading plans.<br/>";
		}
		return $content;
	}

	function bfox_user_reading_plans($blogs)
	{
		global $bfox_plan_progress;
		
		foreach ($blogs as $blog_id => $blog_info)
		{
			$blog_url = $blog_info->siteurl . '/wp-admin/admin.php?';
			$plan_url = $blog_url . 'page=' . BFOX_PLAN_SUBPAGE;
			echo "<strong><a href=\"$plan_url\">$blog_info->blogname</a></strong><br/>";
			$blog_plan = new PlanBlog($blog_id);
			$blog_plans = $blog_plan->get_plans();
			if (0 < count($blog_plans))
			{
				foreach ($blog_plans as $plan)
				{
					$plan_url .= '&amp;plan_id=' . $plan->id . '&amp;';
					echo "<strong>$plan->name</strong> (<a href=\"$plan_url\">view plan</a>)<br/><i>$plan->summary</i><br/>";
					$progress_plan_id = $bfox_plan_progress->get_plan_id($blog_id, $plan->id);
					if (isset($progress_plan_id))
					{
						$refs_object = $bfox_plan_progress->get_plan_refs($progress_plan_id);
						if (isset($refs_object->last_read))
							echo 'The furthest you have read is ' . $refs_object->read[$refs_object->last_read]->get_link() . '.<br/>';
						if (isset($refs_object->first_unread))
							echo 'You should read ' . $refs_object->unread[$refs_object->first_unread]->get_link() . ' next.<br/>';
					}
					else
					{
						$track_url = $plan_url . 'action=track&amp;';
						echo "Not tracked. You can choose to <a href=\"$track_url\">follow this reading plan</a>.<br/>";
					}
					echo '<br/>';
				}
			}
			else
			{
				echo "This Bible Study Blog currently has no reading plans.<br/>";
			}
			echo "<br/>";
		}
	}
	
	function bfox_progress_page()
	{
		global $user_ID;
		// Get the bible study blogs for the current user
		$blogs = bfox_get_bible_study_blogs($user_ID);

		echo "<div class=\"wrap\">";
		echo "<h2>Bible Study Blogs</h2>";
		if (0 < count($blogs))
		{
			echo "You are a part of the following Bible Study Blogs:<br/>";
			echo "<ul>";
			foreach ($blogs as $blog_id => $blog_info)
				echo "<li><a href=\"{$blog_info->siteurl}/wp-admin/\">$blog_info->blogname</a></li>";
			echo "</ul>";
		}
		else
			echo "You are not yet a part of any Bible Study Blogs.<br/><br/>";
		$home_dir = get_option('home');
		echo "You can always <a href=\"{$home_dir}/wp-signup.php\">create a new Bible Study Blog</a>. <br/>";
		echo "</div>";

		echo "<div class=\"wrap\">";
		echo "<h2>Reading Plans</h2><br/>";
		if (0 < count($blogs))
		{
			bfox_user_reading_plans($blogs);
		}
		else
		{
			echo "You need to be part of a Bible Study Blog to have a reading plan. Feel free to join one or create your own.<br/>";
		}
		echo "</div>";
	}

	function bfox_create_plan()
	{
		global $bfox_plan, $bfox_plan_progress, $blog_id;

		// Only level 7 users can edit/create plans
		$can_edit = current_user_can(7);

		if($can_edit && ($_GET['hidden_field'] == 'Y'))
		{
			$plan = array();
			if (isset($_GET['plan_name'])) $plan['name'] = (string) $_GET['plan_name'];
			if (isset($_GET['plan_summary'])) $plan['summary'] = (string) $_GET['plan_summary'];

			if (isset($_GET['plan_id']) && ('' != $_GET['plan_id']))
			{
				$plan['id'] = (int) $_GET['plan_id'];
				$bfox_plan->edit_plan((object) $plan);
			}
			else
			{
				$text = (string) $_GET['books'];
				$period_length = (string) $_GET['frequency'];
				$section_size = (int) $_GET['num_chapters'];
				if ($section_size == 0) $section_size = 1;

				$refs = new BibleRefs($text);
				$plan['refs_array'] = $refs->get_sections($section_size);
				$bfox_plan->add_new_plan((object) $plan);
			}
		}
		
		if (isset($_GET['plan_id']))
		{
			if ($_GET['action'] == 'delete')
			{
				if ($can_edit) $bfox_plan->delete($_GET['plan_id']);
			}
			else if ($_GET['action'] == 'track')
				$bfox_plan_progress->track_plan($blog_id, $_GET['plan_id']);

			$display_plans = $bfox_plan->get_plans($_GET['plan_id']);
		}

		echo "<div class=\"wrap\">";
		if (0 < count($display_plans))
		{
			$plan_url_base = 'admin.php?page=' . BFOX_PLAN_SUBPAGE . '&amp;';
			echo "<h2>View Reading Plan</h2><br/>";
			echo "You have selected the following Reading Plan: (<a href=\"$plan_url_base\">view all</a>)";
			echo bfox_blog_reading_plans($display_plans, $can_edit);

			if ($can_edit)
				foreach ($display_plans as $plan) bfox_edit_plan_menu($plan);
		}
		else
		{
			$display_plans = $bfox_plan->get_plans();

			echo "<h2>Available Reading Plans</h2><br/>";
			if (0 < count($display_plans))
			{
				echo "This Bible Study Blog has the following Reading Plans:";
				echo bfox_blog_reading_plans($display_plans, $can_edit);
			}
			else
			{
				echo "This Bible Study Blog has no bible reading plans.<br/>";
			}

			if ($can_edit) bfox_edit_plan_menu();
		}
		echo "</div>";

	}

	function bfox_get_recent_scriptures_output($max = 1, $read = false)
	{
		global $bfox_history;

		$output = '';
		$refs_array = $bfox_history->get_refs_array($max, $read);
		if (0 < count($refs_array))
		{
			$read_str = $read? 'Read' : 'Viewed';
			$lc_read_str = strtolower($read_str);
			$output .= "<h3>Recently $read_str Scriptures<a name=\"recent_{$lc_read_str}\"></a></h3>";
			foreach ($refs_array as $refs)
			{
				$output .= bfox_get_bible_link($refs->get_string()) . '<br/>';
			}
		}
		return $output;
	}

?>
