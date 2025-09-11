/**
 * Plik: stats.js
 * Logika dla strony statystyk (stats.php)
 */

export function handleStatsForm(statsForm) {
    statsForm.querySelectorAll('.date-preset').forEach(button => {
        button.addEventListener('click', function() {
            const preset = this.dataset.preset;
            const today = new Date();
            let start = new Date();
            const end = new Date();

            if (preset === 'week') {
                start.setDate(today.getDate() - (today.getDay() === 0 ? 6 : today.getDay() - 1));
            } else if (preset === 'month') {
                start = new Date(today.getFullYear(), today.getMonth(), 1);
            } else if (preset === 'quarter') {
                start = new Date(today.getFullYear(), Math.floor(today.getMonth() / 3) * 3, 1);
            } else if (preset === 'year') {
                start = new Date(today.getFullYear(), 0, 1);
            }

            const startDateInput = statsForm.querySelector('#start_date');
            const endDateInput = statsForm.querySelector('#end_date');

            if (startDateInput) {
                startDateInput.value = start.toISOString().split('T')[0];
            }
            if (endDateInput) {
                endDateInput.value = end.toISOString().split('T')[0];
            }
            
            statsForm.submit();
        });
    });
}