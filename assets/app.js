// Mobile menu: show and hide the nav when the menu button is tapped
const menuBtn = document.getElementById('menuBtn');
const mainNav = document.getElementById('mainNav');
if (menuBtn && mainNav) {
    menuBtn.addEventListener('click', () => mainNav.classList.toggle('open'));
}

// Ask before submitting any form that has a data-confirm message (deletes, payments)
document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});

// Fade out success messages after 4 seconds
document.querySelectorAll('.alert-success').forEach((alert) => {
    setTimeout(() => {
        alert.style.transition = 'opacity .5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    }, 4000);
});

// Plate fields: turn letters into capitals while typing
document.querySelectorAll('input.upper').forEach((input) => {
    input.addEventListener('input', () => {
        const pos = input.selectionStart;
        input.value = input.value.toUpperCase();
        input.setSelectionRange(pos, pos);
    });
});
