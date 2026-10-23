/**
 * HamiShop account UI - no jQuery, no framework, no external requests.
 *
 * Progressive enhancement only: login/register and ticket forms work without
 * this file. It adds auth tabs, password visibility, membership tabs, the
 * notification bell behaviour and the local-storage wishlist page.
 */
(function () {
	'use strict';

	var WISH_KEY = 'hsw_wishlist';

	function data() {
		return window.hswAccountData || {};
	}

	function faNum(value) {
		var text = String(value == null ? '' : value);
		if (!data().faDigits) {
			return text;
		}
		return text.replace(/\d/g, function (digit) {
			return '۰۱۲۳۴۵۶۷۸۹'.charAt(Number(digit));
		});
	}

	function wishList() {
		try {
			var value = JSON.parse(localStorage.getItem(WISH_KEY) || '[]');
			return Array.isArray(value) ? value.map(String) : [];
		} catch (error) {
			return [];
		}
	}

	function saveWish(list) {
		try {
			localStorage.setItem(WISH_KEY, JSON.stringify(list));
		} catch (error) {
			/* storage may be disabled */
		}
	}

	function updateCounts() {
		var count = wishList().length;
		document.querySelectorAll('[data-hsw-wish-count]').forEach(function (badge) {
			badge.textContent = count ? faNum(count) : '';
			badge.hidden = !count;
		});
	}

	function setHidden(element, hidden) {
		if (!element) {
			return;
		}
		element.hidden = hidden;
		element.style.display = hidden ? 'none' : '';
	}

	function wishlistEmpty(page) {
		var grid = page.querySelector('[data-hsw-wish-grid]');
		var empty = page.querySelector('[data-hsw-wish-empty]');
		var clear = page.querySelector('[data-hsw-wish-clear]');
		if (grid) {
			grid.innerHTML = '';
		}
		setHidden(empty, false);
		setHidden(clear, true);
	}

	function renderWishlist(page) {
		if (!page || page.getAttribute('data-hsw-ready') === '1') {
			return;
		}
		page.setAttribute('data-hsw-ready', '1');

		var grid = page.querySelector('[data-hsw-wish-grid]');
		var empty = page.querySelector('[data-hsw-wish-empty]');
		var clear = page.querySelector('[data-hsw-wish-clear]');
		var list = wishList();

		updateCounts();

		if (!list.length) {
			wishlistEmpty(page);
			return;
		}

		var rest = data().rest || '/wp-json/hsw/v1';
		var url = rest.replace(/\/$/, '') + '/account/wishlist?ids=' + encodeURIComponent(list.join(','));

		setHidden(clear, false);

		fetch(url, {
			credentials: 'same-origin',
			headers: data().nonce ? { 'X-WP-Nonce': data().nonce } : {}
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('HTTP ' + response.status);
				}
				return response.json();
			})
			.then(function (payload) {
				if (!grid) {
					return;
				}
				var valid = payload && Array.isArray(payload.ids) ? payload.ids.map(String) : list;
				if (valid.length !== list.length) {
					saveWish(valid);
				}
				updateCounts();
				if (!payload || !payload.html || !valid.length) {
					wishlistEmpty(page);
					return;
				}
				grid.innerHTML = payload.html;
				setHidden(empty, true);
				setHidden(clear, false);
			})
			.catch(function () {
				if (!grid) {
					return;
				}
				grid.innerHTML = '<div class="hsw-ac__empty hsw-ac__empty--inline"><span class="hsw-ac__empty-ic">!</span><h3>' + (data().errorText || 'خطا در بارگذاری علاقه‌مندی‌ها') + '</h3><p>' + (data().retryText || 'اتصال اینترنت را بررسی کنید و صفحه را دوباره باز کنید.') + '</p></div>';
				setHidden(clear, false);
			});
	}

	function init(root) {
		var scope = root || document;
		scope.querySelectorAll('[data-hsw-wishlist]').forEach(renderWishlist);
		scope.querySelectorAll('[data-hsw-verify]').forEach(initEmailVerify);
		updateCounts();
	}

	/*
	 * Email confirmation banner for phone-first accounts.
	 *
	 * The whole flow is optional: without JS the banner still tells the customer
	 * what to do and the account-details form still lets them change the address.
	 * With JS it expands into a two-step code form that talks to admin-ajax.
	 */
	function initEmailVerify(box) {
		if (!box || '1' === box.getAttribute('data-hsw-ready')) {
			return;
		}
		box.setAttribute('data-hsw-ready', '1');

		var openBtn = box.querySelector('[data-hsw-verify-open]');
		var form = box.querySelector('[data-hsw-verify-form]');
		var step = box.querySelector('[data-hsw-verify-step]');
		var email = box.querySelector('[data-hsw-verify-email]');
		var code = box.querySelector('[data-hsw-verify-code]');
		var message = box.querySelector('[data-hsw-verify-msg]');
		var sendBtn = box.querySelector('[data-hsw-verify-send]');
		var confirmBtn = box.querySelector('[data-hsw-verify-confirm]');
		var resendBtn = box.querySelector('[data-hsw-verify-resend]');

		function busy(button, on) {
			if (!button) {
				return;
			}
			button.disabled = on;
			button.classList.toggle('is-busy', on);
		}

		function send() {
			var value = (email && email.value ? email.value : '').trim();
			if (!value || value.indexOf('@') === -1) {
				setMessage(message, data().emailInvalid || 'ایمیل معتبر وارد کنید.', 'error');
				if (email) {
					email.focus();
				}
				return;
			}
			busy(sendBtn, true);
			setMessage(message, data().sending || 'در حال ارسال...', '');
			ajax('hsw_email_send', { email: value }, data().emailNonce)
				.then(function (response) {
					busy(sendBtn, false);
					if (!response || !response.success) {
						setMessage(message, (response && response.data && response.data.message) || data().emailFailed, 'error');
						return;
					}
					setMessage(message, (response.data && response.data.message) || data().emailSent, 'success');
					if (step) {
						step.hidden = false;
					}
					if (code) {
						code.focus();
					}
				})
				.catch(function () {
					busy(sendBtn, false);
					setMessage(message, data().emailFailed, 'error');
				});
		}

		function confirm() {
			var value = (code && code.value ? code.value : '').replace(/\D/g, '');
			if (6 !== value.length) {
				setMessage(message, data().codeRequired || 'کد تأیید را وارد کنید.', 'error');
				return;
			}
			busy(confirmBtn, true);
			ajax('hsw_email_verify', { code: value }, data().emailNonce)
				.then(function (response) {
					busy(confirmBtn, false);
					if (!response || !response.success) {
						setMessage(message, (response && response.data && response.data.message) || data().emailFailed, 'error');
						return;
					}
					box.classList.add('is-done');
					setMessage(message, (response.data && response.data.message) || data().emailDone, 'success');
					/* Let the success state be readable, then drop the banner. */
					window.setTimeout(function () {
						box.hidden = true;
					}, 2200);
				})
				.catch(function () {
					busy(confirmBtn, false);
					setMessage(message, data().emailFailed, 'error');
				});
		}

		if (openBtn && form) {
			openBtn.addEventListener('click', function () {
				form.hidden = false;
				openBtn.hidden = true;
				if (email) {
					email.focus();
				}
			});
		}
		if (sendBtn) {
			sendBtn.addEventListener('click', send);
		}
		if (resendBtn) {
			resendBtn.addEventListener('click', send);
		}
		if (confirmBtn) {
			confirmBtn.addEventListener('click', confirm);
		}
		if (email) {
			email.addEventListener('keydown', function (event) {
				if ('Enter' === event.key) {
					event.preventDefault();
					send();
				}
			});
		}
		if (code) {
			code.addEventListener('keydown', function (event) {
				if ('Enter' === event.key) {
					event.preventDefault();
					confirm();
				}
			});
		}
	}

	function switchAuth(tab) {
		var auth = tab.closest('[data-hsw-auth]');
		if (!auth) {
			return;
		}
		var name = tab.getAttribute('data-hsw-auth-tab');
		auth.setAttribute('data-active', name);
		auth.querySelectorAll('[data-hsw-auth-tab]').forEach(function (button) {
			var active = button === tab;
			button.classList.toggle('is-active', active);
			button.setAttribute('aria-selected', active ? 'true' : 'false');
			button.setAttribute('tabindex', active ? '0' : '-1');
		});
		auth.querySelectorAll('[data-hsw-auth-panel]').forEach(function (panel) {
			var active = panel.getAttribute('data-hsw-auth-panel') === name;
			panel.classList.toggle('is-active', active);
			panel.hidden = !active;
		});
	}

	function switchMember(tab) {
		var member = tab.closest('[data-hsw-member]');
		if (!member) {
			return;
		}
		var name = tab.getAttribute('data-hsw-member-tab');
		member.querySelectorAll('[data-hsw-member-tab]').forEach(function (button) {
			var active = button === tab;
			button.classList.toggle('is-active', active);
			button.setAttribute('aria-selected', active ? 'true' : 'false');
		});
		member.querySelectorAll('[data-hsw-member-panel]').forEach(function (panel) {
			var active = panel.getAttribute('data-hsw-member-panel') === name;
			panel.hidden = !active;
		});
	}

	function togglePassword(button) {
		var wrap = button.closest('.hsw-ac-input-wrap');
		var input = wrap ? wrap.querySelector('input') : null;
		if (!input) {
			return;
		}
		var show = input.type === 'password';
		input.type = show ? 'text' : 'password';
		button.classList.toggle('is-visible', show);
		button.setAttribute('aria-pressed', show ? 'true' : 'false');
		button.setAttribute('aria-label', show ? (data().hidePassword || 'پنهان کردن رمز عبور') : (data().showPassword || 'نمایش رمز عبور'));
	}

	function ajax(action, payload, nonce) {
		var body = new URLSearchParams();
		body.append('action', action);
		body.append('nonce', nonce || '');
		Object.keys(payload || {}).forEach(function (key) {
			body.append(key, payload[key] == null ? '' : payload[key]);
		});
		return fetch(data().ajax || '/wp-admin/admin-ajax.php', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (response) {
			return response.json();
		});
	}

	function setMessage(element, message, type, link, linkText) {
		if (!element) {
			return;
		}
		element.textContent = '';
		element.classList.remove('is-error', 'is-success');
		if (type) {
			element.classList.add('is-' + type);
		}
		if (message) {
			var text = document.createElement('span');
			text.textContent = message;
			element.appendChild(text);
		}
		if (link) {
			var anchor = document.createElement('a');
			anchor.href = link;
			anchor.target = '_blank';
			anchor.rel = 'noopener noreferrer';
			anchor.textContent = linkText || (data().openLink || 'باز کردن لینک');
			element.appendChild(document.createTextNode(' '));
			element.appendChild(anchor);
		}
	}

	function activeOtpChannel(box) {
		var active = box.querySelector('[data-hsw-otp-channel].is-active');
		return active ? active.getAttribute('data-hsw-otp-channel') : (box.getAttribute('data-channel') || '');
	}

	function setOtpChannel(box, channel) {
		box.setAttribute('data-channel', channel);
		box.querySelectorAll('[data-hsw-otp-channel]').forEach(function (button) {
			var on = button.getAttribute('data-hsw-otp-channel') === channel;
			button.classList.toggle('is-active', on);
			button.setAttribute('aria-pressed', on ? 'true' : 'false');
		});
		var step = box.querySelector('[data-hsw-otp-code-step]');
		if (step) {
			setHidden(step, true);
		}
		setMessage(box.querySelector('[data-hsw-otp-message]'), '');
	}

	function sendOtp(box) {
		var button = box.querySelector('[data-hsw-otp-send]');
		var message = box.querySelector('[data-hsw-otp-message]');
		var phone = box.querySelector('[data-hsw-otp-phone]');
		var channel = activeOtpChannel(box);
		var context = box.getAttribute('data-context') || 'login';
		if (!phone || !phone.value.trim()) {
			setMessage(message, data().phoneRequired || 'شماره موبایل را وارد کنید.', 'error');
			if (phone) {
				phone.focus();
			}
			return;
		}
		if (!channel) {
			setMessage(message, data().channelRequired || 'روش دریافت کد را انتخاب کنید.', 'error');
			return;
		}
		if (button) {
			button.disabled = true;
		}
		setMessage(message, data().sending || 'در حال ارسال کد...');
		ajax('hsw_otp_send', { phone: phone.value.trim(), channel: channel, context: context }, data().otpNonce)
			.then(function (payload) {
				if (!payload || !payload.success) {
					var error = payload && payload.data ? payload.data : {};
					setMessage(message, error.message || (data().sendError || 'ارسال کد ناموفق بود.'), 'error', error.link, error.link_text);
					if (error.switch_tab) {
						var authRoot = box.closest('[data-hsw-auth]');
						var tabButton = authRoot ? authRoot.querySelector('[data-hsw-auth-tab="' + error.switch_tab + '"]') : null;
						if (tabButton) {
							tabButton.click();
						}
					}
					return;
				}
				var step = box.querySelector('[data-hsw-otp-code-step]');
				setHidden(step, false);
				setMessage(message, payload.data.message || (data().sent || 'کد ارسال شد.'), 'success');
				var code = box.querySelector('[data-hsw-otp-code]');
				if (code) {
					code.value = '';
					code.focus();
				}
			})
			.catch(function () {
				setMessage(message, data().networkError || 'خطا در ارتباط با سرور. دوباره تلاش کنید.', 'error');
			})
			.finally(function () {
				if (button) {
					button.disabled = false;
				}
			});
	}

	function verifyOtp(box) {
		var button = box.querySelector('[data-hsw-otp-verify]');
		var message = box.querySelector('[data-hsw-otp-message]');
		var phone = box.querySelector('[data-hsw-otp-phone]');
		var code = box.querySelector('[data-hsw-otp-code]');
		var name = box.querySelector('[data-hsw-otp-name]');
		var email = box.querySelector('[data-hsw-otp-email]');
		var channel = activeOtpChannel(box);
		var context = box.getAttribute('data-context') || 'login';
		if (!code || !code.value.trim()) {
			setMessage(message, data().codeRequired || 'کد تأیید را وارد کنید.', 'error');
			if (code) {
				code.focus();
			}
			return;
		}
		if (button) {
			button.disabled = true;
		}
		setMessage(message, data().verifying || 'در حال بررسی کد...');
		ajax('hsw_otp_verify', {
			phone: phone ? phone.value.trim() : '',
			channel: channel,
			context: context,
			code: code.value.trim(),
			name: name ? name.value.trim() : '',
			email: email ? email.value.trim() : ''
		}, data().otpNonce)
			.then(function (payload) {
				if (!payload || !payload.success) {
					var error = payload && payload.data ? payload.data : {};
					setMessage(message, error.message || (data().verifyError || 'کد تأیید نامعتبر است.'), 'error');
					if (error.switch_tab) {
						var authRoot = box.closest('[data-hsw-auth]');
						var tabButton = authRoot ? authRoot.querySelector('[data-hsw-auth-tab="' + error.switch_tab + '"]') : null;
						if (tabButton) {
							tabButton.click();
						}
					}
					return;
				}
				setMessage(message, payload.data.message || (data().verified || 'تأیید شد.'), 'success');
				window.location.href = payload.data.redirect || window.location.href;
			})
			.catch(function () {
				setMessage(message, data().networkError || 'خطا در ارتباط با سرور. دوباره تلاش کنید.', 'error');
			})
			.finally(function () {
				if (button) {
					button.disabled = false;
				}
			});
	}

	function send2faSetup(panel) {
		var button = panel.querySelector('[data-hsw-2fa-setup]');
		var message = panel.querySelector('[data-hsw-2fa-message]');
		var active = panel.querySelector('[data-hsw-2fa-channel].is-active');
		var channel = active ? active.getAttribute('data-hsw-2fa-channel') : '';
		if (!channel) {
			setMessage(message, data().channelRequired || 'روش دریافت کد را انتخاب کنید.', 'error');
			return;
		}
		if (button) {
			button.disabled = true;
		}
		setMessage(message, data().sending || 'در حال ارسال کد...');
		ajax('hsw_2fa_setup_send', { channel: channel }, data().twoFactorNonce)
			.then(function (payload) {
				if (!payload || !payload.success) {
					var error = payload && payload.data ? payload.data : {};
					setMessage(message, error.message || (data().sendError || 'ارسال کد ناموفق بود.'), 'error', error.link, error.link_text);
					return;
				}
				var step = panel.querySelector('[data-hsw-2fa-code-step]');
				setHidden(step, false);
				setMessage(message, payload.data.message || (data().sent || 'کد ارسال شد.'), 'success');
				var code = panel.querySelector('[data-hsw-2fa-code]');
				if (code) {
					code.value = '';
					code.focus();
				}
			})
			.catch(function () {
				setMessage(message, data().networkError || 'خطا در ارتباط با سرور. دوباره تلاش کنید.', 'error');
			})
			.finally(function () {
				if (button) {
					button.disabled = false;
				}
			});
	}

	function verify2faSetup(panel) {
		var message = panel.querySelector('[data-hsw-2fa-message]');
		var code = panel.querySelector('[data-hsw-2fa-code]');
		if (!code || !code.value.trim()) {
			setMessage(message, data().codeRequired || 'کد تأیید را وارد کنید.', 'error');
			return;
		}
		ajax('hsw_2fa_setup_verify', { code: code.value.trim() }, data().twoFactorNonce)
			.then(function (payload) {
				if (!payload || !payload.success) {
					var error = payload && payload.data ? payload.data : {};
					setMessage(message, error.message || (data().verifyError || 'کد تأیید نامعتبر است.'), 'error');
					return;
				}
				setMessage(message, payload.data.message || (data().verified || 'تأیید شد.'), 'success');
				window.setTimeout(function () {
					window.location.reload();
				}, 500);
			})
			.catch(function () {
				setMessage(message, data().networkError || 'خطا در ارتباط با سرور. دوباره تلاش کنید.', 'error');
			});
	}

	function verify2faChallenge(challenge) {
		var message = challenge.querySelector('[data-hsw-2fa-message]');
		var code = challenge.querySelector('[data-hsw-2fa-code]');
		var token = challenge.getAttribute('data-token');
		if (!code || !code.value.trim()) {
			setMessage(message, data().codeRequired || 'کد تأیید را وارد کنید.', 'error');
			return;
		}
		ajax('hsw_2fa_verify', { token: token, code: code.value.trim() }, data().twoFactorNonce)
			.then(function (payload) {
				if (!payload || !payload.success) {
					var error = payload && payload.data ? payload.data : {};
					setMessage(message, error.message || (data().verifyError || 'کد تأیید نامعتبر است.'), 'error');
					return;
				}
				setMessage(message, payload.data.message || (data().verified || 'تأیید شد.'), 'success');
				window.location.href = payload.data.redirect || window.location.href;
			})
			.catch(function () {
				setMessage(message, data().networkError || 'خطا در ارتباط با سرور. دوباره تلاش کنید.', 'error');
			});
	}

	function resend2faChallenge(challenge) {
		var message = challenge.querySelector('[data-hsw-2fa-message]');
		ajax('hsw_2fa_resend', { token: challenge.getAttribute('data-token') }, data().twoFactorNonce)
			.then(function (payload) {
				if (!payload || !payload.success) {
					var error = payload && payload.data ? payload.data : {};
					setMessage(message, error.message || (data().sendError || 'ارسال کد ناموفق بود.'), 'error');
					return;
				}
				setMessage(message, payload.data.message || (data().sent || 'کد ارسال شد.'), 'success');
			})
			.catch(function () {
				setMessage(message, data().networkError || 'خطا در ارتباط با سرور. دوباره تلاش کنید.', 'error');
			});
	}

	function removeWish(id, button) {
		var list = wishList().filter(function (item) {
			return String(item) !== String(id);
		});
		saveWish(list);
		var card = button ? button.closest('.hsw-ac__wish') : null;
		if (card) {
			card.remove();
		}
		updateCounts();
		var page = document.querySelector('[data-hsw-wishlist]');
		if (page && !list.length) {
			wishlistEmpty(page);
		}
	}

	document.addEventListener('click', function (event) {
		var otpChannel = event.target.closest('[data-hsw-otp-channel]');
		if (otpChannel) {
			event.preventDefault();
			var otpBox = otpChannel.closest('[data-hsw-otp]');
			if (otpBox) {
				setOtpChannel(otpBox, otpChannel.getAttribute('data-hsw-otp-channel'));
			}
			return;
		}

		var otpSend = event.target.closest('[data-hsw-otp-send]');
		if (otpSend) {
			event.preventDefault();
			var sendBox = otpSend.closest('[data-hsw-otp]');
			if (sendBox) {
				sendOtp(sendBox);
			}
			return;
		}

		var otpResend = event.target.closest('[data-hsw-otp-resend]');
		if (otpResend) {
			event.preventDefault();
			var resendBox = otpResend.closest('[data-hsw-otp]');
			if (resendBox) {
				sendOtp(resendBox);
			}
			return;
		}

		var otpVerify = event.target.closest('[data-hsw-otp-verify]');
		if (otpVerify) {
			event.preventDefault();
			var verifyBox = otpVerify.closest('[data-hsw-otp]');
			if (verifyBox) {
				verifyOtp(verifyBox);
			}
			return;
		}

		var challengeVerify = event.target.closest('[data-hsw-2fa-challenge] [data-hsw-2fa-verify]');
		if (challengeVerify) {
			event.preventDefault();
			verify2faChallenge(challengeVerify.closest('[data-hsw-2fa-challenge]'));
			return;
		}

		var challengeResend = event.target.closest('[data-hsw-2fa-challenge] [data-hsw-2fa-resend]');
		if (challengeResend) {
			event.preventDefault();
			resend2faChallenge(challengeResend.closest('[data-hsw-2fa-challenge]'));
			return;
		}

		var setupChannel = event.target.closest('[data-hsw-2fa-channel]');
		if (setupChannel) {
			event.preventDefault();
			var setupPanel = setupChannel.closest('[data-hsw-2fa]');
			var setupChannelName = setupChannel.getAttribute('data-hsw-2fa-channel');
			if (setupPanel) {
				setupPanel.setAttribute('data-channel', setupChannelName);
				setupPanel.querySelectorAll('[data-hsw-2fa-channel]').forEach(function (button) {
					var on = button.getAttribute('data-hsw-2fa-channel') === setupChannelName;
					button.classList.toggle('is-active', on);
					button.setAttribute('aria-pressed', on ? 'true' : 'false');
				});
				setHidden(setupPanel.querySelector('[data-hsw-2fa-code-step]'), true);
				setMessage(setupPanel.querySelector('[data-hsw-2fa-message]'), '');
			}
			return;
		}

		var setupSend = event.target.closest('[data-hsw-2fa-setup]');
		if (setupSend) {
			event.preventDefault();
			var setupBox = setupSend.closest('[data-hsw-2fa]');
			if (setupBox) {
				send2faSetup(setupBox);
			}
			return;
		}

		var setupResend = event.target.closest('[data-hsw-2fa-setup-resend]');
		if (setupResend) {
			event.preventDefault();
			var resendPanel = setupResend.closest('[data-hsw-2fa]');
			if (resendPanel) {
				send2faSetup(resendPanel);
			}
			return;
		}

		var setupVerify = event.target.closest('[data-hsw-2fa-setup-verify], [data-hsw-2fa] [data-hsw-2fa-verify]');
		if (setupVerify) {
			event.preventDefault();
			verify2faSetup(setupVerify.closest('[data-hsw-2fa]'));
			return;
		}

		var setupDisable = event.target.closest('[data-hsw-2fa-disable]');
		if (setupDisable) {
			event.preventDefault();
			var disablePanel = setupDisable.closest('[data-hsw-2fa]');
			if (disablePanel) {
				var disableMessage = disablePanel.querySelector('[data-hsw-2fa-message]');
				ajax('hsw_2fa_disable', {}, data().twoFactorNonce)
					.then(function (payload) {
						if (!payload || !payload.success) {
							setMessage(disableMessage, payload && payload.data ? payload.data.message : (data().verifyError || 'عملیات ناموفق بود.'), 'error');
							return;
						}
						setMessage(disableMessage, payload.data.message, 'success');
						window.location.reload();
					})
					.catch(function () {
						setMessage(disableMessage, data().networkError || 'خطا در ارتباط با سرور.', 'error');
					});
			}
			return;
		}

		var authTab = event.target.closest('[data-hsw-auth-tab]');
		if (authTab) {
			event.preventDefault();
			switchAuth(authTab);
			return;
		}

		var memberTab = event.target.closest('[data-hsw-member-tab]');
		if (memberTab) {
			event.preventDefault();
			switchMember(memberTab);
			return;
		}

		var passwordToggle = event.target.closest('[data-hsw-pass-toggle]');
		if (passwordToggle) {
			event.preventDefault();
			togglePassword(passwordToggle);
			return;
		}

		var remove = event.target.closest('[data-hsw-wish-remove]');
		if (remove) {
			event.preventDefault();
			removeWish(remove.getAttribute('data-hsw-wish-remove'), remove);
			return;
		}

		var clear = event.target.closest('[data-hsw-wish-clear]');
		if (clear) {
			event.preventDefault();
			if (window.confirm(data().clearConfirm || 'همهٔ محصولات از علاقه‌مندی‌ها حذف شوند؟')) {
				saveWish([]);
				updateCounts();
				var page = clear.closest('[data-hsw-wishlist]');
				if (page) {
					wishlistEmpty(page);
				}
			}
			return;
		}

		document.querySelectorAll('.hsw-ac__bell[open]').forEach(function (bell) {
			if (!bell.contains(event.target)) {
				bell.removeAttribute('open');
			}
		});
	});

	/*
	 * Fallback for browsers without :has(): the hero has to stop clipping while
	 * the bell dropdown is open, otherwise the panel is cut off at the hero edge.
	 */
	document.querySelectorAll('.hsw-ac__bell').forEach(function (bell) {
		var hero = bell.closest('.hsw-ac__hero');
		if (!hero) {
			return;
		}
		var sync = function () {
			hero.classList.toggle('is-bell-open', bell.hasAttribute('open'));
		};
		bell.addEventListener('toggle', sync);
		sync();
	});

	document.addEventListener('keydown', function (event) {
		if ('Escape' === event.key) {
			document.querySelectorAll('.hsw-ac__bell[open]').forEach(function (bell) {
				bell.removeAttribute('open');
				var summary = bell.querySelector('summary');
				if (summary) {
					summary.focus();
				}
			});
		}
	});

	window.addEventListener('storage', function (event) {
		if (event.key && event.key !== WISH_KEY) {
			return;
		}
		updateCounts();
		var page = document.querySelector('[data-hsw-wishlist]');
		if (page) {
			page.removeAttribute('data-hsw-ready');
			renderWishlist(page);
		}
	});

	function boot() {
		init(document);
		if (window.elementorFrontend && window.elementorFrontend.hooks) {
			window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function () {
				init(document);
			});
		}
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
