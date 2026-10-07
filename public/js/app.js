document.addEventListener('DOMContentLoaded', () => {
    const mobileLayout = window.matchMedia('(max-width: 700px)');

    document.querySelectorAll('.navbar').forEach((navbar, index) => {
        const navigation = navbar.querySelector('.nav-links');

        if (!navigation) {
            return;
        }

        const toggle = document.createElement('button');
        const icon = document.createElement('span');
        const navigationId = navigation.id || `primary-navigation-${index + 1}`;

        navigation.id = navigationId;
        toggle.type = 'button';
        toggle.className = 'menu-toggle';
        toggle.setAttribute('aria-controls', navigationId);
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Avaa navigaatio');
        icon.className = 'menu-toggle-icon';
        icon.setAttribute('aria-hidden', 'true');
        toggle.append(icon);
        navbar.insertBefore(toggle, navigation);

        const syncLayout = () => {
            navbar.classList.toggle('menu-ready', mobileLayout.matches);
            if (!mobileLayout.matches) {
                navbar.classList.remove('menu-open');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.setAttribute('aria-label', 'Avaa navigaatio');
            }
        };

        toggle.addEventListener('click', () => {
            const isOpen = navbar.classList.toggle('menu-open');
            toggle.setAttribute('aria-expanded', String(isOpen));
            toggle.setAttribute('aria-label', isOpen ? 'Sulje navigaatio' : 'Avaa navigaatio');
        });

        mobileLayout.addEventListener('change', syncLayout);
        syncLayout();
    });

    prefillTicketFromChat();
});

/*
 * Esitäyttää tukipyyntölomakkeen tekoälychatin luonnoksella
 * (js/chat.js). Käyttäjä tarkistaa tiedot ja lähettää lomakkeen itse.
 */
function prefillTicketFromChat() {
    const title = document.getElementById('title');
    const description = document.getElementById('description');

    if (
        !title ||
        !description ||
        new URLSearchParams(window.location.search).get('from') !== 'chat'
    ) {
        return;
    }

    let draft = null;

    try {
        draft = JSON.parse(window.sessionStorage.getItem('helpdesk-chat-ticket-draft') || 'null');
        window.sessionStorage.removeItem('helpdesk-chat-ticket-draft');
    } catch {
        return;
    }

    // Luonnos vanhenee puolessa tunnissa.
    if (
        !draft ||
        typeof draft.title !== 'string' ||
        typeof draft.description !== 'string' ||
        Date.now() - Number(draft.createdAt) > 30 * 60 * 1000
    ) {
        return;
    }

    // Lomakkeen palvelinvalidoinnin jälkeen säilytetään käyttäjän omat arvot.
    if (title.value.trim() !== '' || description.value.trim() !== '') {
        return;
    }

    title.value = draft.title.slice(0, Number(title.maxLength) > 0 ? title.maxLength : 255);
    description.value = draft.description.slice(0, Number(description.maxLength) > 0 ? description.maxLength : 10000);

    const notice = document.createElement('div');
    notice.className = 'form-success';
    notice.setAttribute('role', 'status');
    notice.textContent = 'Otsikko ja kuvaus esitäytettiin chat-keskustelusta. Tarkista ja muokkaa tiedot, valitse kategoria ja lähetä tukipyyntö.';
    title.form.before(notice);
    title.focus();
}
