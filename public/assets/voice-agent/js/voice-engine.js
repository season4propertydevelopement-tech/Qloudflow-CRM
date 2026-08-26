/**
 * VoiceEngine - Ultra-Fast Real-Time Speech Recognition & Sub-Second Neural TTS
 * Built for High Accuracy Indian English & Hinglish Speech on Windows Chrome / Edge
 */

class VoiceEngine {
    constructor(options = {}) {
        this.onUserSpeechStart = options.onUserSpeechStart || (() => {});
        this.onUserInterim = options.onUserInterim || (() => {});
        this.onUserTranscript = options.onUserTranscript || (() => {});
        this.onAgentSpeechStart = options.onAgentSpeechStart || (() => {});
        this.onAgentSpeechEnd = options.onAgentSpeechEnd || (() => {});
        this.onError = options.onError || (() => {});
        this.onStateChange = options.onStateChange || (() => {});

        this.recognition = null;
        this.isListening = false;
        this.isSpeaking = false;
        this.isMuted = false;
        this.activePersona = null;
        this.activeLang = 'en-IN';
        this.silenceTimer = null;
        this.speakSafetyTimer = null;
        this.currentAudio = null;
        this.activeBlobUrl = null;
        this.speechCounter = 0;
        this.accumulatedText = '';
        this.latestInterim = '';
        this.mediaStream = null;

        this.initSpeechRecognition();
        this.unlockAudioContext();
    }

    unlockAudioContext() {
        const unlock = () => {
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (AudioCtx) {
                    const ctx = new AudioCtx();
                    ctx.resume().then(() => ctx.close());
                }
            } catch (e) {}
            document.removeEventListener('click', unlock);
            document.removeEventListener('touchstart', unlock);
        };
        document.addEventListener('click', unlock, { once: true });
        document.addEventListener('touchstart', unlock, { once: true });
    }

    initSpeechRecognition() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) {
            console.warn('SpeechRecognition API not supported.');
            return;
        }

        this.recognition = new SpeechRecognition();
        this.recognition.continuous = true;
        this.recognition.interimResults = true;
        this.recognition.maxAlternatives = 1;
        this.recognition.lang = this.activeLang;

        this.recognition.onstart = () => {
            if (!this.isSpeaking) {
                this.onStateChange('listening');
            }
        };

        this.recognition.onresult = (event) => {
            if (this.isMuted || this.isSpeaking) return;

            let interim = '';
            let final = '';

            let hasFinal = false;
            for (let i = event.resultIndex; i < event.results.length; ++i) {
                const item = event.results[i];
                if (item.isFinal) {
                    final += ' ' + item[0].transcript;
                    hasFinal = true;
                } else {
                    interim += ' ' + item[0].transcript;
                }
            }

            if (final.trim().length > 0) {
                this.accumulatedText += final;
            }
            this.latestInterim = interim;

            const fullText = (this.accumulatedText + ' ' + this.latestInterim).trim();

            if (fullText.length > 0) {
                this.onUserSpeechStart();
                this.onUserInterim(fullText);
                this.resetSilenceTimer(hasFinal ? 380 : 650);
            }
        };

        this.recognition.onerror = (event) => {
            if (event.error === 'no-speech' || event.error === 'aborted') return;
            console.warn('SpeechRecognition notice:', event.error);
            this.onError(event.error);
        };

        this.recognition.onend = () => {
            if (this.isListening && !this.isSpeaking) {
                setTimeout(() => {
                    if (this.isListening && !this.isSpeaking) {
                        try {
                            this.recognition.start();
                        } catch (e) {}
                    }
                }, 100);
            }
        };
    }

    setLanguage(langCode) {
        this.activeLang = langCode || 'en-IN';
        if (this.recognition) {
            this.recognition.lang = this.activeLang;
        }
    }

    setPersona(persona) {
        this.activePersona = persona;
        if (persona && persona.lang_code) {
            this.setLanguage(persona.lang_code);
        }
    }

    async getAudioInputDevices() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
            return [];
        }
        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            return devices.filter(d => d.kind === 'audioinput');
        } catch (e) {
            console.warn('Error enumerating audio devices:', e);
            return [];
        }
    }

    async requestMicrophone(deviceId = null) {
        if (this.mediaStream) {
            try {
                this.mediaStream.getTracks().forEach(t => t.stop());
            } catch (e) {}
            this.mediaStream = null;
        }

        try {
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                const audioConstraints = {
                    echoCancellation: true,
                    noiseSuppression: false, // Critical for headsets: prevents hardware mic signal from being filtered out
                    autoGainControl: true
                };

                if (deviceId) {
                    audioConstraints.deviceId = { exact: deviceId };
                }

                this.mediaStream = await navigator.mediaDevices.getUserMedia({
                    audio: audioConstraints
                });
                return this.mediaStream;
            }
        } catch (err) {
            console.warn('Microphone constraint notice, falling back to standard audio:', err);
            try {
                this.mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
                return this.mediaStream;
            } catch (e) {
                console.error('Microphone access denied or unavailable:', e);
            }
        }
        return null;
    }

    startListening() {
        this.isListening = true;
        this.accumulatedText = '';
        this.latestInterim = '';
        if (!this.recognition || this.isSpeaking) return;
        try {
            this.recognition.start();
        } catch (e) {
            // In case already started
        }
    }

    stopListening() {
        this.isListening = false;
        if (this.silenceTimer) {
            clearTimeout(this.silenceTimer);
            this.silenceTimer = null;
        }
        if (this.recognition) {
            try {
                this.recognition.stop();
            } catch (e) {}
        }
    }

    resetSilenceTimer(customTimeout = null) {
        if (this.silenceTimer) {
            clearTimeout(this.silenceTimer);
        }
        const timeoutMs = customTimeout || 500;
        this.silenceTimer = setTimeout(() => {
            const fullText = (this.accumulatedText + ' ' + this.latestInterim).trim();
            if (fullText.length > 0) {
                this.triggerUserTurnComplete();
            }
        }, timeoutMs);
    }

    triggerUserTurnComplete() {
        if (this.silenceTimer) {
            clearTimeout(this.silenceTimer);
            this.silenceTimer = null;
        }
        const fullText = (this.accumulatedText + ' ' + this.latestInterim).trim();
        if (fullText.length > 0) {
            this.accumulatedText = '';
            this.latestInterim = '';
            this.onUserTranscript({ text: fullText, isTurnComplete: true });
        }
    }

    /**
     * Speak with Fast Neural TTS Stream (Swara Neural)
     */
    async speak(text) {
        this.stopAgentSpeech();

        if (!text || text.trim().length === 0) {
            return;
        }

        const cleanText = text.replace(/[*_#`~>\[\]]/g, '').trim();
        const currentId = ++this.speechCounter;

        // Discard any residual speech buffer to eliminate acoustic echo
        this.accumulatedText = '';
        this.latestInterim = '';
        if (this.silenceTimer) {
            clearTimeout(this.silenceTimer);
            this.silenceTimer = null;
        }

        // Hard-stop recognition while speaking
        if (this.recognition) {
            try {
                this.recognition.stop();
            } catch (e) {}
        }

        this.isSpeaking = true;
        this.onAgentSpeechStart();

        return new Promise(async (resolve) => {
            let hasFinished = false;
            const finishSpeaking = () => {
                if (hasFinished || currentId !== this.speechCounter) return;
                hasFinished = true;
                if (this.speakSafetyTimer) {
                    clearTimeout(this.speakSafetyTimer);
                    this.speakSafetyTimer = null;
                }
                this.isSpeaking = false;
                if (this.currentAudio) {
                    try {
                        this.currentAudio.onended = null;
                        this.currentAudio.onerror = null;
                    } catch (e) {}
                    this.currentAudio = null;
                }
                this.onAgentSpeechEnd();

                // Clear speech buffer
                this.accumulatedText = '';
                this.latestInterim = '';

                // Restart microphone after 350ms acoustic room reverb cooldown
                setTimeout(() => {
                    if (this.isListening && !this.isSpeaking && currentId === this.speechCounter) {
                        this.startListening();
                    }
                }, 350);

                resolve();
            };

            // Watchdog Timer: Guarantees speech state NEVER locks
            const estimatedDuration = Math.max(2500, cleanText.length * 75);
            if (this.speakSafetyTimer) clearTimeout(this.speakSafetyTimer);
            this.speakSafetyTimer = setTimeout(finishSpeaking, estimatedDuration + 3500);

            try {
                // 1. Check if phrase matches pre-rendered Neural Audio in /conversation-audio/
                let ttsApiUrl = `/voice-agent/api/tts?text=` + encodeURIComponent(cleanText.substring(0, 300));
                const lower = cleanText.toLowerCase();

                if (lower.includes('whatsapp') && (lower.includes('brochure') || lower.includes('portfolio') || lower.includes('share') || lower.includes('proposal'))) {
                    ttsApiUrl = `/conversation-audio/whatsapp_proposal.mp3`;
                } else if (lower.includes('website') && (lower.includes('15,000') || lower.includes('15000') || lower.includes('package') || lower.includes('cost') || lower.includes('charges'))) {
                    ttsApiUrl = `/conversation-audio/website_cost.mp3`;
                } else if (lower.includes('address') || lower.includes('location') || lower.includes('mumbai') || lower.includes('palghar')) {
                    ttsApiUrl = `/conversation-audio/company_address.mp3`;
                } else if (lower.includes('seo') || lower.includes('smm') || lower.includes('first page') || lower.includes('ranking')) {
                    ttsApiUrl = `/conversation-audio/smm_seo.mp3`;
                } else if (lower.includes('app') && (lower.includes('35,000') || lower.includes('android') || lower.includes('ios'))) {
                    ttsApiUrl = `/conversation-audio/app_development.mp3`;
                } else if (lower.includes('pricing') || lower.includes('starter website') || lower.includes('rate list')) {
                    ttsApiUrl = `/conversation-audio/pricing_overview.mp3`;
                } else if (lower.includes('thank you for speaking with qloudsoft') || lower.includes('shubh rahe')) {
                    ttsApiUrl = `/conversation-audio/call_farewell.mp3`;
                } else if (lower.includes('sun nahi paayi') || lower.includes('dobara bata')) {
                    ttsApiUrl = `/conversation-audio/fallback_repeat.mp3`;
                } else if (lower.includes('namaste') || lower.includes('avni') || lower.includes('qloudsoft')) {
                    ttsApiUrl = `/conversation-audio/greeting_general.mp3`;
                }

                console.log('🔊 Playing Swara Neural Audio Stream:', ttsApiUrl);

                const audio = new Audio(ttsApiUrl);
                audio.playbackRate = 1.0;
                this.currentAudio = audio;

                audio.onended = finishSpeaking;
                audio.onerror = (err) => {
                    console.warn('Neural audio playback error:', err);
                    finishSpeaking();
                };

                const playPromise = audio.play();
                if (playPromise !== undefined) {
                    playPromise.catch(err => {
                        console.warn('Audio autoplay notice:', err);
                        finishSpeaking();
                    });
                }
            } catch (err) {
                console.warn('Neural TTS stream error:', err);
                finishSpeaking();
            }
        });
    }

    stopAgentSpeech() {
        this.speechCounter++;
        if (this.speakSafetyTimer) {
            clearTimeout(this.speakSafetyTimer);
            this.speakSafetyTimer = null;
        }
        if (this.currentAudio) {
            try {
                this.currentAudio.onended = null;
                this.currentAudio.onerror = null;
                this.currentAudio.pause();
                this.currentAudio.currentTime = 0;
                this.currentAudio.src = '';
            } catch (e) {}
            this.currentAudio = null;
        }
        this.isSpeaking = false;
        this.onAgentSpeechEnd();
    }

    toggleMute() {
        this.isMuted = !this.isMuted;
        return this.isMuted;
    }
}
