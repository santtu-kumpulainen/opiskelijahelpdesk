/*
 * Etusivun tekoälychat. Pohjana ai_chatbot-projektin käyttöliittymä.
 *
 * Selain kutsuu vain chat-api.php:tä; PHP välittää pyynnöt Ollamalle.
 * Keskusteluhistoria säilyy muistissa vain sivulatauksen ajan.
 */

const CHAT_API_URL = 'chat-api.php';
const CHAT_MAX_LENGTH = 4000;
const TICKET_DRAFT_KEY = 'helpdesk-chat-ticket-draft';

document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('chat');

    if (panel) {
        initChat(panel);
    }
});

/*
 * Kevyt muotoilu mallin vastauksille: ```koodilohkot```, `koodi` ja
 * **lihavointi**. Kaikki teksti lisätään textContentina, ei HTML:nä.
 */
function formatAnswer(text) {
    const nodes = [];
    const parts = text.split(/```[^\n`]*\n?([\s\S]*?)```/g);

    parts.forEach((part, index) => {
        if (index % 2 === 1) {
            const pre = document.createElement('pre');
            const code = document.createElement('code');
            code.textContent = part.replace(/\n$/, '');
            pre.append(code);
            nodes.push(pre);
            return;
        }

        part.split(/(`[^`\n]+`|\*\*[^*\n]+\*\*)/g).forEach((segment) => {
            if (/^`[^`\n]+`$/.test(segment)) {
                const code = document.createElement('code');
                code.textContent = segment.slice(1, -1);
                nodes.push(code);
            } else if (/^\*\*[^*\n]+\*\*$/.test(segment)) {
                const strong = document.createElement('strong');
                strong.textContent = segment.slice(2, -2);
                nodes.push(strong);
            } else if (segment) {
                nodes.push(document.createTextNode(segment));
            }
        });
    });

    return nodes;
}

/*
 * localStorage voi puuttua tai heittää virheen (esim. yksityinen tila).
 */
const storage = {
    get(key) {
        try {
            return window.localStorage.getItem(key);
        } catch {
            return null;
        }
    },
    set(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch {
            // Asetus jää muistamatta, mutta chat toimii.
        }
    }
};

function initChat(panel) {
    const csrfToken = panel.dataset.csrfToken || '';
    const form = panel.querySelector('#chat-form');
    const input = panel.querySelector('#chat-input');
    const send = panel.querySelector('#chat-send');
    const log = panel.querySelector('#chat-log');
    const errorBox = panel.querySelector('#chat-error');
    const count = panel.querySelector('#chat-count');
    const modelSelect = panel.querySelector('#chat-model');
    const modelStatus = panel.querySelector('#chat-model-status');
    const microphone = panel.querySelector('#chat-mic');
    const voiceStatus = panel.querySelector('#chat-voice-status');
    const speakToggle = panel.querySelector('#chat-speak');
    const stopSpeakingButton = panel.querySelector('#chat-stop-speaking');
    const clearButton = panel.querySelector('#chat-clear');
    const ticketLink = panel.querySelector('#chat-create-ticket');

    const history = [];
    let isSending = false;
    let isListening = false;

    /* ---------- Yleiset apufunktiot ---------- */

    function showError(message) {
        errorBox.textContent = message;
        errorBox.hidden = false;
    }

    function hideError() {
        errorBox.hidden = true;
        errorBox.textContent = '';
    }

    function setModelStatus(text, state) {
        modelStatus.textContent = text;
        modelStatus.dataset.state = state;
    }

    async function apiRequest(action, options = {}) {
        const headers = { Accept: 'application/json' };

        if (options.body !== undefined) {
            headers['Content-Type'] = 'application/json';
            headers['X-CSRF-Token'] = csrfToken;
        }

        let response;

        try {
            response = await fetch(`${CHAT_API_URL}?action=${encodeURIComponent(action)}`, {
                method: options.body === undefined ? 'GET' : 'POST',
                headers,
                credentials: 'same-origin',
                body: options.body === undefined ? undefined : JSON.stringify(options.body)
            });
        } catch {
            throw new Error('Palvelimeen ei saatu yhteyttä. Tarkista verkkoyhteys ja yritä uudelleen.');
        }

        const contentType = response.headers.get('content-type') || '';

        // Vanhentunut istunto ohjautuu kirjautumissivulle (HTML).
        if (response.status === 401 || response.redirected || !contentType.includes('application/json')) {
            throw new Error('Istuntosi on vanhentunut. Päivitä sivu ja kirjaudu sisään uudelleen.');
        }

        const result = await response.json().catch(() => ({}));

        if (!response.ok) {
            const error = new Error(result.error || `Palvelinvirhe (${response.status}).`);
            error.code = result.code || '';
            throw error;
        }

        return result;
    }

    function updateControls() {
        send.disabled = isSending || isListening;
        microphone.disabled = isSending || (!recognition && !isListening);
        microphone.setAttribute('aria-pressed', String(isListening));
        microphone.textContent = isListening ? 'Lopeta sanelu' : 'Sanele';
        log.setAttribute('aria-busy', String(isSending));
    }

    /* ---------- Viestit ---------- */

    function addMessage(role, text, extraClass = '') {
        panel.querySelector('#chat-welcome')?.remove();

        const message = document.createElement('div');
        message.className = `chat-message ${role} ${extraClass}`.trim();

        const label = document.createElement('span');
        label.className = 'chat-message-label';
        label.textContent = role === 'user' ? 'Sinä' : 'Tekoäly';

        const content = document.createElement('div');
        content.className = 'chat-message-text';

        if (role === 'assistant' && !extraClass) {
            content.append(...formatAnswer(text));
        } else {
            content.textContent = text;
        }

        message.append(label, content);
        log.append(message);
        log.scrollTop = log.scrollHeight;

        return message;
    }

    function resizeInput() {
        input.style.height = 'auto';
        input.style.height = `${Math.min(input.scrollHeight, 200)}px`;
        count.textContent = `${input.value.length} / ${CHAT_MAX_LENGTH}`;
    }

    function clearConversation() {
        stopSpeaking();
        history.length = 0;
        log.replaceChildren();
        hideError();
        input.focus();
    }

    input.addEventListener('input', resizeInput);

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    clearButton.addEventListener('click', clearConversation);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const question = input.value.trim();

        if (isSending || isListening) {
            return;
        }

        if (!question) {
            showError('Kirjoita kysymys ennen lähettämistä.');
            input.focus();
            return;
        }

        if (!modelSelect.value) {
            showError('Kielimalli ei ole käytettävissä. Yritä myöhemmin uudelleen.');
            return;
        }

        stopSpeaking();
        hideError();
        addMessage('user', question);
        history.push({ role: 'user', content: question });
        input.value = '';
        resizeInput();

        isSending = true;
        updateControls();
        const typing = addMessage('assistant', 'Kirjoittaa vastausta…', 'typing');

        try {
            const result = await apiRequest('chat', {
                body: { model: modelSelect.value, messages: history }
            });

            typing.remove();
            addMessage('assistant', result.answer);
            history.push({ role: 'assistant', content: result.answer });
            speak(result.answer);
        } catch (error) {
            typing.remove();
            history.pop();

            // Kysymys palautetaan kenttään, jotta sen voi lähettää uudelleen.
            input.value = question;
            resizeInput();
            log.lastElementChild?.remove();

            showError(
                error.code === 'history_too_long'
                    ? `${error.message} Paina ”Aloita uusi keskustelu”.`
                    : error.message
            );
        } finally {
            isSending = false;
            updateControls();
            input.focus();
        }
    });

    /* ---------- Mallit ---------- */

    let loadToken = 0;

    async function warmUpModel() {
        const token = ++loadToken;

        setModelStatus('Ladataan mallia muistiin…', 'loading');

        try {
            await apiRequest('load', { body: { model: modelSelect.value } });

            if (token === loadToken) {
                setModelStatus('Malli valmis', 'ready');
            }
        } catch (error) {
            if (token === loadToken) {
                setModelStatus('Mallin lataus epäonnistui', 'error');
                showError(error.message);
            }
        }
    }

    async function loadModels() {
        try {
            const result = await apiRequest('models');
            const models = Array.isArray(result.models) ? result.models : [];

            if (!models.length) {
                modelSelect.replaceChildren(new Option('Ei asennettuja malleja', ''));
                setModelStatus('Ei käytettävissä', 'error');
                showError('Kielimallipalvelussa ei ole asennettuja malleja.');
                return;
            }

            const saved = storage.get('helpdesk-chat-model');
            const selected = models.includes(saved) ? saved : result.default;

            modelSelect.replaceChildren(
                ...models.map((name) => new Option(name, name, false, name === selected))
            );
            modelSelect.disabled = false;
            warmUpModel();
        } catch (error) {
            modelSelect.replaceChildren(new Option('Malleja ei saatu', ''));
            setModelStatus('Ei käytettävissä', 'error');
            showError(error.message);
        }
    }

    modelSelect.addEventListener('change', () => {
        storage.set('helpdesk-chat-model', modelSelect.value);
        hideError();
        warmUpModel();
    });

    /* ---------- Puheentunnistus ---------- */

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    let recognition = null;

    if (SpeechRecognition) {
        recognition = new SpeechRecognition();
        recognition.lang = 'fi-FI';
        recognition.interimResults = false;
        recognition.continuous = false;

        recognition.addEventListener('start', () => {
            stopSpeaking();
            isListening = true;
            voiceStatus.textContent = 'Kuuntelen… Puhu nyt suomeksi.';
            voiceStatus.hidden = false;
            hideError();
            updateControls();
        });

        recognition.addEventListener('result', (event) => {
            const transcript = Array.from(event.results)
                .slice(event.resultIndex)
                .map((result) => result[0].transcript)
                .join('')
                .trim();

            if (!transcript) {
                return;
            }

            const current = input.value.trimEnd();
            input.value = `${current}${current ? ' ' : ''}${transcript}`.slice(0, CHAT_MAX_LENGTH);
            resizeInput();
            input.focus();
        });

        recognition.addEventListener('error', (event) => {
            const messages = {
                'not-allowed': 'Selain esti mikrofonin käytön. Tarkista sivuston käyttöoikeudet.',
                'service-not-allowed': 'Selain ei salli puheentunnistuspalvelun käyttöä.',
                'audio-capture': 'Mikrofonia ei löytynyt tai se ei ole käytettävissä.',
                'no-speech': 'Puhetta ei tunnistettu. Yritä uudelleen.',
                network: 'Puheentunnistuspalveluun ei saatu yhteyttä.'
            };

            if (event.error !== 'aborted') {
                showError(messages[event.error] || 'Puheentunnistus epäonnistui. Voit kirjoittaa kysymyksen.');
            }
        });

        recognition.addEventListener('end', () => {
            isListening = false;
            voiceStatus.hidden = true;
            updateControls();
        });
    } else {
        microphone.title = 'Selaimesi ei tue puheentunnistusta.';
    }

    microphone.addEventListener('click', () => {
        if (!recognition || isSending) {
            return;
        }

        if (isListening) {
            recognition.stop();
            return;
        }

        try {
            recognition.start();
        } catch {
            showError('Mikrofonin käynnistäminen epäonnistui. Voit kirjoittaa kysymyksen.');
        }
    });

    /* ---------- Puhesynteesi ---------- */

    const synth = window.speechSynthesis;
    // Ääneen luku on oletuksena pois, jotta sivu ei ala puhua yllättäen.
    let speechEnabled = Boolean(synth) && storage.get('helpdesk-chat-speak') === 'on';
    let speechToken = 0;

    function finnishVoice() {
        const voices = synth.getVoices().filter((voice) => voice.lang.toLowerCase().startsWith('fi'));

        return voices.find((voice) => /noora|harri|selma|natural|online/i.test(voice.name))
            || voices[0]
            || null;
    }

    function setSpeaking(active) {
        stopSpeakingButton.hidden = !active;
    }

    function stopSpeaking() {
        speechToken++;

        if (synth) {
            synth.cancel();
        }

        setSpeaking(false);
    }

    function speakableChunks(text) {
        const plain = text
            .replace(/```[\s\S]*?```/g, ' ')
            .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')
            .replace(/[*_#`>~|]/g, '')
            .replace(/\s+/g, ' ')
            .trim();
        const sentences = plain.match(/[^.!?]+[.!?]*\s*/g) || [];
        const chunks = [];

        for (const sentence of sentences) {
            const last = chunks.length - 1;

            if (last >= 0 && chunks[last].length + sentence.length < 200) {
                chunks[last] += sentence;
            } else {
                chunks.push(sentence);
            }
        }

        return chunks.map((chunk) => chunk.trim()).filter(Boolean);
    }

    function speak(text) {
        if (!speechEnabled || !synth) {
            return;
        }

        stopSpeaking();

        const chunks = speakableChunks(text);
        const token = speechToken;
        const voice = finnishVoice();

        chunks.forEach((chunk, index) => {
            const utterance = new SpeechSynthesisUtterance(chunk);
            utterance.lang = 'fi-FI';

            if (voice) {
                utterance.voice = voice;
            }

            if (index === 0) {
                utterance.addEventListener('start', () => {
                    if (token === speechToken) {
                        setSpeaking(true);
                    }
                });
            }

            const finish = () => {
                if (token === speechToken && index === chunks.length - 1) {
                    setSpeaking(false);
                }
            };

            utterance.addEventListener('end', finish);
            utterance.addEventListener('error', finish);
            synth.speak(utterance);
        });
    }

    function updateSpeakToggle() {
        speakToggle.setAttribute('aria-pressed', String(speechEnabled));
        speakToggle.textContent = speechEnabled ? 'Ääneen luku päällä' : 'Lue vastaukset ääneen';
    }

    if (synth) {
        updateSpeakToggle();
    } else {
        speakToggle.disabled = true;
        speakToggle.textContent = 'Ääneen luku ei ole tuettu';
    }

    speakToggle.addEventListener('click', () => {
        speechEnabled = !speechEnabled;
        storage.set('helpdesk-chat-speak', speechEnabled ? 'on' : 'off');

        if (!speechEnabled) {
            stopSpeaking();
        }

        updateSpeakToggle();
    });

    stopSpeakingButton.addEventListener('click', stopSpeaking);
    window.addEventListener('pagehide', stopSpeaking);

    /* ---------- Siirtyminen tukipyyntöön ---------- */

    /*
     * Keskustelusta tehdään luonnos, jonka create-ticket.php esitäyttää.
     * Luonnos välitetään sessionStoragessa, jotta keskustelu ei päädy
     * URL-osoitteeseen tai palvelimen lokeihin. Tikettiä ei luoda
     * ennen kuin käyttäjä lähettää lomakkeen itse.
     */
    function buildTicketDraft() {
        const questions = history.filter((message) => message.role === 'user').map((message) => message.content);

        if (!questions.length) {
            return null;
        }

        const firstQuestion = questions[0].replace(/\s+/g, ' ').trim();
        const title = firstQuestion.length > 100
            ? `${firstQuestion.slice(0, 97).trimEnd()}…`
            : firstQuestion;

        const lastAnswer = [...history].reverse().find((message) => message.role === 'assistant');
        let description = `Ongelman kuvaus:\n\n${questions.join('\n\n')}`;

        if (lastAnswer) {
            const answer = lastAnswer.content.length > 3000
                ? `${lastAnswer.content.slice(0, 3000)}…`
                : lastAnswer.content;
            description += `\n\nTekoälychatin viimeisin ehdotus, joka ei ratkaissut ongelmaa:\n\n${answer}`;
        }

        return {
            title: title.length >= 3 ? title : `Ongelma: ${title}`,
            description: description.slice(0, 9500),
            createdAt: Date.now()
        };
    }

    if (ticketLink) {
        ticketLink.addEventListener('click', (event) => {
            const draft = buildTicketDraft();

            if (!draft) {
                // Tyhjästä keskustelusta siirrytään tavalliseen lomakkeeseen.
                return;
            }

            try {
                window.sessionStorage.setItem(TICKET_DRAFT_KEY, JSON.stringify(draft));
                event.preventDefault();
                stopSpeaking();
                window.location.href = 'create-ticket.php?from=chat';
            } catch {
                // Ilman sessionStoragea siirrytään tyhjään lomakkeeseen.
            }
        });
    }

    updateControls();
    resizeInput();
    loadModels();
}
