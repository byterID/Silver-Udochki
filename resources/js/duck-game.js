import { send } from './http';

/*
 * Мини-игра «Утиная зорька». Полёты уток приходят с сервера (FlightPlanner),
 * браузер рисует их и запоминает выстрелы: когда (мс от начала раунда) и куда.
 * Награду считает сервер (ShotJudge), здесь попадания только для картинки,
 * поэтому формула положения утки и выбор цели повторяют серверные один в один.
 */

/** Запас к паузам ружья: время округляется вниз, а сервер считает строго. */
const GUARD_MS = 25;
const QUIP_MS = 2800;
const DOG_SPEED = 230;
const GRAVITY = 900;

const LOOKS = {
    mallard: { size: 1, body: '#7a5a3c', belly: '#c2ae92', head: '#1f6b3a', wing: '#5d4630', speculum: '#3b5bdb', beak: '#e9b949', collar: true },
    teal: { size: 0.8, body: '#8c8f88', belly: '#d6cfba', head: '#8a3b1f', wing: '#6b6e67', speculum: '#2f9e44', beak: '#3a3a3a', stripe: '#2f9e44' },
    golden: { size: 0.8, body: '#e2b007', belly: '#f7dd72', head: '#f2c94c', wing: '#c99700', speculum: '#fff3bf', beak: '#f08c00', glow: true },
};

/** Где утка в момент t. null — ещё не взлетела или уже улетела. Как ShotJudge::position(). */
export function position(path, t) {
    const last = path.length - 1;
    if (t < path[0][0] || t > path[last][0]) return null;

    for (let i = 0; i < last; i++) {
        const [t0, x0, y0] = path[i];
        const [t1, x1, y1] = path[i + 1];
        if (t <= t1) {
            const f = (t - t0) / (t1 - t0);
            return [x0 + (x1 - x0) * f, y0 + (y1 - y0) * f, Math.sign(x1 - x0)];
        }
    }
    return null;
}

/** Ближайшая к прицелу живая утка в радиусе попадания. Как цикл в ShotJudge::judge(). */
export function target(ducks, t, x, y) {
    let best = null;
    let nearest = Infinity;

    for (const duck of ducks) {
        if (duck.hit) continue;
        const at = position(duck.path, t);
        if (!at) continue;

        const distance = Math.hypot(at[0] - x, at[1] - y);
        if (distance <= duck.radius && distance < nearest) {
            best = duck;
            nearest = distance;
        }
    }
    return best;
}

const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));
const pick = (list) => list[Math.floor(Math.random() * list.length)];

/** Детерминированный генератор: пейзаж не меняется при пересчёте размеров. */
function lcg(seed) {
    let s = seed >>> 0;
    return () => (s = (Math.imul(s, 1664525) + 1013904223) >>> 0) / 4294967296;
}

function scenery({ width: W, height: H, horizon: Y }) {
    const rnd = lcg(20261019);
    const between = (a, b) => a + rnd() * (b - a);
    const trees = (step, low, high) => {
        const list = [];
        for (let x = -10; x < W + 20; x += between(...step)) list.push([x, between(low, high), between(9, 15)]);
        return list;
    };
    const stalks = (from, to, gap, low, high, base) => {
        const list = [];
        for (let x = from; x < to; x += between(...gap)) {
            list.push({ x, h: between(low, high), lean: between(-6, 6), cattail: rnd() < 0.3, phase: between(0, 6.28), base });
        }
        return list;
    };

    return {
        far: trees([12, 22], 24, 52),
        near: trees([18, 32], 36, 84),
        band: stalks(-5, W + 5, [4, 10], 14, 44, Y + 18),
        front: [...stalks(-10, 175, [7, 14], 70, 190, H + 4), ...stalks(W - 160, W + 10, [7, 14], 60, 170, H + 4)],
        clouds: Array.from({ length: 5 }, () => ({ x: between(0, W), y: between(30, Y * 0.45), s: between(0.6, 1.3), v: between(3, 9) })),
        shimmer: Array.from({ length: 46 }, () => ({ x: between(0, W), y: between(Y + 6, H - 8), w: between(16, 70), phase: between(0, 6.28) })),
    };
}

class Hunt {
    constructor(canvas, coat, events) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.coat = coat;
        this.events = events;
        this.reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        this.plan = null;
        this.ducks = [];
        this.floating = [];
        this.effects = [];
        this.shots = [];
        this.ended = true;
        this.aim = { x: 480, y: 220, visibleUntil: 0, mouse: false };
        this.recoil = 0;
        this.flash = 0;
        this.setField({ width: 960, height: 540, horizon: 380 });
        this.dog = { x: this.home.x, y: this.home.y, facing: 1, chasing: null, carrying: null };

        this.resize = this.resize.bind(this);
        this.observer = new ResizeObserver(this.resize);
        this.observer.observe(canvas);
        this.bindPointer();
    }

    setField(field) {
        this.field = field;
        this.view = scenery(field);
        this.home = { x: 112, y: field.height - 34 };
        this.canvas.style.aspectRatio = `${field.width} / ${field.height}`;
    }

    resize() {
        const ratio = window.devicePixelRatio || 1;
        this.canvas.width = Math.round(this.canvas.clientWidth * ratio);
        this.canvas.height = Math.round(this.canvas.clientHeight * ratio);
    }

    bindPointer() {
        const toField = (e) => {
            const rect = this.canvas.getBoundingClientRect();
            return [(e.clientX - rect.left) / rect.width * this.field.width, (e.clientY - rect.top) / rect.height * this.field.height];
        };

        this.canvas.addEventListener('pointermove', (e) => {
            [this.aim.x, this.aim.y] = toField(e);
            this.aim.mouse = e.pointerType === 'mouse';
        });
        this.canvas.addEventListener('pointerleave', () => { this.aim.mouse = false; });
        this.canvas.addEventListener('pointerdown', (e) => {
            [this.aim.x, this.aim.y] = toField(e);
            this.aim.mouse = e.pointerType === 'mouse';
            this.aim.visibleUntil = performance.now() + 500;
            if (e.button === 2) this.reload();
            else if (e.button === 0) this.fire(this.aim.x, this.aim.y);
        });
        this.canvas.addEventListener('contextmenu', (e) => e.preventDefault());
    }

    run() {
        let last = performance.now();
        const frame = (now) => {
            const dt = Math.min(0.05, (now - last) / 1000);
            last = now;
            this.update(now, dt);
            this.draw(now);
            this.raf = requestAnimationFrame(frame);
        };
        this.raf = requestAnimationFrame(frame);
    }

    stop() {
        cancelAnimationFrame(this.raf);
        this.observer.disconnect();
    }

    begin(plan) {
        if (plan.field.width !== this.field.width || plan.field.height !== this.field.height || plan.field.horizon !== this.field.horizon) {
            this.setField(plan.field);
        }
        this.plan = plan;
        this.ducks = plan.ducks.map((duck) => ({ ...duck, look: LOOKS[duck.kind] ?? LOOKS.mallard, state: 'waiting', facing: 1, hit: false }));
        this.floating = [];
        this.dog.chasing = null;
        this.shots = [];
        this.shells = plan.magazine;
        this.reloadUntil = 0;
        this.lastShot = -Infinity;
        this.lastTick = 0;
        this.ended = false;
        this.t0 = performance.now();
    }

    elapsed() {
        return performance.now() - this.t0;
    }

    fire(x, y) {
        if (this.ended) return;
        const t = Math.floor(this.elapsed());
        if (t > this.plan.duration_ms || this.reloadUntil) return;
        if (this.shells === 0) {
            this.reload();
            return;
        }
        if (t - this.lastShot < this.plan.min_interval_ms + GUARD_MS) return;

        const shot = {
            t,
            x: Math.round(clamp(x, 0, this.field.width) * 10) / 10,
            y: Math.round(clamp(y, 0, this.field.height) * 10) / 10,
        };
        this.shots.push(shot);
        this.lastShot = t;
        this.shells--;
        this.recoil = 1;
        this.flash = 1;
        this.effects.push({ type: 'ring', x: shot.x, y: shot.y, age: 0 });

        const duck = target(this.ducks, t, shot.x, shot.y);
        if (duck) this.down(duck, t);
        else this.events.miss();

        if (this.shells === 0) this.reload();
        this.tick(true);
    }

    reload() {
        if (this.ended || this.reloadUntil || this.shells === this.plan.magazine) return;
        this.reloadUntil = this.elapsed() + this.plan.reload_ms + GUARD_MS;
        this.events.reload();
        this.tick(true);
    }

    /** Попадание: утка замирает, падает в воду, собака плывёт за ней. */
    down(duck, t) {
        const [x, y, dir] = position(duck.path, t);
        const { width: W, height: H, horizon: Y } = this.field;

        Object.assign(duck, {
            hit: true,
            state: 'stunned',
            x,
            y,
            facing: dir || duck.facing,
            stunUntil: performance.now() + 220,
            vy: -60,
            spin: 0,
            landX: clamp(x + (Math.random() - 0.5) * 60, 200, W - 60),
            landY: Y + 45 + Math.random() * (H - Y - 100),
        });

        for (let i = 0; i < 9; i++) {
            this.effects.push({
                type: 'feather', x, y, age: 0, color: i % 3 ? duck.look.body : duck.look.belly,
                vx: (Math.random() - 0.5) * 160, vy: -Math.random() * 120, spin: Math.random() * 6,
            });
        }
        this.effects.push({ type: 'popup', x, y: y - 24, age: 0, text: `+${duck.points}` });
        this.events.hit(duck);
    }

    tick(force = false) {
        const now = performance.now();
        if (!force && now - this.lastTick < 100) return;
        this.lastTick = now;

        const t = this.elapsed();
        this.events.tick({
            timeLeft: Math.max(0, this.plan.duration_ms - t),
            progress: clamp(t / this.plan.duration_ms, 0, 1),
            shells: this.shells,
            reloading: Boolean(this.reloadUntil),
        });
    }

    update(now, dt) {
        if (!this.ended) {
            const t = now - this.t0;
            if (this.reloadUntil && t >= this.reloadUntil) {
                this.shells = this.plan.magazine;
                this.reloadUntil = 0;
                this.tick(true);
            }

            for (const duck of this.ducks) {
                if (duck.state === 'waiting' && t >= duck.path[0][0]) duck.state = 'flying';
                if (duck.state !== 'flying') continue;

                const at = position(duck.path, t);
                if (!at) {
                    duck.state = 'escaped';
                    this.events.escaped(duck);
                    continue;
                }
                [duck.x, duck.y] = at;
                duck.facing = at[2] || duck.facing;
            }

            if (t >= this.plan.duration_ms) {
                this.ended = true;
                this.tick(true);
                this.events.end(this.shots.slice());
            } else {
                this.tick();
            }
        }

        for (const duck of this.ducks) {
            if (duck.state === 'stunned' && now >= duck.stunUntil) duck.state = 'falling';
            if (duck.state !== 'falling') continue;

            duck.vy += GRAVITY * dt;
            duck.y += duck.vy * dt;
            duck.x += (duck.landX - duck.x) * Math.min(1, dt * 2);
            duck.spin += dt * 7;
            if (duck.y >= duck.landY) {
                duck.state = 'down';
                this.effects.push({ type: 'splash', x: duck.landX, y: duck.landY, age: 0 });
                this.floating.push({ x: duck.landX, y: duck.landY, look: duck.look, facing: duck.facing, seed: duck.id });
            }
        }

        this.updateDog(dt);

        for (const fx of this.effects) {
            fx.age += dt;
            if (fx.type === 'feather') {
                fx.vy += 60 * dt;
                fx.vx *= 1 - dt * 1.5;
                fx.x += fx.vx * dt + Math.sin(fx.age * 5) * 0.6;
                fx.y += fx.vy * dt;
            }
        }
        this.effects = this.effects.filter((fx) => fx.age < ({ ring: 0.25, popup: 0.9, splash: 0.7, feather: 1.6 })[fx.type]);
        this.recoil = Math.max(0, this.recoil - dt * 6);
        this.flash = Math.max(0, this.flash - dt * 14);
    }

    /** Ретривер по очереди приносит подбитых уток на берег. */
    updateDog(dt) {
        const dog = this.dog;
        if (!dog.chasing && !dog.carrying && this.floating.length) dog.chasing = this.floating[0];

        const goal = dog.chasing ?? this.home;
        const dx = goal.x - dog.x;
        const dy = goal.y - dog.y;
        const distance = Math.hypot(dx, dy);
        const step = DOG_SPEED * dt;

        dog.swimming = distance > 4;
        if (distance > step) {
            dog.x += dx / distance * step;
            dog.y += dy / distance * step;
            if (Math.abs(dx) > 2) dog.facing = Math.sign(dx);
            return;
        }

        dog.x = goal.x;
        dog.y = goal.y;
        if (dog.chasing) {
            this.floating = this.floating.filter((d) => d !== dog.chasing);
            dog.carrying = dog.chasing;
            dog.chasing = null;
        } else if (dog.carrying) {
            dog.carrying = null; // сдал добычу на берегу
        }
    }

    draw(now) {
        const { ctx, field } = this;
        const { width: W, height: H } = field;
        const s = this.canvas.width / W;
        const seconds = now / 1000;
        ctx.setTransform(s, 0, 0, s, 0, 0);

        this.drawSky(seconds);
        this.drawLake(seconds);

        for (const duck of this.ducks) {
            if (duck.state === 'flying') this.drawFlyingDuck(duck, now);
        }

        this.drawStalks(this.view.band, seconds, '#3d4f2a', '#5b3a1e', 1);

        for (const duck of this.floating) {
            if (duck === this.dog.carrying) continue;
            const bob = Math.sin(seconds * 2.2 + duck.seed) * 1.8;
            drawDuck(ctx, duck.look, duck.x, duck.y + bob, duck.facing, -0.2, 0.25);
        }
        for (const fx of this.effects) if (fx.type === 'splash') this.drawSplash(fx);

        this.drawDog(seconds);

        for (const duck of this.ducks) {
            if (duck.state === 'stunned') drawDuck(ctx, duck.look, duck.x, duck.y, duck.facing, 1, 0);
            if (duck.state === 'falling') drawDuck(ctx, duck.look, duck.x, duck.y, duck.facing, Math.sin(duck.spin * 3) * 0.3, duck.spin * duck.facing);
        }
        for (const fx of this.effects) this.drawEffect(fx);

        this.drawStalks(this.view.front, seconds, '#22331b', '#4a2c14', this.reduced ? 0 : 1);
        this.drawGun();
        this.drawCrosshair(now);

        if (this.flash > 0 && !this.reduced) {
            ctx.fillStyle = `rgba(255, 244, 214, ${0.12 * this.flash})`;
            ctx.fillRect(0, 0, W, H);
        }
    }

    drawSky(seconds) {
        const { ctx } = this;
        const { width: W, horizon: Y } = this.field;

        const sky = ctx.createLinearGradient(0, 0, 0, Y);
        sky.addColorStop(0, '#24406b');
        sky.addColorStop(0.55, '#c97b63');
        sky.addColorStop(1, '#f6c27a');
        ctx.fillStyle = sky;
        ctx.fillRect(0, 0, W, Y + 1);

        // Солнце встаёт из-за леса
        const sx = W * 0.7;
        const glow = ctx.createRadialGradient(sx, Y - 6, 10, sx, Y - 6, 170);
        glow.addColorStop(0, 'rgba(255, 236, 170, 0.9)');
        glow.addColorStop(1, 'rgba(255, 236, 170, 0)');
        ctx.fillStyle = glow;
        ctx.fillRect(sx - 170, Y - 176, 340, 176);
        ctx.fillStyle = '#fff1c1';
        ctx.beginPath();
        ctx.arc(sx, Y - 6, 38, Math.PI, 0);
        ctx.fill();

        ctx.fillStyle = 'rgba(255, 228, 205, 0.55)';
        for (const c of this.view.clouds) {
            const x = ((c.x + seconds * c.v) % (W + 200)) - 100;
            ctx.beginPath();
            ctx.ellipse(x, c.y, 46 * c.s, 9 * c.s, 0, 0, Math.PI * 2);
            ctx.ellipse(x + 26 * c.s, c.y - 6 * c.s, 26 * c.s, 9 * c.s, 0, 0, Math.PI * 2);
            ctx.fill();
        }

        this.drawForest(this.view.far, '#3b4f4a');
        this.drawForest(this.view.near, '#1d3328');
    }

    drawForest(trees, color) {
        const { ctx } = this;
        const Y = this.field.horizon;
        ctx.fillStyle = color;
        ctx.beginPath();
        for (const [x, h, w] of trees) {
            ctx.moveTo(x - w, Y + 2);
            ctx.lineTo(x, Y - h);
            ctx.lineTo(x + w, Y + 2);
        }
        ctx.fill();
    }

    drawLake(seconds) {
        const { ctx } = this;
        const { width: W, height: H, horizon: Y } = this.field;

        const water = ctx.createLinearGradient(0, Y, 0, H);
        water.addColorStop(0, '#d39a6f');
        water.addColorStop(0.15, '#5f7f86');
        water.addColorStop(1, '#1d3a45');
        ctx.fillStyle = water;
        ctx.fillRect(0, Y, W, H - Y);

        // Солнечная дорожка
        const path = ctx.createLinearGradient(0, Y, 0, H);
        path.addColorStop(0, 'rgba(255, 236, 170, 0.55)');
        path.addColorStop(1, 'rgba(255, 236, 170, 0)');
        ctx.fillStyle = path;
        ctx.beginPath();
        ctx.moveTo(W * 0.7 - 30, Y);
        ctx.lineTo(W * 0.7 + 30, Y);
        ctx.lineTo(W * 0.7 + 90, H);
        ctx.lineTo(W * 0.7 - 90, H);
        ctx.fill();

        ctx.strokeStyle = 'rgba(255, 245, 225, 0.35)';
        ctx.lineWidth = 1.5;
        for (const line of this.view.shimmer) {
            const a = Math.sin(seconds * 1.4 + line.phase);
            if (a < 0) continue;
            ctx.globalAlpha = a;
            ctx.beginPath();
            ctx.moveTo(line.x, line.y);
            ctx.lineTo(line.x + line.w, line.y);
            ctx.stroke();
        }
        ctx.globalAlpha = 1;
    }

    drawStalks(stalks, seconds, stem, cattail, sway) {
        const { ctx } = this;
        ctx.lineCap = 'round';
        for (const r of stalks) {
            const lean = r.lean + Math.sin(seconds * 0.9 + r.phase) * 3 * sway;
            const top = [r.x + lean, r.base - r.h];
            ctx.strokeStyle = stem;
            ctx.lineWidth = r.h > 60 ? 3.2 : 2;
            ctx.beginPath();
            ctx.moveTo(r.x, r.base);
            ctx.quadraticCurveTo(r.x, r.base - r.h * 0.6, top[0], top[1]);
            ctx.stroke();
            if (r.cattail) {
                ctx.fillStyle = cattail;
                ctx.beginPath();
                ctx.ellipse(top[0], top[1] + r.h * 0.12, r.h > 60 ? 4.5 : 3, r.h * 0.1, lean / 60, 0, Math.PI * 2);
                ctx.fill();
            }
        }
    }

    drawFlyingDuck(duck, now) {
        const flap = Math.sin(now / (duck.kind === 'mallard' ? 70 : 52) + duck.id * 1.7);
        if (duck.look.glow) {
            const g = this.ctx.createRadialGradient(duck.x, duck.y, 4, duck.x, duck.y, 46);
            g.addColorStop(0, 'rgba(255, 220, 90, 0.5)');
            g.addColorStop(1, 'rgba(255, 220, 90, 0)');
            this.ctx.fillStyle = g;
            this.ctx.fillRect(duck.x - 46, duck.y - 46, 92, 92);
        }
        drawDuck(this.ctx, duck.look, duck.x, duck.y, duck.facing, flap, 0);
    }

    drawDog(seconds) {
        const { ctx, dog, coat } = this;
        const bob = Math.sin(seconds * (dog.swimming ? 7 : 2.5)) * (dog.swimming ? 1.5 : 1);

        ctx.save();
        ctx.translate(dog.x, dog.y + bob);

        // Вода вокруг шеи и «усы» за плывущей собакой
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.45)';
        ctx.lineWidth = 1.5;
        ctx.beginPath();
        ctx.ellipse(0, 9, 19, 4, 0, 0, Math.PI * 2);
        if (dog.swimming) {
            ctx.moveTo(-dog.facing * 14, 10);
            ctx.lineTo(-dog.facing * 46, 4);
            ctx.moveTo(-dog.facing * 14, 10);
            ctx.lineTo(-dog.facing * 46, 18);
        }
        ctx.stroke();

        ctx.scale(dog.facing, 1);
        ctx.fillStyle = coat.head;
        ctx.beginPath();
        ctx.ellipse(0, -6, 14, 13, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#e9d9b8';
        ctx.beginPath();
        ctx.ellipse(13, -1, 10, 7, 0.1, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = coat.ears;
        ctx.beginPath();
        ctx.ellipse(-7, -1, 6, 11, 0.35, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#2b1a0e';
        ctx.beginPath();
        ctx.arc(22, -4, 3, 0, Math.PI * 2);
        ctx.arc(5, -11, 2, 0, Math.PI * 2);
        ctx.fill();

        if (dog.carrying) drawDuck(ctx, dog.carrying.look, 18, 6, -1, 0, 1.2, 0.7);
        ctx.restore();
    }

    drawSplash(fx) {
        const { ctx } = this;
        const k = fx.age / 0.7;
        ctx.strokeStyle = `rgba(255, 255, 255, ${0.8 * (1 - k)})`;
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.ellipse(fx.x, fx.y + 6, 10 + 34 * k, 3 + 8 * k, 0, 0, Math.PI * 2);
        ctx.stroke();
        ctx.fillStyle = `rgba(255, 255, 255, ${0.7 * (1 - k)})`;
        for (let i = -2; i <= 2; i++) {
            ctx.beginPath();
            ctx.arc(fx.x + i * 9 * (1 + k), fx.y - 18 * Math.sin(k * Math.PI) * (1 - Math.abs(i) * 0.25), 2.2, 0, Math.PI * 2);
            ctx.fill();
        }
    }

    drawEffect(fx) {
        const { ctx } = this;
        if (fx.type === 'feather') {
            ctx.save();
            ctx.globalAlpha = Math.max(0, 1 - fx.age / 1.6);
            ctx.translate(fx.x, fx.y);
            ctx.rotate(fx.spin * fx.age + Math.sin(fx.age * 6));
            ctx.fillStyle = fx.color;
            ctx.beginPath();
            ctx.ellipse(0, 0, 5, 1.8, 0, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
        } else if (fx.type === 'ring') {
            const k = fx.age / 0.25;
            ctx.strokeStyle = `rgba(255, 250, 230, ${1 - k})`;
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(fx.x, fx.y, 6 + 18 * k, 0, Math.PI * 2);
            ctx.stroke();
        } else if (fx.type === 'popup') {
            ctx.globalAlpha = Math.max(0, 1 - fx.age / 0.9);
            ctx.font = 'bold 22px ui-sans-serif, system-ui, sans-serif';
            ctx.textAlign = 'center';
            ctx.lineWidth = 4;
            ctx.strokeStyle = '#3b2412';
            ctx.strokeText(fx.text, fx.x, fx.y - fx.age * 40);
            ctx.fillStyle = '#fde68a';
            ctx.fillText(fx.text, fx.x, fx.y - fx.age * 40);
            ctx.globalAlpha = 1;
        }
    }

    /** Двустволка охотника: из-под нижнего края, стволы смотрят на прицел. */
    drawGun() {
        const { ctx, aim } = this;
        const { width: W, height: H } = this.field;
        const px = W / 2;
        const py = H + 46;
        const angle = Math.atan2(aim.y - py, aim.x - px);

        ctx.save();
        ctx.translate(px, py);
        ctx.rotate(Math.max(-Math.PI + 0.25, Math.min(-0.25, angle)));
        ctx.translate(-this.recoil * 14, 0);

        ctx.fillStyle = '#6b3d1f';
        ctx.beginPath();
        ctx.moveTo(-30, -15);
        ctx.lineTo(92, -11);
        ctx.lineTo(92, 11);
        ctx.lineTo(-30, 17);
        ctx.fill();
        ctx.fillStyle = '#8a5530';
        ctx.fillRect(10, -10, 70, 4);

        ctx.fillStyle = '#2b2f33';
        ctx.fillRect(88, -9, 120, 8);
        ctx.fillRect(88, 1, 120, 8);
        ctx.fillStyle = '#5c636a';
        ctx.fillRect(88, -8, 120, 2);
        ctx.fillRect(88, 2, 120, 2);

        if (this.flash > 0) {
            ctx.fillStyle = `rgba(255, 200, 80, ${this.flash})`;
            ctx.beginPath();
            for (let i = 0; i < 8; i++) {
                const a = (i / 8) * Math.PI * 2;
                const r = i % 2 ? 9 : 22;
                ctx.lineTo(214 + Math.cos(a) * r * 1.3, Math.sin(a) * r * 0.7);
            }
            ctx.fill();
        }
        ctx.restore();
    }

    drawCrosshair(now) {
        const { ctx, aim } = this;
        if (!aim.mouse && now > aim.visibleUntil) return;

        const reloading = !this.ended && this.reloadUntil;
        ctx.lineCap = 'round';
        for (const [color, width] of [['rgba(0, 0, 0, 0.55)', 4], [reloading ? '#fca5a5' : '#fffbeb', 2]]) {
            ctx.strokeStyle = color;
            ctx.lineWidth = width;
            ctx.beginPath();
            ctx.arc(aim.x, aim.y, 15, 0, Math.PI * 2);
            for (const [dx, dy] of [[1, 0], [-1, 0], [0, 1], [0, -1]]) {
                ctx.moveTo(aim.x + dx * 7, aim.y + dy * 7);
                ctx.lineTo(aim.x + dx * 22, aim.y + dy * 22);
            }
            ctx.stroke();
        }

        if (reloading) {
            const left = (this.reloadUntil - this.elapsed()) / (this.plan.reload_ms + GUARD_MS);
            ctx.strokeStyle = '#f59e0b';
            ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.arc(aim.x, aim.y, 26, -Math.PI / 2, -Math.PI / 2 + Math.PI * 2 * (1 - clamp(left, 0, 1)));
            ctx.stroke();
        }
    }
}

/** Утка в профиль: flap от -1 (крылья вниз) до 1 (вверх), rotation в радианах. */
function drawDuck(ctx, look, x, y, facing, flap, rotation, scale = 1) {
    const wing = (color, f) => {
        ctx.fillStyle = color;
        ctx.beginPath();
        ctx.moveTo(8, -3);
        ctx.quadraticCurveTo(0, -4 - 26 * f, -17, -4 - 22 * f);
        ctx.quadraticCurveTo(-9, -1, -10, -3);
        ctx.fill();
    };

    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(rotation);
    ctx.scale(facing * look.size * scale, look.size * scale);

    wing(shade(look.wing), flap * 0.8);

    ctx.fillStyle = look.body;
    ctx.beginPath();
    ctx.moveTo(-21, -3);
    ctx.lineTo(-33, -8);
    ctx.lineTo(-30, 3);
    ctx.fill();
    ctx.beginPath();
    ctx.ellipse(0, 0, 24, 12, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = look.belly;
    ctx.beginPath();
    ctx.ellipse(3, 5, 17, 6, 0, 0, Math.PI * 2);
    ctx.fill();

    ctx.fillStyle = look.head;
    ctx.beginPath();
    ctx.ellipse(17, -8, 6, 8, 0.3, 0, Math.PI * 2);
    ctx.arc(22, -15, 8, 0, Math.PI * 2);
    ctx.fill();
    if (look.collar) {
        ctx.strokeStyle = '#f8fafc';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(18, -6, 6, 0.4, 2.6);
        ctx.stroke();
    }
    if (look.stripe) {
        ctx.strokeStyle = look.stripe;
        ctx.lineWidth = 2.5;
        ctx.beginPath();
        ctx.moveTo(25, -16);
        ctx.lineTo(16, -14);
        ctx.stroke();
    }
    ctx.fillStyle = look.beak;
    ctx.beginPath();
    ctx.moveTo(28, -17);
    ctx.quadraticCurveTo(39, -14, 29, -11);
    ctx.fill();
    ctx.fillStyle = '#111';
    ctx.beginPath();
    ctx.arc(24, -17, 1.6, 0, Math.PI * 2);
    ctx.fill();

    wing(look.wing, flap);
    ctx.fillStyle = look.speculum;
    ctx.fillRect(-6, -4 - 4 * flap, 8, 2.5);

    ctx.restore();
}

function shade(hex) {
    const n = parseInt(hex.slice(1), 16);
    const f = (v) => Math.round(v * 0.7);
    return `rgb(${f(n >> 16)}, ${f((n >> 8) & 255)}, ${f(n & 255)})`;
}

export default function registerDuckGame(Alpine) {
    Alpine.data('duckGame', (setup) => {
        // Сцена и её объекты живут вне реактивности Alpine: 60 кадров в секунду через Proxy ни к чему
        let hunt = null;
        let round = null;
        let pendingShots = null;
        let quipAt = 0;

        return {
            phase: 'idle', // idle → starting → playing → judging → result
            error: '',
            timeLeft: 0,
            progress: 0,
            magazine: 2,
            shells: 2,
            reloading: false,
            hits: 0,
            ducks: 0,
            score: 0,
            result: null,
            rate: 0,
            dailyLeft: setup.dailyLeft,
            recent: setup.recent,
            quip: 'Утки поднимаются на рассвете. Ружьё заряжено, собака на берегу.',

            init() {
                hunt = new Hunt(this.$refs.canvas, setup.coat, {
                    tick: (s) => Object.assign(this, s),
                    hit: (duck) => {
                        this.hits++;
                        this.score += duck.points;
                        if (duck.kind === 'golden') this.say('golden', 1, true);
                        else this.say('hit', 0.35);
                    },
                    miss: () => this.say('miss', 0.45),
                    escaped: () => this.say('escaped', 0.4),
                    reload: () => this.say('reload', 0.12),
                    end: (shots) => {
                        pendingShots = shots;
                        this.finish();
                    },
                });
                hunt.run();
            },

            destroy() {
                hunt?.stop();
            },

            get seconds() {
                return Math.ceil(this.timeLeft / 1000);
            },

            get busy() {
                return ['starting', 'playing', 'judging'].includes(this.phase);
            },

            async start() {
                if (this.busy) return;
                this.phase = 'starting';
                this.error = '';

                const { ok, data } = await safeSend('POST', setup.startUrl);
                if (!ok) {
                    this.phase = this.result ? 'result' : 'idle';
                    this.error = data?.message ?? 'Не получилось начать раунд. Попробуйте ещё раз.';
                    return;
                }

                round = data;
                Object.assign(this, {
                    hits: 0,
                    score: 0,
                    result: null,
                    ducks: data.plan.ducks.length,
                    magazine: data.plan.magazine,
                    shells: data.plan.magazine,
                    rate: data.plan.rate,
                    reloading: false,
                    timeLeft: data.plan.duration_ms,
                    progress: 0,
                    phase: 'playing',
                });
                this.say('start', 1, true, 'Тихо… Слышишь? Крылья. Сейчас поднимутся.');
                hunt.begin(data.plan);
            },

            reload() {
                if (this.phase === 'playing') hunt.reload();
            },

            onKey(e) {
                if (this.phase !== 'playing' || e.target.closest('input, textarea, select')) return;
                if (['r', 'R', 'к', 'К'].includes(e.key)) this.reload();
            },

            async finish(attempt = 0) {
                this.phase = 'judging';
                this.error = '';
                const { ok, status, data } = await safeSend('POST', round.finish_url, { shots: pendingShots });

                // Часы браузера чуть спешат: сервер просит подождать, пока утки точно улетят
                if (status === 425 && attempt < 3) {
                    setTimeout(() => this.finish(attempt + 1), 1500);
                    return;
                }

                this.phase = 'result';
                if (!ok) {
                    this.result = null;
                    this.error = data?.message ?? 'Не удалось подвести итог: нет связи с сервером.';
                    // Сетевую ошибку можно повторить, решение сервера — нет
                    if (status !== 0) pendingShots = null;
                    return;
                }

                pendingShots = null;
                this.result = data;
                this.dailyLeft = data.daily_left;
                this.recent = [{ at: 'только что', hits: data.hits, ducks: data.ducks, score: data.score, reward: data.reward }, ...this.recent].slice(0, setup.recentLimit);
                window.dispatchEvent(new CustomEvent('wallet-changed', { detail: { balance: data.balance } }));

                const share = data.hits / Math.max(1, data.ducks);
                this.say(share >= 0.75 ? 'great' : share >= 0.4 ? 'good' : data.hits > 0 ? 'poor' : 'none', 1, true);
            },

            get canRetry() {
                return this.phase === 'result' && !this.result && pendingShots !== null;
            },

            retry() {
                if (this.canRetry) this.finish();
            },

            /** Деньги за раунд урезал суточный лимит. */
            get capped() {
                return this.result !== null && this.result.reward < this.result.score * this.rate;
            },

            say(kind, chance, force = false, text = null) {
                const now = performance.now();
                if (!force && (Math.random() > chance || now - quipAt < QUIP_MS)) return;

                const line = text ?? pick(setup.quips[kind] ?? []);
                if (!line) return;
                quipAt = now;
                this.quip = line;
            },
        };
    });
}

/** send() бросает при обрыве связи; тогда status 0. */
async function safeSend(method, url, body = null) {
    try {
        return await send(method, url, body);
    } catch {
        return { ok: false, status: 0, data: null };
    }
}
