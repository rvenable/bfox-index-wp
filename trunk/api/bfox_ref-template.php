<?php

function push_bfox_ref_link_action($action) {
	$refController = BfoxRefController::sharedInstance();
	$refController->pushLinkAction($action);
}

function bfox_ref_link_action_tooltip() {
	return "BfoxAjax.loadTooltip('%ref%');";
}

function bfox_ref_link_action_update_form($formSelector) {
	return "BfoxAjax.updateFormRef('$formSelector', '%ref%'); event.preventDefault();";
}

function pop_bfox_ref_link_action() {
	$refController = BfoxRefController::sharedInstance();
	$refController->popLinkAction();
}

function bfox_ref_link($ref_str, $options = array()) {
	if (empty($ref_str)) return false;

	$defaults = array(
		'href' => '',
		'class' => array(),
		'text' => $ref_str,
	);
	extract(wp_parse_args($options, $defaults));

	// href
	if (empty($href)) $href = bfox_ref_url($ref_str);
	$attrs['href'] = $href;

	// class
	$class = (array) $class;

	if (empty($attrs['onclick'])) {
		$refController = BfoxRefController::sharedInstance();
		$attrs['onclick'] = esc_attr($refController->currentAction($ref_str));

		if (empty($attrs['onclick'])) unset($attrs['onclick']);
	}

	if (!empty($class)) $attrs['class'] = implode(' ', $class);

	// Attribute string
	$attr_str = '';
	foreach ($attrs as $attr => $value) $attr_str .= " $attr='$value'";

	return "<a$attr_str>$text</a>";
}

/**
 * Replaces bible references with bible links in a given html string
 * @param string $content
 * @return string
 */
function bfox_ref_replace_html($content, $callback = 'bfox_ref_replace_html_cb') {
	return BfoxRefParser::simple_html($content, null, $callback);
}
	function bfox_ref_replace_html_cb($text, $ref) {
		return bfox_ref_link($ref->get_string(), array('text' => $text));
	}

function list_bfox_ref_chapters(BfoxRef $ref = null, $options = array()) {
	$defaults = array(
		'format' => BibleMeta::name_none,
		'first_format' => false,
		'before' => '<li>',
		'after' => '</li>',
		'between' => '',
		'link_cb' => 'bfox_ref_link',
		'link_cb_options' => array(),
	);
	extract(wp_parse_args($options, $defaults));

	$chapters = $ref->get_sections(1);

	$strs = array();
	foreach ($chapters as $chapter) {
		if ($first_format !== false) {
			$ref_str = $chapter->get_string($first_format);
			$first_format = false;
		}
		else {
			$ref_str = $chapter->get_string($format);
		}
		$link_cb_options['text'] = $ref_str;

		if ($link_cb && is_callable($link_cb)) $ref_str = call_user_func($link_cb, $chapter->get_string(), $link_cb_options);
		$strs []= $before . $ref_str . $after;
	}

	return implode($between, $strs);
}

?>