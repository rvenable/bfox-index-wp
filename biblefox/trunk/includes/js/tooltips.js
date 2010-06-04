jQuery(document).ready(function() {
	// Add tooltips to bible ref links
	// NOTE: For live() qTip config see: http://stackoverflow.com/questions/2005521/problem-with-qtip-tips-not-showing-because-elements-load-after-the-script#answer-2485862
	jQuery('span.bible-tooltip a').live('click', function() {
		jQuery(this).qtip({
			content: {
				text: 'Loading...',
				url: jQuery(this).next('a.bible-tooltip-url').attr('href'),
				title: {
					text: '<a href="' + jQuery(this).attr('href') + '">' + jQuery(this).text() + '</a>', // Give the tooltip a title using each elements text
					button: 'Close' // Show a close link in the title
				}
			},
			position: {
				corner: {
					target: 'bottomMiddle', // Position the tooltip above the link
					tooltip: 'topMiddle'
				},
				adjust: {
					screen: true // Keep the tooltip on-screen at all times
				}
			},
			overwrite: false,
			show: {
				ready: true,
				when: 'click',
				solo: true
			},
			hide: 'unfocus',
			style: {
				tip: true, // Apply a speech bubble tip to the tooltip at the designated tooltip corner
				border: {
					width: 0,
					radius: 4
				},
				name: 'light', // Use the default light style
				width: 640 // Set the tooltip width
			},
			api: {
				onContentUpdate: function() {
					// When the content is updated, make sure that any iframe selects can update properly
					this.elements.content.find('select.bfox-iframe-select').change(bfox_blog_iframe_select_change);
				},
				onHide: function() {
					// HACK: Firefox has a bug that causes flickering when the iframe scroll position is not 0
					// See: http://craigsworks.com/projects/qtip/forum/topic/314/qtip-flicker-in-firefox/
					// Fix it by disabling scrolling on the iframes when we hide them
					this.elements.content.find('iframe').attr('scrolling', 'no');
				},
				onShow: function() {
					// Re-enable scrolling on the iframes
					this.elements.content.find('iframe').attr('scrolling', 'yes');
				}
			}
		});
		return false;
	});
});