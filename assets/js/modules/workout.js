/**
 * Plik: workout.js
 * Logika dla strony logowania treningu (log_workout.php) oraz kreatora planów.
 * WERSJA Z AUTOSAVE DLA TRENINGU SOLO
 */

// === NOWA SEKCJA: LOGIKA AUTOSAVE ===

let autosaveTimeout;

/**
 * Prosta funkcja "debounce" do opóźniania wykonania.
 * @param {Function} func Funkcja do wykonania.
 * @param {number} delay Opóźnienie w milisekundach.
 */
function debounce(func, delay = 1500) {
    clearTimeout(autosaveTimeout);
    autosaveTimeout = setTimeout(func, delay);
}

/**
 * Zbiera wszystkie dane z formularza treningu i tworzy obiekt JSON.
 * @param {HTMLFormElement} formElement Formularz treningu.
 * @returns {object|null} Obiekt z danymi treningu lub null, jeśli wystąpił błąd.
 */
function gatherWorkoutData(formElement) {
    try {
        const date = formElement.querySelector('#date').value;
        const notes = formElement.querySelector('#notes').value;
        // ZMIANA: Bezpieczniejsze pobieranie nazwy planu
        const planNameElement = formElement.querySelector('h1.h4');
        const planName = planNameElement ? planNameElement.textContent.replace('Trening: ', '').trim() : 'Nowy Trening (Ad-hoc)';
        
        const exercises = [];
        formElement.querySelectorAll('.exercise-block-v2').forEach(exBlock => {
            const exerciseId = exBlock.querySelector('.exercise-select, input[name*="[exercise_id]"]')?.value;
            if (!exerciseId) return;

            const exerciseData = {
                exercise_id: parseInt(exerciseId, 10),
                target_sets: [] // Używamy target_sets dla spójności z sesją
            };

            exBlock.querySelectorAll('.set-row, .list-group-item.set-row').forEach(setRow => {
                const setData = {};
                // Sprawdzamy, czy to seria z planu i czy jest odhaczona
                const performedFlag = setRow.querySelector('.performed-flag');
                if (performedFlag) {
                    setData.performed = performedFlag.value;
                }
                
                // Zbieramy dane z inputów dla tej serii
                setRow.querySelectorAll('input[name*="[sets]"]').forEach(input => {
                    const match = input.name.match(/\[(weight|reps|time|distance)\]$/);
                    if (match && input.value !== '') {
                        setData[match[1]] = input.value;
                    }
                });
                exerciseData.target_sets.push(setData);
            });
            exercises.push(exerciseData);
        });

        return {
            date,
            notes,
            plan: {
                plan_name: planName,
                exercises: exercises
            }
        };
    } catch (error) {
        console.error("Błąd podczas zbierania danych treningu:", error);
        return null;
    }
}

/**
 * Wysyła zebrane dane treningu do API w celu zapisania sesji.
 * @param {HTMLFormElement} formElement Formularz treningu.
 */
async function saveSoloSession(formElement) {
    const workoutData = gatherWorkoutData(formElement);
    if (!workoutData) return;

    const statusIndicator = document.getElementById('autosave-status');
    if (statusIndicator) {
        statusIndicator.innerHTML = '<i class="bi bi-arrow-repeat"></i> Zapisywanie...';
        statusIndicator.className = 'text-warning small';
    }

    try {
        // ZMIANA: Docelowy URL to teraz nasz router API
        const response = await fetch('api/index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            // ZMIANA: Dodajemy główną akcję dla routera i przekazujemy dane
            body: JSON.stringify({
                action: 'save_solo_session',
                ...workoutData
            })
        });

        if (!response.ok) {
            throw new Error(`Błąd serwera: ${response.statusText}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            if (statusIndicator) {
                statusIndicator.innerHTML = '<i class="bi bi-check-circle-fill"></i> Zapisano zmiany';
                statusIndicator.className = 'text-success small';
            }
        } else {
            throw new Error(result.message || 'Nieznany błąd zapisu.');
        }

    } catch (error) {
        console.error('Błąd autosave:', error);
        if (statusIndicator) {
            statusIndicator.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> Błąd zapisu';
            statusIndicator.className = 'text-danger small';
        }
    }
}


// --- STARA LOGIKA (z modyfikacjami) ---

function manageStopwatch(button) {
    document.querySelectorAll('.start-timer-btn[data-state*="running"]').forEach(otherBtn => {
        if (otherBtn !== button) stopTimer(otherBtn);
    });
    const setRow = button.closest('.set-row, .list-group-item');
    const timeInput = setRow.querySelector('input.set-field-time, input[name*="[time]"]');
    const targetTime = parseInt(button.dataset.targetTime, 10) || 0;
    if (button.intervalId) {
        clearInterval(button.intervalId);
        button.intervalId = null;
    }
    const state = button.dataset.state || 'idle';
    if (state === 'idle' || state === 'stopped') {
        if (timeInput) timeInput.value = 0;
        button.disabled = true;
        let countdown = 3;
        button.dataset.state = 'countdown';
        button.classList.remove('btn-outline-secondary', 'btn-success', 'btn-danger');
        button.classList.add('btn-warning');
        button.innerHTML = `<i class="bi bi-hourglass-split"></i> ${countdown}`;
        button.intervalId = setInterval(() => {
            countdown--;
            button.innerHTML = `<i class="bi bi-hourglass-split"></i> ${countdown}`;
            if (countdown <= 0) {
                clearInterval(button.intervalId);
                button.disabled = false;
                if (targetTime > 0) runCountdownTimer(button, timeInput, targetTime);
                else runStopwatch(button, timeInput);
            }
        }, 1000);
    } else {
        stopTimer(button);
    }
}

function runCountdownTimer(button, timeInput, targetTime) {
    button.dataset.state = 'running-down';
    button.classList.replace('btn-warning', 'btn-danger');
    const startTime = Date.now();
    button.intervalId = setInterval(() => {
        const elapsed = Math.floor((Date.now() - startTime) / 1000);
        const remainingTime = targetTime - elapsed;
        if (timeInput) {
            timeInput.value = elapsed;
            timeInput.dispatchEvent(new Event('input', { bubbles: true })); // Trigger autosave
        }
        button.innerHTML = `<i class="bi bi-stopwatch"></i> ${remainingTime}s`;
        if (remainingTime <= 0) {
            clearInterval(button.intervalId);
            runBonusTimer(button, timeInput, targetTime);
        }
    }, 1000);
}

function runBonusTimer(button, timeInput, targetTime) {
    button.dataset.state = 'running-bonus';
    button.classList.replace('btn-danger', 'btn-success');
    const bonusStartTime = Date.now();
    button.intervalId = setInterval(() => {
        const bonusElapsed = Math.floor((Date.now() - bonusStartTime) / 1000);
        const totalTime = targetTime + bonusElapsed;
        if (timeInput) {
            timeInput.value = totalTime;
            timeInput.dispatchEvent(new Event('input', { bubbles: true })); // Trigger autosave
        }
        button.innerHTML = `<i class="bi bi-check-circle-fill"></i> +${bonusElapsed}s`;
    }, 1000);
}

function runStopwatch(button, timeInput) {
    button.dataset.state = 'running-up';
    button.classList.replace('btn-warning', 'btn-danger');
    const startTime = Date.now();
    button.intervalId = setInterval(() => {
        const elapsed = Math.floor((Date.now() - startTime) / 1000);
        if (timeInput) {
            timeInput.value = elapsed;
            timeInput.dispatchEvent(new Event('input', { bubbles: true })); // Trigger autosave
        }
        button.innerHTML = `<i class="bi bi-stopwatch-fill"></i> ${elapsed}s`;
    }, 1000);
}

function stopTimer(button) {
    if (button.intervalId) {
        clearInterval(button.intervalId);
        button.intervalId = null;
    }
    const setRow = button.closest('.set-row, .list-group-item');
    const timeInput = setRow.querySelector('input.set-field-time, input[name*="[time]"]');
    const finalTime = timeInput ? timeInput.value : 0;
    const displaySpan = setRow.querySelector('.set-display');
    if (displaySpan && finalTime > 0) {
        let currentText = displaySpan.innerHTML;
        let details = currentText.split('<span class="text-muted">·</span>').map(s => s.trim());
        details = details.filter(detail => !detail.includes('sek.'));
        details.push(`${finalTime} sek.`);
        displaySpan.innerHTML = details.join(' <span class="text-muted">·</span> ');
    }
    button.dataset.state = 'stopped';
    button.classList.remove('btn-warning', 'btn-danger', 'btn-success');
    button.classList.add('btn-outline-secondary');
    button.innerHTML = '<i class="bi bi-stopwatch"></i>';
}

function initializeTomSelect(selectElement) {
    if (!selectElement || selectElement.tomselect) return;
    new TomSelect(selectElement, {
        create: false,
        sortField: { field: "text", direction: "asc" },
        onChange: function() {
            selectElement.dispatchEvent(new Event('change', { 'bubbles': true }));
        }
    });
}

function handleAdHocElementManagement(formElement) {
    const exercisesContainer = formElement.querySelector('#exercises-container');
    const exerciseTemplate = document.getElementById('exercise-template');
    const setTemplate = document.getElementById('set-template');
    if (!exercisesContainer || !exerciseTemplate || !setTemplate) return;
    
    const addExercise = () => {
        const initialPrompt = formElement.querySelector('#initial-prompt');
        if (initialPrompt) initialPrompt.style.display = 'none';
        
        const exerciseIndex = Date.now(); 
        
        const newExerciseHtml = exerciseTemplate.innerHTML.replace(/{exercise_index}/g, exerciseIndex);
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = newExerciseHtml;
        const newExerciseElement = tempDiv.firstElementChild;
        newExerciseElement.dataset.exerciseIndex = exerciseIndex;
        
        exercisesContainer.appendChild(newExerciseElement);

        const newSelect = newExerciseElement.querySelector('.exercise-select');
        if (newSelect) {
            initializeTomSelect(newSelect);
        }
        
        const addSetButton = newExerciseElement.querySelector('.add-set');
        if (addSetButton) {
            addSet(addSetButton, false); 
        }
    };

    const addSet = (button, updateInputs = true) => {
        const exerciseBlock = button.closest('.exercise-block-v2, .exercise-block');
        if (!exerciseBlock) return;
        
        const setsContainer = exerciseBlock.querySelector('.sets-container');
        if (!setsContainer) return;

        const currentExerciseIndex = exerciseBlock.dataset.exerciseIndex;
        const setIndex = Date.now(); 

        let newSetHtml = setTemplate.innerHTML
            .replace(/{exercise_index}/g, currentExerciseIndex)
            .replace(/{set_index}/g, setIndex);
            
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = newSetHtml;
        const newSetElement = tempDiv.firstElementChild;

        const seriesCounter = newSetElement.querySelector('.series-counter');
        if (seriesCounter) {
            seriesCounter.textContent = `Seria ${setsContainer.children.length + 1}`;
        }
        
        setsContainer.appendChild(newSetElement);
        
        if (updateInputs) {
            const selectElement = exerciseBlock.querySelector('.exercise-select');
            if (selectElement && selectElement.value) {
                updateSetInputs(selectElement);
            }
        }
        
        // Trigger autosave after adding a set
        debounce(() => saveSoloSession(formElement));
    };
    
    const updateSetInputs = (selectElement) => {
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        if (!selectedOption) return;

        const exerciseBlock = selectElement.closest('.exercise-block-v2, .exercise-block');
        if (!exerciseBlock) return;
        
        exerciseBlock.dataset.exerciseName = selectedOption.textContent.toLowerCase();
        
        const prContainer = exerciseBlock.querySelector('.pr-display-container');
        if (prContainer) {
            const prWeight = parseFloat(selectedOption.dataset.prWeight) || 0;
            const prE1rm = parseFloat(selectedOption.dataset.prE1rm) || 0;
            prContainer.innerHTML = (prWeight > 0 || prE1rm > 0) 
                ? `<i class="bi bi-trophy-fill text-warning me-1"></i> PR: <strong>${prWeight > 0 ? prWeight + ' kg' : '-'}</strong> <span class="mx-1">·</span> e1RM: <strong>${prE1rm > 0 ? prE1rm + ' kg' : '-'}</strong>`
                : '';
        }

        const infoContainer = exerciseBlock.querySelector('.info-button-container');
        if (infoContainer) {
            infoContainer.innerHTML = '';
            const desc = selectedOption.dataset.desc || '';
            const howto = selectedOption.dataset.howto || '';
            if (desc || howto) {
                const infoBtn = document.createElement('button');
                infoBtn.type = 'button';
                infoBtn.className = 'btn btn-sm btn-outline-secondary info-btn';
                infoBtn.innerHTML = '<i class="bi bi-info-circle"></i>';
                Object.assign(infoBtn.dataset, { bsToggle: 'modal', bsTarget: '#exerciseInfoModal', bsName: selectedOption.textContent, bsDesc: desc, bsHowto: howto });
                infoContainer.appendChild(infoBtn);
            }
        }

        const tagsContainer = exerciseBlock.querySelector('.tags-display-container');
        if (tagsContainer) {
            tagsContainer.innerHTML = '';
            const tagIds = JSON.parse(selectedOption.dataset.tags || '[]');
            tagIds.forEach(tagId => {
                const tagInfo = window.APP_DATA?.tagMap?.[tagId];
                if (tagInfo) {
                    const badge = document.createElement('span');
                    badge.className = `badge fw-normal bg-${tagInfo.color || 'secondary'}`;
                    badge.textContent = tagInfo.name;
                    tagsContainer.appendChild(badge);
                }
            });
        }
        
        const trackBy = JSON.parse(selectedOption.dataset.trackBy || '[]');
        const allPossibleParams = window.APP_DATA.trackableParams || [];

        exerciseBlock.querySelectorAll('.set-row').forEach(setRow => {
            allPossibleParams.forEach(param => {
                const paramId = param.id;
                const fieldWrapper = setRow.querySelector(`.set-field-wrapper.set-field-${paramId}`);
                if (fieldWrapper) {
                    fieldWrapper.style.display = trackBy.includes(paramId) ? '' : 'none';
                }
            });
        });
    };

    formElement.addEventListener('click', (e) => {
        const button = e.target.closest('button');
        if (!button) return;
        if (button.id === 'add-exercise-btn' || button.id === 'add-first-exercise-btn') { e.preventDefault(); addExercise(); }
        if (button.classList.contains('add-set')) { e.preventDefault(); addSet(button); }
        if (button.classList.contains('remove-set')) { 
            e.preventDefault(); 
            button.closest('.set-row').remove();
            debounce(() => saveSoloSession(formElement)); // Trigger autosave
        }
        if (button.classList.contains('remove-exercise')) {
            e.preventDefault();
            button.closest('.exercise-block-v2, .exercise-block').remove();
            const initialPrompt = formElement.querySelector('#initial-prompt');
            if (exercisesContainer.children.length === 0 && initialPrompt) { initialPrompt.style.display = 'block'; }
            debounce(() => saveSoloSession(formElement)); // Trigger autosave
        }
    });
    
    formElement.addEventListener('change', (e) => {
        if (e.target.classList.contains('exercise-select')) {
            updateSetInputs(e.target);
            debounce(() => saveSoloSession(formElement)); // Trigger autosave
        }
    });
    
    formElement.querySelectorAll('.exercise-select').forEach(select => {
        initializeTomSelect(select);
        if (select.value) {
            updateSetInputs(select);
        }
    });
}

// --- GŁÓWNA EKSPORTOWANA FUNKCJA ---
export function handleWorkoutForm(workoutForm) {
    // Nasłuchuj na zmiany w całym formularzu, aby uruchomić autosave
    workoutForm.addEventListener('input', () => {
        // Upewnij się, że autosave działa tylko na stronie logowania treningu solo
        if (document.getElementById('autosave-status')) {
            debounce(() => saveSoloSession(workoutForm));
        }
    });

    workoutForm.addEventListener('click', function(e) {
        const button = e.target.closest('button');
        if (!button) return;
        
        if (button.classList.contains('start-timer-btn')) {
            e.preventDefault();
            manageStopwatch(button);
            return;
        }

        if (button.classList.contains('check-btn')) {
            e.preventDefault();
            const setRow = button.closest('.set-row, .list-group-item');
            const performedFlag = setRow.querySelector('.performed-flag');
            const icon = button.querySelector('i');
            const isChecked = button.classList.toggle('btn-success');
            button.classList.toggle('btn-outline-secondary', !isChecked);
            icon.classList.toggle('bi-check-circle-fill', isChecked);
            icon.classList.toggle('bi-circle', !isChecked);
            setRow.classList.toggle('bg-success-subtle', isChecked);
            if (performedFlag) performedFlag.value = isChecked ? '1' : '0';
            
            // Trigger autosave after checking a set
            if (document.getElementById('autosave-status')) {
               debounce(() => saveSoloSession(workoutForm));
            }
            return;
        }

        const setRow = button.closest('.set-row, .list-group-item');
        if (!setRow) return;

        if (button.classList.contains('edit-set-btn')) {
            e.preventDefault();
            setRow.classList.toggle('is-editing');
        }
        if (button.classList.contains('cancel-edit-btn')) {
            e.preventDefault();
            setRow.classList.remove('is-editing');
        }
        if (button.classList.contains('save-set-btn')) {
            e.preventDefault();
            const displaySpan = setRow.querySelector('.set-display');
            const editForm = setRow.querySelector('.set-edit-form');
            if (displaySpan && editForm) {
                const inputs = editForm.querySelectorAll('input[name*="[sets]"]');
                let details = [];
                inputs.forEach(input => {
                    if (input.value) {
                        let unit = '';
                        if (input.name.includes('[reps]')) unit = 'powt.';
                        if (input.name.includes('[weight]')) unit = 'kg';
                        if (input.name.includes('[time]')) unit = 'sek.';
                        details.push(`${input.value} ${unit}`);
                    }
                });
                displaySpan.innerHTML = details.length > 0 ? details.join(' <span class="text-muted">·</span> ') : 'Brak danych';
                setRow.classList.remove('is-editing');
                
                // Trigger autosave after saving a set
                if (document.getElementById('autosave-status')) {
                    debounce(() => saveSoloSession(workoutForm));
                }
            }
        }
    });

    handleAdHocElementManagement(workoutForm);
}