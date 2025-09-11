/**
 * Plik: exercises.js
 * Logika dla strony zarządzania ćwiczeniami (manage_exercises.php)
 */

export function handleExerciseFiltering() {
    const form = document.getElementById('filter-form');
    if (!form) return;

    const searchInput = form.querySelector('#filter-search');
    const categorySelect = form.querySelector('#filter-category');
    const tagsSelect = form.querySelector('#filter-tags');
    const resetButton = form.querySelector('#filter-reset-btn');
    const exerciseCards = document.querySelectorAll('.exercise-card');
    const noResultsMessage = document.getElementById('no-results-message');

    const filterExercises = () => {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedCategory = categorySelect.value;
        const selectedTag = tagsSelect.value;
        let visibleCount = 0;

        exerciseCards.forEach(card => {
            const name = card.dataset.name.toLowerCase();
            const category = card.dataset.category;
            const tags = JSON.parse(card.dataset.tags || '[]');
            
            const nameMatch = name.includes(searchTerm);
            const categoryMatch = !selectedCategory || category === selectedCategory;
            const tagMatch = !selectedTag || tags.includes(selectedTag);

            const isVisible = nameMatch && categoryMatch && tagMatch;
            card.style.display = isVisible ? 'flex' : 'none';
            if (isVisible) visibleCount++;
        });

        if (noResultsMessage) {
            noResultsMessage.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    };

    form.addEventListener('input', filterExercises);
    form.addEventListener('change', filterExercises);
    resetButton.addEventListener('click', () => { 
        form.reset(); 
        filterExercises(); 
    });
}