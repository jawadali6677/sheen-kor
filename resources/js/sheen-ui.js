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

    Alpine.data('infiniteFeed', (config) => ({
        nextUrl: config.nextUrl || null,
        finishedText: config.finishedText || 'No more items',
        loading: false,
        finished: ! config.nextUrl,
        observer: null,
        init() {
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
            });
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
