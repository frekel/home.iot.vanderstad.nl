const mobileFurnitureQuery = window.matchMedia('(max-width: 750px)');

document.addEventListener('click', (event) => {
    if (!mobileFurnitureQuery.matches) {
        return;
    }

    const target = event.target;
    if (!(target instanceof Element)) {
        return;
    }

    const button = target.closest<HTMLButtonElement>('.header-right > button.lights-button:first-child');
    if (!button || !button.textContent?.trim().startsWith('Meubels')) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();
    window.location.assign('/measure');
}, true);
