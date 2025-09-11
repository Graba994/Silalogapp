// =========================================================================
// === GŁÓWNY PLIK INICJUJĄCY APLIKACJĘ (app.js) ===
// =========================================================================

// --- KROK 1: IMPORT WSZYSTKICH POTRZEBNYCH MODUŁÓW ---

// Moduły globalne (działają na każdej stronie)
import { handleThemeToggle } from './modules/theme.js';
import { handleDeleteButtons, handleExerciseInfoModal } from './modules/common.js';

// Moduły specyficzne dla poszczególnych podstron
import { handleWorkoutForm } from './modules/workout.js';
import { handlePlanFiltering, handlePlanEditMode, handlePlanImport } from './modules/plans.js';
import { handleExerciseFiltering } from './modules/exercises.js';
import { handleStatsForm } from './modules/stats.js';
import { handleGoalsPage, handleGoalsFiltering } from './modules/goals.js';
import { initializeCommunityFeed } from './modules/community.js';
import { initializeWorkoutFilters } from './modules/workoutFilter.js';
import { initializeSharedWorkout } from './modules/shared_workout.js';
import { initializeActivityCalendar } from './modules/calendar.js'; // <-- Zmieniona nazwa importu

/**
 * Główna funkcja inicjująca, uruchamiana po załadowaniu drzewa DOM.
 * Organizuje kod w logiczne bloki: najpierw globalne, potem specyficzne.
 */
function initialize() {
    
    // --- SEKCJA GLOBALNA (uruchamiana zawsze) ---
    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
    [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl));
    
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

    handleThemeToggle();
    handleDeleteButtons();
    handleExerciseInfoModal();

    
    // --- SEKCJA SPECYFICZNA (uruchamiana warunkowo) ---
    
    const workoutForm = document.getElementById('workout-form');
    if (workoutForm) {
        handleWorkoutForm(workoutForm);
        initializeWorkoutFilters();
    }
    
    const planCreatorForm = document.getElementById('plan-creator-form');
    if (planCreatorForm) {
        handleWorkoutForm(planCreatorForm); 
    }
    
    const plansContainer = document.getElementById('plans-container');
    if (plansContainer) {
        handlePlanEditMode();
        handlePlanFiltering();
        handlePlanImport();
    }
    
    const exerciseListPage = document.getElementById('exercise-list-container');
    if (exerciseListPage) {
        handleExerciseFiltering();
    }

    const statsForm = document.getElementById('stats-form');
    if (statsForm) {
        handleStatsForm(statsForm);
    }

    const goalsAccordion = document.getElementById('goalsAccordion');
    if (goalsAccordion) {
        handleGoalsPage(goalsAccordion.closest('form'));
        handleGoalsFiltering();
    }

    const communityFeed = document.querySelector('.activity-feed');
    if (communityFeed) {
        initializeCommunityFeed();
    }

    const sharedWorkoutContainer = document.getElementById('live-workout-container');
    if (sharedWorkoutContainer) {
        initializeSharedWorkout();
    }
    
    // Inicjalizacja kalendarza na Dashboardzie
    const calendarEl = document.getElementById('activity-calendar');
    if (calendarEl) {
        initializeActivityCalendar(); // <-- Zmieniona nazwa wywołania
    }
}

// Uruchom całą aplikację po załadowaniu strony
document.addEventListener('DOMContentLoaded', initialize);