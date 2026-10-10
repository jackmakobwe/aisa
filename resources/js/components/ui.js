/**
 * Keeps an unsent message in sessionStorage (this tab only) so switching
 * language does not lose it. Cleared on submit and by Quick exit.
 */
export function draftKeeper() {
    return {
        key: 'aisa.draft',

        init() {
            try {
                const saved = sessionStorage.getItem(this.key);
                if (saved && !this.$refs.input.value) {
                    this.$refs.input.value = saved;
                }
            } catch (e) {
                // Storage unavailable: nothing to restore.
            }
        },

        save(event) {
            try {
                sessionStorage.setItem(this.key, event.target.value);
            } catch (e) {
                // Ignore.
            }
        },

        clear() {
            try {
                sessionStorage.removeItem(this.key);
            } catch (e) {
                // Ignore.
            }
        },
    };
}

export function copyCode() {
    return {
        copied: false,
        code: '',
        copiedText: '',

        init() {
            this.code = this.$el.dataset.code || '';
            this.copiedText = this.$el.dataset.copiedText || '';
        },

        get notCopied() {
            return !this.copied;
        },

        get announcement() {
            return this.copied ? this.copiedText : '';
        },

        async copy() {
            const text = this.code;
            try {
                await navigator.clipboard.writeText(text);
            } catch (e) {
                // Fallback for browsers without the async clipboard API.
                const range = document.createRange();
                range.selectNodeContents(this.$refs.code);
                const selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
                document.execCommand('copy');
                selection.removeAllRanges();
            }
            this.copied = true;
            setTimeout(() => {
                this.copied = false;
            }, 2500);
        },

        print() {
            window.print();
        },
    };
}

export function menu() {
    return {
        open: false,

        get navClass() {
            return this.open ? '!block' : '';
        },

        toggle() {
            this.open = !this.open;
        },

        close() {
            this.open = false;
        },
    };
}

/** Enables a submit button only once a required checkbox is ticked. */
export function consentToggle() {
    return {
        agreed: false,

        init() {
            this.agreed = Boolean(this.$refs.box?.checked);
        },

        sync(event) {
            this.agreed = event.target.checked;
        },

        get blocked() {
            return !this.agreed;
        },
    };
}

/** Inserts a saved response into the admin reply box. */
export function savedResponses() {
    return {
        target: '',

        init() {
            this.target = this.$el.dataset.target || '';
        },

        insert(event) {
            const option = event.target.selectedOptions[0];
            const body = option?.dataset.body;
            if (!body) {
                return;
            }
            const box = document.getElementById(this.target);
            box.value = box.value ? `${box.value.trimEnd()}\n\n${body}` : body;
            box.focus();
            event.target.value = '';
        },
    };
}
