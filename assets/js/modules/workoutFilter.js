/**
 * Plik: workoutFilter.js
 * Odpowiada za dynamiczne filtrowanie ćwiczeń na stronie log_workout.php.
 */

// Przechowuje aktualne wartości filtrów
const filters = {
    name: '',
    group: 'all'
};

/**
 * Główna funkcja filtrująca, która pokazuje/ukrywa ćwiczenia i grupy.
 * Działa zarówno w trybie planu, jak i ad-hoc.
 */
function applyFilters() {
    const exercisesContainer = document.getElementById('exercises-container');
    if (!exercisesContainer) return;

    const exerciseGroups = exercisesContainer.querySelectorAll('.exercise-group');
    const adHocBlocks = exercisesContainer.querySelectorAll('.exercise-block-v2[data-exercise-name]');

    // Sprawdź, w jakim trybie jesteśmy
    if (exerciseGroups.length > 0) {
        // --- TRYB PLANU (z grupami) ---
        exerciseGroups.forEach(group => {
            let hasVisibleExercisesInGroup = false;
            const groupMatch = (filters.group === 'all' || group.dataset.groupId === filters.group);

            // Najpierw filtruj ćwiczenia wewnątrz grupy
            group.querySelectorAll('.exercise-block-v2').forEach(block => {
                const nameMatch = block.dataset.exerciseName.includes(filters.name);
                if (nameMatch) {
                    block.style.display = '';
                    hasVisibleExercisesInGroup = true; // Znaleziono co najmniej jedno pasujące ćwiczenie
                } else {
                    block.style.display = 'none';
                }
            });

            // Teraz zdecyduj, czy pokazać całą grupę (razem z nagłówkiem)
            if (groupMatch && hasVisibleExercisesInGroup) {
                group.style.display = ''; // Pokaż grupę, bo pasuje i ma widoczne ćwiczenia
            } else {
                group.style.display = 'none'; // Ukryj grupę
            }
        });

    } else if (adHocBlocks.length > 0) {
        // --- TRYB AD-HOC (bez grup, filtrujemy każdy blok osobno) ---
        adHocBlocks.forEach(block => {
            const nameMatch = block.dataset.exerciseName.includes(filters.name);
            const groupMatch = (filters.group === 'all' || block.dataset.groupId === filters.group);

            // Pokaż blok tylko jeśli pasuje do OBU filtrów
            block.style.display = (nameMatch && groupMatch) ? '' : 'none';
        });
    }
}


/**
 * Inicjalizuje nasłuchiwanie na zdarzenia w panelu filtrów.
 */
export function initializeWorkoutFilters() {
    const filterPanel = document.getElementById('workout-filters');
    if (!filterPanel) return;

    const nameInput = filterPanel.querySelector('#filter-exercise-name');
    const groupSelect = filterPanel.querySelector('#filter-exercise-group');
    const resetButton = filterPanel.querySelector('#reset-filters-btn');

    if (nameInput) {
        nameInput.addEventListener('input', (e) => {
            filters.name = e.target.value.toLowerCase().trim();
            applyFilters();
        });
    }

    if (groupSelect) {
        groupSelect.addEventListener('change', (e) => {
            filters.group = e.target.value;
            applyFilters();
        });
    }
    
    if (resetButton) {
        resetButton.addEventListener('click', () => {
            if (nameInput) nameInput.value = '';
            if (groupSelect) groupSelect.value = 'all';
            filters.name = '';
            filters.group = 'all';
            applyFilters();
        });
    }
}