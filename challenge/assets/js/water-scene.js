/**
 * Lightweight Three.js water fill for the dashboard water tracker.
 * Falls back to CSS bar when WebGL or reduced-motion is preferred.
 */
import * as THREE from 'three';

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const canvas = document.getElementById('waterSceneCanvas');
const visual = document.querySelector('.water-visual');
const tiltControl = document.getElementById('enableWaterTilt');

function showFallback() {
    if (visual) visual.classList.add('use-fallback');
    if (canvas) canvas.style.display = 'none';
}

window.WaterScene = {
    setLevel() {},
    requestTilt: async () => false,
    dispose() {}
};

if (!canvas || !visual || reducedMotion) {
    showFallback();
} else {
    let renderer;
    let animationId = 0;
    let targetLevel = Math.min(1, Math.max(0, (parseFloat(visual.dataset.waterPercent) || 0) / 100));
    let currentLevel = targetLevel;
    let ripple = 0;
    let time = 0;
    let tiltEnabled = false;
    let orientationListening = false;
    const targetTilt = new THREE.Vector2(0, 0);

    try {
        renderer = new THREE.WebGLRenderer({
            canvas,
            antialias: true,
            alpha: true,
            powerPreference: 'low-power'
        });
    } catch (e) {
        showFallback();
    }

    if (renderer) {
        const scene = new THREE.Scene();
        const camera = new THREE.OrthographicCamera(-1, 1, 1, -1, 0.1, 10);
        camera.position.z = 1;

        const uniforms = {
            uTime: { value: 0 },
            uLevel: { value: currentLevel },
            uRipple: { value: 0.35 },
            uSplash: { value: 0 },
            uTilt: { value: new THREE.Vector2(0, 0) },
            uColorTop: { value: new THREE.Color('#E8D5A3') },
            uColorBottom: { value: new THREE.Color('#C4A35A') },
            uFoam: { value: new THREE.Color('#FFFCFA') }
        };

        const material = new THREE.ShaderMaterial({
            transparent: true,
            uniforms,
            vertexShader: `
                varying vec2 vUv;
                void main() {
                    vUv = uv;
                    gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
                }
            `,
            fragmentShader: `
                precision mediump float;
                varying vec2 vUv;
                uniform float uTime;
                uniform float uLevel;
                uniform float uRipple;
                uniform float uSplash;
                uniform vec2 uTilt;
                uniform vec3 uColorTop;
                uniform vec3 uColorBottom;
                uniform vec3 uFoam;

                void main() {
                    float wave = sin(vUv.x * 12.0 + uTime * 5.0) * 0.024
                               + sin(vUv.x * 5.5 - uTime * 3.7) * 0.018
                               + sin(vUv.x * 24.0 + uTime * 7.2) * 0.016 * uRipple
                               + sin(vUv.x * 42.0 - uTime * 9.0) * 0.008 * uSplash;
                    float gravityLean = dot(vUv - vec2(0.5), uTilt);
                    float surface = uLevel + wave + gravityLean;
                    float dist = vUv.y - surface;

                    if (dist > 0.02) {
                        discard;
                    }

                    float depth = clamp(1.0 - vUv.y / max(surface, 0.001), 0.0, 1.0);
                    vec3 water = mix(uColorTop, uColorBottom, depth);
                    float foam = smoothstep(0.035 + uSplash * 0.02, 0.0, abs(dist));
                    float sparkle = smoothstep(0.92, 1.0, sin((vUv.x + vUv.y) * 70.0 + uTime * 12.0)) * uSplash;
                    water = mix(water, uFoam, clamp(foam * 0.95 + sparkle * 0.35, 0.0, 1.0));

                    float alpha = smoothstep(0.02, 0.0, dist) * 0.92;
                    gl_FragColor = vec4(water, alpha);
                }
            `
        });

        const mesh = new THREE.Mesh(new THREE.PlaneGeometry(2, 2), material);
        scene.add(mesh);

        function resize() {
            const rect = visual.getBoundingClientRect();
            const w = Math.max(160, Math.floor(rect.width));
            const h = Math.max(92, Math.min(132, Math.floor(rect.height || w * 0.28)));
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            renderer.setPixelRatio(dpr);
            renderer.setSize(w, h, false);
            canvas.style.width = '100%';
            canvas.style.height = '100%';
        }

        function render(now) {
            if (document.hidden) {
                animationId = 0;
                return;
            }

            time = now * 0.001;
            currentLevel += (targetLevel - currentLevel) * 0.2;
            ripple = Math.max(0.25, ripple - 0.018);
            uniforms.uSplash.value = Math.max(0, uniforms.uSplash.value - 0.022);
            uniforms.uTilt.value.lerp(targetTilt, 0.62);

            uniforms.uTime.value = time;
            uniforms.uLevel.value = currentLevel;
            uniforms.uRipple.value = ripple;
            renderer.render(scene, camera);
            animationId = requestAnimationFrame(render);
        }

        function onOrientation(event) {
            if (!tiltEnabled) return;
            const roll = Number.isFinite(event.gamma) ? event.gamma : 0;
            const pitch = Number.isFinite(event.beta) ? event.beta : 0;
            const x = THREE.MathUtils.clamp(roll / 22, -1, 1) * 0.38;
            const y = THREE.MathUtils.clamp((pitch - 45) / 30, -1, 1) * 0.2;
            targetTilt.set(x, -y);
            ripple = Math.min(1.45, ripple + 0.18);
            uniforms.uSplash.value = Math.min(1, uniforms.uSplash.value + 0.2);
        }

        function startTilt() {
            tiltEnabled = true;
            if (!orientationListening) {
                window.addEventListener('deviceorientation', onOrientation, { passive: true });
                orientationListening = true;
            }
            try {
                localStorage.setItem('waterTiltEnabled', '1');
            } catch (error) {
                // The effect still works when device storage is unavailable.
            }
            if (tiltControl) {
                tiltControl.setAttribute('aria-pressed', 'true');
                tiltControl.classList.add('is-enabled');
                const label = tiltControl.querySelector('span');
                if (label) label.textContent = 'Tilt enabled';
            }
            return true;
        }

        async function requestTilt() {
            if (!('DeviceOrientationEvent' in window)) return false;
            try {
                if (typeof DeviceOrientationEvent.requestPermission === 'function') {
                    const permission = await DeviceOrientationEvent.requestPermission();
                    if (permission !== 'granted') return false;
                }
                return startTilt();
            } catch (error) {
                return false;
            }
        }

        window.WaterScene = {
            setLevel(percent, splash) {
                targetLevel = Math.min(1, Math.max(0, (Number(percent) || 0) / 100));
                if (splash) {
                    ripple = 1.45;
                    uniforms.uSplash.value = 1;
                }
            },
            requestTilt,
            dispose() {
                cancelAnimationFrame(animationId);
                if (orientationListening) {
                    window.removeEventListener('deviceorientation', onOrientation);
                }
                material.dispose();
                mesh.geometry.dispose();
                renderer.dispose();
            }
        };

        resize();
        window.addEventListener('resize', resize);
        if ('ResizeObserver' in window) {
            new ResizeObserver(resize).observe(visual);
        }
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && !animationId) {
                animationId = requestAnimationFrame(render);
            }
        });
        visual.classList.add('has-webgl');
        animationId = requestAnimationFrame(render);

        if ('DeviceOrientationEvent' in window) {
            window.addEventListener('waterTiltPreferenceChanged', (event) => {
                if (event.detail && event.detail.enabled) {
                    startTilt();
                } else {
                    tiltEnabled = false;
                    targetTilt.set(0, 0);
                    try {
                        localStorage.removeItem('waterTiltEnabled');
                    } catch (error) {
                        // Ignore unavailable local storage.
                    }
                }
            });

            if (tiltControl) {
                tiltControl.hidden = false;
                tiltControl.addEventListener('click', async () => {
                    const enabled = await requestTilt();
                    if (!enabled) {
                        const label = tiltControl.querySelector('span');
                        if (label) label.textContent = 'Tilt permission denied';
                        tiltControl.classList.add('is-denied');
                    }
                });
            }

            try {
                if (localStorage.getItem('waterTiltEnabled') === '1') {
                    startTilt();
                }
            } catch (error) {
                // Ignore unavailable local storage.
            }
        }
    }
}
