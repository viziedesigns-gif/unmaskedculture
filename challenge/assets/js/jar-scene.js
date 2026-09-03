import * as THREE from 'three';

const canvas = document.getElementById('jarSceneCanvas');
const wrap = document.getElementById('jarSceneWrap');
const fallback = document.getElementById('jarCssFallback');
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const palette = {
    general: '#f3ead7', encouragement: '#e8d5a3', memory: '#cbd9d2',
    happy_moment: '#ead0c4', gratitude: '#d8cbe0'
};
let entryCount = Number(document.getElementById('jarPage')?.dataset.entryCount || 0);
let initialTypes = [];
try { initialTypes = JSON.parse(document.getElementById('jarPage')?.dataset.entryTypes || '[]'); } catch (error) { initialTypes = []; }

const fallbackApi = {
    addNote() { fallback?.classList.add('is-bouncing'); setTimeout(() => fallback?.classList.remove('is-bouncing'), 500); },
    pullNote() { return Promise.resolve(); },
    setCount(value) { entryCount = Number(value) || 0; },
    dispose() {}
};
window.KintoJarScene = fallbackApi;

if (!canvas || !wrap || reducedMotion) {
    wrap?.classList.add('use-fallback');
} else {
    try {
        const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true, powerPreference: 'low-power' });
        renderer.outputColorSpace = THREE.SRGBColorSpace;
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.05;

        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(34, 1, 0.1, 50);
        camera.position.set(0, 0.6, 9.5);
        camera.lookAt(0, 0.4, 0);
        scene.add(new THREE.HemisphereLight('#fff8e8', '#86755e', 2.2));
        const key = new THREE.DirectionalLight('#fff0c9', 4.2);
        key.position.set(-4, 6, 5);
        scene.add(key);
        const glow = new THREE.PointLight('#c4a35a', 22, 12, 2);
        glow.position.set(3.5, 1.5, 4);
        scene.add(glow);

        const jar = new THREE.Group();
        jar.position.y = -0.25;
        scene.add(jar);

        const glass = new THREE.MeshPhysicalMaterial({
            color: '#e9e0ce', transparent: true, opacity: 0.28, transmission: 0.82,
            thickness: 0.34, roughness: 0.12, metalness: 0, clearcoat: 1,
            clearcoatRoughness: 0.08, side: THREE.DoubleSide, depthWrite: false
        });
        const outline = new THREE.MeshPhysicalMaterial({ color: '#dacaa9', transparent: true, opacity: 0.5, transmission: 0.5, roughness: 0.2 });
        const profile = [
            new THREE.Vector2(0.02, -2.05), new THREE.Vector2(1.62, -2.05),
            new THREE.Vector2(1.86, -1.75), new THREE.Vector2(1.92, 1.05),
            new THREE.Vector2(1.55, 1.5), new THREE.Vector2(1.28, 1.62),
            new THREE.Vector2(1.23, 2.0)
        ];
        jar.add(new THREE.Mesh(new THREE.LatheGeometry(profile, 64), glass));
        const rim = new THREE.Mesh(new THREE.TorusGeometry(1.28, 0.12, 18, 64), outline);
        rim.rotation.x = Math.PI / 2;
        rim.position.y = 2.02;
        jar.add(rim);
        const base = new THREE.Mesh(new THREE.CylinderGeometry(1.62, 1.62, 0.12, 64), outline);
        base.position.y = -2.02;
        jar.add(base);

        const floor = new THREE.Mesh(
            new THREE.CircleGeometry(4, 64),
            new THREE.MeshStandardMaterial({ color: '#ede3d1', roughness: 0.86, transparent: true, opacity: 0.72 })
        );
        floor.rotation.x = -Math.PI / 2;
        floor.position.y = -2.35;
        scene.add(floor);

        const noteGroup = new THREE.Group();
        jar.add(noteGroup);
        const notes = [];
        const typeKeys = Object.keys(palette);

        function notePosition(index) {
            const angle = index * 2.399 + Math.random() * 0.45;
            const radius = Math.min(1.28, 0.25 + Math.sqrt((index % 18) / 18) * 1.05);
            return new THREE.Vector3(Math.cos(angle) * radius, -1.72 + Math.floor(index / 18) * 0.52 + Math.random() * 0.15, Math.sin(angle) * radius * 0.55);
        }

        function makeNote(type = 'general', falling = false) {
            const material = new THREE.MeshStandardMaterial({ color: palette[type] || palette.general, roughness: 0.82, metalness: 0 });
            const mesh = new THREE.Mesh(new THREE.BoxGeometry(0.72, 0.16, 0.5), material);
            const target = notePosition(notes.length);
            mesh.position.copy(falling ? new THREE.Vector3((Math.random() - 0.5) * 0.6, 3.8, 0.2) : target);
            mesh.rotation.set(Math.random() * 0.8, Math.random() * Math.PI, Math.random() * 0.65);
            mesh.userData.target = target;
            mesh.userData.velocity = falling ? 0 : null;
            noteGroup.add(mesh);
            notes.push(mesh);
            return mesh;
        }

        function rebuildCount(value) {
            entryCount = Math.max(0, Number(value) || 0);
            const visible = Math.min(60, entryCount);
            while (notes.length > visible) {
                const note = notes.pop(); noteGroup.remove(note); note.geometry.dispose(); note.material.dispose();
            }
            while (notes.length < visible) makeNote(initialTypes[notes.length] || typeKeys[notes.length % typeKeys.length]);
        }
        rebuildCount(entryCount);

        let animationId = 0;
        let last = 0;
        let shakeUntil = 0;
        function render(now) {
            const delta = Math.min(0.04, (now - last) / 1000 || 0.016); last = now;
            if (shakeUntil > now) {
                jar.rotation.z = Math.sin(now * 0.045) * 0.04;
                jar.position.x = Math.sin(now * 0.062) * 0.055;
            } else {
                jar.rotation.z *= 0.84; jar.position.x *= 0.84;
            }
            notes.forEach(note => {
                if (note.userData.velocity !== null) {
                    note.userData.velocity += 8.5 * delta;
                    note.position.y -= note.userData.velocity * delta;
                    note.rotation.x += delta * 4.5;
                    note.rotation.z += delta * 3.2;
                    if (note.position.y <= note.userData.target.y) {
                        note.position.copy(note.userData.target);
                        note.userData.velocity = null;
                    }
                }
            });
            jar.rotation.y = Math.sin(now * 0.00035) * 0.035;
            renderer.render(scene, camera);
            animationId = requestAnimationFrame(render);
        }

        function resize() {
            const rect = wrap.getBoundingClientRect();
            const width = Math.max(280, Math.floor(rect.width));
            const height = Math.max(400, Math.floor(rect.height));
            renderer.setPixelRatio(Math.min(2, window.devicePixelRatio || 1));
            renderer.setSize(width, height, false);
            camera.aspect = width / height;
            camera.updateProjectionMatrix();
        }

        window.KintoJarScene = {
            addNote(type) {
                entryCount++;
                const added = makeNote(type, true);
                if (notes.length > 60) {
                    window.setTimeout(() => {
                        const removeIndex = notes.findIndex(note => note !== added);
                        if (removeIndex < 0) return;
                        const [oldest] = notes.splice(removeIndex, 1);
                        noteGroup.remove(oldest);
                        oldest.geometry.dispose();
                        oldest.material.dispose();
                    }, 950);
                }
                shakeUntil = performance.now() + 520;
            },
            pullNote() {
                shakeUntil = performance.now() + 850;
                const chosen = notes[Math.floor(Math.random() * notes.length)];
                if (!chosen) return Promise.resolve();
                const start = chosen.position.clone();
                const started = performance.now();
                return new Promise(resolve => {
                    const lift = now => {
                        const t = Math.min(1, (now - started) / 850);
                        const eased = 1 - Math.pow(1 - t, 3);
                        chosen.position.y = start.y + eased * 4.7;
                        chosen.rotation.z += 0.04;
                        chosen.material.opacity = 1 - Math.max(0, (t - 0.75) * 4);
                        chosen.material.transparent = true;
                        if (t < 1) requestAnimationFrame(lift);
                        else { chosen.position.copy(start); chosen.material.opacity = 1; resolve(); }
                    };
                    requestAnimationFrame(lift);
                });
            },
            setCount: rebuildCount,
            dispose() {
                cancelAnimationFrame(animationId);
                renderer.dispose();
            }
        };

        wrap.classList.add('has-webgl');
        resize();
        new ResizeObserver(resize).observe(wrap);
        window.addEventListener('resize', resize);
        animationId = requestAnimationFrame(render);
    } catch (error) {
        wrap.classList.add('use-fallback');
        console.warn('Jar WebGL fallback:', error);
    }
}
