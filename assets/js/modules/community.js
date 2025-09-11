/**
 * Plik: community.js
 * Logika dla interakcji na stronie społeczności (community.php),
 * takich jak polubienia i komentarze.
 */

/**
 * Wysyła żądanie do API w celu obsługi interakcji (polubienie, komentarz).
 * @param {string} ownerId - ID właściciela treningu.
 * @param {string} workoutId - ID treningu.
 * @param {string} subAction - 'like' lub 'comment'.
 * @param {string|null} commentText - Treść komentarza (jeśli dotyczy).
 * @returns {Promise<object>} - Odpowiedź z serwera w formacie JSON.
 */
async function handleInteraction(ownerId, workoutId, subAction, commentText = null) {
    try {
        // ZMIANA: Docelowy URL to teraz nasz router API
        const response = await fetch('api/index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            // ZMIANA: Dodajemy główną akcję dla routera i przekazujemy dane
            body: JSON.stringify({ 
                action: 'handle_interaction',
                subAction, // 'like' lub 'comment'
                ownerId, 
                workoutId, 
                commentText 
            })
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || `Błąd serwera: ${response.statusText}`);
        }

        return await response.json();
    } catch (error) {
        console.error('Błąd interakcji:', error);
        // Można tutaj dodać powiadomienie dla użytkownika o błędzie
        return { success: false, error: error.message };
    }
}

/**
 * Tworzy element HTML dla nowego komentarza.
 * @param {object} comment - Obiekt komentarza zwrócony przez API.
 * @returns {HTMLElement} - Nowy element DOM gotowy do wstawienia.
 */
function createCommentElement(comment) {
    const commentEl = document.createElement('div');
    commentEl.className = 'd-flex small gap-2 mb-2';
    // Używamy bezpiecznego wstawiania tekstu, aby uniknąć problemów z XSS
    const icon = document.createElement('i');
    icon.className = 'bi bi-person-circle mt-1';
    
    const contentDiv = document.createElement('div');
    contentDiv.className = 'flex-grow-1';

    const strong = document.createElement('strong');
    strong.textContent = comment.user_name;

    const p = document.createElement('p');
    p.className = 'mb-0';
    p.textContent = comment.text;

    contentDiv.appendChild(strong);
    contentDiv.appendChild(p);
    commentEl.appendChild(icon);
    commentEl.appendChild(contentDiv);
    
    return commentEl;
}

/**
 * Inicjalizuje nasłuchiwanie na zdarzenia na tablicy aktywności.
 * Używa delegacji zdarzeń dla optymalnej wydajności.
 */
export function initializeCommunityFeed() {
    const feedContainer = document.querySelector('.activity-feed');
    if (!feedContainer) return;

    // Główny listener dla całej tablicy
    feedContainer.addEventListener('click', async (e) => {
        // --- OBSŁUGA POLUBIEŃ ---
        const likeButton = e.target.closest('.like-btn');
        if (likeButton) {
            e.preventDefault();
            likeButton.disabled = true; // Zapobiegaj wielokrotnym kliknięciom
            
            const { ownerId, workoutId } = likeButton.dataset;
            // ZMIANA: Przekazujemy 'like' jako subAction
            const result = await handleInteraction(ownerId, workoutId, 'like');

            if (result.success) {
                const likeCounter = likeButton.querySelector('.like-counter');
                const likeIcon = likeButton.querySelector('i');
                
                if (likeCounter) {
                    likeCounter.textContent = result.likesCount;
                }
                
                if (likeIcon) {
                    if (result.action === 'liked') {
                        likeButton.classList.add('active');
                        likeIcon.classList.replace('bi-hand-thumbs-up', 'bi-hand-thumbs-up-fill');
                    } else {
                        likeButton.classList.remove('active');
                        likeIcon.classList.replace('bi-hand-thumbs-up-fill', 'bi-hand-thumbs-up');
                    }
                }
            }
            likeButton.disabled = false; // Włącz przycisk z powrotem
        }
    });

    // Osobny listener dla wysyłania formularzy komentarzy
    feedContainer.addEventListener('submit', async (e) => {
        if (e.target.classList.contains('comment-form')) {
            e.preventDefault();
            const form = e.target;
            const input = form.querySelector('.comment-input');
            const button = form.querySelector('button[type="submit"]');
            const { ownerId, workoutId } = form.dataset;

            const commentText = input.value.trim();
            if (!commentText) return; // Nie wysyłaj pustego komentarza
            
            button.disabled = true;
            // ZMIANA: Przekazujemy 'comment' jako subAction
            const result = await handleInteraction(ownerId, workoutId, 'comment', commentText);

            if (result.success && result.newComment) {
                const commentsContainer = form.closest('.card-footer').querySelector('.comments-container');
                
                // Usuń wiadomość "Brak komentarzy", jeśli istnieje
                const noCommentsMsg = commentsContainer.querySelector('.text-muted');
                if (noCommentsMsg) {
                    noCommentsMsg.remove();
                }

                // Dodaj nowy komentarz do widoku
                commentsContainer.appendChild(createCommentElement(result.newComment));
                input.value = ''; // Wyczyść pole tekstowe
                
                // Zaktualizuj licznik komentarzy w głównym przycisku
                const commentCounter = form.closest('.card').querySelector('.comment-counter');
                if (commentCounter) {
                    commentCounter.textContent = parseInt(commentCounter.textContent, 10) + 1;
                }
            }
            button.disabled = false;
        }
    });
}