function initGallery() : void
{
	const galleryWrapperEl = document.getElementsByClassName('gallery')[0] as HTMLElement;
	if(!galleryWrapperEl) return;

	const stageEl = galleryWrapperEl.getElementsByClassName('stage')[0] as HTMLElement;

	const slidesEls = stageEl.children;
	if(slidesEls.length === 0) return;

	function toggleGalleryFullscreen() : void
	{
		if(!galleryWrapperEl.classList.contains('is-fullscreen')) {
			stageEl.style.scrollBehavior = 'instant';
			galleryWrapperEl.classList.add('is-fullscreen');

			document.body.style.overflow = 'hidden';
			stageEl.scrollLeft = stageEl.scrollLeft;
			setTimeout(() => { stageEl.style.scrollBehavior = ''; }, 50);

			history.pushState(null, null, document.URL); // Push empty state whos pop event we can catch to prevent the user from accidentally navigating away in fullscreen by wanting to go back from fullscreen mode.
		}
		else {
			stageEl.style.scrollBehavior = 'instant';
			galleryWrapperEl.classList.remove('is-fullscreen');

			document.body.style.overflow = '';
			stageEl.scrollLeft = stageEl.scrollLeft;
			setTimeout(() => { stageEl.style.scrollBehavior = ''; }, 50);

			history.back(); // Clean up the empty state object so navigation works normally again.
		}
	}

	window.addEventListener('popstate', (e) => {
		if(!galleryWrapperEl.classList.contains('is-fullscreen')) return;

		history.pushState(null, null, document.URL); // Cooked way to prevent the back button form actually navigating in fullscreen.
		toggleGalleryFullscreen();
	})

	let current = 0;

	// Escape key exits fullscreen
	document.addEventListener('keydown', (e : KeyboardEvent) => {
		switch(e.key) {
			case 'Escape':
				if(galleryWrapperEl.classList.contains('is-fullscreen'))
					toggleGalleryFullscreen();
				break;

			case 'ArrowLeft':
				if(galleryWrapperEl.classList.contains('is-fullscreen'))
					if(current > 0) goTo(current - 1);
				break;

			case 'ArrowRight':
				if(galleryWrapperEl.classList.contains('is-fullscreen'))
					if(current < slidesEls.length - 1) goTo(current + 1);
				break;
		}
	});

	let userInteracted = false;

	galleryWrapperEl.getElementsByClassName('fullscreen')[0]
		.addEventListener('click', () => {
			userInteracted = true;
			toggleGalleryFullscreen();
		})

	if(slidesEls.length < 2) return;

	const viewportEl = galleryWrapperEl.getElementsByClassName('viewport')[0] as HTMLElement;

	const prevButtonEl = galleryWrapperEl.getElementsByClassName('prev')[0] as HTMLElement;
	const nextButtonEl = galleryWrapperEl.getElementsByClassName('next')[0] as HTMLElement;
	const navStripEl = galleryWrapperEl.querySelector('nav')!;
	const selectionIndicatorEl = navStripEl.getElementsByClassName('indicator')[0] as HTMLElement;

	// Arrow navigation
	function updateNavArrows() : void
	{
		prevButtonEl.style.display = current === 0 ? 'none' : '';
		nextButtonEl.style.display = current === slidesEls.length - 1 ? 'none' : '';

		// Shrink arrows when showing the video:
		galleryWrapperEl.classList.toggle('video', slidesEls[current].firstElementChild!.nodeName === "IFRAME");
	}

	function updateSelectionIndicator() : void
	{
		selectionIndicatorEl.style.transform = `translateX(${current * (2 + 64)}px)`; // 2 px gap between thumbs :ThumbGap
	}

	function maybePausePlayer() : void
	{
		const iframe = slidesEls[current].firstElementChild as HTMLIFrameElement;
		if(iframe.nodeName !== 'IFRAME') return;

		const src = iframe.src;
		if(src.startsWith('https://www.youtube-nocookie.com/embed/')) {
			iframe.contentWindow?.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*');
		}
		else if(src.startsWith('https://player.vimeo.com/video/')) {
			iframe.contentWindow?.postMessage('{"method":"pause"}', '*');
		}
		else {
			console.info("Don't know how to pause '"+src+"', sorry.");
		}
	}

	// Set up callbacks to recognize if a user started playing a video (needs to stop auto-panning):
	{
		window.addEventListener('message', e => {
			if(e.origin === 'https://www.youtube-nocookie.com') {
				if(!e.data) return;
				const data = JSON.parse(e.data);
	
				if(data.event === 'onStateChange') {
					userInteracted = true;
				}
			}
			else if(e.origin === "https://player.vimeo.com") {
				if(!e.data) return;
				const data = JSON.parse(e.data);

				if(data.event === 'play') {
					userInteracted = true;
				}
			}
		});

		let i = 0;
		for(const wrapperEl of slidesEls) {
			const iframe = wrapperEl.firstElementChild as HTMLIFrameElement;
			if(iframe.nodeName !== 'IFRAME') continue;

			const src = iframe.src;
			if(src.startsWith('https://www.youtube-nocookie.com/embed/')) {
				iframe.addEventListener('load', () => {
					iframe.contentWindow!.postMessage(`{"event":"listening","id":"${i}","channel":"widget"}`, '*');
					iframe.contentWindow!.postMessage(`{"event":"command","id":"${i}","channel":"widget","func":"addEventListener","args":["onStateChange"]}`, '*');
				});
			}
			else if(src.startsWith("https://player.vimeo.com/video/")) {
				iframe.addEventListener('load', () => {
					iframe.contentWindow!.postMessage(`{"method":"addEventListener","value":"play"}`, '*');
				});
			}
			else {
				console.info("Don't know how to subscribe to play events for '"+src+"', sorry.");
			}

			i++;
		}
	}

	const storageKey = 'gallery-' + location.pathname;

	let isTriggeredScroll = false;
	function goTo(idx : number) : void
	{
		maybePausePlayer();

		current = idx;
		isTriggeredScroll = true;
		sessionStorage.setItem(storageKey, String(idx));

		stageEl.scrollLeft = stageEl.offsetWidth * idx;

		updateSelectionIndicator();
		updateNavArrows();
	}

	prevButtonEl.addEventListener('click', () => {
		userInteracted = true;
		if(current > 0) goTo(current - 1);
	});
	nextButtonEl.addEventListener('click', () => {
		userInteracted = true;
		if(current < slidesEls.length - 1) goTo(current + 1);
	});

	// Navigation interaction
	navStripEl.addEventListener('click', (e: MouseEvent) => {
		let btnEl = e.target as HTMLElement;
		for(let i = 0; btnEl && btnEl.nodeName !== 'BUTTON' && i < 3; i++)
			btnEl = btnEl.parentElement!;
		if(!btnEl || btnEl.nodeName !== 'BUTTON') return;

		userInteracted = true;
		goTo(parseInt(btnEl.dataset.i!, 10));
	});

	// hovering should pause auto-pan:
	let currentlyHoveringViewport = false;
	galleryWrapperEl.addEventListener('mouseenter', () => currentlyHoveringViewport = true);
	galleryWrapperEl.addEventListener('mouseleave', () => currentlyHoveringViewport = false);

	// Drag-to-pan
	let dragStartX = 0;
	let mightDrag = false;
	let isDragging = false;

	viewportEl.addEventListener('mousedown', (e : MouseEvent) => {
		e.preventDefault();

		mightDrag = true;
		isDragging = false;
		dragStartX = e.pageX;
		viewportEl.style.cursor = 'grabbing';

		userInteracted = true;
	});

	document.addEventListener('mousemove', (e : MouseEvent) => {
		if(!mightDrag) return;

		if(!isDragging && Math.abs(e.pageX - dragStartX) > 5) {
			isDragging = true;
			stageEl.classList.add('manually-dragging');
		}

		if(isDragging) stageEl.scrollLeft -= e.movementX;
	});

	document.addEventListener('mouseup', (e : MouseEvent) => {
		if(!mightDrag) return;

		mightDrag = false;
		viewportEl.style.cursor = '';

		if(!isDragging) return;

		e.preventDefault(); // prevent turning this into a click.

		stageEl.classList.remove('manually-dragging');

		const idx = Math.round(stageEl.scrollLeft / stageEl.offsetWidth);
		goTo(Math.max(0, Math.min(idx, slidesEls.length - 1)));
	});

	// Horizontal scroll (deltaX) navigates one slide at a time
	stageEl.addEventListener('wheel', (e : WheelEvent) => {
		if(!e.deltaX || Math.abs(e.deltaY) >= Math.abs(e.deltaX)) return;

		userInteracted = true;
	});

	// Update our state after "normal" scroll interaction by the user
	stageEl.addEventListener('scroll', () => {
		if(isTriggeredScroll) { return; }

		userInteracted = true;

		const idx = Math.round(stageEl.scrollLeft / stageEl.offsetWidth);
		if(idx !== current) {
			maybePausePlayer();

			current = idx;

			updateSelectionIndicator();
			updateNavArrows();
			sessionStorage.setItem(storageKey, String(idx));
		}
	});

	stageEl.addEventListener('scrollend', () => {
		if(isTriggeredScroll) {
			isTriggeredScroll = false;
		}
	});

	// Auto-play: advance every 5s until user interacts
	const autoplay = setInterval(() => {
		if(currentlyHoveringViewport) return;

		if(userInteracted) {
			clearInterval(autoplay);
			return;
		}

		isTriggeredScroll = true;
		goTo((current + 1) % slidesEls.length);
	}, 5000);

	// Restore active index from sessionStorage (scroll-snap resets scrollLeft on reload)
	const saved = sessionStorage.getItem(storageKey);
	if(saved) {
		const idx = parseInt(saved, 10);
		if(idx > 0 && idx < slidesEls.length) {
			current = idx;

			isTriggeredScroll = true;
			selectionIndicatorEl.style.transition = 'none';
			stageEl.scrollLeft = stageEl.offsetWidth * idx;
			updateSelectionIndicator();
			setTimeout(() => { selectionIndicatorEl.style.transition = ''; }, 100); // have to do this because just toggling the style change will be batched and have no effect.
		}
	}
	updateNavArrows();
}
