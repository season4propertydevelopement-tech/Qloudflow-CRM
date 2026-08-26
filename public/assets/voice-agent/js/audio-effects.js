/**
 * AudioEffects - Web Audio API Telephony Sound Synthesizer
 * Zero external audio assets required. Generates realistic DTMF, Ring tones, Chimes, and Haptics.
 */
class TelephonyAudioEffects {
    constructor() {
        this.ctx = null;
        this.ringInterval = null;
        this.dtmfFreqs = {
            '1': [697, 1209], '2': [697, 1336], '3': [697, 1477],
            '4': [770, 1209], '5': [770, 1336], '6': [770, 1477],
            '7': [852, 1209], '8': [852, 1336], '9': [852, 1477],
            '*': [941, 1209], '0': [941, 1336], '#': [941, 1477]
        };
    }

    init() {
        if (!this.ctx) {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            this.ctx = new AudioContext();
        }
        if (this.ctx.state === 'suspended') {
            this.ctx.resume();
        }
    }

    /**
     * Play DTMF Dual-Tone Multi-Frequency for dialpad
     */
    playDTMF(key) {
        this.init();
        const freqs = this.dtmfFreqs[key];
        if (!freqs) return;

        const osc1 = this.ctx.createOscillator();
        const osc2 = this.ctx.createOscillator();
        const gainNode = this.ctx.createGain();

        osc1.type = 'sine';
        osc2.type = 'sine';
        osc1.frequency.setValueAtTime(freqs[0], this.ctx.currentTime);
        osc2.frequency.setValueAtTime(freqs[1], this.ctx.currentTime);

        gainNode.gain.setValueAtTime(0.15, this.ctx.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + 0.18);

        osc1.connect(gainNode);
        osc2.connect(gainNode);
        gainNode.connect(this.ctx.destination);

        osc1.start();
        osc2.start();
        osc1.stop(this.ctx.currentTime + 0.18);
        osc2.stop(this.ctx.currentTime + 0.18);
    }

    /**
     * Start playing phone ringing tone (Cadence: 1.5s ring, 2.5s pause)
     */
    startRinging() {
        this.init();
        this.stopRinging();

        const playSingleRing = () => {
            if (!this.ctx) return;
            const osc1 = this.ctx.createOscillator();
            const osc2 = this.ctx.createOscillator();
            const gain = this.ctx.createGain();

            osc1.type = 'sine';
            osc2.type = 'sine';
            // Standard US/IN Phone Ring frequencies: 440Hz + 480Hz
            osc1.frequency.setValueAtTime(440, this.ctx.currentTime);
            osc2.frequency.setValueAtTime(480, this.ctx.currentTime);

            gain.gain.setValueAtTime(0.08, this.ctx.currentTime);
            gain.gain.setValueAtTime(0.08, this.ctx.currentTime + 1.2);
            gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + 1.4);

            osc1.connect(gain);
            osc2.connect(gain);
            gain.connect(this.ctx.destination);

            osc1.start();
            osc2.start();
            osc1.stop(this.ctx.currentTime + 1.4);
            osc2.stop(this.ctx.currentTime + 1.4);
        };

        playSingleRing();
        this.ringInterval = setInterval(playSingleRing, 3500);
    }

    /**
     * Stop ringing
     */
    stopRinging() {
        if (this.ringInterval) {
            clearInterval(this.ringInterval);
            this.ringInterval = null;
        }
    }

    /**
     * Play Call Connected Chime (Ascending Pleasant Major Third)
     */
    playCallConnected() {
        this.init();
        const now = this.ctx.currentTime;
        const notes = [523.25, 659.25, 783.99]; // C5, E5, G5

        notes.forEach((freq, idx) => {
            const osc = this.ctx.createOscillator();
            const gain = this.ctx.createGain();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, now + idx * 0.09);

            gain.gain.setValueAtTime(0.12, now + idx * 0.09);
            gain.gain.exponentialRampToValueAtTime(0.001, now + idx * 0.09 + 0.25);

            osc.connect(gain);
            gain.connect(this.ctx.destination);

            osc.start(now + idx * 0.09);
            osc.stop(now + idx * 0.09 + 0.25);
        });
    }

    /**
     * Play Call Ended Tone (Busy disconnect signal or pleasant soft drop)
     */
    playCallEnded() {
        this.init();
        const now = this.ctx.currentTime;
        const notes = [440, 349.23, 261.63]; // A4, F4, C4

        notes.forEach((freq, idx) => {
            const osc = this.ctx.createOscillator();
            const gain = this.ctx.createGain();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, now + idx * 0.11);

            gain.gain.setValueAtTime(0.1, now + idx * 0.11);
            gain.gain.exponentialRampToValueAtTime(0.001, now + idx * 0.11 + 0.22);

            osc.connect(gain);
            gain.connect(this.ctx.destination);

            osc.start(now + idx * 0.11);
            osc.stop(now + idx * 0.11 + 0.22);
        });
    }

    /**
     * Play UI Button Click Haptic Click
     */
    playClick() {
        this.init();
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();

        osc.type = 'triangle';
        osc.frequency.setValueAtTime(1200, this.ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(200, this.ctx.currentTime + 0.04);

        gain.gain.setValueAtTime(0.06, this.ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + 0.04);

        osc.connect(gain);
        gain.connect(this.ctx.destination);

        osc.start();
        osc.stop(this.ctx.currentTime + 0.04);
    }
}

// Global instance
window.telephonyAudio = new TelephonyAudioEffects();
