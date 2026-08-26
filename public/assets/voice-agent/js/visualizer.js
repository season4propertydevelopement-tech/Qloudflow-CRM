/**
 * VoiceVisualizer - Dynamic Glowing Voice Orb & Audio Waveform Visualizer
 * Inspired by Ravan.ai Agni Engine & Gemini Live Interface
 */
class VoiceVisualizer {
    constructor(canvasId) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) return;
        this.ctx = this.canvas.getContext('2d');
        this.state = 'idle'; // 'idle' | 'listening' | 'thinking' | 'speaking'
        this.audioContext = null;
        this.analyser = null;
        this.dataArray = null;
        this.animationFrameId = null;
        this.angle = 0;
        this.particles = [];
        this.agentColor = '#6366f1';
        this.agentSecondaryColor = '#06b6d4';

        this.initCanvasSize();
        this.createParticles();
        window.addEventListener('resize', () => this.initCanvasSize());
        this.startRenderLoop();
    }

    initCanvasSize() {
        if (!this.canvas) return;
        const rect = this.canvas.parentElement.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        this.width = rect.width || 340;
        this.height = rect.height || 340;
        this.canvas.width = this.width * dpr;
        this.canvas.height = this.height * dpr;
        this.ctx.scale(dpr, dpr);
    }

    createParticles() {
        this.particles = [];
        for (let i = 0; i < 36; i++) {
            this.particles.push({
                angle: (i / 36) * Math.PI * 2,
                baseRadius: 65,
                radius: 65,
                speed: 0.02 + Math.random() * 0.02,
                size: 2 + Math.random() * 3,
                opacity: 0.3 + Math.random() * 0.7
            });
        }
    }

    setAgentTheme(primaryColor, secondaryColor) {
        this.agentColor = primaryColor || '#6366f1';
        this.agentSecondaryColor = secondaryColor || '#06b6d4';
    }

    setState(newState) {
        this.state = newState;
    }

    connectMicrophoneStream(stream) {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!this.audioContext) {
                this.audioContext = new AudioContext();
            }
            if (this.audioContext.state === 'suspended') {
                this.audioContext.resume();
            }
            const source = this.audioContext.createMediaStreamSource(stream);
            this.analyser = this.audioContext.createAnalyser();
            this.analyser.fftSize = 128;
            this.dataArray = new Uint8Array(this.analyser.frequencyBinCount);
            source.connect(this.analyser);
        } catch (e) {
            console.warn('Could not connect visualizer analyser to mic stream:', e);
        }
    }

    getAudioEnergy() {
        if (!this.analyser || !this.dataArray) {
            if (this.state === 'speaking') {
                return 40 + Math.sin(this.angle * 4) * 30 + Math.random() * 20;
            }
            if (this.state === 'thinking') {
                return 15 + Math.sin(this.angle * 6) * 10;
            }
            return 0;
        }
        this.analyser.getByteFrequencyData(this.dataArray);
        let sum = 0;
        for (let i = 0; i < this.dataArray.length; i++) {
            sum += this.dataArray[i];
        }
        return sum / this.dataArray.length;
    }

    startRenderLoop() {
        const render = () => {
            this.render();
            this.animationFrameId = requestAnimationFrame(render);
        };
        render();
    }

    render() {
        if (!this.ctx || !this.canvas) return;

        const cx = this.width / 2;
        const cy = this.height / 2;
        const energy = this.getAudioEnergy();
        this.angle += 0.025;

        this.ctx.clearRect(0, 0, this.width, this.height);

        // State Color Palette
        let primary = this.agentColor;
        let secondary = this.agentSecondaryColor;
        let glowRadius = 55;
        let pulseFactor = 1;

        if (this.state === 'idle') {
            pulseFactor = 1 + Math.sin(this.angle * 1.5) * 0.05;
            glowRadius = 50 * pulseFactor;
        } else if (this.state === 'listening') {
            primary = '#10b981'; // Emerald Green
            secondary = '#34d399';
            pulseFactor = 1 + (energy / 255) * 0.6 + Math.sin(this.angle * 3) * 0.08;
            glowRadius = 60 * pulseFactor;
        } else if (this.state === 'thinking') {
            primary = '#f59e0b'; // Amber Golden
            secondary = '#fbbf24';
            pulseFactor = 1 + Math.sin(this.angle * 5) * 0.15;
            glowRadius = 62 * pulseFactor;
        } else if (this.state === 'speaking') {
            primary = this.agentColor;
            secondary = this.agentSecondaryColor;
            pulseFactor = 1 + (energy / 255) * 0.8 + Math.sin(this.angle * 4) * 0.1;
            glowRadius = 68 * pulseFactor;
        }

        // 1. Draw Outer Ambient Glow Aurora
        const outerGrad = this.ctx.createRadialGradient(cx, cy, 10, cx, cy, glowRadius * 1.8);
        outerGrad.addColorStop(0, this.hexToRgba(primary, 0.45));
        outerGrad.addColorStop(0.5, this.hexToRgba(secondary, 0.2));
        outerGrad.addColorStop(1, 'rgba(0, 0, 0, 0)');

        this.ctx.fillStyle = outerGrad;
        this.ctx.beginPath();
        this.ctx.arc(cx, cy, glowRadius * 1.8, 0, Math.PI * 2);
        this.ctx.fill();

        // 2. Draw Orbiting Fluid Waves
        const numWaves = 4;
        for (let w = 0; w < numWaves; w++) {
            this.ctx.save();
            this.ctx.beginPath();
            const waveAngleOffset = (w / numWaves) * Math.PI * 2 + this.angle * (w % 2 === 0 ? 1 : -1);
            const r = glowRadius * (0.85 + w * 0.12);

            for (let a = 0; a <= Math.PI * 2; a += 0.1) {
                const waveDeform = Math.sin(a * 4 + waveAngleOffset) * (6 + (energy / 30) * (w + 1));
                const x = cx + Math.cos(a) * (r + waveDeform);
                const y = cy + Math.sin(a) * (r + waveDeform);
                if (a === 0) this.ctx.moveTo(x, y);
                else this.ctx.lineTo(x, y);
            }

            this.ctx.closePath();
            this.ctx.strokeStyle = this.hexToRgba(w % 2 === 0 ? primary : secondary, 0.35 - w * 0.06);
            this.ctx.lineWidth = 2;
            this.ctx.stroke();
            this.ctx.restore();
        }

        // 3. Draw Core 3D Glowing Orb
        const coreGrad = this.ctx.createRadialGradient(
            cx - glowRadius * 0.25,
            cy - glowRadius * 0.25,
            glowRadius * 0.1,
            cx,
            cy,
            glowRadius
        );
        coreGrad.addColorStop(0, '#ffffff');
        coreGrad.addColorStop(0.2, this.hexToRgba(primary, 0.95));
        coreGrad.addColorStop(0.7, this.hexToRgba(secondary, 0.85));
        coreGrad.addColorStop(1, this.hexToRgba('#050608', 0.9));

        this.ctx.save();
        this.ctx.shadowColor = primary;
        this.ctx.shadowBlur = 30;
        this.ctx.fillStyle = coreGrad;
        this.ctx.beginPath();
        this.ctx.arc(cx, cy, glowRadius * 0.85, 0, Math.PI * 2);
        this.ctx.fill();
        this.ctx.restore();

        // 4. Draw Orbiting Sparkle Particles
        this.particles.forEach(p => {
            p.angle += p.speed * (this.state === 'speaking' ? 2 : 1);
            const dynamicRadius = p.baseRadius * pulseFactor + Math.sin(p.angle * 3) * 8;
            const px = cx + Math.cos(p.angle) * dynamicRadius;
            const py = cy + Math.sin(p.angle) * dynamicRadius;

            this.ctx.fillStyle = this.hexToRgba('#ffffff', p.opacity * (this.state === 'idle' ? 0.4 : 0.9));
            this.ctx.beginPath();
            this.ctx.arc(px, py, p.size, 0, Math.PI * 2);
            this.ctx.fill();
        });

        // 5. Draw Dynamic Ripple Rings if Speaking or Listening
        if (this.state === 'speaking' || this.state === 'listening') {
            const rippleR = (this.angle * 45) % (this.width * 0.45);
            const rippleOpacity = Math.max(0, 1 - rippleR / (this.width * 0.45));

            this.ctx.strokeStyle = this.hexToRgba(primary, rippleOpacity * 0.5);
            this.ctx.lineWidth = 1.5;
            this.ctx.beginPath();
            this.ctx.arc(cx, cy, glowRadius + rippleR, 0, Math.PI * 2);
            this.ctx.stroke();
        }
    }

    hexToRgba(hex, alpha = 1) {
        if (!hex || !hex.startsWith('#')) return `rgba(99, 102, 241, ${alpha})`;
        let c = hex.substring(1);
        if (c.length === 3) {
            c = c.split('').map(x => x + x).join('');
        }
        const num = parseInt(c, 16);
        const r = (num >> 16) & 255;
        const g = (num >> 8) & 255;
        const b = num & 255;
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }
}
