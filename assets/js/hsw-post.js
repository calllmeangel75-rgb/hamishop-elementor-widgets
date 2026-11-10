/**
 * HamiShop — single post page interactions.
 *
 * Vanilla JS, no dependencies. Everything is progressive: with JS off the
 * article and native fragment links still work without JavaScript; button-only
 * TOC entries require JavaScript.
 *
 *  - reading progress bar
 *  - active state for TOC links (IntersectionObserver)
 *  - font-size toggle
 *  - bookmark (localStorage) + share (Web Share API, clipboard fallback)
 *  - like button + comment form jump
 */
(function () {
	'use strict';

	var roots = document.querySelectorAll('[data-hsw-pp]');
	if (!roots.length) {
		return;
	}

	var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* A TOC entry is either a fragment link or a button with data-target. */
	var tocTargetId = function (el) {
		var direct = el.getAttribute('data-target');
		if (direct) {
			return direct;
		}
		return (el.getAttribute('href') || '').replace(/^#/, '');
	};

	Array.prototype.forEach.call(roots, function (root) {
		var postId = root.getAttribute('data-post-id') || '';
		var body = root.querySelector('[data-hsw-pp-body]');
		var progress = root.querySelector('[data-hsw-pp-progress]');

		/* -----------------------------------------------------------------
		 * Reading progress
		 * -------------------------------------------------------------- */
		if (progress && body) {
			var ticking = false;

			var updateProgress = function () {
				ticking = false;
				var rect = body.getBoundingClientRect();
				var viewport = window.innerHeight || 0;
				var total = rect.height - viewport;
				var passed = -rect.top;
				/*
				 * Short articles (body shorter than the viewport) used to report
				 * 100% from the very top, so the bar looked like a solid line.
				 * They now fall back to document scroll, which still gives the
				 * reader a real position.
				 */
				var percent;
				if ( total > 0 ) {
					percent = ( passed / total ) * 100;
				} else {
					var doc = document.documentElement;
					var max = ( doc.scrollHeight || 0 ) - viewport;
					percent = max > 0 ? ( ( window.pageYOffset || doc.scrollTop || 0 ) / max ) * 100 : 0;
				}

				percent = Math.max(0, Math.min(100, percent));
				progress.style.width = percent.toFixed(1) + '%';
			};

			var onScroll = function () {
				if (ticking) {
					return;
				}
				ticking = true;
				window.requestAnimationFrame(updateProgress);
			};

			window.addEventListener('scroll', onScroll, { passive: true });
			window.addEventListener('resize', onScroll);
			updateProgress();
		}

		/* -----------------------------------------------------------------
		 * TOC active link
		 * -------------------------------------------------------------- */
		var tocLinks = root.querySelectorAll('[data-hsw-pp-toclink]');
		if (tocLinks.length && 'IntersectionObserver' in window && body) {
			var byId = {};
			Array.prototype.forEach.call(tocLinks, function (link) {
				var id = tocTargetId(link);
				if (!id) {
					return;
				}
				byId[id] = byId[id] || [];
				byId[id].push(link);
			});

			var headings = body.querySelectorAll('h2[id], h3[id]');

			var observer = new IntersectionObserver(
				function (entries) {
					entries.forEach(function (entry) {
						if (!entry.isIntersecting) {
							return;
						}
						var id = entry.target.id;
						Array.prototype.forEach.call(tocLinks, function (link) {
							link.classList.remove('is-active');
						});
						if (byId[id]) {
							byId[id].forEach(function (link) {
								link.classList.add('is-active');
							});
						}
					});
				},
				{ rootMargin: '-80px 0px -65% 0px', threshold: 0 }
			);

			Array.prototype.forEach.call(headings, function (heading) {
				observer.observe(heading);
			});
		}

		/* Smooth scroll for TOC links (respects reduced motion). */
		Array.prototype.forEach.call(tocLinks, function (link) {
			link.addEventListener('click', function (event) {
				var id = tocTargetId(link);
				// Restrict lookup to this article; another widget can reuse an ID.
				var target = null;
				if (id && body) {
					Array.prototype.some.call(body.querySelectorAll('h2[id], h3[id]'), function (heading) {
						if (heading.id !== id) { return false; }
						target = heading;
						return true;
					});
				}
				if (!target) {
					return;
				}
				event.preventDefault();
				target.scrollIntoView({
					behavior: reduceMotion ? 'auto' : 'smooth',
					block: 'start',
				});

				/*
				 * Only fragment links write the hash. In «بدون لینک لنگر» mode
				 * the entry is a <button>, so the URL never changes and there is
				 * no `…/#heading` address for Google to index.
				 */
				var isAnchor = 'A' === link.tagName && link.getAttribute('href');
				if (isAnchor && window.history && window.history.replaceState) {
					window.history.replaceState(null, '', '#' + id);
				}
			});
		});

		/* -----------------------------------------------------------------
		 * Font size toggle
		 * -------------------------------------------------------------- */
		Array.prototype.forEach.call(root.querySelectorAll('[data-hsw-pp-font]'), function (button) {
			button.addEventListener('click', function () {
				if (!body) {
					return;
				}
				var large = body.classList.toggle('is-large');
				button.classList.toggle('is-on', large);
				button.setAttribute('aria-pressed', large ? 'true' : 'false');
				try {
					window.localStorage.setItem('hswPpLarge', large ? '1' : '0');
				} catch (e) {}
			});
		});

		try {
			if (body && window.localStorage.getItem('hswPpLarge') === '1') {
				body.classList.add('is-large');
				Array.prototype.forEach.call(root.querySelectorAll('[data-hsw-pp-font]'), function (button) {
					button.classList.add('is-on');
					button.setAttribute('aria-pressed', 'true');
				});
			}
		} catch (e) {}

		/* -----------------------------------------------------------------
		 * Bookmark (per post, local only)
		 * -------------------------------------------------------------- */
		var bookmarkKey = 'hswPpBookmark:' + postId;
		var bookmarkButtons = root.querySelectorAll('[data-hsw-pp-bookmark]');

		var paintBookmark = function (on) {
			Array.prototype.forEach.call(bookmarkButtons, function (button) {
				button.classList.toggle('is-on', on);
				button.setAttribute('aria-pressed', on ? 'true' : 'false');
				var icon = button.querySelector('.hsw-ic');
				if (icon) {
					window.hswSetIcon(icon, on ? 'bookmark_added' : 'bookmark');
					window.hswFill(icon, on);
				}
			});
		};

		if (bookmarkButtons.length) {
			var saved = false;
			try {
				saved = window.localStorage.getItem(bookmarkKey) === '1';
			} catch (e) {}
			paintBookmark(saved);

			Array.prototype.forEach.call(bookmarkButtons, function (button) {
				button.addEventListener('click', function () {
					var on = button.classList.contains('is-on') ? false : true;
					paintBookmark(on);
					try {
						if (on) {
							window.localStorage.setItem(bookmarkKey, '1');
						} else {
							window.localStorage.removeItem(bookmarkKey);
						}
					} catch (e) {}
				});
			});
		}

		/* -----------------------------------------------------------------
		 * Share
		 * -------------------------------------------------------------- */
		Array.prototype.forEach.call(root.querySelectorAll('[data-hsw-pp-share]'), function (button) {
			button.addEventListener('click', function () {
				var title = document.title;
				var url = window.location.href;
				var heading = root.querySelector('.hsw-pp__title');
				if (heading) {
					title = heading.textContent.trim() || title;
				}

				if (navigator.share) {
					navigator.share({ title: title, url: url }).catch(function () {});
					return;
				}

				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(url).then(function () {
						flash(button);
					}).catch(function () {});
				}
			});
		});

		var flash = function (button) {
			button.classList.add('is-on');
			window.setTimeout(function () {
				button.classList.remove('is-on');
			}, 1400);
		};

		/* -----------------------------------------------------------------
		 * Like + comment jump
		 * -------------------------------------------------------------- */
		Array.prototype.forEach.call(root.querySelectorAll('[data-hsw-pp-like]'), function (button) {
			var key = 'hswPpLike:' + postId;
			var liked = false;

			try {
				liked = window.localStorage.getItem(key) === '1';
			} catch (e) {}

			var paint = function (on) {
				button.classList.toggle('is-on', on);
				button.setAttribute('aria-pressed', on ? 'true' : 'false');
			};

			paint(liked);

			button.addEventListener('click', function () {
				var on = !button.classList.contains('is-on');
				paint(on);
				try {
					if (on) {
						window.localStorage.setItem(key, '1');
					} else {
						window.localStorage.removeItem(key);
					}
				} catch (e) {}
			});
		});

		Array.prototype.forEach.call(root.querySelectorAll('[data-hsw-pp-commenttoggle]'), function (button) {
			button.addEventListener('click', function () {
				var form = root.querySelector('.hsw-pp__form');
				if (!form) {
					return;
				}
				form.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
				var field = form.querySelector('#comment');
				if (field) {
					window.setTimeout(function () {
						field.focus({ preventScroll: true });
					}, reduceMotion ? 0 : 380);
				}
			});
		});

		/* -----------------------------------------------------------------
		 * AJAX comment submission (bypasses 403 WAF blocks on wp-comments-post.php)
		 * -------------------------------------------------------------- */
		Array.prototype.forEach.call(root.querySelectorAll('.hsw-pp__form'), function (form) {
			form.addEventListener('submit', function (event) {
				if (!window.fetch || !window.FormData) {
					return;
				}
				event.preventDefault();
				if (form.classList.contains('is-busy')) {
					return;
				}

				var submit = form.querySelector('.hsw-pp__submit, [type="submit"]');
				var origLabel = submit ? (submit.value || submit.textContent || '') : '';
				var msgBox = form.querySelector('.hsw-pp__form-msg');
				if (!msgBox) {
					msgBox = document.createElement('p');
					msgBox.className = 'hsw-pp__form-msg';
					msgBox.setAttribute('role', 'status');
					form.insertBefore(msgBox, form.firstChild);
				}
				msgBox.className = 'hsw-pp__form-msg';
				msgBox.textContent = '';
				msgBox.hidden = true;

				form.classList.add('is-busy');
				if (submit) {
					submit.disabled = true;
					if ('INPUT' === submit.tagName) {
						submit.value = 'در حال ارسال...';
					} else {
						submit.textContent = 'در حال ارسال...';
					}
				}

				var fd = new FormData(form);
				fd.set('action', 'hsw_submit_comment');
				fd.set('hsw_ajax', '1');
				var ajaxUrl = form.getAttribute('data-hsw-ajax-url') || root.getAttribute('data-ajax-url') || '/wp-admin/admin-ajax.php';

				window.fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: fd
				})
					.then(function (r) {
						return r.json();
					})
					.then(function (res) {
						var ok = !!(res && res.success && res.data);
						var text = (res && res.data && res.data.message) ? res.data.message : (ok ? 'دیدگاه شما با موفقیت ثبت شد.' : 'ثبت دیدگاه انجام نشد. لطفاً دوباره تلاش کنید.');
						msgBox.className = 'hsw-pp__form-msg ' + (ok ? 'is-ok' : 'is-err');
						msgBox.textContent = text;
						msgBox.hidden = false;

						if (ok) {
							var textarea = form.querySelector('textarea[name="comment"]');
							if (textarea) {
								textarea.value = '';
							}
							if (res.data.approved) {
								var sec = root.querySelector('.hsw-pp__comments');
								if (sec) {
									var emptyNote = sec.querySelector('.hsw-pp__noComments');
									if (emptyNote) {
										emptyNote.remove();
									}
									var list = sec.querySelector('.hsw-pp__commentlist');
									if (!list) {
										list = document.createElement('div');
										list.className = 'hsw-pp__commentlist';
										var head = sec.querySelector('.hsw-pp__sechead');
										if (head && head.nextSibling) {
											sec.insertBefore(list, head.nextSibling);
										} else {
											sec.insertBefore(list, sec.firstChild);
										}
									}
									var item = document.createElement('div');
									item.className = 'hsw-pp__comment';
									var headEl = document.createElement('div');
									headEl.className = 'hsw-pp__commenthead';
									var whoEl = document.createElement('div');
									whoEl.className = 'hsw-pp__commentwho';
									var metaWrap = document.createElement('div');
									var nameEl = document.createElement('span');
									nameEl.className = 'hsw-pp__commentname';
									nameEl.textContent = res.data.author || '';
									var dateEl = document.createElement('span');
									dateEl.className = 'hsw-pp__commentdate';
									dateEl.textContent = res.data.date || '';
									metaWrap.appendChild(nameEl);
									metaWrap.appendChild(dateEl);
									whoEl.appendChild(metaWrap);
									headEl.appendChild(whoEl);
									var bodyEl = document.createElement('div');
									bodyEl.className = 'hsw-pp__commentbody';
									var pEl = document.createElement('p');
									pEl.textContent = res.data.text || '';
									bodyEl.appendChild(pEl);
									item.appendChild(headEl);
									item.appendChild(bodyEl);
									list.appendChild(item);
								}
							}
						}
					})
					.catch(function () {
						msgBox.className = 'hsw-pp__form-msg is-err';
						msgBox.textContent = 'خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.';
						msgBox.hidden = false;
					})
					.finally(function () {
						form.classList.remove('is-busy');
						if (submit) {
							submit.disabled = false;
							if ('INPUT' === submit.tagName) {
								submit.value = origLabel;
							} else {
								submit.textContent = origLabel;
							}
						}
					});
			});
		});
	});
})();
