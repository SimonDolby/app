/* global UserLoginFacebook:true, UserLoginAjaxForm:true */
require(['jquery'], function($){
	'use strict';
	var $entryPoint, $userLoginDropdown, loginAjaxForm = false;

	function openMenu() {
		$entryPoint.addClass('active');
		window.transparentOut.show();

		if (!loginAjaxForm) {
			loginAjaxForm = new UserLoginAjaxForm($entryPoint);
			UserLoginFacebook.init(UserLoginFacebook.origins.DROPDOWN);
		}
	}

	function closeMenu() {
		$entryPoint.removeClass('active');
		window.transparentOut.hide();
	}

	function maybeCloseMenu() {
		var id = document.activeElement.id;
		if ( !( id === 'usernameInput' || id === 'passwordInput' ) ) {
			closeMenu();
		}
	}

	$(function(){
		window.transparentOut.bindClick(closeMenu);

		$entryPoint = $('#AccountNavigation');
		$entryPoint.on('click touchstart', '.ajaxLogin', function(ev) {
			ev.preventDefault();
			ev.stopImmediatePropagation();

			if ( $entryPoint.hasClass('active') ) {
				if ( !!wgUserName ) {
					window.location = $(this).attr('href');
				} else {
					closeMenu();
				}
			} else {
				openMenu();
			}

		});

		$userLoginDropdown = $('#UserLoginDropdown');

		if (!window.touchstart) {
			window.delayedHover(
				$entryPoint.get(0),
				{
					checkInterval: 100,
					maxActivationDistance: 20,
					onActivate: openMenu,
					onDeactivate: ($userLoginDropdown.length ? maybeCloseMenu : closeMenu),
					activateOnClick: false
				}
			);
		}
	});
});
