/**
 * Plik: goals.js
 * Logika dla strony celów (goals.php)
 */

export function handleGoalsPage(goalsForm) {
    const goalCardTemplate = document.getElementById('goal-card-template');
    if (!goalCardTemplate) return;

    goalsForm.addEventListener('click', function(e) {
        const button = e.target.closest('button');
        if (!button) return;

        if (button.classList.contains('remove-goal-btn')) {
            e.preventDefault();
            button.closest('.goal-card').remove();
        }

        if (button.classList.contains('add-goal-btn')) {
            e.preventDefault();
            const container = button.previousElementSibling;
            if (!container || !container.classList.contains('goals-for-exercise-container')) return;

            const exerciseId = container.dataset.exerciseId;
            const trackBy = JSON.parse(button.dataset.trackBy);
            const goalIndex = container.querySelectorAll('.goal-card').length;
            
            let newCardHtml = goalCardTemplate.innerHTML
                .replace(/{exercise_id}/g, exerciseId)
                .replace(/{goal_index}/g, goalIndex);
                
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = newCardHtml;
            const newCard = tempDiv.firstElementChild;
            const targetFieldsContainer = newCard.querySelector('.target-fields-container');

            trackBy.forEach(param => {
                const col = document.createElement('div');
                col.className = 'col';
                const paramLabel = {reps: 'Powt.', weight: 'Ciężar', time: 'Czas'}[param] || param;
                col.innerHTML = `<label class="form-label small">${paramLabel}</label><input type="number" step="0.1" class="form-control" name="goals[${exerciseId}][${goalIndex}][targets][${param}]" placeholder="${paramLabel}">`;
                targetFieldsContainer.appendChild(col);
            });
            container.appendChild(newCard);
        }
    });
}

export function handleGoalsFiltering() {
    const container = document.getElementById('goalsAccordion');
    const searchInput = document.getElementById('goal-search-input');
    const sortSelect = document.getElementById('goal-sort-select');
    const noResultsMsg = document.getElementById('no-goals-found');
    if (!container || !searchInput || !sortSelect) return;

    const allExercises = Array.from(container.querySelectorAll('.accordion-item'));

    const filterAndSort = () => {
        const searchTerm = searchInput.value.toLowerCase();
        const visibleExercises = allExercises.filter(item => 
            (item.dataset.exerciseName || '').toLowerCase().includes(searchTerm)
        );
        
        const sortValue = sortSelect.value;
        visibleExercises.sort((a, b) => {
            const nameA = a.dataset.exerciseName;
            const nameB = b.dataset.exerciseName;
            return sortValue === 'name-desc' ? nameB.localeCompare(nameA) : nameA.localeCompare(nameB);
        });

        allExercises.forEach(item => item.style.display = 'none');
        visibleExercises.forEach(item => {
            container.appendChild(item);
            item.style.display = 'block';
        });

        if (noResultsMsg) {
            noResultsMsg.style.display = visibleExercises.length === 0 ? 'block' : 'none';
        }
    };
    
    searchInput.addEventListener('input', filterAndSort);
    sortSelect.addEventListener('change', filterAndSort);
    filterAndSort();
}