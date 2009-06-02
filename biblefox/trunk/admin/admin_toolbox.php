<?php

class BfoxMainToolbox extends BfoxToolBox
{

	/*
	 Upgrade function for DB tables
	 */
	function upgrade_all_tables()
	{
		// Get the blogs using a WPMU function (from wpmu-functions.php)
		$blogs = get_blog_list();
		foreach ($blogs as $blog)
		{
			echo "<strong>Upgrading Blog {$blog['blog_id']}...</strong><br/>";

			$plan = new PlanBlog($blog['blog_id']);
			if (!$plan->are_tables_installed())
			{
				echo "Upgrading Plan<br/>";
				$plan->create_tables();
				//$plan->reset_end_dates();
			}
		}

		global $wpdb;
		$users = $wpdb->get_col("SELECT ID FROM $wpdb->users");
		foreach ($users as $user_id)
		{
			echo "<strong>Upgrading User $user_id...</strong><br/>";

			$history = new History($user_id);
			if ($history->are_tables_installed())
			{
				echo "Upgrading History<br/>";
				$history->create_tables();
			}

			$plan = new PlanProgress($user_id);
			if ($plan->are_tables_installed())
			{
				echo "Upgrading Plan Progress<br/>";
				$plan->create_tables();
			}
		}
	}

	function test_html_ref_replace()
	{
		$str = "<xml>
		<p>I like Gen 1.</p>
		<p>What do you think? <a href=''>john 21</a> Do you prefer<d><d> ex 2 or 1sam 3 - 4 or 1 th 4? gen 3:4-8:2 gen 3ddd:2 fff- 1 1 3 </p>
		<p>gen lala yoyo 4:5</p>
		</xml>
		";

		echo $str;
		echo strip_tags($str);
		echo bfox_process_html_text($str, 'bfox_ref_replace');
	}

	/**
	 * Takes a bible ref string and uses it to create a BibleRefs to test BibleRefs for different inputs
	 *
	 * @param string $ref_str Bible Reference string to test
	 */
	private function test_ref($ref_str, $expected = '')
	{

		// Test setting a BibleRefs by a string
		$ref = RefManager::get_from_str($ref_str);
		$result = $ref->get_string();

		// Test setting a BibleRefs by a set of unique ids
		$sets = $ref->get_sets();
		$ref2 = RefManager::get_from_sets($sets);
		$result2 = $ref2->get_string();

		echo "$ref_str -> <strong>$result</strong>";
		if (!empty($expected))
		{
			if ($expected != $result) echo " (ERROR: expected $expected)";
			else echo " (Expected...)";
		}
		if ($result != $result2) echo " (ERROR: Result2 not equal - $result2)";
		echo '<br/>';
	}

	/**
	 * Tests different bible reference input strings
	 *
	 */
	function test_refs()
	{
		// Test the typical references
		$this->test_ref('1 sam', '1 Samuel');
		$this->test_ref('1sam 1', '1 Samuel 1');
		$this->test_ref('1sam 1-2', '1 Samuel 1-2');
		$this->test_ref('1sam 1:1', '1 Samuel 1:1');
		$this->test_ref('1sam 1:1-5', '1 Samuel 1:1-5');
		$this->test_ref('1sam 1:1-2:5', '1 Samuel 1-2:5');
		$this->test_ref('1sam 1:2-2:5', '1 Samuel 1:2-2:5');
		$this->test_ref('1sam 1-2:5', '1 Samuel 1-2:5');

		// Test periods
		$this->test_ref('1sam. 1', '1 Samuel 1');

		// This test was failing (see bug 21)
		$this->test_ref('Judges 2:6-3:6', 'Judges 2:6-3:6');

		// Test ignore words
		$this->test_ref('Book of Judges 2', 'Judges 2');
		$this->test_ref('First Book of Judges 2', 'error'); // This one should not work!
		$this->test_ref('First Book of Samuel 2', '1 Samuel 2');

		// Test that we can match synonyms with multiple words
		$this->test_ref('Song Solomon 2', 'Song of Solomon 2');

		// This should be Gen 1:1, 1:3 - 2:3
		$this->test_ref('gen 1:1,3-2:3', 'Genesis 1:1,3-2:3');

		$this->test_ref('gen 1-100', 'Genesis');
		$this->test_ref('gen 2-100', 'Genesis 2-50');
		$this->test_ref('gen 49:1-100', 'Genesis 49');
		$this->test_ref('gen 49:2-100', 'Genesis 49:2-33');
		$this->test_ref('gen 50:1-100', 'Genesis 50');
		$this->test_ref('gen 50:2-100', 'Genesis 50:2-26');
		$this->test_ref('gen 50:1,2-100', 'Genesis 50');
		$this->test_ref('gen 50:1,3-100', 'Genesis 50:1,3-26');

		// Test min/max in Romans 14
		$this->test_ref('rom 14:2-100', 'Romans 14:2-26');
		$this->test_ref('rom 14:1-22', 'Romans 14:1-22');
		$this->test_ref('rom 14:1-23', 'Romans 14');
		$this->test_ref('rom 14:2-23', 'Romans 14:2-26');

		// Test having consecutive books
		$this->test_ref('Gen 2-100, Exodus', 'Genesis 2-50; Exodus');
		$this->test_ref('Gen 2-100, Exodus, Lev', 'Genesis 2-50; Exodus; Leviticus');

		// Test long strings with lots of garbage
		$this->test_ref('hello dude genesis 1,;,2 gen 5 1 sam 4, song ;of song 3', 'Genesis 1-2; 5; 1 Samuel 4; Song of Solomon'); // TODO3: words like song get detected as the entire book Song of Solomon
		$this->test_ref("<xml>
		<p>I like Gen 1.</p>
		<p>What do you think? john. 21 Do you prefer<d><d> ex 2 or 1sam 3 - 4 or 1 th 4? gen 3:4-8:2 gen 3ddd:2 fff- 1 1 3 </p>
		<p>exodus lala yoyo 4:5</p>
		</xml>
		", 'Genesis 1; 3-8:2; Exodus; 1 Samuel 3-4; John 21; 1 Thessalonians 4'); // TODO3: 'ex' is not detected because it is only 2 letters
	}

	public function test_sub_refs() {

		$start = 'eze 14,15';
		$subs = array('eze 14:3', 'eze 14:5-15:2', 'eze 14:1-2', 'eze 14:4', 'eze 15:7-9', 'eze 13-16');

		$refs = RefManager::get_from_str($start);
		pre("start:" . $refs->get_string());

		foreach ($subs as $sub) {
			$sub_refs = RefManager::get_from_str($sub);
			$refs->sub($sub_refs);
			pre(" - " . $sub_refs->get_string() . " = " . $refs->get_string());
			//pre($refs);
		}
	}

	/**
	 * Tests the bfox_get_discussions() function
	 *
	 */
	function test_discussions()
	{
		echo bfox_get_discussions(array());//'limit' => 4));
	}

	/**
	 * Tests the quicknotes system
	 *
	 */
	function test_quicknotes()
	{
		global $bfox_quicknote;
		$bfox_quicknote->create_tables();
		// $bfox_quicknote->save_quicknote(RefManager::get_from_str('Genesis 2, Gen 7-9'), 'Fun stuff!');
		//$res = $bfox_quicknote->get_quicknotes(RefManager::get_from_str('Gen'));
		//$this->echo_table_results($res);
		//$bfox_quicknote->list_quicknotes(RefManager::get_from_str('Gen'));
	}

	/**
	 * Sends all of today's reading plan emails for the current blog
	 *
	 */
	function send_reading_plan_emails()
	{
		bfox_plan_emails_send();
	}

	/**
	 * Clears the reading plan email scheduled event for this blog.
	 *
	 * To add the event again after clearing it, edit any unfinished reading plan.
	 *
	 */
	function clear_plan_email_event()
	{
		wp_clear_scheduled_hook('bfox_plan_emails_send_action');
	}

	/**
	 * For every user, updates all the default user options which haven't been set yet.
	 *
	 */
	function update_all_user_options()
	{
		global $wpdb;
		$user_ids = $wpdb->get_col("SELECT ID FROM $wpdb->users");
		foreach ($user_ids as $user_id)
			bfox_user_add_defaults($user_id);
	}

	function create_translation_table()
	{
		Translations::create_tables();
	}

	/**
	 * Create the translation index table
	 *
	 */
	function create_translation_index_table()
	{
		global $wpdb;
		$wpdb->query('DROP TABLE ' . Translations::index_table);
		Translations::create_translation_index_table();
		echo "Dropped and recreated the index table. Please run refresh_all_translation_indexes().";
	}

	/**
	 * Loop through each enabled bible translation and refresh their index data
	 *
	 */
	function refresh_all_translation_indexes()
	{
		$translations = Translations::get_translations();
		foreach ($translations as $translation)
		{
			echo "Refreshing $translation->long_name (ID: $translation->id)...<br/>";
			Translations::refresh_translation_index($translation);
		}
		echo 'Finished<br/>';
	}

	/**
	 * Run this to check if there are any synonyms that need to have their ignore words removed
	 *
	 */
	function clean_ignore_words()
	{
		$prefixes = array();
		foreach (BibleMeta::$synonyms[0] as $synonym => $book_id)
		{
			$raw_words = str_word_count($synonym, 1, '0123456789');

			$words = array();
			foreach ($raw_words as $word) if (!isset(BibleMeta::$ignore_words[$word])) $words []= $word;
			unset($raw_words);

			$new_syn = implode(' ', $words);
			if ($synonym != $new_syn)
			{
				echo "Replace '$synonym' with '$new_syn'<br/>";
			}
		}
	}

	private static function print_array_decl($array, $level = 0)
	{
		$elements = array();
		foreach ($array as $key => $value)
		{
			if (is_array($value)) $value = print_array_decl($value, $level + 1);
			else $value = "'$value'";
			$elements []= "'$key' => $value";
		}

		if (0 >= $level) $glue = ",\n";
		else $glue = ', ';

		return 'array(' . implode($glue, $elements) . ')';
	}

	function create_synonym_prefixes_array()
	{
		$prefixes = array();
		foreach (BibleMeta::$synonyms[0] as $synonym => $book_id)
		{
			$words = str_word_count($synonym, 1, BibleMeta::digits);
			array_pop($words);
			while (!empty($words))
			{
				$prefixes[implode(' ', $words)] = TRUE;
				array_pop($words);
			}
		}
		pre(self::print_array_decl($prefixes));
	}

	function test_string_parsing()
	{
		$str = 'hello dude genesis 1,;,2 gen 5 1 sam 4, song ;of song 3';
		echo "$str<br/>";
		$books = BibleMeta::get_books_in_string($str);
		foreach ($books as $book)
		{
			$book_name = BibleMeta::get_book_name($book[0]);
			echo "Book: $book_name ($book[1])<br/>";
		}
	}

	public function verse_count()
	{
		global $wpdb;
		$translations = Translations::get_translations();

		$errors = array();
		$vs_counts = array();

		foreach ($translations as $trans)
		{
			$vals = array('name' => $trans->short_name, 'id' => $trans->id);
			$vals['total_count'] = $wpdb->get_var("SELECT COUNT(*) FROM $trans->table");
			$vals['book_count'] = $wpdb->get_var("SELECT COUNT(DISTINCT book_id) FROM $trans->table");
			$vals['ch_count'] = $wpdb->get_var("SELECT COUNT(DISTINCT book_id, chapter_id) FROM $trans->table");
			$vals['vs_count'] = $wpdb->get_var("SELECT COUNT(*) FROM $trans->table WHERE book_id != 0 AND chapter_id != 0 AND verse_id != 0");
			$vals['ch_count66'] = $wpdb->get_var("SELECT COUNT(DISTINCT book_id, chapter_id) FROM $trans->table WHERE book_id <= 66");
			$vals['vs_count66'] = $wpdb->get_var("SELECT COUNT(*) FROM $trans->table WHERE book_id <= 66 AND book_id != 0 AND chapter_id != 0 AND verse_id != 0");
			pre($vals);

			$this_ch_counts = $wpdb->get_col("SELECT COUNT(DISTINCT chapter_id) FROM $trans->table WHERE chapter_id != 0 GROUP BY book_id");
			foreach ($this_ch_counts as $book => $ch_count)
			{
				$book++;
				if (isset($vs_counts[$book][0]) && ($ch_count != $vs_counts[$book][0]))
				{
					$errors []= "($trans->id) Chapter Count Error: Book $book";
					$vs_counts[$book][0] = min($ch_count, $vs_counts[$book][0]);
				}
				else $vs_counts[$book][0] = $ch_count;
			}

			$this_vs_counts = $wpdb->get_results("SELECT book_id, chapter_id, COUNT(DISTINCT verse_id) as vs_count FROM $trans->table WHERE verse_id != 0 GROUP BY book_id, chapter_id");
			foreach ($this_vs_counts as $vs_count)
			{
				if (isset($vs_counts[$vs_count->book_id][$vs_count->chapter_id]) && ($vs_count->vs_count != $vs_counts[$vs_count->book_id][$vs_count->chapter_id]))
				{
					$errors []= "($trans->id) Verse Count Error: Book $vs_count->book_id, Chapter $vs_count->chapter_id";
					$vs_counts[$vs_count->book_id][$vs_count->chapter_id] = min($vs_count->vs_count, $vs_counts[$vs_count->book_id][$vs_count->chapter_id]);
					$max_vs_counts[$vs_count->book_id][$vs_count->chapter_id] = max($vs_count->vs_count, $vs_counts[$vs_count->book_id][$vs_count->chapter_id], $max_vs_counts[$vs_count->book_id][$vs_count->chapter_id]);
				}
				else $vs_counts[$vs_count->book_id][$vs_count->chapter_id] = $vs_count->vs_count;
			}
		}

		// Hard-code error fixing
		// WEB only has 24 real verses, but has a verse 25 which is just a footnote, so lets just use 24
		$vs_counts[45][16] = min(24, $vs_counts[45][16]);

		pre($errors);

		$str = '';
		foreach ($vs_counts as $book => $counts) $str .= "$book => array(" . implode(', ', $counts) . "),\n";
		pre($str);
		echo '<pre>';
		var_export($max_vs_counts);
		echo '</pre>';
		$str = '';
		foreach ($max_vs_counts as $book => $counts)
		{
			$chs = array();
			foreach ($counts as $ch => $count) $chs []= "$ch => $count";
			$str .= "$book => array(" . implode(', ', $chs) . "),\n";
		}
		pre($str);
	}

	public function test_reading_plan_dividing()
	{
		$seq = new BibleRefs();
		$seq->push_string('john, acts, romans, 1 john, 2 john, 3 john');
		echo $seq->get_string() . '<br/>';
		foreach ($seq->get_sections(3) as $sec) echo $sec->get_string() . '<br/>';
	}

	private function random_populate($str, $junk)
	{
		$refs = RefManager::get_from_str($str);
		BfoxPosts::set_post_refs(rand(200,900), $refs, FALSE);
	}

	function random_populate_posts_table()
	{
		// Test the typical references
		$this->random_populate('1 sam', '1 Samuel');
		$this->random_populate('1sam 1', '1 Samuel 1');
		$this->random_populate('1sam 1-2', '1 Samuel 1-2');
		$this->random_populate('1sam 1:1', '1 Samuel 1:1');
		$this->random_populate('1sam 1:1-5', '1 Samuel 1:1-5');
		$this->random_populate('1sam 1:1-2:5', '1 Samuel 1-2:5');
		$this->random_populate('1sam 1:2-2:5', '1 Samuel 1:2-2:5');
		$this->random_populate('1sam 1-2:5', '1 Samuel 1-2:5');

		// Test periods
		$this->random_populate('1sam. 1', '1 Samuel 1');

		// This test was failing (see bug 21)
		$this->random_populate('Judges 2:6-3:6', 'Judges 2:6-3:6');

		// Test ignore words
		$this->random_populate('Book of Judges 2', 'Judges 2');
		$this->random_populate('First Book of Judges 2', 'error'); // This one should not work!
		$this->random_populate('First Book of Samuel 2', '1 Samuel 2');

		// Test that we can match synonyms with multiple words
		$this->random_populate('Song Solomon 2', 'Song of Solomon 2');

		// This should be Gen 1:1, 1:3 - 2:3
		$this->random_populate('gen 1:1,3-2:3', 'Genesis 1:1,3-2:3');

		$this->random_populate('gen 1-100', 'Genesis');
		$this->random_populate('gen 2-100', 'Genesis 2-50');
		$this->random_populate('gen 49:1-100', 'Genesis 49');
		$this->random_populate('gen 49:2-100', 'Genesis 49:2-33');
		$this->random_populate('gen 50:1-100', 'Genesis 50');
		$this->random_populate('gen 50:2-100', 'Genesis 50:2-26');
		$this->random_populate('gen 50:1,2-100', 'Genesis 50');
		$this->random_populate('gen 50:1,3-100', 'Genesis 50:1,3-26');

		// Test min/max in Romans 14
		$this->random_populate('rom 14:2-100', 'Romans 14:2-26');
		$this->random_populate('rom 14:1-22', 'Romans 14:1-22');
		$this->random_populate('rom 14:1-23', 'Romans 14');
		$this->random_populate('rom 14:2-23', 'Romans 14:2-26');

		// Test having consecutive books
		$this->random_populate('Gen 2-100, Exodus', 'Genesis 2-50; Exodus');
		$this->random_populate('Gen 2-100, Exodus, Lev', 'Genesis 2-50; Exodus; Leviticus');

		// Test long strings with lots of garbage
		$this->random_populate('hello dude genesis 1,;,2 gen 5 1 sam 4, song ;of song 3', 'Genesis 1-2; 5; 1 Samuel 4; Song of Solomon'); // TODO3: words like song get detected as the entire book Song of Solomon
		$this->random_populate("<xml>
		<p>I like Gen 1.</p>
		<p>What do you think? john. 21 Do you prefer<d><d> ex 2 or 1sam 3 - 4 or 1 th 4? gen 3:4-8:2 gen 3ddd:2 fff- 1 1 3 </p>
		<p>exodus lala yoyo 4:5</p>
		</xml>
		", 'Genesis 1; 3-8:2; Exodus; 1 Samuel 3-4; John 21; 1 Thessalonians 4'); // TODO3: 'ex' is not detected because it is only 2 letters
	}

	private function post_refs_to_tags($blog_id) {
		global $wpdb;

		// Get all the post info for this blog
		$results = $wpdb->get_results($wpdb->prepare("SELECT post_id, FALSE, verse_begin, verse_end FROM " . $wpdb->bfox_bible_ref));
		$post_refs = array();


		foreach ($results as $result) {
			if (!isset($post_refs[$result->post_id])) $post_refs[$result->post_id] = new BibleRefs();
			$post_refs[$result->post_id]->add_seq($result->verse_begin, $result->verse_end);
		}

		foreach ($post_refs as $post_id => $new_tag_refs) {
			if ($new_tag_refs->is_valid()) {
				$new_tag = $new_tag_refs->get_string(BibleMeta::name_short);

				// Get the bible references from the post tags
				$tags = wp_get_post_tags($post_id, array('fields' => 'names'));
				//pre($tags);
				foreach ($tags as &$tag)
				{
					if (trim($tag) == $new_tag) $new_tag = '';
				}

				if (!empty($new_tag)) {
					$tags []= $new_tag;
					echo "New tag: $post_id - $new_tag (" . implode(',', $tags) . ")<br/>";
					wp_set_post_tags($post_id, $tags);
				}
				echo "Saving $post_id<br/>";
				bfox_save_post($post_id, get_post($post_id));
			}
		}
	}

	public function repopulate_posts_table() {
		global $wpdb;

		/*echo 'Dropping table<br/>';
		$wpdb->query('DROP TABLE IF EXISTS ' . BfoxPosts::table);
		echo 'Creating table<br/>';
		BfoxPosts::create_table();*/

		$blogs = get_blog_list(0, 'all');

		echo 'Populating from blogs:<br/>';
		foreach ($blogs as $blog) {
			$blog = (object) $blog;
			switch_to_blog($blog->blog_id);
			/*$wpdb->query($wpdb->prepare("INSERT INTO " . BfoxPosts::table . " (blog_id, post_id, ref_type, verse_begin, verse_end)
			SELECT %d, post_id, FALSE, verse_begin, verse_end FROM " . $wpdb->bfox_bible_ref, $blog->blog_id));*/
			$this->post_refs_to_tags($blog->blog_id);
			restore_current_blog();
			echo "Populated from blog $blog->blog_id ($blog->domain$blog->path)<br/>";
		}
	}

	public function recreate_plans_table() {
		/*global $wpdb;
		echo 'Dropping tables<br/>';
		$wpdb->query('DROP TABLE IF EXISTS ' . BfoxPlans::table_plans);
		$wpdb->query('DROP TABLE IF EXISTS ' . BfoxPlans::table_readings);
		$wpdb->query('DROP TABLE IF EXISTS ' . BfoxPlans::table_subs);*/
		echo 'Creating tables<br/>';
		BfoxPlans::create_tables();

		/*$blogs = get_blog_list(0, 'all');

		echo 'Populating from blogs:<br/>';
		foreach ($blogs as $blog) {
			$blog = (object) $blog;

			switch_to_blog($blog->blog_id);

			$bfox_plan = new PlanBlog();
			$plans = $bfox_plan->get_plans();

			foreach ($plans as $plan) {
				$plan->id = 0;
				$plan->is_private = FALSE;
				$plan->is_scheduled = TRUE;
				$plan->description = $plan->summary;
				$plan->is_recurring = FALSE;
				$plan->start_date = date('Y-m-d', strtotime($plan->start_date));
				$plan->end_date = date('Y-m-d', strtotime($plan->end_date));

				$new_plan = new BfoxReadingPlan($plan);
				foreach ($plan->refs as $refs) $new_plan->set_reading($refs);
				BfoxPlans::save_plan($new_plan);

				$new_sub = new BfoxReadingSub();
				$new_sub->plan_id = $new_plan->id;
				$new_sub->user_id = $blog->blog_id;
				$new_sub->user_type = BfoxPlans::user_type_blog;
				$new_sub->is_subscribed = TRUE;
				$new_sub->is_owned = TRUE;
				BfoxPlans::save_sub($new_sub);

				$new_sub->user_type = BfoxPlans::user_type_user;
				$new_sub->is_owned = FALSE;

				$users = get_users_of_blog($blog->blog_id);
				foreach ($users as $user) {
					$new_sub->user_id = $user->user_id;
					BfoxPlans::save_sub($new_sub);
				}
			}

			restore_current_blog();
			echo "Populated from blog $blog->blog_id ($blog->domain$blog->path)<br/>";
		}*/
	}

	/*public function create_history_table() {
		global $wpdb;
		require_once BFOX_BIBLE_DIR . '/history.php';
		$wpdb->query('DROP TABLE IF EXISTS ' . BfoxHistory::table);
		BfoxHistory::create_table();
	}

	public function create_note_tables() {
		global $wpdb;
		require_once BFOX_BIBLE_DIR . '/notes.php';
		$wpdb->query('DROP TABLE IF EXISTS ' . BfoxNotes::table_notes);
		$wpdb->query('DROP TABLE IF EXISTS ' . BfoxNotes::table_refs);
		BfoxNotes::create_tables();
	}*/

	/**
	 * A function for dumping temporary functionality to do temporary tasks
	 *
	 */
	function temp() {
		echo BfoxPosts::get_post_ids(RefManager::get_from_str('Gen 1'));
		echo BfoxPosts::get_post_ids_for_blogs(RefManager::get_from_str('Gen 1'), array(1, 2, 3));
	}

}
BfoxAdminTools::add_toolbox(new BfoxMainToolbox());

?>
