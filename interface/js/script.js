document.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.alert');
    setTimeout(() => {
        alerts.forEach(alert => alert.remove());
    }, 5000); // Удаление через 5 секунд
});
