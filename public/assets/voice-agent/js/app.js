/**
 * App - Main Controller for Voice Calling Agent Web Application (Laravel Edition)
 * Connects Visualizer, Audio Effects, Voice Engine, and Backend Gemini Endpoints
 */

document.addEventListener('DOMContentLoaded', () => {
    // CSRF Token Helper
    const csrfToken = window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // DOM Elements
    const visualizerCanvas = document.getElementById('visualizer-canvas');
    const startCallBtn = document.getElementById('btn-start-call');
    const endCallBtn = document.getElementById('btn-end-call');
    const muteBtn = document.getElementById('btn-mute');
    const holdBtn = document.getElementById('btn-hold');
    const keypadBtn = document.getElementById('btn-keypad');
    const callTimerEl = document.getElementById('call-timer');
    const callStateBadge = document.getElementById('call-state-badge');
    const stateText = document.getElementById('state-text');
    const transcriptFeed = document.getElementById('transcript-feed');
    const manualMsgInput = document.getElementById('manual-msg-input');
    const manualSendBtn = document.getElementById('manual-send-btn');
    const callerNameInput = document.getElementById('caller-name-input');
    const callerPhoneInput = document.getElementById('caller-phone-input');
    const micDeviceSelect = document.getElementById('mic-device-select');
    const micStatusText = document.getElementById('mic-status-text');

    // Telemetry DOM
    const hudLatency = document.getElementById('hud-latency');
    const hudSentiment = document.getElementById('hud-sentiment');
    const hudEmotion = document.getElementById('hud-emotion');
    const hudRate = document.getElementById('hud-rate');

    // Modals
    const analyticsModal = document.getElementById('analytics-modal');
    const closeAnalyticsBtn = document.getElementById('btn-close-analytics');
    const dialpadModal = document.getElementById('dialpad-modal');
    const closeDialpadBtn = document.getElementById('btn-close-dialpad');
    const settingsModal = document.getElementById('settings-modal');
    const openSettingsBtn = document.getElementById('btn-open-settings');
    const closeSettingsBtn = document.getElementById('btn-close-settings');
    const testKeyBtn = document.getElementById('btn-test-key');
    const testKeyResult = document.getElementById('test-key-result');
    const customKeyInput = document.getElementById('custom-gemini-key');
    const modelSelect = document.getElementById('select-gemini-model');

    // WhatsApp Follow-up action buttons
    const btnSendWhatsApp = document.getElementById('btn-send-whatsapp');
    const btnCopyWa = document.getElementById('btn-copy-wa');
    const whatsappMsgPreview = document.getElementById('whatsapp-msg-preview');

    // App State
    const defaultPersonaKey = window.SELECTED_PERSONA_ID || 'universal';
    let activePersona = window.PERSONAS_DATA ? (window.PERSONAS_DATA[defaultPersonaKey] || window.PERSONAS_DATA['universal'] || window.PERSONAS_DATA['avni']) : null;
    let callState = 'idle'; // 'idle' | 'ringing' | 'connected' | 'ended'
    let callTimerInterval = null;
    let callSeconds = 0;
    let isHold = false;
    let conversationTranscript = [];
    let currentPostCallData = null;
    let selectedMicId = localStorage.getItem('voice_preferred_mic_id') || '';

    // Initialize Subsystems
    const visualizer = new VoiceVisualizer('visualizer-canvas');
    if (activePersona) {
        visualizer.setAgentTheme(activePersona.color, '#06b6d4');
    }

    // Audio Input Device Enumeration & Auto-Detection
    async function populateMicrophoneDevices() {
        if (!micDeviceSelect) return;
        try {
            // Trigger permission prompt if devices don't have labels yet
            const tempStream = await navigator.mediaDevices.getUserMedia({ audio: true }).catch(() => null);
            const devices = await voiceEngine.getAudioInputDevices();
            if (tempStream) {
                tempStream.getTracks().forEach(t => t.stop());
            }

            micDeviceSelect.innerHTML = '';

            const defaultOpt = document.createElement('option');
            defaultOpt.value = '';
            defaultOpt.textContent = '🎧 Auto-Detect / Default Microphone';
            micDeviceSelect.appendChild(defaultOpt);

            let headsetFound = null;

            devices.forEach((device, idx) => {
                const opt = document.createElement('option');
                opt.value = device.deviceId;
                const label = device.label || `Microphone ${idx + 1}`;
                const lower = label.toLowerCase();
                const isHeadset = lower.includes('headset') || lower.includes('headphone') || lower.includes('earphone') || lower.includes('bluetooth') || lower.includes('usb') || lower.includes('hands-free');

                if (isHeadset) {
                    opt.textContent = `🎧 ${label} (Headset Mic)`;
                    if (!headsetFound) headsetFound = device.deviceId;
                } else {
                    opt.textContent = `🎙️ ${label}`;
                }

                if (selectedMicId === device.deviceId) {
                    opt.selected = true;
                }
                micDeviceSelect.appendChild(opt);
            });

            // Auto-select headset if detected and no prior preference was set
            if (!selectedMicId && headsetFound) {
                selectedMicId = headsetFound;
                micDeviceSelect.value = headsetFound;
                if (micStatusText) micStatusText.textContent = 'Headset Connected';
            } else if (micStatusText) {
                micStatusText.textContent = selectedMicId ? 'Mic Selected' : 'Ready';
            }
        } catch (e) {
            console.warn('Device enumeration notice:', e);
        }
    }

    // Initial enumeration & watch for headset plug/unplug
    populateMicrophoneDevices();
    if (navigator.mediaDevices && navigator.mediaDevices.addEventListener) {
        navigator.mediaDevices.addEventListener('devicechange', () => {
            console.log('Audio devices changed (headset plugged/unplugged)');
            populateMicrophoneDevices();
        });
    }

    if (micDeviceSelect) {
        micDeviceSelect.addEventListener('change', async (e) => {
            selectedMicId = e.target.value;
            localStorage.setItem('voice_preferred_mic_id', selectedMicId);
            if (micStatusText) {
                micStatusText.textContent = selectedMicId ? 'Active Mic Changed' : 'Default Mic';
            }
            // If in active call, switch live stream
            if (callState === 'connected') {
                const stream = await voiceEngine.requestMicrophone(selectedMicId);
                if (stream) {
                    visualizer.connectMicrophoneStream(stream);
                }
            }
        });
    }

    const voiceEngine = new VoiceEngine({
        onUserSpeechStart: () => {
            if (callState === 'connected') {
                visualizer.setState('listening');
                updateCallStateUI('listening', '🎤 Hearing you...');
            }
        },
        onUserInterim: (liveText) => {
            if (callState === 'connected') {
                const bubble = document.getElementById('live-speech-bubble');
                const bubbleText = document.getElementById('live-speech-text');
                if (bubble && bubbleText) {
                    bubble.style.display = 'inline-block';
                    bubbleText.textContent = liveText;
                }
                const shortPreview = liveText.length > 30 ? ('...' + liveText.slice(-28)) : liveText;
                updateCallStateUI('listening', `🗣️ "${shortPreview}"`);
            }
        },
        onUserTranscript: (data) => {
            if (data.isTurnComplete) {
                const bubble = document.getElementById('live-speech-bubble');
                if (bubble) bubble.style.display = 'none';
                handleUserUtterance(data.text);
            }
        },
        onAgentSpeechStart: () => {
            const bubble = document.getElementById('live-speech-bubble');
            if (bubble) bubble.style.display = 'none';
            visualizer.setState('speaking');
            updateCallStateUI('speaking', '🔊 Avni Speaking...');
        },
        onAgentSpeechEnd: () => {
            if (callState === 'connected') {
                visualizer.setState('listening');
                updateCallStateUI('active', '🎤 Listening to you...');
            }
        },
        onError: (err) => {
            console.warn('Voice Engine Error:', err);
        },
        onStateChange: (state) => {
            if (callState === 'connected') {
                visualizer.setState(state);
            }
        }
    });

    if (activePersona) {
        voiceEngine.setPersona(activePersona);
    }

    // =========================================================================
    // Persona Selection
    // =========================================================================
    document.querySelectorAll('.persona-card').forEach(card => {
        card.addEventListener('click', () => {
            if (callState === 'connected' || callState === 'ringing') {
                if (!confirm('Switching agent will end current call. Proceed?')) return;
                endCall();
            }

            document.querySelectorAll('.persona-card').forEach(c => {
                c.classList.remove('bg-indigo-50/80', 'border-indigo-300', 'ring-2', 'ring-indigo-500/20', 'active');
                c.classList.add('bg-slate-50/70', 'border-slate-200/80');
            });
            card.classList.remove('bg-slate-50/70', 'border-slate-200/80');
            card.classList.add('bg-indigo-50/80', 'border-indigo-300', 'ring-2', 'ring-indigo-500/20', 'active');

            const personaId = card.dataset.personaId;
            activePersona = window.PERSONAS_DATA[personaId];
            voiceEngine.setPersona(activePersona);
            visualizer.setAgentTheme(activePersona.color, '#06b6d4');

            // Update Header & HUD
            const nameEl = document.getElementById('active-agent-name');
            const roleEl = document.getElementById('active-agent-role');
            const avatarEl = document.getElementById('active-agent-avatar');
            if (nameEl) nameEl.textContent = activePersona.name;
            if (roleEl) roleEl.textContent = activePersona.role;
            if (avatarEl) avatarEl.textContent = activePersona.avatar;
            if (hudEmotion) hudEmotion.textContent = activePersona.emotion_preset.replace('Thunder Emotion: ', '');
            if (hudRate) hudRate.textContent = activePersona.voice_rate + 'x';

            // Play sample greeting preview tone
            if (window.telephonyAudio) {
                window.telephonyAudio.playDTMF('5');
            }
        });
    });

    // =========================================================================
    // Call Lifecycle (Start, Connect, End)
    // =========================================================================
    if (startCallBtn) {
        startCallBtn.addEventListener('click', async () => {
            if (callState !== 'idle') return;
            await startOutboundCall();
        });
    }

    if (endCallBtn) {
        endCallBtn.addEventListener('click', () => {
            if (callState === 'idle') return;
            endCall();
        });
    }

    async function startOutboundCall() {
        if (window.telephonyAudio) {
            window.telephonyAudio.init();
        }

        try {
            // Request active microphone access with headset support
            const stream = await voiceEngine.requestMicrophone(selectedMicId);
            if (stream) {
                visualizer.connectMicrophoneStream(stream);
            }
        } catch (e) {
            console.warn('Microphone permission notice:', e);
        }

        callState = 'ringing';
        updateCallStateUI('ringing', 'Dialing & Ringing...');
        if (startCallBtn) startCallBtn.style.display = 'none';
        const controls = document.getElementById('active-call-controls');
        if (controls) controls.style.display = 'flex';
        conversationTranscript = [];
        if (transcriptFeed) transcriptFeed.innerHTML = '';

        // Play phone ringing
        if (window.telephonyAudio) {
            window.telephonyAudio.startRinging();
        }

        // Simulate network connection delay (1.8s)
        setTimeout(() => {
            if (callState !== 'ringing') return;
            if (window.telephonyAudio) {
                window.telephonyAudio.stopRinging();
                window.telephonyAudio.playCallConnected();
            }
            connectCall();
        }, 1800);
    }

    function connectCall() {
        callState = 'connected';
        updateCallStateUI('active', 'Connected (Live)');
        startCallTimer();

        // Speak Universal Agent Greeting
        const greeting = activePersona ? activePersona.greeting : "Namaste! Main Qloudsoft Solutions se Avni baat kar rahi hoon. Kya aap apne business ki website ya digital growth ke baare mein baat karna chahte hain?";
        appendTranscript('agent', activePersona ? activePersona.name : 'Avni', greeting, '👩‍💼');

        voiceEngine.startListening();
        voiceEngine.speak(greeting);
    }

    function endCall() {
        if (callState === 'idle') return;

        if (window.telephonyAudio) {
            window.telephonyAudio.stopRinging();
            window.telephonyAudio.playCallEnded();
        }

        callState = 'ended';
        stopCallTimer();
        voiceEngine.stopListening();
        voiceEngine.stopAgentSpeech();
        visualizer.setState('idle');
        updateCallStateUI('idle', 'Call Ended');

        if (startCallBtn) startCallBtn.style.display = 'flex';
        const controls = document.getElementById('active-call-controls');
        if (controls) controls.style.display = 'none';

        // Trigger Post-Call Analytics if conversation took place
        if (conversationTranscript.length > 0) {
            triggerPostCallAnalysis();
        }

        setTimeout(() => {
            callState = 'idle';
            if (callTimerEl) callTimerEl.textContent = '00:00';
            callSeconds = 0;
        }, 1200);
    }

    // =========================================================================
    // Conversation & AI Turn Handling
    // =========================================================================
    let isProcessingTurn = false;

    async function handleUserUtterance(text) {
        if (!text || text.trim().length === 0) return;
        if (callState !== 'connected' || isHold || isProcessingTurn) return;

        isProcessingTurn = true;
        voiceEngine.stopListening(); // Mute recognition while AI processes turn

        const callerName = callerNameInput ? callerNameInput.value.trim() : 'You';
        appendTranscript('user', callerName || 'You', text, '👤');

        // Visualizer Thinking State
        visualizer.setState('thinking');
        updateCallStateUI('thinking', 'Processing Voice...');

        const startTime = Date.now();

        try {
            const res = await fetch('/voice-agent/api/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    message: text,
                    persona_id: 'universal',
                    history: conversationTranscript,
                    caller_name: callerName,
                    caller_phone: callerPhoneInput ? callerPhoneInput.value.trim() : ''
                })
            });

            const data = await res.json();
            const latency = Date.now() - startTime;
            if (hudLatency) hudLatency.textContent = (data.latency_ms || latency) + 'ms';

            if (data.success && data.reply) {
                // Update HUD metrics
                if (data.sentiment && hudSentiment) hudSentiment.textContent = data.sentiment;
                if (data.emotion_hud && data.emotion_hud.tone && hudEmotion) {
                    hudEmotion.textContent = data.emotion_hud.tone.split(',')[0];
                }

                appendTranscript('agent', activePersona ? activePersona.name : 'Avni', data.reply, '👩‍💼');

                if (callState === 'connected' && !isHold) {
                    await voiceEngine.speak(data.reply);
                }

                if (data.is_call_ended) {
                    setTimeout(() => endCall(), 1500);
                }
            } else {
                const errMsg = data.error || "Main sun nahi paayi. Kya aap dobara bata sakte hain?";
                appendTranscript('agent', activePersona ? activePersona.name : 'Avni', errMsg, '👩‍💼');
                if (callState === 'connected') {
                    await voiceEngine.speak(errMsg);
                }
            }
        } catch (e) {
            console.error('Chat API Error:', e);
            visualizer.setState('listening');
            updateCallStateUI('active', 'Connected (Live)');
            if (voiceEngine.isListening && !voiceEngine.isSpeaking) {
                voiceEngine.startListening();
            }
        } finally {
            isProcessingTurn = false;
        }
    }

    function appendTranscript(role, name, text, avatar) {
        conversationTranscript.push({
            role: role === 'agent' ? 'model' : 'user',
            text: text,
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        });

        if (!transcriptFeed) return;

        const msgDiv = document.createElement('div');
        msgDiv.className = `transcript-msg ${role}`;

        const headerDiv = document.createElement('div');
        headerDiv.className = 'msg-header';
        headerDiv.innerHTML = `<span>${avatar || ''} ${name}</span><span>${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>`;

        const bubbleDiv = document.createElement('div');
        bubbleDiv.className = 'msg-bubble';
        bubbleDiv.textContent = text;

        msgDiv.appendChild(headerDiv);
        msgDiv.appendChild(bubbleDiv);

        transcriptFeed.appendChild(msgDiv);
        transcriptFeed.scrollTop = transcriptFeed.scrollHeight;
    }

    // Manual Text Fallback Send
    if (manualSendBtn) {
        manualSendBtn.addEventListener('click', () => {
            const txt = manualMsgInput ? manualMsgInput.value.trim() : '';
            if (!txt) return;
            if (callState !== 'connected') {
                alert('Please click "Start Voice Call" first to begin the live call session.');
                return;
            }
            if (manualMsgInput) manualMsgInput.value = '';
            handleUserUtterance(txt);
        });
    }

    if (manualMsgInput) {
        manualMsgInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (manualSendBtn) manualSendBtn.click();
            }
        });
    }

    // Quick Hindi Prompt Chips
    document.querySelectorAll('.chip-btn').forEach(chip => {
        chip.addEventListener('click', () => {
            const prompt = chip.dataset.prompt;
            if (!prompt) return;
            if (callState !== 'connected') {
                if (confirm('Start voice call to ask this prompt?')) {
                    startOutboundCall().then(() => {
                        setTimeout(() => handleUserUtterance(prompt), 2200);
                    });
                }
                return;
            }
            handleUserUtterance(prompt);
        });
    });

    // =========================================================================
    // Post-Call CRM Intelligence
    // =========================================================================
    async function triggerPostCallAnalysis() {
        if (!analyticsModal) return;
        analyticsModal.classList.add('open');
        const loader = document.getElementById('analytics-loader');
        const content = document.getElementById('analytics-content');
        if (loader) loader.style.display = 'block';
        if (content) content.style.display = 'none';

        const durationStr = formatDuration(callSeconds);

        try {
            const res = await fetch('/voice-agent/api/analyze', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Gemini-Key': customKeyInput ? customKeyInput.value.trim() : '',
                    'X-Gemini-Model': modelSelect ? modelSelect.value : ''
                },
                body: JSON.stringify({
                    transcript: conversationTranscript,
                    persona_id: activePersona ? activePersona.id : 'universal',
                    caller_name: callerNameInput ? callerNameInput.value.trim() : '',
                    caller_phone: callerPhoneInput ? callerPhoneInput.value.trim() : '',
                    duration: durationStr
                })
            });

            const data = await res.json();
            if (loader) loader.style.display = 'none';
            if (content) content.style.display = 'block';

            if (data.success && data.analysis) {
                currentPostCallData = data;
                renderAnalytics(data.analysis);
            }
        } catch (e) {
            console.error('Analytics API Error:', e);
            if (loader) loader.style.display = 'none';
            if (content) content.style.display = 'block';
        }
    }

    function renderAnalytics(report) {
        const scoreEl = document.getElementById('lead-score-val');
        const stageEl = document.getElementById('lead-stage-badge');
        const summaryEl = document.getElementById('summary-text');
        const entityName = document.getElementById('entity-name');
        const entityPhone = document.getElementById('entity-phone');
        const entityIntent = document.getElementById('entity-intent');
        const entityNext = document.getElementById('entity-next-step');
        const waMsgEl = document.getElementById('whatsapp-msg-preview');

        if (scoreEl) scoreEl.textContent = report.lead_score || 80;
        if (stageEl) stageEl.textContent = report.lead_stage || 'Warm Prospect';
        if (summaryEl) summaryEl.textContent = report.call_summary || 'Call concluded successfully.';

        if (report.key_entities) {
            if (entityName) entityName.textContent = report.key_entities.customer_name || callerNameInput.value || 'N/A';
            if (entityPhone) entityPhone.textContent = report.key_entities.phone || callerPhoneInput.value || 'N/A';
            if (entityIntent) entityIntent.textContent = report.key_entities.intent || 'Inquiry';
            if (entityNext) entityNext.textContent = report.key_entities.next_step || 'Send WhatsApp details';
        }

        if (waMsgEl) {
            waMsgEl.textContent = report.whatsapp_followup_message || "Namaste! Thank you for speaking with our AI specialist.";
        }
    }

    if (closeAnalyticsBtn) {
        closeAnalyticsBtn.addEventListener('click', () => {
            if (analyticsModal) analyticsModal.classList.remove('open');
        });
    }

    // Direct WhatsApp Send from Modal
    if (btnSendWhatsApp) {
        btnSendWhatsApp.addEventListener('click', async () => {
            const phone = callerPhoneInput ? callerPhoneInput.value.trim() : '';
            const msg = whatsappMsgPreview ? whatsappMsgPreview.textContent.trim() : '';

            if (!phone) {
                alert('Please provide a valid recipient phone number.');
                return;
            }

            btnSendWhatsApp.disabled = true;
            btnSendWhatsApp.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Sending...';

            try {
                const res = await fetch('/voice-agent/api/send-whatsapp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ phone, message: msg })
                });

                const data = await res.json();
                if (data.success) {
                    alert('✅ WhatsApp Follow-Up message sent successfully via Qloudflow API!');
                    btnSendWhatsApp.innerHTML = '✅ Sent!';
                } else {
                    // Fallback to WhatsApp Web link
                    const cleanPhone = phone.replace(/\D/g, '');
                    const waUrl = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(msg)}`;
                    window.open(waUrl, '_blank');
                    btnSendWhatsApp.innerHTML = '💬 Opened WhatsApp Web';
                }
            } catch (e) {
                // Fallback to WhatsApp Web
                const cleanPhone = phone.replace(/\D/g, '');
                const waUrl = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(msg)}`;
                window.open(waUrl, '_blank');
                btnSendWhatsApp.innerHTML = '💬 Opened WhatsApp Web';
            } finally {
                setTimeout(() => {
                    btnSendWhatsApp.disabled = false;
                    btnSendWhatsApp.innerHTML = '💬 Dispatch WhatsApp';
                }, 3000);
            }
        });
    }

    if (btnCopyWa) {
        btnCopyWa.addEventListener('click', () => {
            const msg = whatsappMsgPreview ? whatsappMsgPreview.textContent.trim() : '';
            if (navigator.clipboard) {
                navigator.clipboard.writeText(msg).then(() => {
                    btnCopyWa.textContent = '✅ Copied!';
                    setTimeout(() => btnCopyWa.textContent = '📋 Copy Text', 2000);
                });
            }
        });
    }

    // =========================================================================
    // Call Controls (Mute, Hold, Dialpad)
    // =========================================================================
    if (muteBtn) {
        muteBtn.addEventListener('click', () => {
            const isMuted = voiceEngine.toggleMute();
            muteBtn.classList.toggle('active', isMuted);
            muteBtn.innerHTML = isMuted ? '🔇' : '🎤';
            muteBtn.title = isMuted ? 'Unmute Microphone' : 'Mute Microphone';
        });
    }

    if (holdBtn) {
        holdBtn.addEventListener('click', () => {
            isHold = !isHold;
            holdBtn.classList.toggle('active', isHold);
            if (isHold) {
                voiceEngine.stopListening();
                voiceEngine.stopAgentSpeech();
                visualizer.setState('idle');
                updateCallStateUI('idle', 'Call On Hold');
            } else {
                voiceEngine.startListening();
                visualizer.setState('listening');
                updateCallStateUI('active', 'Connected (Live)');
            }
        });
    }

    if (keypadBtn) {
        keypadBtn.addEventListener('click', () => {
            if (dialpadModal) dialpadModal.classList.add('open');
        });
    }

    if (closeDialpadBtn) {
        closeDialpadBtn.addEventListener('click', () => {
            if (dialpadModal) dialpadModal.classList.remove('open');
        });
    }

    // DTMF Dialpad Buttons
    document.querySelectorAll('.dialpad-key').forEach(key => {
        key.addEventListener('click', () => {
            const digit = key.dataset.key;
            if (window.telephonyAudio) {
                window.telephonyAudio.playDTMF(digit);
            }
        });
    });

    // =========================================================================
    // Settings Modal & API Test
    // =========================================================================
    if (openSettingsBtn) {
        openSettingsBtn.addEventListener('click', () => {
            if (settingsModal) settingsModal.classList.add('open');
        });
    }

    if (closeSettingsBtn) {
        closeSettingsBtn.addEventListener('click', () => {
            if (settingsModal) settingsModal.classList.remove('open');
        });
    }

    if (testKeyBtn) {
        testKeyBtn.addEventListener('click', async () => {
            if (!testKeyResult) return;
            testKeyResult.textContent = 'Testing connection...';
            testKeyResult.style.color = 'var(--text-muted)';

            try {
                const res = await fetch('/voice-agent/api/test-key', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        api_key: customKeyInput ? customKeyInput.value.trim() : '',
                        model: modelSelect ? modelSelect.value : ''
                    })
                });

                const data = await res.json();
                if (data.success) {
                    testKeyResult.textContent = `✅ Connected (${data.model} - ${data.latency_ms}ms)`;
                    testKeyResult.style.color = '#34d399';
                } else {
                    testKeyResult.textContent = `❌ ${data.error || 'Connection Failed'}`;
                    testKeyResult.style.color = '#f87171';
                }
            } catch (e) {
                testKeyResult.textContent = '❌ Network request error';
                testKeyResult.style.color = '#f87171';
            }
        });
    }

    // =========================================================================
    // Plivo Cloud Telephony Outbound PSTN Dialing
    // =========================================================================
    const btnPlivoCall = document.getElementById('btn-plivo-call');
    const plivoHudBadge = document.getElementById('plivo-hud-badge');

    async function checkPlivoBalance() {
        if (!plivoHudBadge) return;
        try {
            const res = await fetch('/voice-agent/plivo/account');
            const data = await res.json();
            if (data.success && data.cash_credits) {
                plivoHudBadge.textContent = `Active ($${parseFloat(data.cash_credits).toFixed(2)})`;
                plivoHudBadge.className = 'px-2 py-0.5 rounded-md font-bold text-[10px] bg-emerald-100 text-emerald-800 border border-emerald-200';
            }
        } catch (e) {
            console.warn('Plivo account status notice:', e);
        }
    }
    checkPlivoBalance();

    if (btnPlivoCall) {
        btnPlivoCall.addEventListener('click', async () => {
            const phone = callerPhoneInput ? callerPhoneInput.value.trim() : '';
            const name = callerNameInput ? callerNameInput.value.trim() : 'Customer';

            if (!phone) {
                alert('Please enter a valid recipient phone number first.');
                if (callerPhoneInput) callerPhoneInput.focus();
                return;
            }

            const cleanPhone = phone.replace(/\D/g, '');
            if (cleanPhone.length < 10) {
                alert('Please enter a valid 10-digit mobile number with country code (e.g. +91 98765 43210).');
                return;
            }

            const confirmDial = confirm(`Dial ${phone} via Avni (Plivo Phone: 918031803464)?\n\nWhen the recipient answers, Avni will converse in Hindi/Hinglish and auto-save the call summary to CRM!`);
            if (!confirmDial) return;

            btnPlivoCall.disabled = true;
            btnPlivoCall.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Dialing Phone...';

            try {
                const res = await fetch('/voice-agent/plivo/dial', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        phone: phone,
                        name: name
                    })
                });

                const data = await res.json();

                if (data.success) {
                    alert(`📞 Call dispatched successfully via Plivo!\n\nDestination: ${data.to}\nCaller ID: ${data.from} (Avni)\n\nAvni will speak with the customer in real-time.`);
                    btnPlivoCall.innerHTML = '✅ Dialed!';
                } else {
                    alert(`❌ Could not dispatch Plivo call: ${data.error || 'Unknown error'}`);
                    btnPlivoCall.innerHTML = '<i class="fa-solid fa-phone-volume text-sm"></i> Dial via Plivo (918031803464)';
                }
            } catch (e) {
                console.error('Plivo Dial Error:', e);
                alert('❌ Network error while communicating with Plivo server.');
                btnPlivoCall.innerHTML = '<i class="fa-solid fa-phone-volume text-sm"></i> Dial via Plivo (918031803464)';
            } finally {
                setTimeout(() => {
                    btnPlivoCall.disabled = false;
                    btnPlivoCall.innerHTML = '<i class="fa-solid fa-phone-volume text-sm"></i> <span>Dial via Plivo (918031803464)</span>';
                }, 4000);
            }
        });
    }

    // =========================================================================
    // Helpers & UI States
    // =========================================================================
    function startCallTimer() {
        stopCallTimer();
        callSeconds = 0;
        callTimerInterval = setInterval(() => {
            callSeconds++;
            if (callTimerEl) callTimerEl.textContent = formatDuration(callSeconds);
        }, 1000);
    }

    function stopCallTimer() {
        if (callTimerInterval) {
            clearInterval(callTimerInterval);
            callTimerInterval = null;
        }
    }

    function formatDuration(sec) {
        const m = Math.floor(sec / 60).toString().padStart(2, '0');
        const s = (sec % 60).toString().padStart(2, '0');
        return `${m}:${s}`;
    }

    function updateCallStateUI(state, text) {
        if (!stateText || !callStateBadge) return;
        stateText.textContent = text;
        callStateBadge.className = `call-state-badge ${state}`;
    }
});
