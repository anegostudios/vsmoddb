function toggleGalleryFullscreen(wrap) {
	var stage = wrap.querySelector('.gallery-stage');
	if (wrap.classList.contains('is-fullscreen')) {
		stage.style.scrollBehavior = 'auto';
		wrap.classList.remove('is-fullscreen');
		document.body.style.overflow = '';
		stage.style.width = wrap.style.getPropertyValue('--gallery-w');
		stage.style.height = wrap.style.getPropertyValue('--gallery-h');
		stage.scrollLeft = stage.scrollLeft;
		setTimeout(function() { stage.style.scrollBehavior = ''; }, 50);
	} else {
		stage.style.scrollBehavior = 'auto';
		wrap.classList.add('is-fullscreen');
		document.body.style.overflow = 'hidden';
		stage.style.width = '100%';
		stage.style.height = '100%';
		stage.scrollLeft = stage.scrollLeft;
		setTimeout(function() { stage.style.scrollBehavior = ''; }, 50);
	}
}

document.addEventListener('keydown', function(e) {
	if (e.key === 'Escape') {
		var wrap = document.querySelector('.imageslideshow.is-fullscreen');
		if (wrap) toggleGalleryFullscreen(wrap);
	}
});

function initGallery() {
	var wrap = document.querySelector('.imageslideshow');
	if (!wrap) return;
	var stage = wrap.querySelector('.gallery-stage');
	var slides = stage.children;
	if (slides.length === 0) return;

	// Fullscreen button
	var full = wrap.querySelector('.gallery-fullscreen');
	full.onclick = toggleGalleryFullscreen.bind(null, wrap);

	var prev = wrap.querySelector('.gallery-arr.prev');
	var next = wrap.querySelector('.gallery-arr.next');
	var current = 0;
	var userInteracted = false;

	// In fullscreen, prevent image links from opening in new tab
	stage.addEventListener('click', function(e) {
		if (wrap.classList.contains('is-fullscreen')) {
			var link = e.target.closest('a');
			if (link) e.preventDefault();
		}
	});

	if (slides.length < 2) return;

	// Arrow navigation
	var border = wrap.querySelector('.gallery-thumb-border');

	function updateArrows() {
		if (prev) prev.style.visibility = current === 0 ? 'hidden' : '';
		if (next) next.style.visibility = current === slides.length - 1 ? 'hidden' : '';
	}

	function updateBorder() {
		if (border) {
			var thumbWidth = 64 + 2;
			border.style.transform = 'translate3d(' + (current * thumbWidth) + 'px, 0, 0)';
		}
	}

	function goTo(idx) {
		current = idx;
		updateBorder();
		updateArrows();
		slides[idx].scrollIntoView({behavior: 'smooth', block: 'nearest', inline: 'start'});
	}

	prev.onclick = function() { userInteracted = true; if (current > 0) goTo(current - 1); };
	next.onclick = function() { userInteracted = true; if (current < slides.length - 1) goTo(current + 1); };

	// Thumbnail click
	var shaft = wrap.querySelector('.gallery-nav-shaft');
	if (shaft) shaft.onclick = function(e) {
		var btn = e.target.closest('.gallery-thumb');
		if (!btn) return;
		userInteracted = true;
		goTo(parseInt(btn.dataset.i, 10));
	};

	// Sync on scroll end
	stage.addEventListener('scrollend', function() {
		var idx = Math.round(stage.scrollLeft / stage.offsetWidth);
		if (idx !== current) { current = idx; updateBorder(); updateArrows(); }
	});

	// Auto-play: advance every 5s until user interacts
	var autoplay = setInterval(function() {
		if (userInteracted) { clearInterval(autoplay); return; }
		goTo((current + 1) % slides.length);
	}, 5000);

	// Initial state
	updateArrows();
}
