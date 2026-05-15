function toggleGalleryFullscreen(wrap: HTMLElement): void {
	const stage = wrap.querySelector('.gallery-stage') as HTMLElement;
	if (wrap.classList.contains('is-fullscreen')) {
		stage.style.scrollBehavior = 'auto';
		wrap.classList.remove('is-fullscreen');
		document.body.style.overflow = '';
		stage.style.width = wrap.style.getPropertyValue('--gallery-w');
		stage.style.height = wrap.style.getPropertyValue('--gallery-h');
		stage.scrollLeft = stage.scrollLeft;
		setTimeout(() => { stage.style.scrollBehavior = ''; }, 50);
	} else {
		stage.style.scrollBehavior = 'auto';
		wrap.classList.add('is-fullscreen');
		document.body.style.overflow = 'hidden';
		stage.style.width = '100%';
		stage.style.height = '100%';
		stage.scrollLeft = stage.scrollLeft;
		setTimeout(() => { stage.style.scrollBehavior = ''; }, 50);
	}
}

document.addEventListener('keydown', (e: KeyboardEvent) => {
	if (e.key === 'Escape') {
		const wrap = document.querySelector('.imageslideshow.is-fullscreen') as HTMLElement | null;
		if (wrap) toggleGalleryFullscreen(wrap);
	}
});

function initGallery(): void {
	const wrap = document.querySelector('.imageslideshow') as HTMLElement | null;
	if (!wrap) return;
	const stage = wrap.querySelector('.gallery-stage') as HTMLElement;
	const slides = stage.children;
	if (slides.length === 0) return;

	// Fullscreen button
	const full = wrap.querySelector('.gallery-fullscreen') as HTMLElement;
	full.onclick = toggleGalleryFullscreen.bind(null, wrap);

	const prev = wrap.querySelector('.gallery-arr.prev') as HTMLElement | null;
	const next = wrap.querySelector('.gallery-arr.next') as HTMLElement | null;
	let current = 0;
	let userInteracted = false;

	// In fullscreen, prevent image links from opening in new tab
	stage.addEventListener('click', function(e: MouseEvent) {
		if (wrap.classList.contains('is-fullscreen')) {
			const link = (e.target as HTMLElement).closest('a');
			if (link) e.preventDefault();
		}
	});

	if (slides.length < 2) return;

	// Arrow navigation
	const border = wrap.querySelector('.gallery-thumb-border') as HTMLElement | null;

	function updateArrows(): void {
		if (prev) prev.style.visibility = current === 0 ? 'hidden' : '';
		if (next) next.style.visibility = current === slides.length - 1 ? 'hidden' : '';
	}

	function updateBorder(): void {
		if (border) {
			const thumbWidth = 64 + 2;
			border.style.transform = 'translate3d(' + (current * thumbWidth) + 'px, 0, 0)';
		}
	}

	function goTo(idx: number): void {
		current = idx;
		updateBorder();
		updateArrows();
		slides[idx].scrollIntoView({behavior: 'smooth', block: 'nearest', inline: 'start'});
	}

	prev!.onclick = () => { userInteracted = true; if (current > 0) goTo(current - 1); };
	next!.onclick = () => { userInteracted = true; if (current < slides.length - 1) goTo(current + 1); };

	// Thumbnail click
	const shaft = wrap.querySelector('.gallery-nav-shaft') as HTMLElement | null;
	if (shaft) shaft.onclick = (e: MouseEvent) => {
		const btn = (e.target as HTMLElement).closest('.gallery-thumb') as HTMLElement | null;
		if (!btn) return;
		userInteracted = true;
		goTo(parseInt(btn.dataset.i!, 10));
	};

	// Sync on scroll end
	stage.addEventListener('scrollend', () => {
		const idx = Math.round(stage.scrollLeft / stage.offsetWidth);
		if (idx !== current) { current = idx; updateBorder(); updateArrows(); }
	});

	// Auto-play: advance every 5s until user interacts
	const autoplay = setInterval(() => {
		if (userInteracted) { clearInterval(autoplay); return; }
		goTo((current + 1) % slides.length);
	}, 5000);

	// Initial state
	updateArrows();
}
