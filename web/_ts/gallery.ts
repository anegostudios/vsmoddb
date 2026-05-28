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

function initGallery(): void {
	const wrap = document.querySelector('.imageslideshow') as HTMLElement | null;
	if (!wrap) return;
	const stage = wrap.querySelector('.gallery-stage') as HTMLElement;
	const slides = stage.children;
	if (slides.length === 0) return;

	// Escape key exits fullscreen
	document.addEventListener('keydown', (e: KeyboardEvent) => {
		if (e.key === 'Escape') {
			if (wrap.classList.contains('is-fullscreen')) toggleGalleryFullscreen(wrap);
		}
	});

	// Fullscreen button
	const full = wrap.querySelector('.gallery-fullscreen') as HTMLElement;
	full.onclick = toggleGalleryFullscreen.bind(null, wrap);

	// Prevent inline image clicks from opening new tab
	stage.addEventListener('click', function(e: MouseEvent) {
		const link = (e.target as HTMLElement).closest('a');
		if (link) e.preventDefault();
	});

	if (slides.length < 2) return;

	const prev = wrap.querySelector('.gallery-arr.prev') as HTMLElement | null;
	const next = wrap.querySelector('.gallery-arr.next') as HTMLElement | null;
	let current = 0;
	let userInteracted = false;

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

	// Drag-to-pan
	let dragStartX = 0;
	let dragScrollLeft = 0;
	let isDragging = false;

	stage.addEventListener('mousedown', (e: MouseEvent) => {
		isDragging = true;
		dragStartX = e.pageX;
		dragScrollLeft = stage.scrollLeft;
		stage.style.scrollSnapType = 'none';
		stage.style.scrollBehavior = 'auto';
		stage.style.cursor = 'grabbing';
	});

	document.addEventListener('mousemove', (e: MouseEvent) => {
		if (!isDragging) return;
		e.preventDefault();
		stage.scrollLeft = dragScrollLeft - (e.pageX - dragStartX);
	});

	document.addEventListener('mouseup', () => {
		if (!isDragging) return;
		isDragging = false;
		userInteracted = true;
		stage.style.scrollSnapType = '';
		stage.style.scrollBehavior = '';
		stage.style.cursor = '';
		// Snap to nearest slide
		const idx = Math.round(stage.scrollLeft / stage.offsetWidth);
		goTo(Math.max(0, Math.min(idx, slides.length - 1)));
	});

	// Horizontal scroll (deltaX) navigates one slide at a time
	let wheelLock = false;
	stage.addEventListener('wheel', (e: WheelEvent) => {
		if (!e.deltaX || Math.abs(e.deltaY) >= Math.abs(e.deltaX)) return;
		e.preventDefault();
		userInteracted = true;
		if (wheelLock) return;
		wheelLock = true;
		const direction = e.deltaX > 0 ? 1 : -1;
		const target = current + direction;
		if (target >= 0 && target < slides.length) goTo(target);
		setTimeout(() => { wheelLock = false; }, 300);
	});

	// Sync on scroll end
	stage.addEventListener('scrollend', () => {
		userInteracted = true;
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
