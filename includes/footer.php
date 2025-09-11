    </main>

    <!-- ISTNIEJĄCY MODAL INFORMACJI O ĆWICZENIU (bez zmian) -->
    <div class="modal fade" id="exerciseInfoModal" tabindex="-1" aria-labelledby="exerciseInfoModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exerciseInfoModalLabel">Informacje o ćwiczeniu</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <h6 class="text-muted">Opis</h6>
            <div id="exercise-description-content" class="mb-4"></div>
            <div id="exercise-howto-wrapper">
                <h6 class="text-muted border-top pt-3">Jak wykonać?</h6>
                <div id="exercise-howto-content"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================================= -->
    <!-- === NOWY MODAL DO PLANOWANIA TRENINGU W KALENDARZU === -->
    <!-- ========================================================= -->
    <div class="modal fade" id="scheduleEventModal" tabindex="-1" aria-labelledby="scheduleEventModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="scheduleEventModalLabel">Zaplanuj Trening na <span id="modalDate"></span></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form id="scheduleEventForm">
                <input type="hidden" id="eventDate" name="date">
                <input type="hidden" id="targetUserId" name="targetUserId">

                <div class="mb-3">
                    <label for="eventTitle" class="form-label">Tytuł wydarzenia</label>
                    <input type="text" class="form-control" id="eventTitle" name="title" placeholder="np. Trening nóg" required>
                </div>

                <div class="mb-3">
                    <label for="eventPlanId" class="form-label">Wybierz plan (opcjonalne)</label>
                    <select class="form-select" id="eventPlanId" name="planId">
                        <option value="adhoc" selected>Trening Ad-Hoc (bez planu)</option>
                        <!-- Opcje planów zostaną wstawione dynamicznie przez JS -->
                    </select>
                </div>

                <!-- Opcje widoczne tylko dla trenera -->
                <div id="coachOptions" style="display: none;">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="isCoachSession" name="isCoachSession">
                        <label class="form-check-label" for="isCoachSession">Oznacz jako wspólny trening z trenerem</label>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Kolor wydarzenia</label>
                    <div class="d-flex gap-2">
                        <input type="radio" class="btn-check" name="eventColor" id="color-primary" value="var(--bs-primary)" checked>
                        <label class="btn btn-outline-primary" for="color-primary">Niebieski</label>

                        <input type="radio" class="btn-check" name="eventColor" id="color-purple" value="#6f42c1">
                        <label class="btn" for="color-purple" style="--bs-btn-color: #6f42c1; --bs-btn-border-color: #6f42c1; --bs-btn-hover-bg: #6f42c1; --bs-btn-hover-border-color: #6f42c1; --bs-btn-active-bg: #6f42c1;">Fioletowy</label>

                         <input type="radio" class="btn-check" name="eventColor" id="color-warning" value="var(--bs-warning)">
                        <label class="btn btn-outline-warning" for="color-warning">Żółty</label>
                    </div>
                </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-danger me-auto" id="deleteEventBtn" style="display: none;">Usuń</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
            <button type="button" class="btn btn-primary" id="saveEventBtn">Zapisz</button>
          </div>
        </div>
      </div>
    </div>


    <footer class="container text-center text-muted py-4 mt-auto">
        <small>
            <?php
                if (!isset($themeConfig)) {
                    require_once 'includes/theme_functions.php';
                    $themeConfig = get_theme_config();
                }
                echo htmlspecialchars(str_replace('{rok}', date('Y'), $themeConfig['footerText']));
            ?>
        </small>
    </footer>

    <!-- SKRYPTY BOOTSTRAP I APLIKACJI -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- NASZ SKRYPT APLIKACJI JS (ZAWSZE NA KOŃCU) -->
    <script src="assets/js/app.js" type="module"></script>
</body>
</html>