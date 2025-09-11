/**
 * Plik: common.js
 * Zawiera funkcje wspólne, które mogą być używane na wielu stronach aplikacji,
 * takie jak obsługa modala z informacjami o ćwiczeniu czy obsługa przycisków usuwania.
 */

/**
 * Obsługuje wszystkie przyciski usuwania z atrybutem .delete-*.
 * Wyświetla okno confirm() i wysyła odpowiedni formularz GET lub POST.
 */
export function handleDeleteButtons() {
    document.addEventListener('click', function (e) {
        const button = e.target.closest('.delete-plan-btn, .delete-exercise-btn, .delete-workout-btn, .delete-tag-btn');
        if (!button) {
            return;
        }

        e.preventDefault();

        let name = 'element';
        let type = 'element';
        
        if (button.classList.contains('delete-workout-btn')) {
            const cardHeader = button.closest('.accordion-item')?.querySelector('.accordion-header .fw-bold');
            name = cardHeader ? cardHeader.textContent.trim() : 'trening';
            type = 'trening';
        } else {
            const card = button.closest('.card, .plan-card-wrapper, .list-group-item');
            const nameElement = card?.querySelector('h5, .card-title, span');
            if (nameElement) {
                name = nameElement.textContent.trim().split('(ID:')[0].trim();
            }
            if (button.classList.contains('delete-plan-btn')) type = 'plan';
            else if (button.classList.contains('delete-exercise-btn')) type = 'ćwiczenie';
            else if (button.classList.contains('delete-tag-btn')) type = 'tag';
        }

        if (confirm(`Czy na pewno chcesz usunąć ${type} "${name}"? Tej operacji nie można cofnąć.`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            
            // POBIERZ AKCJĘ Z ATRYBUTU `data-action` LUB `href`
            const actionUrl = button.dataset.action || button.getAttribute('href');
            if (!actionUrl) {
                console.error('Przycisk usuwania nie ma zdefiniowanej akcji (ani data-action, ani href)!');
                return;
            }
            form.action = actionUrl;

            // Dodaj ukryte pola z atrybutów data-*
            for (const key in button.dataset) {
                if (key !== 'action') {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key.replace(/[A-Z]/g, letter => `_${letter.toLowerCase()}`);
                    input.value = button.dataset[key];
                    form.appendChild(input);
                }
            }
            
            document.body.appendChild(form);
            form.submit();
        }
    });
}

/**
 * Obsługuje dynamiczne wypełnianie modala z informacjami o ćwiczeniu.
 * Odczytuje dane z atrybutów `data-bs-*` klikniętego przycisku.
 */
export function handleExerciseInfoModal() {
    const modalEl = document.getElementById('exerciseInfoModal');
    if (!modalEl) return;

    const modalTitle = modalEl.querySelector('.modal-title');
    const descContent = modalEl.querySelector('#exercise-description-content');
    const howtoWrapper = modalEl.querySelector('#exercise-howto-wrapper');
    const howtoContent = modalEl.querySelector('#exercise-howto-content');

    modalEl.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) return;
        
        const name = button.getAttribute('data-bs-name');
        const description = button.getAttribute('data-bs-desc');
        const howto = button.getAttribute('data-bs-howto');

        modalTitle.textContent = name || 'Informacje o ćwiczeniu';
        
        // Bezpieczne wstawianie HTML, który może być pusty
        const isEmpty = (content) => !content || content.trim() === '' || content.trim() === '<p><br></p>';

        descContent.innerHTML = !isEmpty(description) 
            ? description 
            : '<p class="text-muted">Brak opisu.</p>';

        if (!isEmpty(howto)) {
            howtoContent.innerHTML = howto;
            howtoWrapper.style.display = 'block';
        } else {
            howtoWrapper.style.display = 'none';
        }
    });
}