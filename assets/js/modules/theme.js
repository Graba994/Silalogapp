export function handleThemeToggle() {
    const themeToggles = document.querySelectorAll('.theme-toggle');
    if (themeToggles.length === 0) return;

    const updateTogglesState = (theme) => {
        themeToggles.forEach(toggle => {
            if (toggle.type === 'checkbox') {
                toggle.checked = (theme === 'dark');
            } else {
                const icon = toggle.querySelector('i');
                const text = toggle.querySelector('span');
                if (icon && text) {
                    if (theme === 'dark') {
                        icon.classList.replace('bi-moon-stars-fill', 'bi-sun-fill');
                        text.textContent = 'Tryb jasny';
                    } else {
                        icon.classList.replace('bi-sun-fill', 'bi-moon-stars-fill');
                        text.textContent = 'Tryb ciemny';
                    }
                }
            }
        });
    };

    updateTogglesState(document.documentElement.getAttribute('data-bs-theme'));

    themeToggles.forEach(toggle => {
        const handler = (e) => {
            const isChecked = e.currentTarget.type === 'checkbox' ? e.currentTarget.checked : document.documentElement.getAttribute('data-bs-theme') !== 'dark';
            const newTheme = isChecked ? 'dark' : 'light';
            document.documentElement.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateTogglesState(newTheme);
        };

        if (toggle.type === 'checkbox') {
            toggle.addEventListener('change', handler);
        } else {
            toggle.addEventListener('click', handler);
        }
    });
}