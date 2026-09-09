export function registerNotifications(Alpine) {
    Alpine.store('notifications', {
        booted: false,
        open: false,
        unread: 0,
        chatUnread: 0,
        items: [],
        toast: null,
        toastTimer: null,
        userId: null,
        markReadTemplate: '',
        markAllUrl: '',
        csrfToken: '',

        boot(config) {
            if (this.booted) {
                return;
            }

            this.booted = true;
            this.userId = config.userId;
            this.unread = Number(config.unread || 0);
            this.chatUnread = Number(config.chatUnread || 0);
            this.items = Array.isArray(config.items) ? config.items : [];
            this.markReadTemplate = config.markReadTemplate;
            this.markAllUrl = config.markAllUrl;
            this.csrfToken = config.csrfToken;

            this.subscribeToEcho();
        },

        subscribeToEcho() {
            if (! window.Echo || ! this.userId) {
                return;
            }

            window.Echo.private(`App.Models.User.${this.userId}`)
                .listen('.UserNotificationBroadcasted', (notification) => {
                    this.handleNotification(notification);
                });

            window.Echo.private(`users.${this.userId}.conversations`)
                .listen('.MessageSent', (message) => {
                    this.handleInboxMessage(message);
                });
        },

        handleNotification(notification) {
            const item = this.normalize(notification);

            if (! item.id || this.items.some((existing) => existing.id === item.id)) {
                return;
            }

            if (this.isActiveConversation(item.conversation_id)) {
                this.markRead(item.id, true);

                return;
            }

            this.items.unshift(item);
            this.unread += 1;
            this.showToast(item);
        },

        handleInboxMessage(message) {
            if (! message?.conversation_id || this.isActiveConversation(message.conversation_id)) {
                return;
            }

            this.chatUnread += 1;
            this.moveConversationToTop(message);
        },

        isActiveConversation(conversationId) {
            if (! conversationId || window.activeConversationId == null) {
                return false;
            }

            return String(window.activeConversationId) === String(conversationId);
        },

        normalize(notification) {
            return {
                id: notification.id,
                kind: notification.kind ?? '',
                title: notification.title ?? 'New notification',
                body: notification.body ?? '',
                url: notification.url ?? '/',
                actor_name: notification.actor_name ?? '',
                actor_avatar_url: notification.actor_avatar_url ?? null,
                conversation_id: notification.conversation_id ?? null,
                read_at: notification.read_at ?? null,
                created_at: notification.created_at ?? 'Just now',
            };
        },

        showToast(item) {
            this.toast = item;

            if (this.toastTimer) {
                window.clearTimeout(this.toastTimer);
            }

            this.toastTimer = window.setTimeout(() => {
                this.toast = null;
            }, 4000);
        },

        async openItem(item) {
            if (! item.read_at) {
                await this.markRead(item.id, false);
            }

            if (item.url) {
                window.location.href = item.url;
            }
        },

        async markRead(id, silent) {
            const url = this.markReadTemplate.replace('__ID__', encodeURIComponent(id));

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                });

                if (! response.ok) {
                    return;
                }

                const payload = await response.json();
                this.unread = Number(payload.unread_count ?? Math.max(0, this.unread - 1));
                this.items = this.items.map((item) => {
                    if (item.id !== id) {
                        return item;
                    }

                    return { ...item, read_at: item.read_at || new Date().toISOString() };
                });
            } catch (error) {
                if (! silent) {
                    console.error(error);
                }
            }
        },

        async markAllRead() {
            try {
                const response = await fetch(this.markAllUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                });

                if (! response.ok) {
                    return;
                }

                this.unread = 0;
                this.items = this.items.map((item) => ({
                    ...item,
                    read_at: item.read_at || new Date().toISOString(),
                }));
            } catch (error) {
                console.error(error);
            }
        },

        moveConversationToTop(message) {
            const list = document.querySelector('[data-conversation-list]');

            if (! list) {
                return;
            }

            const row = list.querySelector(`[data-conversation-id="${message.conversation_id}"]`);

            if (! row) {
                return;
            }

            list.prepend(row);

            const preview = row.querySelector('[data-conversation-preview]');

            if (preview) {
                preview.textContent = message.body ?? '';
                preview.classList.add('font-semibold', 'text-gray-700');
                preview.classList.remove('text-gray-500');
            }

            const time = row.querySelector('[data-conversation-time]');

            if (time) {
                time.textContent = message.created_at_human ?? 'Just now';
            }

            const title = row.querySelector('[data-conversation-title]');

            if (title) {
                title.classList.add('font-bold', 'text-forest-900');
                title.classList.remove('font-medium', 'text-forest-800');
            }

            let badge = row.querySelector('[data-conversation-unread]');

            if (! badge) {
                const holder = row.querySelector('[data-conversation-unread-holder]');

                if (holder) {
                    badge = document.createElement('span');
                    badge.setAttribute('data-conversation-unread', '1');
                    badge.className = 'inline-flex min-w-5 items-center justify-center rounded-full bg-lime-400 px-1.5 text-[11px] font-bold text-forest-900';
                    badge.textContent = '1';
                    holder.appendChild(badge);
                }
            } else {
                const next = Number(badge.textContent || '0') + 1;
                badge.textContent = String(next);
            }
        },
    });
}
