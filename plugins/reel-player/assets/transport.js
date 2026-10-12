/* Visual transport only: native audio remains independent of reel inertia. */
(() => {
    'use strict';
    class TapeRoomTransport {
        constructor() { this.reset(); }
        reset() {
            this.running = false; this.speed = 0; this.slack = 8; this.slackVelocity = 0;
            this.from = 0; this.elapsed = 0; this.duration = 0;
        }
        setRunning(running) {
            running = Boolean(running);
            if (running === this.running) return;
            this.running = running; this.from = this.speed; this.elapsed = 0;
            this.duration = running ? .38 : .9;
        }
        step(seconds) {
            if (!Number.isFinite(seconds) || seconds <= 0) return;
            const dt = Math.min(1, seconds);
            const steps = Math.ceil(dt * 120), h = dt / steps;
            let phase, desired;
            for (let i = 0; i < steps; i++) {
                this.elapsed = Math.min(this.duration, this.elapsed + h);
                phase = this.duration ? this.elapsed / this.duration : 1;
                const ease = phase * phase * (3 - 2 * phase);
                this.speed = this.from + ((this.running ? 1 : 0) - this.from) * ease;
                // Use small physical steps even after a dropped display frame:
                // braking follows elapsed time instead of stretching indefinitely.
                const release = !this.running && phase < 1 ? Math.sin(Math.PI * phase) * 3 : 0;
                desired = (1 - this.speed) * 8 + release;
                this.slackVelocity += ((desired - this.slack) * 220 - this.slackVelocity * 25) * h;
                this.slack += this.slackVelocity * h;
            }
            if (phase === 1 && Math.abs(desired - this.slack) < .02 && Math.abs(this.slackVelocity) < .08) {
                this.slack = desired; this.slackVelocity = 0;
            }
        }
        get moving() {
            return this.speed > 0 || this.elapsed < this.duration || this.slackVelocity !== 0 || this.slack !== (this.running ? 0 : 8);
        }
        static tapeEntry(cx, radius, direction) {
            const rollerX = direction === 1 ? 378 : 822;
            // The centre line sits half a ribbon width outside each surface.
            const rollerRadius = 29.75, tapeRadius = radius + 1.75;
            const dx = rollerX - cx, dy = 215, length = Math.hypot(dx, dy);
            const ux = dx / length, uy = dy / length;
            const along = (tapeRadius - rollerRadius) / length;
            const across = direction * Math.sqrt(1 - along ** 2);
            const nx = along * ux - across * uy, ny = along * uy + across * ux;
            const start = [cx + tapeRadius * nx, 210 + tapeRadius * ny];
            const end = [rollerX + rollerRadius * nx, 425 + rollerRadius * ny];
            const x = end[0] - start[0], y = end[1] - start[1];
            const tangentLength = Math.hypot(x, y), tx = x / tangentLength, ty = y / tangentLength;
            return {x:start[0],y:start[1],tx,ty};
        }
        static tapePath(cx, radius, direction, slack = 0) {
            const rollerX = direction === 1 ? 378 : 822, rollerRadius = 29.75, tapeRadius = radius + 1.75;
            const contact = TapeRoomTransport.tapeEntry(cx, radius, direction);
            const {tx,ty} = contact, nx = (contact.x - cx) / tapeRadius, ny = (contact.y - 210) / tapeRadius;
            const start = [contact.x,contact.y], end = [rollerX + rollerRadius * nx,425 + rollerRadius * ny];
            const x = end[0] - start[0], y = end[1] - start[1];
            const hiddenStart = [start[0] - nx * 6 - tx * 8, start[1] - ny * 6 - ty * 8];
            const coordinate = (px, py) => `${px.toFixed(2)} ${py.toFixed(2)}`;
            // The dynamic pack mask covers the ribbon's complete leading cap.
            // A short hidden lead-in emerges with the exact outer tangent rather
            // than exposing a detached flat tip on the clear reel face.
            const entry = `M${coordinate(...hiddenStart)} C${coordinate(hiddenStart[0] + tx * 4,hiddenStart[1] + ty * 4)} ${coordinate(start[0] - tx * 4,start[1] - ty * 4)} ${coordinate(...start)}`;
            const sag = Math.max(0, Math.min(12, Number(slack) || 0)) * .75;
            const point = (part, drop = 0) => `${(start[0] + x * part).toFixed(2)} ${(start[1] + y * part + drop).toFixed(2)}`;
            // Both end derivatives stay on the true outer tangent. The two
            // halves share a tangent too, so slack never kinks at a guide.
            return `${entry} C${point(1/6)} ${point(1/3,sag)} ${point(1/2,sag)} C${point(2/3,sag)} ${point(5/6)} ${point(1)} A${rollerRadius} ${rollerRadius} 0 0 ${direction === 1 ? 0 : 1} ${rollerX + direction * rollerRadius} 425 L${rollerX} 425`;
        }
    }
    if (typeof module !== 'undefined' && module.exports) module.exports = TapeRoomTransport;
    if (typeof window !== 'undefined') window.TapeRoomTransport = TapeRoomTransport;
})();
