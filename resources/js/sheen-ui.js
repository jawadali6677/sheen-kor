export function registerSheenUi(Alpine) {
    Alpine.store('confirm', {
        open: false,
        title: '',
        message: '',
        actionLabel: 'Confirm',
        variant: 'danger',
        resolver: null,
        ask(options) {
            this.title = options.title || 'Are you sure?';
            this.message = options.message || '';
            this.actionLabel = options.actionLabel || 'Confirm';
            this.variant = options.variant || 'danger';
            this.open = true;

            return new Promise((resolve) => {
                this.resolver = resolve;
            });
        },
        accept() {
            this.open = false;
            this.resolver?.(true);
            this.resolver = null;
        },
        cancel() {
            this.open = false;
            this.resolver?.(false);
            this.resolver = null;
        },
    });

    window.skConfirm = (options) => Alpine.store('confirm').ask(options);

    window.skBackToStories = (event) => {
        if (window.history.length <= 1 || ! document.referrer) {
            return;
        }

        try {
            const referrer = new URL(document.referrer);

            if (referrer.origin !== window.location.origin) {
                return;
            }
        } catch (error) {
            return;
        }

        event.preventDefault();
        window.history.back();
    };

    Alpine.data('contentVideoAds', (config) => ({
        open: false,
        advertisement: null,
        requestInFlight: false,
        init() {
            this.$el.querySelectorAll('video').forEach((video) => {
                video.addEventListener('ended', () => this.onEnded(video));
            });
        },
        csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        },
        async onEnded(video) {
            if (this.open || this.requestInFlight) {
                return;
            }

            const duration = Number.isFinite(video.duration) ? Math.round(video.duration) : 0;

            this.requestInFlight = true;

            try {
                const response = await fetch(config.eligibilityUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        source_type: config.sourceType,
                        source_id: config.sourceId,
                        duration_seconds: duration,
                    }),
                });

                const payload = await response.json().catch(() => ({}));

                if (! response.ok || ! payload.allowed || ! payload.advertisement) {
                    return;
                }

                this.advertisement = payload.advertisement;
                this.open = true;
                this.recordImpression(payload.advertisement);
            } finally {
                this.requestInFlight = false;
            }
        },
        async recordImpression(advertisement) {
            if (! advertisement?.impression_url) {
                return;
            }

            try {
                await fetch(advertisement.impression_url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        placement: advertisement.placement || 'video_interstitial',
                    }),
                });
            } catch (error) {
                // Impression tracking is best-effort.
            }
        },
        dismiss() {
            this.open = false;
            this.advertisement = null;
        },
    }));

    Alpine.data('mediaCarousel', (count) => ({
        index: 0,
        count,
        next() {
            if (this.count < 2) {
                return;
            }

            this.index = (this.index + 1) % this.count;
        },
        prev() {
            if (this.count < 2) {
                return;
            }

            this.index = (this.index - 1 + this.count) % this.count;
        },
    }));

    Alpine.data('adCard', (config) => ({
        hidden: false,
        recorded: false,
        init() {
            this.recordImpression();
        },
        async recordImpression() {
            if (this.recorded || ! config.impressionUrl) {
                return;
            }

            this.recorded = true;

            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            try {
                await fetch(config.impressionUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        placement: config.placement || 'feed_posts',
                    }),
                });
            } catch (error) {
                this.recorded = false;
            }
        },
    }));

    Alpine.data('infiniteFeed', (config) => ({
        nextUrl: config.nextUrl || null,
        finishedText: config.finishedText || 'No more items',
        loading: false,
        finished: ! config.nextUrl,
        observer: null,
        onPageShow: null,
        init() {
            this.restoreScroll();
            this.onPageShow = (event) => {
                if (! event.persisted) {
                    this.restoreScroll();
                }
            };
            window.addEventListener('pagehide', () => this.saveScroll());
            window.addEventListener('pageshow', this.onPageShow);

            if (! this.$refs.sentinel || this.finished) {
                return;
            }

            this.observer = new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    this.loadMore();
                }
            }, { rootMargin: '480px 0px' });

            this.observer.observe(this.$refs.sentinel);
        },
        saveScroll() {
            sessionStorage.setItem('sk-feed-scroll', JSON.stringify({
                key: window.location.pathname + window.location.search,
                y: window.scrollY,
            }));
        },
        restoreScroll() {
            try {
                const saved = JSON.parse(sessionStorage.getItem('sk-feed-scroll') || 'null');

                if (saved?.key === window.location.pathname + window.location.search) {
                    window.requestAnimationFrame(() => window.scrollTo(0, Number(saved.y) || 0));
                }
            } catch (error) {
                // Ignore unreadable session storage.
            }
        },
        async loadMore() {
            if (this.loading || ! this.nextUrl) {
                return;
            }

            this.loading = true;

            try {
                const url = new URL(this.nextUrl, window.location.origin);
                url.searchParams.set('partial', '1');

                const response = await fetch(url.toString(), {
                    headers: {
                        Accept: 'text/html',
                        'X-Infinite-Scroll': '1',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (! response.ok) {
                    return;
                }

                const html = await response.text();
                const wrap = document.createElement('div');
                wrap.innerHTML = html.trim();

                const nextMeta = wrap.querySelector('[data-infinite-next]');
                const next = nextMeta ? nextMeta.getAttribute('data-infinite-next') : '';
                nextMeta?.remove();

                const added = [];

                while (wrap.firstChild) {
                    const node = wrap.firstChild;
                    this.$refs.items.appendChild(node);
                    added.push(node);
                }

                added.forEach((node) => {
                    if (node.nodeType === 1 && window.Alpine) {
                        window.Alpine.initTree(node);
                    }
                });

                this.nextUrl = next || null;
                this.finished = ! this.nextUrl;
            } finally {
                this.loading = false;
            }
        },
    }));

    Alpine.data('mediaUploader', (config) => ({
        items: [],
        error: '',
        maxImages: config.maxImages,
        maxVideos: config.maxVideos,
        imageMaxBytes: config.imageMaxBytes,
        videoMaxBytes: config.videoMaxBytes,
        requireImage: config.requireImage,
        mapFirstImageToFeatured: config.mapFirstImageToFeatured,
        dragging: false,
        init() {
            this.$el.closest('form')?.addEventListener('submit', (event) => {
                if (! this.prepareSubmit()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            }, true);
        },
        openPicker() {
            this.$refs.picker?.click();
        },
        onPickerChange(event) {
            this.addFiles(Array.from(event.target.files || []));
            event.target.value = '';
        },
        onDrop(event) {
            this.dragging = false;
            this.addFiles(Array.from(event.dataTransfer?.files || []));
        },
        addFiles(files) {
            this.error = '';

            files.forEach((file) => {
                const kind = this.kindFor(file);

                if (! kind) {
                    this.error = `${file.name} is not a supported photo or video.`;
                    return;
                }

                if (kind === 'image' && file.size > this.imageMaxBytes) {
                    this.error = `${file.name} is larger than 5 MB.`;
                    return;
                }

                if (kind === 'video' && file.size > this.videoMaxBytes) {
                    this.error = `${file.name} is larger than 20 MB.`;
                    return;
                }

                if (kind === 'image' && this.imageCount() >= this.maxImages) {
                    this.error = `You can add up to ${this.maxImages} photos.`;
                    return;
                }

                if (kind === 'video' && this.videoCount() >= this.maxVideos) {
                    this.error = `You can add up to ${this.maxVideos} videos.`;
                    return;
                }

                this.items.push({
                    id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
                    file,
                    kind,
                    url: URL.createObjectURL(file),
                });
            });
        },
        removeItem(id) {
            const item = this.items.find((entry) => entry.id === id);

            if (item?.url) {
                URL.revokeObjectURL(item.url);
            }

            this.items = this.items.filter((entry) => entry.id !== id);
            this.error = '';
        },
        imageCount() {
            return this.items.filter((item) => item.kind === 'image').length;
        },
        videoCount() {
            return this.items.filter((item) => item.kind === 'video').length;
        },
        kindFor(file) {
            const type = (file.type || '').toLowerCase();
            const name = (file.name || '').toLowerCase();

            if (type.startsWith('image/jpeg') || type === 'image/png' || type === 'image/webp' || /\.(jpe?g|png|webp)$/.test(name)) {
                return 'image';
            }

            if (type === 'video/mp4' || type === 'video/webm' || type === 'video/quicktime' || /\.(mp4|webm|mov)$/.test(name)) {
                return 'video';
            }

            return null;
        },
        assignFiles(input, files) {
            if (! input) {
                return;
            }

            const fieldName = input.getAttribute('data-field-name') || input.name;

            if (! files.length) {
                input.removeAttribute('name');
                input.files = new DataTransfer().files;
                return;
            }

            input.setAttribute('name', fieldName);
            const transfer = new DataTransfer();
            files.forEach((file) => transfer.items.add(file));
            input.files = transfer.files;
        },
        prepareSubmit() {
            this.error = '';

            if (this.requireImage && this.imageCount() < 1) {
                this.error = 'Please add at least one photo.';
                return false;
            }

            const images = this.items.filter((item) => item.kind === 'image').map((item) => item.file);
            const videos = this.items.filter((item) => item.kind === 'video').map((item) => item.file);

            if (this.mapFirstImageToFeatured && this.$refs.featured) {
                this.assignFiles(this.$refs.featured, images.slice(0, 1));
                this.assignFiles(this.$refs.images, images.slice(1));
            } else {
                if (this.$refs.featured) {
                    this.assignFiles(this.$refs.featured, []);
                }

                this.assignFiles(this.$refs.images, images);
            }

            this.assignFiles(this.$refs.videos, videos);

            return true;
        },
    }));

    Alpine.data('postComposer', () => ({
        submitting: false,
        onSubmit(event) {
            if (event.defaultPrevented) {
                return;
            }

            if (this.submitting) {
                event.preventDefault();
                return;
            }

            this.submitting = true;
        },
    }));

    const adminModerationQueue = (prefix) => ({
        loading: false,
        acting: false,
        debounceTimer: null,
        onPopState: null,
        prefix,
        init() {
            this.onPopState = () => this.load(window.location.href, false);
            window.addEventListener('popstate', this.onPopState);
            this.$el.addEventListener('click', (event) => this.onClick(event));
            this.$el.addEventListener('submit', (event) => this.onSubmit(event));
            this.$el.querySelector(`[data-${this.prefix}-search] input[name="q"]`)
                ?.addEventListener('input', (event) => this.onSearchInput(event));
        },
        destroy() {
            if (this.onPopState) {
                window.removeEventListener('popstate', this.onPopState);
            }

            if (this.debounceTimer) {
                window.clearTimeout(this.debounceTimer);
            }
        },
        csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        },
        searchForm() {
            return this.$el.querySelector(`[data-${this.prefix}-search]`);
        },
        onSearchInput() {
            if (this.debounceTimer) {
                window.clearTimeout(this.debounceTimer);
            }

            this.debounceTimer = window.setTimeout(() => {
                this.loadFromSearch();
            }, 300);
        },
        onClick(event) {
            const filterLink = event.target.closest(`[data-${this.prefix}-filter]`);

            if (filterLink instanceof HTMLAnchorElement && this.$el.contains(filterLink)) {
                event.preventDefault();
                this.load(this.urlWithSearch(filterLink.href), true);
                return;
            }

            const paginationLink = event.target.closest(`[data-${this.prefix}-pagination] a`);

            if (paginationLink instanceof HTMLAnchorElement && this.$el.contains(paginationLink)) {
                event.preventDefault();
                this.load(paginationLink.href, true);
            }
        },
        onSubmit(event) {
            const form = event.target;

            if (! (form instanceof HTMLFormElement) || ! this.$el.contains(form)) {
                return;
            }

            if (form.hasAttribute(`data-${this.prefix}-search`)) {
                event.preventDefault();
                this.loadFromSearch();
                return;
            }

            if (! form.hasAttribute(`data-${this.prefix}-action`)) {
                return;
            }

            event.preventDefault();
            this.submitAction(form);
        },
        urlWithSearch(href) {
            const url = new URL(href, window.location.origin);
            const query = this.searchForm()?.elements.namedItem('q');
            const search = query instanceof HTMLInputElement ? query.value.trim() : '';

            if (search !== '') {
                url.searchParams.set('q', search);
            } else {
                url.searchParams.delete('q');
            }

            url.searchParams.delete('page');

            return url.toString();
        },
        loadFromSearch() {
            const form = this.searchForm();

            if (! (form instanceof HTMLFormElement)) {
                return;
            }

            const url = new URL(form.action, window.location.origin);
            const status = form.elements.namedItem('status');
            const query = form.elements.namedItem('q');

            if (status instanceof HTMLInputElement && status.value) {
                url.searchParams.set('status', status.value);
            }

            const search = query instanceof HTMLInputElement ? query.value.trim() : '';

            if (search !== '') {
                url.searchParams.set('q', search);
            }

            this.load(url.toString(), true);
        },
        async load(href, push) {
            if (this.loading) {
                return;
            }

            this.loading = true;

            try {
                const url = new URL(href, window.location.origin);
                url.searchParams.set('partial', '1');

                const response = await fetch(url.toString(), {
                    headers: {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Infinite-Scroll': '1',
                    },
                });

                if (! response.ok) {
                    return;
                }

                this.$refs.results.innerHTML = (await response.text()).trim();

                if (window.Alpine) {
                    window.Alpine.initTree(this.$refs.results);
                }

                this.syncChrome();

                if (push) {
                    url.searchParams.delete('partial');
                    window.history.pushState({}, '', url.pathname + url.search);
                }
            } finally {
                this.loading = false;
            }
        },
        syncChrome() {
            const meta = this.$refs.results.querySelector(`[data-${this.prefix}-meta]`);

            if (! (meta instanceof HTMLElement)) {
                return;
            }

            const status = meta.getAttribute('data-status') || 'pending';
            const search = meta.getAttribute('data-search') || '';
            let counts = {};

            try {
                counts = JSON.parse(meta.getAttribute('data-counts') || '{}');
            } catch (error) {
                counts = {};
            }

            const statusInput = this.searchForm()?.elements.namedItem('status');

            if (statusInput instanceof HTMLInputElement) {
                statusInput.value = status;
            }

            const searchInput = this.searchForm()?.elements.namedItem('q');

            if (searchInput instanceof HTMLInputElement && document.activeElement !== searchInput) {
                searchInput.value = search;
            }

            this.$el.querySelectorAll(`[data-${this.prefix}-filter]`).forEach((link) => {
                const active = link.getAttribute(`data-${this.prefix}-filter`) === status;
                const isTab = link.closest(`[data-${this.prefix}-tabs]`);

                if (isTab) {
                    link.className = active
                        ? 'rounded-full px-3 py-1 bg-forest-800 text-white'
                        : 'rounded-full px-3 py-1 bg-white text-gray-700 ring-1 ring-gray-200';
                } else {
                    link.classList.toggle('ring-2', active);
                    link.classList.toggle('ring-amber-400', active && status === 'pending');
                    link.classList.toggle('ring-forest-400', active && status === 'published');
                    link.classList.toggle('ring-red-300', active && status === 'rejected');
                    link.classList.toggle('ring-violet-400', active && status === 'reported');
                    link.classList.toggle('ring-gray-400', active && status === 'all');
                }
            });

            Object.entries(counts).forEach(([key, value]) => {
                const node = this.$el.querySelector(`[data-count="${key}"]`);

                if (node) {
                    node.textContent = Number(value).toLocaleString();
                }
            });
        },
        async submitAction(form) {
            if (this.acting) {
                return;
            }

            this.acting = true;
            const button = form.querySelector('button[type="submit"]');
            const original = button instanceof HTMLButtonElement ? button.textContent : '';

            if (button instanceof HTMLButtonElement) {
                button.disabled = true;
                button.textContent = 'Working…';
            }

            try {
                const response = await fetch(form.action, {
                    method: (form.getAttribute('method') || 'POST').toUpperCase(),
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrf(),
                    },
                    body: new FormData(form),
                });

                const payload = await response.json().catch(() => ({}));

                if (! response.ok || payload.success === false) {
                    this.showNotice(payload.message || 'Something went wrong.', true);
                    return;
                }

                this.showNotice(payload.message || 'Saved.');
                delete form.dataset.confirmAccepted;
                await this.load(window.location.href, false);
            } finally {
                this.acting = false;

                if (button instanceof HTMLButtonElement) {
                    button.disabled = false;
                    button.textContent = original;
                }
            }
        },
        showNotice(message, isError) {
            const store = window.Alpine?.store('notifications');

            if (store?.showToast) {
                store.showToast({
                    title: isError ? 'Could not update' : 'Updated',
                    body: message,
                });
            }
        },
    });

    Alpine.data('adminPostsQueue', () => adminModerationQueue('admin-posts'));
    Alpine.data('adminMarketQueue', () => adminModerationQueue('admin-market'));

    function prependFeedPost(html) {
        const feed = document.getElementById('feed-items');

        if (! feed || ! html) {
            return false;
        }

        const wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        const incoming = wrap.querySelector('[data-post-id]');

        if (incoming && feed.querySelector(`[data-post-id="${incoming.getAttribute('data-post-id')}"]`)) {
            return true;
        }

        const nodes = [];

        while (wrap.firstChild) {
            const node = wrap.firstChild;
            feed.insertBefore(node, feed.firstChild);
            nodes.push(node);
        }

        nodes.forEach((node) => {
            if (node.nodeType === 1 && window.Alpine) {
                window.Alpine.initTree(node);
            }
        });

        feed.querySelector('[data-empty-state]')?.remove();

        return true;
    }

    Alpine.data('postModerationWatch', (config = {}) => ({
        timer: null,
        started: 0,
        start() {
            this.started = Date.now();
            this.timer = window.setTimeout(() => this.poll(), 2000);
        },
        destroy() {
            if (this.timer) {
                window.clearTimeout(this.timer);
            }
        },
        async poll() {
            if (! config.url || Date.now() - this.started > 45000) {
                return;
            }

            try {
                const response = await fetch(config.url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (response.ok) {
                    const data = await response.json();

                    if (data.status === 'published' || data.status === 'rejected') {
                        const message = document.querySelector('[data-post-status-message]');

                        if (message && data.message) {
                            message.textContent = data.message;
                        }

                        if (data.status === 'published' && data.html) {
                            prependFeedPost(data.html);
                        }

                        return;
                    }
                }
            } catch (error) {
                // Keep waiting until the check finishes or the timeout.
            }

            this.timer = window.setTimeout(() => this.poll(), 2000);
        },
    }));

    Alpine.data('quickPostComposer', (config = {}) => ({
        embedded: Boolean(config.embedded),
        open: Boolean(config.open || config.embedded),
        text: config.text || '',
        items: [],
        errors: Array.isArray(config.errors) ? config.errors : [],
        submitting: false,
        phase: 'idle',
        progress: 0,
        success: '',
        moderationTimer: null,
        maxImages: 11,
        maxVideos: 3,
        imageMaxBytes: 5 * 1024 * 1024,
        videoMaxBytes: 20 * 1024 * 1024,
        get canPost() {
            return this.text.trim() !== '' || this.items.length > 0;
        },
        init() {
            this.$nextTick(() => this.resizeBody());
        },
        openWith(detail) {
            const intent = typeof detail === 'string' ? detail : (detail?.intent || 'text');

            this.open = true;
            this.phase = 'idle';
            this.success = '';

            this.$nextTick(() => {
                if (intent === 'photo') {
                    this.$refs.photoPicker?.click();
                } else if (intent === 'video') {
                    this.$refs.videoPicker?.click();
                } else if (intent === 'camera') {
                    this.$refs.cameraPicker?.click();
                } else {
                    this.$refs.body?.focus();
                }
            });
        },
        close() {
            if (this.moderationTimer) {
                window.clearTimeout(this.moderationTimer);
                this.moderationTimer = null;
            }

            if (this.embedded) {
                return;
            }

            this.open = false;
            this.phase = 'idle';
            this.submitting = false;
        },
        grow(event) {
            this.resizeBody(event.target);
        },
        resizeBody(element) {
            const field = element || this.$refs.body;

            if (! field) {
                return;
            }

            field.style.height = 'auto';
            field.style.height = `${Math.min(field.scrollHeight, 224)}px`;
        },
        tryPost() {
            if (this.submitting) {
                return;
            }

            if (! this.canPost) {
                this.errors = ['Please write something or add a photo or video.'];

                return;
            }

            this.$refs.form?.requestSubmit();
        },
        onPickerChange(event) {
            this.addFiles(Array.from(event.target.files || []));
            event.target.value = '';
        },
        addFiles(files) {
            this.errors = [];

            files.forEach((file) => {
                const kind = this.kindFor(file);

                if (! kind) {
                    this.errors = ['Please choose a photo (JPG, PNG, or WEBP) or a video (MP4, WEBM, or MOV).'];

                    return;
                }

                if (kind === 'image' && file.size > this.imageMaxBytes) {
                    this.errors = ['That photo is too big. Photos can be up to 5 MB.'];

                    return;
                }

                if (kind === 'video' && file.size > this.videoMaxBytes) {
                    this.errors = ['That video is too big. Videos can be up to 20 MB.'];

                    return;
                }

                if (kind === 'image' && this.imageCount() >= this.maxImages) {
                    this.errors = ['You can add up to 11 photos.'];

                    return;
                }

                if (kind === 'video' && this.videoCount() >= this.maxVideos) {
                    this.errors = ['You can add up to 3 videos.'];

                    return;
                }

                this.items.push({
                    id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
                    file,
                    kind,
                    url: URL.createObjectURL(file),
                });
            });
        },
        removeItem(id) {
            const item = this.items.find((entry) => entry.id === id);

            if (item?.url) {
                URL.revokeObjectURL(item.url);
            }

            this.items = this.items.filter((entry) => entry.id !== id);
            this.errors = [];
        },
        imageCount() {
            return this.items.filter((item) => item.kind === 'image').length;
        },
        videoCount() {
            return this.items.filter((item) => item.kind === 'video').length;
        },
        kindFor(file) {
            const type = (file.type || '').toLowerCase();
            const name = (file.name || '').toLowerCase();

            if (type.startsWith('image/jpeg') || type === 'image/png' || type === 'image/webp' || /\.(jpe?g|png|webp)$/.test(name)) {
                return 'image';
            }

            if (type === 'video/mp4' || type === 'video/webm' || type === 'video/quicktime' || /\.(mp4|webm|mov)$/.test(name)) {
                return 'video';
            }

            return null;
        },
        assignFiles(input, files) {
            if (! input || typeof DataTransfer === 'undefined') {
                return;
            }

            const fieldName = input.getAttribute('data-field-name') || input.name;

            if (! files.length) {
                input.removeAttribute('name');
                input.files = new DataTransfer().files;

                return;
            }

            input.setAttribute('name', fieldName);
            const transfer = new DataTransfer();
            files.forEach((file) => transfer.items.add(file));
            input.files = transfer.files;
        },
        prepareSubmit() {
            const images = this.items.filter((item) => item.kind === 'image').map((item) => item.file);
            const videos = this.items.filter((item) => item.kind === 'video').map((item) => item.file);

            this.assignFiles(this.$refs.featured, images.slice(0, 1));
            this.assignFiles(this.$refs.images, images.slice(1));
            this.assignFiles(this.$refs.videos, videos);

            return true;
        },
        onSubmit(event) {
            if (event.defaultPrevented || this.submitting) {
                event.preventDefault();

                return;
            }

            this.prepareSubmit();

            if (! this.canPost) {
                event.preventDefault();
                this.errors = ['Please write something or add a photo or video.'];

                return;
            }

            if (! window.XMLHttpRequest || ! window.FormData) {
                this.submitting = true;
                this.phase = 'checking';

                return;
            }

            event.preventDefault();
            this.upload(event.target);
        },
        upload(form) {
            this.submitting = true;
            this.phase = 'uploading';
            this.progress = 0;
            this.errors = [];

            const xhr = new XMLHttpRequest();
            xhr.open('POST', form.action);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.upload.addEventListener('progress', (progressEvent) => {
                if (! progressEvent.lengthComputable || progressEvent.total === 0) {
                    return;
                }

                this.progress = Math.min(100, Math.round((progressEvent.loaded / progressEvent.total) * 100));

                if (this.progress >= 100) {
                    this.phase = 'checking';
                }
            });
            xhr.upload.addEventListener('load', () => {
                this.progress = 100;
                this.phase = 'checking';
            });
            xhr.addEventListener('load', () => this.handleUploadResponse(xhr));
            xhr.addEventListener('error', () => {
                this.submitting = false;
                this.phase = 'idle';
                this.errors = ['Something went wrong. Please try again.'];
            });
            xhr.send(new FormData(form));
        },
        handleUploadResponse(xhr) {
            let data = {};

            try {
                data = JSON.parse(xhr.responseText || '{}');
            } catch (error) {
                data = {};
            }

            if (xhr.status === 419 || xhr.status === 401) {
                window.location.reload();

                return;
            }

            if (xhr.status === 422) {
                this.submitting = false;
                this.phase = 'idle';
                const messages = Object.values(data.errors || {}).flat().map((message) => this.friendly(message));
                this.errors = messages.length ? messages : [this.friendly(data.message || 'Please check your post and try again.')];

                return;
            }

            if (xhr.status < 200 || xhr.status >= 300) {
                this.submitting = false;
                this.phase = 'idle';
                this.errors = [this.friendly(data.message || 'Something went wrong. Please try again.')];

                return;
            }

            this.finishSuccess(data);
        },
        friendly(message) {
            const text = String(message || '');

            if (/must be a file of type|must be an image|mimetypes/i.test(text)) {
                return 'Please choose a photo (JPG, PNG, or WEBP) or a video (MP4, WEBM, or MOV).';
            }

            if (/may not be greater than|kilobytes/i.test(text)) {
                return 'That file is too big. Photos can be 5 MB and videos can be 20 MB.';
            }

            if (/may not have more than/i.test(text)) {
                return 'That is too many files. You can add up to 11 photos and 3 videos.';
            }

            if (/please write something or add a photo or video/i.test(text)) {
                return 'Please write something or add a photo or video.';
            }

            return text;
        },
        finishSuccess(data) {
            this.submitting = false;
            this.success = data.message || 'Your post is being checked. It will appear shortly.';
            this.phase = 'success';
            this.clearItems();
            this.text = '';

            if (data.status === 'published' && data.html && this.prependPost(data.html)) {
                window.setTimeout(() => this.close(), 1400);

                return;
            }

            if (data.status === 'rejected') {
                return;
            }

            if (data.status === 'pending' && data.status_url && document.getElementById('feed-items')) {
                this.watchModeration(data.status_url);

                return;
            }

            if (this.embedded || ! document.getElementById('feed-items')) {
                window.location = data.redirect || window.location.href;
            }
        },
        watchModeration(url) {
            const started = Date.now();

            const poll = async () => {
                if (Date.now() - started > 45000) {
                    return;
                }

                try {
                    const response = await fetch(url, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (response.ok) {
                        const data = await response.json();

                        if (data.status === 'published' || data.status === 'rejected') {
                            this.success = data.message || this.success;

                            if (data.status === 'published' && data.html) {
                                this.prependPost(data.html);
                                window.setTimeout(() => this.close(), 1400);
                            }

                            return;
                        }
                    }
                } catch (error) {
                    // Keep the pending message and try again.
                }

                this.moderationTimer = window.setTimeout(poll, 2000);
            };

            this.moderationTimer = window.setTimeout(poll, 2000);
        },
        clearItems() {
            this.items.forEach((item) => {
                if (item.url) {
                    URL.revokeObjectURL(item.url);
                }
            });
            this.items = [];
        },
        prependPost(html) {
            return prependFeedPost(html);
        },
    }));

    document.addEventListener('submit', async (event) => {
        const form = event.target;

        if (! (form instanceof HTMLFormElement) || ! form.hasAttribute('data-confirm')) {
            return;
        }

        if (form.dataset.confirmAccepted === '1') {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const confirmed = await window.skConfirm({
            title: form.getAttribute('data-confirm') || 'Are you sure?',
            message: form.getAttribute('data-confirm-message') || '',
            actionLabel: form.getAttribute('data-confirm-action') || 'Confirm',
            variant: form.getAttribute('data-confirm-variant') || 'danger',
        });

        if (! confirmed) {
            return;
        }

        form.dataset.confirmAccepted = '1';
        form.requestSubmit();
    }, true);
}
