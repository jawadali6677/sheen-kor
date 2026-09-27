/**
 * Count a feed qualified view when a logged-in member keeps a post card at
 * least 50% visible for 2 continuous seconds. The timer resets if the card
 * drops below 50% or the tab is hidden. Each card fires once per page load.
 * Opening the post page is counted on the server and shares this counter.
 */
export function registerQualifiedPostViews() {
    const url = document.querySelector('meta[name="qualified-view-url"]')?.getAttribute('content');
    const viewerId = document.querySelector('meta[name="qualified-view-user"]')?.getAttribute('content');

    if (! url || ! viewerId || ! document.body) {
        return;
    }

    const dwellMs = 2000;
    const pending = new Map();
    const fired = new Set();
    const queue = new Set();
    let flushTimer = null;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            const postId = entry.target.getAttribute('data-qualified-view-post');

            if (! postId || fired.has(postId)) {
                return;
            }

            const visible = entry.intersectionRatio >= 0.5 && document.visibilityState === 'visible';

            if (visible) {
                arm(postId);

                return;
            }

            disarm(postId);
        });
    }, { threshold: 0.5 });

    function arm(postId) {
        if (pending.has(postId) || fired.has(postId) || document.visibilityState !== 'visible') {
            return;
        }

        const timer = window.setTimeout(() => {
            pending.delete(postId);

            if (document.visibilityState !== 'visible' || fired.has(postId)) {
                return;
            }

            fired.add(postId);
            queue.add(Number(postId));
            scheduleFlush();
        }, dwellMs);

        pending.set(postId, timer);
    }

    function disarm(postId) {
        const timer = pending.get(postId);

        if (timer === undefined) {
            return;
        }

        window.clearTimeout(timer);
        pending.delete(postId);
    }

    function disarmAll() {
        pending.forEach((timer) => window.clearTimeout(timer));
        pending.clear();
    }

    function scheduleFlush() {
        if (flushTimer !== null) {
            return;
        }

        flushTimer = window.setTimeout(() => {
            flushTimer = null;
            flush();
        }, 300);
    }

    async function flush() {
        if (queue.size === 0) {
            return;
        }

        const postIds = Array.from(queue).slice(0, 20);
        postIds.forEach((id) => queue.delete(id));

        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        try {
            await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                keepalive: true,
                body: JSON.stringify({ post_ids: postIds }),
            });
        } catch (error) {
            // Qualified views are best-effort. This card already fired for this page load.
        }

        if (queue.size > 0) {
            scheduleFlush();
        }
    }

    function observe(element) {
        if (! element?.getAttribute || element.dataset.qualifiedViewBound === '1') {
            return;
        }

        const postId = element.getAttribute('data-qualified-view-post');
        const authorId = element.getAttribute('data-qualified-view-author');

        if (! postId || authorId === viewerId) {
            return;
        }

        element.dataset.qualifiedViewBound = '1';
        observer.observe(element);
    }

    function scan(root) {
        if (! root?.querySelectorAll) {
            return;
        }

        root.querySelectorAll('[data-qualified-view-post]').forEach((element) => observe(element));
    }

    document.addEventListener('visibilitychange', () => {
        disarmAll();

        if (document.visibilityState !== 'visible') {
            return;
        }

        document.querySelectorAll('[data-qualified-view-post]').forEach((element) => {
            const postId = element.getAttribute('data-qualified-view-post');

            if (element.dataset.qualifiedViewBound !== '1' || ! postId || fired.has(postId)) {
                return;
            }

            observer.unobserve(element);
            observer.observe(element);
        });
    });

    scan(document);

    const mutations = new MutationObserver((records) => {
        records.forEach((record) => {
            record.addedNodes.forEach((node) => {
                if (node.nodeType !== 1) {
                    return;
                }

                if (node.matches?.('[data-qualified-view-post]')) {
                    observe(node);
                }

                scan(node);
            });
        });
    });

    mutations.observe(document.body, { childList: true, subtree: true });
}
