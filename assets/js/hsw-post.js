/**
 * HamiShop — single post page interactions.
 *
 * Vanilla JS, no dependencies. Everything is progressive: with JS off the
 * article, TOC links, comments and the comment form all still work.
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
				var target = id ? document.getElementById(id) : null;
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
			var label = button.querySelector('.hsw-pp__docklabel');
			var base = label ? parseInt(label.textContent, 10) || 0 : 0;
			var liked = false;

			try {
				liked = window.localStorage.getItem(key) === '1';
			} catch (e) {}

			var paint = function (on) {
				button.classList.toggle('is-on', on);
				button.setAttribute('aria-pressed', on ? 'true' : 'false');
				if (label) {
					label.textContent = String(base + (on ? 1 : 0));
				}
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
	});
})();
