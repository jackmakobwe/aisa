/**
 * Floating chat button. Without JavaScript the button is a plain link to
 * the conversation (or the start form); with it, it opens a panel.
 */
export function launcher() {
    return {
        open: false,

        get expanded() {
            return this.open ? 'true' : 'false';
        },

        get closed() {
            return !this.open;
        },

        toggle(event) {
            event.preventDefault();
            this.open ? this.close() : this.show();
        },

        show() {
            this.open = true;
            this.$nextTick(() => {
                // An ongoing conversation opens at the newest message; a new one at the greeting.
                const scroller = this.$root.querySelector('[data-chat-scroll="end"]');
                if (scroller) {
                    scroller.scrollTop = scroller.scrollHeight;
                }
                // Avoid popping up the on-screen keyboard on phones.
                const fine = window.matchMedia('(pointer: fine)').matches;
                const target = fine ? this.$root.querySelector('[data-chat-input]') : this.$refs.heading;
                target?.focus();
            });
        },

        close() {
            if (!this.open) {
                return;
            }
            this.open = false;
            this.$nextTick(() => this.$refs.button?.focus());
        },

        useExample(event) {
            const button = event.currentTarget;
            const input = this.$root.querySelector('[data-chat-input]');
            if (input) {
                input.value = button.dataset.text || '';
                input.focus();
            }
            if (this.$refs.module) {
                this.$refs.module.value = button.dataset.module || '';
            }
        },
    };
}
