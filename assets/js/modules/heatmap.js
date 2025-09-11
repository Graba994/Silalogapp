/**
 * Plik: heatmap.js
 * Inicjalizuje i renderuje mapę aktywności (CalHeatmap) na panelu głównym.
 */

export function initializeHeatmap() {
    const container = document.getElementById('cal-heatmap');
    // Sprawdź, czy na stronie jest element mapy i czy dane są dostępne
    if (!container || !window.heatmapData) {
        return;
    }

    // Sprawdź, czy biblioteka jest załadowana
    if (typeof CalHeatmap === 'undefined') {
        console.error('Biblioteka CalHeatmap nie została załadowana.');
        return;
    }

    const cal = new CalHeatmap();
    const monthLabels = { 1: 'Sty', 2: 'Lut', 3: 'Mar', 4: 'Kwi', 5: 'Maj', 6: 'Cze', 7: 'Lip', 8: 'Sie', 9: 'Wrz', 10: 'Paź', 11: 'Lis', 12: 'Gru' };

    try {
        cal.paint({
            data: {
                source: window.heatmapData,
                type: 'json',
                x: 'date',
                y: 'value'
            },
            date: { 
                start: new Date(new Date().setFullYear(new Date().getFullYear() - 1)),
                locale: { weekStart: 1 } // Zaczynamy tydzień od poniedziałku
            },
            range: 12,
            scale: {
                color: {
                    type: 'threshold',
                    range: ['#c6e48b', '#7bc96f', '#239a3b', '#196127'],
                    domain: [1, 5, 10, 15]
                }
            },
            domain: {
                type: 'month',
                gutter: 5,
                label: { 
                    text: (timestamp) => monthLabels[new Date(timestamp).getMonth() + 1] || '',
                    textAlign: 'start', 
                    position: 'top' 
                }
            },
            subDomain: { 
                type: 'ghDay', 
                radius: 3,
                width: 15,
                height: 15,
                gutter: 5
            },
            itemSelector: '#cal-heatmap'
        },
        [
            [
                // UWAGA: Tak się teraz importuje pluginy w CalHeatmap v4
                window.CalHeatmap.Tooltip,
                {
                    text: function (date, value, item) {
                        const day = date.getDate();
                        const month = date.toLocaleString('pl-PL', { month: 'long' });
                        const year = date.getFullYear();
                        const formattedDate = `${day} ${month} ${year}`;
                        return (value ? `<strong>${value}</strong> ćwiczeń` : "Brak aktywności") + ` w dniu ${formattedDate}`;
                    },
                },
            ],
        ]);
    } catch (error) {
        console.error("Błąd podczas renderowania CalHeatmap:", error);
        container.innerHTML = '<div class="alert alert-danger">Wystąpił błąd podczas ładowania mapy aktywności.</div>';
    }
}