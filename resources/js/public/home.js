// Promo videos on the public page.
//
// Strip: videos play by themselves, muted and looping, but only while they are
// on screen, so a phone never decodes them all at once. (No `autoplay`
// attribute: the browser would start off-screen videos after we paused them.)
//
// Full screen: tapping a video opens a story-style viewer with sound that
// moves on to the next video when one ends, and back to the first after the
// last. Tap right = next, left = previous, middle = pause; Esc or X closes.
export default function videoStrip(items = []) {
    return {
        items,
        soundOn: null,
        observer: null,
        visible: new Set(),

        // Full-screen viewer
        isOpen: false,
        current: 0,
        progress: 0,
        paused: false,
        muted: false,

        init() {
            this.rootEl = this.$el;
            this.observer = new IntersectionObserver((entries) => {
                entries.forEach(({ target, isIntersecting }) => {
                    if (isIntersecting) {
                        this.visible.add(target);
                        if (!document.hidden && !this.isOpen) target.play().catch(() => {});
                    } else {
                        this.visible.delete(target);
                        target.pause();
                    }
                });
            }, { threshold: 0.5 });
            this.stripVideos().forEach((video) => this.observer.observe(video));

            // Pause while the tab is in the background; resume what is on screen.
            this.onVisibility = () => {
                if (document.hidden) {
                    this.stripVideos().forEach((video) => video.pause());
                    this.$refs.player?.pause();
                } else if (this.isOpen) {
                    if (!this.paused) this.play();
                } else {
                    this.visible.forEach((video) => video.play().catch(() => {}));
                }
            };
            document.addEventListener('visibilitychange', this.onVisibility);

            // Leaving browser full screen (Esc, back gesture) also closes the viewer.
            this.onFullscreenChange = () => {
                if (!document.fullscreenElement && this.isOpen) this.close();
            };
            document.addEventListener('fullscreenchange', this.onFullscreenChange);
        },

        destroy() {
            this.observer?.disconnect();
            document.removeEventListener('visibilitychange', this.onVisibility);
            document.removeEventListener('fullscreenchange', this.onFullscreenChange);
        },

        stripVideos() {
            return this.rootEl.querySelectorAll('.video-strip video');
        },

        toggleSound(id) {
            this.soundOn = this.soundOn === id ? null : id;
            this.stripVideos().forEach((video) => {
                video.muted = Number(video.dataset.id) !== this.soundOn;
            });
        },

        open(index) {
            this.current = index;
            this.isOpen = true;
            this.paused = false;
            this.muted = false; // opened by a tap, so sound is allowed
            this.stripVideos().forEach((video) => video.pause());
            document.documentElement.classList.add('overflow-hidden');

            this.$nextTick(() => {
                // Real full screen where the browser allows it (not on iPhone);
                // the viewer covers the page either way.
                this.$refs.viewer.requestFullscreen?.().catch(() => {});
                this.load();
            });
        },

        close() {
            if (!this.isOpen) return;
            this.isOpen = false;
            this.stopPlayer();
            document.documentElement.classList.remove('overflow-hidden');
            if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
            this.visible.forEach((video) => video.play().catch(() => {}));
        },

        // Detach the source so nothing keeps playing (or downloading) once the
        // viewer is closed, even if a play() was still on its way.
        stopPlayer() {
            const player = this.$refs.player;
            player.pause();
            player.removeAttribute('src');
            player.load();
        },

        load() {
            if (!this.isOpen) return;
            const player = this.$refs.player;
            this.progress = 0;
            player.src = this.items[this.current].src;
            player.muted = this.muted;
            this.play();
        },

        play() {
            if (!this.isOpen) return;
            const player = this.$refs.player;
            this.paused = false;
            player.play().then(() => {
                if (!this.isOpen) this.stopPlayer();
            }).catch((error) => {
                if (!this.isOpen || error.name === 'AbortError') return;
                // Sound blocked by the browser: keep going without it.
                this.muted = true;
                player.muted = true;
                player.play().catch(() => {});
            });
        },

        go(step) {
            if (!this.isOpen) return;
            const count = this.items.length;
            this.current = (this.current + step + count) % count;
            this.load();
        },

        togglePause() {
            const player = this.$refs.player;
            if (player.paused) {
                this.play();
            } else {
                player.pause();
                this.paused = true;
            }
        },

        toggleMute() {
            this.muted = !this.muted;
            this.$refs.player.muted = this.muted;
        },

        onTime(event) {
            const { currentTime, duration } = event.target;
            this.progress = duration ? Math.min(100, (currentTime / duration) * 100) : 0;
        },

        // Width of each progress bar: done, playing, or still to come.
        barWidth(index) {
            if (index < this.current) return '100%';
            if (index > this.current) return '0%';
            return `${this.progress}%`;
        },
    };
}
