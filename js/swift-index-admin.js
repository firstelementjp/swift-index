/**
 * Swift Index Admin Scripts
 *
 * Handles JavaScript functionality for the Swift Index plugin's admin interface,
 * including tab navigation and confirmation dialogs.
 *
 * @package Swift_Index
 * @since   1.0.0
 */
jQuery(document).ready(function($) {
	var wrap = $('#swift-index-wrap');

	/**
	 * Activates a tab based on the current URL hash.
	 * If a hash matches an existing tab link's href, that tab is triggered.
	 */
	function activateTabFromHash() {
		var hash = window.location.hash; // (e.g. "#settings", "#logs", "#setup-guide")
		var $activeTabLink = null;

		if (hash) {
			$activeTabLink = wrap.find('a.nav-tab[href="' + hash + '"]');
		}

		if ($activeTabLink && $activeTabLink.length) {
			if (!$activeTabLink.hasClass('nav-tab-active')) {
				$activeTabLink.trigger('click');
			}
			return true;
		}
		return false;
	}

	wrap.find('a.nav-tab').on('click', function(e) {
		e.preventDefault();
		var $this = $(this);

		var targetContentID = $this.data('tab-content');
		var newHash = $this.attr('href');

		wrap.find('a.nav-tab').removeClass('nav-tab-active');
		wrap.find('.tab-content-panel').removeClass('active-tab-content').hide();

		$this.addClass('nav-tab-active');
		$('#' + targetContentID).addClass('active-tab-content').show();

		if (window.location.hash !== newHash) {
			if (history.pushState) {
				history.pushState(null, null, newHash);
			} else { // For old browser
				window.location.hash = newHash;
			}
		}
	});

	$(window).on('hashchange', function() {
		activateTabFromHash();
	});

	if (!activateTabFromHash()) {
		if (wrap.find('a.nav-tab.nav-tab-active').length === 0) {
			wrap.find('a.nav-tab:first').trigger('click');
		} else {
			var $alreadyActiveTab = wrap.find('a.nav-tab.nav-tab-active');
			if ($alreadyActiveTab.length) {
				$('#' + $alreadyActiveTab.data('tab-content')).show().addClass('active-tab-content');
			}
		}
	}

	/**
	 * Handles the confirmation dialog for the "Delete All Notification Logs" form.
	 */
	$('#swift-index-delete-logs-form').on('submit', function(e) {
		var confirmMessage;
		if (typeof swiftIndexAdminParams !== 'undefined' && typeof swiftIndexAdminParams.delete_logs_confirm_message !== 'undefined') {
			confirmMessage = swiftIndexAdminParams.delete_logs_confirm_message;
		} else {
			confirmMessage = 'Are you sure you want to delete all notification logs? This action cannot be undone.';
		}

		if (!confirm(confirmMessage)) {
			e.preventDefault();
		}
	});
});
