<?php

	function bfox_create_plan_menu()
	{
		$text = (string) $_GET['books'];
		$period_length = (string) $_GET['frequency'];
		$section_size = (int) $_GET['num_chapters'];
		
	?>
<h4>Create a Reading Plan</h4>
<form action="admin.php" method="get">
<input type="hidden" name="page" value="<?php echo BFOX_PLAN_SUBPAGE; ?>">
<input type="hidden" name="hidden_field" value="Y">
Which books?<br/>
<textarea rows="5" cols="20" wrap="physical" name="books"><?php echo $text; ?></textarea><br/>
How Fast?<br/>
<input type="text" size="10" maxlength="40" name="num_chapters" value="<?php echo $section_size; ?>"> chapters per
<select name="frequency" value="<?php echo $period_length; ?>">
<option>day</option>
<option>week</option>
<option>month</option>
</select>
<input type="submit" class="button" />
</form>
<?php
	}
	
	function bfox_get_sections($text, $size)
	{
		$reflist = bfox_parse_reflist($text);
		
		$sections = array();
		$period = 0;
		$section = 0;
		$remainder = 0;
		$remainderStr = "";
		foreach ($reflist as $refStr)
		{
			$ref = bfox_parse_ref($refStr);
			$chapters = bfox_get_chapters($ref);
			$num_chapters = count($chapters);
			$num_sections = (int) floor(($num_chapters + $remainder) / $size);
			
			$tmpRef['book_name'] = $ref['book_name'];
			$chapter1_index = 0;
			$chapter2_index = $size - $remainder - 1;
			for ($index = 0; $index < $num_sections; $index++)
			{
				$tmpRefStr = "";
				if (($index == 0) && ($remainder > 0))
				{
					$tmpRefStr .= "$remainderStr, ";
					$remainderStr = "";
					$remainder = 0;
				}
				
				$tmpRef['chapter1'] = $chapters[$chapter1_index];
				if ($chapter2_index > $chapter1_index)
					$tmpRef['chapter2'] = $chapters[$chapter2_index];
				else $tmpRef['chapter2'] = 0;
				
				$tmpRefStr .= bfox_get_refstr($tmpRef);
				$sections[] = $tmpRefStr;
				
				$chapter1_index = $chapter2_index + 1;
				$chapter2_index = $chapter1_index + $size - 1;
			}
			
			if ($chapter1_index < $num_chapters)
			{
				$remainder += $num_chapters - $chapter1_index;
				$chapter2_index = $num_chapters - 1;
				
				$tmpRef['chapter1'] = $chapters[$chapter1_index];
				if ($chapter2_index > $chapter1_index)
					$tmpRef['chapter2'] = $chapters[$chapter2_index];
				else $tmpRef['chapter2'] = 0;
				
				if ($remainderStr != "")
					$remainderStr .= ", ";
				$remainderStr .= bfox_get_refstr($tmpRef);
			}
		}
		if ($remainderStr != "")
			$sections[] = $remainderStr;
		
		return $sections;
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
	
	function bfox_create_plan()
	{
		if($_GET['hidden_field'] == 'Y')
		{
			$text = (string) $_GET['books'];
			$period_length = (string) $_GET['frequency'];
			$section_size = (int) $_GET['num_chapters'];
			if ($section_size == 0) $section_size = 1;

			$refs = new BibleRefs($text);
			$sections = $refs->get_sections($section_size);
//			$sections = bfox_get_sections_slow($text, $section_size);
			echo "c:" . count($sections) . "<br/>";
			
			$index = 1;
			foreach ($sections as $section)
			{
				echo "<br/>$period_length $index: " . $section->get_string();
				$index++;
			}
		}
		
		bfox_create_plan_menu();
	}

	function bfox_get_recent_scriptures_output($max = 1, $read = false)
	{
		global $bfox_history;

		$output = '';
		$refs_array = $bfox_history->get_refs_array($max, $read);
		if (0 < count($refs_array))
		{
			$read_str = $read? 'Read' : 'Viewed';
			$output .= "<h3>Recently $read_str Scriptures</h3>";
			foreach ($refs_array as $refs)
			{
				$output .= bfox_get_bible_link($refs->get_string()) . '<br/>';
			}
		}
		return $output;
	}

	function bfox_get_special_page_plan()
	{
		require_once("bfox-history.php");
		$content = '';

		// Get the recently read scriptures
		$content .= bfox_get_recent_scriptures_output(10, true);

		// Get the recently viewed scriptures
		$content .= bfox_get_recent_scriptures_output(10, false);

		$special_page = array();
		$special_page['post_title'] = 'Reading Plan';
		$special_page['post_content'] = $content;
		$special_page['post_type'] = 'page';
		return $special_page;
	}

?>
