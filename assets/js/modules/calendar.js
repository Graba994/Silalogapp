/**
 * Plik: calendar.js
 * Inicjalizuje i renderuje interaktywny kalendarz aktywności (FullCalendar) z opcją planowania.
 */

// Zmienne globalne modułu, aby przechowywać stan
let calendar;
let scheduleModalEl;
let scheduleModal;
let currentEventInfo = {};

/**
 * Wysyła żądanie do API kalendarza.
 * @param {object} data - Dane do wysłania (subAction, id, title, itd.).
 * @returns {Promise<object>} - Odpowiedź z serwera.
 */
async function apiCall(data) {
    try {
        // ZMIANA: Docelowy URL to teraz nasz router API
        const response = await fetch('api/index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            // ZMIANA: Dodajemy główną akcję dla routera
            body: JSON.stringify({
                action: 'handle_calendar',
                ...data 
            })
        });
        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || `Błąd serwera: ${response.statusText}`);
        }
        return await response.json();
    } catch (error) {
        console.error('Błąd API kalendarza:', error);
        alert(`Wystąpił błąd: ${error.message}`);
        return { success: false };
    }
}

/**
 * Otwiera i przygotowuje modal do dodawania nowego wydarzenia.
 * @param {object} dateClickInfo - Informacje o klikniętym dniu z FullCalendar.
 */
function openAddModal(dateClickInfo) {
    currentEventInfo = {
        isNew: true,
        date: dateClickInfo.dateStr,
        targetUserId: window.calendarUserId || ''
    };

    const form = document.getElementById('scheduleEventForm');
    form.reset();
    
    document.getElementById('modalDate').textContent = new Date(dateClickInfo.dateStr).toLocaleDateString('pl-PL');
    document.getElementById('eventDate').value = dateClickInfo.dateStr;
    document.getElementById('targetUserId').value = currentEventInfo.targetUserId;
    
    document.querySelector('#scheduleEventModalLabel').textContent = 'Zaplanuj Trening na ';
    document.getElementById('saveEventBtn').textContent = 'Zapisz';
    document.getElementById('deleteEventBtn').style.display = 'none';
    
    // Pokaż/ukryj opcje trenera
    document.getElementById('coachOptions').style.display = window.isCoachView ? 'block' : 'none';

    scheduleModal.show();
}

/**
 * Otwiera i przygotowuje modal do edycji istniejącego wydarzenia.
 * @param {object} eventClickInfo - Informacje o klikniętym wydarzeniu z FullCalendar.
 */
function openEditModal(eventClickInfo) {
    const event = eventClickInfo.event;
    currentEventInfo = {
        isNew: false,
        id: event.id,
        targetUserId: window.calendarUserId || '',
        createdBy: event.extendedProps.createdBy,
    };

    const form = document.getElementById('scheduleEventForm');
    document.getElementById('modalDate').textContent = event.start.toLocaleDateString('pl-PL');
    document.getElementById('eventTitle').value = event.title.replace(/^★\s/, '');
    document.getElementById('eventPlanId').value = event.extendedProps.planId || 'adhoc';
    document.getElementById('eventDate').value = event.startStr.split('T')[0];
    document.getElementById('targetUserId').value = currentEventInfo.targetUserId;
    
    document.querySelector('#scheduleEventModalLabel').textContent = 'Edytuj Trening na ';
    document.getElementById('saveEventBtn').textContent = 'Zapisz Zmiany';
    
    // Ustaw kolor
    const colorInput = document.querySelector(`input[name="eventColor"][value="${event.backgroundColor}"]`);
    if(colorInput) colorInput.checked = true;

    // Pokaż/ukryj opcje trenera i ustaw ich stan
    const coachOptions = document.getElementById('coachOptions');
    if (window.isCoachView) {
        coachOptions.style.display = 'block';
        document.getElementById('isCoachSession').checked = event.extendedProps.isCoachSession || false;
    } else {
        coachOptions.style.display = 'none';
    }

    // Pokaż przycisk usuwania i edycji tylko jeśli użytkownik ma uprawnienia
    const canEdit = window.isCoachView || currentEventInfo.createdBy === window.currentUserId || window.currentUserRole === 'admin';
    document.getElementById('deleteEventBtn').style.display = canEdit ? 'block' : 'none';
    document.getElementById('saveEventBtn').style.display = canEdit ? 'block' : 'none';
    form.querySelectorAll('input, select').forEach(el => el.disabled = !canEdit);

    scheduleModal.show();
}

/**
 * Główna funkcja inicjalizująca FullCalendar.
 */
export function initializeActivityCalendar() {
    const calendarEl = document.getElementById('activity-calendar');
    scheduleModalEl = document.getElementById('scheduleEventModal');

    if (!calendarEl || !scheduleModalEl) return;
    if (typeof FullCalendar === 'undefined') return;

    scheduleModal = new bootstrap.Modal(scheduleModalEl);
    
    const planSelect = document.getElementById('eventPlanId');
    if (window.userPlansForCalendar && planSelect) {
        planSelect.innerHTML = '<option value="adhoc" selected>Trening Ad-Hoc (bez planu)</option>';
        window.userPlansForCalendar.forEach(plan => {
            const option = document.createElement('option');
            option.value = plan.plan_id;
            option.textContent = plan.plan_name;
            planSelect.appendChild(option);
        });
    }

    calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'pl',
        initialView: 'dayGridMonth',
        firstDay: 1,
        headerToolbar: { left: 'prev', center: 'title', right: 'next' },
        height: 'auto',
        buttonText: { today: 'Dziś' },
        editable: true,
        events: window.allCalendarEvents || [],

        dateClick: openAddModal,
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            if (info.event.extendedProps.isCompleted) {
                if(info.event.url) window.location.href = info.event.url;
            } else {
                openEditModal(info); // Otwórz modal edycji dla zaplanowanych
            }
        },
        eventDrop: async function(info) {
            // ZMIANA: Zamiast 'action' używamy 'subAction'
            const response = await apiCall({
                subAction: 'update_date',
                id: info.event.id,
                newDate: info.event.startStr.split('T')[0],
                targetUserId: window.calendarUserId || ''
            });
            if (!response.success) info.revert();
        },
        eventDidMount: function(info) {
            let tooltipTitle = 'Kliknij, aby edytować lub rozpocząć';
            if (info.event.extendedProps.isCompleted) tooltipTitle = 'Kliknij, aby zobaczyć szczegóły';
            else if (info.event.extendedProps.type === 'coach') tooltipTitle = `Zaplanowane przez trenera. ${tooltipTitle}`;
            new bootstrap.Tooltip(info.el, { title: tooltipTitle, placement: 'top', trigger: 'hover', container: 'body' });
        }
    });

    calendar.render();

    document.getElementById('saveEventBtn').addEventListener('click', async () => {
        const form = document.getElementById('scheduleEventForm');
        if (!form.checkValidity()) { form.reportValidity(); return; }

        const data = {
            // ZMIANA: Zamiast 'action' używamy 'subAction'
            subAction: currentEventInfo.isNew ? 'add' : 'edit',
            id: currentEventInfo.id,
            targetUserId: form.elements.targetUserId.value,
            title: form.elements.title.value,
            date: form.elements.date.value,
            planId: form.elements.planId.value,
            color: form.elements.eventColor.value,
            type: window.isCoachView ? 'coach' : 'self',
            isCoachSession: form.elements.isCoachSession ? form.elements.isCoachSession.checked : false,
        };
        
        const response = await apiCall(data);
        if (response.success) {
            window.location.reload(); // Najprostszy sposób na odświeżenie danych
        }
    });

    document.getElementById('deleteEventBtn').addEventListener('click', async () => {
        if (!confirm('Czy na pewno chcesz usunąć to zaplanowane wydarzenie?')) return;
        
        // ZMIANA: Zamiast 'action' używamy 'subAction'
        const response = await apiCall({
            subAction: 'delete',
            id: currentEventInfo.id,
            targetUserId: currentEventInfo.targetUserId,
        });

        if (response.success) {
            window.location.reload(); // Najprostszy sposób na odświeżenie danych
        }
    });
}