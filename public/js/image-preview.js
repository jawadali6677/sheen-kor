(function (window, document) {
    'use strict';

    var objectUrls = [];

    function isPreviewableFileInput(input) {
        if (! (input instanceof HTMLInputElement) || input.type !== 'file') {
            return false;
        }

        if (input.hasAttribute('data-skip-preview') || input.closest('.js-media-uploader')) {
            return false;
        }

        if (! input.accept) {
            return true;
        }

        return input.accept.indexOf('image') !== -1 || input.accept.indexOf('video') !== -1;
    }

    function revokeUrl(url) {
        if (url && url.indexOf('blob:') === 0) {
            URL.revokeObjectURL(url);
        }
    }

    function previewListFor(input) {
        var next = input.nextElementSibling;

        if (next && next.classList.contains('js-image-preview-list')) {
            return next;
        }

        var list = document.createElement('div');
        list.className = 'js-image-preview-list image-preview-list';
        list.setAttribute('aria-live', 'polite');
        input.insertAdjacentElement('afterend', list);

        return list;
    }

    function clearPreviewList(list) {
        list.querySelectorAll('img, video').forEach(function (el) {
            revokeUrl(el.src);
        });
        list.innerHTML = '';
    }

    function mediaFiles(input) {
        return Array.prototype.filter.call(input.files || [], function (file) {
            return file.type.indexOf('image/') === 0 || file.type.indexOf('video/') === 0;
        });
    }

    function imageFiles(files) {
        return files.filter(function (file) {
            return file.type.indexOf('image/') === 0;
        });
    }

    function showTargetPreview(input, files) {
        var selector = input.getAttribute('data-preview-target');

        if (! selector) {
            return false;
        }

        var target = document.querySelector(selector);

        if (! target) {
            return false;
        }

        var photos = imageFiles(files);
        var hideSelector = input.getAttribute('data-preview-hide');
        var hideEl = hideSelector ? document.querySelector(hideSelector) : null;

        if (! photos.length) {
            return true;
        }

        revokeUrl(target.getAttribute('data-object-url'));

        var url = URL.createObjectURL(photos[0]);
        objectUrls.push(url);
        target.setAttribute('data-object-url', url);
        target.src = url;
        target.classList.remove('hidden', 'd-none');
        target.classList.add('js-lightbox');
        target.alt = photos[0].name;

        if (hideEl) {
            hideEl.classList.add('hidden');
        }

        return true;
    }

    function renderListPreview(input, files) {
        var list = previewListFor(input);
        clearPreviewList(list);

        if (! files.length) {
            return;
        }

        var hint = document.createElement('p');
        hint.className = 'image-preview-hint';
        hint.textContent = 'Photos open larger when clicked. Videos play here before you upload.';
        list.appendChild(hint);

        files.forEach(function (file) {
            var url = URL.createObjectURL(file);
            objectUrls.push(url);
            var isVideo = file.type.indexOf('video/') === 0;

            if (isVideo) {
                var video = document.createElement('video');
                video.src = url;
                video.controls = true;
                video.playsInline = true;
                video.muted = true;
                video.preload = 'metadata';
                video.className = 'image-preview-video';
                list.appendChild(video);

                return;
            }

            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'image-preview-thumb';
            button.setAttribute('aria-label', 'Open preview of ' + file.name);

            var img = document.createElement('img');
            img.src = url;
            img.alt = file.name;
            img.className = 'js-lightbox';

            button.appendChild(img);
            list.appendChild(button);
        });
    }

    function renderPreviews(input) {
        var files = mediaFiles(input);

        if (showTargetPreview(input, files)) {
            return;
        }

        renderListPreview(input, files);
    }

    function lightbox() {
        return document.getElementById('image-lightbox');
    }

    function lightboxImage() {
        return document.getElementById('image-lightbox-image');
    }

    function openLightbox(src, alt) {
        var overlay = lightbox();
        var image = lightboxImage();

        if (! overlay || ! image || ! src) {
            return;
        }

        image.src = src;
        image.alt = alt || 'Preview';
        overlay.hidden = false;
        overlay.classList.add('is-open');
        document.body.classList.add('image-lightbox-open');
    }

    function closeLightbox() {
        var overlay = lightbox();
        var image = lightboxImage();

        if (! overlay || ! image) {
            return;
        }

        overlay.hidden = true;
        overlay.classList.remove('is-open');
        image.removeAttribute('src');
        document.body.classList.remove('image-lightbox-open');
    }

    document.addEventListener('change', function (event) {
        if (isPreviewableFileInput(event.target)) {
            renderPreviews(event.target);
        }
    });

    document.addEventListener('click', function (event) {
        var overlay = lightbox();

        if (overlay && overlay.classList.contains('is-open')) {
            if (event.target === overlay || event.target.closest('.js-image-lightbox-close')) {
                closeLightbox();
            }

            return;
        }

        var img = event.target.closest('img.js-lightbox');

        if (! img || ! img.src) {
            return;
        }

        event.preventDefault();
        openLightbox(img.src, img.alt);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeLightbox();
        }
    });
})(window, document);
