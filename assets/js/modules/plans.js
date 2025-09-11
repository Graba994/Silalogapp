/**
 * Plik: plans.js
 * Logika dla strony z planami treningowymi (plans.php)
 */

export function handlePlanImport() {
    const openBtn = document.getElementById('open-import-modal-btn');
    const modalEl = document.getElementById('importPlanModal');
    if (!openBtn || !modalEl) return;

    const importModal = new bootstrap.Modal(modalEl);
    const userSelect = modalEl.querySelector('#import-user-select');
    const planSelect = modalEl.querySelector('#import-plan-select');
    const loader = modalEl.querySelector('#import-loader');
    const importBtn = modalEl.querySelector('#confirm-import-btn');
    
    if (!userSelect || !planSelect || !loader || !importBtn) return;

    openBtn.addEventListener('click', () => {
        userSelect.value = '';
        planSelect.innerHTML = '<option value="">-- Najpierw wybierz użytkownika --</option>';
        planSelect.disabled = true;
        importBtn.disabled = true;
        importModal.show();
    });

    userSelect.addEventListener('change', async () => {
        const userId = userSelect.value;
        if (!userId) return;

        planSelect.innerHTML = '<option value="">Ładowanie planów...</option>';
        planSelect.disabled = true;
        importBtn.disabled = true;
        loader.classList.remove('d-none');

        try {
            // ZMIANA: Nowy, ujednolicony URL do API
            const response = await fetch(`api/index.php?action=get_user_plans&user_id=${userId}`);
            if (!response.ok) throw new Error(`Błąd serwera: ${response.statusText}`);
            const plans = await response.json();

            planSelect.innerHTML = '';
            if (plans.length > 0) {
                planSelect.innerHTML = '<option value="" selected disabled>-- Wybierz plan --</option>';
                plans.forEach(plan => {
                    const option = document.createElement('option');
                    option.value = plan.plan_id;
                    option.textContent = plan.plan_name;
                    planSelect.appendChild(option);
                });
                planSelect.disabled = false;
            } else {
                planSelect.innerHTML = '<option value="">Ten użytkownik nie ma planów</option>';
            }
        } catch (error) {
            console.error('Błąd podczas pobierania planów:', error);
            planSelect.innerHTML = '<option value="">Błąd ładowania</option>';
        } finally {
            loader.classList.add('d-none');
        }
    });

    planSelect.addEventListener('change', () => {
        importBtn.disabled = !planSelect.value;
    });
}

export function handlePlanEditMode() {
    const toggleButton = document.getElementById('toggle-edit-mode');
    const plansContainer = document.getElementById('plans-container');
    if (!toggleButton || !plansContainer) return;

    toggleButton.addEventListener('click', function() {
        const isActive = plansContainer.classList.toggle('edit-mode-active');
        toggleButton.classList.toggle('btn-primary', isActive);
        toggleButton.classList.toggle('btn-outline-secondary', !isActive);
        toggleButton.innerHTML = isActive ? '<i class="bi bi-check-lg"></i> Gotowe' : '<i class="bi bi-gear"></i> <span class="d-none d-sm-inline">Zarządzaj</span>';
    });
}

export function handlePlanFiltering() {
    const container = document.getElementById('plans-container');
    if (!container) return;
    
    const searchInput = document.getElementById('plan-search-input');
    const exerciseFilter = document.getElementById('plan-filter-exercise');
    const sortSelect = document.getElementById('plan-sort-select');
    const viewGridBtn = document.getElementById('view-toggle-grid');
    const viewListBtn = document.getElementById('view-toggle-list');
    const noResultsMsg = document.getElementById('no-plans-found');

    if (!searchInput || !viewGridBtn || !viewListBtn) return;
    
    const planCards = Array.from(container.querySelectorAll('.plan-card-wrapper'));

    const filterAndDisplay = () => {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedExerciseId = exerciseFilter.value;
        
        let visibleCards = planCards.filter(card => {
            const name = (card.dataset.planName || '').toLowerCase();
            const desc = (card.dataset.planDesc || '').toLowerCase();
            const exercises = JSON.parse(card.dataset.planExercises || '[]');
            const nameMatch = name.includes(searchTerm) || desc.includes(searchTerm);
            const exerciseMatch = !selectedExerciseId || exercises.includes(parseInt(selectedExerciseId));
            return nameMatch && exerciseMatch;
        });

        const sortValue = sortSelect.value;
        visibleCards.sort((a, b) => {
            const nameA = a.dataset.planName;
            const nameB = b.dataset.planName;
            return sortValue === 'name-desc' ? nameB.localeCompare(nameA) : nameA.localeCompare(nameB);
        });

        planCards.forEach(card => card.style.display = 'none');
        visibleCards.forEach(card => {
            container.appendChild(card); // Przenieś na koniec, aby zachować sortowanie
            card.style.display = 'block';
        });

        if (noResultsMsg) {
            noResultsMsg.style.display = visibleCards.length === 0 ? 'block' : 'none';
        }
    };

    viewGridBtn.addEventListener('click', () => { 
        container.classList.remove('list-view'); 
        viewGridBtn.classList.add('active'); 
        viewListBtn.classList.remove('active'); 
    });
    viewListBtn.addEventListener('click', () => { 
        container.classList.add('list-view'); 
        viewListBtn.classList.add('active'); 
        viewGridBtn.classList.remove('active'); 
    });

    [searchInput, exerciseFilter, sortSelect].forEach(el => {
        if (el) {
            el.addEventListener('input', filterAndDisplay);
            el.addEventListener('change', filterAndDisplay);
        }
    });

    filterAndDisplay(); // Uruchom przy załadowaniu strony
}