/** Михалыч моргает через случайные промежутки, иногда дважды подряд. */
export default function registerSeller(Alpine) {
    Alpine.data('seller', () => ({
        blink: false,
        timer: null,

        init() {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            this.schedule();
        },

        destroy() {
            clearTimeout(this.timer);
        },

        schedule() {
            this.timer = setTimeout(() => this.doBlink(Math.random() < 0.2), 2500 + Math.random() * 3500);
        },

        doBlink(twice) {
            this.blink = true;
            this.timer = setTimeout(() => {
                this.blink = false;
                this.timer = twice
                    ? setTimeout(() => this.doBlink(false), 180)
                    : (this.schedule(), this.timer);
            }, 120);
        },
    }));
}
