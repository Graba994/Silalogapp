/**
 * Moduł do obsługi interfejsu i logiki dla treningu wspólnego (shared_workout.php)
 * Wersja z inteligentnym wykrywaniem zmian, walidacją i logiką "Trybu Trenera".
 * 
 * ZMIANY: 
 * 1. Przebudowano logikę dodawania ćwiczeń, aby uniknąć błędów (race conditions).
 *    Teraz zdarzenie onChange tylko wysyła zapytanie do API, a funkcja handleApiResponse
 *    odpowiada za odświeżenie całego UI, co jest znacznie bardziej stabilnym rozwiązaniem.
 * 2. Dodano rozbudowane logowanie do konsoli (console.log, console.error) i bloki try...catch,
 *    aby ułatwić diagnozowanie ewentualnych problemów w przyszłości.
 */

// --- ZMIENNE GLOBALNE MODUŁU ---
let state = {};
let container, mainContentArea, participantNav, liveWorkoutId, currentUserId, pollingInterval;
const allExercises = window.allExercisesData || [];

// --- GŁÓWNA FUNKCJA INICJALIZACYJNA ---
export function initializeSharedWorkout() {
    container = document.getElementById('live-workout-container');
    if (!container) return;

    mainContentArea = document.getElementById('main-content-area');
    participantNav = document.getElementById('participant-nav');
    liveWorkoutId = container.dataset.liveId;
    currentUserId = container.dataset.userId;

    init();
}

// === FUNKCJE POMOCNICZE ===

async function apiCall(method = 'GET', body = null) {
    const url = `api/live_workout_handler.php${method === 'GET' ? '?id=' + liveWorkoutId : ''}`;
    try {
        const response = await fetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : null });
        if (!response.ok) {
            const errorText = await response.text();
            console.error(`Błąd serwera (${response.status}):`, errorText);
            throw new Error(`Błąd serwera: ${response.status}`);
        }
        const data = await response.json();
        if (typeof data !== 'object' || data === null) throw new SyntaxError("Odpowiedź serwera nie jest prawidłowym JSON-em.");
        return data;
    } catch (error) {
        console.error('Błąd wywołania API:', error);
        return { status: 'error', message: error.message };
    }
}

async function enrichStateWithDetails() {
    if (state.participants && (!state.participants_details || Object.keys(state.participants_details).length !== state.participants.length)) {
        state.participants_details = {};
        try {
            const usersResponse = await fetch('data/users.json');
            const allUsers = await usersResponse.json();
            state.participants.forEach(pId => {
                state.participants_details[pId] = allUsers.find(u => u.id === pId) || { id: pId, name: 'Nieznany', icon: 'bi-question-circle' };
            });
        } catch (error) {
            console.error("Nie udało się pobrać danych użytkowników:", error);
        }
    }
}

function updateSetInState(pId, exIndex, setIndex, newSetData) {
    if (!state.live_data[pId]?.[exIndex]?.sets) return;
    state.live_data[pId][exIndex].sets[setIndex] = { ...state.live_data[pId][exIndex].sets[setIndex], ...newSetData };
}

function getStructureSignature(workoutState) {
    if (!workoutState.base_plan?.exercises || !workoutState.participants) return '';
    let signature = `p${workoutState.participants.length}_e${workoutState.base_plan.exercises.length}_`;
    if (workoutState.participants.length > 0 && workoutState.participants[0]) {
        const firstParticipantId = workoutState.participants[0];
        signature += workoutState.base_plan.exercises.map((ex, exIndex) => {
            return workoutState.live_data[firstParticipantId]?.[exIndex]?.sets?.length || 0;
        }).join('-');
    }
    return signature;
}

// === FUNKCJE RENDERUJĄCE INTERFEJS ===

function renderUI() {
    console.log("renderUI: Rozpoczynam przebudowę interfejsu ze stanem:", JSON.parse(JSON.stringify(state)));
    try {
        if (!state.participants_details) {
            console.warn("renderUI: Przerwano - brak `participants_details` w stanie.");
            return;
        }
        const canAddContent = (state.coach_mode && state.owner_id === currentUserId) || !state.coach_mode;

        renderParticipantViews();
        renderParticipantNav();
        updateProgressIndicators();
        
        const activeViewId = document.querySelector('.participant-view.active')?.id.replace('view-', '') || currentUserId;
        const initialViewId = state.participants.includes(activeViewId) ? activeViewId : (state.participants[0] || currentUserId);
        if (initialViewId) switchView(initialViewId);

        document.getElementById('add-live-exercise-btn').style.display = canAddContent ? '' : 'none';
        console.log("renderUI: Zakończono pomyślnie.");
    } catch (error) {
        console.error("Krytyczny błąd podczas renderowania UI:", error);
        mainContentArea.innerHTML = `<div class="alert alert-danger">Wystąpił błąd krytyczny podczas odświeżania interfejsu. Sprawdź konsolę (F12) po więcej szczegółów.</div>`;
    }
}

function renderParticipantViews() {
    mainContentArea.innerHTML = '';
    const fragment = document.createDocumentFragment();
    state.participants.forEach(pId => {
        const viewTpl = document.getElementById('participant-view-template').innerHTML;
        const viewDiv = document.createElement('div');
        viewDiv.innerHTML = viewTpl.replace(/{pId}/g, pId);
        const exercisesContainer = viewDiv.querySelector('.exercises-container');
        
        if (state.base_plan.exercises.length > 0) {
            state.base_plan.exercises.forEach((exercise, exIndex) => {
                exercisesContainer.appendChild(renderExerciseBlock(pId, exIndex));
            });
        } else {
             exercisesContainer.innerHTML = `<div class="text-center p-5 text-muted">Brak ćwiczeń. Użyj przycisku poniżej, aby dodać pierwsze.</div>`;
        }
        fragment.appendChild(viewDiv.firstElementChild);
    });
    mainContentArea.appendChild(fragment);
}

function renderExerciseBlock(pId, exIndex) {
    const exercise = state.base_plan.exercises[exIndex];
    const exerciseDetails = allExercises.find(e => e.id === exercise.exercise_id);
    const exTpl = document.getElementById('exercise-block-template').innerHTML;
    const exBlockHtml = exTpl.replace(/{exIndex}/g, exIndex).replace(/{exId}/g, exercise.exercise_id).replace('{exName}', exerciseDetails?.name || 'Nieznane ćwiczenie');
    
    const exBlockWrapper = document.createElement('div');
    exBlockWrapper.innerHTML = exBlockHtml;
    const exBlock = exBlockWrapper.firstElementChild;

    const addSetBtn = exBlock.querySelector('.add-set-btn');
    const canAddContent = (state.coach_mode && state.owner_id === currentUserId) || !state.coach_mode;
    if (!canAddContent) {
        addSetBtn.style.display = 'none';
    }

    const setsContainer = exBlock.querySelector('.sets-container');
    const setsForUser = state.live_data[pId]?.[exIndex]?.sets || [];
    if (setsForUser.length > 0) {
        setsContainer.innerHTML = '';
        setsForUser.forEach((set, setIndex) => {
            setsContainer.innerHTML += renderSetRow(pId, exIndex, setIndex, set);
        });
    } else {
        setsContainer.innerHTML = `<div class="list-group-item text-muted small text-center">Brak serii.</div>`;
    }
    return exBlock;
}

function renderSetRow(pId, exIndex, setIndex, set) {
    const setTpl = document.getElementById('set-row-template').innerHTML;
    const isCompleted = set.status === 'completed';
    const canEdit = (currentUserId === pId) || (state.coach_mode && state.owner_id === currentUserId);
    return setTpl
        .replace(/{pId}/g, pId).replace(/{exIndex}/g, exIndex).replace(/{setIndex}/g, setIndex)
        .replace('{setNum}', setIndex + 1).replace('{reps}', set.reps || '').replace('{weight}', set.weight || '')
        .replace(/{disabled}/g, !canEdit ? 'disabled' : '').replace('{btnClass}', isCompleted ? 'btn-success' : 'btn-outline-secondary')
        .replace('{icon}', isCompleted ? 'bi-check-circle-fill' : 'bi-circle');
}

function renderParticipantNav() {
    participantNav.innerHTML = '';
    state.participants.forEach(pId => {
        const pData = state.participants_details[pId];
        const linkTpl = document.getElementById('participant-nav-link-template').innerHTML;
        participantNav.innerHTML += linkTpl.replace(/{pId}/g, pId).replace('{pIcon}', pData.icon).replace('{pName}', pData.name);
    });
}

function updateProgressIndicators() {
    const container = document.getElementById('progress-indicators');
    container.innerHTML = '';
    state.participants.forEach(pId => {
        const pData = state.participants_details[pId];
        let totalSets = 0, completedSets = 0;
        state.base_plan.exercises.forEach((ex, exIndex) => {
            const sets = state.live_data[pId]?.[exIndex]?.sets || [];
            totalSets += sets.length;
            completedSets += sets.filter(s => s.status === 'completed').length;
        });
        const indicatorTpl = document.getElementById('progress-indicator-template').innerHTML;
        container.innerHTML += indicatorTpl.replace('{pName}', pData.name).replace('{pIcon}', pData.icon).replace('{completed}', completedSets).replace('{total}', totalSets);
    });
}

function switchView(targetPId) {
    document.querySelectorAll('.participant-view').forEach(v => v.classList.remove('active'));
    document.querySelector(`#view-${targetPId}`)?.classList.add('active');
    document.querySelectorAll('#participant-nav .nav-link').forEach(l => l.classList.remove('active'));
    document.querySelector(`#participant-nav .nav-link[data-p-id="${targetPId}"]`)?.classList.add('active');
}

// === GŁÓWNA LOGIKA I OBSŁUGA ZDARZEŃ ===

async function init() {
    console.log("Inicjalizacja treningu live...");
    const initialStateResponse = await apiCall('POST', { live_workout_id: liveWorkoutId, action: 'initialize' });
    
    if (initialStateResponse?.success && initialStateResponse.updated_workout?.status === 'active') {
        state = initialStateResponse.updated_workout;
        await enrichStateWithDetails();
        renderUI();
    } else if (initialStateResponse?.status === 'finished' || initialStateResponse.updated_workout?.status === 'finished') {
        console.log("Trening już zakończony, przekierowanie...");
        window.location.href = 'dashboard.php?status=workout_finished';
        return;
    } else {
        console.error("Błąd inicjalizacji:", initialStateResponse);
        mainContentArea.innerHTML = `<div class="alert alert-danger">Nie udało się zainicjować treningu. Błąd: ${initialStateResponse.message || 'Nieznany'}</div>`;
        return;
    }

    pollingInterval = setInterval(async () => {
        console.log("Polling: Pobieranie nowego stanu...");
        const latestState = await apiCall();
        if (latestState?.status === 'active') {
            const oldSignature = getStructureSignature(state);
            const newSignature = getStructureSignature(latestState);
            const structureChanged = oldSignature !== newSignature;
            
            if (JSON.stringify(state) !== JSON.stringify(latestState)) {
                 console.log("Polling: Wykryto zmiany. Stary podpis:", oldSignature, "Nowy:", newSignature);
                state = { ...state, ...latestState };
                await enrichStateWithDetails();
                structureChanged ? renderUI() : updateUI();
            }
        } else if (latestState?.status === 'finished') {
            console.log("Polling: Trening zakończony, zatrzymuję odpytywanie.");
            clearInterval(pollingInterval);
            alert('Trening został zakończony.');
            window.location.href = 'history.php';
        }
    }, 3000);

    document.body.addEventListener('click', handleClicks);
    document.body.addEventListener('change', handleChanges);
    participantNav.addEventListener('click', e => {
        e.preventDefault();
        const link = e.target.closest('.nav-link');
        if (link?.dataset.pId) switchView(link.dataset.pId);
    });
}

async function handleClicks(e) {
    const button = e.target.closest('button');
    if (!button) return;

    if (button.id === 'cancel-workout-btn' || button.id === 'finish-workout-btn') {
        const isFinishing = button.id === 'finish-workout-btn';
        const confirmationMessage = isFinishing ? 'Zakończyć trening dla wszystkich?' : 'Anulować ten trening? Postępy zostaną utracone.';
        const endpoint = isFinishing ? 'finish_shared_workout.php' : 'cancel_shared_workout.php';
        
        if (confirm(confirmationMessage)) {
            clearInterval(pollingInterval);
            const result = await fetch(endpoint, { method: 'POST', body: JSON.stringify({ live_workout_id: liveWorkoutId }) }).then(res => res.json());
            alert(result.message || 'Błąd');
            if (result.success) window.location.href = isFinishing ? 'history.php' : 'dashboard.php';
        }
        return;
    }

    if (button.id === 'add-live-exercise-btn') {
        if (document.querySelector('.new-exercise-prompt')) return;

        const activeView = document.querySelector('.participant-view.active .exercises-container');
        if (!activeView) {
            console.error("Nie znaleziono aktywnego kontenera ćwiczeń do dodania nowego pola.");
            return;
        }

        const adhocTemplate = document.getElementById('new-exercise-adhoc-template').innerHTML.replace('{exIndex}', state.base_plan.exercises.length);
        activeView.insertAdjacentHTML('beforeend', adhocTemplate);
        const newSelect = activeView.lastElementChild.querySelector('.new-exercise-select');
        
        new TomSelect(newSelect, {
            options: allExercises.map(ex => ({ value: ex.id, text: ex.name })),
            placeholder: 'Wpisz, aby wyszukać ćwiczenie...',
            onInitialize: function() { this.open(); },
            onChange: value => {
                if (value) {
                    console.log(`Wybrano ćwiczenie o ID: ${value}. Wysyłanie do API...`);
                    apiCall('POST', { live_workout_id: liveWorkoutId, action: 'add_exercise', exerciseId: value })
                        .then(handleApiResponse);
                }
            },
            onBlur: function() {
                setTimeout(() => {
                    if (this.getValue() === "") {
                        console.log("Użytkownik nie wybrał ćwiczenia. Usuwanie pola.");
                        const prompt = document.querySelector('.new-exercise-prompt');
                        if(prompt) prompt.remove();
                        this.destroy();
                    }
                }, 200);
            }
        });
        return;
    }
    
    const setRow = button.closest('.set-row');
    if (button.classList.contains('check-btn') && setRow) {
        const { pId, exIndex, setIndex } = setRow.dataset;
        const exIndexInt = parseInt(exIndex, 10), setIndexInt = parseInt(setIndex, 10);
        const reps = setRow.querySelector('.reps-input').value, weight = setRow.querySelector('.weight-input').value;
        const currentSet = state.live_data[pId]?.[exIndexInt]?.sets?.[setIndexInt] || { status: 'pending' };
        const newStatus = currentSet.status === 'completed' ? 'pending' : 'completed';
        
        updateSetInState(pId, exIndexInt, setIndexInt, { reps, weight, status: newStatus });
        setRow.outerHTML = renderSetRow(pId, exIndexInt, setIndexInt, state.live_data[pId][exIndexInt].sets[setIndexInt]);
        updateProgressIndicators();
        
        apiCall('POST', { live_workout_id: liveWorkoutId, action: 'update_set', pId, exIndex: exIndexInt, setIndex: setIndexInt, setData: { reps, weight, status: newStatus } });
        return;
    }
    
    const exBlock = button.closest('.exercise-block-v2');
    if (button.classList.contains('add-set-btn') && exBlock) {
        const exIndex = parseInt(exBlock.dataset.exIndex, 10);
        apiCall('POST', { live_workout_id: liveWorkoutId, action: 'add_set', exIndex }).then(handleApiResponse);
    }
}

function handleChanges(e) {
    const input = e.target.closest('input.reps-input, input.weight-input');
    if (!input) return;

    const setRow = input.closest('.set-row');
    const { pId, exIndex, setIndex } = setRow.dataset;
    const exIndexInt = parseInt(exIndex, 10), setIndexInt = parseInt(setIndex, 10);
    const reps = setRow.querySelector('.reps-input').value, weight = setRow.querySelector('.weight-input').value;
    const status = state.live_data[pId]?.[exIndexInt]?.sets?.[setIndexInt]?.status || 'pending';

    updateSetInState(pId, exIndexInt, setIndexInt, { reps, weight });
    apiCall('POST', { live_workout_id: liveWorkoutId, action: 'update_set', pId, exIndex: exIndexInt, setIndex: setIndexInt, setData: { reps, weight, status } });
}

async function handleApiResponse(result) {
    console.log("Otrzymano odpowiedź z API po akcji POST:", result);
    if (result?.success && result.updated_workout) {
        state = result.updated_workout;
        await enrichStateWithDetails();
        renderUI();
    } else {
        console.error("Odpowiedź API nie powiodła się lub zwróciła nieprawidłowe dane:", result?.message);
        alert(`Wystąpił błąd: ${result?.message || 'Nieznany błąd serwera.'}`);
    }
}

function updateUI() {
    try {
        state.participants.forEach(pId => {
            state.base_plan.exercises.forEach((ex, exIndex) => {
                const setsForUser = state.live_data[pId]?.[exIndex]?.sets || [];
                setsForUser.forEach((set, setIndex) => {
                    const setRow = document.querySelector(`.set-row[data-p-id="${pId}"][data-ex-index="${exIndex}"][data-set-index="${setIndex}"]`);
                    if (setRow) {
                        const repsInput = setRow.querySelector('.reps-input');
                        const weightInput = setRow.querySelector('.weight-input');
                        const checkBtn = setRow.querySelector('.check-btn');
                        
                        if (document.activeElement !== repsInput) repsInput.value = set.reps || '';
                        if (document.activeElement !== weightInput) weightInput.value = set.weight || '';
                        
                        const isCompleted = set.status === 'completed';
                        if ((isCompleted && !checkBtn.classList.contains('btn-success')) || (!isCompleted && !checkBtn.classList.contains('btn-outline-secondary'))) {
                             checkBtn.classList.toggle('btn-success', isCompleted);
                             checkBtn.classList.toggle('btn-outline-secondary', !isCompleted);
                             checkBtn.querySelector('i').className = isCompleted ? 'bi bi-check-circle-fill' : 'bi bi-circle';
                        }
                    }
                });
            });
        });
        updateProgressIndicators();
    } catch (error) {
        console.error("Błąd podczas 'lekkiego' odświeżania UI (updateUI):", error);
    }
}