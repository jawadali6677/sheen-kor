(function (window, document) {
    'use strict';

    var objectUrls = [];

    function isImageFileInput(input) {
        return input instanceof HTMLInputElement
            && input.type === 'file'
            && (!input.accept || input.accept.indexOf('image') !== -1);
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
        list.querySelectorAll('img').forEach(function (img) {
            revokeUrl(img.src);
        });
        list.innerHTML = '';
    }

    function imageFiles(input) {
        return Array.prototype.filter.call(input.files || [], function (file) {
            return file.type.indexOf('image/') === 0;
        });
    }

    function showTargetPreview(input, files) {
        var selector = input.getAttribute('data-preview-target');

        if (!selector) {
            return false;
        }

        var target = document.querySelector(selector);

        if (!target) {
            return false;
        }

        var hideSelector = input.getAttribute('data-preview-hide');
        var hideEl = hideSelector ? document.querySelector(hideSelector) : null;

        if (!files.length) {
            return true;
        }

        revokeUrl(target.getAttribute('data-object-url'));

        var url = URL.createObjectURL(files[0]);
        objectUrls.push(url);
        target.setAttribute('data-object-url', url);
        target.src = url;
        target.classList.remove('hidden', 'd-none');
        target.classList.add('js-lightbox');
        target.alt = files[0].name;

        if (hideEl) {
            hideEl.classList.add('hidden');
        }

        return true;
    }

    function renderListPreview(input, files) {
        var list = previewListFor(input);
        clearPreviewList(list);

        if (!files.length) {
            return;
        }

        var hint = document.createElement('p');
        hint.className = 'image-preview-hint';
        hint.textContent = 'Click a photo to open it larger before you upload.';
        list.appendChild(hint);

        files.forEach(function (file) {
            var url = URL.createObjectURL(file);
            objectUrls.push(url);

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
        var files = imageFiles(input);

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

        if (!overlay || !image || !src) {
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

        if (!overlay || !image) {
            return;
        }

        overlay.hidden = true;
        overlay.classList.remove('is-open');
        image.removeAttribute('src');
        document.body.classList.remove('image-lightbox-open');
    }

    document.addEventListener('change', function (event) {
        if (isImageFileInput(event.target)) {
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

        if (!img || !img.src) {
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
