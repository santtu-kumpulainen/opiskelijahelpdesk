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
});
